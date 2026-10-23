<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifikasi', function (Blueprint $table): void {
            $table->decimal('jumlah', 26, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('notifikasi')
            ->whereNotNull('jumlah')
            ->orderBy('id_notifikasi')
            ->chunkById(500, function ($notifications): void {
                foreach ($notifications as $notification) {
                    if (bccomp((string) $notification->jumlah, '9999999999999.99', 2) > 0) {
                        throw new RuntimeException(
                            'Cannot reduce notifikasi.jumlah precision while values exceed DECIMAL(15,2).'
                        );
                    }
                }
            }, 'id_notifikasi');

        Schema::table('notifikasi', function (Blueprint $table): void {
            $table->decimal('jumlah', 15, 2)->nullable()->change();
        });
    }
};
