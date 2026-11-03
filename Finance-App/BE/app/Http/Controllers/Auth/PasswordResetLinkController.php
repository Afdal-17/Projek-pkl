<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $cache = Cache::store(app()->environment('testing') ? 'array' : 'file');
        $emailKey = hash('sha256', strtolower((string) $request->input('email')));
        $throttleKey = 'password-reset:throttle:'.$emailKey;

        // Respons sukses yang sama persis untuk semua alamat email — terdaftar
        // maupun tidak — agar keberadaan akun tidak bisa ditebak dari isi
        // respons (mencegah user enumeration).
        $sukses = fn (): RedirectResponse => back()
            ->withInput($request->only('email'))
            ->with('status', __('passwords.sent'));

        // Throttle diterapkan SEBELUM lookup dan juga untuk email yang tidak
        // terdaftar, sehingga pola respons tetap identik saat di-probe.
        if ($cache->has($throttleKey)) {
            return $sukses();
        }

        $cache->put($throttleKey, true, now()->addSeconds(60));

        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            return $sukses();
        }

        $token = Str::random(64);
        $cache->put('password-reset:token:'.$emailKey, Hash::make($token), now()->addMinutes(60));

        try {
            $user->sendPasswordResetNotification($token);
        } catch (Throwable $e) {
            // Kegagalan SMTP tidak boleh membocorkan status akun lewat pesan
            // error yang berbeda; laporkan ke log dan tetap balas sukses.
            report($e);
        }

        return $sukses();
    }
}
