@extends('layouts.main')

@section('title', 'My Wallets')

@section('content')
@php
    $currency = auth()->user()->mata_uang ?? 'IDR';
    $currencySymbols = ['IDR' => 'Rp', 'USD' => '$', 'EUR' => '€'];
    $currencySymbol = $currencySymbols[$currency] ?? 'Rp';
    $decimalSeparator = $currency === 'USD' ? '.' : ',';
    $thousandsSeparator = $currency === 'USD' ? ',' : '.';
    $formatMoney = fn ($amount) => $currencySymbol.' '.number_format(
        (float) $amount,
        $currency === 'IDR' ? 0 : 2,
        $decimalSeparator,
        $thousandsSeparator
    );
    $tones = [
        'brand'   => ['soft' => 'bg-brand-soft text-brand',     'solid' => 'bg-brand'],
        'income'  => ['soft' => 'bg-income-soft text-income',   'solid' => 'bg-income'],
        'warn'    => ['soft' => 'bg-warn-soft text-warn',       'solid' => 'bg-warn'],
        'expense' => ['soft' => 'bg-expense-soft text-expense', 'solid' => 'bg-expense'],
        'dark'    => ['soft' => 'bg-gray-100 text-dark',        'solid' => 'bg-dark'],
    ];
    $wallets = $wallets->values()->map(function ($wallet, $index) use ($tones): array {
        $colors = ['income', 'brand', 'warn', 'expense', 'dark'];

        return [
            'id' => $wallet->id_dompet,
            'name' => $wallet->nama_dompet,
            'type' => match ($wallet->jenis) {
                'physical' => 'Physical wallet',
                'bank' => 'Bank account',
                default => 'Digital wallet',
            },
            'description' => $wallet->deskripsi,
            'balance' => (float) $wallet->saldo,
            'icon' => match ($wallet->jenis) {
                'physical' => 'banknote',
                'bank' => 'landmark',
                default => 'smartphone',
            },
            'color' => $wallet->warna ?: $colors[$index % count($colors)],
        ];
    })->all();
    $categories = collect($summary['pengeluaran_per_kategori'])
        ->take(2)
        ->map(fn ($category): array => [
            $category['nama_kategori'],
            'expense',
            $formatMoney($category['total']),
        ])
        ->prepend([
            'Income this month',
            'income',
            $formatMoney($summary['total_pemasukan_bulan_ini']),
        ])
        ->all();
    $total = $summary['total_saldo'];
    $totalSaved = collect($summary['target_tabungan'])
        ->sum(fn ($target) => min((float) $target['jumlah_terkumpul'], (float) $target['nominal_target']));
    $largest = collect($wallets)->sortByDesc('balance')->first();
@endphp

