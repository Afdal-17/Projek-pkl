<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index(): View
    {
        $today = now()->startOfDay();
        $days = [];
        $totalUsersByDay = [];
        $newUsersByDay = [];

        foreach (range(6, 0) as $daysAgo) {
            $date = $today->copy()->subDays($daysAgo);
            $days[] = $date->format('D');
            $newUsersByDay[] = User::whereDate('created_at', $date)->count();
            $totalUsersByDay[] = User::where('created_at', '<', $date->copy()->addDay())->count();
        }

        return view('admin.dashboard', [
            'summary' => [
                'total_user' => User::count(),
                'user_aktif' => User::where('status', true)->count(),
                'user_online' => User::where('status', true)
                    ->where('last_seen_at', '>=', now()->subMinutes(5))
                    ->count(),
                'user_baru_minggu_ini' => User::whereBetween('created_at', [$today->copy()->subDays(6), now()])->count(),
                'total_transaksi' => Transaksi::count(),
            ],
            'users' => User::orderByDesc('id_user')->limit(5)->get(),
            'days' => $days,
            'chart' => [
                'total' => $totalUsersByDay,
                'fresh' => $newUsersByDay,
            ],
        ]);
    }

    public function manageUsers(): View
    {
        return view('admin.users', [
            'users' => User::where('role', 'user')
                ->whereNotNull('email_verified_at')
                ->orderBy('nama')
                ->paginate(15),
        ]);
    }

    public function verifyUsers(): View
    {
        return view('admin.verify-users', [
            'users' => User::where('role', 'user')
                ->whereNull('email_verified_at')
                ->orderBy('created_at', 'desc')
                ->paginate(15),
        ]);
    }

    public function toggleStatus(Request $request, User $user): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'Admins cannot deactivate their own account.');
        abort_if($user->isAdmin(), 403, 'Admin account status cannot be modified from this page.');

        // Hanya untuk mengaktifkan kembali akun yang dibanned (unban)
        if ($user->banned_at !== null) {
            $user->update([
                'status' => true,
                'banned_at' => null,
                'ban_reason' => null,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'active',
                    'message' => 'User account has been reactivated.',
                ]);
            }

            return back()->with('status', 'User account has been reactivated.');
        }

        // User tidak dibanned, tidak ada aksi yang diizinkan
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'no_change',
                'message' => 'No status change.',
            ]);
        }

        return back();
    }

    public function banUser(Request $request, User $user): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        abort_if($user->isAdmin(), 403, 'Admin accounts cannot be suspended from this page.');
        abort_if($user->is($request->user()), 422, 'Admins cannot suspend their own account.');

        $banning = $user->banned_at === null;

        if ($banning) {
            $validated = $request->validate([
                'ban_reason' => ['required', 'string', 'max:500'],
            ]);

            $user->update([
                'status' => false,
                'banned_at' => now(),
                'ban_reason' => $validated['ban_reason'],
            ]);
        } else {
            $user->update([
                'status' => true,
                'banned_at' => null,
                'ban_reason' => null,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $banning ? 'banned' : 'active',
                'message' => $banning ? 'User account has been suspended.' : 'User account has been reactivated.',
            ]);
        }

        return back()->with('status', $banning ? 'User account has been suspended.' : 'User account has been reactivated.');
    }

    public function verifyUser(Request $request, User $user): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        abort_if($user->isAdmin(), 403, 'Admin accounts cannot be verified from this page.');
        abort_if($user->is($request->user()), 422, 'Admins cannot verify their own account.');

        if ($user->email_verified_at !== null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'User has already been verified.',
                ], 409);
            }

            return back()->with('status', 'User has already been verified.');
        }

        $user->update([
            'email_verified_at' => now(),
            'status' => true,
            'banned_at' => null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'verified',
                'message' => 'User account has been verified and can now log in.',
            ]);
        }

        return back()->with('status', 'User account has been verified and can now log in.');
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        abort_if($user->isAdmin(), 403, 'Admin accounts cannot be modified from this page.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('user', 'email')->ignore($user->id_user, 'id_user'),
            ],
        ]);

        $user->update(['nama' => $validated['name'], 'email' => $validated['email']]);

        return response()->json(['name' => $user->nama, 'email' => $user->email]);
    }

    public function deleteUser(Request $request, User $user): JsonResponse|RedirectResponse
    {
        abort_if($user->isAdmin(), 403, 'Admin accounts cannot be deleted from this page.');
        abort_if($user->is($request->user()), 422, 'Admins cannot delete their own account.');

        // User inactive atau banned bisa dihapus (termasuk yang sudah tidak aktif 1 tahun)
        $isInactive = ! $user->status;
        $isBanned = $user->banned_at !== null;
        $lastActiveAt = $user->last_seen_at ?? $user->created_at;
        $longInactive = $lastActiveAt !== null
            && $lastActiveAt->diffInDays(now()) >= 365;

        if (! $isInactive && ! $isBanned && ! $longInactive) {
            return response()->json([
                'message' => 'Users can only be deleted if their status is Inactive or Banned.',
            ], 409);
        }

        $walletIds = $user->dompet()->pluck('id_dompet');
        $hasTransferHistory = Transfer::whereIn('id_dompet_asal', $walletIds)
            ->orWhereIn('id_dompet_tujuan', $walletIds)
            ->exists();

        if ($hasTransferHistory) {
            return response()->json([
                'message' => 'User cannot be deleted because their wallet has existing transfer history.',
            ], 409);
        }

        $user->delete();

        return response()->json([], 204);
    }
}
