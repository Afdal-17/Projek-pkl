<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\TargetTabungan;
use App\Models\Transfer;

class NotifikasiService
{
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
        if ($saldoSebelumnya < (float) $target->nominal_target
            && (float) $target->dompet->saldo >= (float) $target->nominal_target) {
            $this->targetTercapai($target);
        }
    }
}
