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

        // Auto-unban jika sudah lebih dari 30 hari
        if ($user?->isBanned() && $user->banned_at->addDays(30)->isPast()) {
            $user->update([
                'status' => true,
                'banned_at' => null,
                'ban_reason' => null,
            ]);
            $user->refresh();
        }

        // Auto-inactive jika user tidak login selama 1 tahun (365 hari)
        if ($user?->status && $user?->role === 'user') {
            $lastActiveAt = $user->last_seen_at ?? $user->created_at;
            if ($lastActiveAt && $lastActiveAt->diffInDays(now()) >= 365) {
                $user->update(['status' => false]);
                $user->refresh();
            }
        }

        // Akun yang diblokir (banned) oleh admin
        if ($user?->isBanned()) {
            Auth::logout();
            $banReason = $user->ban_reason ?? 'melanggar ketentuan layanan';
            $daysRemaining = max(0, 30 - $user->banned_at->diffInDays(now()));
            return redirect()->route('login')
                ->with('error', "Akun Anda telah diblokir selama 30 hari karena {$banReason}. Sisa {$daysRemaining} hari lagi.")
                ->with('show_contact_admin', true);
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