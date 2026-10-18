<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletHistoryViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_history_renders_properly_with_trillion_balance(): void
    {
        $user = User::create([
            'nama' => 'Test User',
            'email' => 'test-history@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'BNI',
            'deskripsi' => 'Wallet balance history',
            'jenis' => 'bank',
            'warna' => 'brand',
            'saldo_awal' => 1000000000000,
            'saldo' => '1000000000000.00',
        ]);

        $response = $this->actingAs($user)->get(route('dompet.history', $wallet));

        $response->assertOk();
        $response->assertSee('BNI');
        $response->assertSee('Wallet balance history');
        $response->assertSee('Current balance');
        $response->assertSee('Rp 1.000.000.000.000');
        $response->assertSee('flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between');
        $response->assertSee('text-xl sm:text-2xl');
        $response->assertSee('break-words');
        $response->assertSee('min-w-0');
        $response->assertSee('max-w-full');
    }

    public function test_wallet_history_renders_properly_with_ten_quintillion_balance(): void
    {
        $user = User::create([
            'nama' => 'Test User 2',
            'email' => 'test-history2@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Warisan',
            'deskripsi' => 'Wallet balance history',
            'jenis' => 'physical',
            'warna' => 'income',
            'saldo_awal' => 10000000000000000000,
            'saldo' => '10000000000000000000.00',
        ]);

        $response = $this->actingAs($user)->get(route('dompet.history', $wallet));

        $response->assertOk();
        $response->assertSee('Warisan');
        $response->assertSee('Wallet balance history');
        $response->assertSee('Current balance');
        $response->assertSee('Rp 10.000.000.000.000.000.000');
        $response->assertSee('flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between');
        $response->assertSee('text-lg sm:text-xl md:text-2xl');
        $response->assertSee('break-words');
        $response->assertSee('min-w-0');
        $response->assertSee('max-w-full');
    }

    public function test_wallet_history_renders_properly_with_standard_balance(): void
    {
        $user = User::create([
            'nama' => 'Test User 3',
            'email' => 'test-history3@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Cash',
            'deskripsi' => 'Dompet tunai harian',
            'jenis' => 'physical',
            'warna' => 'income',
            'saldo_awal' => 500000,
            'saldo' => '500000.00',
        ]);

        $response = $this->actingAs($user)->get(route('dompet.history', $wallet));

        $response->assertOk();
        $response->assertSee('Cash');
        $response->assertSee('Dompet tunai harian');
        $response->assertSee('Current balance');
        $response->assertSee('Rp 500.000');
        $response->assertSee('text-2xl sm:text-2xl');
    }
}
