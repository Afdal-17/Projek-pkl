<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAmountPrecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_message_formats_large_idr_amount_without_float_conversion(): void
    {
        $user = User::create([
            'nama' => 'Large Amount User',
            'email' => 'large-amount@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
            'mata_uang' => 'IDR',
        ]);

        $this->actingAs($user);

        $notification = Notifikasi::create([
            'id_user' => $user->id_user,
            'tipe' => 'transaksi',
            'pesan' => 'Activity recorded.',
            'jumlah' => '1000000000000000.00',
            'pesan_template' => 'Amount {jumlah} recorded.',
            'sudah_dibaca' => false,
            'tanggal' => now(),
        ]);

        $this->assertSame(
            'Amount Rp 1.000.000.000.000.000 recorded.',
            $notification->pesanTampil()
        );
    }
}
