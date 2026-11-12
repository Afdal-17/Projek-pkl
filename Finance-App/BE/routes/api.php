<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DompetController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TargetTabunganController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Semua endpoint di bawah mengembalikan JSON dan dilindungi Sanctum.
| Frontend (Vue/mobile) mengirim header: Authorization: Bearer <token>
|
*/

// Public API Login, Register & Forgot Password (untuk frontend Vue.js & Mobile)
Route::post('/login', [AuthenticatedSessionController::class, 'apiStore']);
Route::post('/register', [RegisteredUserController::class, 'apiStore']);
Route::post('/register/verify-otp', [RegisteredUserController::class, 'apiVerifyOtp']);
Route::post('/auth/google', [GoogleAuthController::class, 'apiGoogleLogin']);
Route::post('/forgot-password', [PasswordResetLinkController::class, 'apiStore']);
Route::post('/reset-password', [NewPasswordController::class, 'apiStore']);

Route::middleware('auth:sanctum')->group(function () {
    // Autentikasi & Profil
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [AuthenticatedSessionController::class, 'apiLogout']);
    Route::get('/account', [ProfileController::class, 'frontendEdit']);
    Route::patch('/account', [ProfileController::class, 'frontendUpdate']);
    Route::patch('/account/currency', [ProfileController::class, 'switchCurrency']);
    Route::post('/account/avatar', [ProfileController::class, 'updateAvatar']);
    Route::delete('/account/avatar', [ProfileController::class, 'deleteAvatar']);
    Route::put('/password', [PasswordController::class, 'apiUpdate']);

    // Dashboard
    Route::get('/dashboard/summary', [UserController::class, 'summary']);

    // Dompet & Toggle Transfer
    Route::get('/dompet', [DompetController::class, 'index']);
    Route::post('/dompet', [DompetController::class, 'store']);
    Route::get('/dompet/{dompet}', [DompetController::class, 'frontendShow']);
    Route::delete('/dompet/{dompet}', [DompetController::class, 'destroy']);
    Route::patch('/dompet/{dompet}/toggle-transfer', [DompetController::class, 'toggleTransfer']);
    Route::patch('/dompet/{dompet}/manage', [DompetController::class, 'frontendUpdate']);

    // Transfer & Pencarian Penerima
    Route::get('/transfer/recipients/search', [TransferController::class, 'searchRecipients']);
    Route::post('/transfer', [TransferController::class, 'store']);

    // Transaksi (GET mendukung filter + pagination, lihat TransaksiController::apiIndex)
    Route::get('/transactions', [TransaksiController::class, 'apiIndex']);
    Route::post('/transactions', [TransaksiController::class, 'store']);
    Route::patch('/transactions/{transaksi}', [TransaksiController::class, 'update']);
    Route::delete('/transactions/{transaksi}', [TransaksiController::class, 'destroy']);

    // Kategori Transaksi
    Route::get('/categories', [KategoriController::class, 'frontendIndex']);
    Route::post('/categories', [KategoriController::class, 'store']);
    Route::patch('/categories/{kategori}', [KategoriController::class, 'update']);
    Route::delete('/categories/{kategori}', [KategoriController::class, 'destroy']);

    // Target Tabungan
    Route::get('/saving', [TargetTabunganController::class, 'frontendIndex']);
    Route::post('/saving', [TargetTabunganController::class, 'store']);
    Route::patch('/saving/{targetTabungan}', [TargetTabunganController::class, 'update']);
    Route::delete('/saving/{targetTabungan}', [TargetTabunganController::class, 'destroy']);

    // Notifikasi
    Route::get('/notifications', [NotifikasiController::class, 'frontendIndex']);
    Route::patch('/notifications/{notifikasi}/read', [NotifikasiController::class, 'read']);
    Route::patch('/notifications/read-all', [NotifikasiController::class, 'readAll']);
    // Kurs Mata Uang Real-time
    Route::get('/currency/rates', fn () => response()->json(\App\Services\CurrencyService::rates()));
    // Khusus Role Admin (Mobile & SPA)
    Route::middleware([\App\Http\Middleware\CheckRole::class.':admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\AdminController::class, 'apiDashboard']);
        Route::get('/users', [\App\Http\Controllers\AdminController::class, 'apiUsers']);
        Route::get('/verify-users', [\App\Http\Controllers\AdminController::class, 'apiVerifyUsers']);
        Route::patch('/users/{user}/status', [\App\Http\Controllers\AdminController::class, 'toggleStatus']);
        Route::patch('/users/{user}/ban', [\App\Http\Controllers\AdminController::class, 'banUser']);
        Route::patch('/users/{user}/verify', [\App\Http\Controllers\AdminController::class, 'verifyUser']);
        Route::patch('/users/{user}', [\App\Http\Controllers\AdminController::class, 'updateUser']);
        Route::delete('/users/{user}', [\App\Http\Controllers\AdminController::class, 'deleteUser']);
    });
});