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
        Schema::create('target_tabungan', function (Blueprint $table) {
            $table->id('id_target');
            $table->foreignId('id_user')->constrained('user', 'id_user')->cascadeOnDelete();
            $table->foreignId('id_dompet')->constrained('dompet', 'id_dompet')->cascadeOnDelete();
            $table->string('nama_target');
            $table->decimal('nominal_target', 15, 2);
            $table->decimal('nominal_terkumpul', 15, 2)->default(0);
            $table->enum('status', ['belum_tercapai', 'tercapai'])->default('belum_tercapai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('target_tabungan');
    }
};
