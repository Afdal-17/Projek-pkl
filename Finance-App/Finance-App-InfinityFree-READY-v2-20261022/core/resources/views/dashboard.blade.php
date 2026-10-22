@extends('layouts.main')

@section('title', 'My Wallets')

@section('content')
@php
    $currency = auth()->user()->mata_uang ?? 'IDR';

    $formatMoney = fn ($amount) => finance_money($amount);

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
            'balance' => (string) $wallet->saldo,
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
        ->sort(fn ($a, $b) => bccomp((string) $b['balance'], (string) $a['balance'], 2))
        ->first();

    // Skalasi Ukuran Font Dinamis berbasis panjang karakter nominal (Solusi A No. 2)
    $getMainBalanceClass = function (string $formatted): string {
        $len = mb_strlen(trim($formatted));
        if ($len <= 11) {
            return 'text-3xl sm:text-4xl lg:text-[2.5rem]';
        }
        if ($len <= 15) {
            return 'text-2xl sm:text-3xl lg:text-4xl';
        }
        if ($len <= 18) {
            return 'text-xl sm:text-2xl lg:text-3xl';
        }
        return 'text-lg sm:text-xl lg:text-2xl';
    };

    $getCardBalanceClass = function (string $formatted): string {
        $len = mb_strlen(trim($formatted));
        if ($len <= 11) {
            return 'text-2xl';
        }
        if ($len <= 15) {
            return 'text-xl sm:text-2xl';
        }
        if ($len <= 18) {
            return 'text-lg sm:text-xl';
        }
        return 'text-base sm:text-lg';
    };

    $getListAmountClass = function (string $formatted): string {
        $len = mb_strlen(trim($formatted));
        if ($len <= 13) {
            return 'text-sm font-semibold';
        }
        return 'text-xs sm:text-sm font-semibold';
    };
@endphp


<div x-data="dashboardApp">

