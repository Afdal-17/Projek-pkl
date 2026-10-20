<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Transfer;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Skenario terberat: user pernah menerima transfer dari user lain DAN
     * pernah transfer antar dompet sendiri. Dulu ini error 500 (FK
     * restrictOnDelete pada transfer → dompet). Sekarang harus sukses:
     * data user terhapus, riwayat pihak lain tetap utuh (id_transfer NULL).
     */
    public function test_user_can_delete_account_with_cross_user_transfer_history(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $pengirim = User::factory()->create();

        $dompetMilikSaya = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet Saya',
        ]);
        $dompetMilikSayaKedua = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet Saya Kedua',
        ]);
        $dompetPengirim = Dompet::create([
            'id_user' => $pengirim->id_user,
            'nama_dompet' => 'Dompet Pengirim',
        ]);

        // Incoming transfer dari user lain + baris turunannya di kedua sisi.
        $transferMasuk = Transfer::create([
            'id_user' => $pengirim->id_user,
            'id_dompet_asal' => $dompetPengirim->id_dompet,
            'id_dompet_tujuan' => $dompetMilikSaya->id_dompet,
            'jumlah' => 50000,
            'tanggal_transfer' => now(),
        ]);
        Transaksi::create([
            'id_transfer' => $transferMasuk->id_transfer,
            'id_dompet' => $dompetPengirim->id_dompet,
            'nama_transaksi' => 'Transfer ke Dompet Saya',
            'jumlah' => 50000,
            'jenis' => 'pengeluaran',
            'tanggal' => now(),
        ]);
        Transaksi::create([
            'id_transfer' => $transferMasuk->id_transfer,
            'id_dompet' => $dompetMilikSaya->id_dompet,
            'nama_transaksi' => 'Transfer dari Dompet Pengirim',
            'jumlah' => 50000,
            'jenis' => 'pemasukan',
            'tanggal' => now(),
        ]);

        // Transfer antar dompet milik user sendiri.
        Transfer::create([
            'id_user' => $user->id_user,
            'id_dompet_asal' => $dompetMilikSaya->id_dompet,
            'id_dompet_tujuan' => $dompetMilikSayaKedua->id_dompet,
            'jumlah' => 10000,
            'tanggal_transfer' => now(),
        ]);

        $response = $this->actingAs($user)->delete(route('profile.destroy'), [
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertGuest();

        // Data user dan seluruh turunannya ikut terhapus.
        $this->assertDatabaseMissing('user', ['id_user' => $user->id_user]);
        $this->assertDatabaseMissing('dompet', ['id_dompet' => $dompetMilikSaya->id_dompet]);
        $this->assertDatabaseMissing('dompet', ['id_dompet' => $dompetMilikSayaKedua->id_dompet]);
        $this->assertDatabaseMissing('transfer', ['id_transfer' => $transferMasuk->id_transfer]);

        // Pihak lain tidak dirugikan: dompet tetap ada, riwayat ledger tetap
        // ada sebagai baris transaksi dengan id_transfer NULL (nullOnDelete).
        $this->assertDatabaseHas('dompet', ['id_dompet' => $dompetPengirim->id_dompet]);
        $this->assertDatabaseHas('transaksi', [
            'id_dompet' => $dompetPengirim->id_dompet,
            'jenis' => 'pengeluaran',
            'id_transfer' => null,
        ]);
    }

    public function test_wrong_password_blocks_account_deletion(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->actingAs($user)->from('/profile')->delete(route('profile.destroy'), [
            'password' => 'bukan-password-saya',
        ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('user', ['id_user' => $user->id_user]);
    }
}
