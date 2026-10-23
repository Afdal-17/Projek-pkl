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
            return "Pengeluaran \"{$m[1]}\" sebesar {$m[2]} berhasil dicatat.";
        }

        if (preg_match('/^Pemasukan "([^"]+)" sebesar (.+?) berhasil dicatat\.?$/u', $pesan, $m)) {
            return "Pemasukan \"{$m[1]}\" sebesar {$m[2]} berhasil dicatat.";
        }

        if (preg_match('/^Transaksi "([^"]+)" telah diperbarui\.?$/u', $pesan, $m)) {
            return "Transaksi \"{$m[1]}\" telah diperbarui.";
        }

        if (preg_match('/^Transaksi "([^"]+)" sebesar (.+?) telah dihapus\.?$/u', $pesan, $m)) {
            return "Transaksi \"{$m[1]}\" sebesar {$m[2]} telah dihapus.";
        }

        if (preg_match('/^Transfer (.+?) dari (.+?) ke (.+?) berhasil\.?$/u', $pesan, $m)) {
            return "Transfer sebesar {$m[1]} dari {$m[2]} ke {$m[3]} berhasil.";
        }

        if (preg_match('/^Transfer berhasil ke (.+?) dari (.+?) sebesar (.+?)\.?$/u', $pesan, $m)) {
            return "Transfer sebesar {$m[3]} ke {$m[1]} dari {$m[2]} berhasil.";
        }

        if (preg_match('/^Anda di transfer oleh (.+?) sebesar (.+?)\.?$/u', $pesan, $m)) {
            return "Anda menerima transfer sebesar {$m[2]} dari {$m[1]}.";
        }

        if (preg_match('/^Target (.+?) telah tercapai\.?$/u', $pesan, $m)) {
            return "Target {$m[1]} telah tercapai.";
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
            return 'Target tabungan tercapai 🤩';
        }

        if (
            str_starts_with($this->pesan ?? '', 'Transfer ') ||
            str_starts_with($this->pesan ?? '', 'Transfer of ') ||
            str_starts_with($this->pesan ?? '', 'Transfer to ') ||
            str_starts_with($this->pesan ?? '', 'You received a transfer ') ||
            str_starts_with($this->pesan ?? '', 'Anda di transfer oleh ') ||
            str_starts_with($this->pesan ?? '', 'Anda menerima transfer ')
        ) {
            return 'Transfer berhasil';
        }

        return 'Pembaruan transaksi';
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
