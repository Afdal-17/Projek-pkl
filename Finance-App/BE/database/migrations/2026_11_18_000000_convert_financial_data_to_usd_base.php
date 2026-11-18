<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    private const RATE_TABLE = 'currency_base_migration_rates';

    public function up(): void
    {
        Schema::table('dompet', function (Blueprint $table): void {
            $table->decimal('saldo_awal', 26, 8)->default(0)->change();
            $table->decimal('saldo', 26, 8)->default(0)->change();
        });
        Schema::table('transaksi', function (Blueprint $table): void {
            $table->decimal('jumlah', 26, 8)->change();
        });
        Schema::table('transfer', function (Blueprint $table): void {
            $table->decimal('jumlah', 26, 8)->change();
        });
        Schema::table('target_tabungan', function (Blueprint $table): void {
            $table->decimal('nominal_target', 26, 8)->change();
            $table->decimal('nominal_terkumpul', 26, 8)->default(0)->change();
            $table->decimal('saldo_awal_dompet', 26, 8)->default(0)->change();
        });
        Schema::table('notifikasi', function (Blueprint $table): void {
            $table->decimal('jumlah', 26, 8)->nullable()->change();
        });

        // A previous attempt may have completed the data updates before the
        // migration runner failed to commit. The stored rate marks that work.
        if (Schema::hasTable(self::RATE_TABLE) && DB::table(self::RATE_TABLE)->exists()) {
            return;
        }

        if (config('currency.live', true)) {
            $response = Http::timeout((int) config('currency.timeout', 5))
                ->retry(2, 200)
                ->get(config('currency.provider_url', 'https://api.frankfurter.app/latest'), [
                    'from' => 'USD',
                    'to' => 'IDR',
                ])
                ->throw();
            $rate = $response->json('rates.IDR');
        } else {
            $rate = config('currency.fallback_rates.IDR');
        }

        if (! is_numeric($rate) || (float) $rate <= 0) {
            throw new \RuntimeException('Kurs USD/IDR tidak valid; migrasi data ke USD dibatalkan.');
        }

        $rate = (float) $rate;

        if (! Schema::hasTable(self::RATE_TABLE)) {
            Schema::create(self::RATE_TABLE, function (Blueprint $table): void {
                $table->id();
                $table->decimal('idr_per_usd', 20, 8);
            });
        }

        DB::transaction(function () use ($rate): void {
            DB::table(self::RATE_TABLE)->insert(['idr_per_usd' => $rate]);

            foreach ([
                'dompet' => ['saldo', 'saldo_awal'],
                'transaksi' => ['jumlah'],
                'transfer' => ['jumlah'],
                'target_tabungan' => ['nominal_target', 'nominal_terkumpul', 'saldo_awal_dompet'],
                'notifikasi' => ['jumlah'],
            ] as $table => $columns) {
                foreach ($columns as $column) {
                    DB::statement(
                        "UPDATE {$table} SET {$column} = ROUND({$column} / ?, 8) WHERE {$column} IS NOT NULL",
                        [$rate]
                    );
                }
            }
        });
    }

    public function down(): void
    {
        $rate = (float) DB::table(self::RATE_TABLE)->value('idr_per_usd');

        if ($rate <= 0) {
            throw new \RuntimeException('Kurs migrasi USD/IDR tidak tersedia; rollback dibatalkan.');
        }

        DB::transaction(function () use ($rate): void {
            foreach ([
                'dompet' => ['saldo', 'saldo_awal'],
                'transaksi' => ['jumlah'],
                'transfer' => ['jumlah'],
                'target_tabungan' => ['nominal_target', 'nominal_terkumpul', 'saldo_awal_dompet'],
                'notifikasi' => ['jumlah'],
            ] as $table => $columns) {
                foreach ($columns as $column) {
                    DB::statement(
                        "UPDATE {$table} SET {$column} = ROUND({$column} * ?, 2) WHERE {$column} IS NOT NULL",
                        [$rate]
                    );
                }
            }
        });

        Schema::drop(self::RATE_TABLE);

        Schema::table('dompet', function (Blueprint $table): void {
            $table->decimal('saldo_awal', 20, 2)->default(0)->change();
            $table->decimal('saldo', 20, 2)->default(0)->change();
        });
        Schema::table('transaksi', function (Blueprint $table): void {
            $table->decimal('jumlah', 20, 2)->change();
        });
        Schema::table('transfer', function (Blueprint $table): void {
            $table->decimal('jumlah', 20, 2)->change();
        });
        Schema::table('target_tabungan', function (Blueprint $table): void {
            $table->decimal('nominal_target', 20, 2)->change();
            $table->decimal('nominal_terkumpul', 20, 2)->default(0)->change();
            $table->decimal('saldo_awal_dompet', 15, 2)->default(0)->change();
        });
        Schema::table('notifikasi', function (Blueprint $table): void {
            $table->decimal('jumlah', 15, 2)->nullable()->change();
        });
    }
};
