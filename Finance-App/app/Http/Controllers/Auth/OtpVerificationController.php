<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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
            'emailTertutup' => $this->emailTertutup($user->email),
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
            $this->otp->kirim($user);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal mengirim kode OTP. Silakan coba lagi beberapa saat lagi.');
        }

        return back()->with('status', 'Kode OTP baru telah dikirim ke ' . $this->emailTertutup($user->email) . '.');
    }

    private function userFromSession(Request $request): ?User
    {
        $id = $request->session()->get('otp_user_id');

        return $id ? User::find($id) : null;
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
