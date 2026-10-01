<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kategori extends Model
{
    use HasFactory;

    protected $table = 'kategori';

    protected $primaryKey = 'id_kategori';

    public $timestamps = false;

    protected $fillable = [
        'nama_kategori',
        'jenis',
    ];

    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'id_kategori', 'id_kategori');
    }
}
