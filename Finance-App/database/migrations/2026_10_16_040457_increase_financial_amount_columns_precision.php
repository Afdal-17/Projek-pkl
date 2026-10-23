
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        foreach ([
            ['dompet', 'saldo_awal', 'id_dompet'],
            ['dompet', 'saldo', 'id_dompet'],
            ['transaksi', 'jumlah', 'id_transaksi'],
            ['target_tabungan', 'nominal_target', 'id_target'],
            ['target_tabungan', 'nominal_terkumpul', 'id_target'],
            ['target_tabungan', 'saldo_awal_dompet', 'id_target'],
        ] as [$table, $column, $primaryKey]) {
            DB::table($table)
                ->whereNotNull($column)
                ->orderBy($primaryKey)
                ->chunkById(500, function ($rows) use ($table, $column): void {
                    foreach ($rows as $row) {
                        $value = (string) $row->{$column};

                        if (
                            bccomp($value, '9999999999999.99', 2) > 0
                            || bccomp($value, '-9999999999999.99', 2) < 0
                        ) {
                            throw new RuntimeException(
                                "Cannot reduce {$table}.{$column} precision while values exceed DECIMAL(15,2)."
                            );
                        }
                    }
                }, $primaryKey);
        }

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
