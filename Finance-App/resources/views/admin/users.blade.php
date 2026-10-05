@extends('layouts.admin')

@section('title', 'Users Manage')

@section('content')

@php
    // DATA DUMMY: nanti diganti data dari backend
    $users = [
        ['id' => 1, 'name' => 'Maya Putri',     'email' => 'maya.putri@financeapp.id',     'status' => 'Active'],
        ['id' => 2, 'name' => 'Rizky Pratama',  'email' => 'rizky.pratama@financeapp.id',  'status' => 'Active'],
        ['id' => 3, 'name' => 'Siti Aisyah',    'email' => 'siti.aisyah@financeapp.id',    'status' => 'Inactive'],
        ['id' => 4, 'name' => 'Budi Santoso',   'email' => 'budi.santoso@financeapp.id',   'status' => 'Active'],
        ['id' => 5, 'name' => 'Lestari Wijaya', 'email' => 'lestari.wijaya@financeapp.id', 'status' => 'Active'],
        ['id' => 6, 'name' => 'Andi Nugroho',   'email' => 'andi.nugroho@financeapp.id',   'status' => 'Inactive'],
    ];
@endphp

<div
    x-data="{
        users: @js($users),
        search: '',
        editingId: null,
        form: { name: '', email: '' },

        get filtered() {
            const keyword = this.search.toLowerCase().trim();

            if (!keyword) return this.users;

            return this.users.filter(u =>
                u.name.toLowerCase().includes(keyword) ||
                u.email.toLowerCase().includes(keyword)
            );
        },

        activate(u) {
            u.status = 'Active';
        },

        remove(id) {
            if (!confirm('Delete this user?')) return;
            this.users = this.users.filter(u => u.id !== id);
        },

        openEdit(u) {
            this.editingId = u.id;
            this.form = { name: u.name, email: u.email };
        },

        saveEdit() {
            const user = this.users.find(u => u.id === this.editingId);

            if (user && this.form.name.trim() && this.form.email.trim()) {
                user.name = this.form.name.trim();
                user.email = this.form.email.trim();
            }

            this.editingId = null;
        }
    }"
>

    {{-- Header --}}
    <div>
        <h1 class="text-3xl font-bold">Users Manage</h1>
        <p class="mt-1 text-sm text-muted">Monitor users, activity, and manage accounts.</p>
    </div>


    <x-card class="mt-6 overflow-hidden">

        {{-- Judul + pencarian --}}
        <div class="flex items-center justify-between p-6">

            <div class="flex items-center gap-3">
                <h2 class="text-xl font-bold">Users Manage</h2>
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-muted"
                      x-text="users.length + ' users'"></span>
            </div>

            <div class="relative w-72">
                <x-icon name="search" size="h-4 w-4"
                    class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />

                <input
                    type="text"
                    x-model="search"
                    placeholder="Search users"
                    class="w-full rounded-lg border border-line bg-white py-2.5 pl-9 pr-3 text-sm placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                >
            </div>

        </div>


        {{-- Tabel --}}
        <div class="border-t border-line">

            <div class="grid grid-cols-[2fr_2.6fr_1fr_1.3fr] gap-4 bg-gray-50 px-6 py-3 text-xs font-semibold text-muted">
                <span>Name</span>
                <span>Email</span>
                <span>Status</span>
                <span>Actions</span>
            </div>

            <template x-for="u in filtered" :key="u.id">
                <div class="grid grid-cols-[2fr_2.6fr_1fr_1.3fr] items-center gap-4 border-t border-line px-6 py-3 text-sm">

                    <div class="flex items-center gap-3">
                        <span class="h-8 w-8 rounded-full bg-brand-soft"></span>
                        <span class="font-medium" x-text="u.name"></span>
                    </div>

                    <span class="text-muted" x-text="u.email"></span>

                    <span>
                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="u.status === 'Active' ? 'bg-income-soft text-income' : 'bg-gray-100 text-muted'"
                            x-text="u.status"
                        ></span>
                    </span>

                    <div class="flex items-center gap-2">

                        <button type="button" title="Activate" x-on:click="activate(u)"
                                class="flex h-8 w-8 items-center justify-center rounded-md bg-income-soft text-income hover:opacity-80">
                            <x-icon name="check" size="h-4 w-4" />
                        </button>

                        <button type="button" title="Edit" x-on:click="openEdit(u)"
                            class="flex h-8 w-8 items-center justify-center rounded-md bg-warn-soft text-warn hover:opacity-80">
                            <x-icon name="pencil" size="h-4 w-4" />
                        </button>

                        <button type="button" title="Delete" x-on:click="remove(u.id)"
                                class="flex h-8 w-8 items-center justify-center rounded-md bg-expense-soft text-expense hover:opacity-80">
                            <x-icon name="trash" size="h-4 w-4" />
                        </button>

                    </div>

                </div>
            </template>

            <template x-if="filtered.length === 0">
                <p class="border-t border-line px-6 py-10 text-center text-sm text-muted">No users found.</p>
            </template>

        </div>


        {{-- Footer + pagination (statis dulu) --}}
        <div class="flex items-center justify-between border-t border-line px-6 py-4 text-xs text-muted">

            <span x-text="'Showing ' + filtered.length + ' of ' + users.length + ' users'"></span>

            <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-white">
                    <x-icon name="arrow-left" size="h-3.5 w-3.5" />
                </span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-soft font-medium text-brand">1</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-white">2</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-white">
                    <x-icon name="arrow-right" size="h-3.5 w-3.5" />
                </span>
            </div>

        </div>

    </x-card>


    {{-- Modal edit user --}}
    <div x-cloak x-show="editingId !== null" x-transition.opacity
         x-on:keydown.escape.window="editingId = null"
         x-on:click.self="editingId = null"
         class="fixed inset-0 z-30 flex items-center justify-center bg-black/30 p-4">

        <div class="w-full max-w-sm rounded-2xl border border-line bg-white p-6 shadow-xl">

            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-2xl font-bold">Edit User</h3>
                    <p class="mt-1 text-xs text-muted">Update the details for this user.</p>
                </div>

                <button type="button" x-on:click="editingId = null"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-line hover:bg-page">
                    <x-icon name="x" size="h-4 w-4" />
                </button>
            </div>

            <div class="mt-5 space-y-4">
                <x-input label="Name" name="user_name" x-model="form.name" />
                <x-input label="Email" name="user_email" type="email" x-model="form.email" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-button variant="outline" x-on:click="editingId = null">Cancel</x-button>
                <x-button x-on:click="saveEdit()">Save changes</x-button>
            </div>

        </div>

    </div>

</div>

@endsection