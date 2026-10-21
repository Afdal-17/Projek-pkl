<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'user';

    protected $primaryKey = 'id_user';

    /**
     * Nilai default atribut, selaras dengan default di migration.
     *
     * Penting karena 'role' TIDAK ada di $fillable (mencegah mass-assignment
     * privilege escalation): instance baru tetap membawa role 'user' di memori,
     * bukan NULL sampai di-refresh dari database.
     */
    protected $attributes = [
        'role' => 'user',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nama',
        'email',
        'google_id',
        'password',
        'status',
        'nomor_telepon',
        'lokasi',
        'avatar_path',
        'mata_uang',
        'last_seen_at',
        'banned_at',
        'ban_reason',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_kode',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'boolean',
            'last_seen_at' => 'datetime',
            'banned_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'otp_verified_at' => 'datetime',
        ];
    }

    public function getNameAttribute(): string
    {
        return $this->nama;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'id_user', 'id_user');
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function dompet()
    {
        return $this->hasMany(Dompet::class, 'id_user', 'id_user');
    }

    public function targetTabungan()
    {
        return $this->hasMany(TargetTabungan::class, 'id_user', 'id_user');
    }

    public function transfer()
    {
        return $this->hasMany(Transfer::class, 'id_user', 'id_user');
    }
}
