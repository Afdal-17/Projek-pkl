<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencySwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_currency_service_converts_correctly(): void
    {
        // IDR -> USD (1 USD = 16,000 IDR)
        $this->assertEquals(10.0, CurrencyService::convert(160000, 'IDR', 'USD'));

        // USD -> IDR
        $this->assertEquals(160000.0, CurrencyService::convert(10, 'USD', 'IDR'));

        // IDR -> EUR (1 EUR = 17,500 IDR)
        $this->assertEqualsWithDelta(100.0, CurrencyService::convert(1750000, 'IDR', 'EUR'), 0.00001);

        // EUR -> IDR
        $this->assertEqualsWithDelta(1750000.0, CurrencyService::convert(100, 'EUR', 'IDR'), 0.001);

        // USD -> EUR (100 USD = 1,600,000 IDR / 17,500 = 91.4285... EUR)
        $this->assertEqualsWithDelta(91.4286, CurrencyService::convert(100, 'USD', 'EUR'), 0.0001);
    }

    public function test_display_conversion_round_trip_is_exact(): void
    {
        // 50 USD -> ditampilkan sebagai EUR -> dikembalikan ke USD harus tetap 50.
        $usd = 50.0;
        $eur = CurrencyService::fromBase($usd, 'EUR');
        $back = CurrencyService::toBase($eur, 'EUR');

        $this->assertEquals($usd, $back);
        $this->assertEqualsWithDelta(45.7142857, $eur, 0.000001);
    }

    public function test_switch_currency_only_updates_preference_and_never_mutates_financial_data(): void
    {
        $user = User::create([
            'nama' => 'User Currency Test',
            'email' => 'currency@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
            'mata_uang' => 'IDR',
        ]);

        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet Utama',
            'saldo' => 50000,
            'saldo_awal' => 50000,
        ]);

        $kategori = Kategori::create([
            'id_user' => $user->id_user,
            'nama_kategori' => 'Makan',
            'jenis' => 'pengeluaran',
        ]);

        $transaksi = Transaksi::create([
            'id_dompet' => $wallet->id_dompet,
            'id_kategori' => $kategori->id_kategori,
            'nama_transaksi' => 'Makan Siang',
            'jumlah' => 20000,
            'jenis' => 'pengeluaran',
            'tanggal' => now(),
        ]);

        $target = TargetTabungan::create([
            'id_user' => $user->id_user,
            'id_dompet' => $wallet->id_dompet,
            'nama_target' => 'Beli Laptop',
            'nominal_target' => 8000000,
            'nominal_terkumpul' => 50000,
            'saldo_awal_dompet' => 50000,
        ]);

        $this->actingAs($user);

        // IDR -> USD
        $response = $this->patchJson(route('account.currency.switch'), ['mata_uang' => 'USD']);
        $response->assertOk()->assertJson(['success' => true, 'new_currency' => 'USD']);

        // Nilai tersimpan TIDAK boleh berubah (masih dalam USD base)
        $this->assertEquals(50000, (float) $wallet->fresh()->saldo);
        $this->assertEquals(50000, (float) $wallet->fresh()->saldo_awal);
        $this->assertEquals(20000, (float) $transaksi->fresh()->jumlah);
        $this->assertEquals(8000000, (float) $target->fresh()->nominal_target);
        $this->assertEquals(50000, (float) $target->fresh()->nominal_terkumpul);

        $this->assertEquals('$50,000.00', finance_money($wallet->fresh()->saldo));

        // USD -> EUR
        $this->patchJson(route('account.currency.switch'), ['mata_uang' => 'EUR']);
        $this->assertEquals(50000, (float) $wallet->fresh()->saldo);
        $this->assertEquals('45.714,29 €', finance_money($wallet->fresh()->saldo));

        // Kembali ke IDR: tampilan mengikuti kurs USD/IDR
        $this->patchJson(route('account.currency.switch'), ['mata_uang' => 'IDR']);
        $user->refresh();
        $this->assertEquals('IDR', $user->mata_uang);
        $this->assertEquals(50000, (float) $wallet->fresh()->saldo);
        $this->assertEquals('Rp 800.000.000', finance_money($wallet->fresh()->saldo));
    }

    public function test_input_in_display_currency_is_stored_as_usd_base(): void
    {
        $user = User::create([
            'nama' => 'User Currency Test 2',
            'email' => 'currency2@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
            'mata_uang' => 'USD',
        ]);

        $this->actingAs($user);

        // User menabung $100 -> tersimpan 100 USD
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dollar wallet',
            'saldo' => CurrencyService::toBase(100.0),
            'saldo_awal' => CurrencyService::toBase(100.0),
        ]);

        $this->assertEquals(100.0, (float) $wallet->saldo);

        // Ditampilkan kembali dalam USD = $100.00
        $this->assertEquals('$100.00', finance_money($wallet->saldo));

        // Ganti ke IDR -> Rp 1.600.000
        $this->patchJson(route('account.currency.switch'), ['mata_uang' => 'IDR']);
        $this->assertEquals('Rp 1.600.000', finance_money($wallet->fresh()->saldo));
    }

    public function test_account_update_endpoint_updates_currency_preference_without_touching_data(): void
    {
        $user = User::create([
            'nama' => 'User Currency Test 3',
            'email' => 'currency3@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
            'mata_uang' => 'IDR',
        ]);

        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet EUR',
            'saldo' => 100,
            'saldo_awal' => 0,
        ]);

        $this->actingAs($user);

        $response = $this->patch(route('account.update'), [
            'nama' => $user->nama,
            'email' => $user->email,
            'mata_uang' => 'EUR',
        ]);

        $response->assertRedirect(route('account'));

        $user->refresh();
        $wallet->refresh();

        $this->assertEquals('EUR', $user->mata_uang);
        // Data keuangan tetap utuh dalam USD
        $this->assertEquals(100, (float) $wallet->saldo);
        // Tampilan mengikuti fallback EUR/USD.
        $this->assertEquals('91,43 €', finance_money($wallet->saldo));
    }
}
