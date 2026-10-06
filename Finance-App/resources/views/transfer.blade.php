@extends('layouts.main')

@section('title', 'Transfer')

@section('content')

<div
    x-data="{
        mode: 'wallet',
        from: @js((string) ($wallets->first()['id'] ?? '')),
        to: @js((string) ($wallets->skip(1)->first()['id'] ?? $wallets->first()['id'] ?? '')),
        userQuery: '',
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

        get formattedAmount() {
            return window.financeMoney.format(Number(this.amount) || 0);
        },

        toggleMode() {
            this.mode = this.mode === 'wallet' ? 'user' : 'wallet';
        },

        async saveTransfer() {
            const amount = Number(this.amount);
            let targetWalletId = this.to;

            if (!amount || amount <= 0) {
                alert('Amount harus lebih dari 0.');
                return;
            }

            if (this.mode === 'wallet' && !targetWalletId) {
                alert('Pilih tujuan transfer.');
                return;
            }

            if (this.mode === 'user' && !this.userQuery.trim()) {
                alert('Pilih tujuan transfer.');
                return;
            }

            if (this.mode === 'wallet' && String(this.from) === String(this.to)) {
                alert('Wallet asal dan tujuan tidak boleh sama.');
                return;
            }

            if (amount > this.fromBalance) {
                alert('Saldo wallet tidak cukup.');
                return;
            }

            if (this.mode === 'user') {
                const recipientResponse = await fetch(@js(route('transfer.recipients')) + '?query=' + encodeURIComponent(this.userQuery.trim()), {
                    headers: { 'Accept': 'application/json' },
                });

                if (!recipientResponse.ok) {
                    const result = await recipientResponse.json();
                    alert(result.message ?? 'User tidak ditemukan.');
                    return;
                }

                targetWalletId = (await recipientResponse.json()).id_dompet_tujuan;
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
                alert(Object.values(result.errors ?? {}).flat()[0] ?? 'Transfer tidak dapat diproses.');
                return;
            }

            window.location.href = '/transactions';
        }
    }"
>

    {{-- Header --}}
    <div>
        <h1 class="text-3xl font-bold">Transfer Wallet &amp; User</h1>
        <p class="mt-1 text-sm text-muted">
            Transfer from a specific wallet to another wallet or user.
        </p>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[2fr_1.2fr]">

        {{-- FORM --}}
        <x-card class="p-8">

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
                    <span>to</span>
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
                        placeholder="Find user/ID user..."
                        class="w-full rounded-full border border-line bg-white py-2.5 pl-9 pr-9 text-sm placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    >

                    <x-icon name="search" size="h-4 w-4"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted" />
                </div>

            </div>


            {{-- Amount --}}
            <div class="mt-6">
                <label for="amount" class="mb-2 block text-sm font-medium">Amount</label>

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
                <label class="mb-2 block text-sm font-medium">Category</label>

                <div class="flex w-56 items-center gap-2 rounded-lg border border-line bg-white px-3 py-2.5 text-sm">
                    <x-icon name="wallet" size="h-4 w-4" class="text-muted" />
                    Transfer
                </div>
            </div>


            {{-- Tombol --}}
            <div class="mt-6 flex justify-end gap-3">
                <x-button href="/transactions" variant="outline">Cancel</x-button>
                <x-button x-on:click="saveTransfer()">Transfer</x-button>
            </div>

        </x-card>


        {{-- SUMMARY --}}
        <div>
            <x-card class="p-6">

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
                            x-text="mode === 'wallet' ? toLabel : (userQuery || '-')"
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