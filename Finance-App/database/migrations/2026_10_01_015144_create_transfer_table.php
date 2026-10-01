<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transfer', function (Blueprint $table) {
            $table->id('id_transfer');
            $table->foreignId('id_user');
            $table->foreignId('id_dompet_asal');
            $table->foreignId('id_dompet_tujuan');
            $table->decimal('jumlah');
            $table->text('catatan');
            $table->timestamp('tanggal_tranfer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer');
    }
};
