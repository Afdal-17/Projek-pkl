<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\Transfer;

class NotifikasiService
{
    public function transaksiDicatat(Transaksi $transaksi, int $userId): void
    {
        $jenisLabel = $transaksi->jenis === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran';
        $this->catatTransaksi($userId, "{$jenisLabel} \"{$transaksi->nama_transaksi}\" sebesar ".finance_money((float) $transaksi->jumlah).' berhasil dicatat.', now());
    }

    public function transaksiDiubah(Transaksi $transaksi, int $userId): void
    {
        $this->catatTransaksi($userId, "Transaksi \"{$transaksi->nama_transaksi}\" telah diperbarui.", now());
    }

    public function transaksiDihapus(string $namaTransaksi, float $jumlah, int $userId): void
    {
        $this->catatTransaksi($userId, "Transaksi \"{$namaTransaksi}\" sebesar ".finance_money($jumlah).' telah dihapus.', now());
    }

    private function catatTransaksi(int $userId, string $pesan, mixed $tanggal): void
    {
        Notifikasi::create([
            'id_user' => $userId,
            'tipe' => 'transaksi',
            'pesan' => $pesan,
            'sudah_dibaca' => false,
            'tanggal' => $tanggal,
        ]);

        session()->flash('notif_popup', $pesan);
    }

    public function transferBerhasil(Transfer $transfer): void
    {
        $transfer->loadMissing(['dompetAsal.user', 'dompetTujuan.user']);
        $now = now();

        $senderId = (int) $transfer->dompetAsal->id_user;
        $recipientId = (int) $transfer->dompetTujuan->id_user;

        // Transfer antar dompet milik user yang sama
        if ($senderId === $recipientId) {
            $amount = finance_money((float) $transfer->jumlah, $transfer->dompetAsal->user->mata_uang ?? 'IDR');
            $message = "Transfer {$amount} dari "
                .$transfer->dompetAsal->nama_dompet.' ke '.$transfer->dompetTujuan->nama_dompet.' berhasil.';

            Notifikasi::create([
                'id_user' => $senderId,
                'tipe' => 'transaksi',
                'pesan' => $message,
                'sudah_dibaca' => false,
                'tanggal' => $now,
            ]);

            session()->flash('notif_popup', $message);
            return;
        }

        // Transfer ke user lain: pesan berbeda untuk pengirim dan penerima
        $senderAmount = finance_money((float) $transfer->jumlah, $transfer->dompetAsal->user->mata_uang ?? 'IDR');
        $recipientAmount = finance_money((float) $transfer->jumlah, $transfer->dompetTujuan->user->mata_uang ?? 'IDR');
        $senderMessage = 'Transfer berhasil ke '.$transfer->dompetTujuan->user->nama
            .' dari '.$transfer->dompetAsal->nama_dompet
            ." sebesar {$senderAmount}.";
        $recipientMessage = 'Anda di transfer oleh '.$transfer->dompetAsal->user->nama
            ." sebesar {$recipientAmount}.";

        Notifikasi::create([
            'id_user' => $senderId,
            'tipe' => 'transaksi',
            'pesan' => $senderMessage,
            'sudah_dibaca' => false,
            'tanggal' => $now,
        ]);

        Notifikasi::create([
            'id_user' => $recipientId,
            'tipe' => 'transaksi',
            'pesan' => $recipientMessage,
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
