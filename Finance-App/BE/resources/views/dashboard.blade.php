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
            'is_primary' => (bool) $wallet->terima_transfer,
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
        ->reduce(function (string $total, array $target): string {
            $saved = (string) $target['jumlah_terkumpul'];
            $goal = (string) $target['nominal_target'];
            $cappedSaved = bccomp($saved, $goal, 2) > 0 ? $goal : $saved;

            return bcadd($total, $cappedSaved, 2);
        }, '0.00');

    $largest = collect($wallets)
        ->sort(fn ($a, $b) => bccomp((string) $b['balance'], (string) $a['balance'], 2))
        ->first();

    // Skalasi Ukuran Font Dinamis berbasis panjang karakter nominal (Responsive Balance Typography)
    $getMainBalanceClass = function (string $formatted): string {
        $len = mb_strlen(trim($formatted));
        if ($len <= 12) {
            return 'text-4xl sm:text-5xl lg:text-[3.25rem]';
        }
        if ($len <= 17) {
            return 'text-3xl sm:text-4xl lg:text-5xl';
        }
        if ($len <= 22) {
            return 'text-2xl sm:text-3xl lg:text-4xl';
        }
        if ($len <= 27) {
            return 'text-xl sm:text-2xl lg:text-3xl';
        }
        if ($len <= 33) {
            return 'text-lg sm:text-xl lg:text-2xl';
        }
        return 'text-base sm:text-lg lg:text-xl';
    };

    $getCardBalanceClass = function (string $formatted): string {
        $len = mb_strlen(trim($formatted));
        if ($len <= 12) {
            return 'text-2xl sm:text-3xl';
        }
        if ($len <= 17) {
            return 'text-xl sm:text-2xl';
        }
        if ($len <= 22) {
            return 'text-lg sm:text-xl';
        }
        if ($len <= 28) {
            return 'text-base sm:text-lg';
        }
        if ($len <= 34) {
            return 'text-sm sm:text-base';
        }
        return 'text-xs sm:text-sm';
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
            wallets: @js($wallets),
            settingTransferWallet: false,
            primaryTransferWalletId: @js((string) (collect($wallets)->firstWhere('is_primary', true)['id'] ?? '')),
            isSubmitting: false,
            isDeleting: false,

            async chooseTransferWallet(walletId) {
                if (this.settingTransferWallet || String(walletId) === this.primaryTransferWalletId) return;

                this.settingTransferWallet = true;
                try {
                    const response = await fetch(@js(route('dompet.toggleTransfer', '__ID__')).replace('__ID__', walletId), {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ set_primary: true }),
                    });
                    if (!response.ok) {
                        window.financeAlert({
                            title: 'Update Failed',
                            message: 'The transfer receiving wallet could not be updated. Reload the page and try again.',
                            variant: 'danger',
                        });
                        return;
                    }

                    this.primaryTransferWalletId = String(walletId);
                    this.wallets.forEach(wallet => {
                        wallet.is_primary = String(wallet.id) === this.primaryTransferWalletId;
                    });
                    window.financeToast({
                        message: 'Primary transfer wallet updated successfully.',
                        variant: 'income',
                    });
                } catch (error) {
                    window.financeAlert({
                        title: 'Connection Error',
                        message: 'The transfer receiving wallet could not be updated. Please try again.',
                        variant: 'danger',
                    });
                } finally {
                    this.settingTransferWallet = false;
                }
            },

            normalizeColor(color) {
                if (!color) return '';
                const c = String(color).trim().toLowerCase();
                const presets = {
                    'brand': '#4f46e5',
                    'income': '#10b981',
                    'warn': '#f59e0b',
                    'expense': '#ef4444',
                    'dark': '#1f2937',
                };
                return presets[c] || c;
            },

            colorPicker: {
                hue: 240,
                saturation: 100,
                value: 90,
                hexInput: '#4F46E5',
                isDraggingSpectrum: false,
            },

            hsvToHex(h, s, v) {
                s = s / 100;
                v = v / 100;
                const c = v * s;
                const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
                const m = v - c;
                let r = 0, g = 0, b = 0;
                if (h >= 0 && h < 60) { r = c; g = x; b = 0; }
                else if (h >= 60 && h < 120) { r = x; g = c; b = 0; }
                else if (h >= 120 && h < 180) { r = 0; g = c; b = x; }
                else if (h >= 180 && h < 240) { r = 0; g = x; b = c; }
                else if (h >= 240 && h < 300) { r = x; g = 0; b = c; }
                else { r = c; g = 0; b = x; }
                const toHex = (n) => {
                    const hex = Math.max(0, Math.min(255, Math.round((n + m) * 255))).toString(16);
                    return hex.length === 1 ? '0' + hex : hex;
                };
                return ('#' + toHex(r) + toHex(g) + toHex(b)).toLowerCase();
            },

            hexToHsv(hex) {
                if (!hex) return { h: 240, s: 100, v: 90 };
                let c = String(hex).trim().replace(/^#/, '');
                if (c.length === 3) {
                    c = c.split('').map(x => x + x).join('');
                }
                if (c.length !== 6 || !/^[0-9a-fA-F]{6}$/.test(c)) {
                    return { h: 240, s: 100, v: 90 };
                }
                const r = parseInt(c.substring(0, 2), 16) / 255;
                const g = parseInt(c.substring(2, 4), 16) / 255;
                const b = parseInt(c.substring(4, 6), 16) / 255;
                const max = Math.max(r, g, b);
                const min = Math.min(r, g, b);
                const delta = max - min;
                let h = 0;
                if (delta !== 0) {
                    if (max === r) {
                        h = ((g - b) / delta) % 6;
                    } else if (max === g) {
                        h = (b - r) / delta + 2;
                    } else {
                        h = (r - g) / delta + 4;
                    }
                    h = Math.round(h * 60);
                    if (h < 0) h += 360;
                }
                const s = max === 0 ? 0 : Math.round((delta / max) * 100);
                const v = Math.round(max * 100);
                return { h, s, v };
            },

            initColorPicker(colorValue) {
                const normalized = this.normalizeColor(colorValue || 'brand');
                const hsv = this.hexToHsv(normalized);
                this.colorPicker.hue = hsv.h;
                this.colorPicker.saturation = hsv.s;
                this.colorPicker.value = hsv.v;
                this.colorPicker.hexInput = (normalized.startsWith('#') ? normalized : ('#' + normalized)).toUpperCase();
            },

            syncFromHsv() {
                const hex = this.hsvToHex(this.colorPicker.hue, this.colorPicker.saturation, this.colorPicker.value);
                this.editing.color = hex;
                this.colorPicker.hexInput = hex.toUpperCase();
            },

            onSpectrumMouseDown(e) {
                this.colorPicker.isDraggingSpectrum = true;
                this.updateSpectrumPos(e);
            },

            updateSpectrumPos(e) {
                if (!this.$refs.spectrumArea) return;
                const rect = this.$refs.spectrumArea.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                const x = Math.max(0, Math.min(rect.width, clientX - rect.left));
                const y = Math.max(0, Math.min(rect.height, clientY - rect.top));
                this.colorPicker.saturation = Math.round((x / rect.width) * 100);
                this.colorPicker.value = Math.round((1 - (y / rect.height)) * 100);
                this.syncFromHsv();
            },

            onHexInput(value) {
                let raw = String(value || '').trim();
                if (!raw.startsWith('#') && raw.length > 0) {
                    raw = '#' + raw;
                }
                this.colorPicker.hexInput = raw.toUpperCase();
                if (/^#[0-9a-fA-F]{6}$/.test(raw)) {
                    const hex = raw.toLowerCase();
                    this.editing.color = hex;
                    const hsv = this.hexToHsv(hex);
                    this.colorPicker.hue = hsv.h;
                    this.colorPicker.saturation = hsv.s;
                    this.colorPicker.value = hsv.v;
                }
            },

            selectPresetColor(presetCode) {
                const hex = this.normalizeColor(presetCode);
                this.editing.color = presetCode;
                this.initColorPicker(hex);
            },

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
                } else if (this.editing.balanceChanged) {
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
                const allColors = ['brand', 'income', 'warn', 'expense', 'dark', '#8b5cf6', '#ec4899', '#14b8a6', '#06b6d4'];
                const firstFree = allColors[wallets.length % allColors.length];
                editing = {
                    id: null,
                    name: '',
                    description: '',
                    type: 'Digital wallet',
                    balance: '',
                    balanceDisplay: '',
                    saldo_awal: '',
                    saldoAwalDisplay: '',
                    color: firstFree,
                    savedColor: null
                };
                initColorPicker(firstFree);
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

                <div class="flex min-w-0 items-center gap-2 text-income">

                    <x-icon
                        name="arrow-up"
                        size="h-4 w-4 shrink-0"
                    />

                    <span class="min-w-0 truncate tabular-nums">
                        <span class="font-bold text-sm sm:text-base whitespace-nowrap">{{ $formatMoney($summary['total_pemasukan_bulan_ini']) }}</span>
                        <span class="text-xs text-muted ml-1 whitespace-nowrap">this month</span>
                    </span>

                </div>


                <div class="flex min-w-0 items-center gap-2 text-expense">

                    <x-icon
                        name="arrow-down"
                        size="h-4 w-4 shrink-0"
                    />

                    <span class="min-w-0 truncate tabular-nums">
                        <span class="font-bold text-sm sm:text-base whitespace-nowrap">{{ $formatMoney($summary['total_pengeluaran_bulan_ini']) }}</span>
                        <span class="text-xs text-muted ml-1 whitespace-nowrap">this month</span>
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


                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            x-on:click="
                                mode = 'edit';
                                editing = Object.assign({}, wallets.find(w => String(w.id) === String({{ $w['id'] }})) || @js($w));
                                editing.savedColor = editing.color;
                                editing.balance = window.financeMoney.fromBase(editing.balance);
                                editing.balanceDisplay = window.financeMoney.formatNumber(editing.balance, { minDecimals: 0, maxDecimals: 2 });
                                editing.balanceChanged = false;
                                editing.saldo_awal = '';
                                editing.saldoAwalDisplay = '';
                                initColorPicker(editing.color);
                                open = true;
                            "
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-line hover:bg-page"
                            aria-label="Edit wallet"
                        >
                            <x-icon name="more" size="h-4 w-4" />
                        </button>
                    </div>
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

                <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-4">
                    <span
                        x-show="String(primaryTransferWalletId) === String({{ $w['id'] }})"
                        class="inline-flex items-center gap-1.5 rounded-full bg-brand-soft px-2.5 py-1 text-xs font-semibold text-brand"
                    >
                        <x-icon name="check" size="h-3.5 w-3.5" />
                        Primary recipient
                    </span>

                    <button
                        type="button"
                        x-show="String(primaryTransferWalletId) !== String({{ $w['id'] }})"
                        x-on:click.stop="chooseTransferWallet('{{ $w['id'] }}')"
                        x-bind:disabled="settingTransferWallet"
                        class="rounded-lg border border-line bg-white px-3 py-1.5 text-xs font-medium text-ink transition hover:border-brand/40 hover:bg-page disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span x-show="!settingTransferWallet">Set as recipient</span>
                        <span x-show="settingTransferWallet" x-cloak>Updating...</span>
                    </button>
                </div>

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
                ->map(fn ($wallet): string => bccomp((string) $wallet['balance'], '0.00', 2) > 0
                    ? (string) $wallet['balance']
                    : '0.00');
            $totalDistributionBalance = $distributionBalances
                ->reduce(fn (string $total, string $balance): string => bcadd($total, $balance, 2), '0.00');

            // Memastikan seluruh segmen warna wallet terlihat tanpa mendistorsi proporsi saldo
            $minShare = $walletCount > 0 ? min(2.5, 25 / $walletCount) : 0;
            $totalReserved = $walletCount * $minShare;
            $remainingPool = 100 - $totalReserved;
        @endphp

        <div class="mt-5 flex w-full min-w-0 gap-1.5 overflow-hidden rounded-full">

            @forelse ($wallets as $index => $w)
                @php
                    $balance = $distributionBalances[$index] ?? '0.00';
                    $isCustom = str_starts_with($w['color'], '#');
                    $solidClass = ! $isCustom ? ($tones[$w['color']]['solid'] ?? 'bg-brand') : '';

                    if (bccomp($totalDistributionBalance, '0.00', 2) > 0) {
                        // Floating point is used only to size the visual chart segment.
                        $rawRatio = (float) bcdiv($balance, $totalDistributionBalance, 8);
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
        x-on:mousemove.window="if (colorPicker.isDraggingSpectrum) updateSpectrumPos($event)"
        x-on:mouseup.window="colorPicker.isDraggingSpectrum = false"
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


                <div
                    x-data="{
                        typeOpen: false,
                        typeMenuStyle: '',
                        toggleTypeMenu() {
                            this.typeOpen = !this.typeOpen;
                            if (this.typeOpen) this.$nextTick(() => this.positionTypeMenu());
                        },
                        positionTypeMenu() {
                            if (!this.typeOpen || !this.$refs.typeTrigger || !this.$refs.typeMenu) return;
                            const trigger = this.$refs.typeTrigger.getBoundingClientRect();
                            const menu = this.$refs.typeMenu;
                            const height = Math.min(menu.scrollHeight || 3 * 42 + 8, 3 * 42 + 8);
                            const spaceBelow = window.innerHeight - trigger.bottom - 8;
                            const spaceAbove = trigger.top - 8;
                            const openAbove = spaceBelow < height && spaceAbove > spaceBelow;
                            const maxHeight = Math.max(0, openAbove ? spaceAbove : spaceBelow);
                            const top = openAbove
                                ? Math.max(8, trigger.top - Math.min(height, maxHeight) - 6)
                                : Math.min(trigger.bottom + 6, window.innerHeight - Math.min(height, maxHeight) - 8);
                            this.typeMenuStyle = `position: fixed; left: ${trigger.left}px; top: ${top}px; width: ${trigger.width}px; max-height: ${maxHeight}px; z-index: 100;`;
                        }
                    }"
                    class="relative"
                    x-on:click.outside="typeOpen = false"
                    x-on:resize.window="positionTypeMenu()"
                    x-on:scroll.window.capture="positionTypeMenu()"
                >
                    <label class="mb-1.5 block text-sm font-medium">
                        Wallet type
                    </label>

                    <button
                        type="button"
                        x-ref="typeTrigger"
                        x-on:click="toggleTypeMenu()"
                        class="flex w-full items-center justify-between rounded-lg border border-line bg-white px-3 py-2.5 text-sm text-ink transition hover:border-brand/40 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                        :class="typeOpen ? 'border-brand ring-2 ring-brand/20' : ''"
                    >
                        <span x-text="editing.type || 'Digital wallet'" class="font-medium"></span>
                        <span class="text-muted shrink-0 transition-transform duration-200" :class="typeOpen ? 'rotate-180' : ''">
                            <x-icon name="chevron-down" size="h-4 w-4" />
                        </span>
                    </button>

                    <template x-teleport="body">
                        <div
                            x-show="typeOpen"
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            x-ref="typeMenu"
                            x-on:click.stop
                            x-on:scroll="positionTypeMenu()"
                            :style="typeMenuStyle"
                            class="overflow-y-auto rounded-xl border border-line bg-white p-1 shadow-lg ring-1 ring-black/5"
                        >
                            <template x-for="t in ['Physical wallet', 'Digital wallet', 'Bank account']" :key="t">
                                <button
                                    type="button"
                                    x-on:click="editing.type = t; typeOpen = false"
                                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm transition text-left cursor-pointer"
                                    :class="editing.type === t ? 'bg-brand-soft text-brand font-semibold' : 'text-ink hover:bg-page font-medium'"
                                >
                                    <span x-text="t"></span>
                                    <span x-show="editing.type === t" class="text-brand shrink-0">
                                        <x-icon name="check" size="h-4 w-4" />
                                    </span>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>

                <x-input
                    label="{{ __('dashboard.current_balance') }}"
                    name="wallet_balance"
                    inputmode="decimal"
                    type="text"
                    placeholder="0"
                    x-model="editing.balanceDisplay"
                    x-on:input="editing.balanceChanged = mode === 'edit'; formatBalance($event.target.value)"
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
                <div class="mt-4">
                    <label class="mb-2 block text-sm font-medium text-ink">
                        Wallet color
                    </label>

                    {{-- Spectrum Saturation/Value Area --}}
                    <div
                        x-ref="spectrumArea"
                        @mousedown="onSpectrumMouseDown($event)"
                        @touchstart.passive="onSpectrumMouseDown($event)"
                        @touchmove.prevent="if (colorPicker.isDraggingSpectrum) updateSpectrumPos($event)"
                        @touchend="colorPicker.isDraggingSpectrum = false"
                        class="relative h-28 w-full cursor-crosshair rounded-xl overflow-hidden select-none border border-line shadow-inner"
                        :style="`background-color: hsl(${colorPicker.hue}, 100%, 50%);`"
                    >
                        {{-- White horizontal gradient (saturation) --}}
                        <div class="absolute inset-0 pointer-events-none" style="background: linear-gradient(to right, #ffffff, transparent);"></div>
                        {{-- Black vertical gradient (value) --}}
                        <div class="absolute inset-0 pointer-events-none" style="background: linear-gradient(to top, #000000, transparent);"></div>

                        {{-- Draggable selector thumb --}}
                        <div
                            class="pointer-events-none absolute h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-md ring-1 ring-black/25"
                            :style="`left: ${colorPicker.saturation}%; top: ${100 - colorPicker.value}%; background-color: ${normalizeColor(editing.color)};`"
                        ></div>
                    </div>

                    {{-- Hue Slider --}}
                    <div class="mt-3">
                        <input
                            type="range"
                            min="0"
                            max="360"
                            x-model.number="colorPicker.hue"
                            @input="syncFromHsv()"
                            class="finance-color-hue h-3 w-full cursor-pointer appearance-none rounded-full focus:outline-none shadow-inner"
                            style="background: linear-gradient(to right, #ff0000 0%, #ffff00 17%, #00ff00 33%, #00ffff 50%, #0000ff 67%, #ff00ff 83%, #ff0000 100%);"
                        />
                    </div>

                    {{-- Preview & Hex Code Input --}}
                    <div class="mt-3 flex items-center gap-3">
                        {{-- Color Preview Swatch --}}
                        <div
                            class="h-10 w-10 shrink-0 rounded-xl border border-line shadow-xs flex items-center justify-center transition-colors"
                            :style="`background-color: ${normalizeColor(editing.color)};`"
                            :title="`Current: ${colorPicker.hexInput}`"
                        >
                            <x-icon name="check" size="h-4 w-4 drop-shadow-sm text-white" />
                        </div>

                        {{-- HEX Input with '#' prefix --}}
                        <div class="relative flex-1">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted font-mono text-sm">
                                #
                            </div>
                            <input
                                type="text"
                                maxlength="7"
                                :value="colorPicker.hexInput.replace(/^#/, '')"
                                @input="onHexInput($event.target.value)"
                                placeholder="3B82F6"
                                class="w-full rounded-lg border border-line bg-white pl-7 pr-3 py-2 text-sm font-mono font-medium text-ink placeholder:text-muted uppercase focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                            />
                        </div>
                    </div>

                    {{-- Quick Swatches --}}
                    <div class="mt-3.5">
                        <p class="mb-2 text-xs font-medium text-muted">Quick swatches</p>
                        <div class="flex flex-wrap items-center gap-2">
                            @foreach (['brand', 'income', 'warn', 'expense', 'dark'] as $c)
                                <button
                                    type="button"
                                    x-on:click="selectPresetColor('{{ $c }}')"
                                    :class="{
                                        'ring-2 ring-offset-2 ring-brand scale-105 shadow-sm': normalizeColor(editing.color) === normalizeColor('{{ $c }}'),
                                        'hover:scale-110 active:scale-95': true
                                    }"
                                    title="Select color"
                                    class="relative flex h-7 w-7 items-center justify-center rounded-full text-white transition-all duration-150 {{ $tones[$c]['solid'] }}"
                                >
                                    <x-icon
                                        name="check"
                                        size="h-3.5 w-3.5 drop-shadow-sm"
                                        x-show="normalizeColor(editing.color) === normalizeColor('{{ $c }}')"
                                    />
                                </button>
                            @endforeach

                            @foreach ([
                                ['name' => 'Purple', 'hex' => '#8b5cf6'],
                                ['name' => 'Pink', 'hex' => '#ec4899'],
                                ['name' => 'Teal', 'hex' => '#14b8a6'],
                                ['name' => 'Cyan', 'hex' => '#06b6d4'],
                            ] as $extra)
                                <button
                                    type="button"
                                    x-on:click="selectPresetColor('{{ $extra['hex'] }}')"
                                    :class="{
                                        'ring-2 ring-offset-2 ring-brand scale-105 shadow-sm': normalizeColor(editing.color) === '{{ $extra['hex'] }}',
                                        'hover:scale-110 active:scale-95': true
                                    }"
                                    style="background-color: {{ $extra['hex'] }};"
                                    title="{{ $extra['name'] }}"
                                    class="relative flex h-7 w-7 items-center justify-center rounded-full text-white transition-all duration-150"
                                >
                                    <x-icon
                                        name="check"
                                        size="h-3.5 w-3.5 drop-shadow-sm"
                                        x-show="normalizeColor(editing.color) === '{{ $extra['hex'] }}'"
                                    />
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Validation error message --}}
                    <p class="mt-1.5 text-xs leading-5 text-muted">
                        Pick any custom color via spectrum, slider, or HEX code. Colors can be reused across wallets.
                    </p>
                </div>

            </div>


            {{-- Modal actions --}}
            <div class="sticky bottom-0 z-20 -mx-6 -mb-6 mt-7 flex flex-col-reverse gap-3 border-t border-line bg-white px-6 py-4 sm:flex-row sm:items-center sm:justify-between">

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