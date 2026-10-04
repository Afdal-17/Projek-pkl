@extends('layouts.main')

@section('title', 'Add Transaction')

@section('content')

@php
    // DATA DUMMY: nanti diganti data dari backend
    $categories = [
        ['id' => 1, 'name' => 'Food',          'type' => 'expense', 'icon' => 'utensils'],
        ['id' => 2, 'name' => 'Transport',     'type' => 'expense', 'icon' => 'car'],
        ['id' => 3, 'name' => 'Entertainment', 'type' => 'expense', 'icon' => 'film'],
        ['id' => 4, 'name' => 'Bills',         'type' => 'expense', 'icon' => 'landmark'],
        ['id' => 5, 'name' => 'Dining',        'type' => 'expense', 'icon' => 'utensils'],
        ['id' => 6, 'name' => 'Salary',        'type' => 'income',  'icon' => 'landmark'],
        ['id' => 7, 'name' => 'Freelance',     'type' => 'income',  'icon' => 'briefcase'],
    ];

    $wallets = [
        ['name' => 'Cash',  'balance' => 3250000],
        ['name' => 'DANA',  'balance' => 4500000],
        ['name' => 'GoPay', 'balance' => 4250000],
    ];
@endphp

<div
    x-data="{
        type: 'expense',
        amount: 245000,
        transactionName: 'Grocery shopping',
        category: '',
        wallet: 'DANA',
        date: new Date().toLocaleDateString('en-CA'),
        error: '',

        /* Kategori dibaca dari data yang sama dengan halaman Manage Kategori */
        allCategories: JSON.parse(localStorage.getItem('categories') || 'null') ?? @js($categories),
        wallets: @js($wallets),

        savedTransactions: JSON.parse(localStorage.getItem('transactions') || '[]'),

        init() {
            this.pickCategory();

            this.$watch('type', () => this.pickCategory());
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
            return this.wallets.find(w => w.name === this.wallet) ?? { balance: 0 };
        },

        get newBalance() {
            const balance = Number(this.selectedWallet.balance);
            const amount = Number(this.amount) || 0;

            return this.type === 'income' ? balance + amount : balance - amount;
        },

        get formattedBalance() {
            const n = this.newBalance;

            return (n < 0 ? '-' : '') + 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(n));
        },

        saveTransaction() {
            this.error = '';

            if (!this.amount || Number(this.amount) <= 0) {
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
            if (this.type === 'expense' && this.newBalance < 0) {
                this.error = 'Wallet balance is not enough.';
                return;
            }

            this.savedTransactions.push({
                id: Date.now(),
                type: this.type,
                amount: Number(this.amount),
                name: this.transactionName.trim(),
                category: this.category,
                wallet: this.wallet,
                date: this.date,
            });

            localStorage.setItem('transactions', JSON.stringify(this.savedTransactions));

            window.location.href = '/transactions';
        }
    }"
>

    {{-- Page Header --}}
    <div>
        <h1 class="text-3xl font-bold">Add Transaction</h1>

        <p class="mt-1 text-sm text-muted">
            Record an income or expense to keep your balance accurate.
        </p>
    </div>


    {{-- Main Content --}}
    <div class="mt-8 grid gap-6 lg:grid-cols-[2fr_1.2fr]">


        {{-- FORM TRANSACTION --}}
        <x-card class="p-8">

            {{-- Transaction Type --}}
            <div>
                <label class="mb-2 block text-sm font-medium">
                    Transaction type
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
                        Income
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
                        Expense
                    </button>

                </div>
            </div>


            {{-- Amount --}}
            <div class="mt-6">

                <label for="amount" class="mb-2 block text-sm font-medium">
                    Amount
                </label>

                <div class="relative">

                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-lg font-semibold text-muted">
                        Rp
                    </span>

                    <input
                        id="amount"
                        type="number"
                        min="0"
                        x-model.number="amount"
                        class="w-full rounded-lg border border-line bg-white py-3 pl-12 pr-4 text-2xl font-semibold focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    >

                </div>
            </div>


            {{-- Transaction Name --}}
            <div class="mt-6">

                <label for="transaction_name" class="mb-2 block text-sm font-medium">
                    Transaction name
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
                        <label for="category" class="text-sm font-medium">
                            Category
                        </label>

                        <a href="/transactions/categories" class="text-xs font-medium text-brand hover:underline">
                            Manage
                        </a>
                    </div>

                    <div class="relative">

                        <select
                            id="category"
                            x-model="category"
                            class="w-full appearance-none rounded-lg border border-line bg-white px-3 py-3 pr-10 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                        >
                            <template x-if="categories.length === 0">
                                <option value="" disabled>No category yet</option>
                            </template>

                            <template x-for="item in categories" :key="item.id">
                                <option
                                    :value="item.name"
                                    :selected="item.name === category"
                                    x-text="item.name"
                                ></option>
                            </template>
                        </select>

                        <x-icon
                            name="chevron-down"
                            size="h-4 w-4"
                            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted"
                        />

                    </div>

                </div>


                {{-- Wallet --}}
                <div>

                    <label for="wallet" class="mb-2 block text-sm font-medium">
                        Wallet
                    </label>

                    <div class="relative">

                        <select
                            id="wallet"
                            x-model="wallet"
                            class="w-full appearance-none rounded-lg border border-line bg-white px-3 py-3 pr-10 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                        >
                            <template x-for="item in wallets" :key="item.name">
                                <option
                                    :value="item.name"
                                    :selected="item.name === wallet"
                                    x-text="item.name"
                                ></option>
                            </template>
                        </select>

                        <x-icon
                            name="chevron-down"
                            size="h-4 w-4"
                            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted"
                        />

                    </div>

                </div>

            </div>


            {{-- Date --}}
            <div class="mt-6">

                <label for="date" class="mb-2 block text-sm font-medium">
                    Date
                </label>

                <input
                    id="date"
                    type="date"
                    x-model="date"
                    class="w-full rounded-lg border border-line bg-white px-3 py-3 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                >

            </div>


            {{-- Error --}}
            <p x-cloak x-show="error" x-text="error" class="mt-4 text-sm text-expense"></p>


            {{-- Buttons --}}
            <div class="mt-6 flex justify-end gap-3">

                <x-button href="/transactions" variant="outline">
                    Cancel
                </x-button>

                <x-button type="button" x-on:click="saveTransaction()">
                    <span x-text="type === 'income' ? 'Add income' : 'Add expense'"></span>
                </x-button>

            </div>

        </x-card>


        {{-- RIGHT COLUMN: hanya Summary (kartu Add Category sudah dipindah ke Manage Kategori) --}}
        <div class="space-y-6">

            <x-card class="p-6">

                <h2 class="text-lg font-semibold">Summary</h2>

                <div class="mt-5 space-y-5 text-sm">

                    {{-- Type --}}
                    <div class="flex items-center justify-between">
                        <span class="text-muted">Type</span>

                        <span
                            class="rounded-full px-3 py-1 text-xs font-medium"
                            :class="type === 'income'
                                ? 'bg-income-soft text-income'
                                : 'bg-expense-soft text-expense'"
                            x-text="type === 'income' ? 'Income' : 'Expense'"
                        ></span>
                    </div>

                    {{-- Wallet --}}
                    <div class="flex items-center justify-between">
                        <span class="text-muted">Wallet</span>
                        <span class="font-semibold" x-text="wallet"></span>
                    </div>

                    {{-- New Balance --}}
                    <div class="flex items-center justify-between">
                        <span class="text-muted">New balance</span>

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