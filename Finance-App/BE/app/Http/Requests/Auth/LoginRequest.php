<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifier = trim((string) ($this->input('phone') ?: $this->input('email')));
        $password = (string) $this->input('password');

        if (empty($identifier)) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => 'Silakan masukkan email atau nomor telepon.',
            ]);
        }

        $user = null;

        // Cek jika identifier memiliki karakter '@' -> login dengan Email
        if (str_contains($identifier, '@')) {
            $user = User::where('email', strtolower($identifier))->first();
        } else {
            // Login dengan Nomor Telepon
            $cleanPhone = preg_replace('/[^\d+]/', '', $identifier);
            $digitsOnly = preg_replace('/\D/', '', $identifier);

            $user = User::where('nomor_telepon', $identifier)
                ->orWhere('nomor_telepon', $cleanPhone)
                ->when(str_starts_with($digitsOnly, '62'), function ($q) use ($digitsOnly) {
                    $q->orWhere('nomor_telepon', '0' . substr($digitsOnly, 2));
                })
                ->when(str_starts_with($digitsOnly, '0'), function ($q) use ($digitsOnly) {
                    $q->orWhere('nomor_telepon', '+62' . substr($digitsOnly, 1))
                      ->orWhere('nomor_telepon', '62' . substr($digitsOnly, 1));
                })
                ->first();
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($this->throttleKey());

            $field = $this->filled('phone') ? 'phone' : 'email';
            throw ValidationException::withMessages([
                $field => trans('auth.failed'),
            ]);
        }

        Auth::login($user, $this->boolean('remember'));
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());
        $field = $this->filled('phone') ? 'phone' : 'email';

        throw ValidationException::withMessages([
            $field => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $identifier = $this->input('phone') ?: $this->input('email') ?: '';

        return Str::transliterate(Str::lower($identifier).'|'.$this->ip());
    }
}
