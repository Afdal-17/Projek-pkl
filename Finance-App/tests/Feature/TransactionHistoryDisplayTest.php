<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionHistoryDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_transfer_from_other_user_shows_sender_name_not_wallet_name(): void
    {
        [$pengirim, $dompetAsal] = $this->userWithWallet('kirim-display@example.com', 'Dompet DANA Kirim', 100000);
        [$penerima, $dompetTujuan] = $this->userWithWallet('terima-display@example.com', 'Dompet OVO Terima', 10000);
        User::whereKey($pengirim->id_user)->update(['nama' => 'Budi Santoso']);
        User::whereKey($penerima->id_user)->update(['nama' => 'Siti Aminah']);

        $this->actingAs($pengirim)->post(route('transfer.store'), [
            'id_dompet_asal' => $dompetAsal->id_dompet,
            'id_dompet_tujuan' => $dompetTujuan->id_dompet,
            'jumlah' => 25000,
            'tanggal_transfer' => '2026-10-05 10:00:00',
        ])->assertRedirect(route('transfer.index'));

        // Sisi penerima: nama tampilan memakai nama user pengirim
        $transfer = Transfer::firstOrFail();
        $barisMasuk = $transfer->transaksi()
            ->where('id_dompet', $dompetTujuan->id_dompet)
            ->firstOrFail();

        $this->assertSame('Transfer dari Budi Santoso', $barisMasuk->displayNama());

        // Sesi pengirim (toast flash) tidak boleh bocor ke sesi penerima —
        // di produksi keduanya sesi terpisah.
        $this->flushSession();

        // Riwayat transaksi penerima menampilkan nama user, bukan dompet pengirim
        $this->actingAs($penerima)->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('Transfer dari Budi Santoso', false)
            ->assertDontSee('Dompet DANA Kirim', false);

        // Riwayat dompet penerima juga
        $this->actingAs($penerima)->get(route('dompet.history', $dompetTujuan))
            ->assertOk()
            ->assertSee('Transfer dari Budi Santoso', false)
            ->assertDontSee('Dompet DANA Kirim', false);

        // Notifikasi penerima memakai nama user pengirim (bukan dompetnya)
        $this->actingAs($penerima)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Anda di transfer oleh Budi Santoso', false)
            ->assertDontSee('Dompet DANA Kirim', false);

        // Sisi pengirim sekarang simetris: nama penerima, bukan dompetnya
        $this->actingAs($pengirim)->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('Transfer ke Siti Aminah', false);
    }

    public function test_own_wallet_transfer_still_shows_wallet_names(): void
    {
        [$user, $dompetUtama] = $this->userWithWallet('own-display@example.com', 'Dompet Utama', 100000);
        $dompetTabungan = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet Tabungan',
            'saldo_awal' => 0,
            'saldo' => 0,
        ]);

        $this->actingAs($user)->post(route('transfer.store'), [
            'id_dompet_asal' => $dompetUtama->id_dompet,
            'id_dompet_tujuan' => $dompetTabungan->id_dompet,
            'jumlah' => 50000,
            'tanggal_transfer' => '2026-10-05 11:00:00',
        ])->assertRedirect(route('transfer.index'));

        $transfer = Transfer::firstOrFail();
        $barisMasuk = $transfer->transaksi()
            ->where('id_dompet', $dompetTabungan->id_dompet)
            ->firstOrFail();

        // Antar dompet milik sendiri tetap memakai nama dompet (lebih informatif)
        $this->assertSame('Transfer dari Dompet Utama', $barisMasuk->displayNama());

        $this->actingAs($user)->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('Transfer dari Dompet Utama', false);
    }

    public function test_non_transfer_transaction_keeps_its_own_name(): void
    {
        [$user, $dompet] = $this->userWithWallet('reguler-display@example.com', 'Dompet Harian', 100000);

        $kategori = Kategori::create([
            'id_user' => $user->id_user,
            'nama_kategori' => 'Makanan',
            'jenis' => 'pengeluaran',
        ]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'id_dompet' => $dompet->id_dompet,
            'id_kategori' => $kategori->id_kategori,
            'nama_transaksi' => 'Makan Siang',
            'jumlah' => 25000,
            'jenis' => 'pengeluaran',
            'tanggal' => '2026-10-05 12:00:00',
        ])->assertRedirect(route('transaksi.index'));

        $this->actingAs($user)->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('Makan Siang', false);
    }

    public function test_large_transaction_amount_is_serialized_as_a_decimal_string(): void
    {
        [$user, $wallet] = $this->userWithWallet(
            'large-history@example.com',
            'Large Balance Wallet',
            1000000000000000
        );
        $category = Kategori::create([
            'id_user' => $user->id_user,
            'nama_kategori' => 'Large expense',
            'jenis' => 'pengeluaran',
        ]);
        \App\Models\Transaksi::create([
            'id_dompet' => $wallet->id_dompet,
            'id_kategori' => $category->id_kategori,
            'nama_transaksi' => 'Large transaction',
            'jumlah' => '1000000000000000.00',
            'jenis' => 'pengeluaran',
            'tanggal' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('1000000000000000.00', false);
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
            'mata_uang' => 'IDR',
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
