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
        Schema::table('dompet', function (Blueprint $table) {
            $table->decimal('saldo_awal', 20, 2)->default(0)->change();
            $table->decimal('saldo', 20, 2)->default(0)->change();
        });

        Schema::table('transaksi', function (Blueprint $table) {
            $table->decimal('jumlah', 20, 2)->change();
        });

        Schema::table('transfer', function (Blueprint $table) {
            $table->decimal('jumlah', 20, 2)->change();
        });

        Schema::table('target_tabungan', function (Blueprint $table) {
            $table->decimal('nominal_target', 20, 2)->change();
            $table->decimal('nominal_terkumpul', 20, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dompet', function (Blueprint $table) {
            $table->decimal('saldo_awal', 15, 2)->default(0)->change();
            $table->decimal('saldo', 15, 2)->default(0)->change();
        });

        Schema::table('transaksi', function (Blueprint $table) {
            $table->decimal('jumlah', 15, 2)->change();
        });

        Schema::table('transfer', function (Blueprint $table) {
            $table->decimal('jumlah', 15, 2)->change();
        });

        Schema::table('target_tabungan', function (Blueprint $table) {
            $table->decimal('nominal_target', 15, 2)->change();
            $table->decimal('nominal_terkumpul', 15, 2)->default(0)->change();
        });
    }
};
