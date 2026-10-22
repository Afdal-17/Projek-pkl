<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dompet', function (Blueprint $table) {
            $table->boolean('terima_transfer')->default(false)->after('warna');
        });

        /* Backfill: untuk setiap user yang sudah punya dompet, aktifkan
         * dompet pertamanya sebagai penerima transfer agar fitur lama tetap
         * berfungsi tanpa konfigurasi ulang manual. */
        foreach (DB::table('user')->get(['id_user']) as $user) {
            $dompetPertama = DB::table('dompet')
                ->where('id_user', $user->id_user)
                ->orderBy('id_dompet')
                ->first();

            if ($dompetPertama !== null) {
                DB::table('dompet')
                    ->where('id_dompet', $dompetPertama->id_dompet)
                    ->update(['terima_transfer' => true]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dompet', function (Blueprint $table) {
            $table->dropColumn(['terima_transfer']);
        });
    }
};
