<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'summary' => [
                'total_user' => User::count(),
                'user_aktif' => User::where('status', true)->count(),
                'total_transaksi' => Transaksi::count(),
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
}
