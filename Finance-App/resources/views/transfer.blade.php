@extends('layouts.main')

@section('title', 'Transfer')

@section('content')

<div x-data="transferForm">

    {{-- Objek Alpine dipindah ke blok <script> di bawah. Selama objeknya berada di
         dalam atribut HTML, kutip ganda apa pun di dalamnya (string, komentar,
         querySelector) akan memutus atribut dan menumpahkan JS sebagai teks. --}}
<script>
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('transferForm', () => ({
        mode: 'wallet',
        from: @js((string) ($wallets->first()['id'] ?? '')),
        to: @js((string) ($wallets->count() > 1 ? ($wallets->skip(1)->first()['id'] ?? '') : '')),
        userQuery: '',
        recipient: null,
        recipientWalletId: '',
        searchResults: [],
        searching: false,
        saving: false,
        amount: '',
        amountDisplay: '',
        pendingIdempotencyKeys: {},
        wallets: @js($wallets),
        csrfToken: @js(csrf_token()),

        get fromWallet() {
            return this.wallets.find(w => String(w.id) === String(this.from));
        },

        get fromBalance() {
            return this.fromWallet?.balance ?? '0';
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
            // this.amount adalah input user dalam mata uang aktif
            return window.financeMoney.formatNative(this.amount || '0');
        },

        formatAmount(value) {
            // Parser dibagikan lewat window.financeMoney.formatTyped (app.js),
            // satu-satunya sumber aturan pemisah ribuan/desimal lintas halaman.
            const result = window.financeMoney.formatTyped(value);

            this.amount = result.value;
            this.amountDisplay = result.display;
        },

        idempotencyFor(fingerprint) {
            const storageKey = 'finance.transfer.idempotency.' + encodeURIComponent(fingerprint);

            try {
                let key = sessionStorage.getItem(storageKey);

                if (!key) {
                    key = (window.crypto && window.crypto.randomUUID)
                        ? window.crypto.randomUUID()
                        : ('tx_' + Date.now() + '_' + Math.random().toString(36).substring(2));
                    sessionStorage.setItem(storageKey, key);
                }

                this.pendingIdempotencyKeys[fingerprint] = key;
                return { key, storageKey };
            } catch (error) {
                if (this.pendingIdempotencyKeys[fingerprint]) {
                    return {
                        key: this.pendingIdempotencyKeys[fingerprint],
                        storageKey: null,
                    };
                }

                throw new Error('Browser storage is unavailable; the transfer was not sent.');
            }
        },

        clearIdempotency(fingerprint, key, storageKey) {
            if (storageKey && sessionStorage.getItem(storageKey) === key) {
                sessionStorage.removeItem(storageKey);
            }

            delete this.pendingIdempotencyKeys[fingerprint];
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
            if (this.saving) return;

            const amount = this.amount;
            const amountInBase = window.financeMoney.toBase(amount);
            let targetWalletId = this.to;

            if (window.financeMoney.compare(amount, '0.00') <= 0) {
                window.financeAlert({
                    title: 'Invalid Amount',
                    message: 'Amount must be greater than 0.',
                    variant: 'danger',
                });
                return;
            }

            if (this.mode === 'wallet' && !targetWalletId) {
                window.financeAlert({
                    title: 'Destination Required',
                    message: 'Please select a destination wallet.',
                    variant: 'danger',
                });
                return;
            }

            if (this.mode === 'user' && !this.recipient) {
                window.financeAlert({
                    title: 'Recipient Required',
                    message: 'Please search and verify the recipient first.',
                    variant: 'danger',
                });
                return;
            }

            if (this.mode === 'user' && this.recipient?.is_banned) {
                window.financeAlert({
                    title: 'Recipient Suspended',
                    message: 'Recipient account is currently suspended and cannot receive transfers.',
                    variant: 'danger',
                });
                return;
            }

            if (this.mode === 'user' && !this.recipientWalletId) {
                window.financeAlert({
                    title: 'Recipient Wallet Required',
                    message: 'Please select the recipient destination wallet.',
                    variant: 'danger',
                });
                return;
            }

            if (this.mode === 'wallet' && String(this.from) === String(this.to)) {
                window.financeAlert({
                    title: 'Same Wallet Selected',
                    message: 'Source and destination wallets cannot be the same.',
                    variant: 'danger',
                });
                return;
            }

            if (window.financeMoney.compare(amountInBase, this.fromBalance) > 0) {
                window.financeAlert({
                    title: 'Insufficient Balance',
                    message: 'Insufficient wallet balance for this transfer.',
                    variant: 'danger',
                });
                return;
            }

            if (this.mode === 'user') {
                targetWalletId = this.recipientWalletId;
            }

            const transferDate = new Date().toLocaleDateString('en-CA');
            const fingerprint = JSON.stringify([
                String(this.from),
                String(targetWalletId),
                String(amount),
            ]);
            let idempotency;

            try {
                idempotency = this.idempotencyFor(fingerprint);
            } catch (error) {
                window.financeAlert({
                    title: 'Transfer Not Sent',
                    message: error.message,
                    variant: 'danger',
                });
                return;
            }

            const payload = new URLSearchParams({
                _token: this.csrfToken,
                id_dompet_asal: String(this.from),
                id_dompet_tujuan: String(targetWalletId),
                jumlah: amount,
                tanggal_transfer: transferDate,
                idempotency_key: idempotency.key,
            });

            this.saving = true;

            try {
                const response = await fetch(@js(route('transfer.store')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: payload,
                });

                if (!response.ok) {
                    let result = {};
                    let errorMessage = 'Transfer could not be processed. Please try again.';

                    try {
                        result = await response.json();
                        const firstError = Object.values(result.errors ?? {}).flat()[0]
                            ?? result.message
                            ?? null;
                        if (firstError) errorMessage = firstError;
                    } catch (e) {
                        // Respons server bukan JSON (mis. 500 server error).
                        if (response.status === 422) {
                            errorMessage = 'The transfer data is invalid. Please check the amount and wallets.';
                        } else if (response.status === 403) {
                            errorMessage = 'You are not authorized to perform this transfer.';
                        } else if (response.status === 500) {
                            errorMessage = 'A server error occurred. Please try again later.';
                        }
                    }

                    if (
                        response.status === 422
                        && errorMessage.includes('This transfer was already completed')
                    ) {
                        this.clearIdempotency(fingerprint, idempotency.key, idempotency.storageKey);
                        window.location.href = '/transactions';
                        return;
                    }

                    window.financeAlert({
                        title: 'Transfer Failed',
                        message: errorMessage,
                        variant: 'danger',
                        icon: 'transfer',
                    });

                    return;
                }

                this.clearIdempotency(fingerprint, idempotency.key, idempotency.storageKey);
                window.location.href = '/transactions';
            } catch (e) {
                window.financeAlert({
                    title: 'Connection Error',
                    message: 'Connection problem. We could not confirm whether the transfer completed. Retry with the same details; the request key will prevent a duplicate.',
                    variant: 'danger',
                });
            } finally {
                this.saving = false;
            }
                    },
                    }));
                });
