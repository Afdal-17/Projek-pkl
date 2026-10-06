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
            'users' => User::orderBy('nama')->paginate(15),
        ]);
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'Admin tidak dapat menonaktifkan akunnya sendiri.');
        abort_if($user->isAdmin(), 403, 'Status akun admin tidak dapat diubah dari halaman ini.');

        $user->update(['status' => ! $user->status]);

        return back();
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        abort_if($user->isAdmin(), 403, 'Akun admin tidak dapat diubah dari halaman ini.');

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

    public function deleteUser(Request $request, User $user): JsonResponse
    {
        abort_if($user->isAdmin(), 403, 'Akun admin tidak dapat dihapus dari halaman ini.');
        abort_if($user->is($request->user()), 422, 'Admin tidak dapat menghapus akunnya sendiri.');

        $walletIds = $user->dompet()->pluck('id_dompet');
        $hasTransferHistory = Transfer::whereIn('id_dompet_asal', $walletIds)
            ->orWhereIn('id_dompet_tujuan', $walletIds)
            ->exists();

        if ($hasTransferHistory) {
            return response()->json([
                'message' => 'User tidak dapat dihapus karena wallet-nya memiliki riwayat transfer.',
            ], 409);
        }

        $user->delete();

        return response()->json([], 204);
    }
}
