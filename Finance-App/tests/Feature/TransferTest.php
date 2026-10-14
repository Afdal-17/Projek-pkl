<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Transaksi;
use App\Models\Transfer;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_creates_parent_and_two_immutable_transactions_for_another_user(): void
    {
        [$pengirim, $dompetAsal] = $this->userWithWallet('pengirim@example.com', 'Dompet Pengirim', 100000);
        [$penerima, $dompetTujuan] = $this->userWithWallet('penerima@example.com', 'Dompet Penerima', 10000);

        $this->actingAs($pengirim)->get(route('transfer.index'))
            ->assertOk()
            ->assertSee('Transfer Wallet & User')
            ->assertSee('Dompet Pengirim');
        $this->actingAs($pengirim)->get(route('transfer.recipients', ['query' => $penerima->email]))
            ->assertOk()
            ->assertJsonPath('id_dompet_tujuan', $dompetTujuan->id_dompet);

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

    public function test_user_search_returns_matching_users_by_name(): void
    {
        [$pengirim] = $this->userWithWallet('pengirim-search@example.com', 'Dompet Pengirim', 100000);

        // Beberapa calon penerima dengan nama berbeda
        [$alpha] = $this->userWithWallet('alpha@example.com', 'Dompet Alpha', 10000);
        User::where('id_user', $alpha->id_user)->update(['nama' => 'Alpha User']);

        [$beta] = $this->userWithWallet('beta@example.com', 'Dompet Beta', 10000);
        User::where('id_user', $beta->id_user)->update(['nama' => 'Beta User']);

        [$gamma] = $this->userWithWallet('gamma@example.com', 'Dompet Gamma', 10000);
        User::where('id_user', $gamma->id_user)->update(['nama' => 'Gamma User']);

        // Pencarian berdasarkan sebagian nama
        $response = $this->actingAs($pengirim)->get(route('transfer.recipients.search', ['query' => 'User']));

        $response->assertOk();
        $this->assertCount(3, $response->json());

        // Pencarian lebih spesifik
        $response = $this->actingAs($pengirim)->get(route('transfer.recipients.search', ['query' => 'Alpha']));
        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame('Alpha User', $response->json()[0]['nama']);
        $this->assertSame('Dompet Alpha', $response->json()[0]['wallets'][0]['name']);

        // Pencarian email
        $response = $this->actingAs($pengirim)->get(route('transfer.recipients.search', ['query' => 'beta@example.com']));
        $response->assertOk();
        $this->assertCount(1, $response->json());

        // Pengirim tidak muncul di hasil (tidak bisa transfer ke diri sendiri)
        $response = $this->actingAs($pengirim)->get(route('transfer.recipients.search', ['query' => 'pengirim']));
        $response->assertOk();
        $this->assertCount(0, $response->json());
    }

    public function test_user_search_shows_users_without_wallet_as_not_selectable(): void
    {
        [$pengirim] = $this->userWithWallet('pengirim-nodompet@example.com', 'Dompet Pengirim', 100000);

        // User aktif & terverifikasi tetapi BELUM memiliki dompet
        $tanpaDompet = User::create([
            'nama' => 'Tanpa Dompet',
            'email' => 'tanpadompet@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($pengirim)->get(route('transfer.recipients.search', ['query' => 'Tanpa']));

        $response->assertOk();
        $results = $response->json();
        $this->assertCount(1, $results);
        $this->assertSame('Tanpa Dompet', $results[0]['nama']);
        $this->assertFalse($results[0]['has_wallet']);
        $this->assertSame([], $results[0]['wallets']);
        $this->assertNull($results[0]['id_dompet_tujuan']);
    }

    public function test_user_search_returns_all_recipient_wallets(): void
    {
        [$pengirim] = $this->userWithWallet('pengirim-multwallet@example.com', 'Dompet Pengirim', 100000);

        $penerima = User::create([
            'nama' => 'Multi Wallet',
            'email' => 'multiwallet@example.com',
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
        ]);

        Dompet::create([
            'id_user' => $penerima->id_user,
            'nama_dompet' => 'Dompet Tunai',
            'saldo_awal' => 50000,
            'saldo' => 50000,
        ]);

        Dompet::create([
            'id_user' => $penerima->id_user,
            'nama_dompet' => 'Dompet Bank',
            'saldo_awal' => 75000,
            'saldo' => 75000,
        ]);

        $response = $this->actingAs($pengirim)->get(route('transfer.recipients.search', ['query' => 'Multi']));

        $response->assertOk();
        $results = $response->json();
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['has_wallet']);

        // Semua dompet penerima dikembalikan, diurutkan berdasarkan nama (saldo dirahasiakan demi privasi)
        $this->assertCount(2, $results[0]['wallets']);
        $this->assertSame('Dompet Bank', $results[0]['wallets'][0]['name']);
        $this->assertArrayNotHasKey('balance', $results[0]['wallets'][0]);
        $this->assertSame('Dompet Tunai', $results[0]['wallets'][1]['name']);
        $this->assertArrayNotHasKey('balance', $results[0]['wallets'][1]);
    }

    public function test_transfer_rejected_when_recipient_user_is_banned(): void
    {
        [$pengirim, $dompetAsal] = $this->userWithWallet('pengirim_banned_test@example.com', 'Dompet Pengirim', 100000);
        [$penerima, $dompetTujuan] = $this->userWithWallet('penerima_banned_test@example.com', 'Dompet Penerima', 10000);

        $penerima->update([
            'banned_at' => now(),
            'ban_reason' => 'Melanggar ketentuan',
        ]);

        $response = $this->actingAs($pengirim)->post(route('transfer.store'), [
            'id_dompet_asal' => $dompetAsal->id_dompet,
            'id_dompet_tujuan' => $dompetTujuan->id_dompet,
            'jumlah' => 20000,
            'tanggal_transfer' => '2026-10-01 10:00:00',
        ]);

        $response->assertSessionHasErrors('id_dompet_tujuan');
    }

    public function test_user_search_includes_banned_status_for_banned_user(): void
    {
        [$pengirim, $dompetAsal] = $this->userWithWallet('pengirim_search_banned@example.com', 'Dompet Pengirim', 100000);
        [$penerima, $dompetTujuan] = $this->userWithWallet('penerima_search_banned@example.com', 'Dompet Penerima', 10000);

        $penerima->update([
            'banned_at' => now(),
            'ban_reason' => 'Penyalahgunaan akun',
        ]);

        $response = $this->actingAs($pengirim)->get(route('transfer.recipients.search', ['query' => 'penerima_search_banned']));

        $response->assertOk();
        $results = $response->json();
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['is_banned']);
        $this->assertArrayNotHasKey('ban_reason', $results[0]);
    }

    public function test_transfer_from_eur_sender_to_idr_receiver_converts_correctly(): void
    {
        // Pengirim memakai EUR, penerima memakai IDR. Seluruh nilai di database
        // disimpan dalam IDR (base), jadi transfer harus dikonversi dengan tepat.
        [$pengirim, $dompetAsal] = $this->userWithWallet('eu@example.com', 'Dompet EUR', 1000000, 'EUR');
        [$penerima, $dompetTujuan] = $this->userWithWallet('id@example.com', 'Dompet IDR', 50000, 'IDR');

        $this->assertSame('EUR', $pengirim->mata_uang);
        $this->assertSame('IDR', $penerima->mata_uang);

        // Backend menerima "jumlah" dalam mata uang aktif pengirim (EUR) lalu
        // mengonversinya ke IDR base. 50 EUR dengan rate EUR harus menjadi 50*rate.
        $rateEur = CurrencyService::rates()['EUR'];
        $jumlahEur = 50.0;
        $jumlahDalamIdr = $jumlahEur * $rateEur;

        $this->actingAs($pengirim)->post(route('transfer.store'), [
            'id_dompet_asal' => $dompetAsal->id_dompet,
            'id_dompet_tujuan' => $dompetTujuan->id_dompet,
            'jumlah' => $jumlahEur,
            'tanggal_transfer' => '2026-10-02 09:00:00',
        ])->assertRedirect(route('transfer.index'));

        $transfer = Transfer::firstOrFail();
        // Nilai tersimpan dalam IDR, presisi 2 desimal
        $this->assertEqualsWithDelta($jumlahDalamIdr, (float) $transfer->jumlah, 0.01);

        // Saldo kedua dompet (keduanya disimpan dalam IDR) berkurang & bertambah
        // sebesar nilai yang sama dalam IDR — bukan sebesar "50" mentah.
        $saldoAsal = (float) Dompet::findOrFail($dompetAsal->id_dompet)->saldo;
        $saldoTujuan = (float) Dompet::findOrFail($dompetTujuan->id_dompet)->saldo;

        $this->assertEqualsWithDelta(1000000 - $jumlahDalamIdr, $saldoAsal, 0.01);
        $this->assertEqualsWithDelta(50000 + $jumlahDalamIdr, $saldoTujuan, 0.01);

        // Total keseluruhan uang tetap terjaga (tidak ada nilai hilang/tambahan
        // akibat pembulatan antar mata uang).
        $this->assertEqualsWithDelta(1050000, $saldoAsal + $saldoTujuan, 0.01);
    }

    public function test_transfer_rejects_amount_exceeding_source_balance_in_sender_currency(): void
    {
        // Dompet asal berisi 1.000.000 IDR. Dalam EUR (rate 20000) itu hanya
        // setara 50 EUR, jadi mengirim 60 EUR harus ditolak karena melebihi saldo.
        [$pengirim, $dompetAsal] = $this->userWithWallet('eux@example.com', 'Dompet EUR Kecil', 1000000, 'EUR');
        [$penerima, $dompetTujuan] = $this->userWithWallet('idx@example.com', 'Dompet IDR Tujuan', 0, 'IDR');

        $rateEur = CurrencyService::rates()['EUR'];
        $melebihi = (1000000 / $rateEur) + 10; // jauh di atas saldo yang tersedia

        $this->actingAs($pengirim)->post(route('transfer.store'), [
            'id_dompet_asal' => $dompetAsal->id_dompet,
            'id_dompet_tujuan' => $dompetTujuan->id_dompet,
            'jumlah' => $melebihi,
            'tanggal_transfer' => '2026-10-02 09:00:00',
        ])->assertSessionHasErrors('jumlah');

        $this->assertDatabaseCount('transfer', 0);
        // Saldo tidak berubah sama sekali
        $this->assertEqualsWithDelta(1000000, (float) Dompet::findOrFail($dompetAsal->id_dompet)->saldo, 0.01);
    }

    private function userWithWallet(string $email, string $walletName, int $balance, string $currency = 'IDR'): array
    {
        $user = User::create([
            'nama' => $email,
            'email' => $email,
            'password' => 'password',
            'role' => 'user',
            'status' => true,
            'email_verified_at' => now(),
            'mata_uang' => $currency,
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
