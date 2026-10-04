<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DompetController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\ProfileController;
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
});

// Route khusus USER biasa
Route::middleware(['auth', CheckRole::class . ':user'])->group(function () {
    Route::get('/dashboard', [UserController::class, 'index'])->name('user.dashboard');
    Route::resource('dompet', DompetController::class);
    Route::resource('kategori', KategoriController::class);
    Route::resource('transaksi', TransaksiController::class);
    Route::resource('transfer', TransferController::class);
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

    // Halaman frontend (sementara Route::view, nanti diganti controller)
    Route::view('/transactions', 'transactions.index')->name('transactions.index');
    Route::view('/transactions/create', 'transactions.create')->name('transactions.create');
    Route::view('/transactions/categories', 'transactions.categories')->name('categories.index');
    Route::view('/saving', 'saving.index')->name('saving.index');
    Route::view('/saving/create', 'saving.create')->name('saving.create');
    Route::view('/notifications', 'notifications')->name('notifications');
    Route::view('/account', 'account')->name('account');

require __DIR__.'/auth.php';
