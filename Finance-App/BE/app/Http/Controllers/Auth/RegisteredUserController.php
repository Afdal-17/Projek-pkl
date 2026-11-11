<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FonnteService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * API Endpoint untuk registrasi user via email atau nomor telepon dari Mobile / Frontend.
     */
    public function apiStore(Request $request): JsonResponse
    {
        $request->merge(['nama' => $request->input('nama', $request->input('name'))]);
        $phoneInput = $request->input('phone', $request->input('nomor_telepon'));

        if (! empty($phoneInput)) {
            $cleanPhone = preg_replace('/[^\d+]/', '', (string) $phoneInput);
            $request->merge(['nomor_telepon' => $cleanPhone]);

            $request->validate([
                'nama' => ['required', 'string', 'max:255'],
                'nomor_telepon' => ['required', 'string', 'max:32', 'unique:'.User::class.',nomor_telepon'],
                'email' => ['nullable', 'string', 'lowercase', 'max:255', 'unique:'.User::class.',email'],
                'password' => ['required', 'string', Rules\Password::defaults()],
            ]);

            $user = User::create([
                'nama' => $request->nama,
                'nomor_telepon' => $cleanPhone,
                'email' => $request->filled('email') ? $request->email : null,
                'password' => Hash::make($request->password),
                'role' => 'user',
                'status' => false,
                'email_verified_at' => null,
            ]);

            if ($user->email) {
                try {
                    app(OtpService::class)->kirim($user);
                } catch (Throwable $e) {
                    report($e);
                }
            } elseif (app(FonnteService::class)->aktif()) {
                try {
                    app(OtpService::class)->kirimWhatsapp($user);
                } catch (Throwable $e) {
                    report($e);
                }
            }

            return response()->json([
                'message' => 'Registrasi berhasil. Kode OTP telah dikirim.',
                'user_id' => $user->id_user,
                'nomor_telepon' => $user->nomor_telepon,
            ], 201);
        }

        $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                app()->environment('testing') ? 'email' : 'email:rfc,dns',
                'max:255',
                'unique:'.User::class,
                function ($attribute, $value, $fail) {
                    if (app()->environment('testing')) {
                        return;
                    }
                    $domain = substr(strrchr($value, "@"), 1);
                    if ($domain && !checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
                        $fail('Domain email tidak valid atau tidak ditemukan di internet.');
                    }
                },
            ],
            'password' => ['required', 'string', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'nama' => $request->nama,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'status' => false,
            'email_verified_at' => null,
        ]);

        try {
            app(OtpService::class)->kirim($user);
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => 'Registrasi berhasil. Kode OTP telah dikirim ke email Anda.',
            'user_id' => $user->id_user,
            'email' => $user->email,
        ], 201);
    }

    /**
     * API Endpoint untuk verifikasi kode OTP user dari Mobile / Frontend.
     */
    public function apiVerifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id_user'],
            'kode' => ['required', 'string', 'digits:6'],
        ]);

        $user = User::where('id_user', $request->user_id)->first();

        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        if ($user->email_verified_at !== null) {
            return response()->json(['message' => 'Email sudah terverifikasi sebelumnya.'], 400);
        }

        if (blank($user->otp_kode) || ! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
            return response()->json(['message' => 'Kode OTP telah kedaluwarsa. Silakan minta kode baru.'], 422);
        }

        if ($user->otp_attempts >= OtpService::MAKS_PERCOBAAN) {
            return response()->json(['message' => 'Terlalu banyak percobaan salah. Silakan minta kode baru.'], 422);
        }

        if (! Hash::check($request->kode, $user->otp_kode)) {
            $user->increment('otp_attempts');
            $sisa = max(0, OtpService::MAKS_PERCOBAAN - $user->otp_attempts);
            return response()->json([
                'message' => "Kode OTP salah. Sisa percobaan {$sisa} kali."
            ], 422);
        }

        // OTP benar -> Verifikasi sukses
        $user->forceFill([
            'email_verified_at' => now(),
            'status' => true,
            'otp_kode' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
        ])->save();

        // Buat Sanctum token agar langsung bisa login otomatis setelah verifikasi
        $token = $user->createToken('mobile-register-token')->plainTextToken;

        return response()->json([
            'message' => 'Verifikasi OTP berhasil. Akun Anda aktif.',
            'token' => $token,
            'user' => $user
        ], 200);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['nama' => $request->input('nama', $request->input('name'))]);
        $phoneInput = $request->input('phone', $request->input('nomor_telepon'));

        if (! empty($phoneInput)) {
            $cleanPhone = preg_replace('/[^\d+]/', '', (string) $phoneInput);
            $request->merge(['nomor_telepon' => $cleanPhone]);

            $request->validate([
                'nama' => ['required', 'string', 'max:255'],
                'nomor_telepon' => ['required', 'string', 'max:32', 'unique:'.User::class.',nomor_telepon'],
                'email' => [
                    'nullable',
                    'string',
                    'lowercase',
                    app()->environment('testing') ? 'email' : 'email:rfc,dns',
                    'max:255',
                    'unique:'.User::class.',email',
                    function ($attribute, $value, $fail) {
                        if (!empty($value)) {
                            $domain = substr(strrchr($value, "@"), 1);
                            if ($domain && !checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
                                $fail('Domain email tidak valid atau tidak ditemukan di internet.');
                            }
                        }
                    },
                ],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]);

            $user = User::create([
                'nama' => $request->nama,
                'nomor_telepon' => $cleanPhone,
                'email' => $request->filled('email') ? $request->email : null,
                'password' => Hash::make($request->password),
                'role' => 'user',
                'status' => false,
                'email_verified_at' => null,
            ]);

            if ($user->email) {
                return $this->kirimOtpDanArahkan($request, $user);
            }

            // Register via nomor telepon: kirim kode OTP ke WhatsApp (Fonnte).
            // Akun langsung aktif setelah kode benar — tanpa menunggu admin.
            if (app(FonnteService::class)->aktif()) {
                try {
                    app(OtpService::class)->kirimWhatsapp($user);
                    $request->session()->put('otp_user_id', $user->id_user);

                    return redirect()->route('otp.show');
                } catch (Throwable $e) {
                    report($e);

                    // Gagal kirim → bersihkan kode OTP agar alur fallback admin
                    // tidak terblokir login-gate (kode pending).
                    $user->forceFill([
                        'otp_kode' => null,
                        'otp_expires_at' => null,
                        'otp_attempts' => 0,
                    ])->save();

                    return redirect()->route('login')->with('status', 'Akun berhasil dibuat, namun gagal mengirim kode OTP ke WhatsApp ' . $cleanPhone . '. Menunggu verifikasi dari admin untuk dapat login.');
                }
            }

            return redirect()->route('login')->with('status', 'Akun dengan nomor telepon ' . $cleanPhone . ' berhasil dibuat. Menunggu verifikasi dari admin untuk dapat login.');
        }

        $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                app()->environment('testing') ? 'email' : 'email:rfc,dns',
                'max:255',
                'unique:'.User::class,
                function ($attribute, $value, $fail) {
                    if (app()->environment('testing')) {
                        return;
                    }
                    $domain = substr(strrchr($value, "@"), 1);
                    if ($domain && !checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
                        $fail('Domain email tidak valid atau tidak ditemukan di internet.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'nama' => $request->nama,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'status' => false,
            'email_verified_at' => null,
        ]);

        return $this->kirimOtpDanArahkan($request, $user);
    }

    /**
     * Kirim kode OTP ke email user dan arahkan ke halaman verifikasi OTP.
     */
    private function kirimOtpDanArahkan(Request $request, User $user): RedirectResponse
    {
        try {
            app(OtpService::class)->kirim($user);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->with('status', 'Akun berhasil dibuat, namun gagal mengirim kode OTP ke email Anda. Silakan hubungi admin untuk verifikasi.');
        }

        $request->session()->put('otp_user_id', $user->id_user);

        return redirect()->route('otp.show');
    }
}
