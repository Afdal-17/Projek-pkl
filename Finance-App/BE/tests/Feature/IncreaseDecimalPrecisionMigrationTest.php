<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IncreaseDecimalPrecisionMigrationTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_10_22_000001_increase_decimal_precision_to_quadrillions.php';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['dompet', 'transaksi', 'transfer', 'target_tabungan'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('dompet', function ($table): void {
            $table->integer('id_dompet')->primary();
            $table->text('saldo_awal');
            $table->text('saldo');
        });
        Schema::create('transaksi', function ($table): void {
            $table->integer('id_transaksi')->primary();
            $table->text('jumlah');
        });
        Schema::create('transfer', function ($table): void {
            $table->integer('id_transfer')->primary();
            $table->text('jumlah');
        });
        Schema::create('target_tabungan', function ($table): void {
            $table->integer('id_target')->primary();
            $table->text('nominal_target');
            $table->text('nominal_terkumpul');
        });
    }

    protected function tearDown(): void
    {
        foreach (['dompet', 'transaksi', 'transfer', 'target_tabungan'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_transfer_range_guard_accepts_exact_signed_decimal_26_2_boundaries(): void
    {
        DB::table('transfer')->insert([
            ['id_transfer' => 1, 'jumlah' => '999999999999999999999999.99'],
            ['id_transfer' => 2, 'jumlah' => '-999999999999999999999999.99'],
        ]);

        $this->assertTransferRangeFits();

        $this->assertSame(
            ['999999999999999999999999.99', '-999999999999999999999999.99'],
            DB::table('transfer')->orderBy('id_transfer')->pluck('jumlah')->all()
        );
    }

    public function test_transfer_range_guard_rejects_value_outside_decimal_26_2(): void
    {
        DB::table('transfer')->insert([
            'id_transfer' => 1,
            'jumlah' => '1000000000000000000000000.00',
        ]);

        try {
            $this->assertTransferRangeFits();
            $this->fail('Expected the transfer amount to exceed DECIMAL(26,2).');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString(
                'Cannot change transfer.jumlah precision',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            '1000000000000000000000000.00',
            DB::table('transfer')->value('jumlah')
        );
    }

    public function test_up_checks_transfer_range_before_changing_any_column(): void
    {
        DB::table('transfer')->insert([
            'id_transfer' => 1,
            'jumlah' => '1000000000000000000000000.00',
        ]);

        try {
            $migration = require base_path(self::MIGRATION);
            $migration->up();
            $this->fail('Expected the migration to stop before changing columns.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString(
                'Cannot change transfer.jumlah precision',
                $exception->getMessage()
            );
        }

        $dompetColumns = collect(DB::select("PRAGMA table_info('dompet')"));
        $saldoColumn = $dompetColumns->firstWhere('name', 'saldo');

        $this->assertSame('TEXT', $saldoColumn->type);
        $this->assertSame(
            '1000000000000000000000000.00',
            DB::table('transfer')->value('jumlah')
        );
    }

    public function test_up_can_be_repeated_after_columns_reach_the_target_precision(): void
    {
        $migration = require base_path(self::MIGRATION);

        $migration->up();
        $migration->up();

        $this->assertSame('numeric', DB::selectOne(
            "SELECT type FROM pragma_table_info('transfer') WHERE name = 'jumlah'"
        )->type);
    }

    private function assertTransferRangeFits(): void
    {
        $migration = require base_path(self::MIGRATION);
        $guard = new \ReflectionMethod($migration, 'assertColumnFits');
        $guard->invoke(
            $migration,
            'transfer',
            'jumlah',
            'id_transfer',
            '999999999999999999999999.99',
            26
        );
    }
}
