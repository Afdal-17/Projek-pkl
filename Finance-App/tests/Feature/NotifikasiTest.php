<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\Notifikasi;
use App\Models\TargetTabungan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotifikasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_creates_in_app_notifications_for_both_users(): void
    {
        [$pengirim, $asal] = $this->userWithWallet('notif-pengirim@example.com', 'Asal', 100000);
        [$penerima, $tujuan] = $this->userWithWallet('notif-penerima@example.com', 'Tujuan', 0);

        $pengirim->update(['nama' => 'Pengirim']);
        $penerima->update(['nama' => 'Penerima']);

        $this->actingAs($pengirim)->post(route('transfer.store'), [
            'id_dompet_asal' => $asal->id_dompet,
            'id_dompet_tujuan' => $tujuan->id_dompet,
            'jumlah' => 10000,
            'tanggal_transfer' => '2026-10-01 10:00:00',
        ])->assertRedirect(route('transfer.index'));

        $this->assertSame(1, Notifikasi::where('id_user', $pengirim->id_user)->count());
        $this->assertSame(1, Notifikasi::where('id_user', $penerima->id_user)->count());

        // Pengirim mendapat notifikasi transfer berhasil ke penerima dari dompet asal
        $senderNotification = Notifikasi::where('id_user', $pengirim->id_user)->firstOrFail();
        $this->assertSame('Transfer berhasil ke Penerima dari Asal sebesar Rp 10.000,00.', $senderNotification->pesan);

        // Penerima mendapat notifikasi di transfer oleh pengirim
        $recipientNotification = Notifikasi::where('id_user', $penerima->id_user)->firstOrFail();
        $this->assertSame('Anda di transfer oleh Pengirim sebesar Rp 10.000,00.', $recipientNotification->pesan);

        // Waktu notifikasi mengikuti waktu pembuatan (sekarang), bukan tanggal transfer
        $this->assertLessThanOrEqual(60, $senderNotification->tanggal->diffInSeconds(now()));
        $this->assertLessThanOrEqual(60, $recipientNotification->tanggal->diffInSeconds(now()));

        $this->actingAs($penerima)->patch(route('notifikasi.read', $recipientNotification))->assertRedirect();
        $this->assertTrue((bool) $recipientNotification->fresh()->sudah_dibaca);

        $recipientNotification->update(['sudah_dibaca' => false]);
        $this->actingAs($penerima)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Transfer success!')
            ->assertSee($recipientNotification->pesan);
        $this->actingAs($penerima)->patch(route('notifications.read', $recipientNotification))->assertRedirect();
        $this->assertTrue((bool) $recipientNotification->fresh()->sudah_dibaca);
    }

    public function test_notifications_are_listed_newest_first(): void
    {
        [$user] = $this->userWithWallet('urutan@example.com', 'Dompet', 100000);

        // Notifikasi lama dibuat lebih dulu, meskipun tanggalnya lebih baru
        Notifikasi::create([
            'id_user' => $user->id_user,
            'tipe' => 'transaksi',
            'pesan' => 'Notifikasi lama',
            'sudah_dibaca' => false,
            'tanggal' => now()->subDays(2),
        ]);

        Notifikasi::create([
            'id_user' => $user->id_user,
            'tipe' => 'transaksi',
            'pesan' => 'Notifikasi terbaru',
            'sudah_dibaca' => false,
            'tanggal' => now()->subDay(),
        ]);

        // Notifikasi terbaru selalu muncul di atas, sesuai urutan pembuatan
        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeInOrder(['Notifikasi terbaru', 'Notifikasi lama']);
    }

    public function test_target_notification_is_created_when_wallet_reaches_target(): void
    {
        [$user, $dompet] = $this->userWithWallet('notif-target@example.com', 'Target', 90000);
        $target = TargetTabungan::create([
            'id_user' => $user->id_user,
            'id_dompet' => $dompet->id_dompet,
            'nama_target' => 'Dana Darurat',
            'nominal_target' => 100000,
            'nominal_terkumpul' => 0,
            'status' => 'belum_tercapai',
        ]);
        $kategori = Kategori::create([
            'id_user' => $user->id_user,
            'nama_kategori' => 'Bonus',
            'jenis' => 'pemasukan',
        ]);

        $this->actingAs($user)->post(route('transaksi.store'), [
            'id_dompet' => $dompet->id_dompet,
            'id_kategori' => $kategori->id_kategori,
            'nama_transaksi' => 'Bonus',
            'jumlah' => 10000,
            'jenis' => 'pemasukan',
            'tanggal' => '2026-10-01 11:00:00',
        ])->assertRedirect(route('transaksi.index'));

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $user->id_user,
            'tipe' => 'target',
            'sudah_dibaca' => false,
        ]);
        $this->assertTrue($target->load('dompet')->tercapai);
    }

    private function userWithWallet(string $email, string $walletName, int $balance): array
    {
        $user = User::create([
            'nama' => $email,
            'email' => $email,
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => $walletName,
            'saldo_awal' => $balance,
            'saldo' => $balance,
        ]);

        return [$user, $wallet];
    }
}
