@extends('layouts.main')

@section('title', 'Manage Kategori')

@section('content')

@php
    // Pilihan ikon: kunci ikon => nama yang tampil
    $icons = [
        'utensils' => 'Food', 'landmark' => 'Bank', 'car' => 'Transport',
        'briefcase' => 'Work', 'film' => 'Entertainment', 'shopping-bag' => 'Shopping',
    ];
@endphp

<div
    x-data="{
        list: @js($categories),
        labels: @js($icons),
        csrfToken: @js(csrf_token()),
        storeUrl: @js(route('categories.store')),
        updateUrl: @js(route('categories.update', ['kategori' => '__ID__'])),
        deleteUrl: @js(route('categories.destroy', ['kategori' => '__ID__'])),

        search: '', type: 'all', sort: 'name', sortOpen: false,
        page: 1, perPage: 5,

        modal: null, editingId: null, original: '',
        form: { icon: '', name: '', type: '' },
        deleting: { name: '', type: 'expense', icon: 'utensils', count: 0 },

        init() {
            this.$watch('search', () => this.page = 1);
            this.$watch('type', () => this.page = 1);
        },

        get filtered() {
            const k = this.search.toLowerCase().trim();

            return this.list
                .filter(c => (this.type === 'all' || c.type === this.type) && (!k || c.name.toLowerCase().includes(k)))
                .sort((a, b) => this.sort === 'name' ? a.name.localeCompare(b.name) : b.id - a.id);
        },

        get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
        get pageNumbers() { return Array.from({ length: this.totalPages }, (_, i) => i + 1); },
        get pageItems() { const s = (this.page - 1) * this.perPage; return this.filtered.slice(s, s + this.perPage); },

        get rangeText() {
            const t = this.filtered.length;
            if (!t) return 'No categories';
            const from = (this.page - 1) * this.perPage + 1;
            return 'Showing ' + from + '–' + Math.min(this.page * this.perPage, t) + ' of ' + t + ' categories';
        },

        goTo(n) { this.page = Math.min(Math.max(1, n), this.totalPages); },

        get isEdit() { return this.editingId !== null; },

        get duplicate() {
            const n = this.form.name.trim().toLowerCase();
            return n !== '' && this.list.some(c => c.id !== this.editingId && c.name.toLowerCase() === n);
        },

        get canSave() { return this.form.icon && this.form.name.trim() && this.form.type && !this.duplicate; },

        openAdd() { this.editingId = null; this.original = ''; this.form = { icon: '', name: '', type: '' }; this.modal = 'form'; },
        openEdit(c) { this.editingId = c.id; this.original = c.name; this.form = { icon: c.icon, name: c.name, type: c.type }; this.modal = 'form'; },
        openDelete(c) { this.deleting = c; this.modal = 'delete'; },
        close() { this.modal = null; },

        async save() {
            if (!this.canSave) return;

            const data = new URLSearchParams({
                _token: this.csrfToken,
                nama_kategori: this.form.name.trim(),
                jenis: this.form.type === 'income' ? 'pemasukan' : 'pengeluaran',
            });
            const url = this.isEdit ? this.updateUrl.replace('__ID__', this.editingId) : this.storeUrl;
            const response = await fetch(url, {
                method: this.isEdit ? 'PATCH' : 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                body: data,
            });

            if (!response.ok) {
                alert('Category could not be saved. Check the name and try again.');
                return;
            }

            window.location.reload();
        },

        async confirmDelete() {
            const response = await fetch(this.deleteUrl.replace('__ID__', this.deleting.id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
            });

            if (!response.ok) {
                alert('Category could not be deleted.');
                return;
            }

            window.location.reload();
        }
    }"
