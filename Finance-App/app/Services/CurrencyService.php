<?php

namespace App\Services;

use App\Models\User;

class CurrencyService
{
    /**
     * Mata uang penyimpanan (base currency). Seluruh nilai keuangan di database
     * SELALU tersimpan dalam IDR. Konversi hanya dilakukan saat menampilkan
     * (display) dan saat menyimpan input user (toBase). Dengan demikian,
     * mengganti mata uang tidak pernah mengubah data tersimpan, sehingga
     * konversi selalu akurat dan dapat dikembalikan tanpa kehilangan presisi.
     */
    public const BASE = 'IDR';

    /**
     * Memoisasi kurs per-request (konversi dipanggil berkali-kali per halaman).
     * @var array<string, float>|null
     */
    private static ?array $kursMemo = null;

    public static function normalize(?string $currency): string
    {
        $currency = strtoupper(trim((string) $currency));

        return in_array($currency, self::supported(), true) ? $currency : self::BASE;
    }

    /**
     * Daftar mata uang yang didukung (config/currency.php).
     */
    public static function supported(): array
    {
        return array_values((array) config('currency.supported', [self::BASE]));
    }

    public static function isSupported(string $currency): bool
    {
        return in_array(strtoupper(trim($currency)), self::supported(), true);
    }

    /**
     * Kurs relatif IDR (IDR = 1.0) dari sumber live (Frankfurter, di-cache)
     * dengan fallback otomatis ke kurs cadangan bila internet tidak tersedia.
     */
    public static function rates(): array
    {
        return self::$kursMemo ??= app(CurrencyRateProvider::class)->rates();
    }

    /**
     * Kosongkan memoisasi kurs (untuk test atau refresh manual).
     */
    public static function flushRates(): void
    {
        self::$kursMemo = null;
    }

    /**
     * Konversi nominal antar mata uang via base IDR.
     */
    public static function convert(float|int $amount, string $from, string $to): float
    {
        $from = self::normalize($from);
        $to = self::normalize($to);
        $amount = (float) $amount;

        if ($from === $to || $amount === 0.0) {
            return $amount;
        }

        $rates = self::rates();
        $dari = $rates[$from] ?? null;
        $ke = $rates[$to] ?? null;

        if (! $dari || ! $ke) {
            return $amount;
        }

        return $amount * ($dari / $ke);
    }

    /**
     * Konversi nilai input user (dalam mata uang aktif) ke IDR base untuk disimpan.
     */
    public static function toBase(float|int $amount, ?string $currency = null): float
    {
        $currency = self::normalize($currency ?? (auth()->user()->mata_uang ?? self::BASE));

        return self::convert($amount, $currency, self::BASE);
    }

    /**
     * Konversi nilai IDR base ke mata uang aktif untuk ditampilkan.
     */
    public static function fromBase(float|int $amount, ?string $currency = null): float
    {
        $currency = self::normalize($currency ?? (auth()->user()->mata_uang ?? self::BASE));

        return self::convert($amount, self::BASE, $currency);
    }

    /**
     * Ubah preferensi mata uang user. Nilai keuangan tetap tersimpan dalam IDR
     * (base currency), sehingga perpindahan mata uang selalu akurat dan tidak
     * merusak saldo maupun riwayat transaksi.
     */
    public function setUserCurrency(User $user, string $newCurrency): User
    {
        $user->mata_uang = self::normalize($newCurrency);
        $user->save();

        return $user;
    }
}
