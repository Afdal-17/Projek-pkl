<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Notifikasi;
use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_balance_edit_creates_ledger_adjustment_and_updates_target_progress(): void
    {
        $user = User::create([
            'nama' => 'Wallet User',
            'email' => 'wallet-user@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
        ]);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Cash',
            'deskripsi' => 'Physical wallet',
            'jenis' => 'physical',
            'warna' => 'income',
            'saldo_awal' => 1000,
            'saldo' => 1000,
        ]);
        $target = TargetTabungan::create([
            'id_user' => $user->id_user,
            'id_dompet' => $wallet->id_dompet,
            'nama_target' => 'Emergency fund',
            'deskripsi' => 'Unexpected expenses',
            'nominal_target' => 600,
            'nominal_terkumpul' => 200,
            'saldo_awal_dompet' => 1000,
            'status' => 'belum_tercapai',
        ]);

        $this->actingAs($user)->patchJson(route('dompet.manage', $wallet), [
            'nama_dompet' => 'Main cash',
            'deskripsi' => 'Updated physical wallet',
            'jenis' => 'physical',
            'warna' => 'warn',
            'saldo' => 1500,
        ])->assertOk();

        $this->assertDatabaseHas('dompet', [
            'id_dompet' => $wallet->id_dompet,
            'nama_dompet' => 'Main cash',
            'jenis' => 'physical',
            'warna' => 'warn',
            'saldo' => 1500,
        ]);
        $this->assertDatabaseHas('transaksi', [
            'id_dompet' => $wallet->id_dompet,
            'nama_transaksi' => 'Penyesuaian saldo wallet',
            'jumlah' => 500,
            'jenis' => 'pemasukan',
        ]);
        $this->assertSame(100.0, $target->fresh()->load('dompet')->progress);
        $this->assertSame(1, Notifikasi::where('id_user', $user->id_user)->where('tipe', 'target')->count());

        $this->actingAs($user)->patchJson(route('dompet.manage', $wallet), [
            'nama_dompet' => 'Main cash',
            'deskripsi' => 'Updated physical wallet',
            'jenis' => 'physical',
            'warna' => 'warn',
            'saldo' => 1200,
        ])->assertOk();

        $this->assertDatabaseHas('transaksi', [
            'id_dompet' => $wallet->id_dompet,
            'nama_transaksi' => 'Penyesuaian saldo wallet',
            'jumlah' => 300,
            'jenis' => 'pengeluaran',
        ]);
        $this->assertSame(66.67, $target->fresh()->load('dompet')->progress);
    }

    public function test_wallet_creation_persists_kind_and_color(): void
    {
        $user = User::create([
            'nama' => 'Wallet Creator',
            'email' => 'wallet-creator@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
        ]);

        $this->actingAs($user)->postJson(route('dompet.store'), [
            'nama_dompet' => 'Travel account',
            'deskripsi' => 'Trip budget',
            'jenis' => 'bank',
            'warna' => 'brand',
            'saldo_awal' => 2500,
        ])->assertRedirect(route('dompet.index'));

        $this->assertDatabaseHas('dompet', [
            'id_user' => $user->id_user,
            'nama_dompet' => 'Travel account',
            'jenis' => 'bank',
            'warna' => 'brand',
            'saldo' => 2500,
        ]);
    }
}