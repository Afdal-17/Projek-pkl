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
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id('id_transaksi');
            $table->unsignedBigInteger('id_kategori')->nullable();
            $table->foreignId('id_dompet')->constrained('dompet', 'id_dompet')->cascadeOnDelete();
            $table->string('nama_transaksi');
            $table->decimal('jumlah', 15, 2);
            $table->enum('jenis', ['pemasukan', 'pengeluaran']);
            $table->timestamp('tanggal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
