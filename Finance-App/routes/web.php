<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DompetController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\CheckRole;
use Illuminate\Support\Facades\Auth;

// Route khusus ADMIN
Route::middleware(['auth', CheckRole::class . ':admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/users', [AdminController::class, 'manageUsers'])->name('admin.users');
});

// Route khusus USER biasa
Route::middleware(['auth', CheckRole::class . ':user'])->group(function () {
    Route::get('/dashboard', [UserController::class, 'index'])->name('user.dashboard');
    Route::resource('dompet', DompetController::class);
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

require __DIR__.'/auth.php';