<script>
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('dashboardApp', () => ({
            open: false,
            mode: 'edit',
            csrfToken: @js(csrf_token()),
            isSubmitting: false,
            isDeleting: false,

            confirmDialog: {
                open: false,
                title: '',
                message: '',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                variant: 'danger',
                resolve: null,
            },

            alertDialog: {
                open: false,
                title: '',
                message: '',
                buttonText: 'Got it',
                resolve: null,
            },

            editing: {
                id: null,
                name: '',
                description: '',
                type: 'Digital wallet',
                balance: '',
                balanceDisplay: '',
                saldo_awal: '',
                saldoAwalDisplay: '',
                color: 'brand'
            },

            askConfirm({ title, message, confirmText = 'Delete', cancelText = 'Cancel', variant = 'danger' }) {
                if (this.confirmDialog.open) {
                    return Promise.resolve(false);
                }

                if (typeof this.confirmDialog.resolve === 'function') {
                    const prevResolve = this.confirmDialog.resolve;
                    this.confirmDialog.resolve = null;
                    prevResolve(false);
                }

                return new Promise((resolve) => {
                    this.confirmDialog = {
                        open: true,
                        title,
                        message,
                        confirmText,
                        cancelText,
                        variant,
                        resolve,
                    };
                });
            },

            closeConfirm(choice = false) {
                if (this.isDeleting && choice !== true) return;

                const resolver = this.confirmDialog.resolve;
                this.confirmDialog.resolve = null;

                if (choice) {
                    this.isDeleting = true;
                } else {
                    this.confirmDialog.open = false;
                    this.isDeleting = false;
                }

                if (typeof resolver === 'function') {
                    resolver(choice);
                }
            },

            showAlert({ title = 'Notice', message, buttonText = 'Got it' }) {
                if (typeof this.alertDialog.resolve === 'function') {
                    const prevResolve = this.alertDialog.resolve;
                    this.alertDialog.resolve = null;
                    prevResolve();
                }

                return new Promise((resolve) => {
                    this.alertDialog = {
                        open: true,
                        title,
                        message,
                        buttonText,
                        resolve,
                    };
                });
            },

            closeAlert() {
                const resolver = this.alertDialog.resolve;
                this.alertDialog.resolve = null;
                this.alertDialog.open = false;
                if (typeof resolver === 'function') {
                    resolver();
                }
            },

            formatBalance(value) {
                const result = window.financeMoney.formatTyped(value);
                this.editing.balance = result.display === '' ? '' : result.value;
                this.editing.balanceDisplay = result.display;
                if (result.display !== '') {
                    this.editing.saldo_awal = '';
                    this.editing.saldoAwalDisplay = '';
                }
            },

            formatSaldoAwal(value) {
                const result = window.financeMoney.formatTyped(value);
                this.editing.saldo_awal = result.display === '' ? '' : result.value;
                this.editing.saldoAwalDisplay = result.display;
                if (result.display !== '') {
                    this.editing.balance = '';
                    this.editing.balanceDisplay = '';
                }
            },

            async saveWallet() {
                if (this.isSubmitting || this.isDeleting || this.confirmDialog.open) return;

                const data = new URLSearchParams({
                    _token: this.csrfToken,
                    nama_dompet: this.editing.name.trim(),
                    deskripsi: this.editing.description ?? '',
                    jenis: (this.editing.type === 'Physical wallet' || this.editing.type === @js(__('dashboard.physical_wallet')))
                        ? 'physical'
                        : ((this.editing.type === 'Bank account' || this.editing.type === @js(__('dashboard.bank_account')))
                            ? 'bank'
                            : 'digital'),
                    warna: this.editing.color,
                });

                if (this.mode === 'add') {
                    const balanceShown = String(this.editing.balanceDisplay ?? '').trim();
                    const saldoAwalShown = String(this.editing.saldoAwalDisplay ?? '').trim();

                    if (balanceShown !== '') {
                        data.set('saldo', window.financeMoney.parseFormatted(balanceShown));
                    }
                    if (saldoAwalShown !== '') {
                        data.set('saldo_awal', window.financeMoney.parseFormatted(saldoAwalShown));
                    }
                } else {
                    data.set('saldo', window.financeMoney.parseFormatted(this.editing.balanceDisplay ?? this.editing.balance));
                }

                this.isSubmitting = true;

                try {
                    const response = await fetch(
                        this.mode === 'add'
                            ? @js(route('dompet.store'))
                            : @js(route('dompet.manage', ['dompet' => '__ID__'])).replace('__ID__', this.editing.id),
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

                    const result = await response.json().catch(() => ({}));
                    const errorMessage = Object.values(result.errors ?? {}).flat()[0]
                        ?? result.message
                        ?? 'Wallet could not be saved.';

                    await this.showAlert({
                        title: 'Unable to Save Wallet',
                        message: errorMessage,
                        buttonText: 'Got it',
                    });
                } catch (e) {
                    await this.showAlert({
                        title: 'Network Error',
                        message: 'A network error occurred while saving the wallet. Please try again.',
                        buttonText: 'Dismiss',
                    });
                } finally {
                    this.isSubmitting = false;
                }
            },

            async deleteWallet() {
                if (this.isDeleting || this.confirmDialog.open || this.isSubmitting) return;

                let confirmed = false;
                try {
                    confirmed = await this.askConfirm({
                        title: 'Delete Wallet',
                        message: @js(__('dashboard.delete_confirm')) || 'Are you sure you want to delete this wallet? This action cannot be undone.',
                        confirmText: 'Delete',
                        cancelText: 'Cancel',
                        variant: 'danger',
                    });
                } catch (err) {
                    confirmed = false;
                }

                if (!confirmed) {
                    this.confirmDialog.open = false;
                    this.isDeleting = false;
                    this.confirmDialog.resolve = null;
                    return;
                }

                this.isDeleting = true;

                try {
                    const data = new URLSearchParams({
                        _token: this.csrfToken,
                        _method: 'DELETE'
                    });

                    const response = await fetch(
                        @js(route('dompet.destroy', ['dompet' => '__ID__'])).replace('__ID__', this.editing.id),
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

                    if (response.status === 422 && result.transfer_count) {
                        this.isDeleting = false;
                        this.confirmDialog.open = false;
                        this.confirmDialog.resolve = null;
                        const count = result.transfer_count;

                        let forceConfirmed = false;
                        try {
                            forceConfirmed = await this.askConfirm({
                                title: 'Delete Wallet with Transfer History',
                                message: `This wallet has ${count} transfer record${count > 1 ? 's' : ''}. Deleting it will permanently remove the wallet and all associated transfer records. Are you sure you want to proceed?`,
                                confirmText: 'Delete Anyway',
                                cancelText: 'Cancel',
                                variant: 'danger',
                            });
                        } catch (err) {
                            forceConfirmed = false;
                        }

                        if (!forceConfirmed) {
                            this.confirmDialog.open = false;
                            this.isDeleting = false;
                            this.confirmDialog.resolve = null;
                            return;
                        }

                        this.isDeleting = true;

                        const forceData = new URLSearchParams({
                            _token: this.csrfToken,
                            _method: 'DELETE',
                            force: '1'
                        });

                        const forceResponse = await fetch(
                            @js(route('dompet.destroy', ['dompet' => '__ID__'])).replace('__ID__', this.editing.id),
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

                        const forceResult = await forceResponse.json().catch(() => ({}));
                        this.isDeleting = false;
                        this.confirmDialog.open = false;
                        this.confirmDialog.resolve = null;
                        await this.showAlert({
                            title: 'Unable to Delete Wallet',
                            message: forceResult.message ?? 'Wallet could not be deleted.',
                            buttonText: 'Dismiss',
                        });
                        return;
                    }

                    this.isDeleting = false;
                    this.confirmDialog.open = false;
                    this.confirmDialog.resolve = null;
                    await this.showAlert({
                        title: 'Unable to Delete Wallet',
                        message: result.message ?? 'Wallet could not be deleted.',
                        buttonText: 'Dismiss',
                    });
                } catch (e) {
                    this.isDeleting = false;
                    this.confirmDialog.open = false;
                    this.confirmDialog.resolve = null;
                    await this.showAlert({
                        title: 'Network Error',
                        message: 'A network error occurred while deleting the wallet. Please try again.',
                        buttonText: 'Dismiss',
                    });
                } finally {
                    this.isDeleting = false;
                    this.confirmDialog.open = false;
                    this.confirmDialog.resolve = null;
                }
            }
        }));
    });
</script>

    {{-- =========================================================
        PAGE HEADER
    ========================================================== --}}
    <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">

        <div class="min-w-0">
            <h1 class="text-3xl font-bold tracking-tight">
                {{ __('dashboard.title') }}
            </h1>

            <p class="mt-3 max-w-[520px] text-sm leading-6 text-muted">
                {{ __('dashboard.subtitle') }}
            </p>
        </div>

        <x-button
            class="w-full shrink-0 sm:w-auto"
            x-on:click="
                mode = 'add';
                editing = {
                    id: null,
                    name: '',
                    description: '',
                    type: 'Digital wallet',
                    balance: '',
                    balanceDisplay: '',
                    saldo_awal: '',
                    saldoAwalDisplay: '',
                    color: 'brand'
                };
                open = true;
            "
        >
            <x-icon name="plus" size="h-4 w-4" />
            {{ __('dashboard.add_wallet') }}
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
            @php
                $formattedTotalSaldo = $formatMoney($summary['total_saldo']);
            @endphp
            <div class="mt-8 min-w-0 overflow-hidden">

                <p
                    class="truncate {{ $getMainBalanceClass($formattedTotalSaldo) }} font-bold leading-none tracking-tight tabular-nums"
                    title="{{ $formattedTotalSaldo }}"
                >
                    {{ $formattedTotalSaldo }}
                </p>

            </div>


            {{-- Income & Expense --}}
            <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-5">

                <div class="flex min-w-0 items-center gap-2 text-xs text-income">

                    <x-icon
                        name="arrow-up"
                        size="h-3.5 w-3.5 shrink-0"
                    />

                    <span class="min-w-0 truncate tabular-nums">
                        <span class="font-semibold whitespace-nowrap">{{ $formatMoney($summary['total_pemasukan_bulan_ini']) }}</span>
                        <span class="text-muted ml-1 whitespace-nowrap">this month</span>
                    </span>

                </div>


                <div class="flex min-w-0 items-center gap-2 text-xs text-expense">

                    <x-icon
                        name="arrow-down"
                        size="h-3.5 w-3.5 shrink-0"
                    />

                    <span class="min-w-0 truncate tabular-nums">
                        <span class="font-semibold whitespace-nowrap">{{ $formatMoney($summary['total_pengeluaran_bulan_ini']) }}</span>
                        <span class="text-muted ml-1 whitespace-nowrap">this month</span>
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

                    <li class="flex items-start justify-between gap-4">

                        <span class="min-w-0 font-medium leading-5 truncate" title="{{ $name }}">
                            {{ $name }}
                        </span>

                        <div class="flex flex-col items-end shrink-0 text-right">
                            <span
                                class="
                                    flex
                                    items-center
                                    gap-1
                                    tabular-nums
                                    whitespace-nowrap
                                    {{ $type === 'income'
                                        ? 'text-income'
                                        : 'text-expense' }}
                                    {{ $getListAmountClass($amount) }}
                                "
                            >
                                <x-icon
                                    :name="$type === 'income'
                                        ? 'arrow-up'
                                        : 'arrow-down'"
                                    size="h-3.5 w-3.5 shrink-0"
                                />
                                <span>{{ $amount }}</span>
                            </span>
                            <span class="text-[11px] text-muted leading-tight whitespace-nowrap">
                                this month
                            </span>
                        </div>

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


            @php
                $formattedTotalSaved = $formatMoney($totalSaved);
            @endphp
            <p
                class="mt-8 truncate {{ $getCardBalanceClass($formattedTotalSaved) }} font-bold tracking-tight tabular-nums"
                title="{{ $formattedTotalSaved }}"
            >
                {{ $formattedTotalSaved }}
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

        @forelse ($wallets as $w)

            <x-card class="min-w-0 p-6 sm:p-7">

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
                                ? ($tones[$w['color']]['soft'] ?? 'bg-brand-soft text-brand')
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
                            editing.balance = window.financeMoney.fromBase(editing.balance);
                            editing.balanceDisplay = window.financeMoney.formatNumber(editing.balance, { minDecimals: 0, maxDecimals: 2 });
                            editing.saldo_awal = '';
                            editing.saldoAwalDisplay = '';
                            open = true;
                        "
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-line hover:bg-page"
                    >
                        <x-icon name="more" size="h-4 w-4" />
                    </button>
                </div>


                {{-- Wallet information --}}
                <p class="mt-5 truncate text-lg font-semibold" title="{{ $w['name'] }}">
                    {{ $w['name'] }}
                </p>

                <p class="mt-1 truncate text-sm text-muted">
                    {{ $w['type'] }}
                </p>


                <p class="mt-5 text-[11px] uppercase tracking-wide text-muted">
                    Available balance
                </p>

                @php
                    $formattedWalletBalance = $formatMoney($w['balance']);
                @endphp
                <p
                    class="mt-1 truncate {{ $getCardBalanceClass($formattedWalletBalance) }} font-bold tracking-tight tabular-nums"
                    title="{{ $formattedWalletBalance }}"
                >
                    {{ $formattedWalletBalance }}
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

        @empty

            <x-card class="col-span-full p-8 text-center">
                <p class="text-sm text-muted">No wallets found.</p>
            </x-card>

        @endforelse

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

        @php
            $walletCount = count($wallets);
            $distributionBalances = collect($wallets)
                ->map(fn ($wallet) => max(0, (float) $wallet['balance']));
            $totalDistributionBalance = $distributionBalances->sum();

            // Memastikan seluruh segmen warna wallet terlihat tanpa mendistorsi proporsi saldo
            $minShare = $walletCount > 0 ? min(2.5, 25 / $walletCount) : 0;
            $totalReserved = $walletCount * $minShare;
            $remainingPool = 100 - $totalReserved;
        @endphp

        <div class="mt-5 flex w-full min-w-0 gap-1.5 overflow-hidden rounded-full">

            @forelse ($wallets as $index => $w)
                @php
                    $balance = $distributionBalances[$index] ?? 0;
                    $isCustom = str_starts_with($w['color'], '#');
                    $solidClass = ! $isCustom ? ($tones[$w['color']]['solid'] ?? 'bg-brand') : '';

                    if ($totalDistributionBalance > 0) {
                        $rawRatio = $balance / $totalDistributionBalance;
                        $share = $minShare + ($rawRatio * $remainingPool);
                    } else {
                        $share = 100 / max($walletCount, 1);
                    }
                @endphp

                <span
                    class="h-2.5 min-w-[8px] sm:min-w-[10px] rounded-full {{ $solidClass }}"
                    style="
                        flex-grow: {{ round($share, 3) }};
                        flex-basis: 0;
                        flex-shrink: 0;
                        @if ($isCustom)
                            background-color: {{ $w['color'] }};
                        @endif
                    "
                    title="{{ $w['name'] }}: {{ $formatMoney($w['balance']) }}"
                ></span>

            @empty

                <span class="h-2.5 w-full rounded-full bg-line/60"></span>

            @endforelse

        </div>


        <p class="mt-5 text-sm leading-6 text-muted">

            @if ($largest && bccomp((string) $total, '0', 2) > 0)
                {{ $largest['name'] }}
                currently holds the largest share of your available balance
                at
                {{ round((float) bcdiv(bcmul((string) $largest['balance'], '100', 4), (string) $total, 4), 1) }}%.

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
        x-on:keydown.escape.window="if (!confirmDialog.open && !alertDialog.open && !isSubmitting && !isDeleting) open = false"
        x-on:click.self="if (!confirmDialog.open && !alertDialog.open && !isSubmitting && !isDeleting) open = false"
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
                    x-on:click="if (!confirmDialog.open && !alertDialog.open && !isSubmitting && !isDeleting) open = false"
                    x-bind:disabled="confirmDialog.open || alertDialog.open || isSubmitting || isDeleting"
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
                        disabled:opacity-50 disabled:cursor-not-allowed
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
                    label="{{ __('dashboard.current_balance') }}"
                    name="wallet_balance"
                    inputmode="decimal"
                    type="text"
                    placeholder="0"
                    x-model="editing.balanceDisplay"
                    x-on:input="formatBalance($event.target.value)"
                />

                <div x-show="mode === 'add'">
                    <label class="mb-1.5 block text-sm font-medium">
                        {{ __('dashboard.initial_balance') }}
                    </label>

                    <input
                        name="saldo_awal"
                        inputmode="decimal"
                        type="text"
                        placeholder="0"
                        x-model="editing.saldoAwalDisplay"
                        x-on:input="formatSaldoAwal($event.target.value)"
                        class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-sm text-ink placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    />

                    <p class="mt-1.5 text-xs leading-5 text-muted">
                        {{ __('dashboard.initial_balance_note') }}
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
                    x-bind:disabled="isSubmitting || isDeleting || confirmDialog.open"
                >
                    <span x-show="!isDeleting">Delete</span>
                    <span x-show="isDeleting" class="inline-flex items-center gap-1.5" x-cloak>
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        Deleting...
                    </span>
                </x-button>


                <div class="flex w-full gap-3 sm:w-auto">

                    <x-button
                        variant="outline"
                        x-on:click="open = false"
                        class="flex-1 sm:flex-none"
                        x-bind:disabled="isSubmitting || isDeleting || confirmDialog.open"
                    >
                        Cancel
                    </x-button>

                    <x-button
                        x-on:click="saveWallet()"
                        class="flex-1 sm:flex-none"
                        x-bind:disabled="isSubmitting || isDeleting || confirmDialog.open"
                    >
                        <span
                            x-show="!isSubmitting"
                            x-text="
                                mode === 'add'
                                    ? 'Add Wallet'
                                    : 'Save changes'
                            "
                        ></span>
                        <span x-show="isSubmitting" class="inline-flex items-center gap-1.5" x-cloak>
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            Saving...
                        </span>
                    </x-button>

                </div>

            </div>

        </div>

    </div>

    {{-- =========================================================
        CUSTOM CONFIRMATION DIALOG (DOMPET)
    ========================================================== --}}
    <div
        x-cloak
        x-show="confirmDialog.open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:keydown.escape.window.stop="if (confirmDialog.open && !isDeleting) { closeConfirm(false); $event.stopPropagation(); }"
        x-on:click.self="if (!isDeleting) closeConfirm(false)"
        class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
    >
        <div
            class="w-full max-w-sm rounded-2xl border border-line bg-white p-6 shadow-2xl"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-expense-soft text-expense">
                    <x-icon name="trash" size="h-5 w-5" />
                </div>

                <button
                    type="button"
                    x-on:click="if (!isDeleting) closeConfirm(false)"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-line text-muted hover:bg-page hover:text-ink transition disabled:opacity-50 disabled:cursor-not-allowed"
                    x-bind:disabled="isDeleting"
                >
                    <x-icon name="x" size="h-4 w-4" />
                </button>
            </div>

            <div class="mt-4">
                <h4 class="text-lg font-bold text-ink" x-text="confirmDialog.title"></h4>
                <p class="mt-2 text-sm text-muted leading-relaxed" x-text="confirmDialog.message"></p>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                <x-button
                    variant="outline"
                    x-on:click="if (!isDeleting) closeConfirm(false)"
                    class="w-full justify-center sm:w-auto"
                    x-bind:disabled="isDeleting"
                >
                    <span x-text="confirmDialog.cancelText"></span>
                </x-button>

                <x-button
                    variant="danger"
                    x-on:click="if (!isDeleting) closeConfirm(true)"
                    class="w-full justify-center sm:w-auto"
                    x-bind:disabled="isDeleting"
                >
                    <span x-show="!isDeleting" x-text="confirmDialog.confirmText"></span>
                    <span x-show="isDeleting" class="inline-flex items-center gap-1.5" x-cloak>
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        Deleting...
                    </span>
                </x-button>
            </div>
        </div>
    </div>

    {{-- =========================================================
        CUSTOM ALERT / ERROR DIALOG (DOMPET)
    ========================================================== --}}
    <div
        x-cloak
        x-show="alertDialog.open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:keydown.escape.window.stop="if (alertDialog.open) { closeAlert(); $event.stopPropagation(); }"
        x-on:click.self="closeAlert()"
        class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
    >
        <div
            class="w-full max-w-sm rounded-2xl border border-line bg-white p-6 shadow-2xl"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-expense-soft text-expense">
                    <x-icon name="alert-circle" size="h-5 w-5" />
                </div>

                <button
                    type="button"
                    x-on:click="closeAlert()"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-line text-muted hover:bg-page hover:text-ink transition"
                >
                    <x-icon name="x" size="h-4 w-4" />
                </button>
            </div>

            <div class="mt-4">
                <h4 class="text-lg font-bold text-ink" x-text="alertDialog.title"></h4>
                <p class="mt-2 text-sm text-muted leading-relaxed" x-text="alertDialog.message"></p>
            </div>

            <div class="mt-6 flex justify-end">
                <x-button
                    variant="dark"
                    x-on:click="closeAlert()"
                    class="w-full justify-center sm:w-auto"
                >
                    <span x-text="alertDialog.buttonText"></span>
                </x-button>
            </div>
        </div>
    </div>

</div>
@endsection