<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    use HasFactory;

    protected $table = 'transfer';

    protected $primaryKey = 'id_transfer';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'id_dompet_asal',
        'id_dompet_tujuan',
        'jumlah',
        'catatan',
        'tanggal_transfer',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'tanggal_transfer' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function dompetAsal(): BelongsTo
    {
        return $this->belongsTo(Dompet::class, 'id_dompet_asal', 'id_dompet');
    }

    public function dompetTujuan(): BelongsTo
    {
        return $this->belongsTo(Dompet::class, 'id_dompet_tujuan', 'id_dompet');
    }
}
