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

    private static array $columnsCache = [];

    protected static function booted(): void
    {
        static::saving(function (Notifikasi $notifikasi): void {
            if (! $notifikasi->tableHasColumn('jumlah')) {
                unset($notifikasi->attributes['jumlah']);
            }
            if (! $notifikasi->tableHasColumn('pesan_template')) {
                unset($notifikasi->attributes['pesan_template']);
            }
        });
    }

    public function tableHasColumn(string $column): bool
    {
        $table = $this->getTable();
        if (! isset(self::$columnsCache[$table])) {
            self::$columnsCache[$table] = \Illuminate\Support\Facades\Schema::hasTable($table)
                ? \Illuminate\Support\Facades\Schema::getColumnListing($table)
                : [];
        }

        return in_array($column, self::$columnsCache[$table], true);
    }

    public static function hasColumn(string $column, string $table = 'notifikasi'): bool
    {
        if (! isset(self::$columnsCache[$table])) {
            self::$columnsCache[$table] = \Illuminate\Support\Facades\Schema::hasTable($table)
                ? \Illuminate\Support\Facades\Schema::getColumnListing($table)
                : [];
        }

        return in_array($column, self::$columnsCache[$table], true);
    }

    public static function flushColumnCache(): void
    {
        self::$columnsCache = [];
    }

    /**
     * Message shown to the notification owner.
     *
     * Amounts use the owner's current currency preference. Notifications
     * without a template fall back to their stored message.
     */
    public function pesanTampil(): string
    {
        if ($this->pesan_template === null || $this->jumlah === null) {
            return self::formatMessage($this->pesan ?? '');
        }

        $rendered = str_replace('{jumlah}', finance_money((string) $this->jumlah), $this->pesan_template);
        return self::formatMessage($rendered);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public static function formatMessage(mixed $pesan = null): string
    {
        if (is_object($pesan)) {
            $pesan = $pesan->pesan ?? (string) $pesan;
        }

        $pesan = trim((string) ($pesan ?? ''));

        if ($pesan === '') {
            return '';
        }

        if (preg_match('/^Pengeluaran "([^"]+)" sebesar (.+?) berhasil dicatat\.?$/u', $pesan, $m)) {
            return "Expense \"{$m[1]}\" of {$m[2]} was successfully recorded.";
        }

        if (preg_match('/^Pemasukan "([^"]+)" sebesar (.+?) berhasil dicatat\.?$/u', $pesan, $m)) {
            return "Income \"{$m[1]}\" of {$m[2]} was successfully recorded.";
        }

        if (preg_match('/^Transaksi "([^"]+)" telah diperbarui\.?$/u', $pesan, $m)) {
            return "Transaction \"{$m[1]}\" has been updated.";
        }

        if (preg_match('/^Transaksi "([^"]+)" sebesar (.+?) telah dihapus\.?$/u', $pesan, $m)) {
            return "Transaction \"{$m[1]}\" of {$m[2]} has been deleted.";
        }

        if (preg_match('/^Transfer (.+?) dari (.+?) ke (.+?) berhasil\.?$/u', $pesan, $m)) {
            return "Transfer of {$m[1]} from {$m[2]} to {$m[3]} was successful.";
        }

        if (preg_match('/^Transfer berhasil ke (.+?) dari (.+?) sebesar (.+?)\.?$/u', $pesan, $m)) {
            return "Transfer to {$m[1]} from {$m[2]} of {$m[3]} was successful.";
        }

        if (preg_match('/^Anda di transfer oleh (.+?) sebesar (.+?)\.?$/u', $pesan, $m)) {
            return "You received a transfer of {$m[2]} from {$m[1]}.";
        }

        if (preg_match('/^Target (.+?) telah tercapai\.?$/u', $pesan, $m)) {
            return "Target {$m[1]} has been reached.";
        }

        return $pesan;
    }

    /**
     * Formatted display message using formatMessage.
     */
    public function getFormattedPesanAttribute(): string
    {
        return self::formatMessage($this->pesanTampil());
    }

    /**
     * Display title based on notification type and message content.
     */
    public function getDisplayTitleAttribute(): string
    {
        if ($this->tipe === 'target') {
            return 'Savings goal reached 🤩';
        }

        if (
            str_starts_with($this->pesan ?? '', 'Transfer ') ||
            str_starts_with($this->pesan ?? '', 'Transfer of ') ||
            str_starts_with($this->pesan ?? '', 'Transfer to ') ||
            str_starts_with($this->pesan ?? '', 'You received a transfer ') ||
            str_starts_with($this->pesan ?? '', 'Anda di transfer oleh ') ||
            str_starts_with($this->pesan ?? '', 'Anda menerima transfer ')
        ) {
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

        if (
            str_starts_with($this->pesan ?? '', 'Transfer ') ||
            str_starts_with($this->pesan ?? '', 'Transfer of ') ||
            str_starts_with($this->pesan ?? '', 'Transfer to ') ||
            str_starts_with($this->pesan ?? '', 'You received a transfer ') ||
            str_starts_with($this->pesan ?? '', 'Anda di transfer oleh ') ||
            str_starts_with($this->pesan ?? '', 'Anda menerima transfer ')
        ) {
            return 'transfer';
        }

        return 'transaction';
    }
}
