<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class CurrencyRateProvider
{
    /** Kurs segar (hasil fetch API), di-cache selama ttl_minutes. */
    public const CACHE_KEY = 'currency.rates.usd';

    /** Kurs terakhir yang berhasil diambil — dipakai saat API sedang gagal. */
    public const STALE_KEY = 'currency.rates.usd.stale';

    /**
     * Jumlah unit setiap mata uang per 1 USD.
     * Contoh hasil: ['USD' => 1.0, 'IDR' => 16000.0, 'EUR' => 0.92]
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
     * Frankfurter memberi jumlah unit target per 1 USD, sama dengan format
     * rates yang dipakai aplikasi.
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

        $result = [CurrencyService::BASE => 1.0];

        foreach ($targets as $code) {
            $perUsd = isset($rates[$code]) ? (float) $rates[$code] : 0.0;

            if ($perUsd <= 0) {
                throw new RuntimeException("Kurs {$code} tidak tersedia di respons API.");
            }

            $result[$code] = $perUsd;
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
        $out = [CurrencyService::BASE => 1.0];

        foreach ($supported as $code) {
            if ($code === CurrencyService::BASE) {
                continue;
            }

            if (isset($rates[$code]) && is_numeric($rates[$code]) && $rates[$code] > 0) {
                $out[$code] = (float) $rates[$code];
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
}
