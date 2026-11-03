<?php

namespace Tests\Feature;

use App\Services\CurrencyRateProvider;
use App\Services\CurrencyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencyRateProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CurrencyService::flushRates();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        CurrencyService::flushRates();

        parent::tearDown();
    }

    public function test_live_rates_are_fetched_and_inverted_to_idr_base(): void
    {
        config(['currency.live' => true]);

        // Frankfurter memberi kurs "per 1 IDR": USD = 0.00005 → 1 USD = 20.000 IDR
        Http::fake([
            '*' => Http::response([
                'amount' => 1.0,
                'base' => 'IDR',
                'date' => '2026-10-12',
                'rates' => ['USD' => 0.00005, 'EUR' => 0.00004],
            ]),
        ]);

        $rates = CurrencyService::rates();

        $this->assertSame('1', $rates['IDR']);
        $this->assertSame('20000.000000000000', $rates['USD']);
        $this->assertSame('25000.000000000000', $rates['EUR']);

        // Konversi memakai kurs live, bukan fallback statis
        $this->assertEqualsWithDelta(10.0, CurrencyService::convert(200000, 'IDR', 'USD'), 0.001);
        $this->assertEqualsWithDelta(200000.0, CurrencyService::convert(10, 'USD', 'IDR'), 0.001);
        $this->assertSame('200000.00', CurrencyService::toBaseExact('10.00', 'USD'));
    }

    public function test_provider_is_called_once_and_rates_are_cached(): void
    {
        config(['currency.live' => true]);

        Http::fake([
            '*' => Http::response(['rates' => ['USD' => 0.00005, 'EUR' => 0.00004]]),
        ]);

        CurrencyService::rates();
        CurrencyService::rates();
        CurrencyService::convert(1000, 'IDR', 'USD');
        CurrencyService::fromBase(50000, 'EUR');

        Http::assertSentCount(1);
    }

    public function test_fallback_rates_are_used_when_provider_fails(): void
    {
        config(['currency.live' => true]);

        Http::fake([
            '*' => Http::response('server error', 500),
        ]);

        $rates = CurrencyService::rates();

        // Kurs cadangan statis dipakai saat API gagal
        $this->assertSame('16000', $rates['USD']);
        $this->assertSame('17500', $rates['EUR']);
        $this->assertEqualsWithDelta(10.0, CurrencyService::convert(160000, 'IDR', 'USD'), 0.001);
    }

    public function test_stale_cache_is_used_when_provider_fails(): void
    {
        config(['currency.live' => true]);

        Cache::forever(CurrencyRateProvider::STALE_KEY, [
            'IDR' => 1.0,
            'USD' => 17000.0,
            'EUR' => 18000.0,
        ]);

        Http::fake([
            '*' => Http::response([], 500),
        ]);

        // Kurs terakhir yang berhasil diambil tetap dipakai sebelum fallback statis
        $this->assertEqualsWithDelta(17000.0, CurrencyService::rates()['USD'], 0.01);
    }

    public function test_disabled_live_mode_uses_fallback_without_http(): void
    {
        config(['currency.live' => false]);

        Http::fake();
        Http::preventStrayRequests();

        $rates = CurrencyService::rates();

        $this->assertSame('1', $rates['IDR']);
        $this->assertSame('16000', $rates['USD']);
        $this->assertSame('17500', $rates['EUR']);

        Http::assertNothingSent();
    }

    public function test_normalize_uses_supported_list_from_config(): void
    {
        $this->assertSame('USD', CurrencyService::normalize('usd'));
        $this->assertSame('IDR', CurrencyService::normalize('JPY')); // tidak didukung → base
        $this->assertFalse(CurrencyService::isSupported('JPY'));
        $this->assertTrue(CurrencyService::isSupported('eur'));
        $this->assertSame(['IDR', 'USD', 'EUR'], CurrencyService::supported());
    }

    public function test_exact_conversion_preserves_cents_and_fails_when_a_rate_is_missing(): void
    {
        config(['currency.live' => false]);
        CurrencyService::flushRates();

        $this->assertSame('160800.00', CurrencyService::toBaseExact('10.05', 'USD'));
        $this->assertSame('0.63', CurrencyService::fromBaseExact('10000.00', 'USD'));

        config([
            'currency.supported' => ['IDR', 'EUR'],
            'currency.fallback_rates' => ['EUR' => 0],
        ]);
        CurrencyService::flushRates();

        $this->expectException(\RuntimeException::class);
        CurrencyService::convertExact('1.00', 'EUR', 'IDR');
    }

    public function test_exact_conversion_keeps_large_amounts_as_decimal_strings(): void
    {
        config(['currency.live' => false]);

        $this->assertSame(
            '999999999999999999.99',
            CurrencyService::toBaseExact('999999999999999999.99', 'IDR')
        );
        $this->assertSame(
            '62500000000000.00',
            CurrencyService::fromBaseExact('1000000000000000000.00', 'USD')
        );
    }
}
