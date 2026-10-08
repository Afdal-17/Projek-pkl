@extends('layouts.main')

@section('title', 'Transactions')

@section('content')

<div
    x-data="{
        dummy: [],
        saved: @js($transactions),
        allCategories: @js($categories),
        wallets: @js($wallets),

        search: '',
        category: '',
        wallet: '',
        dateFrom: '',
        dateTo: '',
        open: null,

        page: 1,
        perPage: 6,

        init() {
            ['search', 'category', 'wallet', 'dateFrom', 'dateTo'].forEach(key =>
                this.$watch(key, () => this.page = 1)
            );
        },

        /* ---------- Data ---------- */

        /* Semua transaksi, terbaru di atas */
        get all() {
            const dummy = this.dummy.map((t, i) => ({ ...t, seq: -i }));
            const saved = this.saved.map(t => ({ ...t, seq: Number(t.id) || 0 }));

            return [...dummy, ...saved].sort((a, b) =>
                (b.date || '').localeCompare(a.date || '') || b.seq - a.seq
            );
        },

        /* Hasil pencarian + filter */
        get filtered() {
            const k = this.search.toLowerCase().trim();

            return this.all.filter(t =>
                (!this.category || t.category === this.category) &&
                (!this.wallet || t.wallet === this.wallet || t.to === this.wallet) &&
                (!this.dateFrom || (t.date || '') >= this.dateFrom) &&
                (!this.dateTo || (t.date || '') <= this.dateTo) &&
                (!k || [t.name, t.category, t.wallet, t.to].join(' ').toLowerCase().includes(k))
            );
        },

        get hasFilter() {
            return !!(this.search.trim() || this.category || this.wallet || this.dateFrom || this.dateTo);
        },

        clearFilters() {
            this.search = '';
            this.category = '';
            this.wallet = '';
            this.dateFrom = '';
            this.dateTo = '';
            this.open = null;
        },

        toggle(name) {
            this.open = this.open === name ? null : name;
        },

        /* ---------- Paginasi ---------- */

        get totalPages() {
            return Math.max(1, Math.ceil(this.filtered.length / this.perPage));
        },

        get pageNumbers() {
            return Array.from({ length: this.totalPages }, (_, i) => i + 1);
        },

        get pageItems() {
            const start = (this.page - 1) * this.perPage;
            return this.filtered.slice(start, start + this.perPage);
        },

        get rangeText() {
            const total = this.filtered.length;

            if (total === 0) return 'No transactions';

            const from = (this.page - 1) * this.perPage + 1;
            const to = Math.min(this.page * this.perPage, total);

            return 'Showing ' + from + 'â€“' + to + ' of ' + total + ' transactions';
        },

        goTo(n) {
            this.page = Math.min(Math.max(1, n), this.totalPages);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        prev() { this.goTo(this.page - 1); },
        next() { this.goTo(this.page + 1); },

        /* ---------- Kelompok per tanggal ---------- */

        get groups() {
            const map = {};
            const order = [];

            this.pageItems.forEach(t => {
                const key = t.date || 'unknown';

                if (!map[key]) {
                    map[key] = { date: key, label: this.labelFor(t.date), total: 0, items: [] };
                    order.push(key);
                }

                map[key].items.push(t);
                map[key].total += this.signed(t);
            });

            return order.map(key => map[key]);
        },

        labelFor(date) {
            if (!date) return 'Unknown date';

            const text = new Date(date + 'T00:00:00')
                .toLocaleDateString('en-GB', { day: 'numeric', month: 'long' });

            const today = new Date().toLocaleDateString('en-CA');
            const yesterday = new Date(Date.now() - 86400000).toLocaleDateString('en-CA');

            if (date === today) return 'Today, ' + text;
            if (date === yesterday) return 'Yesterday, ' + text;

            return text;
        },

        /* ---------- Helper tampilan ---------- */

        rp(n) {
            return window.financeMoney.format(Math.abs(n));
        },

        /* Income positif; expense dan transfer dihitung uang keluar (sama seperti Figma) */
        signed(t) {
            return t.type === 'income' ? Number(t.amount) : -Number(t.amount);
        },

        sign(type, Jenis) {
            if (type === 'income') return '+';
            if (type === 'expense') return '-';
            if (type === 'transfer') {
                return Jenis === 'pemasukan' ? '+' : '-';
            }
            return 'â‡† ';
        },

        iconFor(t) {
            if (t.type === 'transfer') return 'transfer';
            if (t.icon) return t.icon;
            const foundCat = this.allCategories.find(c => c.name === t.category);
            if (foundCat && foundCat.icon) return foundCat.icon;
            if (t.type === 'income') return 'landmark';
            return 'utensils';
        },

        boxClass(type) {
            if (type === 'income') return 'bg-income-soft text-income';
            if (type === 'transfer') return 'bg-brand-soft text-brand';
            return 'bg-expense-soft text-expense';
        },

        amountClass(type, Jenis) {
            if (type === 'income') return 'text-income';
            if (type === 'transfer') return Jenis === 'pemasukan' ? 'text-income' : 'text-expense';
            return 'text-expense';
        }
    }"
