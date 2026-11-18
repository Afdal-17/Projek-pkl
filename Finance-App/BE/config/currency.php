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

    'base' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Mata Uang yang Didukung
    |--------------------------------------------------------------------------
    | Daftar kode mata uang yang boleh dipilih user.
    */

    'supported' => ['USD', 'IDR', 'EUR'],

    /*
    |--------------------------------------------------------------------------
    | Kurs Otomatis
    |--------------------------------------------------------------------------
    | Jika true, kurs harian terbaru diambil dari internet (Frankfurter) lalu di-cache.
    | Jika false (mis. test/offline), aplikasi memakai fallback_rates.
    */

    'live' => env('CURRENCY_LIVE_RATES', true),

    /*
    | Sumber kurs: Frankfurter (https://frankfurter.dev) — gratis, tanpa API key,
    | kurs resmi harian dari ECB dan bank sentral lainnya; bukan kurs intraday.
    */

    'provider_url' => env('CURRENCY_PROVIDER_URL', 'https://api.frankfurter.app/latest'),

    'timeout' => (int) env('CURRENCY_TIMEOUT', 5),

    'ttl_minutes' => (int) env('CURRENCY_TTL_MINUTES', 360),

    /*
    |--------------------------------------------------------------------------
    | Kurs Cadangan
    |--------------------------------------------------------------------------
    | Jumlah unit mata uang per 1 USD. Dipakai saat internet/API tidak tersedia.
    */

    'fallback_rates' => [
        'IDR' => 16000.0,
        'EUR' => 0.9142857143,
    ],

];
