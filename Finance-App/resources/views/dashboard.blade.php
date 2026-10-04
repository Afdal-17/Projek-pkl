@extends('layouts.app')

@section('title', 'My Wallets')

@section('content')
@php
    // DATA DUMMY: nanti diganti data dari backend
    $wallets = [
        ['name' => 'Cash',  'type' => 'Physical wallet', 'balance' => 3250000, 'icon' => 'banknote',   'color' => 'income'],
        ['name' => 'DANA',  'type' => 'Digital wallet',  'balance' => 4500000, 'icon' => 'smartphone', 'color' => 'brand'],
        ['name' => 'GoPay', 'type' => 'Digital wallet',  'balance' => 4250000, 'icon' => 'smartphone', 'color' => 'warn'],
    ];
    $categories = [
        ['Salary', 'income', 'Rp 850.000'],
        ['Food', 'expense', 'Rp 85.000'],
        ['Transportation', 'expense', 'Rp 850.000'],
    ];
    $tones = [
        'brand'   => ['soft' => 'bg-brand-soft text-brand',     'solid' => 'bg-brand'],
        'income'  => ['soft' => 'bg-income-soft text-income',   'solid' => 'bg-income'],
        'warn'    => ['soft' => 'bg-warn-soft text-warn',       'solid' => 'bg-warn'],
        'expense' => ['soft' => 'bg-expense-soft text-expense', 'solid' => 'bg-expense'],
        'dark'    => ['soft' => 'bg-gray-100 text-dark',        'solid' => 'bg-dark'],
    ];
    $total   = array_sum(array_column($wallets, 'balance'));
    $largest = collect($wallets)->sortByDesc('balance')->first();
@endphp

