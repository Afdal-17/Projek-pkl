<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // View demo (resources/views/demo) TIDAK lagi didaftarkan sebagai
        // lokasi resolusi cadangan — mencegah view demo lama (tanpa dukungan
        // multi-mata-uang) dirender diam-diam jika ada nama view yang hilang.

        // Aturan minimum password untuk registrasi, ganti password, dan reset:
        // minimal 8 karakter serta mengandung huruf dan angka (memblokir
        // password lemah seperti "password" atau "12345678").
        Password::defaults(function (): Password {
            return Password::min(8)->letters()->numbers();
        });
    }
}
