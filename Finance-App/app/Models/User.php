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
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nama',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'boolean',
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
