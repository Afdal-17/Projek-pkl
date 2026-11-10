<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaksi extends Model
{
    use HasFactory;

    protected $table = 'transaksi';

    protected $primaryKey = 'id_transaksi';

    public $timestamps = false;

    protected $fillable = [
        'id_kategori',
        'id_transfer',
        'id_dompet',
        'nama_transaksi',
        'jumlah',
        'jenis',
        'tanggal',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'tanggal' => 'datetime',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'id_kategori', 'id_kategori');
    }

    public function dompet(): BelongsTo
    {
        return $this->belongsTo(Dompet::class, 'id_dompet', 'id_dompet');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class, 'id_transfer', 'id_transfer');
    }

    /**
     * Dompet lawan transaksi untuk baris transfer: dompet tujuan bila baris ini
     * berada di dompet asal, dan sebaliknya.
     */
    public function lawanTransfer(): ?Dompet
    {
        if ($this->id_transfer === null || $this->transfer === null) {
            return null;
        }

        $adalahDompetAsal = $this->id_dompet !== null
            && (int) $this->transfer->id_dompet_asal === (int) $this->id_dompet;

        return $adalahDompetAsal ? $this->transfer->dompetTujuan : $this->transfer->dompetAsal;
    }

    /**
     * User pemilik dompet lawan, hanya bila transfer antar user berbeda.
     * Transfer antar dompet milik sendiri mengembalikan null.
     */
    private function lawanUser(): ?User
    {
        $lawan = $this->lawanTransfer();

        if ($lawan === null
            || $this->dompet === null
            || (int) $lawan->id_user === (int) $this->dompet->id_user) {
            return null;
        }

        return $lawan->user;
    }

    /**
     * Nama tampilan transaksi di riwayat. Transfer antar user menampilkan nama
     * user lawan transaksi (bukan nama dompetnya); transfer antar dompet milik
     * sendiri tetap memakai nama dompet.
     */
    public function displayNama(): string
    {
        $userLawan = $this->lawanUser();

        if ($userLawan === null || $this->transfer === null) {
            return $this->nama_transaksi;
        }

        $keLuar = (int) $this->transfer->id_dompet_asal === (int) $this->id_dompet;

        return ($keLuar ? 'Transfer ke ' : 'Transfer dari ').$userLawan->nama;
    }

    /**
     * Label lawan transaksi untuk chip riwayat: nama user lain, atau nama
     * dompet lawan (untuk transfer antar dompet milik sendiri / non-transfer).
     */
    public function displayLawan(): ?string
    {
        $lawan = $this->lawanTransfer();

        if ($lawan === null) {
            return null;
        }

        return $this->lawanUser()?->nama ?? $lawan->nama_dompet;
    }
}
