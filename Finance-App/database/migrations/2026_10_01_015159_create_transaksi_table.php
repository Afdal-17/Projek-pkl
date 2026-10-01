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
            $table->foreignId('id_kategori');
            $table->foreignId('id_tranfer');
            $table->string('nama_transaksi');
            $table->decimal('jumlah');
            $table->enum('jenis',['pemasukan','pengeluaran']);
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
