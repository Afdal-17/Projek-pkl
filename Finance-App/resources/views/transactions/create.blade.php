@extends('layouts.main')

@section('title', __('transactions.add_title'))

@section('content')

<div
    x-data="{
        type: 'expense',
        amount: '',
        amountDisplay: '',
        transactionName: 'Grocery shopping',
        category: '',
        wallet: @js((string) ($wallets->first()['id'] ?? '')),
        date: new Date().toLocaleDateString('en-CA'),
        error: '',
        submitting: false,

        /* Kategori dibaca dari data yang sama dengan halaman Manage Kategori */
        allCategories: @js($categories),
        wallets: @js($wallets),
        csrfToken: @js(csrf_token()),

        init() {
            this.pickCategory();

            this.$watch('type', () => this.pickCategory());
        },

        formatAmount(value) {
            const result = window.financeMoney.formatTyped(value);
            this.amount = result.value;
            this.amountDisplay = result.display;
        },

        /* Hanya kategori yang sesuai tipe transaksi (Income / Expense) */
        get categories() {
            return this.allCategories.filter(c => c.type === this.type);
        },

        pickCategory() {
            const first = this.categories[0];

            this.category = first ? first.name : '';
        },

        get selectedWallet() {
            return this.wallets.find(w => String(w.id) === String(this.wallet)) ?? { balance: 0 };
        },

        get amountInBase() {
            // Input user dalam mata uang aktif, dikonversi ke IDR base
            return window.financeMoney.toBase(this.amount || '0');
        },

        get newBalance() {
            const amount = this.amountInBase;

            return this.type === 'income'
                ? window.financeMoney.add(this.selectedWallet.balance, amount)
                : window.financeMoney.subtract(this.selectedWallet.balance, amount);
        },

        get formattedBalance() {
            return window.financeMoney.format(this.newBalance);
        },

        async saveTransaction() {
            if (this.submitting) return;

            this.error = '';

            if (!this.amount || window.financeMoney.compare(this.amount, '0') <= 0) {
                this.error = 'Amount must be greater than 0.';
                return;
            }

            if (!this.transactionName.trim()) {
                this.error = 'Transaction name is required.';
                return;
            }

            if (!this.category) {
                this.error = 'Choose a category first. You can add one in Manage Category.';
                return;
            }

            /* Sama seperti aturan backend: saldo dompet tidak boleh minus */
            if (this.type === 'expense' && window.financeMoney.compare(this.newBalance, '0') < 0) {
                this.error = 'Wallet balance is not enough.';
                return;
            }

            const selectedCategory = this.allCategories.find(item => item.name === this.category);
            const selectedWallet = this.wallets.find(item => String(item.id) === String(this.wallet));

            if (!selectedCategory || !selectedWallet) {
                this.error = 'Choose a valid category and wallet.';
                return;
            }

            const payload = new URLSearchParams({
                _token: this.csrfToken,
                id_dompet: String(selectedWallet.id),
                id_kategori: String(selectedCategory.id),
                nama_transaksi: this.transactionName.trim(),
                jumlah: String(this.amount),
                jenis: this.type === 'income' ? 'pemasukan' : 'pengeluaran',
                tanggal: this.date,
            });

            this.submitting = true;

            try {
                const response = await fetch(@js(route('transactions.store')), {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: payload,
                });

                if (!response.ok) {
                    const result = await response.json();
                    this.error = Object.values(result.errors ?? {}).flat()[0] ?? 'Transaction could not be saved.';
                    return;
                }

                window.location.href = '/transactions';
            } catch (e) {
                this.error = 'Connection failed — the transaction was not saved. Please try again.';
            } finally {
                this.submitting = false;
            }
        }
    }"
