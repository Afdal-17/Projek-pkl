@extends('layouts.main')

@section('title', 'Transfer')

@section('content')

<div
    x-data="{
        mode: 'wallet',
        from: @js((string) ($wallets->first()['id'] ?? '')),
        to: @js((string) ($wallets->skip(1)->first()['id'] ?? $wallets->first()['id'] ?? '')),
        userQuery: '',
        recipient: null,
        recipientWalletId: '',
        searchResults: [],
        searching: false,
        amount: '',
        wallets: @js($wallets),
        csrfToken: @js(csrf_token()),

        get fromWallet() {
            return this.wallets.find(w => String(w.id) === String(this.from));
        },

        get fromBalance() {
            return Number(this.fromWallet?.balance ?? 0);
        },

        get fromLabel() {
            return this.fromWallet?.name ?? '-';
        },

        get toLabel() {
            return this.wallets.find(w => String(w.id) === String(this.to))?.name ?? '-';
        },

        get recipientWallet() {
            return this.recipient?.wallets.find(w => String(w.id) === String(this.recipientWalletId)) ?? null;
        },

        get formattedAmount() {
            return window.financeMoney.format(Number(this.amount) || 0);
        },

        toggleMode() {
            this.mode = this.mode === 'wallet' ? 'user' : 'wallet';
            this.recipient = null;
            this.searchResults = [];
        },

        async searchUsers() {
            const query = this.userQuery.trim();

            if (!query) {
                this.searchResults = [];
                this.recipient = null;
                return;
            }

            this.searching = true;

            try {
                const response = await fetch(@js(route('transfer.recipients.search')) + '?query=' + encodeURIComponent(query), {
                    headers: { 'Accept': 'application/json' },
                });

                this.searchResults = response.ok ? await response.json() : [];
            } catch (e) {
                this.searchResults = [];
            } finally {
                this.searching = false;
            }
        },

        selectUser(user) {
            const firstWallet = user.wallets[0] ?? null;

            this.recipient = {
                penerima: user.nama,
                is_banned: user.is_banned,
                wallets: user.wallets,
            };
            this.recipientWalletId = firstWallet ? String(firstWallet.id) : '';

            this.userQuery = user.nama;
            this.searchResults = [];
        },

        clearRecipient() {
            this.recipient = null;
            this.recipientWalletId = '';
            this.userQuery = '';
            this.searchResults = [];
        },

        async saveTransfer() {
            const amount = Number(this.amount);
            let targetWalletId = this.to;

            if (!amount || amount <= 0) {
                alert(@js(__('transfer.alert_amount_min')));
                return;
            }

            if (this.mode === 'wallet' && !targetWalletId) {
                alert(@js(__('transfer.alert_select_target')));
                return;
            }

            if (this.mode === 'user' && !this.recipient) {
                alert(@js(__('transfer.alert_verify_recipient')));
                return;
            }

            if (this.mode === 'user' && this.recipient?.is_banned) {
                alert(@js(__('transfer.alert_recipient_suspended')));
                return;
            }

            if (this.mode === 'user' && !this.recipientWalletId) {
                alert(@js(__('transfer.alert_select_recipient_wallet')));
                return;
            }

            if (this.mode === 'wallet' && String(this.from) === String(this.to)) {
                alert(@js(__('transfer.alert_same_wallet')));
                return;
            }

            if (amount > this.fromBalance) {
                alert(@js(__('transfer.alert_insufficient_balance')));
                return;
            }

            if (this.mode === 'user') {
                targetWalletId = this.recipientWalletId;
            }

            const payload = new URLSearchParams({
                _token: this.csrfToken,
                id_dompet_asal: String(this.from),
                id_dompet_tujuan: String(targetWalletId),
                jumlah: String(amount),
                tanggal_transfer: new Date().toLocaleDateString('en-CA'),
            });

            const response = await fetch(@js(route('transfer.store')), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload,
            });

            if (!response.ok) {
                const result = await response.json();
                alert(Object.values(result.errors ?? {}).flat()[0] ?? @js(__('transfer.alert_process_failed')));
                return;
            }

            window.location.href = '/transactions';
        }
    }"
