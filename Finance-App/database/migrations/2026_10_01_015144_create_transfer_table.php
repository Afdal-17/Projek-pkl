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
            $table->foreignId('id_user')->constrained('user', 'id_user')->cascadeOnDelete();
            $table->foreignId('id_dompet_asal')->constrained('dompet', 'id_dompet')->restrictOnDelete();
            $table->foreignId('id_dompet_tujuan')->constrained('dompet', 'id_dompet')->restrictOnDelete();
            $table->decimal('jumlah', 15, 2);
            $table->text('catatan')->nullable();
            $table->timestamp('tanggal_transfer');
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
