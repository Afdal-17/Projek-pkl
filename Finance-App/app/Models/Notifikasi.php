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
        'jumlah',
        'pesan_template',
        'sudah_dibaca',
        'tanggal',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'sudah_dibaca' => 'boolean',
            'tanggal' => 'datetime',
        ];
    }

    /**
     * Pesan yang ditampilkan ke pemilik notifikasi.
     *
     * Nominal dikonversi sesuai mata uang pemilik SAAT INI (bukan mata uang
     * saat notifikasi dibuat), sehingga notifikasi ikut berubah saat user
     * mengganti preferensi mata uang akunnya. Notifikasi lama tanpa template
     * jatuh kembali ke teks tersimpan (pesan).
     */
    public function pesanTampil(): string
    {
        if ($this->pesan_template === null || $this->jumlah === null) {
            return $this->pesan;
        }

        return str_replace('{jumlah}', finance_money((float) $this->jumlah), $this->pesan_template);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