>


    {{-- Judul + 3 tombol --}}
    <div class="flex items-start justify-between">

        <div>
            <h1 class="text-3xl font-bold">Transactions</h1>

            <p class="mt-1 text-sm text-muted">
                Review every income and expense in one place.
            </p>
        </div>

        <div class="flex items-center gap-3">

            {{-- Membuka halaman Manage Kategori (tombol Add Category ada di sana) --}}
            <a
                href="/transactions/categories"
                class="inline-flex items-center gap-2 rounded-lg bg-slate-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-600"
            >
                Manage Category
            </a>

            <x-button href="/transfer" variant="brand">
                <x-icon name="arrow-right" size="h-4 w-4" />
                Transfer
            </x-button>

            <x-button href="/transactions/create">
                <x-icon name="plus" size="h-4 w-4" />
                Add Transaction
            </x-button>

        </div>

    </div>


    {{-- Pencarian + filter --}}
    <x-card class="mt-6 p-4">

        <div class="flex items-center gap-4">

            <div class="relative flex-1">

                <x-icon
                    name="search"
                    size="h-4 w-4"
                    class="absolute left-3 top-1/2 -translate-y-1/2 text-muted"
                />

                <input
                    type="text"
                    x-model="search"
                    placeholder="Search transactions"
                    class="w-full rounded-lg border border-line bg-white py-2.5 pl-9 pr-3 text-sm placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                >

            </div>

            <span class="text-xs text-muted" x-text="filtered.length + ' results'"></span>

        </div>


        <div class="mt-3 flex items-center gap-2 text-sm">

            {{-- All: aktif kalau tidak ada filter, klik untuk reset --}}
            <button
                type="button"
                x-on:click="clearFilters()"
                class="rounded-lg px-4 py-2 font-medium transition"
                :class="!hasFilter ? 'bg-dark text-white' : 'border border-line bg-white text-muted hover:bg-page'"
            >
                All
            </button>


            {{-- Filter Category --}}
            <div class="relative" x-on:click.outside="open === 'category' && (open = null)">

                <button
                    type="button"
                    x-on:click="toggle('category')"
                    class="flex items-center gap-2 rounded-lg border px-3 py-2 transition"
                    :class="category ? 'border-brand bg-brand-soft text-brand' : 'border-line bg-white text-muted hover:bg-page'"
                >
                    <span x-text="category || 'Category'"></span>
                    <x-icon name="chevron-down" size="h-4 w-4" />
                </button>

                <div
                    x-cloak
                    x-show="open === 'category'"
                    class="absolute left-0 z-20 mt-2 max-h-72 w-52 overflow-y-auto rounded-xl border border-line bg-white p-1 shadow-lg"
                >
                    <button
                        type="button"
                        x-on:click="category = ''; open = null"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left hover:bg-page"
                        :class="!category ? 'bg-brand-soft text-brand' : ''"
                    >
                        <span>All categories</span>
                        <x-icon name="check" size="h-4 w-4" x-show="!category" x-cloak />
                    </button>

                    <template x-for="c in allCategories" :key="c.id">
                        <button
                            type="button"
                            x-on:click="category = c.name; open = null"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left hover:bg-page"
                            :class="category === c.name ? 'bg-brand-soft text-brand' : ''"
                        >
                            <span x-text="c.name"></span>
                            <x-icon name="check" size="h-4 w-4" x-show="category === c.name" x-cloak />
                        </button>
                    </template>
                </div>

            </div>


            {{-- Filter Wallet --}}
            <div class="relative" x-on:click.outside="open === 'wallet' && (open = null)">

                <button
                    type="button"
                    x-on:click="toggle('wallet')"
                    class="flex items-center gap-2 rounded-lg border px-3 py-2 transition"
                    :class="wallet ? 'border-brand bg-brand-soft text-brand' : 'border-line bg-white text-muted hover:bg-page'"
                >
                    <span x-text="wallet || 'Wallet'"></span>
                    <x-icon name="chevron-down" size="h-4 w-4" />
                </button>

                <div
                    x-cloak
                    x-show="open === 'wallet'"
                    class="absolute left-0 z-20 mt-2 w-44 rounded-xl border border-line bg-white p-1 shadow-lg"
                >
                    <button
                        type="button"
                        x-on:click="wallet = ''; open = null"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left hover:bg-page"
                        :class="!wallet ? 'bg-brand-soft text-brand' : ''"
                    >
                        <span>All wallets</span>
                        <x-icon name="check" size="h-4 w-4" x-show="!wallet" x-cloak />
                    </button>

                    <template x-for="w in wallets" :key="w.name">
                        <button
                            type="button"
                            x-on:click="wallet = w.name; open = null"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left hover:bg-page"
                            :class="wallet === w.name ? 'bg-brand-soft text-brand' : ''"
                        >
                            <span x-text="w.name"></span>
                            <x-icon name="check" size="h-4 w-4" x-show="wallet === w.name" x-cloak />
                        </button>
                    </template>
                </div>

            </div>


            {{-- Filter Date --}}
            <div class="relative" x-on:click.outside="open === 'date' && (open = null)">

                <button
                    type="button"
                    x-on:click="toggle('date')"
                    class="flex items-center gap-2 rounded-lg border px-3 py-2 transition"
                    :class="(dateFrom || dateTo) ? 'border-brand bg-brand-soft text-brand' : 'border-line bg-white text-muted hover:bg-page'"
                >
                    <span x-text="(dateFrom || dateTo) ? 'Date selected' : 'Date'"></span>
                    <x-icon name="chevron-down" size="h-4 w-4" />
                </button>

                <div
                    x-cloak
                    x-show="open === 'date'"
                    class="absolute left-0 z-20 mt-2 w-64 rounded-xl border border-line bg-white p-4 shadow-lg"
                >
                    <label class="block text-xs font-medium text-muted">From</label>
                    <input
                        type="date"
                        x-model="dateFrom"
                        class="mt-1 w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    >

                    <label class="mt-3 block text-xs font-medium text-muted">To</label>
                    <input
                        type="date"
                        x-model="dateTo"
                        class="mt-1 w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    >

                    <button
                        type="button"
                        x-on:click="dateFrom = ''; dateTo = ''; open = null"
                        class="mt-3 text-xs font-medium text-brand hover:underline"
                    >
                        Clear dates
                    </button>
                </div>

            </div>

        </div>

    </x-card>


    {{-- Daftar transaksi per tanggal (hanya halaman yang aktif) --}}
    <template x-for="g in groups" :key="g.date">

        <x-card class="mt-6 px-5 pb-2 pt-5">

            <div class="flex items-center justify-between">

                <h2 class="font-semibold" x-text="g.label"></h2>

                <span
                    class="text-sm"
                    :class="g.total < 0 ? 'text-muted' : 'text-income'"
                    x-text="(g.total < 0 ? '-' : '+') + rp(g.total)"
                ></span>

            </div>


            <template x-for="t in g.items" :key="t.id">

                <div class="mt-3 flex items-center gap-4 border-b border-line pb-3">

                    {{-- Ikon --}}
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-lg"
                        :class="boxClass(t.type)"
                    >
                        <x-icon name="utensils" size="h-4 w-4" x-show="iconFor(t) === 'utensils'" />
                        <x-icon name="landmark" size="h-4 w-4" x-show="iconFor(t) === 'landmark'" x-cloak />
                        <x-icon name="car" size="h-4 w-4" x-show="iconFor(t) === 'car'" x-cloak />
                        <x-icon name="film" size="h-4 w-4" x-show="iconFor(t) === 'film'" x-cloak />
                        <x-icon name="briefcase" size="h-4 w-4" x-show="iconFor(t) === 'briefcase'" x-cloak />
                        <x-icon name="shopping-bag" size="h-4 w-4" x-show="iconFor(t) === 'shopping-bag'" x-cloak />
                        <x-icon name="transfer" size="h-4 w-4" x-show="iconFor(t) === 'transfer'" x-cloak />
                        <x-icon name="smartphone" size="h-4 w-4" x-show="iconFor(t) === 'smartphone'" x-cloak />
                        <x-icon name="banknote" size="h-4 w-4" x-show="iconFor(t) === 'banknote'" x-cloak />
                    </span>

                    {{-- Nama --}}
                    <p class="flex-1 font-medium" x-text="t.name"></p>

                    {{-- Chip kategori + dompet --}}
                    <div class="flex items-center gap-2">

                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="t.type === 'income' ? 'bg-income-soft text-income' : 'bg-gray-100 text-muted'"
                            x-text="t.category"
                        ></span>

                        <span
                            class="rounded-full bg-brand-soft px-2.5 py-1 text-xs font-medium text-brand"
                            x-text="t.wallet"
                        ></span>

                        <template x-if="t.type === 'transfer' && t.to">
                            <div class="flex items-center gap-2">

                                <span class="flex h-6 w-6 items-center justify-center rounded-full border border-ink">
                                    <x-icon name="arrow-right" size="h-3 w-3" />
                                </span>

                                <span
                                    class="rounded-full bg-brand-soft px-2.5 py-1 text-xs font-medium text-brand"
                                    x-text="t.to"
                                ></span>

                            </div>
                        </template>

                    </div>

                    {{-- Nominal --}}
                    <p
                        class="w-36 text-right text-lg font-semibold"
                        :class="amountClass(t.type, t.jenis)"
                        x-text="sign(t.type, t.jenis) + rp(t.amount)"
                    ></p>

                </div>

            </template>

        </x-card>

    </template>


    {{-- Kosong --}}
    <template x-if="filtered.length === 0">
        <x-card class="mt-6 p-10 text-center text-sm text-muted">
            No transactions found.
        </x-card>
    </template>


    {{-- Pagination --}}
    <div class="mt-6 flex items-center justify-between text-sm">

        <span class="text-muted" x-text="rangeText"></span>

        <div class="flex items-center gap-2">

            <x-button
                variant="outline"
                x-on:click="prev()"
                x-bind:disabled="page === 1"
                class="disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-white"
            >
                Previous
            </x-button>

            <template x-for="n in pageNumbers" :key="n">
                <button
                    type="button"
                    x-on:click="goTo(n)"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-sm transition"
                    :class="n === page
                        ? 'bg-dark font-medium text-white'
                        : 'border border-line bg-white text-muted hover:bg-page'"
                    x-text="n"
                ></button>
            </template>

            <x-button
                variant="outline"
                x-on:click="next()"
                x-bind:disabled="page === totalPages"
                class="disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-white"
            >
                Next
            </x-button>

        </div>

    </div>

</div>

@endsection


