<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // User biasa wajib sudah diverifikasi admin
        if ($user?->role === 'user' && $user?->email_verified_at === null) {
            Auth::logout();
            return redirect()->route('login')->with('error', 'Akun Anda belum diverifikasi oleh admin.');
        }

        // Akun yang diblokir (banned) oleh admin
        if ($user?->isBanned()) {
            Auth::logout();
            return redirect()->route('login')->with('error', 'Akun Anda telah diblokir. Silakan hubungi admin.');
        }

        $isActive = $user?->status_aktif ?? $user?->status ?? true;

        if (!$isActive) {
            Auth::logout();
            return redirect()->route('login')->with('error', 'Akun Anda tidak aktif.');
        }

        if ($user?->role !== $role) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        if ($user->last_seen_at === null || $user->last_seen_at->lt(now()->subMinute())) {
            DB::table('user')->where('id_user', $user->id_user)->update(['last_seen_at' => now()]);
        }

        return $next($request);
    }
}