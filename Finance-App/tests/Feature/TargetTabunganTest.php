<?php

namespace Tests\Feature;

use App\Models\Dompet;
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

        $dompet->update(['saldo' => 100000]);
        $target->refresh()->load('dompet');

        $this->assertSame(100.0, $target->progress);
        $this->assertTrue($target->tercapai);
    }
}
