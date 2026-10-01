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
        Schema::create('dompet', function (Blueprint $table) {
            $table->id('id_dompet');
            $table->foreignId('id_user');
            $table->string('nama_dompet');
            $table->text('deskripsi');
            $table->decimal('saldo_awal');
            $table->decimal('saldo');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dompet');
    }
};
