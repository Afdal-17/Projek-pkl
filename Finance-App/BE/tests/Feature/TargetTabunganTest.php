<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Notifikasi;
use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\CurrencyService;
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
            ->assertSee('Dompet Target');

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

    public function test_default_initial_savings_uses_the_wallet_usd_balance_without_reconverting(): void
    {
        $user = User::create([
            'nama' => 'EUR Target User',
            'email' => 'eur-target@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
            'mata_uang' => 'EUR',
        ]);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'USD wallet',
            'saldo_awal' => 100,
            'saldo' => 100,
        ]);

        $this->actingAs($user)->postJson(route('saving.store'), [
            'id_dompet' => $wallet->id_dompet,
            'nama_target' => 'EUR target',
            'nominal_target' => 200,
        ])->assertCreated();

        $target = TargetTabungan::firstOrFail();
        $this->assertEquals(100.0, (float) $target->nominal_terkumpul);
        $this->assertEquals(100.0, (float) $target->saldo_awal_dompet);
        $this->assertEqualsWithDelta(
            CurrencyService::toBase(200, 'EUR'),
            (float) $target->nominal_target,
            0.00000001
        );
    }
}
