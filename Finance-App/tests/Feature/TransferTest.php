<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Transaksi;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_creates_parent_and_two_immutable_transactions_for_another_user(): void
    {
        [$pengirim, $dompetAsal] = $this->userWithWallet('pengirim@example.com', 'Dompet Pengirim', 100000);
        [$penerima, $dompetTujuan] = $this->userWithWallet('penerima@example.com', 'Dompet Penerima', 10000);

        $this->actingAs($pengirim)->post(route('transfer.store'), [
            'id_dompet_asal' => $dompetAsal->id_dompet,
            'id_dompet_tujuan' => $dompetTujuan->id_dompet,
            'jumlah' => 25000,
            'catatan' => 'Bantuan keluarga',
            'tanggal_transfer' => '2026-10-01 10:00:00',
        ])->assertRedirect(route('transfer.index'));

        $transfer = Transfer::firstOrFail();
        $this->assertSame($pengirim->id_user, $transfer->id_user);
        $this->assertDatabaseHas('dompet', ['id_dompet' => $dompetAsal->id_dompet, 'saldo' => 75000]);
        $this->assertDatabaseHas('dompet', ['id_dompet' => $dompetTujuan->id_dompet, 'saldo' => 35000]);
        $this->assertSame(2, $transfer->transaksi()->count());
        $this->assertSame(1, Transaksi::where('id_transfer', $transfer->id_transfer)->where('jenis', 'pengeluaran')->count());
        $this->assertSame(1, Transaksi::where('id_transfer', $transfer->id_transfer)->where('jenis', 'pemasukan')->count());

        $incoming = $transfer->transaksi()->where('id_dompet', $dompetTujuan->id_dompet)->firstOrFail();
        $this->actingAs($penerima)->get(route('transaksi.edit', $incoming))->assertStatus(405);
        $this->actingAs($penerima)->delete(route('transaksi.destroy', $incoming))->assertStatus(405);
    }

    public function test_transfer_is_rejected_when_source_balance_is_insufficient(): void
    {
        [$pengirim, $dompetAsal] = $this->userWithWallet('kurang@example.com', 'Dompet Pengirim', 1000);
        [, $dompetTujuan] = $this->userWithWallet('tujuan@example.com', 'Dompet Tujuan', 0);

        $this->actingAs($pengirim)->from(route('transfer.create'))->post(route('transfer.store'), [
            'id_dompet_asal' => $dompetAsal->id_dompet,
            'id_dompet_tujuan' => $dompetTujuan->id_dompet,
            'jumlah' => 1001,
            'tanggal_transfer' => '2026-10-01 10:00:00',
        ])->assertSessionHasErrors('jumlah');

        $this->assertSame(0, Transfer::count());
        $this->assertDatabaseHas('dompet', ['id_dompet' => $dompetAsal->id_dompet, 'saldo' => 1000]);
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
