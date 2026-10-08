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
    Route::get('/profile', [ProfileController::class, 'adminEdit'])->name('admin.profile');
    Route::patch('/profile', [ProfileController::class, 'frontendUpdate'])->name('admin.profile.update');
    Route::patch('/users/{user}/status', [AdminController::class, 'toggleStatus'])->name('admin.users.status');
    Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::patch('/users/{user}/ban', [AdminController::class, 'banUser'])->name('admin.users.ban');
    Route::patch('/users/{user}/verify', [AdminController::class, 'verifyUser'])->name('admin.users.verify');
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('admin.users.destroy');
});

// Route khusus USER biasa
Route::middleware(['auth', CheckRole::class . ':user'])->group(function () {
    Route::get('/dashboard', [UserController::class, 'index'])->name('user.dashboard');
    Route::get('/dashboard/summary', [UserController::class, 'summary'])->name('user.dashboard.summary');
    Route::get('/wallet/{dompet}', [DompetController::class, 'frontendShow'])->name('dompet.history');
    Route::resource('dompet', DompetController::class);
    Route::patch('/dompet/{dompet}/manage', [DompetController::class, 'frontendUpdate'])->name('dompet.manage');
    Route::resource('kategori', KategoriController::class);
    Route::get('/transactions/categories', [KategoriController::class, 'frontendIndex'])->name('categories.index');
    Route::post('/transactions/categories', [KategoriController::class, 'store'])->name('categories.store');
    Route::patch('/transactions/categories/{kategori}', [KategoriController::class, 'update'])->name('categories.update');
    Route::delete('/transactions/categories/{kategori}', [KategoriController::class, 'destroy'])->name('categories.destroy');
    Route::resource('transaksi', TransaksiController::class);
    Route::get('/transactions', [TransaksiController::class, 'frontendIndex'])->name('transactions.index');
    Route::get('/transactions/create', [TransaksiController::class, 'frontendCreate'])->name('transactions.create');
    Route::post('/transactions', [TransaksiController::class, 'store'])->name('transactions.store');
    Route::get('/saving', [TargetTabunganController::class, 'frontendIndex'])->name('saving.index');
    Route::get('/saving/create', [TargetTabunganController::class, 'frontendCreate'])->name('saving.create');
    Route::post('/saving', [TargetTabunganController::class, 'store'])->name('saving.store');
    Route::delete('/saving/{targetTabungan}', [TargetTabunganController::class, 'destroy'])->name('saving.destroy');
    Route::get('/transfer/recipients', [TransferController::class, 'frontendRecipients'])->name('transfer.recipients');
    Route::get('/transfer/recipients/search', [TransferController::class, 'searchRecipients'])->name('transfer.recipients.search');
    Route::resource('transfer', TransferController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('target-tabungan', TargetTabunganController::class);
    Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi.index');
    Route::patch('/notifikasi/{notifikasi}/read', [NotifikasiController::class, 'read'])->name('notifikasi.read');
    Route::patch('/notifikasi/read-all', [NotifikasiController::class, 'readAll'])->name('notifikasi.read-all');
    Route::get('/notifications', [NotifikasiController::class, 'frontendIndex'])->name('notifications.index');
    Route::patch('/notifications/{notifikasi}/read', [NotifikasiController::class, 'read'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotifikasiController::class, 'readAll'])->name('notifications.read-all');
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
    Route::get('/account', [ProfileController::class, 'frontendEdit'])->name('account');
    Route::patch('/account', [ProfileController::class, 'frontendUpdate'])->name('account.update');
    Route::post('/account/avatar', [ProfileController::class, 'updateAvatar'])->name('account.avatar.update');
    Route::delete('/account/avatar', [ProfileController::class, 'deleteAvatar'])->name('account.avatar.destroy');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

    // Halaman frontend yang memakai controller backend yang sama

require __DIR__.'/auth.php';
