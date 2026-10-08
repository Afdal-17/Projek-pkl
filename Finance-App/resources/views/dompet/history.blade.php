@extends('layouts.main')

@section('title', 'Wallet History')

@section('content')

<a href="/dashboard" class="inline-flex items-center gap-2 text-sm text-muted hover:text-ink">
    <x-icon name="arrow-left" size="h-4 w-4" /> Back to wallets
</a>

<div class="mt-4 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold">{{ $dompet->nama_dompet }}</h1>
        <p class="mt-1 text-sm text-muted">{{ $dompet->deskripsi ?: 'Wallet balance history' }}</p>
    </div>
    <div class="text-right">
        <p class="text-xs text-muted">Current balance</p>
        <p class="text-2xl font-bold">Rp {{ number_format((float) $dompet->saldo, 0, ',', '.') }}</p>
    </div>
</div>

<x-card class="mt-8 overflow-hidden">
    @if ($transactions->isEmpty())
        <p class="px-6 py-12 text-center text-sm text-muted">No transactions for this wallet yet.</p>
    @else
        <div class="divide-y divide-line">
            @foreach ($transactions as $t)
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg {{ $t['type'] === 'income' ? 'bg-income-soft text-income' : ($t['type'] === 'transfer' ? 'bg-brand-soft text-brand' : 'bg-expense-soft text-expense') }}">
                            <x-icon :name="$t['icon'] ?? ($t['type'] === 'transfer' ? 'transfer' : ($t['type'] === 'income' ? 'landmark' : 'utensils'))" size="h-4 w-4" />
                        </span>
                        <div class="leading-tight">
                            <p class="text-sm font-semibold">{{ $t['name'] }}</p>
                            <p class="text-xs text-muted">{{ $t['category'] }} · {{ $t['date'] }}</p>
                        </div>
                    </div>
                    <p class="text-sm font-semibold {{ $t['jenis'] === 'pemasukan' ? 'text-income' : 'text-expense' }}">
                        {{ $t['jenis'] === 'pemasukan' ? '+' : '-' }} Rp {{ number_format($t['amount'], 0, ',', '.') }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif
</x-card>

@endsection
