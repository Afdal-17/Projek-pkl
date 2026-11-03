<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinancialAmountPrecisionRollbackTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_10_16_040457_increase_financial_amount_columns_precision.php';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('dompet');
        Schema::dropIfExists('transaksi');
        Schema::dropIfExists('target_tabungan');

        Schema::create('dompet', function ($table): void {
            $table->integer('id_dompet')->primary();
            $table->text('saldo_awal');
            $table->text('saldo');
        });
        Schema::create('transaksi', function ($table): void {
            $table->integer('id_transaksi')->primary();
            $table->text('jumlah');
        });
        Schema::create('target_tabungan', function ($table): void {
            $table->integer('id_target')->primary();
            $table->text('nominal_target');
            $table->text('nominal_terkumpul');
            $table->text('saldo_awal_dompet');
        });

        DB::table('dompet')->insert([
            'id_dompet' => 1,
            'saldo_awal' => '10000000000000.00',
            'saldo' => '10000000000000.00',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('dompet');
        Schema::dropIfExists('transaksi');
        Schema::dropIfExists('target_tabungan');

        parent::tearDown();
    }

    public function test_down_rejects_out_of_range_amount_before_changing_schema_or_data(): void
    {
        $migration = require base_path(self::MIGRATION);

        try {
            $migration->down();
            $this->fail('Expected rollback to reject a value outside DECIMAL(15,2).');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString(
                'Cannot reduce dompet.saldo_awal precision',
                $exception->getMessage()
            );
        }

        $columns = collect(DB::select("PRAGMA table_info('dompet')"));
        $this->assertSame('TEXT', $columns->firstWhere('name', 'saldo_awal')->type);
        $this->assertSame('10000000000000.00', DB::table('dompet')->value('saldo_awal'));
    }
}
