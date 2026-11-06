<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        if (empty($clientId) || empty($clientSecret)) {
            return redirect()->route('login')->with('error', 'Google Sign-In belum dikonfigurasi. Silakan isi GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET di file .env.');
        }

        $mode = $request->query('mode', 'login');
        session(['google_auth_mode' => $mode]);

        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google and authenticate.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            return redirect()->route('login')->with('error', 'Gagal masuk dengan akun Google: ' . $e->getMessage());
        }

        $mode = session()->pull('google_auth_mode', 'login');

        $email = $googleUser->getEmail();
        $googleId = $googleUser->getId();

        $user = User::where('google_id', $googleId)->first();

        if (! $user && ! empty($email)) {
            $user = User::where('email', $email)->first();
        }

        // Jika user mencoba LOGIN dengan Google tetapi belum memiliki akun
        if ($mode === 'login' && ! $user) {
            return redirect()->route('register')->with('error', 'Akun Google Anda belum terdaftar. Silakan lakukan pendaftaran (register) terlebih dahulu.');
        }

        // Jika user melakukan REGISTER dengan Google dan belum ada akun
        if (! $user) {
            $user = User::create([
                'nama' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google User',
                'email' => $email,
                'google_id' => $googleId,
                'password' => Hash::make(Str::random(32)),
                'role' => 'user',
                'status' => false,
                'email_verified_at' => null,
                'mata_uang' => 'IDR',
            ]);

            if (! empty($email)) {
                // Register via Google: kirim kode OTP ke email, lalu ke halaman verifikasi OTP
                try {
                    app(OtpService::class)->kirim($user);
                    session()->put('otp_user_id', $user->id_user);

                    return redirect()->route('otp.show');
                } catch (Throwable $e) {
                    report($e);

                    return redirect()->route('login')->with('error', 'Akun Google berhasil dibuat, namun gagal mengirim kode OTP ke email Anda. Silakan masuk kembali dengan akun Google Anda.');
                }
            }

            // Fallback: email dari Google tidak tersedia → verifikasi langsung
            $user->forceFill([
                'email_verified_at' => now(),
                'status' => true,
                'otp_verified_at' => now(),
            ])->save();
        } else {
            // Jika akun sudah ada, perbarui google_id dan verifikasi jika belum
            if (! $user->google_id) {
                $user->google_id = $googleId;
            }
            if (! $user->email_verified_at) {
                $user->email_verified_at = now();
                $user->status = true;
            }
            if (! $user->otp_verified_at) {
                $user->otp_verified_at = now();
            }
            $user->save();
        }

        // Akun yang diblokir (banned) oleh admin
        if ($user->isBanned()) {
            if ($user->banned_at && $user->banned_at->addDays(30)->isPast()) {
                $user->update([
                    'status' => true,
                    'banned_at' => null,
                    'ban_reason' => null,
                ]);
            } else {
                $banReason = $user->ban_reason ?: 'Melanggar ketentuan layanan';
                $daysRemaining = $user->banned_at ? (int) floor(max(0, 30 - $user->banned_at->diffInDays(now()))) : 30;

                return redirect()->route('login')
                    ->with('banned_modal', [
                        'reason' => $banReason,
                        'days_remaining' => $daysRemaining,
                    ])
                    ->with('error', "Akun Anda telah diblokir selama 30 hari karena {$banReason}. Sisa {$daysRemaining} hari lagi.")
                    ->with('show_contact_admin', true);
            }
        }

        if (! $user->status) {
            return redirect()->route('login')->with('error', 'Akun Anda sedang tidak aktif. Silakan hubungi admin.');
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        if ($user->isAdmin()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('user.dashboard'));
    }
}
