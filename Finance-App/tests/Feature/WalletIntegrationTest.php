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

    public function test_wallet_edit_accepts_hex_for_its_existing_preset_color(): void
    {
        $user = User::create([
            'nama' => 'Wallet Color User',
            'email' => 'wallet-color@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Main wallet',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo_awal' => '100.00',
            'saldo' => '100.00',
        ]);

        $this->actingAs($user)
            ->patchJson(route('dompet.manage', $wallet), [
                'nama_dompet' => 'Main wallet',
                'jenis' => 'digital',
                'warna' => '#4F46E5',
                'saldo' => '100.00',
            ])
            ->assertOk()
            ->assertJson(['status' => 'wallet-updated']);

        $this->assertDatabaseHas('dompet', [
            'id_dompet' => $wallet->id_dompet,
            'warna' => '#4f46e5',
        ]);
    }

    public function test_wallet_color_edit_does_not_revalidate_or_resubmit_an_existing_large_balance(): void
    {
        $user = User::create([
            'nama' => 'Legacy Balance User',
            'email' => 'legacy-balance@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Warisan',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo_awal' => '10000000000000000000.00',
            'saldo' => '10000000000000000000.00',
        ]);

        $this->actingAs($user)
            ->patchJson(route('dompet.manage', $wallet), [
                'nama_dompet' => 'Warisan',
                'deskripsi' => null,
                'jenis' => 'digital',
                'warna' => 'income',
            ])
            ->assertOk()
            ->assertJson(['status' => 'wallet-updated']);

        $this->assertSame('10000000000000000000.00', (string) $wallet->fresh()->saldo);
        $this->assertDatabaseHas('dompet', [
            'id_dompet' => $wallet->id_dompet,
            'warna' => 'income',
            'saldo' => '10000000000000000000.00',
        ]);
        $this->assertDatabaseMissing('transaksi', [
            'id_dompet' => $wallet->id_dompet,
            'nama_transaksi' => 'Penyesuaian saldo wallet',
        ]);
    }

    public function test_wallet_metadata_edit_preserves_zero_balance(): void
    {
        $user = User::create([
            'nama' => 'Zero Balance Edit User',
            'email' => 'zero-balance-edit@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Empty wallet',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo_awal' => '0.00',
            'saldo' => '0.00',
        ]);

        $this->actingAs($user)
            ->patchJson(route('dompet.manage', $wallet), [
                'nama_dompet' => 'Empty wallet renamed',
                'deskripsi' => null,
                'jenis' => 'digital',
                'warna' => 'income',
            ])
            ->assertOk()
            ->assertJson(['status' => 'wallet-updated']);

        $this->assertSame('0.00', (string) $wallet->fresh()->saldo);
    }

    public function test_wallet_balance_can_be_set_to_zero(): void
    {
        $user = User::create([
            'nama' => 'Set Zero Balance User',
            'email' => 'set-zero-balance@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Wallet with balance',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo_awal' => '10.00',
            'saldo' => '10.00',
        ]);

        $this->actingAs($user)
            ->patchJson(route('dompet.manage', $wallet), [
                'nama_dompet' => 'Wallet with balance',
                'deskripsi' => null,
                'jenis' => 'digital',
                'warna' => 'brand',
                'saldo' => '0.00',
            ])
            ->assertOk()
            ->assertJson(['status' => 'wallet-updated']);

        $this->assertSame('0.00', (string) $wallet->fresh()->saldo);
        $this->assertDatabaseHas('transaksi', [
            'id_dompet' => $wallet->id_dompet,
            'jumlah' => '10.00',
            'jenis' => 'pengeluaran',
            'nama_transaksi' => 'Penyesuaian saldo wallet',
        ]);
    }

    public function test_wallet_balance_limit_error_is_english_and_consistent(): void
    {
        $user = User::create([
            'nama' => 'Wallet Validation User',
            'email' => 'wallet-validation@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Main wallet',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo_awal' => '100.00',
            'saldo' => '100.00',
        ]);

        $this->actingAs($user)
            ->patchJson(route('dompet.manage', $wallet), [
                'nama_dompet' => 'Main wallet',
                'jenis' => 'digital',
                'warna' => 'brand',
                'saldo' => '1000000000000000000.00',
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.saldo.0',
                'The balance must be a non-negative number and must not exceed the supported maximum.'
            );
    }
    
    public function test_wallet_creation_rejects_initial_balance_above_database_limit(): void
    {
        $user = User::create([
            'nama' => 'Wallet Creation Limit User',
            'email' => 'wallet-creation-limit@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $data = [
            'nama_dompet' => 'Dompet Saldo Terlalu Besar',
            'deskripsi' => 'Tes batas saldo',
            'jenis' => 'bank',
            'warna' => 'warn',
            'saldo_awal' => '10000000000000000000000000000',
        ];

        $this->actingAs($user)
            ->post(route('dompet.store'), $data)
            ->assertSessionHasErrors('saldo_awal');

        $this->assertDatabaseMissing('dompet', [
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet Saldo Terlalu Besar',
        ]);

        $this->actingAs($user)
            ->postJson(route('dompet.store'), $data)
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.saldo_awal.0',
                'The balance must be a non-negative number and must not exceed the supported maximum.'
            );
    }

    
    public function test_wallet_balance_edit_rejects_amount_above_database_limit(): void
    {
        $user = User::create([
            'nama' => 'Wallet Limit User',
            'email' => 'wallet-limit@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet Tes',
            'jenis' => 'physical',
            'warna' => 'warn',
            'saldo_awal' => 1000,
            'saldo' => 1000,
        ]);

        $this->actingAs($user)
            ->patchJson(route('dompet.manage', $wallet), [
                'nama_dompet' => 'Dompet Tes',
                'deskripsi' => null,
                'jenis' => 'physical',
                'warna' => 'warn',
                'saldo' => '10000000000000000000000000000',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('saldo');

        $this->assertDatabaseHas('dompet', [
            'id_dompet' => $wallet->id_dompet,
            'saldo' => 1000,
        ]);
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

    public function test_wallet_creation_allows_color_already_used_by_another_wallet(): void
    {
        $user = User::create([
            'nama' => 'Shared Wallet Color User',
            'email' => 'shared-wallet-color@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);
        Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Existing wallet',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo_awal' => '0.00',
            'saldo' => '0.00',
        ]);

        $this->actingAs($user)->post(route('dompet.store'), [
            'nama_dompet' => 'Another wallet',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo_awal' => '0',
        ])->assertRedirect(route('dompet.index'));

        $this->assertSame(
            2,
            Dompet::where('id_user', $user->id_user)->where('warna', 'brand')->count()
        );
    }

    public function test_wallet_creation_still_rejects_invalid_color_format(): void
    {
        $user = User::create([
            'nama' => 'Invalid Wallet Color User',
            'email' => 'invalid-wallet-color@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->postJson(route('dompet.store'), [
            'nama_dompet' => 'Invalid color wallet',
            'jenis' => 'digital',
            'warna' => '#12xz89',
            'saldo_awal' => '0',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('warna');
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