</script>

    {{-- Header --}}
    <div>
        <h1 class="text-3xl font-bold">Transfer Wallet &amp; User</h1>
        <p class="mt-1 text-sm text-muted">
            Transfer from a specific wallet to another wallet or user.
        </p>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[2fr_1.2fr]">

        {{-- FORM --}}
        <x-card class="p-4 sm:p-8">

            {{-- Label + tombol ganti mode --}}
            <div class="flex items-center justify-between">
                <label
                    class="text-sm font-medium"
                    x-text="mode === 'wallet' ? 'Transfer Wallet' : 'Transfer User'"
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
            <div class="mt-2 flex min-w-0 flex-col gap-3 rounded-xl bg-page p-2 sm:flex-row sm:items-center">

                {{-- Dari wallet --}}
                <div
                    class="relative flex-1 min-w-0"
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
                        type="button"
                        @click="toggle($el)"
                        class="flex w-full items-center justify-between rounded-lg border border-line bg-white py-2.5 pl-9 pr-3 text-sm text-left shadow-sm transition hover:border-brand/40 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 cursor-pointer"
                        :class="open ? 'border-brand ring-2 ring-brand/20' : ''"
                    >
                        <x-icon name="wallet" size="h-4 w-4"
                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                        <span class="truncate font-medium text-ink" x-text="fromLabel"></span>
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
                                    @click="from = item.id; open = false"
                                    class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-sm transition text-left cursor-pointer"
                                    :class="String(from) === String(item.id) ? 'bg-brand-soft text-brand font-semibold' : 'text-ink hover:bg-page font-medium'"
                                >
                                    <span x-text="item.name" class="truncate"></span>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <span class="text-xs text-muted" x-text="window.financeMoney.format(item.balance)"></span>
                                        <x-icon x-show="String(from) === String(item.id)" name="check" size="h-4 w-4" class="text-brand" />
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>

                    <select x-model="from" class="sr-only" tabindex="-1" aria-hidden="true">
                        <template x-for="item in wallets" :key="item.name">
                            <option :value="item.id" :selected="String(item.id) === String(from)" x-text="item.name"></option>
                        </template>
                    </select>
                </div>

                {{-- Penghubung --}}
                <div class="flex flex-row items-center justify-center gap-0.5 text-xs sm:flex-col">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full border border-ink">
                        <x-icon name="arrow-right" size="h-3 w-3" class="rotate-90 sm:rotate-0" />
                    </span>
                    <span>to</span>
                </div>

                {{-- Tujuan: wallet --}}
                <div
                    class="relative flex-1 min-w-0"
                    x-show="mode === 'wallet'"
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
                        type="button"
                        @click="toggle($el)"
                        class="flex w-full items-center justify-between rounded-lg border border-line bg-white py-2.5 pl-9 pr-3 text-sm text-left shadow-sm transition hover:border-brand/40 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 cursor-pointer"
                        :class="open ? 'border-brand ring-2 ring-brand/20' : ''"
                    >
                        <x-icon name="wallet" size="h-4 w-4"
                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                        <span class="truncate font-medium text-ink" x-text="wallets.find(w => String(w.id) === String(to))?.name ?? 'Select wallet'"></span>
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
                                    @click="to = item.id; open = false"
                                    class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-sm transition text-left cursor-pointer"
                                    :class="String(to) === String(item.id) ? 'bg-brand-soft text-brand font-semibold' : 'text-ink hover:bg-page font-medium'"
                                >
                                    <span x-text="item.name" class="truncate"></span>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <span class="text-xs text-muted" x-text="window.financeMoney.format(item.balance)"></span>
                                        <x-icon x-show="String(to) === String(item.id)" name="check" size="h-4 w-4" class="text-brand" />
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>

                    <select x-model="to" class="sr-only" tabindex="-1" aria-hidden="true">
                        <template x-for="item in wallets" :key="item.name">
                            <option :value="item.id" :selected="String(item.id) === String(to)" x-text="item.name"></option>
                        </template>
                    </select>
                </div>

                {{-- Tujuan: user --}}
                <div class="relative min-w-0 flex-1" x-show="mode === 'user'" x-cloak>
                    <x-icon name="user" size="h-4 w-4"
                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-brand" />

                    <input
                        type="text"
                        x-model="userQuery"
                        x-on:input.debounce.300ms="searchUsers()"
                        placeholder="Search username or email"
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
                                :title="u.has_wallet ? '' : (u.has_any_wallet ? u.nama + ' belum mengaktifkan dompet untuk menerima transfer.' : 'The recipient does not yet have a wallet.')"
                                class="flex w-full items-center gap-3 border-b border-line px-4 py-3 text-left transition last:border-0 hover:bg-page disabled:cursor-not-allowed disabled:opacity-50">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="u.is_banned ? 'bg-expense-soft text-expense' : 'bg-brand-soft text-brand'">
                                <x-icon name="user" size="h-4 w-4" />
                            </span>
                            <span class="min-w-0 flex-1 leading-tight">
                                <span class="block truncate text-sm font-semibold" x-text="u.nama"></span>
                                <span class="block truncate text-xs text-muted" x-text="u.email"></span>
                            </span>
                            <span x-show="u.is_banned" class="shrink-0 rounded-full bg-expense-soft px-2.5 py-1 text-xs font-semibold text-expense">
                                Banned Account
                            </span>
                            <span x-show="!u.is_banned"
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="u.has_wallet ? 'bg-page text-muted' : 'bg-expense-soft text-expense'"
                                x-text="u.has_wallet ? u.wallets[0].name + (u.wallets.length > 1 ? ' +' + (u.wallets.length - 1) : '') : (u.has_any_wallet ? 'Inactive a wallet' : 'Don\'t have a wallet')">
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
                            <p class="text-xs text-muted" x-text="recipient?.is_banned ? 'The recipient is currently blocked.' : 'Recipient found & verified'"></p>
                        </div>
                        <span x-show="!recipient?.is_banned" class="ml-auto rounded-full bg-income-soft px-2.5 py-1 text-xs font-semibold text-income">Verified</span>
                        <span x-show="recipient?.is_banned" class="ml-auto rounded-full bg-expense-soft px-2.5 py-1 text-xs font-semibold text-expense">Banned</span>
                        <button type="button" @click="clearRecipient()" title="Ganti penerima"
                                class="flex h-7 w-7 items-center justify-center rounded-md text-muted hover:bg-white">
                            <x-icon name="x" size="h-3.5 w-3.5" />
                        </button>
                    </div>

                    {{-- Warning jika akun dibanned --}}
                    <div x-show="recipient?.is_banned" class="mt-3 rounded-lg border border-red-200 bg-white p-3 text-xs text-red-700">
                        <p class="font-bold">Peringatan: Akun Terblokir</p>
                        <p class="mt-0.5">Penerima tidak dapat menerima transfer karena akun sedang diblokir oleh admin.</p>
                    </div>

                    {{-- Pilih dompet penerima --}}
                    <div x-show="!recipient?.is_banned" class="mt-3 flex flex-col sm:flex-row sm:items-center gap-2">
                        <label class="shrink-0 text-xs font-medium text-muted sm:w-28">Destination wallet</label>
                        <div
                            class="relative w-full sm:flex-1 min-w-0"
                            x-data="{
                                open: false,
                                dropUp: false,
                                toggle(el) {
                                    this.open = !this.open;
                                    if (this.open) {
                                        const rect = el.getBoundingClientRect();
                                        this.dropUp = (window.innerHeight - rect.bottom) < 220;
                                    }
                                }
                            }"
                            @click.outside="open = false"
                            @keydown.escape.window="open = false"
                        >
                            <button
                                type="button"
                                @click="toggle($el)"
                                class="flex w-full items-center justify-between rounded-lg border border-line bg-white py-2 pl-9 pr-3 text-sm text-left shadow-sm transition hover:border-brand/40 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 cursor-pointer"
                                :class="open ? 'border-brand ring-2 ring-brand/20' : ''"
                            >
                                <x-icon name="wallet" size="h-4 w-4"
                                    class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-brand" />
                                <span class="truncate font-medium text-ink" x-text="recipient?.wallets?.find(w => String(w.id) === String(recipientWalletId))?.name ?? 'Select destination wallet'"></span>
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
                                    <template x-for="w in recipient?.wallets ?? []" :key="w.id">
                                        <button
                                            type="button"
                                            @click="recipientWalletId = String(w.id); open = false"
                                            class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-sm transition text-left cursor-pointer"
                                            :class="String(recipientWalletId) === String(w.id) ? 'bg-brand-soft text-brand font-semibold' : 'text-ink hover:bg-page font-medium'"
                                        >
                                            <span x-text="w.name" class="truncate font-medium"></span>
                                            <div class="flex items-center gap-1.5 shrink-0">
                                                <x-icon x-show="String(recipientWalletId) === String(w.id)" name="check" size="h-4 w-4" class="text-brand" />
                                            </div>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <select x-model="recipientWalletId" class="sr-only" tabindex="-1" aria-hidden="true">
                                <template x-for="w in recipient?.wallets ?? []" :key="w.id">
                                    <option :value="String(w.id)" :selected="String(w.id) === String(recipientWalletId)" x-text="w.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Amount --}}
            <div class="mt-6">
                <div class="mb-2 flex items-center justify-between">
                    <label for="amount" class="block text-sm font-medium">Amount</label>
                    <span class="text-xs text-muted">
                        Balance: <span class="font-semibold" x-text="window.financeMoney.format(fromBalance)"></span>
                    </span>
                </div>

                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-base sm:text-lg font-semibold text-muted" x-text="window.financeMoney.symbol"></span>

                    <input
                        id="amount"
                        type="text"
                        inputmode="decimal"
                        x-model="amountDisplay"
                        x-on:input="formatAmount($event.target.value)"
                        x-on:focus="$event.target.select()"
                        class="w-full rounded-lg border border-line bg-white py-3 pl-12 pr-4 text-xl sm:text-2xl font-semibold focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                        placeholder="0"
                    >
                </div>
            </div>


            {{-- Category (tetap) --}}
            <div class="mt-6">
                <label class="mb-2 block text-sm font-medium">Category</label>

                <div class="flex w-56 items-center gap-2 rounded-lg border border-line bg-white px-3 py-2.5 text-sm">
                    <x-icon name="wallet" size="h-4 w-4" class="text-muted" />
                    Transfer
                </div>
            </div>


            {{-- Tombol --}}
            <div class="mt-6 flex justify-end gap-3">
                <x-button href="/transactions" variant="outline">Cancel</x-button>
                <x-button x-on:click="saveTransfer()" x-bind:disabled="saving">
                    <span x-text="saving ? 'Memproses...' : 'Transfer'"></span>
                </x-button>
            </div>

        </x-card>


        {{-- SUMMARY --}}
        <div>
            <x-card class="p-4 sm:p-6">

                <h2 class="text-lg font-semibold">Summary</h2>

                <div class="mt-5 space-y-4 text-sm">

                    <div class="flex items-center justify-between">
                        <span class="text-muted">From Wallet</span>
                        <span class="rounded-full bg-expense-soft px-3 py-1 text-xs font-medium" x-text="fromLabel"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted" x-text="mode === 'wallet' ? 'To Wallet' : 'To User'"></span>
                        <span
                            class="rounded-full bg-expense-soft px-3 py-1 text-xs font-medium"
                            x-text="mode === 'wallet' ? toLabel : (recipient ? recipient.penerima : (userQuery || '-'))"
                        ></span>
                    </div>

                    <div class="flex items-center justify-between" x-show="mode === 'user' && recipient" x-cloak>
                        <span class="text-muted">To Wallet</span>
                        <span
                            class="rounded-full bg-page px-3 py-1 text-xs font-medium"
                            x-text="recipientWallet?.name ?? '-'"
                        ></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted">Amount</span>
                        <span class="text-base font-bold" x-text="formattedAmount"></span>
                    </div>

                </div>

            </x-card>
        </div>

    </div>

</div>

@endsection