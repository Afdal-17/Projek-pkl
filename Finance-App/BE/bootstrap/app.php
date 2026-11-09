<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
            // Reverse proxy (nginx/ngrok/LB): isi TRUSTED_PROXIES di .env, contoh:
            //   *            -> percaya semua proxy (untuk ngrok)
            //   REMOTE_ADDR  -> percaya hanya IP yang terhubung langsung
            //   127.0.0.1    -> daftar IP, boleh dipisah koma
            // agar aplikasi mengenali skema HTTPS aslinya dari header X-Forwarded-*.
            $raw = trim((string) config('app.trusted_proxies', ''));

            if ($raw === '') {
                return;
            }

            // PENTING: '*' dan '**' HARUS diteruskan sebagai string.
            // TrustProxies membandingkan secara ketat (=== '*'); jika dipecah
            // menjadi array ['*'], Symfony tidak mengenalinya sehingga
            // X-Forwarded-Proto diabaikan -> halaman https:// menghasilkan
            // asset http:// -> browser memblokir (mixed content) -> Alpine.js
            // tidak termuat -> menu Notifikasi/Profil tidak bisa diklik.
            TrustProxies::at(
                in_array($raw, ['*', '**'], true)
                    ? $raw
                    : array_values(array_filter(array_map('trim', explode(',', $raw))))
            );
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
