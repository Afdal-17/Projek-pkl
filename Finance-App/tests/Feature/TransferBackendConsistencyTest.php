<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Notifikasi;
use App\Models\Transaksi;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 12 Pengujian Regresi Wajib Backend Finance App:
 * 1. Transfer berhasil dengan saldo pengirim dan penerima yang benar.
 * 2. Transfer gagal tidak mengubah saldo maupun membuat histori parsial.
 * 3. Notifikasi berhasil disimpan menggunakan skema terbaru.
 * 4. Penanganan database lama yang belum dimigrasikan jelas dan teruji.
 * 5. Hanya satu dompet utama transfer per pengguna (tidak ada promosi diam-diam).
 * 6. Penggantian dompet utama menonaktifkan dompet sebelumnya.
 * 7. Pengguna tidak dapat mengubah dompet milik orang lain.
 * 8. Transfer ke dompet sumber sendiri ditolak.
 * 9. Saldo tidak mencukupi dan akun tidak aktif/banned ditolak.
 * 10. Request ganda dengan token idempotensi tidak menggandakan transaksi.
 * 11. Request bersamaan terlindungi dengan penguncian baris (mencegah saldo negatif).
 * 12. Penghapusan dompet utama tidak meninggalkan promosi dompet palsu.
 */
class TransferBackendConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email, bool $active = true, bool $banned = false, bool $verified = true): User
    {
        return User::create([
            'nama'              => $email,
            'email'             => $email,
            'password'          => 'password',
            'role'              => 'user',
            'status'            => $active,
            'banned_at'         => $banned ? now() : null,
            'email_verified_at' => $verified ? now() : null,
            'mata_uang'         => 'IDR',
        ]);
    }

    private function makeWallet(User $user, string $name, string|int $balance, bool $terima = false): Dompet
    {
        return Dompet::create([
            'id_user'         => $user->id_user,
            'nama_dompet'     => $name,
            'saldo_awal'      => (string) $balance,
            'saldo'           => (string) $balance,
            'terima_transfer' => $terima,
        ]);
    }

    public function test_transfer_page_renders_persistent_retry_key_support(): void
    {
        $user = $this->makeUser('transfer-page@example.com');
        $this->makeWallet($user, 'Transfer Page Wallet', 500_000);

        $this->actingAs($user)
            ->get(route('transfer.index'))
            ->assertOk()
            ->assertSee('finance.transfer.idempotency.', false)
            ->assertSee('Status transfer belum dapat dipastikan.', false);
    }

    /**
     * 1. Transfer valid: saldo berkurang di pengirim dan bertambah di penerima secara konsisten.
     */
    public function test_1_valid_transfer_adjusts_sender_and_receiver_balances_correctly(): void
    {
        $sender   = $this->makeUser('sender-bal@example.com');
        $receiver = $this->makeUser('receiver-bal@example.com');
        $source   = $this->makeWallet($sender, 'Source', 500_000);
        $target   = $this->makeWallet($receiver, 'Target', 100_000, true);

        $this->actingAs($sender)->post(route('transfer.store'), [
            'id_dompet_asal'   => $source->id_dompet,
            'id_dompet_tujuan' => $target->id_dompet,
            'jumlah'           => 75_000,
            'tanggal_transfer' => '2026-10-20',
        ])->assertRedirect(route('transfer.index'));

        $this->assertDatabaseHas('dompet', ['id_dompet' => $source->id_dompet, 'saldo' => '425000.00']);
        $this->assertDatabaseHas('dompet', ['id_dompet' => $target->id_dompet, 'saldo' => '175000.00']);

        // Tepat dua transaksi: debit pengirim, kredit penerima
        $transfer = Transfer::firstOrFail();
        $this->assertSame(2, $transfer->transaksi()->count());
        $this->assertSame(1, $transfer->transaksi()->where('jenis', 'pengeluaran')->count());
        $this->assertSame(1, $transfer->transaksi()->where('jenis', 'pemasukan')->count());

        // Total uang dalam sistem tetap seimbang
        $totalAfter = (float) Dompet::whereIn('id_dompet', [$source->id_dompet, $target->id_dompet])->sum('saldo');
        $this->assertEqualsWithDelta(600_000.0, $totalAfter, 0.01);
    }

    /**
     * 2. Transfer gagal tidak mengubah saldo maupun membuat histori parsial.
     */
    public function test_2_failed_transfer_leaves_no_partial_balance_change_or_history(): void
    {
        $sender   = $this->makeUser('sender-rollback@example.com');
        $receiver = $this->makeUser('receiver-rollback@example.com');
        $source   = $this->makeWallet($sender, 'Source Rollback', 200_000);
        $target   = $this->makeWallet($receiver, 'Target Rollback', 50_000, true);

        // Pasang callback error saat Transaksi disimpan untuk mensimulasikan kegagalan database
        Transaksi::creating(function (): void {
            static $failOnce = true;
            if ($failOnce) {
                $failOnce = false;
                throw new \RuntimeException('Simulated catastrophic DB error during transaction record creation');
            }
        });

        try {
            $this->actingAs($sender)->post(route('transfer.store'), [
                'id_dompet_asal'   => $source->id_dompet,
                'id_dompet_tujuan' => $target->id_dompet,
                'jumlah'           => 50_000,
                'tanggal_transfer' => '2026-10-20',
            ]);
        } catch (\Throwable) {
            // Expected exception
        }

        // Saldo kedua dompet TIDAK BOLEH berubah
        $this->assertDatabaseHas('dompet', ['id_dompet' => $source->id_dompet, 'saldo' => '200000.00']);
        $this->assertDatabaseHas('dompet', ['id_dompet' => $target->id_dompet, 'saldo' => '50000.00']);

        // Tidak boleh ada transfer atau transaksi tersisa
        $this->assertDatabaseCount('transfer', 0);
        $this->assertDatabaseCount('transaksi', 0);
        $this->assertDatabaseCount('transfer_idempotency_keys', 0);
    }

    /**
     * 3. Notifikasi transfer tersimpan dengan kolom jumlah & pesan_template (skema terbaru).
     */
    public function test_3_transfer_notification_is_saved_with_jumlah_and_template(): void
    {
        $sender   = $this->makeUser('sender-notif@example.com');
        $receiver = $this->makeUser('receiver-notif@example.com');
        $source   = $this->makeWallet($sender, 'Source Notif', 200_000);
        $target   = $this->makeWallet($receiver, 'Target Notif', 50_000, true);

        $this->actingAs($sender)->post(route('transfer.store'), [
            'id_dompet_asal'   => $source->id_dompet,
            'id_dompet_tujuan' => $target->id_dompet,
            'jumlah'           => 30_000,
            'tanggal_transfer' => '2026-10-20',
        ])->assertRedirect();

        $this->assertSame(2, Notifikasi::count());

        $senderNotif = Notifikasi::where('id_user', $sender->id_user)->first();
        $this->assertNotNull($senderNotif);
        $this->assertNotNull($senderNotif->jumlah);
        $this->assertNotNull($senderNotif->pesan_template);
        $this->assertStringContainsString('{jumlah}', $senderNotif->pesan_template);

        $receiverNotif = Notifikasi::where('id_user', $receiver->id_user)->first();
        $this->assertNotNull($receiverNotif);
        $this->assertNotNull($receiverNotif->jumlah);
        $this->assertNotNull($receiverNotif->pesan_template);
    }

    /**
     * 4. Penanganan database lama tanpa kolom jumlah / pesan_template teruji & tidak error.
     */
    public function test_4_legacy_database_without_jumlah_column_is_handled_gracefully(): void
    {
        $user = $this->makeUser('legacy-user@example.com');

        // Buat tabel notifikasi_legacy tanpa kolom jumlah dan pesan_template
        Schema::dropIfExists('notifikasi_legacy');
        Schema::create('notifikasi_legacy', function ($table) {
            $table->id('id_notifikasi');
            $table->unsignedBigInteger('id_user');
            $table->string('tipe');
            $table->text('pesan');
            $table->boolean('sudah_dibaca')->default(false);
            $table->timestamp('tanggal')->nullable();
        });

        // Mock instance model Notifikasi untuk menunjuk ke tabel legacy
        $legacyModel = new class extends Notifikasi {
            protected $table = 'notifikasi_legacy';
        };

        // Buat record notifikasi dengan atribut jumlah dan pesan_template
        // Model harus aman menyimpan tanpa melempar SQLSTATE[42S22]
        $legacyModel->flushColumnCache();
        $record = new $legacyModel([
            'id_user'        => $user->id_user,
            'tipe'           => 'transaksi',
            'pesan'          => 'Transfer of Rp 50.000 was successful.',
            'jumlah'         => '50000.00',
            'pesan_template' => 'Transfer of {jumlah} was successful.',
            'sudah_dibaca'   => false,
            'tanggal'        => now(),
        ]);
        $record->save();

        $this->assertDatabaseHas('notifikasi_legacy', [
            'id_user' => $user->id_user,
            'pesan'   => 'Transfer of Rp 50.000 was successful.',
        ]);

        Schema::dropIfExists('notifikasi_legacy');
        Notifikasi::flushColumnCache();
    }

    /**
     * 5. Hanya satu dompet utama transfer per pengguna, dan tidak ada pemaksaan dompet pertama secara diam-diam.
     */
    public function test_5_user_has_no_primary_wallet_by_default_and_sets_at_most_one(): void
    {
        $user = $this->makeUser('no-silent-primary@example.com');

        // Saat membuat dompet pertama, terima_transfer TIDAK BOLEH otomatis diset true secara diam-diam
        $wallet1 = $this->makeWallet($user, 'First Wallet', 100_000);
        $this->assertFalse((bool) $wallet1->fresh()->terima_transfer, 'First wallet must NOT be silently set as transfer wallet');

        $wallet2 = $this->makeWallet($user, 'Second Wallet', 200_000);
        $this->assertFalse((bool) $wallet2->fresh()->terima_transfer);

        // Pengguna secara eksplisit memilih wallet 2
        $this->actingAs($user)->patchJson(route('dompet.toggleTransfer', $wallet2), [
            'set_primary' => true,
        ])->assertOk()->assertJson(['status' => 'ok', 'terima_transfer' => true]);

        $this->assertTrue((bool) $wallet2->fresh()->terima_transfer);
        $this->assertFalse((bool) $wallet1->fresh()->terima_transfer);

        // Hanya tepat satu dompet utama
        $count = Dompet::where('id_user', $user->id_user)->where('terima_transfer', true)->count();
        $this->assertSame(1, $count);
    }

    /**
     * 6. Penggantian dompet utama menonaktifkan dompet sebelumnya secara atomik.
     */
    public function test_6_switching_primary_wallet_deactivates_previous_primary_wallet(): void
    {
        $user    = $this->makeUser('switch-primary@example.com');
        $walletA = $this->makeWallet($user, 'Wallet A', 100_000, true);
        $walletB = $this->makeWallet($user, 'Wallet B', 200_000, false);

        $this->actingAs($user)->patchJson(route('dompet.toggleTransfer', $walletB), [
            'set_primary' => true,
        ])->assertOk();

        $this->assertTrue((bool) $walletB->fresh()->terima_transfer);
        $this->assertFalse((bool) $walletA->fresh()->terima_transfer);
    }

    /**
     * 7. Pengguna tidak dapat mengubah dompet milik orang lain (403/404 ditolak).
     */
    public function test_7_user_cannot_modify_transfer_setting_of_another_users_wallet(): void
    {
        $userA   = $this->makeUser('user-a@example.com');
        $userB   = $this->makeUser('user-b@example.com');
        $walletB = $this->makeWallet($userB, 'Wallet B', 100_000, false);

        $this->actingAs($userA)->patchJson(route('dompet.toggleTransfer', $walletB), [
            'set_primary' => true,
        ])->assertStatus(404);

        $this->assertFalse((bool) $walletB->fresh()->terima_transfer);
    }

    /**
     * 8. Transfer ke dompet sumber sendiri ditolak di validasi backend.
     */
    public function test_8_transfer_to_same_source_wallet_is_rejected(): void
    {
        $user   = $this->makeUser('same-wallet@example.com');
        $wallet = $this->makeWallet($user, 'Single Wallet', 100_000, true);

        $this->actingAs($user)->postJson(route('transfer.store'), [
            'id_dompet_asal'   => $wallet->id_dompet,
            'id_dompet_tujuan' => $wallet->id_dompet,
            'jumlah'           => 10_000,
            'tanggal_transfer' => '2026-10-20',
        ])->assertUnprocessable()->assertJsonValidationErrors('id_dompet_tujuan');

        $this->assertDatabaseCount('transfer', 0);
        $this->assertDatabaseHas('dompet', ['id_dompet' => $wallet->id_dompet, 'saldo' => '100000.00']);
    }

    /**
     * 9. Saldo tidak mencukupi, akun nonaktif, atau akun dibanned ditolak.
     */
    public function test_9_insufficient_balance_and_inactive_or_banned_accounts_are_rejected(): void
    {
        $sender   = $this->makeUser('sender-check@example.com');
        $receiver = $this->makeUser('receiver-check@example.com');
        $source   = $this->makeWallet($sender, 'Source Check', 5_000);
        $target   = $this->makeWallet($receiver, 'Target Check', 0, true);

        // Saldo tidak mencukupi
        $this->actingAs($sender)->from(route('transfer.create'))->post(route('transfer.store'), [
            'id_dompet_asal'   => $source->id_dompet,
            'id_dompet_tujuan' => $target->id_dompet,
            'jumlah'           => 10_000,
            'tanggal_transfer' => '2026-10-20',
        ])->assertSessionHasErrors('jumlah');

        // Penerima nonaktif
        $receiver->update(['status' => false]);
        $this->actingAs($sender)->from(route('transfer.create'))->post(route('transfer.store'), [
            'id_dompet_asal'   => $source->id_dompet,
            'id_dompet_tujuan' => $target->id_dompet,
            'jumlah'           => 1_000,
            'tanggal_transfer' => '2026-10-20',
        ])->assertSessionHasErrors('id_dompet_tujuan');

        // Pengirim dibanned: dicegah melakukan transfer (diredirect ke login atau forbidden)
        $receiver->update(['status' => true]);
        $sender->update(['banned_at' => now()]);
        $response = $this->actingAs($sender)->from(route('transfer.create'))->post(route('transfer.store'), [
            'id_dompet_asal'   => $source->id_dompet,
            'id_dompet_tujuan' => $target->id_dompet,
            'jumlah'           => 1_000,
            'tanggal_transfer' => '2026-10-20',
        ]);
        $this->assertTrue($response->isRedirect() || $response->isForbidden());
        $this->assertDatabaseCount('transfer', 0);
    }

    /**
     * 10. Request ganda dengan token idempotensi / double-click tidak menggandakan transaksi.
     */
    public function test_10_duplicate_request_with_idempotency_token_does_not_duplicate_transfer(): void
    {
        $sender   = $this->makeUser('dup-token@example.com');
        $receiver = $this->makeUser('dup-rec@example.com');
        $source   = $this->makeWallet($sender, 'Source Dup', 500_000);
        $target   = $this->makeWallet($receiver, 'Target Dup', 0, true);

        $payload = [
            'id_dompet_asal'   => $source->id_dompet,
            'id_dompet_tujuan' => $target->id_dompet,
            'jumlah'           => 100_000,
            'tanggal_transfer' => '2026-10-20',
            'idempotency_key'  => 'tx-token-unique-12345',
        ];

        // Request pertama sukses
        $this->actingAs($sender)->post(route('transfer.store'), $payload)->assertRedirect();

        // Request kedua dengan token idempotensi yang sama langsung ditolak
        $this->actingAs($sender)->from(route('transfer.create'))->post(route('transfer.store'), $payload)
            ->assertSessionHasErrors('jumlah');

        // Hanya tepat 1 transfer tersimpan
        $this->assertDatabaseCount('transfer', 1);
        $this->assertDatabaseHas('dompet', ['id_dompet' => $source->id_dompet, 'saldo' => '400000.00']);
        $this->assertDatabaseHas('dompet', ['id_dompet' => $target->id_dompet, 'saldo' => '100000.00']);
    }

    public function test_persistent_idempotency_key_rejects_retry_after_duplicate_guard_expires(): void
    {
        $sender = $this->makeUser('expired-token@example.com');
        $receiver = $this->makeUser('expired-rec@example.com');
        $source = $this->makeWallet($sender, 'Source Expired Token', 500_000);
        $target = $this->makeWallet($receiver, 'Target Expired Token', 0, true);
        $payload = [
            'id_dompet_asal' => $source->id_dompet,
            'id_dompet_tujuan' => $target->id_dompet,
            'jumlah' => 100_000,
            'tanggal_transfer' => '2026-10-20',
            'idempotency_key' => 'tx-persistent-after-30-seconds',
        ];

        $this->actingAs($sender)->post(route('transfer.store'), $payload)->assertRedirect();
        $transfer = Transfer::firstOrFail();
        $idempotencyRecord = DB::table('transfer_idempotency_keys')->first();
        $this->assertSame($transfer->id_transfer, $idempotencyRecord->id_transfer);

        $this->travel(31)->seconds();

        $this->actingAs($sender)
            ->from(route('transfer.create'))
            ->post(route('transfer.store'), $payload)
            ->assertSessionHasErrors([
                'jumlah' => 'This transfer was already completed; the same request was not repeated.',
            ]);

        $payload['jumlah'] = 50_000;
        $this->actingAs($sender)
            ->from(route('transfer.create'))
            ->post(route('transfer.store'), $payload)
            ->assertSessionHasErrors([
                'jumlah' => 'This idempotency key was already used for a different transfer request.',
            ]);

        $this->assertDatabaseCount('transfer', 1);
        $this->assertDatabaseCount('transfer_idempotency_keys', 1);
        $this->assertDatabaseHas('dompet', [
            'id_dompet' => $source->id_dompet,
            'saldo' => '400000.00',
        ]);
        $this->assertDatabaseHas('dompet', [
            'id_dompet' => $target->id_dompet,
            'saldo' => '100000.00',
        ]);
    }

    public function test_distinct_idempotency_tokens_allow_distinct_transfer_requests(): void
    {
        $sender = $this->makeUser('distinct-token@example.com');
        $receiver = $this->makeUser('distinct-rec@example.com');
        $source = $this->makeWallet($sender, 'Source Distinct', 500_000);
        $target = $this->makeWallet($receiver, 'Target Distinct', 0, true);

        foreach (['tx-distinct-1', 'tx-distinct-2'] as $token) {
            $this->actingAs($sender)->post(route('transfer.store'), [
                'id_dompet_asal' => $source->id_dompet,
                'id_dompet_tujuan' => $target->id_dompet,
                'jumlah' => 100_000,
                'tanggal_transfer' => '2026-10-20',
                'idempotency_key' => $token,
            ])->assertRedirect();
        }

        $this->assertDatabaseCount('transfer', 2);
        $this->assertDatabaseCount('transfer_idempotency_keys', 2);
        $this->assertDatabaseHas('dompet', [
            'id_dompet' => $source->id_dompet,
            'saldo' => '300000.00',
        ]);
        $this->assertDatabaseHas('dompet', [
            'id_dompet' => $target->id_dompet,
            'saldo' => '200000.00',
        ]);
    }

    /**
     * 11. Request bersamaan terlindungi dengan pessimistic locking (saldo tidak negatif).
     */
    public function test_11_concurrent_transactions_cannot_result_in_negative_balance(): void
    {
        $sender   = $this->makeUser('conc-sender@example.com');
        $receiver = $this->makeUser('conc-receiver@example.com');
        $source   = $this->makeWallet($sender, 'Source Conc', 100_000);
        $target   = $this->makeWallet($receiver, 'Target Conc', 0, true);

        // Simulasi 2 transfer masing-masing 70.000 dengan saldo awal 100.000
        // Transfer 1 sukses, transfer 2 harus gagal karena sisa saldo (30.000) tidak cukup
        $this->actingAs($sender)->post(route('transfer.store'), [
            'id_dompet_asal'   => $source->id_dompet,
            'id_dompet_tujuan' => $target->id_dompet,
            'jumlah'           => 70_000,
            'tanggal_transfer' => '2026-10-20',
            'idempotency_key'  => 'tx-conc-1',
        ])->assertRedirect();

        $this->actingAs($sender)->from(route('transfer.create'))->post(route('transfer.store'), [
            'id_dompet_asal'   => $source->id_dompet,
            'id_dompet_tujuan' => $target->id_dompet,
            'jumlah'           => 70_000,
            'tanggal_transfer' => '2026-10-20',
            'idempotency_key'  => 'tx-conc-2',
        ])->assertSessionHasErrors('jumlah');

        // Saldo akhir harus 30.000 (TIDAK PERNAH negatif)
        $this->assertSame('30000.00', (string) $source->fresh()->saldo);
        $this->assertSame('70000.00', (string) $target->fresh()->saldo);
        $this->assertDatabaseCount('transfer', 1);
    }

    /**
     * 12. Penghapusan dompet utama tidak meninggalkan promosi dompet palsu.
     */
    public function test_12_deleting_primary_wallet_leaves_no_misleading_promoted_wallet(): void
    {
        $user    = $this->makeUser('del-primary@example.com');
        $wallet1 = $this->makeWallet($user, 'Primary Wallet', 100_000, true);
        $wallet2 = $this->makeWallet($user, 'Other Wallet', 50_000, false);

        $this->assertTrue((bool) $wallet1->fresh()->terima_transfer);
        $this->assertFalse((bool) $wallet2->fresh()->terima_transfer);

        // Hapus dompet utama
        $this->actingAs($user)->delete(route('dompet.destroy', $wallet1))->assertRedirect();

        $this->assertDatabaseMissing('dompet', ['id_dompet' => $wallet1->id_dompet]);

        // Dompet kedua TIDAK otomatis dipromosikan diam-diam
        $this->assertFalse((bool) $wallet2->fresh()->terima_transfer);

        // Jumlah dompet utama aktif saat ini adalah 0
        $activeCount = Dompet::where('id_user', $user->id_user)->where('terima_transfer', true)->count();
        $this->assertSame(0, $activeCount);
    }
}