>

    {{-- Page Header --}}
    <div>
        <h1 class="text-3xl font-bold">{{ __('transactions.add_title') }}</h1>

        <p class="mt-1 text-sm text-muted">
            {{ __('transactions.add_subtitle') }}
        </p>
    </div>


    {{-- Main Content --}}
    <div class="mt-8 grid gap-6 lg:grid-cols-[2fr_1.2fr]">


        {{-- FORM TRANSACTION --}}
        <x-card class="p-8">

            {{-- Transaction Type --}}
            <div>
                <label class="mb-2 block text-sm font-medium">
                    {{ __('transactions.type') }}
                </label>

                <div class="grid grid-cols-2 gap-2 rounded-lg bg-page p-1">

                    {{-- Income --}}
                    <button
                        type="button"
                        x-on:click="type = 'income'"
                        class="flex h-10 items-center justify-center gap-2 rounded-lg text-sm font-medium transition"
                        :class="type === 'income'
                            ? 'bg-white text-income shadow-sm border border-line'
                            : 'text-income'"
                    >
                        <x-icon name="plus" size="h-4 w-4" />
                        {{ __('transactions.income') }}
                    </button>

                    {{-- Expense --}}
                    <button
                        type="button"
                        x-on:click="type = 'expense'"
                        class="flex h-10 items-center justify-center gap-2 rounded-lg text-sm font-medium transition"
                        :class="type === 'expense'
                            ? 'bg-white text-expense shadow-sm border border-line'
                            : 'text-expense'"
                    >
                        <x-icon name="minus" size="h-4 w-4" />
                        {{ __('transactions.expense') }}
                    </button>

                </div>
            </div>


            {{-- Amount --}}
            <div class="mt-6">

                <label for="amount" class="mb-2 block text-sm font-medium">
                    {{ __('transactions.amount') }}
                </label>

                <div class="relative">

                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-muted">
                        <span x-text="window.financeMoney.symbol"></span>
                    </span>

                    <input
                        id="amount"
                        type="text"
                        x-model="amountDisplay"
                        x-on:input="formatAmount($event.target.value)"
                        x-on:focus="$event.target.select()"
                        placeholder="0"
                        class="w-full rounded-lg border border-line bg-white py-3 pl-12 pr-4 text-2xl font-semibold focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    >

                </div>
            </div>


            {{-- Transaction Name --}}
            <div class="mt-6">

                <label for="transaction_name" class="mb-2 block text-sm font-medium">
                    {{ __('transactions.transaction_name') }}
                </label>

                <input
                    id="transaction_name"
                    type="text"
                    x-model="transactionName"
                    class="w-full rounded-lg border border-line bg-white px-3 py-3 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                >

            </div>


            {{-- Category + Wallet --}}
            <div class="mt-6 grid gap-4 md:grid-cols-2">

                {{-- Category --}}
                <div>

                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-sm font-medium">
                            {{ __('transactions.category') }}
                        </label>

                        <a href="/transactions/categories" class="text-xs font-medium text-brand hover:underline">
                            {{ __('transactions.manage') }}
                        </a>
                    </div>

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
                        {{-- Trigger --}}
                        <button
                            id="category"
                            type="button"
                            @click="toggle($el)"
                            class="flex w-full items-center justify-between rounded-lg border border-line bg-white px-3.5 py-3 text-sm font-medium text-ink shadow-sm transition hover:border-brand/40 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 cursor-pointer"
                            :class="open ? 'border-brand ring-2 ring-brand/20' : ''"
                        >
                            <span x-text="category || 'Select category'" class="truncate"></span>
                            <x-icon
                                name="chevron-down"
                                size="h-4 w-4"
                                class="text-muted shrink-0 transition-transform duration-200"
                                x-bind:class="open ? 'rotate-180' : ''"
                            />
                        </button>

                        {{-- Dropdown Popover --}}
                        <div
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute left-0 right-0 z-50 max-h-60 overflow-y-auto rounded-2xl border border-line bg-white p-1.5 shadow-2xl ring-1 ring-black/5"
                            :class="dropUp ? 'bottom-full mb-2 origin-bottom' : 'top-full mt-2 origin-top'"
                        >
                            <template x-if="categories.length === 0">
                                <div class="px-3 py-2.5 text-xs text-muted text-center">
                                    {{ __('transactions.no_category') }}
                                </div>
                            </template>

                            <div class="space-y-0.5">
                                <template x-for="item in categories" :key="item.id">
                                    <button
                                        type="button"
                                        @click="category = item.name; open = false"
                                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm transition text-left cursor-pointer"
                                        :class="category === item.name ? 'bg-brand-soft text-brand font-semibold' : 'text-ink hover:bg-page font-medium'"
                                    >
                                        <span x-text="item.name" class="truncate"></span>
                                        <x-icon
                                            x-show="category === item.name"
                                            name="check"
                                            size="h-4 w-4"
                                            class="text-brand shrink-0"
                                        />
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Hidden Native Select --}}
                        <select id="category_native" x-model="category" class="sr-only" tabindex="-1" aria-hidden="true">
                            <template x-for="item in categories" :key="item.id">
                                <option :value="item.name" :selected="item.name === category" x-text="item.name"></option>
                            </template>
                        </select>
                    </div>

                </div>


                {{-- Wallet --}}
                <div>

                    <label class="mb-2 block text-sm font-medium">
                        {{ __('transactions.wallet') }}
                    </label>

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
                        {{-- Trigger --}}
                        <button
                            id="wallet"
                            type="button"
                            @click="toggle($el)"
                            class="flex w-full items-center justify-between rounded-lg border border-line bg-white px-3.5 py-3 text-sm font-medium text-ink shadow-sm transition hover:border-brand/40 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 cursor-pointer"
                            :class="open ? 'border-brand ring-2 ring-brand/20' : ''"
                        >
                            <div class="flex items-center gap-2.5 truncate min-w-0">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-brand-soft text-brand">
                                    <x-icon name="wallet" size="h-3.5 w-3.5" />
                                </span>
                                <span x-text="selectedWallet.name || 'Select wallet'" class="truncate"></span>
                            </div>
                            <x-icon
                                name="chevron-down"
                                size="h-4 w-4"
                                class="text-muted shrink-0 transition-transform duration-200"
                                x-bind:class="open ? 'rotate-180' : ''"
                            />
                        </button>

                        {{-- Dropdown Popover --}}
                        <div
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute left-0 right-0 z-50 max-h-60 overflow-y-auto rounded-2xl border border-line bg-white p-1.5 shadow-2xl ring-1 ring-black/5"
                            :class="dropUp ? 'bottom-full mb-2 origin-bottom' : 'top-full mt-2 origin-top'"
                        >
                            <div class="space-y-0.5">
                                <template x-for="item in wallets" :key="item.id">
                                    <button
                                        type="button"
                                        @click="wallet = String(item.id); open = false"
                                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm transition text-left cursor-pointer"
                                        :class="String(wallet) === String(item.id) ? 'bg-brand-soft text-brand font-semibold' : 'text-ink hover:bg-page font-medium'"
                                    >
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-page text-muted">
                                                <x-icon name="wallet" size="h-3.5 w-3.5" />
                                            </span>
                                            <span x-text="item.name" class="truncate font-medium"></span>
                                        </div>

                                        <div class="flex items-center gap-2 shrink-0">
                                            <span class="text-xs font-semibold text-muted" x-text="window.financeMoney.format(item.balance)"></span>
                                            <x-icon
                                                x-show="String(wallet) === String(item.id)"
                                                name="check"
                                                size="h-4 w-4"
                                                class="text-brand"
                                            />
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Hidden Native Select --}}
                        <select id="wallet_native" x-model="wallet" class="sr-only" tabindex="-1" aria-hidden="true">
                            <template x-for="item in wallets" :key="item.id">
                                <option :value="String(item.id)" :selected="String(item.id) === String(wallet)" x-text="item.name"></option>
                            </template>
                        </select>
                    </div>

                </div>

            </div>


            {{-- Date --}}
            <div class="mt-6">
                <x-date-picker
                    id="date"
                    name="date"
                    :label="__('transactions.date')"
                    model="date"
                    :required="true"
                />
            </div>


            {{-- Error --}}
            <p x-cloak x-show="error" x-text="error" class="mt-4 text-sm text-expense"></p>


            {{-- Buttons --}}
            <div class="mt-6 flex justify-end gap-3">

                <x-button href="/transactions" variant="outline">
                    {{ __('transactions.cancel') }}
                </x-button>

                <x-button type="button" x-on:click="saveTransaction()" x-bind:disabled="submitting">
                    <span x-text="submitting ? 'Saving...' : (type === 'income' ? @js(__('transactions.add_income')) : @js(__('transactions.add_expense')))"></span>
                </x-button>

            </div>

        </x-card>


        {{-- RIGHT COLUMN: hanya Summary --}}
        <div class="space-y-6">

            <x-card class="p-6">

                <h2 class="text-lg font-semibold">{{ __('transactions.summary') }}</h2>

                <div class="mt-5 space-y-5 text-sm">

                    {{-- Type --}}
                    <div class="flex items-center justify-between">
                        <span class="text-muted">{{ __('transactions.type') }}</span>

                        <span
                            class="rounded-full px-3 py-1 text-xs font-medium"
                            :class="type === 'income'
                                ? 'bg-income-soft text-income'
                                : 'bg-expense-soft text-expense'"
                            x-text="type === 'income' ? @js(__('transactions.income')) : @js(__('transactions.expense'))"
                        ></span>
                    </div>

                    {{-- Wallet --}}
                    <div class="flex items-center justify-between">
                        <span class="text-muted">{{ __('transactions.wallet') }}</span>
                        <span class="font-semibold" x-text="wallet"></span>
                    </div>

                    {{-- New Balance --}}
                    <div class="flex items-center justify-between">
                        <span class="text-muted">{{ __('transactions.new_balance') }}</span>

                        <span
                            class="text-base font-bold"
                            :class="newBalance < 0 ? 'text-expense' : 'text-ink'"
                            x-text="formattedBalance"
                        ></span>
                    </div>

                </div>

            </x-card>

        </div>

    </div>

</div>

@endsection