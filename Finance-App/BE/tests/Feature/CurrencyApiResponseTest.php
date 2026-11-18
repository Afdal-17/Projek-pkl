<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyApiResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_api_responses_keep_usd_values_and_add_user_currency_values(): void
    {
        $user = User::factory()->create(['mata_uang' => 'EUR']);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'USD wallet',
            'saldo_awal' => 50,
            'saldo' => 100,
        ]);
        $category = Kategori::create([
            'id_user' => $user->id_user,
            'nama_kategori' => 'Food',
            'jenis' => 'pengeluaran',
        ]);
        Transaksi::create([
            'id_dompet' => $wallet->id_dompet,
            'id_kategori' => $category->id_kategori,
            'nama_transaksi' => 'Lunch',
            'jumlah' => 25,
            'jenis' => 'pengeluaran',
            'tanggal' => now(),
        ]);
        TargetTabungan::create([
            'id_user' => $user->id_user,
            'id_dompet' => $wallet->id_dompet,
            'nama_target' => 'Savings',
            'nominal_target' => 200,
            'nominal_terkumpul' => 0,
            'saldo_awal_dompet' => 0,
        ]);

        $this->actingAs($user)
            ->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonPath('data.0.amount', 25)
            ->assertJsonPath('data.0.amount_money.base_currency', 'USD')
            ->assertJsonPath('data.0.amount_money.base_amount', 25)
            ->assertJsonPath('data.0.amount_money.display_currency', 'EUR')
            ->assertJsonPath('data.0.amount_money.display_amount', 22.86)
            ->assertJsonPath('data.0.amount_money.rate.from', 'USD')
            ->assertJsonPath('data.0.amount_money.rate.to', 'EUR');

        $this->getJson('/api/dompet')
            ->assertOk()
            ->assertJsonPath('data.0.saldo', '100.00000000')
            ->assertJsonPath('data.0.saldo_money.display_amount', 91.43)
            ->assertJsonPath('data.0.saldo_money.display_currency', 'EUR');

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('total_saldo_money.base_currency', 'USD')
            ->assertJsonPath('total_saldo_money.display_amount', 91.43)
            ->assertJsonPath('target_tabungan.0.nominal_target_money.display_amount', 182.86);

        $this->getJson('/api/saving')
            ->assertOk()
            ->assertJsonPath('0.target_money.base_amount', 200)
            ->assertJsonPath('0.target_money.display_amount', 182.86);
    }
}
