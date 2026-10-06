<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\User;
use App\Models\Transaksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransaksiTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_changes_wallet_balance_and_reverses_on_update_and_delete(): void
    {
        $user = User::create([
            'nama' => 'Test User',
            'email' => 'transaksi@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
        ]);
        $dompet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet Test',
            'saldo_awal' => 100000,
            'saldo' => 100000,
        ]);
        $pengeluaran = Kategori::create([
            'id_user' => $user->id_user,
            'nama_kategori' => 'Makan',
            'jenis' => 'pengeluaran',
        ]);
        $pemasukan = Kategori::create([
            'id_user' => $user->id_user,
            'nama_kategori' => 'Gaji',
            'jenis' => 'pemasukan',
        ]);

        $this->actingAs($user)->post(route('transaksi.store'), [
            'id_dompet' => $dompet->id_dompet,
            'id_kategori' => $pengeluaran->id_kategori,
            'nama_transaksi' => 'Makan siang',
            'jumlah' => 25000,
            'jenis' => 'pengeluaran',
            'tanggal' => '2026-10-01 12:00:00',
        ])->assertRedirect(route('transaksi.index'));

        $this->assertDatabaseHas('dompet', ['id_dompet' => $dompet->id_dompet, 'saldo' => 75000]);
        $transaksi = Transaksi::where('id_dompet', $dompet->id_dompet)->firstOrFail();

        $this->actingAs($user)->put(route('transaksi.update', $transaksi), [
            'id_dompet' => $dompet->id_dompet,
            'id_kategori' => $pemasukan->id_kategori,
            'nama_transaksi' => 'Bonus',
            'jumlah' => 50000,
            'jenis' => 'pemasukan',
            'tanggal' => '2026-10-01 13:00:00',
        ])->assertRedirect(route('transaksi.index'));

        $this->assertDatabaseHas('dompet', ['id_dompet' => $dompet->id_dompet, 'saldo' => 150000]);

        $this->actingAs($user)->delete(route('transaksi.destroy', $transaksi))->assertRedirect(route('transaksi.index'));
        $this->assertDatabaseHas('dompet', ['id_dompet' => $dompet->id_dompet, 'saldo' => 100000]);
    }

    public function test_transaction_history_can_be_filtered(): void
    {
        $user = User::create([
            'nama' => 'Filter User',
            'email' => 'filter@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
        ]);
        $dompet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet Filter',
            'saldo_awal' => 100000,
            'saldo' => 100000,
        ]);
        $kategori = Kategori::create([
            'id_user' => $user->id_user,
            'nama_kategori' => 'Gaji',
            'jenis' => 'pemasukan',
        ]);

        foreach ([['Pertama', '2026-09-01 08:00:00'], ['Kedua', '2026-10-01 08:00:00']] as [$nama, $tanggal]) {
            $this->actingAs($user)->post(route('transaksi.store'), [
                'id_dompet' => $dompet->id_dompet,
                'id_kategori' => $kategori->id_kategori,
                'nama_transaksi' => $nama,
                'jumlah' => 1000,
                'jenis' => 'pemasukan',
                'tanggal' => $tanggal,
            ])->assertRedirect(route('transaksi.index'));
        }

        $response = $this->actingAs($user)->get(route('transaksi.index', [
            'q' => 'Kedua',
            'tanggal_mulai' => '2026-10-01',
            'tanggal_selesai' => '2026-10-01',
        ]));

        $response->assertOk()->assertViewHas('transaksi', fn ($items) => $items->total() === 1
            && $items->first()->nama_transaksi === 'Kedua');
    }

    public function test_merged_transaction_pages_render_owned_backend_data(): void
    {
        $user = User::create([
            'nama' => 'Frontend User',
            'email' => 'frontend@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
        ]);
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Frontend Wallet',
            'saldo_awal' => 100000,
            'saldo' => 100000,
        ]);
        $category = Kategori::create([
            'id_user' => $user->id_user,
            'nama_kategori' => 'Frontend Category',
            'jenis' => 'pengeluaran',
        ]);
        Transaksi::create([
            'id_dompet' => $wallet->id_dompet,
            'id_kategori' => $category->id_kategori,
            'nama_transaksi' => 'Frontend Transaction',
            'jumlah' => 5000,
            'jenis' => 'pengeluaran',
            'tanggal' => now(),
        ]);

        $this->actingAs($user)->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('Frontend Transaction')
            ->assertSee('Frontend Wallet')
            ->assertSee('Frontend Category');

        $this->actingAs($user)->get(route('transactions.create'))
            ->assertOk()
            ->assertSee('Frontend Wallet')
            ->assertSee('Frontend Category');

        $this->actingAs($user)->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Frontend Category');
    }
}
