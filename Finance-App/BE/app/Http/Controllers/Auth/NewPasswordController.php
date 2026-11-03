<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
            $user = User::where('email', $request->input('email'))->first();
            $cache = Cache::store(app()->environment('testing') ? 'array' : 'file');
            $emailKey = hash('sha256', strtolower((string) $request->input('email')));
            $tokenKey = 'password-reset:token:'.$emailKey;
            $hashedToken = $cache->get($tokenKey);

            if (! $user || ! is_string($hashedToken) || ! Hash::check($request->input('token'), $hashedToken)) {
                return back()->withInput($request->only('email'))->withErrors(['email' => __('passwords.token')]);
            }

            $user->forceFill([
                'password' => Hash::make($request->input('password')),
                'remember_token' => Str::random(60),
            ])->save();
            $cache->forget($tokenKey);
            $cache->forget('password-reset:throttle:'.$emailKey);
            event(new PasswordReset($user));

            return redirect()->route('login')->with('status', __('passwords.reset'));
    }
}
