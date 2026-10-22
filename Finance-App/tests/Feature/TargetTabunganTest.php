<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Notifikasi;
use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetTabunganTest extends TestCase
{
    use RefreshDatabase;

    public function test_target_progress_follows_wallet_balance_without_creating_a_transaction(): void
    {
        $user = User::create([
            'nama' => 'Target User',
            'email' => 'target@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);
        $dompet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet Target',
            'saldo_awal' => 50000,
            'saldo' => 50000,
        ]);

        $this->actingAs($user)->post(route('target-tabungan.store'), [
            'id_dompet' => $dompet->id_dompet,
            'nama_target' => 'Laptop',
            'nominal_target' => 100000,
        ])->assertRedirect(route('target-tabungan.index'));

        $target = TargetTabungan::firstOrFail()->load('dompet');
        $this->assertSame(50.0, $target->progress);
        $this->assertFalse($target->tercapai);
        $this->assertSame(0, Transaksi::count());

        $this->actingAs($user)->get(route('saving.index'))
            ->assertOk()
            ->assertSee('Laptop')
            ->assertSee('Dompet Target');
        $this->actingAs($user)->get(route('saving.create'))
            ->assertOk()
            ->assertSee('Dompet Target')
            ->assertViewHas('wallets', fn ($wallets) => $wallets->contains(
                fn (array $item): bool => (int) $item['id'] === (int) $dompet->id_dompet
            ));

        $dompet->update(['saldo' => 100000]);
        $target->refresh()->load('dompet');

        $this->assertSame(100.0, $target->progress);
        $this->assertTrue($target->tercapai);
    }

    public function test_saving_frontend_persists_description_and_initial_savings_baseline(): void
    {
        $user = User::create([
            'nama' => 'Saving Form User',
            'email' => 'saving-form@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Savings Wallet',
            'saldo_awal' => 1000,
            'saldo' => 1000,
        ]);

        $this->actingAs($user)->post(route('saving.store'), [
            'id_dompet' => $wallet->id_dompet,
            'nama_target' => 'Rainy day fund',
            'deskripsi' => 'Unexpected expenses',
            'nominal_target' => 2000,
            'initial_savings' => 400,
        ])->assertRedirect(route('target-tabungan.index'));

        $target = TargetTabungan::firstOrFail()->load('dompet');
        $this->assertSame('Unexpected expenses', $target->deskripsi);
        $this->assertSame(20.0, $target->progress);

        $wallet->update(['saldo' => 1300]);
        $this->assertSame(35.0, $target->fresh()->load('dompet')->progress);
    }

    public function test_initial_savings_at_target_marks_target_complete_and_notifies_user(): void
    {
        $user = User::create([
            'nama' => 'Completed Target User',
            'email' => 'completed-target@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Goal Wallet',
            'saldo_awal' => 1000,
            'saldo' => 1000,
        ]);

        $this->actingAs($user)->post(route('saving.store'), [
            'id_dompet' => $wallet->id_dompet,
            'nama_target' => 'Already funded',
            'nominal_target' => 1000,
            'initial_savings' => 1000,
        ])->assertRedirect(route('target-tabungan.index'));

        $this->assertDatabaseHas('target_tabungan', [
            'id_user' => $user->id_user,
            'status' => 'tercapai',
        ]);
        $this->assertSame(1, Notifikasi::where('id_user', $user->id_user)->where('tipe', 'target')->count());
    }

    public function test_default_initial_savings_is_not_converted_twice_for_non_idr_user(): void
    {
        $user = User::create([
            'nama' => 'USD Saving User',
            'email' => 'usd-saving@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
            'mata_uang' => 'USD',
        ]);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'USD Wallet',
            'saldo_awal' => 800000,
            'saldo' => 800000,
        ]);

        $this->actingAs($user)->post(route('saving.store'), [
            'id_dompet' => $wallet->id_dompet,
            'nama_target' => 'Laptop in USD',
            'nominal_target' => '100.00',
        ])->assertRedirect(route('target-tabungan.index'));

        $this->assertDatabaseHas('target_tabungan', [
            'id_user' => $user->id_user,
            'nominal_target' => '1600000.00',
            'nominal_terkumpul' => '800000.00',
            'saldo_awal_dompet' => '800000.00',
        ]);
    }

    public function test_saving_page_renders_with_notif_popup_session(): void
    {
        $user = User::create([
            'nama' => 'Saving Popup User',
            'email' => 'saving-popup@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['notif_popup' => 'Target Dana Darurat telah tercapai.'])
            ->get(route('saving.index'))
            ->assertOk()
            ->assertSee('Target Dana Darurat has been reached.');
    }

    public function test_saving_target_can_be_deleted_from_the_frontend_route(): void
    {
        $user = User::create([
            'nama' => 'Delete Saving User',
            'email' => 'delete-saving@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Delete Wallet',
            'saldo_awal' => 1000,
            'saldo' => 1000,
        ]);
        $target = TargetTabungan::create([
            'id_user' => $user->id_user,
            'id_dompet' => $wallet->id_dompet,
            'nama_target' => 'Delete me',
            'nominal_target' => 2000,
            'nominal_terkumpul' => 0,
            'saldo_awal_dompet' => 1000,
            'status' => 'belum_tercapai',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('saving.destroy', $target))
            ->assertOk()
            ->assertJson(['status' => 'deleted']);

        $this->assertDatabaseMissing('target_tabungan', ['id_target' => $target->id_target]);
    }
}
