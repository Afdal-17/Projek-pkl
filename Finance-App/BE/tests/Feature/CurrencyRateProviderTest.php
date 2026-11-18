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

    public function test_live_rates_are_fetched_relative_to_usd_base(): void
    {
        config(['currency.live' => true]);

        // Frankfurter memberi jumlah unit target per 1 USD.
        Http::fake([
            '*' => Http::response([
                'amount' => 1.0,
                'base' => 'USD',
                'date' => '2026-10-12',
                'rates' => ['IDR' => 20000.0, 'EUR' => 0.8],
            ]),
        ]);

        $rates = CurrencyService::rates();

        $this->assertSame(1.0, $rates['USD']);
        $this->assertEqualsWithDelta(20000.0, $rates['IDR'], 0.01);
        $this->assertEqualsWithDelta(0.8, $rates['EUR'], 0.01);

        // Konversi memakai kurs live, bukan fallback statis
        $this->assertEqualsWithDelta(10.0, CurrencyService::convert(200000, 'IDR', 'USD'), 0.001);
        $this->assertEqualsWithDelta(200000.0, CurrencyService::convert(10, 'USD', 'IDR'), 0.001);
        $this->assertEqualsWithDelta(80.0, CurrencyService::convert(100, 'USD', 'EUR'), 0.001);
    }

    public function test_provider_is_called_once_and_rates_are_cached(): void
    {
        config(['currency.live' => true]);

        Http::fake([
            '*' => Http::response(['rates' => ['IDR' => 20000.0, 'EUR' => 0.8]]),
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
        $this->assertSame(1.0, $rates['USD']);
        $this->assertSame(16000.0, $rates['IDR']);
        $this->assertEqualsWithDelta(0.9142857143, $rates['EUR'], 0.0000001);
        $this->assertEqualsWithDelta(10.0, CurrencyService::convert(160000, 'IDR', 'USD'), 0.001);
    }

    public function test_stale_cache_is_used_when_provider_fails(): void
    {
        config(['currency.live' => true]);

        Cache::forever(CurrencyRateProvider::STALE_KEY, [
            'USD' => 1.0,
            'IDR' => 17000.0,
            'EUR' => 0.9,
        ]);

        Http::fake([
            '*' => Http::response([], 500),
        ]);

        // Kurs terakhir yang berhasil diambil tetap dipakai sebelum fallback statis
        $this->assertEqualsWithDelta(17000.0, CurrencyService::rates()['IDR'], 0.01);
    }

    public function test_disabled_live_mode_uses_fallback_without_http(): void
    {
        config(['currency.live' => false]);

        Http::fake();
        Http::preventStrayRequests();

        $rates = CurrencyService::rates();

        $this->assertSame(1.0, $rates['USD']);
        $this->assertSame(16000.0, $rates['IDR']);
        $this->assertEqualsWithDelta(0.9142857143, $rates['EUR'], 0.0000001);

        Http::assertNothingSent();
    }

    public function test_normalize_uses_supported_list_from_config(): void
    {
        $this->assertSame('USD', CurrencyService::normalize('usd'));
        $this->assertSame('USD', CurrencyService::normalize('JPY')); // tidak didukung → base
        $this->assertFalse(CurrencyService::isSupported('JPY'));
        $this->assertTrue(CurrencyService::isSupported('eur'));
        $this->assertSame(['USD', 'IDR', 'EUR'], CurrencyService::supported());
    }
}
