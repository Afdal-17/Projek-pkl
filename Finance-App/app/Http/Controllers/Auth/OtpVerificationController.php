<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FonnteService;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class OtpVerificationController extends Controller
{
    public function __construct(private OtpService $otp) {}

    /**
     * Tampilkan halaman input kode OTP (diakses guest via session penanda pendaftaran).
     */
    public function show(Request $request): RedirectResponse|View
    {
        $user = $this->userFromSession($request);

        if (! $user) {
            return redirect()->route('login')->with('error', 'Silakan masuk terlebih dahulu.');
        }

        if ($user->otp_verified_at) {
            $request->session()->forget('otp_user_id');

            return redirect()->route('user.dashboard');
        }

        return view('auth.otp', [
            'kanal' => $user->email ? 'email' : 'whatsapp',
            'tujuanTertutup' => $this->tujuanTertutup($user),
        ]);
    }

    /**
     * Konfirmasi kode OTP yang diinput user.
     */
    public function verify(Request $request): RedirectResponse
    {
        $user = $this->userFromSession($request);

        if (! $user) {
            return redirect()->route('login')->with('error', 'Silakan masuk terlebih dahulu.');
        }

        if ($user->otp_verified_at) {
            $request->session()->forget('otp_user_id');

            return redirect()->route('login')->with('status', 'Email Anda sudah terverifikasi.');
        }

        $validated = $request->validate([
            'kode' => ['required', 'string', 'digits:6'],
        ], [], ['kode' => 'verification code']);

        if ($this->otp->sedangMenunggu($user) && $user->otp_expires_at && $user->otp_expires_at->isPast()) {
            return back()->withErrors(['kode' => 'Kode OTP telah kedaluwarsa. Silakan minta kode baru.']);
        }

        if ($user->otp_attempts >= OtpService::MAKS_PERCOBAAN) {
            return back()->withErrors(['kode' => 'Terlalu banyak percobaan. Silakan minta kode OTP baru.']);
        }

        if ($this->otp->konfirmasi($user, $validated['kode'])) {
            // Kode benar = email terverifikasi → akun aktif, langsung masuk dashboard
            $user->forceFill([
                'email_verified_at' => $user->email_verified_at ?? now(),
                'status' => true,
            ])->save();

            $request->session()->forget('otp_user_id');

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route('user.dashboard');
        }

        $sisa = max(0, OtpService::MAKS_PERCOBAAN - $user->otp_attempts);

        return back()->withErrors([
            'kode' => $sisa > 0
                ? "Kode OTP salah. Sisa percobaan Anda {$sisa} kali."
                : 'Kode OTP salah. Jumlah percobaan habis, silakan minta kode baru.',
        ]);
    }

    /**
     * Kirim ulang kode OTP baru ke email user.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $this->userFromSession($request);

        if (! $user) {
            return redirect()->route('login')->with('error', 'Silakan masuk terlebih dahulu.');
        }

        if ($user->otp_verified_at) {
            $request->session()->forget('otp_user_id');

            return redirect()->route('login')->with('status', 'Email Anda sudah terverifikasi.');
        }

        try {
            if ($user->email) {
                $this->otp->kirim($user);
            } else {
                if (! app(FonnteService::class)->aktif()) {
                    return back()->with('error', 'Pengiriman OTP via WhatsApp sedang tidak tersedia. Silakan hubungi admin untuk verifikasi.');
                }

                $this->otp->kirimWhatsapp($user);
            }
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal mengirim kode OTP. Silakan coba lagi beberapa saat lagi.');
        }

        return back()->with('status', 'Kode OTP baru telah dikirim ke ' . $this->tujuanTertutup($user) . '.');
    }

    private function userFromSession(Request $request): ?User
    {
        $id = $request->session()->get('otp_user_id');

        return $id ? User::find($id) : null;
    }

    /**
     * Tujuan pengiriman kode OTP dalam bentuk tertutup:
     * ali***@domain.com untuk email, 0812*****890 (WhatsApp) untuk nomor telepon.
     */
    private function tujuanTertutup(User $user): string
    {
        if ($user->email) {
            return $this->emailTertutup($user->email);
        }

        return $this->nomorTertutup($user->nomor_telepon) . ' (WhatsApp)';
    }

    /**
     * Sembunyikan sebagian nomor telepon: 0812*****890
     */
    private function nomorTertutup(?string $nomor): string
    {
        $digits = preg_replace('/\D+/', '', (string) $nomor);

        if (strlen($digits) < 7) {
            return 'nomor Anda';
        }

        return substr($digits, 0, 4)
            . str_repeat('*', max(1, strlen($digits) - 7))
            . substr($digits, -3);
    }

    /**
     * Sembunyikan sebagian alamat email: ali***@domain.com
     */
    private function emailTertutup(?string $email): string
    {
        if (blank($email) || ! str_contains($email, '@')) {
            return 'email Anda';
        }

        [$local, $domain] = explode('@', $email, 2);

        $tutup = strlen($local) > 2
            ? substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2))
            : str_repeat('*', strlen($local));

        return $tutup . '@' . $domain;
    }
}
