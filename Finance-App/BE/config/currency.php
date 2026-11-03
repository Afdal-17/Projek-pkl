<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Currency
    |--------------------------------------------------------------------------
    | Seluruh nilai keuangan di database SELALU disimpan dalam mata uang ini.
    | Konversi hanya terjadi saat menampilkan (display) dan saat menyimpan
    | input user (toBase), sehingga mengganti mata uang atau berubahnya kurs
    | dunia tidak pernah merusak data tersimpan.
    */

    'base' => 'IDR',

    /*
    |--------------------------------------------------------------------------
    | Mata Uang yang Didukung
    |--------------------------------------------------------------------------
    | Daftar kode mata uang yang boleh dipilih user.
    */

    'supported' => ['IDR', 'USD', 'EUR'],

    /*
    |--------------------------------------------------------------------------
    | Kurs Real-Time
    |--------------------------------------------------------------------------
    | Jika true, kurs diambil dari internet (Frankfurter) lalu di-cache.
    | Jika false (mis. test/offline), aplikasi memakai fallback_rates.
    */

    'live' => env('CURRENCY_LIVE_RATES', true),

    /*
    | Sumber kurs: Frankfurter (https://frankfurter.dev) — gratis, tanpa API key,
    | kurs resmi harian dari ECB dan bank sentral lainnya.
    */

    'provider_url' => env('CURRENCY_PROVIDER_URL', 'https://api.frankfurter.app/latest'),

    'timeout' => (int) env('CURRENCY_TIMEOUT', 5),

    'ttl_minutes' => (int) env('CURRENCY_TTL_MINUTES', 360),

    /*
    |--------------------------------------------------------------------------
    | Kurs Cadangan
    |--------------------------------------------------------------------------
    | Dipakai saat internet/API tidak tersedia sama sekali.
    */

    'fallback_rates' => [
        'IDR' => 1.0,
        'USD' => 16000.0,
        'EUR' => 17500.0,
    ],

];
