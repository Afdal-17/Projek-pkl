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
}
