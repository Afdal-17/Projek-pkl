<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\Transfer;

class NotifikasiService
{
    /**
     * Catat notifikasi transaksi.
     *
     * $jumlah selalu dalam IDR base dan $pesanTemplate memakai placeholder
     * {jumlah} — nominal dirender ulang saat ditampilkan sesuai mata uang
     * pemilik notifikasi saat ini (lihat Notifikasi::pesanTampil()). $pesan
     * (teks jadi) tetap disimpan untuk popup sesi dan fallback.
     */
    private function catatTransaksi(
        int $userId,
        string $pesan,
        mixed $tanggal,
        ?float $jumlah = null,
        ?string $pesanTemplate = null,
    ): void {
        Notifikasi::create([
            'id_user' => $userId,
            'tipe' => 'transaksi',
            'pesan' => $pesan,
            'jumlah' => $jumlah,
            'pesan_template' => $pesanTemplate,
            'sudah_dibaca' => false,
            'tanggal' => $tanggal,
        ]);

        session()->flash('notif_popup', $pesan);
    }

    public function transaksiDicatat(Transaksi $transaksi, int $userId): void
    {
        $jenisLabel = $transaksi->jenis === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran';
        $template = $jenisLabel.' "'.$transaksi->nama_transaksi.'" sebesar {jumlah} berhasil dicatat.';
        $this->catatTransaksi(
            $userId,
            str_replace('{jumlah}', finance_money((float) $transaksi->jumlah), $template),
            now(),
            (float) $transaksi->jumlah,
            $template,
        );
    }

    public function transaksiDiubah(Transaksi $transaksi, int $userId): void
    {
        $this->catatTransaksi($userId, "Transaksi \"{$transaksi->nama_transaksi}\" telah diperbarui.", now());
    }

    public function transaksiDihapus(string $namaTransaksi, float $jumlah, int $userId): void
    {
        $template = 'Transaksi "'.$namaTransaksi.'" sebesar {jumlah} telah dihapus.';
        $this->catatTransaksi(
            $userId,
            str_replace('{jumlah}', finance_money($jumlah), $template),
            now(),
            $jumlah,
            $template,
        );
    }

    public function transferBerhasil(Transfer $transfer): void
    {
        $transfer->loadMissing(['dompetAsal.user', 'dompetTujuan.user']);
        $now = now();
        $jumlah = (float) $transfer->jumlah;

        $senderId = (int) $transfer->dompetAsal->id_user;
        $recipientId = (int) $transfer->dompetTujuan->id_user;

        // Transfer antar dompet milik user yang sama
        if ($senderId === $recipientId) {
            $template = 'Transfer {jumlah} dari '
                .$transfer->dompetAsal->nama_dompet.' ke '.$transfer->dompetTujuan->nama_dompet.' berhasil.';
            $message = str_replace('{jumlah}', finance_money($jumlah, $transfer->dompetAsal->user->mata_uang ?? 'IDR'), $template);

            Notifikasi::create([
                'id_user' => $senderId,
                'tipe' => 'transaksi',
                'pesan' => $message,
                'jumlah' => $jumlah,
                'pesan_template' => $template,
                'sudah_dibaca' => false,
                'tanggal' => $now,
            ]);

            session()->flash('notif_popup', $message);
            return;
        }

        // Transfer ke user lain: pesan berbeda untuk pengirim dan penerima.
        // Baik teks jadi maupun template disiapkan per penerima; nominal pada
        // template dirender ulang dengan mata uang masing-masing saat dibuka.
        $senderTemplate = 'Transfer berhasil ke '.$transfer->dompetTujuan->user->nama
            .' dari '.$transfer->dompetAsal->nama_dompet
            .' sebesar {jumlah}.';
        $recipientTemplate = 'Anda di transfer oleh '.$transfer->dompetAsal->user->nama
            .' sebesar {jumlah}.';

        $senderMessage = str_replace('{jumlah}', finance_money($jumlah, $transfer->dompetAsal->user->mata_uang ?? 'IDR'), $senderTemplate);
        $recipientMessage = str_replace('{jumlah}', finance_money($jumlah, $transfer->dompetTujuan->user->mata_uang ?? 'IDR'), $recipientTemplate);

        Notifikasi::create([
            'id_user' => $senderId,
            'tipe' => 'transaksi',
            'pesan' => $senderMessage,
            'jumlah' => $jumlah,
            'pesan_template' => $senderTemplate,
            'sudah_dibaca' => false,
            'tanggal' => $now,
        ]);

        Notifikasi::create([
            'id_user' => $recipientId,
            'tipe' => 'transaksi',
            'pesan' => $recipientMessage,
            'jumlah' => $jumlah,
            'pesan_template' => $recipientTemplate,
            'sudah_dibaca' => false,
            'tanggal' => $now,
        ]);

        session()->flash('notif_popup', $senderMessage);
    }

    public function targetTercapai(TargetTabungan $target): void
    {
        $target->loadMissing('dompet');
        Notifikasi::create([
            'id_user' => $target->id_user,
            'tipe' => 'target',
            'pesan' => 'Target '.$target->nama_target.' telah tercapai.',
            'sudah_dibaca' => false,
            'tanggal' => now(),
        ]);

        session()->flash('notif_popup', 'Target '.$target->nama_target.' telah tercapai.');
    }

    public function targetBaruTercapai(TargetTabungan $target, float $saldoSebelumnya): void
    {
        $target->loadMissing('dompet');
        $savedBefore = max(
            0,
            (float) $target->nominal_terkumpul
                + $saldoSebelumnya
                - (float) $target->saldo_awal_dompet
        );

        if ($savedBefore < (float) $target->nominal_target && $target->tercapai) {
            $this->targetTercapai($target);
        }
    }
}
