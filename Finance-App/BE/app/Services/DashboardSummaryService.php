<?php

namespace App\Services;

use App\Models\TargetTabungan;
use App\Models\Transaksi;
use App\Models\User;
use Carbon\CarbonImmutable;

class DashboardSummaryService
{
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
                    'nama_kategori' => $items->first()->kategori?->nama_kategori ?? 'Tanpa kategori',
                    'total' => round((float) $items->sum('jumlah'), 8),
                    'total_money' => CurrencyService::moneyData((float) $items->sum('jumlah')),
                ];
            })
            ->values()
            ->sortByDesc('total')
            ->values()
            ->all();

        $totalSaldo = round((float) $user->dompet()->sum('saldo'), 8);
        $totalPemasukan = round((float) (clone $monthTransactions)->where('jenis', 'pemasukan')->sum('jumlah'), 8);
        $totalPengeluaran = round((float) (clone $monthTransactions)->where('jenis', 'pengeluaran')->sum('jumlah'), 8);

        return [
            'periode' => [
                'mulai' => $monthStart->toDateString(),
                'selesai' => $monthEnd->toDateString(),
            ],
            'total_saldo' => $totalSaldo,
            'total_saldo_money' => CurrencyService::moneyData($totalSaldo),
            'total_pemasukan_bulan_ini' => $totalPemasukan,
            'total_pemasukan_bulan_ini_money' => CurrencyService::moneyData($totalPemasukan),
            'total_pengeluaran_bulan_ini' => $totalPengeluaran,
            'total_pengeluaran_bulan_ini_money' => CurrencyService::moneyData($totalPengeluaran),
            'pengeluaran_per_kategori' => $categoryExpenses,
            'target_tabungan' => TargetTabungan::with('dompet')
                ->where('id_user', $user->id_user)
                ->get()
                ->map(fn (TargetTabungan $target): array => [
                    'id_target' => $target->id_target,
                    'nama_target' => $target->nama_target,
                    'nama_dompet' => $target->dompet->nama_dompet,
                    'saldo_dompet' => round((float) $target->dompet->saldo, 8),
                    'saldo_dompet_money' => CurrencyService::moneyData((float) $target->dompet->saldo),
                    'jumlah_terkumpul' => round((float) $target->jumlah_terkumpul, 8),
                    'jumlah_terkumpul_money' => CurrencyService::moneyData((float) $target->jumlah_terkumpul),
                    'nominal_target' => round((float) $target->nominal_target, 8),
                    'nominal_target_money' => CurrencyService::moneyData((float) $target->nominal_target),
                    'progress' => $target->progress,
                    'tercapai' => $target->tercapai,
                ])->all(),
        ];
    }
}
