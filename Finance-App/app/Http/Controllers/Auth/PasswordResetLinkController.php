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

        $user = User::where('email', $request->input('email'))->first();
        if (! $user) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __('passwords.user')]);
        }

        $cache = Cache::store(app()->environment('testing') ? 'array' : 'file');
        $emailKey = hash('sha256', strtolower($user->email));
        $throttleKey = 'password-reset:throttle:'.$emailKey;
        if ($cache->has($throttleKey)) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __('passwords.throttled')]);
        }

        $token = Str::random(64);
        $cache->put('password-reset:token:'.$emailKey, Hash::make($token), now()->addMinutes(60));
        $cache->put($throttleKey, true, now()->addSeconds(60));
        $user->sendPasswordResetNotification($token);

        return back()->with('status', __('passwords.sent'));
    }
}
