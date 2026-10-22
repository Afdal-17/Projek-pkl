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
                if (bccomp((string) $this->nominal_target, '0.00', 2) <= 0) {
                    return 0.0;
                }

                if (bccomp((string) $this->jumlah_terkumpul, '0.00', 2) <= 0) {
                    return 0.0;
                }

                if (bccomp((string) $this->jumlah_terkumpul, (string) $this->nominal_target, 2) >= 0) {
                    return 100.0;
                }

                $terkumpul = (float) bcdiv(
                    bcmul((string) $this->jumlah_terkumpul, '100', 4),
                    (string) $this->nominal_target,
                    4
                );

                return round($terkumpul, 2);
            }
        );
    }

    /**
     * True bila saldo dompet sudah mencapai atau melebihi nominal target.
     */
    public function tercapai(): Attribute
    {
        return Attribute::make(
            get: fn () => bccomp((string) $this->jumlah_terkumpul, (string) $this->nominal_target, 2) >= 0
        );
    }

    protected function jumlahTerkumpul(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $amount = bcadd(
                    (string) $this->nominal_terkumpul,
                    bcsub((string) $this->dompet->saldo, (string) $this->saldo_awal_dompet, 2),
                    2
                );

                return bccomp($amount, '0.00', 2) < 0 ? '0.00' : $amount;
            }
        );
    }
}