>

    {{-- Header --}}
    <div>
        <h1 class="text-3xl font-bold">{{ __('transfer.title') }}</h1>
        <p class="mt-1 text-sm text-muted">
            {{ __('transfer.subtitle') }}
        </p>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[2fr_1.2fr]">

        {{-- FORM --}}
        <x-card class="p-8">

            {{-- Label + tombol ganti mode --}}
            <div class="flex items-center justify-between">
                <label
                    class="text-sm font-medium"
                    x-text="mode === 'wallet' ? @js(__('transfer.transfer_wallet')) : @js(__('transfer.transfer_user'))"
                ></label>

                <button
                    type="button"
                    x-on:click="toggleMode()"
                    title="Switch transfer type"
                    class="flex h-9 w-9 items-center justify-center rounded-full text-ink hover:bg-page"
                >
                    <x-icon name="arrow-right" x-show="mode === 'wallet'" />
                    <x-icon name="arrow-left" x-show="mode === 'user'" x-cloak />
                </button>
            </div>


            {{-- Dari ... ke ... --}}
            <div class="mt-2 flex items-center gap-3 rounded-xl bg-page p-2">

                {{-- Dari wallet --}}
                <div class="relative flex-1">
                    <x-icon name="wallet" size="h-4 w-4"
                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />

                    <select
                        x-model="from"
                        class="w-full appearance-none rounded-lg border border-line bg-white py-2.5 pl-9 pr-9 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    >
                        <template x-for="item in wallets" :key="item.name">
                            <option :value="item.id" :selected="String(item.id) === String(from)" x-text="item.name"></option>
                        </template>
                    </select>

                    <x-icon name="chevron-down" size="h-4 w-4"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted" />
                </div>

                {{-- Penghubung --}}
                <div class="flex flex-col items-center gap-0.5 text-xs">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full border border-ink">
                        <x-icon name="arrow-right" size="h-3 w-3" />
                    </span>
                    <span>{{ __('transfer.to') }}</span>
                </div>

                {{-- Tujuan: wallet --}}
                <div class="relative flex-1" x-show="mode === 'wallet'">
                    <x-icon name="wallet" size="h-4 w-4"
                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />

                    <select
                        x-model="to"
                        class="w-full appearance-none rounded-lg border border-line bg-white py-2.5 pl-9 pr-9 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    >
                        <template x-for="item in wallets" :key="item.name">
                            <option :value="item.id" :selected="String(item.id) === String(to)" x-text="item.name"></option>
                        </template>
                    </select>

                    <x-icon name="chevron-down" size="h-4 w-4"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted" />
                </div>

                {{-- Tujuan: user --}}
                <div class="relative flex-1" x-show="mode === 'user'" x-cloak>
                    <x-icon name="user" size="h-4 w-4"
                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-brand" />

                    <input
                        type="text"
                        x-model="userQuery"
                        x-on:input.debounce.300ms="searchUsers()"
                        placeholder="{{ __('transfer.search_placeholder') }}"
                        class="w-full rounded-full border border-line bg-white py-2.5 pl-9 pr-9 text-sm placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    >

                    <x-icon name="search" size="h-4 w-4"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted" />
                </div>
            </div>

            {{-- Search Result (live) --}}
            <div class="mt-2" x-show="mode === 'user' && searchResults.length > 0 && !recipient" x-cloak>
                <div class="overflow-hidden rounded-xl border border-line bg-white shadow-lg">
                    <template x-for="u in searchResults" :key="u.id_user">
                        <button type="button"
                                @click="u.has_wallet ? selectUser(u) : null"
                                :disabled="!u.has_wallet"
                                :title="u.has_wallet ? '' : @js(__('transfer.recipient_no_wallet'))"
                                class="flex w-full items-center gap-3 border-b border-line px-4 py-3 text-left transition last:border-0 hover:bg-page disabled:cursor-not-allowed disabled:opacity-50">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="u.is_banned ? 'bg-expense-soft text-expense' : 'bg-brand-soft text-brand'">
                                <x-icon name="user" size="h-4 w-4" />
                            </span>
                            <span class="min-w-0 flex-1 leading-tight">
                                <span class="block truncate text-sm font-semibold" x-text="u.nama"></span>
                                <span class="block truncate text-xs text-muted" x-text="u.email"></span>
                            </span>
                            <span x-show="u.is_banned" class="shrink-0 rounded-full bg-expense-soft px-2.5 py-1 text-xs font-semibold text-expense">
                                {{ __('transfer.account_banned') }}
                            </span>
                            <span x-show="!u.is_banned"
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="u.has_wallet ? 'bg-page text-muted' : 'bg-expense-soft text-expense'"
                                x-text="u.has_wallet ? u.wallets[0].name + (u.wallets.length > 1 ? ' +' + (u.wallets.length - 1) : '') : @js(__('transfer.no_wallet'))">
                            </span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Verifikasi penerima --}}
            <div class="mt-3" x-show="mode === 'user' && recipient" x-cloak>
                <div class="rounded-xl border p-4" :class="recipient?.is_banned ? 'border-red-300 bg-red-50' : 'border-brand/30 bg-brand-soft'">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full text-white" :class="recipient?.is_banned ? 'bg-expense' : 'bg-brand'">
                            <x-icon name="user" size="h-4 w-4" />
                        </span>
                        <div class="leading-tight">
                            <p class="text-sm font-semibold" x-text="recipient?.penerima"></p>
                            <p class="text-xs text-muted" x-text="recipient?.is_banned ? @js(__('transfer.recipient_suspended')) : @js(__('transfer.recipient_verified'))"></p>
                        </div>
                        <span x-show="!recipient?.is_banned" class="ml-auto rounded-full bg-income-soft px-2.5 py-1 text-xs font-semibold text-income">{{ __('transfer.verified') }}</span>
                        <span x-show="recipient?.is_banned" class="ml-auto rounded-full bg-expense-soft px-2.5 py-1 text-xs font-semibold text-expense">{{ __('transfer.banned') }}</span>
                        <button type="button" @click="clearRecipient()" title="{{ __('transfer.change_recipient') }}"
                                class="flex h-7 w-7 items-center justify-center rounded-md text-muted hover:bg-white">
                            <x-icon name="x" size="h-3.5 w-3.5" />
                        </button>
                    </div>

                    {{-- Warning jika akun dibanned --}}
                    <div x-show="recipient?.is_banned" class="mt-3 rounded-lg border border-red-200 bg-white p-3 text-xs text-red-700">
                        <p class="font-bold">{{ __('transfer.suspended_warning_title') }}</p>
                        <p class="mt-0.5">{{ __('transfer.suspended_warning_body') }}</p>
                    </div>

                    {{-- Pilih dompet penerima --}}
                    <div x-show="!recipient?.is_banned" class="mt-3 flex items-center gap-2">
                        <label class="shrink-0 text-xs font-medium text-muted">{{ __('transfer.target_wallet') }}</label>
                        <div class="relative flex-1">
                            <x-icon name="wallet" size="h-4 w-4"
                                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-brand" />

                            <select
                                x-model="recipientWalletId"
                                class="w-full appearance-none rounded-lg border border-line bg-white py-2 pl-9 pr-9 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                            >
                                <template x-for="w in recipient?.wallets ?? []" :key="w.id">
                                    <option :value="String(w.id)" x-text="w.name"></option>
                                </template>
                            </select>

                            <x-icon name="chevron-down" size="h-4 w-4"
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- Amount --}}
            <div class="mt-6">
                <label for="amount" class="mb-2 block text-sm font-medium">{{ __('transfer.amount') }}</label>

                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-muted" x-text="window.financeMoney.symbol"></span>

                    <input
                        id="amount"
                        type="number"
                        min="0"
                        x-model.number="amount"
                        class="w-full rounded-lg border border-line bg-white py-3 pl-12 pr-4 text-2xl font-semibold focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    >
                </div>
            </div>


            {{-- Category (tetap) --}}
            <div class="mt-6">
                <label class="mb-2 block text-sm font-medium">{{ __('transfer.category') }}</label>

                <div class="flex w-56 items-center gap-2 rounded-lg border border-line bg-white px-3 py-2.5 text-sm">
                    <x-icon name="wallet" size="h-4 w-4" class="text-muted" />
                    {{ __('transfer.transfer') }}
                </div>
            </div>


            {{-- Tombol --}}
            <div class="mt-6 flex justify-end gap-3">
                <x-button href="/transactions" variant="outline">{{ __('transfer.cancel') }}</x-button>
                <x-button x-on:click="saveTransfer()">{{ __('transfer.transfer') }}</x-button>
            </div>

        </x-card>


        {{-- SUMMARY --}}
        <div>
            <x-card class="p-6">

                <h2 class="text-lg font-semibold">{{ __('transfer.summary') }}</h2>

                <div class="mt-5 space-y-4 text-sm">

                    <div class="flex items-center justify-between">
                        <span class="text-muted">{{ __('transfer.from_wallet') }}</span>
                        <span class="rounded-full bg-expense-soft px-3 py-1 text-xs font-medium" x-text="fromLabel"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted" x-text="mode === 'wallet' ? @js(__('transfer.to_wallet')) : @js(__('transfer.to_user'))"></span>
                        <span
                            class="rounded-full bg-expense-soft px-3 py-1 text-xs font-medium"
                            x-text="mode === 'wallet' ? toLabel : (recipient ? recipient.penerima : (userQuery || '-'))"
                        ></span>
                    </div>

                    <div class="flex items-center justify-between" x-show="mode === 'user' && recipient" x-cloak>
                        <span class="text-muted">{{ __('transfer.to_wallet') }}</span>
                        <span
                            class="rounded-full bg-page px-3 py-1 text-xs font-medium"
                            x-text="recipientWallet?.name ?? '-'"
                        ></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted">{{ __('transfer.amount') }}</span>
                        <span class="text-base font-bold" x-text="formattedAmount"></span>
                    </div>

                </div>

            </x-card>
        </div>

    </div>

</div>

@endsection