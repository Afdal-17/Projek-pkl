<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // User role harus diverifikasi admin terlebih dahulu
        if ($user?->role === 'user' && $user?->email_verified_at === null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda belum diverifikasi oleh admin. Silakan hubungi admin.',
            ]);
        }

        // Akun yang diblokir (banned) oleh admin
        if ($user?->isBanned()) {
            // Auto-unban jika sudah lebih dari 30 hari
            if ($user->banned_at && $user->banned_at->addDays(30)->isPast()) {
                $user->update([
                    'status' => true,
                    'banned_at' => null,
                    'ban_reason' => null,
                ]);
            } else {
                $banReason = $user->ban_reason ?: 'Melanggar ketentuan layanan';
                $daysRemaining = $user->banned_at ? (int) floor(max(0, 30 - $user->banned_at->diffInDays(now()))) : 30;

                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('banned_modal', [
                        'reason' => $banReason,
                        'days_remaining' => $daysRemaining,
                    ])
                    ->with('error', "Akun Anda telah diblokir selama 30 hari karena {$banReason}. Sisa {$daysRemaining} hari lagi.")
                    ->with('show_contact_admin', true);
            }
        }

        $isActive = $user?->status_aktif ?? $user?->status ?? true;

        if (!$isActive) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda sedang tidak aktif. Silakan hubungi admin.',
            ]);
        }

        if ($user?->role === 'admin') {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        return redirect()->intended(route('user.dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
