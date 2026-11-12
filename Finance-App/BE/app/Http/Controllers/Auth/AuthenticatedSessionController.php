<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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
     * Handle an incoming API authentication request.
     */
    public function apiStore(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // Cek verifikasi email / admin untuk role user
        if ($user?->role === 'user' && $user?->email_verified_at === null) {
            $user->tokens()->delete();
            
            $pesan = ($user->otp_kode && ! $user->otp_verified_at)
                ? 'Akun Anda belum diverifikasi. Silakan masukkan kode OTP yang dikirim ke email Anda.'
                : 'Akun Anda belum diverifikasi oleh admin. Silakan hubungi admin.';

            throw ValidationException::withMessages([
                'email' => $pesan,
            ]);
        }

        // Cek status banned
        if ($user?->isBanned()) {
            if ($user->banned_at && $user->banned_at->addDays(30)->isPast()) {
                $user->update([
                    'status' => true,
                    'banned_at' => null,
                    'ban_reason' => null,
                ]);
            } else {
                $user->tokens()->delete();
                $banReason = $user->ban_reason ?: 'Melanggar ketentuan layanan';
                throw ValidationException::withMessages([
                    'email' => 'Akun Anda ditangguhkan: '.$banReason,
                ]);
            }
        }

        // Akun dinonaktifkan oleh admin (status=false) — tidak boleh login via mobile
        if ($user?->status === false) {
            $user->tokens()->delete();
            throw ValidationException::withMessages([
                'email' => 'Akun Anda tidak aktif. Silakan hubungi admin.',
            ]);
        }

        // Buat Sanctum token untuk autentikasi API frontend Vue/mobile
        $token = $user->createToken('vue-frontend-token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => $user
        ]);
    }

    /**
     * Revoke the token used by the API client (Vue/mobile).
     */
    public function apiLogout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout berhasil']);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // User dengan kode OTP pending wajib menyelesaikan verifikasi kode OTP
        if ($user?->role === 'user' && ($user?->email || $user?->nomor_telepon) && $user?->otp_kode && ! $user?->otp_verified_at) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $request->session()->put('otp_user_id', $user->id_user);

            return redirect()->route('otp.show');
        }

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
                    ]);
            }
        }

        // Akun dinonaktifkan oleh admin (status=false, bukan banned)
        if ($user?->status === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda tidak aktif. Silakan hubungi admin.',
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

        $request->user()?->tokens()->delete();

        return redirect('/');
    }
}
