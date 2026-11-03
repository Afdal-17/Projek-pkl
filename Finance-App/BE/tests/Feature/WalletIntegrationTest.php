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
            'email_verified_at' => now(),
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
            'email_verified_at' => now(),
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
        $this->assertDatabaseHas('transaksi', [
            'nama_transaksi' => 'Saldo awal',
            'jumlah' => 2500,
            'jenis' => 'pemasukan',
        ]);
    }

    public function test_wallet_creation_with_optional_empty_initial_balance_defaults_to_zero_without_transaction(): void
    {
        $user = User::create([
            'nama' => 'Zero Balance User',
            'email' => 'zero-balance@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        // Test without saldo_awal field provided
        $this->actingAs($user)->post(route('dompet.store'), [
            'nama_dompet' => 'Dompet Kosong',
            'deskripsi' => 'Tanpa saldo awal',
            'jenis' => 'digital',
            'warna' => 'brand',
        ])->assertRedirect(route('dompet.index'));

        $this->assertDatabaseHas('dompet', [
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet Kosong',
            'saldo_awal' => 0,
            'saldo' => 0,
        ]);
        $this->assertDatabaseMissing('transaksi', [
            'nama_transaksi' => 'Saldo awal',
        ]);
    }

    public function test_wallet_creation_with_initial_balance_creates_transaction(): void
    {
        $user = User::create([
            'nama' => 'Initial Balance User',
            'email' => 'initial-balance@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->post(route('dompet.store'), [
            'nama_dompet' => 'Dompet Tabungan Baru',
            'deskripsi' => 'Dengan saldo awal',
            'jenis' => 'bank',
            'warna' => 'income',
            'saldo_awal' => 150000,
        ])->assertRedirect(route('dompet.index'));

        $wallet = Dompet::where('id_user', $user->id_user)->firstOrFail();
        $this->assertSame('150000.00', (string) $wallet->saldo);
        $this->assertSame('150000.00', (string) $wallet->saldo_awal);

        $this->assertDatabaseHas('transaksi', [
            'id_dompet' => $wallet->id_dompet,
            'nama_transaksi' => 'Saldo awal',
            'jumlah' => 150000,
            'jenis' => 'pemasukan',
        ]);
    }

    public function test_wallet_creation_with_both_current_balance_and_initial_balance(): void
    {
        $user = User::create([
            'nama' => 'Both Balances User',
            'email' => 'both-balances@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('dompet.store'), [
            'nama_dompet' => 'Dompet Fleksibel',
            'deskripsi' => 'Dengan saldo saat ini dan saldo awal',
            'jenis' => 'bank',
            'warna' => 'warn',
            'saldo' => 200000,
            'saldo_awal' => 100000,
        ]);

        $response->assertSessionHasErrors(['saldo', 'saldo_awal']);
        $this->assertDatabaseMissing('dompet', [
            'nama_dompet' => 'Dompet Fleksibel',
        ]);
    }

    public function test_wallet_creation_with_only_current_balance(): void
    {
        $user = User::create([
            'nama' => 'Only Current Balance User',
            'email' => 'only-current@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->post(route('dompet.store'), [
            'nama_dompet' => 'Dompet Saldo Saat Ini',
            'jenis' => 'digital',
            'warna' => 'dark',
            'saldo' => 75000,
        ])->assertRedirect(route('dompet.index'));

        $wallet = Dompet::where('id_user', $user->id_user)->firstOrFail();
        $this->assertSame('75000.00', (string) $wallet->saldo);
        $this->assertSame('0.00', (string) $wallet->saldo_awal);

        $this->assertDatabaseMissing('transaksi', [
            'id_dompet' => $wallet->id_dompet,
            'nama_transaksi' => 'Saldo awal',
        ]);
    }
}