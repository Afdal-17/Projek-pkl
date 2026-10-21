<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Header keamanan global (nosniff, clickjacker, referrer, dll).
        $middleware->append(SecurityHeaders::class);

        // Closure ini dieksekusi SEBELUM .env/config dimuat (saat kernel di-resolve),
        // sehingga config() belum tersedia — baca konfigurasi setelah aplikasi boot.
        // TrustProxies::at() hanya menyetel properti statis yang dipakai saat
        // request ditangani, jadi pemanggilan tertunda ini aman.
        app()->booted(static function (): void {
            // Reverse proxy (nginx/LB): isi TRUSTED_PROXIES di .env, contoh: 127.0.0.1
            // agar aplikasi mengenali skema HTTPS aslinya dari header X-Forwarded-*.
            $proxies = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) config('app.trusted_proxies', ''))
            )));

            if ($proxies !== []) {
                TrustProxies::at($proxies);
            }
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
