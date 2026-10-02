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
                    'total' => round((float) $items->sum('jumlah'), 2),
                ];
            })
            ->values()
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'periode' => [
                'mulai' => $monthStart->toDateString(),
                'selesai' => $monthEnd->toDateString(),
            ],
            'total_saldo' => round((float) $user->dompet()->sum('saldo'), 2),
            'total_pemasukan_bulan_ini' => round((float) (clone $monthTransactions)->where('jenis', 'pemasukan')->sum('jumlah'), 2),
            'total_pengeluaran_bulan_ini' => round((float) (clone $monthTransactions)->where('jenis', 'pengeluaran')->sum('jumlah'), 2),
            'pengeluaran_per_kategori' => $categoryExpenses,
            'target_tabungan' => TargetTabungan::with('dompet')
                ->where('id_user', $user->id_user)
                ->get()
                ->map(fn (TargetTabungan $target): array => [
                    'id_target' => $target->id_target,
                    'nama_target' => $target->nama_target,
                    'nama_dompet' => $target->dompet->nama_dompet,
                    'saldo_dompet' => round((float) $target->dompet->saldo, 2),
                    'nominal_target' => round((float) $target->nominal_target, 2),
                    'progress' => $target->progress,
                    'tercapai' => $target->tercapai,
                ])->all(),
        ];
    }
}
