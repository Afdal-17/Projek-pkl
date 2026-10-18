@extends('layouts.main')

@section('title', __('saving.add_target'))

@section('content')

<div
    x-data="{
        name: '',
        note: '',
        wallet: @js((string) ($wallets->first()['id'] ?? '')),
        target: '',
        initial: @js((float) ($wallets->first()['balance'] ?? 0)),
        initialManual: false,
        wallets: @js($wallets),
        csrfToken: @js(csrf_token()),

        init() {
            this.$watch('wallet', () => this.syncInitial());
            this.$watch('target', () => this.syncInitial());
        },

        get selectedWallet() {
            return this.wallets.find(item => String(item.id) === String(this.wallet)) ?? { balance: 0 };
        },

        get walletBalanceDisplay() {
            // Saldo wallet tersimpan dalam IDR base; konversi ke mata uang aktif
            return window.financeMoney.fromBase(Number(this.selectedWallet.balance));
        },

        syncInitial() {
            if (!this.initialManual) {
                this.initial = this.target
                    ? Math.min(this.walletBalanceDisplay, Number(this.target))
                    : this.walletBalanceDisplay;
            }
        },

        rp(amount) {
            // Nilai input user dalam mata uang aktif
            return window.financeMoney.formatNative(Number(amount) || 0);
        },

        async saveTarget() {
            const target = Number(this.target);
            const initial = Number(this.initial) || 0;

            if (!this.name.trim()) {
                window.financeAlert({
                    title: 'Target Name Required',
                    message: 'Please enter a target name.',
                    variant: 'danger',
                });
                return;
            }

            if (!target || target <= 0) {
                window.financeAlert({
                    title: 'Invalid Target Amount',
                    message: 'Target amount must be greater than 0.',
                    variant: 'danger',
                });
                return;
            }

            if (initial < 0 || initial > target) {
                window.financeAlert({
                    title: 'Invalid Initial Savings',
                    message: 'Initial deposit cannot exceed the target amount.',
                    variant: 'danger',
                });
                return;
            }

            if (initial > this.walletBalanceDisplay) {
                window.financeAlert({
                    title: 'Insufficient Balance',
                    message: 'Initial deposit cannot exceed the selected wallet balance.',
                    variant: 'danger',
                });
                return;
            }

            const payload = new URLSearchParams({
                _token: this.csrfToken,
                id_dompet: this.wallet,
                nama_target: this.name.trim(),
                deskripsi: this.note.trim(),
                nominal_target: String(target),
                initial_savings: this.initial === '' ? '' : String(initial),
            });

            const response = await fetch(@js(route('saving.store')), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload,
            });

            if (!response.ok) {
                const result = await response.json();
                window.financeAlert({
                    title: 'Saving Failed',
                    message: Object.values(result.errors ?? {}).flat()[0] ?? 'Saving target could not be saved.',
                    variant: 'danger',
                });
                return;
            }

            window.location.href = '/saving';
        }
    }"
