<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    protected $table = 'notifikasi';

    protected $primaryKey = 'id_notifikasi';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'tipe',
        'pesan',
        'sudah_dibaca',
        'tanggal',
    ];

    protected function casts(): array
    {
        return [
            'sudah_dibaca' => 'boolean',
            'tanggal' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
