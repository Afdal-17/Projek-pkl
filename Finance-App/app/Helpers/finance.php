<?php

if (! function_exists('finance_money')) {
    /**
     * Format uang sesuai mata uang user. Nilai $amount tersimpan dalam
     * IDR (base currency) dan dikonversi ke mata uang aktif saat menampilkan:
     *   IDR -> "Rp 1.000.000"    (locale id-ID, tanpa desimal)
     *   USD -> "$1,000.00"         (locale en-US)
     *   EUR -> "1.000.000,00 €"    (locale de-DE)
     */
    function finance_money($amount, ?string $currency = null): string
    {
        $currency = \App\Services\CurrencyService::normalize($currency ?? (auth()->user()->mata_uang ?? 'IDR'));

        $raw = trim((string) ($amount ?? '0'));
        $isNegative = str_starts_with($raw, '-');
        $clean = ltrim($raw, '-');

        if (preg_match('/^\d+(?:\.\d+)?$/', $clean)) {
            $sign = $isNegative ? '-' : '';

            if ($currency === 'IDR') {
                $integerPart = explode('.', $clean)[0];
                $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $integerPart);

                return "Rp {$sign}{$grouped}";
            }

            $converted = \App\Services\CurrencyService::fromBaseExact($clean, $currency);
            [$cInteger, $cDecimal] = array_pad(explode('.', $converted), 2, '00');
            $cDecimal = substr(str_pad($cDecimal, 2, '0'), 0, 2);

            return match ($currency) {
                'USD' => '$' . $sign . preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $cInteger) . '.' . $cDecimal,
                'EUR' => $sign . preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $cInteger) . ',' . $cDecimal . ' €',
                default => "Rp {$sign}" . preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $cInteger),
            };
        }

        throw new \InvalidArgumentException('Money amount must be a plain decimal string.');
    }
}

if (! function_exists('finance_locale')) {
    /**
     * Locale Intl.NumberFormat untuk mata uang tertentu (dipakai sisi Blade/PHP).
     */
    function finance_locale(?string $currency = null): string
    {
        $currency = \App\Services\CurrencyService::normalize($currency ?? (auth()->user()->mata_uang ?? 'IDR'));

        return match ($currency) {
            'USD' => 'en-US',
            'EUR' => 'de-DE',
            default => 'id-ID',
        };
    }
}
