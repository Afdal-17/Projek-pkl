<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FonnteService;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Throwable;

class PhonePasswordResetController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public function requestCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9\s().-]+$/'],
        ]);
        $phone = $this->normalizePhone($validated['phone']);

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            throw ValidationException::withMessages([
                'phone' => 'Masukkan nomor telepon yang valid.',
            ]);
        }

        $cache = $this->cache();
        $phoneKey = $this->phoneKey($phone);
        $throttleKey = 'phone-password-reset:throttle:'.$phoneKey;
        $sukses = fn (): JsonResponse => response()->json([
            'message' => 'Jika nomor terdaftar dan dapat dipulihkan, kode pemulihan akan dikirim melalui WhatsApp.',
        ]);

        if (! $cache->add($throttleKey, true, now()->addSeconds(60))) {
            return $sukses();
        }

        $user = $this->findUserByPhone($validated['phone']);

        if (! $user || ! $user->otp_verified_at || $user->usesGoogleSignIn()) {
            return $sukses();
        }

        if (! app(FonnteService::class)->aktif()) {
            Log::error('Phone password reset is unavailable because Fonnte is not configured.', [
                'user_id' => $user->id_user,
            ]);

            return $sukses();
        }

        $otp = (string) random_int(100000, 999999);
        $otpKey = 'phone-password-reset:otp:'.$phoneKey;
        $attemptsKey = 'phone-password-reset:attempts:'.$phoneKey;

        $cache->put($otpKey, [
            'user_id' => $user->id_user,
            'hash' => Hash::make($otp),
        ], now()->addMinutes(self::OTP_TTL_MINUTES));
        $cache->put($attemptsKey, 0, now()->addMinutes(self::OTP_TTL_MINUTES));

        try {
            app(FonnteService::class)->kirim(
                (string) $user->nomor_telepon,
                implode("\n", [
                    'Pemulihan Password '.config('app.name', 'Finance App'),
                    '',
                    "Halo {$user->nama}, gunakan kode berikut untuk memulihkan password akun Anda:",
                    '',
                    $otp,
                    '',
                    'Kode berlaku selama '.self::OTP_TTL_MINUTES.' menit. Jangan bagikan kode ini kepada siapa pun.',
                    'Jika Anda tidak meminta pemulihan password, abaikan pesan ini.',
                ]),
            );
        } catch (Throwable $e) {
            $cache->forget($otpKey);
            $cache->forget($attemptsKey);
            report($e);
        }

        return $sukses();
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9\s().-]+$/'],
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $user = $this->findUserByPhone($validated['phone']);
        $cache = $this->cache();
        $phoneKey = $this->phoneKey($this->normalizePhone($validated['phone']));
        $otpKey = 'phone-password-reset:otp:'.$phoneKey;
        $attemptsKey = 'phone-password-reset:attempts:'.$phoneKey;
        $otpRecord = $cache->get($otpKey);
        $hashedOtp = is_array($otpRecord) ? ($otpRecord['hash'] ?? null) : null;
        $attempts = (int) $cache->get($attemptsKey, 0);

        if (
            ! $user
            || ! $user->otp_verified_at
            || $user->usesGoogleSignIn()
            || (int) ($otpRecord['user_id'] ?? 0) !== $user->id_user
            || ! is_string($hashedOtp)
            || $attempts >= self::MAX_ATTEMPTS
        ) {
            return $this->invalidCode();
        }

        if (! Hash::check($validated['code'], $hashedOtp)) {
            $attempts++;
            $cache->put($attemptsKey, $attempts, now()->addMinutes(self::OTP_TTL_MINUTES));

            if ($attempts >= self::MAX_ATTEMPTS) {
                $cache->forget($otpKey);
            }

            return $this->invalidCode();
        }

        $resetToken = Str::random(64);
        $cache->put(
            'phone-password-reset:token:'.$phoneKey,
            [
                'user_id' => $user->id_user,
                'hash' => Hash::make($resetToken),
            ],
            now()->addMinutes(self::OTP_TTL_MINUTES),
        );
        $cache->forget($otpKey);
        $cache->forget($attemptsKey);

        return response()->json([
            'message' => 'Nomor berhasil diverifikasi. Silakan buat password baru.',
            'reset_token' => $resetToken,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9\s().-]+$/'],
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $this->findUserByPhone($validated['phone']);
        $cache = $this->cache();
        $phoneKey = $this->phoneKey($this->normalizePhone($validated['phone']));
        $tokenKey = 'phone-password-reset:token:'.$phoneKey;
        $tokenRecord = $cache->get($tokenKey);
        $hashedToken = is_array($tokenRecord) ? ($tokenRecord['hash'] ?? null) : null;

        if (
            ! $user
            || ! $user->otp_verified_at
            || $user->usesGoogleSignIn()
            || (int) ($tokenRecord['user_id'] ?? 0) !== $user->id_user
            || ! is_string($hashedToken)
            || ! Hash::check($validated['reset_token'], $hashedToken)
        ) {
            return response()->json([
                'message' => 'Token pemulihan tidak valid atau telah kedaluwarsa.',
                'errors' => ['reset_token' => ['Token pemulihan tidak valid atau telah kedaluwarsa.']],
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        $cache->forget($tokenKey);
        $cache->forget('phone-password-reset:throttle:'.$phoneKey);

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
        ]);
    }

    private function findUserByPhone(string $phone): ?User
    {
        $clean = $this->normalizePhone($phone);
        $digits = preg_replace('/\D+/', '', $clean);
        $variants = [$clean, $digits];

        if (str_starts_with($digits, '62')) {
            $variants[] = '0'.substr($digits, 2);
            $variants[] = '+'.$digits;
        } elseif (str_starts_with($digits, '0')) {
            $variants[] = '62'.substr($digits, 1);
            $variants[] = '+62'.substr($digits, 1);
        }

        $users = User::whereIn('nomor_telepon', array_unique($variants))
            ->limit(2)
            ->get();

        return $users->count() === 1 ? $users->first() : null;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', trim($phone));
    }

    private function phoneKey(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return hash('sha256', $digits);
    }

    private function cache(): Repository
    {
        return Cache::store(app()->environment('testing') ? 'array' : 'file');
    }

    private function invalidCode(): JsonResponse
    {
        return response()->json([
            'message' => 'Kode pemulihan tidak valid, kedaluwarsa, atau jumlah percobaan telah habis.',
            'errors' => ['code' => ['Kode pemulihan tidak valid, kedaluwarsa, atau jumlah percobaan telah habis.']],
        ], 422);
    }
}
