<?php

namespace Database\Seeders;

use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'nama' => 'Admin',
            'email' => 'admin@finance.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $user = User::create([
            'nama' => 'User',
            'email' => 'user@finance.test',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $dompetUtama = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet Utama',
            'deskripsi' => 'Dompet untuk kebutuhan sehari-hari',
            'saldo_awal' => 500000,
            'saldo' => 500000,
        ]);

        $dompetTabungan = Dompet::create([
            'id_user' => $user->id_user,
            'nama_dompet' => 'Dompet Tabungan',
            'deskripsi' => 'Dompet untuk menabung',
            'saldo_awal' => 0,
            'saldo' => 0,
        ]);

        $gaji = Kategori::create([
            'nama_kategori' => 'Gaji',
            'jenis' => 'pemasukan',
        ]);

        $makan = Kategori::create([
            'nama_kategori' => 'Makan',
            'jenis' => 'pengeluaran',
        ]);

        $transportasi = Kategori::create([
            'nama_kategori' => 'Transportasi',
            'jenis' => 'pengeluaran',
        ]);

        Transaksi::create([
            'id_kategori' => $gaji->id_kategori,
            'id_dompet' => $dompetUtama->id_dompet,
            'nama_transaksi' => 'Gaji Bulan Ini',
            'jumlah' => 1000000,
            'jenis' => 'pemasukan',
            'tanggal' => now(),
        ]);

        Transaksi::create([
            'id_kategori' => $makan->id_kategori,
            'id_dompet' => $dompetUtama->id_dompet,
            'nama_transaksi' => 'Makan Siang',
            'jumlah' => 25000,
            'jenis' => 'pengeluaran',
            'tanggal' => now(),
        ]);

        TargetTabungan::create([
            'id_user' => $user->id_user,
            'id_dompet' => $dompetTabungan->id_dompet,
            'nama_target' => 'Beli Laptop',
            'nominal_target' => 15000000,
            'nominal_terkumpul' => 0,
            'status' => 'belum_tercapai',
        ]);

        Transfer::create([
            'id_user' => $user->id_user,
            'id_dompet_asal' => $dompetUtama->id_dompet,
            'id_dompet_tujuan' => $dompetTabungan->id_dompet,
            'jumlah' => 100000,
            'catatan' => 'Menabung untuk laptop',
            'tanggal_transfer' => now(),
        ]);
    }
}
