<?php

namespace App\Services;

use App\Mail\KodeVerifikasiMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    /** Kode OTP berlaku selama X menit. */
    public const MASA_BERLAKU_MENIT = 10;

    /** Jumlah maksimal percobaan salah sebelum wajib minta kode baru. */
    public const MAKS_PERCOBAAN = 5;

    /**
     * Buat kode OTP 6 digit (disimpan sebagai hash) dan kembalikan kode plaintext.
     */
    public function buatKode(User $user): string
    {
        $kode = (string) random_int(100000, 999999);

        $user->forceFill([
            'otp_kode' => Hash::make($kode),
            'otp_expires_at' => now()->addMinutes(self::MASA_BERLAKU_MENIT),
            'otp_attempts' => 0,
        ])->save();

        return $kode;
    }

    /**
     * Buat kode OTP baru dan kirimkan ke email user.
     */
    public function kirim(User $user): void
    {
        abort_if(blank($user->email), 422, 'User tidak memiliki email.');

        $kode = $this->buatKode($user);

        Mail::to($user->email)->send(new KodeVerifikasiMail(
            namaPenerima: $user->nama,
            kode: $kode,
            menitBerlaku: self::MASA_BERLAKU_MENIT,
        ));
    }

    /**
     * Buat kode OTP baru dan kirimkan ke nomor WhatsApp user (via Fonnte).
     */
    public function kirimWhatsapp(User $user): void
    {
        abort_if(blank($user->nomor_telepon), 422, 'User tidak memiliki nomor telepon.');

        $kode = $this->buatKode($user);

        $pesan = implode("\n", [
            'Kode Verifikasi ' . config('app.name', 'Finance App'),
            '',
            "Halo {$user->nama}, gunakan kode di bawah ini untuk verifikasi nomor telepon Anda:",
            '',
            $kode,
            '',
            'Berlaku selama ' . self::MASA_BERLAKU_MENIT . ' menit. Jangan bagikan kode ini kepada siapa pun.',
            'Jika Anda tidak merasa mendaftar, abaikan pesan ini.',
        ]);

        app(FonnteService::class)->kirim($user->nomor_telepon, $pesan);
    }

    /**
     * User sedang menunggu konfirmasi kode OTP (terdaftar tapi belum verifikasi).
     */
    public function sedangMenunggu(User $user): bool
    {
        return $user->otp_verified_at === null && filled($user->otp_kode);
    }

    /**
     * Konfirmasi input kode OTP. Mengembalikan true bila kode benar.
     * Percobaan yang salah menambah counter; kode kedaluwarsa ditolak.
     */
    public function konfirmasi(User $user, string $input): bool
    {
        if ($user->otp_verified_at !== null) {
            return true;
        }

        if (blank($user->otp_kode)) {
            return false;
        }

        if ($user->otp_attempts >= self::MAKS_PERCOBAAN) {
            return false;
        }

        if (! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
            return false;
        }

        if (! Hash::check($input, $user->otp_kode)) {
            $user->increment('otp_attempts');

            return false;
        }

        $user->forceFill([
            'otp_verified_at' => now(),
            'otp_kode' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
        ])->save();

        return true;
    }
}
