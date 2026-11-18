<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\Transfer;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_excludes_transfer_transactions(): void
    {
        $user = User::create([
            'nama' => 'Dashboard User',
            'email' => 'dashboard@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
            'mata_uang' => 'IDR',
        ]);
        $asal = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Asal',
            'saldo_awal' => CurrencyService::toBase(100000, 'IDR'),
            'saldo' => CurrencyService::toBase(140000, 'IDR'),
        ]);
        $tujuan = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Tujuan',
            'saldo_awal' => 0,
            'saldo' => CurrencyService::toBase(60000, 'IDR'),
        ]);
        $income = Kategori::create(['id_user' => $user->id_user, 'nama_kategori' => 'Gaji', 'jenis' => 'pemasukan']);
        $expense = Kategori::create(['id_user' => $user->id_user, 'nama_kategori' => 'Makan', 'jenis' => 'pengeluaran']);
        Transaksi::create([
            'id_kategori' => $income->id_kategori,
            'id_dompet' => $asal->id_dompet,
            'nama_transaksi' => 'Gaji',
            'jumlah' => CurrencyService::toBase(50000, 'IDR'),
            'jenis' => 'pemasukan',
            'tanggal' => now(),
        ]);
        Transaksi::create([
            'id_kategori' => $expense->id_kategori,
            'id_dompet' => $asal->id_dompet,
            'nama_transaksi' => 'Makan',
            'jumlah' => CurrencyService::toBase(10000, 'IDR'),
            'jenis' => 'pengeluaran',
            'tanggal' => now(),
        ]);
        $transfer = Transfer::create([
            'id_user' => $user->id_user,
            'id_dompet_asal' => $asal->id_dompet,
            'id_dompet_tujuan' => $tujuan->id_dompet,
            'jumlah' => CurrencyService::toBase(20000, 'IDR'),
            'tanggal_transfer' => now(),
        ]);
        Transaksi::create(['id_transfer' => $transfer->id_transfer, 'id_dompet' => $asal->id_dompet, 'nama_transaksi' => 'Transfer keluar', 'jumlah' => CurrencyService::toBase(20000, 'IDR'), 'jenis' => 'pengeluaran', 'tanggal' => now()]);
        Transaksi::create(['id_transfer' => $transfer->id_transfer, 'id_dompet' => $tujuan->id_dompet, 'nama_transaksi' => 'Transfer masuk', 'jumlah' => CurrencyService::toBase(20000, 'IDR'), 'jenis' => 'pemasukan', 'tanggal' => now()]);
        TargetTabungan::create([
            'id_user' => $user->id_user,
            'id_dompet' => $tujuan->id_dompet,
            'nama_target' => 'Dana Darurat',
            'nominal_target' => CurrencyService::toBase(100000, 'IDR'),
            'nominal_terkumpul' => 0,
            'status' => 'belum_tercapai',
        ]);

        $this->actingAs($user)->getJson(route('user.dashboard.summary'))
            ->assertOk()
            ->assertJsonPath('total_saldo', 12.5)
            ->assertJsonPath('total_pemasukan_bulan_ini', 3.125)
            ->assertJsonPath('total_pengeluaran_bulan_ini', 0.625)
            ->assertJsonPath('pengeluaran_per_kategori.0.nama_kategori', 'Makan')
            ->assertJsonPath('target_tabungan.0.progress', 60);

        $this->actingAs($user)->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('Asal')
            ->assertSee('Rp 200.000')
            ->assertSee('Makan')
            ->assertSee('1 savings targets tracked');
    }

    public function test_dashboard_renders_profile_currency_preference(): void
    {
        $user = User::create([
            'nama' => 'Currency User',
            'email' => 'currency@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
            'mata_uang' => 'USD',
        ]);
        Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dollar wallet',
            'saldo_awal' => CurrencyService::toBase(1250000, 'IDR'),
            'saldo' => CurrencyService::toBase(1250000, 'IDR'),
        ]);

        // Saldo lama setara 1.250.000 IDR; ditampilkan sebagai USD:
        // 1.250.000 / 16.000 = $78.13
        $this->actingAs($user)->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('$78.13')
            ->assertSee('name="finance-currency" content="USD"', false);
    }
}
