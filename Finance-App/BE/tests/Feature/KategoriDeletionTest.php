<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KategoriDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_in_use_returns_indonesian_422_message(): void
    {
        [$user, $category] = $this->categoryWithTransaction();

        $this->actingAs($user)
            ->deleteJson(route('categories.destroy', $category))
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Kategori "Makanan" tidak dapat dihapus karena masih digunakan oleh 1 transaksi. Ubah atau hapus transaksi terkait terlebih dahulu.'
            );

        $this->assertDatabaseHas('kategori', ['id_kategori' => $category->id_kategori]);
        $this->assertDatabaseCount('transaksi', 1);
    }

    public function test_repeated_delete_does_not_delete_more_than_the_requested_category(): void
    {
        $user = User::factory()->create();
        $category = Kategori::create([
            'id_user' => $user->id_user,
            'nama_kategori' => 'Perjalanan',
            'jenis' => 'pengeluaran',
        ]);

        $this->actingAs($user)->delete(route('categories.destroy', $category))
            ->assertRedirect(route('kategori.index'));
        $this->actingAs($user)->delete(route('categories.destroy', $category))
            ->assertNotFound();

        $this->assertDatabaseMissing('kategori', ['id_kategori' => $category->id_kategori]);
    }

    private function categoryWithTransaction(): array
    {
        $user = User::factory()->create();
        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet',
            'saldo_awal' => 10000,
            'saldo' => 10000,
        ]);
        $category = Kategori::create([
            'id_user' => $user->id_user,
            'nama_kategori' => 'Makanan',
            'jenis' => 'pengeluaran',
        ]);
        Transaksi::create([
            'id_dompet' => $wallet->id_dompet,
            'id_kategori' => $category->id_kategori,
            'nama_transaksi' => 'Makan siang',
            'jumlah' => 1000,
            'jenis' => 'pengeluaran',
            'tanggal' => now(),
        ]);

        return [$user, $category];
    }
}
