<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\Transfer;
use App\Models\User;
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
        ]);
        $asal = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Asal',
            'saldo_awal' => 100000,
            'saldo' => 140000,
        ]);
        $tujuan = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Tujuan',
            'saldo_awal' => 0,
            'saldo' => 60000,
        ]);
        $income = Kategori::create(['id_user' => $user->id_user, 'nama_kategori' => 'Gaji', 'jenis' => 'pemasukan']);
        $expense = Kategori::create(['id_user' => $user->id_user, 'nama_kategori' => 'Makan', 'jenis' => 'pengeluaran']);
        Transaksi::create([
            'id_kategori' => $income->id_kategori,
            'id_dompet' => $asal->id_dompet,
            'nama_transaksi' => 'Gaji',
            'jumlah' => 50000,
            'jenis' => 'pemasukan',
            'tanggal' => now(),
        ]);
        Transaksi::create([
            'id_kategori' => $expense->id_kategori,
            'id_dompet' => $asal->id_dompet,
            'nama_transaksi' => 'Makan',
            'jumlah' => 10000,
            'jenis' => 'pengeluaran',
            'tanggal' => now(),
        ]);
        $transfer = Transfer::create([
            'id_user' => $user->id_user,
            'id_dompet_asal' => $asal->id_dompet,
            'id_dompet_tujuan' => $tujuan->id_dompet,
            'jumlah' => 20000,
            'tanggal_transfer' => now(),
        ]);
        Transaksi::create(['id_transfer' => $transfer->id_transfer, 'id_dompet' => $asal->id_dompet, 'nama_transaksi' => 'Transfer keluar', 'jumlah' => 20000, 'jenis' => 'pengeluaran', 'tanggal' => now()]);
        Transaksi::create(['id_transfer' => $transfer->id_transfer, 'id_dompet' => $tujuan->id_dompet, 'nama_transaksi' => 'Transfer masuk', 'jumlah' => 20000, 'jenis' => 'pemasukan', 'tanggal' => now()]);
        TargetTabungan::create([
            'id_user' => $user->id_user,
            'id_dompet' => $tujuan->id_dompet,
            'nama_target' => 'Dana Darurat',
            'nominal_target' => 100000,
            'nominal_terkumpul' => 0,
            'status' => 'belum_tercapai',
        ]);

        $this->actingAs($user)->getJson(route('user.dashboard.summary'))
            ->assertOk()
            ->assertJsonPath('total_saldo', 200000)
            ->assertJsonPath('total_pemasukan_bulan_ini', 50000)
            ->assertJsonPath('total_pengeluaran_bulan_ini', 10000)
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
            'mata_uang' => 'USD',
        ]);
        Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dollar wallet',
            'saldo_awal' => 1250000,
            'saldo' => 1250000,
        ]);

        $this->actingAs($user)->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('$ 1,250,000.00')
            ->assertSee('name="finance-currency" content="USD"', false);
    }
}
