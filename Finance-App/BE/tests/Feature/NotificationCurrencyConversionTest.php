<?php

namespace Tests\Feature;

use App\Models\Dompet;
use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Skenario laporan bug: pengirim berakun USD mengirim transfer (input dalam
     * USD), penerima berakun IDR. Notifikasi penerima harus tampil dalam IDR
     * sesuai mata uang penerima — bukan mata uang dolar si pengirim.
     */
    public function test_recipient_notification_is_formatted_in_recipient_currency(): void
    {
        // Kurs fallback test: 1 USD = 16.000 IDR → $100 = Rp 1.600.000
        [$pengirim, $asal] = $this->userWithWallet('usd-sender@example.com', 'Asal', 1_600_000, 'USD');
        [$penerima, $tujuan] = $this->userWithWallet('idr-recipient@example.com', 'Tujuan', 0, 'IDR');

        $pengirim->update(['nama' => 'Pengirim Dollar']);
        $penerima->update(['nama' => 'Penerima Rupiah']);

        $this->actingAs($pengirim)->post(route('transfer.store'), [
            'id_dompet_asal' => $asal->id_dompet,
            'id_dompet_tujuan' => $tujuan->id_dompet,
            'jumlah' => 100, // diketik dalam USD
            'tanggal_transfer' => '2026-10-01 10:00:00',
        ])->assertRedirect(route('transfer.index'));

        // Penerima (IDR) melihat rupiah, bukan dolar
        $notifPenerima = Notifikasi::where('id_user', $penerima->id_user)->firstOrFail();
        $this->assertSame(
            'Anda di transfer oleh Pengirim Dollar sebesar Rp 1.600.000.',
            $notifPenerima->pesan
        );

        // Pengirim (USD) melihat dolar
        $notifPengirim = Notifikasi::where('id_user', $pengirim->id_user)->firstOrFail();
        $this->assertSame(
            'Transfer berhasil ke Penerima Rupiah dari Asal sebesar $100.00.',
            $notifPengirim->pesan
        );

        // Nilai tersimpan dalam IDR base (100 USD × 16.000)
        $this->assertSame('1600000.00', (string) \App\Models\Transfer::firstOrFail()->jumlah);
    }

    /**
     * Notifikasi yang sudah tersimpan harus ikut dikonversi saat penerima
     * mengganti mata uang akunnya — teks notifikasi tidak boleh teks mati.
     */
    public function test_notification_converts_when_recipient_changes_currency_later(): void
    {
        [$pengirim, $asal] = $this->userWithWallet('usd-sender2@example.com', 'Asal', 1_600_000, 'USD');
        [$penerima, $tujuan] = $this->userWithWallet('idr-recipient2@example.com', 'Tujuan', 0, 'IDR');

        $this->actingAs($pengirim)->post(route('transfer.store'), [
            'id_dompet_asal' => $asal->id_dompet,
            'id_dompet_tujuan' => $tujuan->id_dompet,
            'jumlah' => 100,
            'tanggal_transfer' => '2026-10-01 10:00:00',
        ])->assertRedirect(route('transfer.index'));

        // Penerima pindah akun ke USD: halaman notifikasi harus ikut $100.00
        $penerima->update(['mata_uang' => 'USD']);

        $this->actingAs($penerima->refresh())->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('$100.00')
            ->assertDontSee('Rp 1.600.000');
    }

    /** @return array{0: User, 1: Dompet} */
    private function userWithWallet(string $email, string $walletName, float $saldo, string $mataUang): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'mata_uang' => $mataUang,
        ]);

        $wallet = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => $walletName,
            'saldo' => $saldo,
        ]);

        return [$user, $wallet];
    }
}