<div x-data="{ 
    open: false, 
    mode: 'edit',
    editing: {
        name: '',
        type: 'Digital wallet',
        balance: '',
        color: 'brand'
    }
}">

    {{-- Judul --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-3xl font-bold">My Wallets</h1>
            <p class="mt-1 text-sm text-muted">Track balances across all the places you keep money.</p>
        </div>
        <x-button x-on:click="
            mode = 'add';
            editing = {
                name: '',
                type: 'Digital wallet',
                balance: '',
                color: 'brand'
            };
            open = true;
        ">
            <x-icon name="plus" size="h-4 w-4" /> Add Wallet
        </x-button>
    </div>

    {{-- Tiga kartu ringkasan --}}
    <div class="mt-8 grid gap-6 lg:grid-cols-[422fr_461fr_256fr]">

        <x-card class="p-6">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-brand-soft"><x-icon name="wallet" /></span>
                    <div class="leading-tight">
                        <p class="text-sm text-muted">Total balance</p>
                        <p class="text-xs text-muted">Updated a few seconds ago</p>
                    </div>
                </div>
                <x-badge color="brand">{{ count($wallets) }} active wallets</x-badge>
            </div>
            <p class="mt-6 text-4xl font-bold">Rp {{ number_format($total, 0, ',', '.') }}</p>
            <div class="mt-3 flex gap-4 text-xs">
                <span class="flex items-center gap-1 text-income"><x-icon name="arrow-up" size="h-3 w-3" /> Rp 850.000 this month</span>
                <span class="flex items-center gap-1 text-expense"><x-icon name="arrow-down" size="h-3 w-3" /> Rp 850.000 this month</span>
            </div>
        </x-card>

        <x-card class="p-6">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-brand-soft"><x-icon name="wallet" /></span>
                    <div class="leading-tight">
                        <p class="text-sm text-muted">Category</p>
                        <p class="text-xs text-muted">Updated a few seconds ago</p>
                    </div>
                </div>
                <x-badge color="brand">{{ count($categories) + 1 }} active Category</x-badge>
            </div>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach ($categories as [$name, $type, $amount])
                    <li class="flex items-center justify-between">
                        <span class="font-medium">{{ $name }}</span>
                        <span class="flex items-center gap-1 {{ $type === 'income' ? 'text-income' : 'text-expense' }}">
                            <x-icon :name="$type === 'income' ? 'arrow-up' : 'arrow-down'" size="h-3 w-3" /> {{ $amount }} this month
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card class="p-6">
            <div class="flex items-start justify-between">
                <p class="text-sm text-muted">Total saved</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-income-soft text-income"><x-icon name="dollar" size="h-5 w-5" /></span>
            </div>
            <p class="mt-4 text-2xl font-bold">Rp 34.000.000</p>
            <p class="mt-2 text-xs text-income">+Rp 2.500.000 this month</p>
        </x-card>
    </div>

    {{-- Daftar wallet --}}
    <div class="mt-8 flex items-center justify-between">
        <h2 class="font-semibold">Your wallets</h2>
        <span class="text-sm text-muted">{{ count($wallets) }} wallets</span>
    </div>

    <div class="mt-4 grid gap-6 md:grid-cols-3">
        @foreach ($wallets as $w)
            <x-card class="p-6">
                <div class="flex items-start justify-between">
                    <span class="flex h-11 w-11 items-center justify-center rounded-lg {{ $tones[$w['color']]['soft'] }}">
                        <x-icon :name="$w['icon']" />
                    </span>
                    <button type="button"
                        x-on:click="
                            mode = 'edit';
                            editing = @js($w);
                            open = true;"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-line hover:bg-page">
                    <x-icon name="more" size="h-4 w-4" />
                </button>
                </div>
                <p class="mt-4 text-lg font-semibold">{{ $w['name'] }}</p>
                <p class="text-sm text-muted">{{ $w['type'] }}</p>
                <p class="mt-3 text-[11px] uppercase tracking-wide text-muted">Available balance</p>
                <p class="text-2xl font-bold">Rp {{ number_format($w['balance'], 0, ',', '.') }}</p>
            </x-card>
        @endforeach
    </div>

    {{-- Distribusi saldo --}}
    <x-card class="mt-8 p-6">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">Balance distribution</h2>
            <span class="text-sm text-muted">All wallets</span>
        </div>
        <div class="mt-4 flex gap-1">
            @foreach ($wallets as $w)
                <span class="h-2.5 basis-0 rounded-full {{ $tones[$w['color']]['solid'] }}" style="flex-grow: {{ $w['balance'] }}"></span>
            @endforeach
        </div>
        <p class="mt-4 text-sm text-muted">
            {{ $largest['name'] }} currently holds the largest share of your available balance at {{ round($largest['balance'] / $total * 100, 1) }}%.
        </p>
    </x-card>

    {{-- Modal Edit Wallet --}}
    <div x-cloak x-show="open" x-transition.opacity
         x-on:keydown.escape.window="open = false"
         x-on:click.self="open = false"
         class="fixed inset-0 z-30 flex items-center justify-center bg-black/30 p-4">
        <div class="w-full max-w-sm rounded-2xl border border-line bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between">
                <div>
                    <h3
                        class="text-2xl font-bold"
                        x-text="mode === 'add' ? 'Add Wallet' : 'Edit Wallet'">
                    </h3>
                    <p class="mt-1 text-xs text-muted"
                        x-text="mode === 'add'
                            ? 'Create a new wallet to track your money.'
                            : 'Update the details for this wallet.'">
                    </p>
                </div>
                <button type="button" x-on:click="open = false" class="flex h-9 w-9 items-center justify-center rounded-lg border border-line hover:bg-page">
                    <x-icon name="x" size="h-4 w-4" />
                </button>
            </div>

            <div class="mt-5 space-y-4">
                <x-input label="Wallet name" name="wallet_name" x-model="editing.name" />

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Wallet type</label>
                    <select x-model="editing.type" class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                        <option>Physical wallet</option>
                        <option>Digital wallet</option>
                        <option>Bank account</option>
                    </select>
                </div>

                <x-input label="Current balance" name="wallet_balance" inputmode="numeric" x-model="editing.balance" />

                <div>
                    <label class="mb-2 block text-sm font-medium">Wallet color</label>
                    <div class="flex gap-3">
                        @foreach (['brand', 'income', 'warn', 'expense', 'dark'] as $c)
                            <button type="button" x-on:click="editing.color = '{{ $c }}'"
                                    class="flex h-8 w-8 items-center justify-center rounded-full text-white {{ $tones[$c]['solid'] }}">
                                <x-icon name="check" size="h-4 w-4" x-show="editing.color === '{{ $c }}'" />
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-button variant="outline" x-on:click="open = false">
                    Cancel
                </x-button>

                <x-button x-on:click="open = false">
                    <span x-text="mode === 'add' ? 'Add Wallet' : 'Save changes'"></span>
                </x-button>
            </div>
        </div>
    </div>

</div>
@endsection