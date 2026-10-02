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

        $this->actingAs($pengirim)->post(route('transfer.store'), [
            'id_dompet_asal' => $asal->id_dompet,
            'id_dompet_tujuan' => $tujuan->id_dompet,
            'jumlah' => 10000,
            'tanggal_transfer' => '2026-10-01 10:00:00',
        ])->assertRedirect(route('transfer.index'));

        $this->assertSame(1, Notifikasi::where('id_user', $pengirim->id_user)->count());
        $this->assertSame(1, Notifikasi::where('id_user', $penerima->id_user)->count());

        $notification = Notifikasi::where('id_user', $penerima->id_user)->firstOrFail();
        $this->actingAs($penerima)->patch(route('notifikasi.read', $notification))->assertRedirect();
        $this->assertTrue((bool) $notification->fresh()->sudah_dibaca);
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
