<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\OtpDeliveryException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
                'mata_uang' => 'USD',
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
            // Jika akun sudah ada, perbarui google_id jika belum terhubung
            if (! $user->google_id) {
                $user->google_id = $googleId;
                $user->save();
            }

            // Jika akun belum diverifikasi OTP, arahkan ke verifikasi OTP
            if (! $user->email_verified_at && ! $user->otp_verified_at) {
                if (blank($user->otp_kode) || ! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
                    try {
                        app(OtpService::class)->kirim($user);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
                session()->put('otp_user_id', $user->id_user);

                return redirect()->route('otp.show')->with('warning', 'Akun Anda belum diverifikasi. Silakan masukkan kode OTP yang dikirim ke email Anda.');
            }
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

    /**
     * API endpoint untuk login/register Google dari aplikasi Mobile (Flutter).
     * Klien (Flutter) mengirim data profil Google hasil sign-in di HP,
     * backend mengembalikan Sanctum Bearer Token dan data user.
     */
    public function apiGoogleLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'google_id' => 'required|string',
            'nama' => 'nullable|string',
            'avatar_path' => 'nullable|string',
            // mode = 'login'    -> akun wajib sudah terdaftar (akun terhapus ditolak)
            // mode = 'register' -> akun baru boleh dibuatkan otomatis
            'mode' => 'required|in:login,register',
        ]);

        $email = strtolower(trim($request->email));
        $googleId = $request->google_id;
        $nama = $request->nama ?: 'Google Sign-In User';
        $avatarPath = $request->avatar_path;
        $isRegister = $request->mode === 'register';

        // Cari user berdasarkan google_id atau email
        $user = User::where('google_id', $googleId)->orWhere('email', $email)->first();

        if (! $user) {
            // Mode LOGIN: akun harus sudah ada. Jika sudah dihapus admin,
            // tolak dan minta user mendaftar ulang — jangan dibuatkan diam-diam.
            if (! $isRegister) {
                return response()->json([
                    'message' => 'Akun tidak ditemukan. Silakan melakukan registrasi terlebih dahulu.',
                ], 404);
            }

            // Mode REGISTER: buatkan akun baru dalam status BELUM TERVERIFIKASI
            // dan kirimkan kode OTP ke Gmail user.
            try {
                $user = DB::transaction(function () use ($email, $googleId, $nama, $avatarPath) {
                    $newUser = User::create([
                        'nama' => $nama,
                        'email' => $email,
                        'google_id' => $googleId,
                        'password' => Hash::make(Str::random(32)),
                        'role' => 'user',
                        'status' => false,
                        'email_verified_at' => null,
                        'otp_verified_at' => null,
                        'avatar_path' => $avatarPath,
                        'mata_uang' => 'USD',
                    ]);

                    app(OtpService::class)->kirim($newUser);

                    return $newUser;
                });
            } catch (OtpDeliveryException $e) {
                report($e);

                return response()->json([
                    'message' => 'Gagal mengirim kode OTP email. Silakan coba lagi nanti.',
                ], 503);
            } catch (Throwable $e) {
                report($e);

                return response()->json([
                    'message' => 'Terjadi kesalahan saat memproses registrasi Google.',
                ], 500);
            }

            return response()->json([
                'message' => 'Registrasi Google berhasil. Silakan masukkan kode OTP yang dikirim ke email Anda.',
                'requires_otp' => true,
                'user_id' => $user->id_user,
                'email' => $user->email,
            ], 201);
        }

        // Perbarui data jika belum sinkron (jangan ubah status verifikasi)
        $updates = [];
        if (! $user->google_id) {
            $updates['google_id'] = $googleId;
        }
        if ($avatarPath && ! $user->avatar_path) {
            $updates['avatar_path'] = $avatarPath;
        }
        if (! empty($updates)) {
            $user->update($updates);
        }

        // Cek status banned
        if ($user->isBanned()) {
            if ($user->banned_at && $user->banned_at->addDays(30)->isPast()) {
                $user->update([
                    'status' => true,
                    'banned_at' => null,
                    'ban_reason' => null,
                ]);
            } else {
                $banReason = $user->ban_reason ?: 'Melanggar ketentuan layanan';
                throw ValidationException::withMessages([
                    'email' => 'Akun Anda ditangguhkan: '.$banReason,
                ]);
            }
        }

        // Jika user belum diverifikasi OTP:
        // JANGAN berikan token Sanctum. Arahkan kembali ke verifikasi OTP dan kirimkan ulang OTP jika diperlukan.
        if (! $user->email_verified_at && ! $user->otp_verified_at) {
            if (blank($user->otp_kode) || ! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
                try {
                    app(OtpService::class)->kirim($user);
                } catch (OtpDeliveryException $e) {
                    report($e);

                    return response()->json([
                        'message' => 'Gagal mengirim kode OTP email. Silakan coba lagi nanti.',
                    ], 503);
                }
            }

            return response()->json([
                'message' => 'Akun Google Anda belum diverifikasi. Silakan masukkan kode OTP yang dikirim ke email Anda.',
                'requires_otp' => true,
                'user_id' => $user->id_user,
                'email' => $user->email,
            ], 200);
        }

        // Jika user sudah terverifikasi tetapi akun dinonaktifkan
        if (! $user->status) {
            throw ValidationException::withMessages([
                'email' => 'Akun Anda sedang tidak aktif. Silakan hubungi admin.',
            ]);
        }

        // User terverifikasi dan aktif: Buat Sanctum token untuk mobile
        $token = $user->createToken('mobile-google-token')->plainTextToken;

        return response()->json([
            'message' => 'Login Google berhasil',
            'token' => $token,
            'user' => $user,
        ]);
    }
}
