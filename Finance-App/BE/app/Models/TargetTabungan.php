<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetTabungan extends Model
{
    use HasFactory;

    protected $table = 'target_tabungan';

    protected $primaryKey = 'id_target';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'id_dompet',
        'nama_target',
        'deskripsi',
        'nominal_target',
        'nominal_terkumpul',
        'saldo_awal_dompet',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'nominal_target' => 'decimal:2',
            'nominal_terkumpul' => 'decimal:2',
            'saldo_awal_dompet' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function dompet(): BelongsTo
    {
        return $this->belongsTo(Dompet::class, 'id_dompet', 'id_dompet');
    }

    /**
    * Progress menuju target (0-100) mengikuti saldo dompet yang dipilih.
     */
    public function progress(): Attribute
    {
        return Attribute::make(
            get: function () {
                $target = (float) $this->nominal_target;
                if ($target <= 0) {
                    return 0.0;
                }

                $terkumpul = (float) $this->jumlah_terkumpul;
                $persen = ($terkumpul / $target) * 100;

                return round(min(max($persen, 0.0), 100.0), 2);
            }
        );
    }

    /**
     * True bila saldo dompet sudah mencapai atau melebihi nominal target.
     */
    public function tercapai(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->jumlah_terkumpul >= (float) $this->nominal_target
        );
    }

    protected function jumlahTerkumpul(): Attribute
    {
        return Attribute::make(
            get: fn () => max(
                0,
                (float) $this->nominal_terkumpul
                    + (float) $this->dompet->saldo
                    - (float) $this->saldo_awal_dompet
            )
        );
    }
}