>

    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-3xl font-bold">Manage Kategori</h1>
            <p class="mt-1 text-sm text-muted">Create and manage your transaction categories.</p>
        </div>

        <x-button x-on:click="openAdd()">
            <x-icon name="plus" size="h-4 w-4" /> Add Category
        </x-button>
    </div>


    {{-- Pencarian, urutan, filter --}}
    <x-card class="mt-6 p-4">

        <div class="flex items-center gap-4">

            <div class="relative flex-1">
                <x-icon name="search" size="h-4 w-4" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                <input type="text" x-model="search" placeholder="Search categories"
                       class="w-full rounded-lg border border-line bg-white py-2.5 pl-9 pr-3 text-sm placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
            </div>

            <div class="relative">
                <button type="button" x-on:click="sortOpen = !sortOpen"
                        class="flex w-44 items-center justify-between rounded-lg border border-line bg-white px-3 py-2.5 text-sm font-medium">
                    <span x-text="sort === 'name' ? 'Name A-Z' : 'Recently Added'"></span>
                    <x-icon name="chevron-down" size="h-4 w-4" class="text-muted" />
                </button>

                <div x-cloak x-show="sortOpen" x-on:click.outside="sortOpen = false"
                     class="absolute right-0 z-20 mt-2 w-44 rounded-xl border border-line bg-white p-1 text-sm shadow-lg">
                    <template x-for="o in [['name', 'Name A-Z'], ['recent', 'Recently Added']]" :key="o[0]">
                        <button type="button" x-on:click="sort = o[0]; sortOpen = false"
                                class="flex w-full items-center justify-between rounded-lg px-3 py-2 hover:bg-page"
                                :class="sort === o[0] ? 'bg-brand-soft text-brand' : ''">
                            <span x-text="o[1]"></span>
                            <x-icon name="check" size="h-4 w-4" x-show="sort === o[0]" x-cloak />
                        </button>
                    </template>
                </div>
            </div>

        </div>

        <div class="mt-3 flex items-center gap-2 text-sm">
            @foreach (['all' => 'All', 'income' => 'Income', 'expense' => 'Expense'] as $key => $label)
                <button type="button" x-on:click="type = '{{ $key }}'"
                        class="rounded-lg px-4 py-2 font-medium transition"
                        :class="type === '{{ $key }}' ? 'bg-dark text-white' : 'border border-line bg-white text-muted hover:bg-page'">
                    {{ $label }}
                </button>
            @endforeach

            <span class="ml-1 text-xs text-muted" x-text="filtered.length + ' categories'"></span>
        </div>

    </x-card>


    {{-- Tabel kategori --}}
    <x-card class="mt-6 overflow-hidden">

        <div class="grid grid-cols-[72px_2fr_1.2fr_1.4fr_1.6fr] gap-4 border-b border-line px-6 py-4 text-xs font-semibold text-muted">
            <span>Icon</span>
            <span>Name</span>
            <span>Type</span>
            <span>Transactions count</span>
            <span>Actions</span>
        </div>

        <template x-for="c in pageItems" :key="c.id">
            <div class="grid grid-cols-[72px_2fr_1.2fr_1.4fr_1.6fr] items-center gap-4 border-b border-line px-6 py-4 text-sm">

                <span class="flex h-10 w-10 items-center justify-center rounded-lg"
                      :class="c.type === 'income' ? 'bg-income-soft text-income' : 'bg-expense-soft text-expense'">
                    @foreach (array_keys($icons) as $key)
                        <x-icon :name="$key" size="h-4 w-4" x-show="c.icon === '{{ $key }}'" x-cloak />
                    @endforeach
                </span>

                <span class="font-semibold" x-text="c.name"></span>

                <span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                          :class="c.type === 'income' ? 'bg-income-soft text-income' : 'bg-expense-soft text-expense'"
                          x-text="c.type === 'income' ? 'Income' : 'Expense'"></span>
                </span>

                <span class="text-muted" x-text="c.count"></span>

                <div class="flex items-center gap-2">
                    <button type="button" x-on:click="openEdit(c)"
                            class="flex items-center gap-1.5 rounded-md bg-brand-soft px-3 py-1.5 text-xs font-semibold text-brand hover:opacity-80">
                        <x-icon name="pencil" size="h-3.5 w-3.5" /> Edit
                    </button>

                    <button type="button" x-on:click="openDelete(c)"
                            class="flex items-center gap-1.5 rounded-md bg-expense-soft px-3 py-1.5 text-xs font-semibold text-expense hover:opacity-80">
                        <x-icon name="trash" size="h-3.5 w-3.5" /> Delete
                    </button>
                </div>

            </div>
        </template>

        <template x-if="filtered.length === 0">
            <p class="px-6 py-12 text-center text-sm text-muted">No categories found.</p>
        </template>

        {{-- Footer + paginasi --}}
        <div class="flex items-center justify-between px-6 py-4 text-sm">

            <span class="text-xs text-muted" x-text="rangeText"></span>

            <div class="flex items-center gap-2">

                <button type="button" x-on:click="goTo(page - 1)" x-bind:disabled="page === 1"
                        class="rounded-lg px-3 py-2 text-xs text-muted hover:bg-page disabled:cursor-not-allowed disabled:opacity-40">
                    Previous
                </button>

                <template x-for="n in pageNumbers" :key="n">
                    <button type="button" x-on:click="goTo(n)"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-xs transition"
                            :class="n === page ? 'bg-dark font-medium text-white' : 'border border-line bg-white text-muted hover:bg-page'"
                            x-text="n"></button>
                </template>

                <button type="button" x-on:click="goTo(page + 1)" x-bind:disabled="page === totalPages"
                        class="rounded-lg px-3 py-2 text-xs text-muted hover:bg-page disabled:cursor-not-allowed disabled:opacity-40">
                    Next
                </button>

            </div>

        </div>

    </x-card>


    {{-- Modal Add / Edit --}}
    <div x-cloak x-show="modal === 'form'" x-transition.opacity
         x-on:keydown.escape.window="close()" x-on:click.self="close()"
         class="fixed inset-0 z-30 flex items-center justify-center bg-black/30 p-4">

        <div class="w-full max-w-md rounded-2xl border border-line bg-white p-6 shadow-xl">

            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-xl font-bold" x-text="isEdit ? 'Edit Category' : 'Add Category'"></h3>
                    <p class="mt-1 text-xs text-muted"
                       x-text="isEdit ? 'Update the details for your ' + original + ' category.' : 'Create a category to organize your transactions.'"></p>
                </div>

                <button type="button" x-on:click="close()"
                        class="flex h-9 w-9 items-center justify-center rounded-lg hover:bg-page">
                    <x-icon name="x" size="h-4 w-4" />
                </button>
            </div>

            {{-- Pilih ikon --}}
            <div class="mt-5 flex items-center justify-between">
                <span class="text-sm font-medium">Category icon</span>
                <span class="text-xs text-muted"
                      x-text="form.icon ? labels[form.icon] + ' icon selected' : 'Choose an icon'"></span>
            </div>

            <div class="mt-2 flex gap-2">
                @foreach ($icons as $key => $label)
                    <button type="button" title="{{ $label }}" x-on:click="form.icon = '{{ $key }}'"
                            class="flex h-10 w-10 items-center justify-center rounded-lg border transition"
                            :class="form.icon === '{{ $key }}' ? 'border-brand bg-brand-soft text-brand' : 'border-line bg-white text-muted hover:bg-page'">
                        <x-icon :name="$key" size="h-4 w-4" />
                    </button>
                @endforeach
            </div>

            {{-- Nama --}}
            <div class="mt-4">
                <x-input label="Category name" name="category_name" placeholder="Enter category name" x-model="form.name" />
                <p x-cloak x-show="duplicate" class="mt-1 text-xs text-expense">A category with this name already exists.</p>
            </div>

            {{-- Tipe --}}
            <label class="mb-1.5 mt-4 block text-sm font-medium">Category type</label>
            <div class="grid grid-cols-2 gap-1 rounded-lg bg-page p-1 text-sm font-medium">
                @foreach (['income' => 'Income', 'expense' => 'Expense'] as $key => $label)
                    <button type="button" x-on:click="form.type = '{{ $key }}'"
                            class="rounded-md py-2 transition"
                            :class="form.type === '{{ $key }}' ? 'bg-dark text-white' : 'text-muted hover:bg-white'">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="mt-5 flex justify-end gap-3 border-t border-line pt-5">
                <x-button variant="outline" x-on:click="close()">Cancel</x-button>
                <x-button x-on:click="save()" x-bind:disabled="!canSave"
                          class="disabled:cursor-not-allowed disabled:bg-gray-300 disabled:hover:bg-gray-300">
                    Save
                </x-button>
            </div>

        </div>
    </div>


    {{-- Modal Delete --}}
    <div x-cloak x-show="modal === 'delete'" x-transition.opacity
         x-on:keydown.escape.window="close()" x-on:click.self="close()"
         class="fixed inset-0 z-30 flex items-center justify-center bg-black/30 p-4">

        <div class="w-full max-w-md rounded-2xl border border-line bg-white p-6 shadow-xl">

            <div class="flex items-start justify-between">
                <h3 class="text-xl font-bold">Delete Category</h3>

                <button type="button" x-on:click="close()"
                        class="flex h-9 w-9 items-center justify-center rounded-lg hover:bg-page">
                    <x-icon name="x" size="h-4 w-4" />
                </button>
            </div>

            <span class="mt-4 flex h-9 w-9 items-center justify-center rounded-lg bg-expense-soft text-expense">
                <x-icon name="trash" size="h-4 w-4" />
            </span>

            <p class="mt-4 font-semibold">Are you sure you want to delete this category?</p>

            <div class="mt-3 flex items-center gap-3 rounded-lg bg-page px-4 py-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg"
                      :class="deleting.type === 'income' ? 'bg-income-soft text-income' : 'bg-expense-soft text-expense'">
                    @foreach (array_keys($icons) as $key)
                        <x-icon :name="$key" size="h-4 w-4" x-show="deleting.icon === '{{ $key }}'" x-cloak />
                    @endforeach
                </span>

                <div class="flex-1 leading-tight">
                    <p class="text-sm font-semibold" x-text="deleting.name"></p>
                    <p class="text-xs text-muted" x-text="deleting.count + ' transactions'"></p>
                </div>

                <span class="text-xs font-semibold"
                      :class="deleting.type === 'income' ? 'text-income' : 'text-expense'"
                      x-text="deleting.type === 'income' ? 'Income' : 'Expense'"></span>
            </div>

            <p class="mt-3 text-xs text-muted">
                Transactions remain unchanged. Only this category will be removed. This action cannot be undone.
            </p>

            <div class="mt-5 flex justify-end gap-3 border-t border-line pt-5">
                <x-button variant="outline" x-on:click="close()">Cancel</x-button>
                <x-button variant="danger" x-on:click="confirmDelete()">Delete</x-button>
            </div>

        </div>
    </div>

</div>
@endsection