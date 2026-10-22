<?php

namespace App\Services;

use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\User;
use Carbon\CarbonImmutable;

class DashboardSummaryService
{
    /**
     * Menormalkan nominal menjadi string desimal dua angka.
     */
    private function decimal(mixed $amount): string
    {
        $value = (string) ($amount ?? '0');

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException(
                'Nominal harus berupa angka positif dengan maksimal dua angka desimal.'
            );
        }

        return bcadd($value, '0', 2);
    }

    /**
     * Menjumlahkan nominal secara eksak menggunakan BCMath.
     */
    private function sumAmounts(iterable $amounts): string
    {
        $total = '0.00';

        foreach ($amounts as $amount) {
            $total = bcadd($total, $this->decimal($amount), 2);
        }

        return $total;
    }

    public function forUser(User $user): array
    {
        $walletIds = $user->dompet()->pluck('id_dompet');

        $monthStart = CarbonImmutable::now()->startOfMonth();
        $monthEnd = CarbonImmutable::now()->endOfMonth();

        $monthTransactions = Transaksi::query()
            ->whereIn('id_dompet', $walletIds)
            ->whereNull('id_transfer')
            ->whereBetween('tanggal', [$monthStart, $monthEnd]);

        $categoryExpenses = (clone $monthTransactions)
            ->where('jenis', 'pengeluaran')
            ->with('kategori')
            ->get()
            ->groupBy('id_kategori')
            ->map(function ($items): array {
                return [
                    'id_kategori' => $items->first()->id_kategori,
                    'nama_kategori' => $items->first()->kategori?->nama_kategori
                        ?? 'Tanpa kategori',
                    'total' => $this->sumAmounts(
                        $items->pluck('jumlah')
                    ),
                ];
            })
            ->values()
            ->sortByDesc('total')
            ->values()
            ->all();

        $walletBalances = $user->dompet()
            ->get(['saldo'])
            ->pluck('saldo');

        $totalBalance = $this->sumAmounts($walletBalances);

        $totalIncome = $this->sumAmounts(
            (clone $monthTransactions)
                ->where('jenis', 'pemasukan')
                ->pluck('jumlah')
        );

        $totalExpense = $this->sumAmounts(
            (clone $monthTransactions)
                ->where('jenis', 'pengeluaran')
                ->pluck('jumlah')
        );

        return [
            'periode' => [
                'mulai' => $monthStart->toDateString(),
                'selesai' => $monthEnd->toDateString(),
            ],

            'total_saldo' => $totalBalance,

            'total_pemasukan_bulan_ini' => $totalIncome,

            'total_pengeluaran_bulan_ini' => $totalExpense,

            'pengeluaran_per_kategori' => $categoryExpenses,

            'target_tabungan' => TargetTabungan::with('dompet')
                ->where('id_user', $user->id_user)
                ->get()
                ->map(fn (TargetTabungan $target): array => [
                    'id_target' => $target->id_target,
                    'nama_target' => $target->nama_target,
                    'nama_dompet' => $target->dompet->nama_dompet,

                    'saldo_dompet' => $this->decimal(
                        $target->dompet->saldo
                    ),

                    'jumlah_terkumpul' => $this->decimal(
                        $target->jumlah_terkumpul
                    ),

                    'nominal_target' => $this->decimal(
                        $target->nominal_target
                    ),

                    'progress' => $target->progress,
                    'tercapai' => $target->tercapai,
                ])
                ->all(),
        ];
    }
}