>

    {{-- Header --}}
    <div>
        <h1 class="text-3xl font-bold">{{ __('saving.add_target') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ __('saving.subtitle') }}</p>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[2fr_1.2fr]">

        {{-- FORM --}}
        <x-card class="p-8">

            {{-- Name --}}
            <div>
                <label for="target_name" class="mb-2 block text-sm font-medium">{{ __('saving.target_name') }}</label>
                <input
                    id="target_name"
                    type="text"
                    x-model="name"
                    placeholder="e.g. New Laptop"
                    class="w-full rounded-lg border border-line bg-white px-3 py-3 text-sm placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                >
            </div>

            {{-- Description --}}
            <div class="mt-6">
                <label for="target_note" class="mb-2 block text-sm font-medium">{{ __('saving.description') }}</label>
                <textarea
                    id="target_note"
                    rows="4"
                    x-model="note"
                    placeholder="e.g. Work equipment"
                    class="w-full rounded-lg border border-line bg-white px-3 py-3 text-sm placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                ></textarea>
            </div>

            {{-- Wallet --}}
            <div class="mt-6">
                <label for="target_wallet" class="mb-2 block text-sm font-medium">{{ __('saving.wallet') }}</label>

                <div
                    class="relative"
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
                        class="flex w-full items-center justify-between rounded-lg border border-line bg-white py-3 pl-9 pr-3 text-sm text-left shadow-sm transition hover:border-brand/40 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 cursor-pointer"
                        :class="open ? 'border-brand ring-2 ring-brand/20' : ''"
                    >
                        <x-icon name="wallet" size="h-4 w-4"
                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                        <span class="truncate font-medium text-ink" x-text="wallets.find(w => String(w.id) === String(wallet))?.name ?? 'Select wallet'"></span>
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
                            <template x-for="item in wallets" :key="item.name">
                                <button
                                    type="button"
                                    @click="wallet = String(item.id); open = false"
                                    class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-sm transition text-left cursor-pointer"
                                    :class="String(wallet) === String(item.id) ? 'bg-brand-soft text-brand font-semibold' : 'text-ink hover:bg-page font-medium'"
                                >
                                    <span x-text="item.name" class="truncate font-medium"></span>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <span class="text-xs text-muted" x-text="rp(item.balance)"></span>
                                        <x-icon x-show="String(wallet) === String(item.id)" name="check" size="h-4 w-4" class="text-brand" />
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>

                    <select id="target_wallet_native" x-model="wallet" class="sr-only" tabindex="-1" aria-hidden="true">
                        <template x-for="item in wallets" :key="item.name">
                            <option :value="String(item.id)" :selected="String(item.id) === wallet" x-text="item.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            {{-- Saving target --}}
            <div class="mt-6">
                <label for="target_amount" class="mb-2 block text-sm font-medium">{{ __('saving.saving_target') }}</label>

                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-muted" x-text="window.financeMoney.symbol"></span>
                    <input
                        id="target_amount"
                        type="number"
                        min="0"
                        x-model.number="target"
                        placeholder="0"
                        class="w-full rounded-lg border border-line bg-white py-3 pl-12 pr-4 text-2xl font-semibold placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    >
                </div>
            </div>

            {{-- Initial savings --}}
            <div class="mt-6">
                <label for="initial_amount" class="mb-2 block text-sm font-medium">{{ __('saving.initial_savings') }}</label>

                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-muted" x-text="window.financeMoney.symbol"></span>
                    <input
                        id="initial_amount"
                        type="number"
                        min="0"
                        x-model.number="initial"
                        x-on:input="initialManual = true"
                        placeholder="0"
                        class="w-full rounded-lg border border-line bg-white py-3 pl-12 pr-4 text-2xl font-semibold placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    >
                </div>
            </div>

            {{-- Tombol --}}
            <div class="mt-6 flex justify-end gap-3">
                <x-button href="/saving" variant="outline">{{ __('saving.cancel') }}</x-button>
                <x-button x-on:click="saveTarget()">{{ __('saving.add_button') }}</x-button>
            </div>

        </x-card>


        {{-- SUMMARY --}}
        <div>
            <x-card class="p-6">

                <h2 class="text-lg font-semibold">{{ __('saving.summary') }}</h2>

                <div class="mt-5 space-y-4 text-sm">

                    <div class="flex items-center justify-between">
                        <span class="text-muted">{{ __('saving.target_name') }}</span>
                        <span class="rounded-full bg-expense-soft px-3 py-1 text-xs font-medium"
                              x-text="name || '-'"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted">{{ __('saving.wallet') }}</span>
                        <span class="rounded-full bg-expense-soft px-3 py-1 text-xs font-medium"
                              x-text="wallets.find(item => String(item.id) === wallet)?.name ?? '-' "></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted">{{ __('saving.saving_target') }}</span>
                        <span class="text-base font-bold" x-text="rp(target)"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted">{{ __('saving.initial_savings') }}</span>
                        <span class="text-base font-bold" x-text="rp(initial)"></span>
                    </div>

                </div>

            </x-card>
        </div>

    </div>

</div>

@endsection