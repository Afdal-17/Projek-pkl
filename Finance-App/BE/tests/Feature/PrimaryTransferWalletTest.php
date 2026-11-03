<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrimaryTransferWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_set_primary_transfer_wallet(): void
    {
        $user = User::create([
            'nama' => 'User One',
            'email' => 'user-one@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Main Wallet',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo_awal' => '100.00',
            'saldo' => '100.00',
            'terima_transfer' => false,
        ]);

        $response = $this->actingAs($user)->patchJson(route('dompet.toggleTransfer', $wallet), [
            'set_primary' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'terima_transfer' => true,
            ]);

        $this->assertDatabaseHas('dompet', [
            'id_dompet' => $wallet->id_dompet,
            'terima_transfer' => 1,
        ]);
    }

    public function test_transfer_wallet_setting_rejects_invalid_boolean_value(): void
    {
        $user = User::create([
            'nama' => 'Invalid Primary Setting User',
            'email' => 'invalid-primary-setting@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Wallet',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo_awal' => '0.00',
            'saldo' => '0.00',
            'terima_transfer' => false,
        ]);

        $this->actingAs($user)
            ->patchJson(route('dompet.toggleTransfer', $wallet), [
                'set_primary' => 'not-a-boolean',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('set_primary');

        $this->actingAs($user)
            ->patchJson(route('dompet.toggleTransfer', $wallet), [
                'set_primary' => true,
            ])
            ->assertOk();

        $this->actingAs($user)
            ->patchJson(route('dompet.toggleTransfer', $wallet), [
                'set_primary' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('set_primary');

        $this->actingAs($user)
            ->patchJson(route('dompet.toggleTransfer', $wallet))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('set_primary');

        $this->assertTrue((bool) $wallet->fresh()->terima_transfer);
    }

    public function test_primary_transfer_wallet_is_managed_from_dashboard_not_account_preferences(): void
    {
        $user = User::create([
            'nama' => 'Transfer Preferences User',
            'email' => 'transfer-preferences@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Receiving Wallet',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo_awal' => '100.00',
            'saldo' => '100.00',
            'terima_transfer' => false,
        ]);

        $this->actingAs($user)
            ->get(route('account'))
            ->assertOk()
            ->assertDontSee('transfer_receiving_wallet')
            ->assertDontSee('Transfer receiving wallet')
            ->assertDontSee('name="transfer_receiving_wallet"', false);

        $this->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('Set as recipient')
            ->assertSee('Primary recipient');
    }

    public function test_setting_new_primary_wallet_automatically_deactivates_previous_wallet(): void
    {
        $user = User::create([
            'nama' => 'User Multi',
            'email' => 'user-multi@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $wallet1 = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Wallet A',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo_awal' => '100.00',
            'saldo' => '100.00',
            'terima_transfer' => true,
        ]);

        $wallet2 = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Wallet B',
            'jenis' => 'bank',
            'warna' => 'income',
            'saldo_awal' => '200.00',
            'saldo' => '200.00',
            'terima_transfer' => false,
        ]);

        // Set wallet 2 as primary
        $response = $this->actingAs($user)->patchJson(route('dompet.toggleTransfer', $wallet2), [
            'set_primary' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'terima_transfer' => true,
            ]);

        // Wallet 2 is now true, Wallet 1 is now false
        $this->assertTrue((bool) $wallet2->fresh()->terima_transfer);
        $this->assertFalse((bool) $wallet1->fresh()->terima_transfer);

        $this->assertDatabaseHas('dompet', [
            'id_dompet' => $wallet2->id_dompet,
            'terima_transfer' => 1,
        ]);
        $this->assertDatabaseHas('dompet', [
            'id_dompet' => $wallet1->id_dompet,
            'terima_transfer' => 0,
        ]);
    }

    public function test_user_cannot_modify_transfer_setting_of_another_users_wallet(): void
    {
        $userA = User::create([
            'nama' => 'User A',
            'email' => 'usera@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $userB = User::create([
            'nama' => 'User B',
            'email' => 'userb@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $walletB1 = Dompet::create([
            'id_user' => $userB->id_user,
            'nama_dompet' => 'Wallet B1',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo_awal' => '50.00',
            'saldo' => '50.00',
        ]);

        $walletB2 = Dompet::create([
            'id_user' => $userB->id_user,
            'nama_dompet' => 'Wallet B2',
            'jenis' => 'bank',
            'warna' => 'income',
            'saldo_awal' => '100.00',
            'saldo' => '100.00',
            'terima_transfer' => false,
        ]);

        // User A tries to modify User B's second wallet
        $response = $this->actingAs($userA)->patchJson(route('dompet.toggleTransfer', $walletB2), [
            'set_primary' => true,
        ]);

        $response->assertStatus(404);
        $this->assertFalse((bool) $walletB2->fresh()->terima_transfer);
    }

    public function test_wallet_edit_preserves_its_own_saved_color_without_duplicate_error(): void
    {
        $user = User::create([
            'nama' => 'Color User',
            'email' => 'color@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Transportasi',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo_awal' => '550.00',
            'saldo' => '550.00',
        ]);

        // Editing name while keeping same color 'brand' must succeed
        $response = $this->actingAs($user)->patchJson(route('dompet.manage', $wallet), [
            'nama_dompet' => 'Transportasi Jabodetabek',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo' => '550.00',
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'wallet-updated']);

        $this->assertDatabaseHas('dompet', [
            'id_dompet' => $wallet->id_dompet,
            'nama_dompet' => 'Transportasi Jabodetabek',
            'warna' => 'brand',
        ]);
    }

    public function test_wallet_edit_allows_color_already_used_by_another_wallet(): void
    {
        $user = User::create([
            'nama' => 'Color Dup User',
            'email' => 'colordup@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $wallet1 = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Wallet 1',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo_awal' => '100.00',
            'saldo' => '100.00',
        ]);

        $wallet2 = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Wallet 2',
            'jenis' => 'digital',
            'warna' => 'income',
            'saldo_awal' => '100.00',
            'saldo' => '100.00',
        ]);

        // Wallet colors are cosmetic and can be shared.
        $response = $this->actingAs($user)->patchJson(route('dompet.manage', $wallet2), [
            'nama_dompet' => 'Wallet 2 Modified',
            'jenis' => 'digital',
            'warna' => 'brand',
            'saldo' => '100.00',
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'wallet-updated']);
        $this->assertSame('brand', $wallet2->fresh()->warna);
    }
}
