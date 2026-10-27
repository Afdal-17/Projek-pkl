<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DompetController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\TargetTabunganController;
use App\Http\Controllers\TransaksiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Endpoint untuk Vue Frontend User
Route::middleware(['auth:sanctum'])->group(function () {
    // Dompet & Toggle Transfer
    Route::get('/dompet', [DompetController::class, 'index']);
    Route::post('/dompet', [DompetController::class, 'store']);
    Route::patch('/dompet/{dompet}/toggle-transfer', [DompetController::class, 'toggleTransfer']);
    Route::patch('/dompet/{dompet}/manage', [DompetController::class, 'frontendUpdate']);

    // Transfer & Pencarian Penerima
    Route::get('/transfer/recipients/search', [TransferController::class, 'searchRecipients']);
    Route::post('/transfer', [TransferController::class, 'store']);

    // Transaksi & Tabungan
    Route::get('/transactions', [TransaksiController::class, 'frontendIndex']);
    Route::post('/transactions', [TransaksiController::class, 'store']);
    Route::get('/saving', [TargetTabunganController::class, 'frontendIndex']);
    Route::post('/saving', [TargetTabunganController::class, 'store']);
});
