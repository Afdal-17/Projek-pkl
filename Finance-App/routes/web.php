<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DompetController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\TargetTabunganController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\CheckRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Route khusus ADMIN
Route::middleware(['auth', CheckRole::class . ':admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/users', [AdminController::class, 'manageUsers'])->name('admin.users');
    Route::patch('/users/{user}/status', [AdminController::class, 'toggleStatus'])->name('admin.users.status');
});

// Route khusus USER biasa
Route::middleware(['auth', CheckRole::class . ':user'])->group(function () {
    Route::get('/dashboard', [UserController::class, 'index'])->name('user.dashboard');
    Route::get('/dashboard/summary', [UserController::class, 'summary'])->name('user.dashboard.summary');
    Route::resource('dompet', DompetController::class);
    Route::resource('kategori', KategoriController::class);
    Route::resource('transaksi', TransaksiController::class);
    Route::resource('transfer', TransferController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('target-tabungan', TargetTabunganController::class);
    Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi.index');
    Route::patch('/notifikasi/{notifikasi}/read', [NotifikasiController::class, 'read'])->name('notifikasi.read');
    Route::patch('/notifikasi/read-all', [NotifikasiController::class, 'readAll'])->name('notifikasi.read-all');
});

Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    return Auth::user()->role === 'admin'
        ? redirect()->route('admin.dashboard')
        : redirect()->route('user.dashboard');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
