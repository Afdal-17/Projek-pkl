<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->string('nomor_telepon', 32)->nullable();
            $table->string('lokasi')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('mata_uang', 3)->default('IDR');
            $table->string('awal_minggu', 10)->default('monday');
            $table->timestamp('last_seen_at')->nullable();
        });

        Schema::table('dompet', function (Blueprint $table) {
            $table->string('jenis', 20)->default('digital');
            $table->string('warna', 20)->default('brand');
        });

        Schema::table('target_tabungan', function (Blueprint $table) {
            $table->text('deskripsi')->nullable();
            $table->decimal('saldo_awal_dompet', 15, 2)->default(0);
        });

        $colors = ['income', 'brand', 'warn', 'expense', 'dark'];
        $colorIndexes = [];

        foreach (DB::table('dompet')->orderBy('id_user')->orderByDesc('created_at')->orderByDesc('id_dompet')->get(['id_dompet', 'id_user']) as $wallet) {
            $index = $colorIndexes[$wallet->id_user] ?? 0;
            DB::table('dompet')->where('id_dompet', $wallet->id_dompet)->update([
                'warna' => $colors[$index % count($colors)],
                'jenis' => $index === 0 ? 'physical' : 'digital',
            ]);
            $colorIndexes[$wallet->id_user] = $index + 1;
        }

        foreach (DB::table('target_tabungan')
            ->join('dompet', 'target_tabungan.id_dompet', '=', 'dompet.id_dompet')
            ->get(['target_tabungan.id_target', 'target_tabungan.nominal_terkumpul']) as $target) {
            DB::table('target_tabungan')->where('id_target', $target->id_target)->update([
                'saldo_awal_dompet' => $target->nominal_terkumpul,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('target_tabungan', function (Blueprint $table) {
            $table->dropColumn(['deskripsi', 'saldo_awal_dompet']);
        });

        Schema::table('dompet', function (Blueprint $table) {
            $table->dropColumn(['jenis', 'warna']);
        });

        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn([
                'nomor_telepon',
                'lokasi',
                'avatar_path',
                'mata_uang',
                'awal_minggu',
                'last_seen_at',
            ]);
        });
    }
};