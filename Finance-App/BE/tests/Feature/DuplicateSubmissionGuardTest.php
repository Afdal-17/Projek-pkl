<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateSubmissionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_double_tap_identical_transaction_is_recorded_only_once(): void
    {
        [$user, $dompet] = $this->userWithWallet('dobel@example.com', 100000);
        $kategori = $this->kategoriPengeluaran($user);

        $payload = $this->transaksiPayload($dompet, $kategori);

        $this->actingAs($user)->post(route('transaksi.store'), $payload)
            ->assertRedirect(route('transaksi.index'));

        $this->actingAs($user)->from(route('transaksi.create'))->post(route('transaksi.store'), $payload)
            ->assertRedirect(route('transaksi.create'))
            ->assertSessionHasErrors('nama_transaksi');

        $this->assertSame(1, Transaksi::count());
        $this->assertDatabaseHas('dompet', ['id_dompet' => $dompet->id_dompet, 'saldo' => 75000]);
    }

    public function test_double_tap_via_json_returns_clear_duplicate_message(): void
    {
        [$user, $dompet] = $this->userWithWallet('dobel-json@example.com', 100000);
        $kategori = $this->kategoriPengeluaran($user);

        $payload = $this->transaksiPayload($dompet, $kategori);

        $this->actingAs($user)->postJson(route('transactions.store'), $payload)
            ->assertRedirect(route('transaksi.index'));

        $this->actingAs($user)->postJson(route('transactions.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nama_transaksi');

        $this->assertSame(1, Transaksi::count());
    }

    public function test_different_transaction_payloads_are_both_recorded(): void
    {
        [$user, $dompet] = $this->userWithWallet('beda-payload@example.com', 100000);
        $kategori = $this->kategoriPengeluaran($user);

        $this->actingAs($user)->post(route('transaksi.store'), $this->transaksiPayload($dompet, $kategori))
            ->assertRedirect(route('transaksi.index'));
        $this->actingAs($user)->post(route('transaksi.store'), $this->transaksiPayload($dompet, $kategori, [
            'nama_transaksi' => 'Kopi susu',
            'jumlah' => 20000,
        ]))->assertRedirect(route('transaksi.index'));

        $this->assertSame(2, Transaksi::count());
        $this->assertDatabaseHas('dompet', ['id_dompet' => $dompet->id_dompet, 'saldo' => 55000]);
    }

    public function test_failed_transaction_releases_guard_so_user_can_retry(): void
    {
        [$user, $dompet] = $this->userWithWallet('gagal-lalu-ulang@example.com', 100000);
        $kategori = $this->kategoriPengeluaran($user);
        $kategoriPemasukan = Kategori::create([
            'id_user' => $user->id_user,
            'nama_kategori' => 'Gaji',
            'jenis' => 'pemasukan',
        ]);

        /* Pengeluaran 150rb dengan saldo 100rb → gagal, guard dilepas. */
        $this->actingAs($user)->post(route('transaksi.store'), $this->transaksiPayload($dompet, $kategori, [
            'jumlah' => 150000,
        ]))->assertSessionHasErrors('jumlah');

        /* Isi saldo dulu. */
        $this->actingAs($user)->post(route('transaksi.store'), $this->transaksiPayload($dompet, $kategoriPemasukan, [
            'nama_transaksi' => 'Top up',
            'jumlah' => 100000,
            'jenis' => 'pemasukan',
        ]))->assertRedirect(route('transaksi.index'));

        /* Payload yang tadinya gagal sekarang berhasil — tidak terkunci guard. */
        $this->actingAs($user)->post(route('transaksi.store'), $this->transaksiPayload($dompet, $kategori, [
            'jumlah' => 150000,
        ]))->assertRedirect(route('transaksi.index'));

        $this->assertSame(2, Transaksi::count());
        $this->assertDatabaseHas('dompet', ['id_dompet' => $dompet->id_dompet, 'saldo' => 50000]);
    }

    public function test_double_tap_identical_transfer_moves_money_only_once(): void
    {
        [$pengirim, $dompetAsal] = $this->userWithWallet('kirim-dobel@example.com', 100000);
        [$penerima, $dompetTujuan] = $this->userWithWallet('terima-dobel@example.com', 10000);

        $payload = [
            'id_dompet_asal' => $dompetAsal->id_dompet,
            'id_dompet_tujuan' => $dompetTujuan->id_dompet,
            'jumlah' => 25000,
            'tanggal_transfer' => '2026-10-01 10:00:00',
        ];

        $this->actingAs($pengirim)->from(route('transfer.index'))->post(route('transfer.store'), $payload)
            ->assertRedirect(route('transfer.index'));

        $this->actingAs($pengirim)->from(route('transfer.index'))->post(route('transfer.store'), $payload)
            ->assertRedirect(route('transfer.index'))
            ->assertSessionHasErrors('jumlah');

        $this->assertSame(1, Transfer::count());
        $this->assertDatabaseHas('dompet', ['id_dompet' => $dompetAsal->id_dompet, 'saldo' => 75000]);
        $this->assertDatabaseHas('dompet', ['id_dompet' => $dompetTujuan->id_dompet, 'saldo' => 35000]);
        $this->assertSame(2, Transaksi::count()); /* 1 pengeluaran + 1 penerimaan */

        $this->assertNotNull($penerima->id_user);
    }

    public function test_double_tap_identical_saving_target_is_recorded_only_once(): void
    {
        [$user, $dompet] = $this->userWithWallet('tabungan-dobel@example.com', 100000);

        $payload = [
            'id_dompet' => $dompet->id_dompet,
            'nama_target' => 'Liburan',
            'deskripsi' => 'Ke Bali',
            'nominal_target' => 5000000,
            'initial_savings' => 100000,
        ];

        $this->actingAs($user)->from(route('saving.create'))->post(route('saving.store'), $payload)
            ->assertRedirect(route('target-tabungan.index'));

        $this->actingAs($user)->from(route('saving.create'))->post(route('saving.store'), $payload)
            ->assertRedirect(route('saving.create'))
            ->assertSessionHasErrors('nama_target');

        $this->assertSame(1, TargetTabungan::count());
    }

    private function userWithWallet(string $email, int $saldo): array
    {
        $user = User::create([
            'nama' => 'User Guard',
            'email' => $email,
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);
        $dompet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet '.$email,
            'saldo_awal' => $saldo,
            'saldo' => $saldo,
        ]);

        return [$user, $dompet];
    }

    private function kategoriPengeluaran(User $user): Kategori
    {
        return Kategori::create([
            'id_user' => $user->id_user,
            'nama_kategori' => 'Makanan',
            'jenis' => 'pengeluaran',
        ]);
    }

    private function transaksiPayload(Dompet $dompet, Kategori $kategori, array $override = []): array
    {
        return array_merge([
            'id_dompet' => $dompet->id_dompet,
            'id_kategori' => $kategori->id_kategori,
            'nama_transaksi' => 'Makan siang',
            'jumlah' => 25000,
            'jenis' => 'pengeluaran',
            'tanggal' => '2026-10-05 12:00:00',
        ], $override);
    }
}
