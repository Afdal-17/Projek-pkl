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

    /**
     * Formatted display message — handles legacy Indonesian messages by returning them as-is.
     */
    public function getFormattedPesanAttribute(): string
    {
        return (string) $this->pesan;
    }

    /**
     * Display title based on notification type and message content.
     */
    public function getDisplayTitleAttribute(): string
    {
        if ($this->tipe === 'target') {
            return 'Savings goal reached';
        }

        if (str_starts_with((string) $this->pesan, 'Transfer ') ||
            str_starts_with((string) $this->pesan, 'Anda di transfer oleh ')) {
            return 'Transfer success!';
        }

        return 'Transaction update';
    }

    /**
     * Display type for icon selection in the notification UI.
     */
    public function getDisplayTypeAttribute(): string
    {
        if ($this->tipe === 'target') {
            return 'saving';
        }

        if (str_starts_with((string) $this->pesan, 'Transfer ') ||
            str_starts_with((string) $this->pesan, 'Anda di transfer oleh ')) {
            return 'transfer';
        }

        return 'transaction';
    }
}
