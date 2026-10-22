<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class CurrencyRateProvider
{
    /** Kurs segar (hasil fetch API), di-cache selama ttl_minutes. */
    public const CACHE_KEY = 'currency.rates';

    /** Kurs terakhir yang berhasil diambil — dipakai saat API sedang gagal. */
    public const STALE_KEY = 'currency.rates.stale';

    /**
     * Kurs relatif terhadap IDR (IDR = 1), disimpan sebagai string desimal.
     * Contoh hasil: ['IDR' => '1', 'USD' => '17857.14', 'EUR' => '20000']
     *
     * Prioritas: cache → API Frankfurter → cache lama (stale) → fallback statis.
     */
    public function rates(): array
    {
        $supported = $this->supported();

        if (! config('currency.live', true)) {
            return $this->hanyaDidukung($this->fallback(), $supported);
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && $cached !== []) {
            return $this->hanyaDidukung($cached, $supported);
        }

        try {
            $rates = $this->fetch($supported);

            Cache::put(
                self::CACHE_KEY,
                $rates,
                now()->addMinutes((int) config('currency.ttl_minutes', 360))
            );
            Cache::forever(self::STALE_KEY, $rates);

            return $rates;
        } catch (Throwable) {
            // API tidak terjangkau → pakai kurs terakhir yang tersimpan, atau fallback statis
            $stale = Cache::get(self::STALE_KEY);

            return $this->hanyaDidukung(
                is_array($stale) && $stale !== [] ? $stale : $this->fallback(),
                $supported
            );
        }
    }

    /**
     * Ambil kurs segar dari Frankfurter.
     *
     * Frankfurter memberi kurs dalam format "sejumlah X mata uang per 1 IDR"
     * (mis. USD = 0.000056), sedangkan aplikasi memakai konvensi
     * "IDR per 1 unit mata uang" (mis. USD = 17857) → nilainya dibalik.
     */
    protected function fetch(array $supported): array
    {
        $targets = array_values(array_diff($supported, [CurrencyService::BASE]));

        $response = Http::timeout((int) config('currency.timeout', 5))
            ->retry(2, 200, throw: false)
            ->get(config('currency.provider_url'), [
                'from' => CurrencyService::BASE,
                'to' => implode(',', $targets),
            ]);

        if ($response->failed()) {
            throw new RuntimeException("API kurs tidak merespons (HTTP {$response->status()}).");
        }

        $rates = $response->json('rates');

        if (! is_array($rates) || $rates === []) {
            throw new RuntimeException('Respons API kurs tidak valid.');
        }

        if (! preg_match('/"rates"\s*:\s*\{([^}]*)\}/s', $response->body(), $rateObject)) {
            throw new RuntimeException('Respons API kurs tidak valid.');
        }

        $result = [CurrencyService::BASE => '1'];

        foreach ($targets as $code) {
            $pattern = '/"'.preg_quote($code, '/').'"\\s*:\\s*(\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)/';

            if (! isset($rates[$code])
                || ! preg_match($pattern, $rateObject[1], $match)
                || bccomp($this->decimalString($match[1]), '0', 12) <= 0) {
                throw new RuntimeException("Kurs {$code} tidak tersedia di respons API.");
            }

            $result[$code] = bcdiv('1', $this->decimalString($match[1]), 12);
        }

        return $result;
    }

    protected function fallback(): array
    {
        return array_merge(
            [CurrencyService::BASE => 1.0],
            (array) config('currency.fallback_rates', [])
        );
    }

    protected function hanyaDidukung(array $rates, array $supported): array
    {
        $out = [CurrencyService::BASE => '1'];

        foreach ($supported as $code) {
            if ($code === CurrencyService::BASE) {
                continue;
            }

            $rate = isset($rates[$code]) ? trim((string) $rates[$code]) : '';

            if (preg_match('/^\d+(?:\.\d+)?$/', $rate)
                && bccomp($rate, '0', 12) > 0) {
                $out[$code] = $rate;
            }
        }

        return $out;
    }

    protected function supported(): array
    {
        return array_values(array_unique(array_merge(
            [CurrencyService::BASE],
            (array) config('currency.supported', [CurrencyService::BASE])
        )));
    }

    private function decimalString(string $value): string
    {
        if (! preg_match('/^(\d+)(?:\.(\d+))?(?:[eE]([+-]?\d+))?$/', $value, $matches)) {
            throw new RuntimeException('Kurs dari API tidak berbentuk desimal yang valid.');
        }

        $whole = $matches[1];
        $fraction = $matches[2] ?? '';
        $exponent = (int) ($matches[3] ?? 0);

        if (abs($exponent) > 20) {
            throw new RuntimeException('Kurs dari API memiliki eksponen di luar batas.');
        }

        $digits = $whole.$fraction;
        $decimalPosition = strlen($whole) + $exponent;

        if ($decimalPosition <= 0) {
            return '0.'.str_repeat('0', -$decimalPosition).$digits;
        }

        if ($decimalPosition >= strlen($digits)) {
            return $digits.str_repeat('0', $decimalPosition - strlen($digits));
        }

        return substr($digits, 0, $decimalPosition).'.'.substr($digits, $decimalPosition);
    }
}
