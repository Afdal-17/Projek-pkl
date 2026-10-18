@extends('layouts.main')

@section('title', 'Wallet History')

@section('content')

<a href="/dashboard" class="inline-flex items-center gap-2 text-sm text-muted hover:text-ink">
    <x-icon name="arrow-left" size="h-4 w-4" /> Back to wallets
</a>

@php
    $formattedSaldo = finance_money($dompet->saldo);
    $saldoLen = mb_strlen(trim($formattedSaldo));
    $balanceClass = match (true) {
        $saldoLen <= 15 => 'text-2xl sm:text-2xl',
        $saldoLen <= 22 => 'text-xl sm:text-2xl',
        default => 'text-lg sm:text-xl md:text-2xl',
    };
@endphp

<div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-6 min-w-0 max-w-full">
    <div class="min-w-0 max-w-full">
        <h1 class="text-2xl sm:text-3xl font-bold text-ink break-words">{{ $dompet->nama_dompet }}</h1>
        <p class="mt-1 text-sm text-muted break-words">{{ $dompet->deskripsi ?: 'Wallet balance history' }}</p>
    </div>
    <div class="flex flex-col sm:items-end min-w-0 max-w-full sm:text-right">
        <p class="text-xs text-muted">Current balance</p>
        <p class="mt-0.5 {{ $balanceClass }} font-bold text-ink tabular-nums leading-tight break-words [overflow-wrap:anywhere]">
            {{ $formattedSaldo }}
        </p>
    </div>
</div>

<x-card class="mt-6 sm:mt-8 overflow-hidden w-full max-w-full min-w-0">
    @if ($transactions->isEmpty())
        <p class="px-6 py-12 text-center text-sm text-muted">No transactions for this wallet yet.</p>
    @else
        <div class="divide-y divide-line">
            @foreach ($transactions as $t)
                <div class="flex items-center justify-between gap-3 px-4 py-3.5 sm:px-6 sm:py-4 min-w-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $t['type'] === 'income' ? 'bg-income-soft text-income' : ($t['type'] === 'transfer' ? 'bg-brand-soft text-brand' : 'bg-expense-soft text-expense') }}">
                            <x-icon :name="$t['icon'] ?? ($t['type'] === 'transfer' ? 'transfer' : ($t['type'] === 'income' ? 'landmark' : 'utensils'))" size="h-4 w-4" />
                        </span>
                        <div class="leading-tight min-w-0">
                            <p class="text-sm font-semibold truncate">{{ $t['name'] }}</p>
                            <p class="text-xs text-muted truncate">{{ $t['category'] }} · {{ $t['date'] }}</p>
                        </div>
                    </div>
                    <p class="text-sm font-semibold shrink-0 text-right tabular-nums {{ $t['jenis'] === 'pemasukan' ? 'text-income' : 'text-expense' }}">
                        {{ $t['jenis'] === 'pemasukan' ? '+' : '-' }} {{ finance_money($t['amount']) }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif
</x-card>

@endsection
