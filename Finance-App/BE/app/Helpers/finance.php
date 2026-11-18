<?php

if (! function_exists('finance_money')) {
    /**
     * Format uang sesuai mata uang user. Nilai $amount tersimpan dalam
     * USD (base currency) dan dikonversi ke mata uang aktif saat menampilkan.
     */
    function finance_money($amount, ?string $currency = null): string
    {
        $currency = \App\Services\CurrencyService::normalize($currency ?? (auth()->user()->mata_uang ?? 'USD'));
        $display = \App\Services\CurrencyService::fromBase((float) $amount, $currency);

        return match ($currency) {
            'USD' => '$' . number_format($display, 2, '.', ','),
            'EUR' => number_format($display, 2, ',', '.') . ' €',
            default => 'Rp ' . number_format($display, 0, ',', '.'),
        };
    }
}

if (! function_exists('finance_locale')) {
    /**
     * Locale Intl.NumberFormat untuk mata uang tertentu (dipakai sisi Blade/PHP).
     */
    function finance_locale(?string $currency = null): string
    {
        $currency = \App\Services\CurrencyService::normalize($currency ?? (auth()->user()->mata_uang ?? 'USD'));

        return match ($currency) {
            'USD' => 'en-US',
            'EUR' => 'de-DE',
            default => 'id-ID',
        };
    }
}
