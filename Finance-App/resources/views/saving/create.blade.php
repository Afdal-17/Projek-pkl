@extends('layouts.main')

@section('title', __('saving.add_target'))

@section('content')

<div
    x-data="{
        name: '',
        note: '',
        wallet: @js((string) ($wallets->first()['id'] ?? '')),
        target: '',
        targetDisplay: '',
        initial: '',
        initialDisplay: '',
        saving: false,
        wallets: @js($wallets),
        csrfToken: @js(csrf_token()),

        get selectedWallet() {
            return this.wallets.find(item => String(item.id) === String(this.wallet)) ?? { balance: 0 };
        },

        get walletBalanceDisplay() {
            return window.financeMoney.fromBase(this.selectedWallet.balance);
        },

        formatTarget(value) {
            const result = window.financeMoney.formatTyped(value);
            this.target = result.value === '0' && result.display === '' ? '' : result.value;
            this.targetDisplay = result.display;
        },

        formatInitial(value) {
            const result = window.financeMoney.formatTyped(value);
            this.initial = result.value === '0' && result.display === '' ? '' : result.value;
            this.initialDisplay = result.display;
        },

        rp(amount) {
            return window.financeMoney.formatNative(amount || 0);
        },

        formatWalletBalance(balance) {
            return window.financeMoney.format(window.financeMoney.fromBase(balance));
        },

        async saveTarget() {
            if (this.saving) return;

            const target = String(this.target || '0');
            const initial = String(this.initial || '0');

            if (!this.name.trim()) {
                window.financeAlert({
                    title: 'Target Name Required',
                    message: 'Please enter a target name.',
                    variant: 'danger',
                });
                return;
            }

            if (window.financeMoney.compare(target, '0') <= 0) {
                window.financeAlert({
                    title: 'Invalid Target Amount',
                    message: 'Target amount must be greater than 0.',
                    variant: 'danger',
                });
                return;
            }

            if (this.initial && window.financeMoney.compare(initial, '0') > 0) {
                if (window.financeMoney.compare(initial, target) > 0) {
                    window.financeAlert({
                        title: 'Invalid Initial Savings',
                        message: 'Initial deposit cannot exceed the target amount.',
                        variant: 'danger',
                    });
                    return;
                }

                if (window.financeMoney.compare(initial, this.walletBalanceDisplay) > 0) {
                    window.financeAlert({
                        title: 'Insufficient Balance',
                        message: 'Initial deposit cannot exceed the selected wallet balance.',
                        variant: 'danger',
                    });
                    return;
                }
            }

            const payload = new URLSearchParams({
                _token: this.csrfToken,
                id_dompet: this.wallet,
                nama_target: this.name.trim(),
                deskripsi: this.note.trim(),
                nominal_target: target,
                initial_savings: this.initial === '' ? '0' : initial,
            });

            this.saving = true;

            try {
                const response = await fetch(@js(route('saving.store')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: payload,
                });

                if (!response.ok) {
                    let result = {};
                    try {
                        result = await response.json();
                    } catch (e) {}

                    window.financeAlert({
                        title: 'Saving Failed',
                        message: Object.values(result.errors ?? {}).flat()[0]
                            ?? 'Saving target could not be saved.',
                        variant: 'danger',
                    });
                    return;
                }

                window.location.href = '/saving';
            } catch (e) {
                window.financeAlert({
                    title: 'Connection Error',
                    message: 'Connection problem. Your data was not sent. Please try again.',
                    variant: 'danger',
                });
            } finally {
                this.saving = false;
            }
        },
    }"
    class="min-w-0"
