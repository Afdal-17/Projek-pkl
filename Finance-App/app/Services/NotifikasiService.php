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
        $this->catatTransaksi($userId, "{$jenisLabel} \"{$transaksi->nama_transaksi}\" sebesar Rp ".number_format((float) $transaksi->jumlah, 2, ',', '.').' berhasil dicatat.', $transaksi->tanggal);
    }

    public function transaksiDiubah(Transaksi $transaksi, int $userId): void
    {
        $this->catatTransaksi($userId, "Transaksi \"{$transaksi->nama_transaksi}\" telah diperbarui.", now());
    }

    public function transaksiDihapus(string $namaTransaksi, float $jumlah, int $userId): void
    {
        $this->catatTransaksi($userId, "Transaksi \"{$namaTransaksi}\" sebesar Rp ".number_format($jumlah, 2, ',', '.').' telah dihapus.', now());
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
    }

    public function transferBerhasil(Transfer $transfer): void
    {
        $transfer->loadMissing(['dompetAsal', 'dompetTujuan']);
        $message = 'Transfer Rp '.number_format((float) $transfer->jumlah, 2, ',', '.').' dari '
            .$transfer->dompetAsal->nama_dompet.' ke '.$transfer->dompetTujuan->nama_dompet.' berhasil.';
        $recipientIds = array_unique([
            (int) $transfer->dompetAsal->id_user,
            (int) $transfer->dompetTujuan->id_user,
        ]);

        foreach ($recipientIds as $userId) {
            Notifikasi::create([
                'id_user' => $userId,
                'tipe' => 'transaksi',
                'pesan' => $message,
                'sudah_dibaca' => false,
                'tanggal' => $transfer->tanggal_transfer,
            ]);
        }
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
