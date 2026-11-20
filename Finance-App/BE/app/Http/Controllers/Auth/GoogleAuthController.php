<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use App\Services\OtpDeliveryException;
use App\Services\OtpService;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

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
            return redirect()->route('login')->with('error', 'Gagal masuk dengan akun Google: '.$e->getMessage());
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

    /**
     * Authenticate a mobile user with a verified Google ID token.
     */
    public function apiGoogleLogin(Request $request, GoogleIdTokenVerifier $verifier): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string', 'max:8192'],
            'mode' => ['required', 'in:login,register'],
        ]);

        try {
            $claims = $verifier->verify($validated['id_token']);
        } catch (ExpiredException $e) {
            return response()->json([
                'message' => 'Google ID token tidak valid atau sudah kedaluwarsa.',
            ], 401);
        } catch (BeforeValidException $e) {
            return response()->json([
                'message' => 'Google ID token tidak valid atau sudah kedaluwarsa.',
            ], 401);
        } catch (UnexpectedValueException|\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Google ID token tidak valid atau sudah kedaluwarsa.',
            ], 401);
        } catch (RuntimeException $e) {
            report($e);

            return response()->json([
                'message' => 'Layanan autentikasi Google sedang tidak tersedia.',
            ], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Autentikasi Google gagal diproses.',
            ], 500);
        }

        $email = strtolower(trim($claims['email']));
        $googleId = $claims['sub'];
        $nama = $claims['name'] ?? 'Google User';
        $nama = is_string($nama) && trim($nama) !== '' ? trim($nama) : 'Google User';
        $nama = mb_strlen($nama) > 255 ? mb_substr($nama, 0, 255) : $nama;
        $avatarPath = $claims['picture'] ?? null;
        if (! is_string($avatarPath)
            || filter_var($avatarPath, FILTER_VALIDATE_URL) === false
            || parse_url($avatarPath, PHP_URL_SCHEME) !== 'https') {
            $avatarPath = null;
        }

        $user = User::where('google_id', $googleId)->first();
        if (! $user) {
            $user = User::where('email', $email)->first();
        }

        if (! $user) {
            if ($validated['mode'] === 'login') {
                return response()->json([
                    'message' => 'Akun tidak ditemukan. Silakan melakukan registrasi terlebih dahulu.',
                ], 404);
            }

            try {
                $user = DB::transaction(function () use ($nama, $email, $googleId, $avatarPath): User {
                    return User::create([
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
                });
            } catch (QueryException $e) {
                if (! User::where('google_id', $googleId)->orWhere('email', $email)->exists()) {
                    throw $e;
                }

                return response()->json([
                    'message' => 'Akun dengan alamat email ini sudah terdaftar. Silakan masuk.',
                ], 409);
            }

            if ($user->google_id !== null && ! hash_equals($user->google_id, $googleId)) {
                return response()->json([
                    'message' => 'Akun ini sudah terhubung ke akun Google lain.',
                ], 409);
            }

            if ($user->google_id === null) {
                $user->google_id = $googleId;
                $user->save();
            }

            try {
                app(OtpService::class)->kirim($user);
            } catch (OtpDeliveryException $e) {
                report($e);

                return response()->json([
                    'message' => 'Akun dibuat, tetapi kode verifikasi gagal dikirim. Silakan minta kode baru.',
                    'user_id' => $user->id_user,
                    'email' => $user->email,
                ], 503);
            }

            return response()->json([
                'message' => 'Registrasi berhasil. Kode verifikasi telah dikirim ke email Anda.',
                'user_id' => $user->id_user,
                'email' => $user->email,
            ], 201);
        }

        if ($user->google_id !== null && ! hash_equals($user->google_id, $googleId)) {
            return response()->json([
                'message' => 'Akun ini sudah terhubung ke akun Google lain.',
            ], 409);
        }

        if ($user->google_id === null) {
            try {
                $user->google_id = $googleId;
                $user->save();
            } catch (QueryException $e) {
                if (User::where('google_id', $googleId)->exists()) {
                    return response()->json([
                        'message' => 'Akun Google sudah terhubung ke akun lain.',
                    ], 409);
                }

                throw $e;
            }
        }

        if ($avatarPath && ! $user->avatar_path) {
            $user->avatar_path = $avatarPath;
            $user->save();
        }

        if ($user->isBanned()) {
            if ($user->banned_at && $user->banned_at->addDays(30)->isPast()) {
                $user->update([
                    'status' => $user->email_verified_at !== null && $user->otp_verified_at !== null,
                    'banned_at' => null,
                    'ban_reason' => null,
                ]);
            } else {
                $banReason = $user->ban_reason ?: 'Melanggar ketentuan layanan';

                return response()->json([
                    'message' => 'Akun Anda ditangguhkan: '.$banReason,
                ], 403);
            }
        }

        if (! $user->email_verified_at || ! $user->otp_verified_at) {
            return response()->json([
                'message' => 'Akun belum menyelesaikan verifikasi email.',
                'user_id' => $user->id_user,
                'email' => $user->email,
            ], 409);
        }

        if (! $user->status) {
            return response()->json([
                'message' => 'Akun Anda sedang tidak aktif. Silakan hubungi admin.',
            ], 403);
        }

        $token = $user->createToken('mobile-google-token')->plainTextToken;

        return response()->json([
            'message' => 'Login Google berhasil',
            'token' => $token,
            'user' => $user->only([
                'id_user',
                'nama',
                'email',
                'google_id',
                'role',
                'status',
                'email_verified_at',
                'avatar_path',
                'mata_uang',
            ]),
        ]);
    }
}