<div x-data="{ 
    open: false, 
    mode: 'edit',
    csrfToken: @js(csrf_token()),
    editing: {
        id: null,
        name: '',
        type: 'Digital wallet',
        balance: '',
        color: 'brand'
    },
    async saveWallet() {
        const data = new URLSearchParams({
            _token: this.csrfToken,
            nama_dompet: this.editing.name.trim(),
            deskripsi: this.editing.description ?? '',
            jenis: this.editing.type === 'Physical wallet' ? 'physical' : (this.editing.type === 'Bank account' ? 'bank' : 'digital'),
            warna: this.editing.color,
        });

        if (this.mode === 'add') data.set('saldo_awal', String(Number(String(this.editing.balance).replace(/[^0-9.-]/g, '')) || 0));
        else data.set('saldo', String(Number(String(this.editing.balance).replace(/[^0-9.-]/g, '')) || 0));

        const response = await fetch(this.mode === 'add' ? @js(route('dompet.store')) : @js(route('dompet.manage', ['dompet' => '__ID__'])).replace('__ID__', this.editing.id), {
            method: this.mode === 'add' ? 'POST' : 'PATCH',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: data,
        });

        if (response.ok) {
            window.location.reload();
            return;
        }

        const result = await response.json();
        alert(Object.values(result.errors ?? {}).flat()[0] ?? 'Wallet could not be saved.');
    },

    async deleteWallet() {
        if (!window.confirm('Delete this wallet? Its balance and savings targets will be removed.')) return;

        const data = new URLSearchParams({ _token: this.csrfToken, _method: 'DELETE' });
        const response = await fetch(@js(route('dompet.destroy', ['dompet' => '__ID__'])).replace('__ID__', this.editing.id), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: data,
        });

        if (response.ok) {
            window.location.reload();
            return;
        }

        const result = await response.json().catch(() => ({}));

        if (response.status === 422 && result.transfer_count && window.confirm(`Wallet ini punya ${result.transfer_count} riwayat transfer. Hapus wallet beserta riwayat transfernya?`)) {
            const forceData = new URLSearchParams({ _token: this.csrfToken, _method: 'DELETE', force: '1' });
            const forceResponse = await fetch(@js(route('dompet.destroy', ['dompet' => '__ID__'])).replace('__ID__', this.editing.id), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: forceData,
            });

            if (forceResponse.ok) {
                window.location.reload();
                return;
            }

            const forceResult = await forceResponse.json().catch(() => ({}));
            alert(forceResult.message ?? 'Wallet could not be deleted.');
            return;
        }

        alert(result.message ?? 'Wallet could not be deleted.');
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
                id: null,
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
            <p class="mt-6 text-4xl font-bold">{{ $formatMoney($summary['total_saldo']) }}</p>
            <div class="mt-3 flex gap-4 text-xs">
                <span class="flex items-center gap-1 text-income"><x-icon name="arrow-up" size="h-3 w-3" /> {{ $formatMoney($summary['total_pemasukan_bulan_ini']) }} this month</span>
                <span class="flex items-center gap-1 text-expense"><x-icon name="arrow-down" size="h-3 w-3" /> {{ $formatMoney($summary['total_pengeluaran_bulan_ini']) }} this month</span>
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
                <x-badge color="brand">{{ count($categories) }} active Category</x-badge>
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
            <p class="mt-4 text-2xl font-bold">{{ $formatMoney($totalSaved) }}</p>
            <p class="mt-2 text-xs text-income">{{ count($summary['target_tabungan']) }} savings targets tracked</p>
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
                    @php($side = str_starts_with($w['color'], '#') ? 'custom' : 'preset')
                    <span class="flex h-11 w-11 items-center justify-center rounded-lg {{ $side === 'preset' ? $tones[$w['color']]['soft'] : '' }}"
                          @if ($side === 'custom') style="background-color: {{ $w['color'] }}1a; color: {{ $w['color'] }}" @endif>
                        <x-icon :name="$w['icon']" />
                    </span>
                    <button type="button"
                        x-on:click="
                            mode = 'edit';
                            editing = @js($w);
                            editing.balance = Number(editing.balance).toFixed(2);
                            open = true;"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-line hover:bg-page">
                    <x-icon name="more" size="h-4 w-4" />
                </button>
                </div>
                <p class="mt-4 text-lg font-semibold">{{ $w['name'] }}</p>
                <p class="text-sm text-muted">{{ $w['type'] }}</p>
                <p class="mt-3 text-[11px] uppercase tracking-wide text-muted">Available balance</p>
                <p class="text-2xl font-bold">{{ $formatMoney($w['balance']) }}</p>
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
                <span class="h-2.5 basis-0 rounded-full {{ str_starts_with($w['color'], '#') ? '' : $tones[$w['color']]['solid'] }}"
                      style="flex-grow: {{ $w['balance'] }}; @if (str_starts_with($w['color'], '#')) background-color: {{ $w['color'] }}; @endif"></span>
            @endforeach
        </div>
        <p class="mt-4 text-sm text-muted">
            @if ($largest && $total > 0)
                {{ $largest['name'] }} currently holds the largest share of your available balance at {{ round($largest['balance'] / $total * 100, 1) }}%.
            @else
                No wallet balance available.
            @endif
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

                <x-input label="Current balance" name="wallet_balance" inputmode="decimal" type="text" placeholder="0.00" x-model="editing.balance" />

                <div>
                    <label class="mb-2 block text-sm font-medium">Wallet color</label>
                    <div class="flex items-center gap-3">
                        @foreach (['brand', 'income', 'warn', 'expense', 'dark'] as $c)
                            <button type="button" x-on:click="editing.color = '{{ $c }}'"
                                    class="flex h-8 w-8 items-center justify-center rounded-full text-white {{ $tones[$c]['solid'] }}">
                                <x-icon name="check" size="h-4 w-4" x-show="editing.color === '{{ $c }}'" />
                            </button>
                        @endforeach

                        <div class="flex items-center gap-1.5">
                            <input type="color" x-model="editing.color" class="h-8 w-8 cursor-pointer rounded-full border border-line p-0" title="Custom color" />
                            <span x-cloak x-show="editing.color.startsWith('#')" class="text-xs text-muted" x-text="editing.color"></span>
                        </div>
                    </div>
                    <p class="mt-1.5 text-xs text-muted">Pick a preset, or click the color swatch for a custom color.</p>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between gap-3">
                <x-button variant="danger" x-show="mode === 'edit'" x-on:click="deleteWallet()">
                    Delete
                </x-button>

                <div class="flex gap-3">
                    <x-button variant="outline" x-on:click="open = false">
                        Cancel
                    </x-button>

                    <x-button x-on:click="saveWallet()">
                        <span x-text="mode === 'add' ? 'Add Wallet' : 'Save changes'"></span>
                    </x-button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection