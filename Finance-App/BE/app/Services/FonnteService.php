<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class FonnteService
{
    public const ENDPOINT = 'https://api.fonnte.com/send';

    /**
     * Apakah Fonnte sudah dikonfigurasi (token tersedia di .env).
     */
    public function aktif(): bool
    {
        return filled(config('services.fonnte.token'));
    }

    /**
     * Kirim pesan WhatsApp lewat Fonnte.
     * Dokumen: https://docs.fonnte.com/api-send-message/
     * Header Authorization memakai token mentah (TANPA "Bearer").
     *
     * Melempar RuntimeException bila gagal (token salah, target invalid, dsb).
     */
    public function kirim(string $nomor, string $pesan): bool
    {
        if (! $this->aktif()) {
            throw new RuntimeException('Fonnte belum dikonfigurasi (FONNTE_TOKEN kosong).');
        }

        $response = Http::timeout((int) config('services.fonnte.timeout', 10))
            ->withHeaders([
                'Authorization' => (string) config('services.fonnte.token'),
            ])
            ->asForm()
            ->post(self::ENDPOINT, [
                'target' => $this->normalisasi($nomor),
                'message' => $pesan,
                'countryCode' => '0', // target sudah format internasional (62xxx)
            ]);

        $json = is_array($response->json()) ? $response->json() : [];
        $status = $json['status'] ?? $json['Status'] ?? null;

        if ($response->failed() || $status !== true) {
            $reason = $json['reason'] ?? $json['detail'] ?? ('HTTP ' . $response->status());

            throw new RuntimeException("Fonnte gagal mengirim pesan: {$reason}");
        }

        return true;
    }

    /**
     * Normalisasi nomor Indonesia ke format internasional tanpa tanda plus:
     * 0812… → 62812…, +62812… → 62812…, 62812… tetap.
     */
    public function normalisasi(string $nomor): string
    {
        $digits = preg_replace('/\D+/', '', $nomor);

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        return $digits;
    }
}
