<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ([
            ['dompet', 'saldo_awal', 'id_dompet'],
            ['dompet', 'saldo', 'id_dompet'],
            ['transaksi', 'jumlah', 'id_transaksi'],
            ['transfer', 'jumlah', 'id_transfer'],
            ['target_tabungan', 'nominal_target', 'id_target'],
            ['target_tabungan', 'nominal_terkumpul', 'id_target'],
        ] as [$table, $column, $primaryKey]) {
            $this->assertColumnFits($table, $column, $primaryKey, '999999999999999999999999.99', 26);
        }

        Schema::table('dompet', function (Blueprint $table) {
            $table->decimal('saldo_awal', 26, 2)->default(0)->change();
            $table->decimal('saldo', 26, 2)->default(0)->change();
        });

        Schema::table('transaksi', function (Blueprint $table) {
            $table->decimal('jumlah', 26, 2)->change();
        });

        Schema::table('transfer', function (Blueprint $table) {
            $table->decimal('jumlah', 26, 2)->change();
        });

        Schema::table('target_tabungan', function (Blueprint $table) {
            $table->decimal('nominal_target', 26, 2)->change();
            $table->decimal('nominal_terkumpul', 26, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->assertColumnFits(
            'transfer',
            'jumlah',
            'id_transfer',
            '9999999999999.99',
            15
        );

        Schema::table('dompet', function (Blueprint $table) {
            $table->decimal('saldo_awal', 30, 2)->default(0)->change();
            $table->decimal('saldo', 30, 2)->default(0)->change();
        });

        Schema::table('transaksi', function (Blueprint $table) {
            $table->decimal('jumlah', 30, 2)->change();
        });

        Schema::table('transfer', function (Blueprint $table) {
            $table->decimal('jumlah', 15, 2)->change();
        });

        Schema::table('target_tabungan', function (Blueprint $table) {
            $table->decimal('nominal_target', 30, 2)->change();
            $table->decimal('nominal_terkumpul', 30, 2)->default(0)->change();
        });
    }

    private function assertColumnFits(
        string $table,
        string $column,
        string $primaryKey,
        string $maximum,
        int $precision
    ): void {
        DB::table($table)
            ->whereNotNull($column)
            ->orderBy($primaryKey)
            ->chunkById(500, function ($rows) use ($table, $column, $maximum, $precision): void {
                $minimum = '-'.$maximum;

                foreach ($rows as $row) {
                    $value = (string) $row->{$column};

                    if (bccomp($value, $maximum, 2) > 0 || bccomp($value, $minimum, 2) < 0) {
                        throw new RuntimeException(
                            "Cannot change {$table}.{$column} precision while values exceed DECIMAL({$precision},2)."
                        );
                    }
                }
            }, $primaryKey);
    }
};
