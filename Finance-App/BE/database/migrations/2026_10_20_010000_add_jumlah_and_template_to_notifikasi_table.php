<?php

use App\Models\Notifikasi;
use App\Services\CurrencyService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notifikasi lama disimpan sebagai teks jadi yang diformat dengan mata uang
     * orang yang MEMBUAT notifikasi saat itu — sehingga penerima melihat mata
     * uang asing, dan teks tidak pernah ikut saat user mengganti mata uang.
     *
     * Solusi: simpan nominal dalam IDR base (jumlah) + template pesan dengan
     * placeholder {jumlah}, lalu render ulang saat ditampilkan sesuai mata uang
     * pemilik notifikasi saat ini. Kolom lama (pesan) tetap ada sebagai fallback.
     */
    public function up(): void
    {
        Schema::table('notifikasi', function (Blueprint $table): void {
            $table->decimal('jumlah', 15, 2)->nullable()->after('pesan');
            $table->text('pesan_template')->nullable()->after('jumlah');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('notifikasi', function (Blueprint $table): void {
            $table->dropColumn(['jumlah', 'pesan_template']);
        });
    }

    /**
     * Isi data terstruktur untuk notifikasi lama dengan membaca ulang teks
     * yang tersimpan (pola pesan tetap, hanya nominal yang diparsing).
     * Baris yang tidak bisa diparsing dibiarkan (tetap menampilkan teks lama).
     */
    private function backfill(): void
    {
        Notifikasi::query()->orderBy('id_notifikasi')->chunkById(200, function ($notifikasi): void {
            foreach ($notifikasi as $item) {
                $pesans = $item->pesan;

                $pola = [
                    // "Transfer Rp 10.000 dari A ke B berhasil."
                    '/^Transfer (.+) dari (.+) ke (.+) berhasil\.$/' => fn (array $cocok): ?array => [
                        'template' => 'Transfer {jumlah} dari '.$cocok[2].' ke '.$cocok[3].' berhasil.',
                        'uang' => $cocok[1],
                    ],
                    // "Transfer berhasil ke B dari A sebesar $100.00."
                    '/^Transfer berhasil ke (.+) dari (.+) sebesar (.+)\.$/' => fn (array $cocok): ?array => [
                        'template' => 'Transfer berhasil ke '.$cocok[1].' dari '.$cocok[2].' sebesar {jumlah}.',
                        'uang' => $cocok[3],
                    ],
                    // "Anda di transfer oleh A sebesar Rp 1.600.000."
                    '/^Anda di transfer oleh (.+) sebesar (.+)\.$/' => fn (array $cocok): ?array => [
                        'template' => 'Anda di transfer oleh '.$cocok[1].' sebesar {jumlah}.',
                        'uang' => $cocok[2],
                    ],
                    // 'Pemasukan "Gaji" sebesar Rp 5.000.000 berhasil dicatat.'
                    '/^(Pemasukan|Pengeluaran) "(.+)" sebesar (.+) berhasil dicatat\.$/' => fn (array $cocok): ?array => [
                        'template' => $cocok[1].' "'.$cocok[2].'" sebesar {jumlah} berhasil dicatat.',
                        'uang' => $cocok[3],
                    ],
                    // 'Transaksi "Kopi" sebesar Rp 25.000 telah dihapus.'
                    '/^Transaksi "(.+)" sebesar (.+) telah dihapus\.$/' => fn (array $cocok): ?array => [
                        'template' => 'Transaksi "'.$cocok[1].'" sebesar {jumlah} telah dihapus.',
                        'uang' => $cocok[2],
                    ],
                ];

                foreach ($pola as $regex => $builder) {
                    if (preg_match($regex, $pesans, $cocok) !== 1) {
                        continue;
                    }

                    $hasil = $builder($cocok);

                    if ($hasil === null) {
                        continue;
                    }

                    $nilai = $this->parseNominal($hasil['uang']);

                    if ($nilai === null) {
                        continue; // format asing tak dikenal: biarkan teks lama
                    }

                    [$nilaiIdr, $mataUang] = $nilai;

                    // Konversi ke IDR base memakai kurs saat ini; bila kurs tidak
                    // tersedia, konversi mengembalikan nilai apa adanya (fallback).
                    $item->forceFill([
                        'jumlah' => CurrencyService::convert($nilaiIdr, $mataUang, CurrencyService::BASE),
                        'pesan_template' => $hasil['template'],
                    ])->save();

                    break;
                }
            }
        });
    }

    /**
     * "Rp 1.600.000" → [1600000.0, 'IDR']
     * "$100.00"      → [100.0, 'USD']
     * "1.000,00 €"   → [1000.0, 'EUR']
     */
    private function parseNominal(string $teks): ?array
    {
        $teks = trim($teks);

        if (str_starts_with($teks, 'Rp ')) {
            return [(float) str_replace('.', '', substr($teks, 3)), 'IDR'];
        }

        if (str_starts_with($teks, '$')) {
            return [(float) str_replace(',', '', substr($teks, 1)), 'USD'];
        }

        if (str_ends_with($teks, ' €')) {
            return [(float) str_replace(['.', ','], ['', '.'], substr($teks, 0, -2)), 'EUR'];
        }

        return null;
    }
};
