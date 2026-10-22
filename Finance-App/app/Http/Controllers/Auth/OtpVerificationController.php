<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FonnteService;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->autoUnbanIfExpired($user);

        // Akun yang diblokir tidak boleh mengakses halaman verifikasi OTP
        if ($user->isBanned()) {
            $request->session()->forget('otp_user_id');
            $banReason = $user->ban_reason ?: 'melanggar ketentuan layanan';
            return redirect()->route('login')
                ->with('error', "Akun Anda telah diblokir karena {$banReason}. Silakan hubungi admin.");
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

        $this->autoUnbanIfExpired($user);

        // Akun yang diblokir tidak boleh mendapatkan sesi autentikasi melalui OTP
        if ($user->isBanned()) {
            $request->session()->forget('otp_user_id');
            $banReason = $user->ban_reason ?: 'melanggar ketentuan layanan';
            return redirect()->route('login')
                ->with('error', "Akun Anda telah diblokir karena {$banReason}. Silakan hubungi admin.");
        }

        // Akun yang sudah pernah aktif (otp_verified_at ada) tetapi kini dinonaktifkan
        // admin tidak boleh login kembali via jalur OTP.
        if ($user->otp_verified_at && ! ($user->status ?? true)) {
            $request->session()->forget('otp_user_id');
            return redirect()->route('login')
                ->with('error', 'Akun Anda sedang tidak aktif. Silakan hubungi admin.');
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

        $this->autoUnbanIfExpired($user);

        if ($user->isBanned()) {
            $request->session()->forget('otp_user_id');
            $banReason = $user->ban_reason ?: 'melanggar ketentuan layanan';
            return redirect()->route('login')
                ->with('error', "Akun Anda telah diblokir karena {$banReason}. Silakan hubungi admin.");
        }

        if ($user->otp_verified_at && ! ($user->status ?? true)) {
            $request->session()->forget('otp_user_id');
            return redirect()->route('login')
                ->with('error', 'Akun Anda sedang tidak aktif. Silakan hubungi admin.');
        }

        if ($user->otp_verified_at) {
            $request->session()->forget('otp_user_id');

            return redirect()->route('login')->with('status', 'Email Anda sudah terverifikasi.');
        }

        // Batas per-akun: mencegah spam pesan ke satu nomor/email tujuan meski
        // pelaku memakai banyak IP (throttle di route hanya berbasis IP).
        $kunciRate = 'otp-resend:'.$user->id_user;
        if (RateLimiter::tooManyAttempts($kunciRate, 6)) {
            $detik = RateLimiter::availableIn($kunciRate);

            return back()->with('error', "Terlalu banyak permintaan kode. Silakan coba lagi dalam {$detik} detik.");
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

            RateLimiter::hit($kunciRate, 900); // jendela 15 menit
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

    private function autoUnbanIfExpired(User $user): void
    {
        if ($user->isBanned() && $user->banned_at->addDays(30)->isPast()) {
            $user->update([
                'status' => true,
                'banned_at' => null,
                'ban_reason' => null,
            ]);
        }
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
