@extends('layouts.main')

@section('title', 'My Wallets')

@section('content')
@php
    $currency = auth()->user()->mata_uang ?? 'IDR';

    $currencySymbols = [
        'IDR' => 'Rp',
        'USD' => '$',
        'EUR' => '€',
    ];

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
        'brand'   => [
            'soft' => 'bg-brand-soft text-brand',
            'solid' => 'bg-brand',
        ],
        'income'  => [
            'soft' => 'bg-income-soft text-income',
            'solid' => 'bg-income',
        ],
        'warn'    => [
            'soft' => 'bg-warn-soft text-warn',
            'solid' => 'bg-warn',
        ],
        'expense' => [
            'soft' => 'bg-expense-soft text-expense',
            'solid' => 'bg-expense',
        ],
        'dark'    => [
            'soft' => 'bg-gray-100 text-dark',
            'solid' => 'bg-dark',
        ],
    ];

    $wallets = $wallets->values()->map(function ($wallet, $index) use ($tones): array {
        $colors = [
            'income',
            'brand',
            'warn',
            'expense',
            'dark',
        ];

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
        ->sum(fn ($target) => min(
            (float) $target['jumlah_terkumpul'],
            (float) $target['nominal_target']
        ));

    $largest = collect($wallets)
        ->sortByDesc('balance')
        ->first();
@endphp

<div
    x-data="{
        open: false,
        mode: 'edit',

        csrfToken: @js(csrf_token()),

        editing: {
            id: null,
            name: '',
            type: 'Digital wallet',
            balance: '',
            saldo_awal: '',
            color: 'brand'
        },

        async saveWallet() {
            const data = new URLSearchParams({
                _token: this.csrfToken,
                nama_dompet: this.editing.name.trim(),
                deskripsi: this.editing.description ?? '',
                jenis: this.editing.type === 'Physical wallet'
                    ? 'physical'
                    : (this.editing.type === 'Bank account'
                        ? 'bank'
                        : 'digital'),
                warna: this.editing.color,
            });

            if (this.mode === 'add') {
                const rawBalance = String(this.editing.balance ?? '').trim();
                const rawSaldoAwal = String(this.editing.saldo_awal ?? '').trim();

                if (rawBalance !== '') {
                    data.set(
                        'saldo',
                        String(
                            Number(
                                rawBalance.replace(/[^0-9.-]/g, '')
                            ) || 0
                        )
                    );
                }

                if (rawSaldoAwal !== '') {
                    data.set(
                        'saldo_awal',
                        String(
                            Number(
                                rawSaldoAwal.replace(/[^0-9.-]/g, '')
                            ) || 0
                        )
                    );
                }
            } else {
                data.set(
                    'saldo',
                    String(
                        Number(
                            String(this.editing.balance)
                                .replace(/[^0-9.-]/g, '')
                        ) || 0
                    )
                );
            }

            const response = await fetch(
                this.mode === 'add'
                    ? @js(route('dompet.store'))
                    : @js(route('dompet.manage', ['dompet' => '__ID__']))
                        .replace('__ID__', this.editing.id),
                {
                    method: this.mode === 'add' ? 'POST' : 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: data,
                    redirect: 'manual'
                }
            );

            if (response.ok || response.type === 'opaqueredirect') {
                window.location.reload();
                return;
            }

            const result = await response.json();

            alert(
                Object.values(result.errors ?? {}).flat()[0]
                ?? 'Wallet could not be saved.'
            );
        },

        async deleteWallet() {
            if (
                !window.confirm(
                    'Delete this wallet? Its balance and savings targets will be removed.'
                )
            ) {
                return;
            }

            const data = new URLSearchParams({
                _token: this.csrfToken,
                _method: 'DELETE'
            });

            const response = await fetch(
                @js(route('dompet.destroy', ['dompet' => '__ID__']))
                    .replace('__ID__', this.editing.id),
                {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: data,
                    redirect: 'manual'
                }
            );

            if (response.ok || response.type === 'opaqueredirect') {
                window.location.reload();
                return;
            }

            const result = await response.json().catch(() => ({}));

            if (
                response.status === 422 &&
                result.transfer_count &&
                window.confirm(
                    `Wallet ini punya ${result.transfer_count} riwayat transfer. Hapus wallet beserta riwayat transfernya?`
                )
            ) {
                const forceData = new URLSearchParams({
                    _token: this.csrfToken,
                    _method: 'DELETE',
                    force: '1'
                });

                const forceResponse = await fetch(
                    @js(route('dompet.destroy', ['dompet' => '__ID__']))
                        .replace('__ID__', this.editing.id),
                    {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: forceData,
                        redirect: 'manual'
                    }
                );

                if (forceResponse.ok || forceResponse.type === 'opaqueredirect') {
                    window.location.reload();
                    return;
                }

                const forceResult = await forceResponse
                    .json()
                    .catch(() => ({}));

                alert(
                    forceResult.message
                    ?? 'Wallet could not be deleted.'
                );

                return;
            }

            alert(
                result.message
                ?? 'Wallet could not be deleted.'
            );
        }
    }"
>

    {{-- =========================================================
        PAGE HEADER
    ========================================================== --}}
    <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">

        <div class="min-w-0">
            <h1 class="text-3xl font-bold tracking-tight">
                My Wallets
            </h1>

            <p class="mt-3 max-w-[520px] text-sm leading-6 text-muted">
                Track balances across all the places you keep money.
            </p>
        </div>

        <x-button
            class="w-full shrink-0 sm:w-auto"
            x-on:click="
                mode = 'add';

                editing = {
                    id: null,
                    name: '',
                    type: 'Digital wallet',
                    balance: '',
                    saldo_awal: '',
                    color: 'brand'
                };

                open = true;
            "
        >
            <x-icon name="plus" size="h-4 w-4" />
            Add Wallet
        </x-button>

    </div>


    {{-- =========================================================
        SUMMARY CARDS
    ========================================================== --}}
    <div class="mt-10 grid grid-cols-1 gap-6 lg:grid-cols-[1.5fr_1.5fr_1fr]">

        {{-- =========================
            TOTAL BALANCE
        ========================== --}}
        <x-card class="p-6 sm:p-7">

            <div class="flex items-start gap-4">

                <span
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-soft"
                >
                    <x-icon name="wallet" />
                </span>

                <div class="min-w-0 flex-1">

                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">

                        <div class="min-w-0">

                            <p class="text-sm font-medium text-muted">
                                Total balance
                            </p>

                            <p class="mt-1 text-xs leading-5 text-muted">
                                Updated a few seconds ago
                            </p>

                        </div>

                        <x-badge
                            color="brand"
                            class="shrink-0"
                        >
                            {{ count($wallets) }} active wallets
                        </x-badge>

                    </div>

                </div>

            </div>


            {{-- Total saldo --}}
            <div class="mt-8 min-w-0 overflow-hidden">

                <p
                    class="truncate text-[clamp(1.75rem,7vw,2.5rem)] font-bold leading-none tracking-tight"
                    title="{{ $formatMoney($summary['total_saldo']) }}"
                >
                    {{ $formatMoney($summary['total_saldo']) }}
                </p>

            </div>


            {{-- Income & Expense --}}
            <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-5">

                <div class="flex min-w-0 items-center gap-2 text-xs text-income">

                    <x-icon
                        name="arrow-up"
                        size="h-3.5 w-3.5 shrink-0"
                    />

                    <span class="min-w-0 break-words">
                        {{ $formatMoney($summary['total_pemasukan_bulan_ini']) }}

                        <span class="text-muted">
                            this month
                        </span>
                    </span>

                </div>


                <div class="flex min-w-0 items-center gap-2 text-xs text-expense">

                    <x-icon
                        name="arrow-down"
                        size="h-3.5 w-3.5 shrink-0"
                    />

                    <span class="min-w-0 break-words">
                        {{ $formatMoney($summary['total_pengeluaran_bulan_ini']) }}

                        <span class="text-muted">
                            this month
                        </span>
                    </span>

                </div>

            </div>

        </x-card>


        {{-- =========================
            CATEGORY
        ========================== --}}
        <x-card class="p-6 sm:p-7">

            <div class="flex items-start gap-4">

                <span
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-soft"
                >
                    <x-icon name="wallet" />
                </span>

                <div class="min-w-0 flex-1">

                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">

                        <div class="min-w-0">

                            <p class="text-sm font-medium text-muted">
                                Category
                            </p>

                            <p class="mt-1 text-xs leading-5 text-muted">
                                Updated a few seconds ago
                            </p>

                        </div>

                        <x-badge
                            color="brand"
                            class="shrink-0"
                        >
                            {{ count($categories) }} active Category
                        </x-badge>

                    </div>

                </div>

            </div>


            <ul class="mt-7 space-y-5 text-sm">

                @foreach ($categories as [$name, $type, $amount])

                    <li class="flex items-start justify-between gap-5">

                        <span class="min-w-0 font-medium leading-5">
                            {{ $name }}
                        </span>

                        <span
                            class="
                                flex
                                min-w-0
                                max-w-[58%]
                                items-start
                                gap-2
                                text-right
                                {{ $type === 'income'
                                    ? 'text-income'
                                    : 'text-expense' }}
                            "
                        >

                            <x-icon
                                :name="$type === 'income'
                                    ? 'arrow-up'
                                    : 'arrow-down'"
                                size="h-3.5 w-3.5 shrink-0 mt-0.5"
                            />

                            <span class="break-words leading-5">

                                {{ $amount }}

                                <span class="text-muted">
                                    this month
                                </span>

                            </span>

                        </span>

                    </li>

                @endforeach

            </ul>

        </x-card>


        {{-- =========================
            TOTAL SAVED
        ========================== --}}
        <x-card class="p-6 sm:p-7">

            <div class="flex items-start justify-between gap-4">

                <div>

                    <p class="text-sm font-medium text-muted">
                        Total saved
                    </p>

                    <p class="mt-1 text-xs leading-5 text-muted">
                        Your current savings
                    </p>

                </div>

                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-income-soft text-income"
                >
                    <x-icon
                        name="dollar"
                        size="h-5 w-5"
                    />
                </span>

            </div>


            <p class="mt-8 whitespace-nowrap text-2xl font-bold tracking-tight">
                {{ $formatMoney($totalSaved) }}
            </p>

            <p class="mt-3 text-xs text-income">{{ count($summary['target_tabungan']) }} savings targets tracked</p>

        </x-card>

    </div>


    {{-- =========================================================
        YOUR WALLETS HEADER
    ========================================================== --}}
    <div class="mt-10 flex items-center justify-between gap-4">

        <h2 class="font-semibold">
            Your wallets
        </h2>

        <span class="text-sm text-muted">
            {{ count($wallets) }} wallets
        </span>

    </div>


    {{-- =========================================================
        WALLET LIST
    ========================================================== --}}
    <div class="mt-5 grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">

        @foreach ($wallets as $w)

            <x-card class="p-6 sm:p-7">

                {{-- Wallet header --}}
                <div class="flex items-start justify-between gap-4">

                    @php
                        $side = str_starts_with($w['color'], '#')
                            ? 'custom'
                            : 'preset';
                    @endphp

                    <span
                        class="
                            flex
                            h-11
                            w-11
                            shrink-0
                            items-center
                            justify-center
                            rounded-xl
                            {{ $side === 'preset'
                                ? $tones[$w['color']]['soft']
                                : '' }}
                        "
                        @if ($side === 'custom')
                            style="
                                background-color: {{ $w['color'] }}1a;
                                color: {{ $w['color'] }}
                            "
                        @endif
                    >
                        <x-icon :name="$w['icon']" />
                    </span>


                    <button
                        type="button"
                        x-on:click="
                            mode = 'edit';
                            editing = @js($w);
                            editing.balance = Number(editing.balance).toFixed(2);
                            open = true;
                        "
                        class="
                            flex
                            h-9
                            w-9
                            shrink-0
                            items-center
                            justify-center
                            rounded-lg
                            border
                            border-line
                            hover:bg-page
                        "
                    >
                        <x-icon
                            name="more"
                            size="h-4 w-4"
                        />
                    </button>

                </div>


                {{-- Wallet information --}}
                <p class="mt-5 text-lg font-semibold">
                    {{ $w['name'] }}
                </p>

                <p class="mt-1 text-sm text-muted">
                    {{ $w['type'] }}
                </p>


                <p class="mt-5 text-[11px] uppercase tracking-wide text-muted">
                    Available balance
                </p>

                <p class="mt-1 whitespace-nowrap text-2xl font-bold tracking-tight">
                    {{ $formatMoney($w['balance']) }}
                </p>


                <a
                    href="/wallet/{{ $w['id'] }}"
                    class="
                        mt-5
                        inline-flex
                        items-center
                        gap-1
                        text-xs
                        font-medium
                        text-brand
                        hover:underline
                    "
                >
                    <x-icon
                        name="history"
                        size="h-3 w-3"
                    />

                    View history
                </a>

            </x-card>

        @endforeach

    </div>


    {{-- =========================================================
        BALANCE DISTRIBUTION
    ========================================================== --}}
    <x-card class="mt-8 p-6 sm:p-7">

        <div class="flex items-center justify-between gap-4">

            <h2 class="font-semibold">
                Balance distribution
            </h2>

            <span class="text-sm text-muted">
                All wallets
            </span>

        </div>


        <div class="mt-5 flex gap-1.5">

            @foreach ($wallets as $w)

                <span
                    class="
                        h-2.5
                        basis-0
                        rounded-full
                        {{ str_starts_with($w['color'], '#')
                            ? ''
                            : $tones[$w['color']]['solid'] }}
                    "
                    style="
                        flex-grow: {{ $w['balance'] }};

                        @if (str_starts_with($w['color'], '#'))
                            background-color: {{ $w['color'] }};
                        @endif
                    "
                ></span>

            @endforeach

        </div>


        <p class="mt-5 text-sm leading-6 text-muted">

            @if ($largest && $total > 0)

                {{ $largest['name'] }}
                currently holds the largest share of your available balance
                at
                {{ round($largest['balance'] / $total * 100, 1) }}%.

            @else

                No wallet balance available.

            @endif

        </p>

    </x-card>


    {{-- =========================================================
        MODAL EDIT / ADD WALLET
    ========================================================== --}}
    <div
        x-cloak
        x-show="open"
        x-transition.opacity
        x-on:keydown.escape.window="open = false"
        x-on:click.self="open = false"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >

        <div
            class="w-full max-w-sm max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl border border-line bg-white p-6 shadow-xl"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >

            {{-- Modal header --}}
            <div class="flex items-start justify-between gap-4">

                <div class="min-w-0">

                    <h3
                        class="text-2xl font-bold"
                        x-text="
                            mode === 'add'
                                ? 'Add Wallet'
                                : 'Edit Wallet'
                        "
                    ></h3>

                    <p
                        class="mt-1 text-xs leading-5 text-muted"
                        x-text="
                            mode === 'add'
                                ? 'Create a new wallet to track your money.'
                                : 'Update the details for this wallet.'
                        "
                    ></p>

                </div>


                <button
                    type="button"
                    x-on:click="open = false"
                    class="
                        flex
                        h-9
                        w-9
                        shrink-0
                        items-center
                        justify-center
                        rounded-lg
                        border
                        border-line
                        hover:bg-page
                    "
                >
                    <x-icon
                        name="x"
                        size="h-4 w-4"
                    />
                </button>

            </div>


            {{-- Modal form --}}
            <div class="mt-6 space-y-5">

                <x-input
                    label="Wallet name"
                    name="wallet_name"
                    x-model="editing.name"
                />


                <div>

                    <label class="mb-1.5 block text-sm font-medium">
                        Wallet type
                    </label>

                    <select
                        x-model="editing.type"
                        class="
                            w-full
                            rounded-lg
                            border
                            border-line
                            bg-white
                            px-3
                            py-2.5
                            text-sm
                            focus:border-brand
                            focus:outline-none
                            focus:ring-2
                            focus:ring-brand/20
                        "
                    >
                        <option>
                            Physical wallet
                        </option>

                        <option>
                            Digital wallet
                        </option>

                        <option>
                            Bank account
                        </option>
                    </select>

                </div>


                <x-input
                    label="Current balance"
                    name="wallet_balance"
                    inputmode="decimal"
                    type="text"
                    placeholder="0.00"
                    x-model="editing.balance"
                    x-on:input="
                        if (editing.balance) {
                            editing.saldo_awal = ''
                        }
                    "
                />


                <div x-show="mode === 'add'">

                    <label class="mb-1.5 block text-sm font-medium">
                        Saldo awal (Opsional)
                    </label>

                    <input
                        name="saldo_awal"
                        inputmode="decimal"
                        type="text"
                        placeholder="0.00"
                        x-model="editing.saldo_awal"
                        x-on:input="
                            if (editing.saldo_awal) {
                                editing.balance = ''
                            }
                        "
                        class="
                            w-full
                            rounded-lg
                            border
                            border-line
                            bg-white
                            px-3
                            py-2.5
                            text-sm
                            text-ink
                            placeholder:text-muted
                            focus:border-brand
                            focus:outline-none
                            focus:ring-2
                            focus:ring-brand/20
                        "
                    />

                    <p class="mt-1.5 text-xs leading-5 text-muted">
                        Only fill one of the options.
                        Initial balance will be recorded as an income transaction.
                    </p>

                </div>


                {{-- Wallet color --}}
                <div>

                    <label class="mb-2 block text-sm font-medium">
                        Wallet color
                    </label>

                    <div class="flex flex-wrap items-center gap-3">

                        @foreach (['brand', 'income', 'warn', 'expense', 'dark'] as $c)

                            <button
                                type="button"
                                x-on:click="editing.color = '{{ $c }}'"
                                class="
                                    flex
                                    h-8
                                    w-8
                                    items-center
                                    justify-center
                                    rounded-full
                                    text-white
                                    {{ $tones[$c]['solid'] }}
                                "
                            >
                                <x-icon
                                    name="check"
                                    size="h-4 w-4"
                                    x-show="editing.color === '{{ $c }}'"
                                />
                            </button>

                        @endforeach


                        <div class="flex items-center gap-1.5">

                            <input
                                type="color"
                                x-model="editing.color"
                                class="
                                    h-8
                                    w-8
                                    cursor-pointer
                                    rounded-full
                                    border
                                    border-line
                                    p-0
                                "
                                title="Custom color"
                            />

                            <span
                                x-cloak
                                x-show="editing.color.startsWith('#')"
                                class="text-xs text-muted"
                                x-text="editing.color"
                            ></span>

                        </div>

                    </div>

                    <p class="mt-1.5 text-xs leading-5 text-muted">
                        Pick a preset, or click the color swatch for a custom color.
                    </p>

                </div>

            </div>


            {{-- Modal actions --}}
            <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">

                <x-button
                    variant="danger"
                    x-show="mode === 'edit'"
                    x-on:click="deleteWallet()"
                    class="w-full sm:w-auto"
                >
                    Delete
                </x-button>


                <div class="flex w-full gap-3 sm:w-auto">

                    <x-button
                        variant="outline"
                        x-on:click="open = false"
                        class="flex-1 sm:flex-none"
                    >
                        Cancel
                    </x-button>

                    <x-button
                        x-on:click="saveWallet()"
                        class="flex-1 sm:flex-none"
                    >
                        <span
                            x-text="
                                mode === 'add'
                                    ? 'Add Wallet'
                                    : 'Save changes'
                            "
                        ></span>
                    </x-button>

                </div>

            </div>

        </div>

    </div>

</div>
@endsection