>

    {{-- Header --}}
    <div>
        <h1 class="text-3xl font-bold">{{ __('saving.add_target') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ __('saving.subtitle') }}</p>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[2fr_1.2fr] min-w-0">

        {{-- FORM --}}
        <x-card class="p-5 sm:p-8 min-w-0">

            {{-- Name --}}
            <div>
                <label for="target_name" class="mb-2 block text-sm font-medium text-ink">{{ __('saving.target_name') }}</label>
                <input
                    id="target_name"
                    type="text"
                    x-model="name"
                    placeholder="e.g. New Laptop"
                    class="w-full min-w-0 rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 transition"
                >
            </div>

            {{-- Description --}}
            <div class="mt-6">
                <label for="target_note" class="mb-2 block text-sm font-medium text-ink">{{ __('saving.description') }}</label>
                <textarea
                    id="target_note"
                    rows="4"
                    x-model="note"
                    placeholder="e.g. Work equipment"
                    class="w-full min-w-0 rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 transition resize-y"
                ></textarea>
            </div>

            {{-- Wallet --}}
            <div class="mt-6">
                <label for="target_wallet" class="mb-2 block text-sm font-medium text-ink">{{ __('saving.wallet') }}</label>

                <div
                    class="relative min-w-0"
                    x-data="{
                        open: false,
                        dropUp: false,
                        toggle(el) {
                            this.open = !this.open;
                            if (this.open) {
                                const rect = el.getBoundingClientRect();
                                this.dropUp = (window.innerHeight - rect.bottom) < 260;
                            }
                        }
                    }"
                    @click.outside="open = false"
                    @keydown.escape.window="open = false"
                >
                    <button
                        id="target_wallet"
                        type="button"
                        @click="toggle($el)"
                        class="flex w-full min-w-0 items-center justify-between gap-3 rounded-xl border border-line bg-white py-2.5 pl-9 pr-3 text-sm text-left shadow-sm transition hover:border-brand/40 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 cursor-pointer"
                        :class="open ? 'border-brand ring-2 ring-brand/20' : ''"
                    >
                        <x-icon name="wallet" size="h-4 w-4"
                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                        <div class="min-w-0 flex-1 truncate">
                            <span class="font-medium text-ink" x-text="selectedWallet?.name ?? 'Select wallet'"></span>
                            <span class="ml-1.5 text-xs text-muted" x-show="selectedWallet?.name" x-text="'(' + formatWalletBalance(selectedWallet.balance) + ')'"></span>
                        </div>
                        <x-icon name="chevron-down" size="h-4 w-4" class="text-muted shrink-0 transition-transform duration-200" x-bind:class="open ? 'rotate-180' : ''" />
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute left-0 right-0 z-50 max-h-60 overflow-y-auto rounded-xl border border-line bg-white p-1.5 shadow-2xl ring-1 ring-black/5"
                        :class="dropUp ? 'bottom-full mb-2 origin-bottom' : 'top-full mt-2 origin-top'"
                    >
                        <div class="space-y-0.5">
                            <template x-for="item in wallets" :key="item.id">
                                <button
                                    type="button"
                                    @click="wallet = String(item.id); open = false"
                                    class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm transition text-left cursor-pointer"
                                    :class="String(wallet) === String(item.id) ? 'bg-brand-soft text-brand font-semibold' : 'text-ink hover:bg-page font-medium'"
                                >
                                    <div class="min-w-0 flex-1 truncate">
                                        <p class="truncate font-medium" x-text="item.name"></p>
                                        <p class="truncate text-xs text-muted tabular-nums" x-text="'Balance: ' + formatWalletBalance(item.balance)"></p>
                                    </div>
                                    <x-icon x-show="String(wallet) === String(item.id)" name="check" size="h-4 w-4" class="text-brand shrink-0" />
                                </button>
                            </template>
                        </div>
                    </div>

                    <select id="target_wallet_native" x-model="wallet" class="sr-only" tabindex="-1" aria-hidden="true">
                        <template x-for="item in wallets" :key="item.id">
                            <option :value="String(item.id)" :selected="String(item.id) === wallet" x-text="item.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            {{-- Saving target --}}
            <div class="mt-6">
                <label for="target_amount" class="mb-2 block text-sm font-medium text-ink">{{ __('saving.saving_target') }}</label>

                <div class="relative min-w-0">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                        <span class="text-sm font-semibold text-muted" x-text="window.financeMoney.symbol"></span>
                    </div>
                    <input
                        id="target_amount"
                        type="text"
                        inputmode="decimal"
                        x-model="targetDisplay"
                        x-on:input="formatTarget($event.target.value)"
                        x-on:focus="$event.target.select()"
                        placeholder="0"
                        class="w-full min-w-0 rounded-xl border border-line bg-white py-2.5 pl-11 pr-3.5 text-sm sm:text-base font-medium text-ink placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 transition"
                    >
                </div>
            </div>

            {{-- Initial savings --}}
            <div class="mt-6">
                <div class="mb-2 flex items-center justify-between">
                    <label for="initial_amount" class="block text-sm font-medium text-ink">{{ __('saving.initial_savings') }}</label>
                    <span class="text-xs text-muted">
                        Wallet balance: <span class="font-medium text-ink" x-text="formatWalletBalance(selectedWallet.balance)"></span>
                    </span>
                </div>

                <div class="relative min-w-0">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                        <span class="text-sm font-semibold text-muted" x-text="window.financeMoney.symbol"></span>
                    </div>
                    <input
                        id="initial_amount"
                        type="text"
                        inputmode="decimal"
                        x-model="initialDisplay"
                        x-on:input="formatInitial($event.target.value)"
                        x-on:focus="$event.target.select()"
                        placeholder="0"
                        class="w-full min-w-0 rounded-xl border border-line bg-white py-2.5 pl-11 pr-3.5 text-sm sm:text-base font-medium text-ink placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 transition"
                    >
                </div>
            </div>

            {{-- Buttons --}}
            <div class="mt-8 flex justify-end gap-3">
                <x-button href="/saving" variant="outline">{{ __('saving.cancel') }}</x-button>
                <x-button x-on:click="saveTarget()" x-bind:disabled="saving">
                    <span x-text="saving ? 'Saving...' : @js(__('saving.add_button'))"></span>
                </x-button>
            </div>

        </x-card>


        {{-- SUMMARY --}}
        <div class="min-w-0">
            <x-card class="p-6 min-w-0">

                <h2 class="text-lg font-semibold">{{ __('saving.summary') }}</h2>

                <div class="mt-5 space-y-4 text-sm">

                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted shrink-0">{{ __('saving.target_name') }}</span>
                        <span class="truncate rounded-full bg-expense-soft px-3 py-1 text-xs font-medium text-right max-w-[60%]"
                              x-text="name || '-'"></span>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted shrink-0">{{ __('saving.wallet') }}</span>
                        <span class="truncate rounded-full bg-expense-soft px-3 py-1 text-xs font-medium text-right max-w-[60%]"
                              x-text="wallets.find(item => String(item.id) === wallet)?.name ?? '-' "></span>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted shrink-0">{{ __('saving.saving_target') }}</span>
                        <span class="text-base font-bold truncate text-right tabular-nums" x-text="rp(target)"></span>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted shrink-0">{{ __('saving.initial_savings') }}</span>
                        <span class="text-base font-bold truncate text-right tabular-nums" x-text="rp(initial)"></span>
                    </div>

                </div>

            </x-card>
        </div>

    </div>

</div>

@endsection