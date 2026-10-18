
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dompet', function (Blueprint $table) {
            $table->decimal('saldo_awal', 30, 2)->default(0)->change();
            $table->decimal('saldo', 30, 2)->default(0)->change();
        });

        Schema::table('transaksi', function (Blueprint $table) {
            $table->decimal('jumlah', 30, 2)->change();
        });

        Schema::table('target_tabungan', function (Blueprint $table) {
            $table->decimal('nominal_target', 30, 2)->change();
            $table->decimal('nominal_terkumpul', 30, 2)->default(0)->change();
            $table->decimal('saldo_awal_dompet', 30, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('target_tabungan', function (Blueprint $table) {
            $table->decimal('nominal_target', 15, 2)->change();
            $table->decimal('nominal_terkumpul', 15, 2)->default(0)->change();
            $table->decimal('saldo_awal_dompet', 15, 2)->default(0)->change();
        });

        Schema::table('transaksi', function (Blueprint $table) {
            $table->decimal('jumlah', 15, 2)->change();
        });

        Schema::table('dompet', function (Blueprint $table) {
            $table->decimal('saldo_awal', 15, 2)->default(0)->change();
            $table->decimal('saldo', 15, 2)->default(0)->change();
        });
    }
};
