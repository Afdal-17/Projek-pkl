<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dompet extends Model
{
    use HasFactory;

    protected $table = 'dompet';

    protected $primaryKey = 'id_dompet';

    protected $fillable = [
        'id_user',
        'nama_dompet',
        'deskripsi',
        'jenis',
        'warna',
        'saldo_awal',
        'saldo',
    ];

    protected function casts(): array
    {
        return [
            'saldo_awal' => 'decimal:2',
            'saldo' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'id_dompet', 'id_dompet');
    }

    public function targetTabungan(): HasMany
    {
        return $this->hasMany(TargetTabungan::class, 'id_dompet', 'id_dompet');
    }

    public function transferKeluar(): HasMany
    {
        return $this->hasMany(Transfer::class, 'id_dompet_asal', 'id_dompet');
    }

    public function transferMasuk(): HasMany
    {
        return $this->hasMany(Transfer::class, 'id_dompet_tujuan', 'id_dompet');
    }
}
