@extends('layouts.admin')

@section('title', 'Verify Users')

@section('content')

@php
    $userRows = $users->getCollection()->map(fn ($user) => [
        'id' => $user->id_user,
        'name' => $user->nama,
        'email' => $user->email,
        'avatar' => $user->avatar_path ? asset('storage/' . $user->avatar_path) : null,
        'created_at' => $user->created_at->format('d M Y H:i'),
        'created_diff' => $user->created_at->diffForHumans(),
    ])->values();
@endphp

<div
    x-data="{
        users: @js($userRows),
        search: '',

        get filtered() {
            const q = this.search.toLowerCase().trim();
            if (!q) return this.users;
            return this.users.filter(u => 
                u.name.toLowerCase().includes(q) || 
                u.email.toLowerCase().includes(q)
            );
        },

        async verifyUser(user) {
            const confirmed = await window.financeConfirm({
                title: 'Verify User?',
                message: 'Verifikasi akun ' + user.name + ' untuk mengaktifkan akun mereka?',
                confirmText: 'Verify',
                variant: 'income',
            });
            if (!confirmed) return;

            const response = await fetch('/admin/users/' + user.id + '/verify', {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': @js(csrf_token()),
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                // Hapus dari list
                this.users = this.users.filter(u => u.id !== user.id);
                window.financeToast({
                    message: 'User ' + user.name + ' berhasil diverifikasi.',
                    variant: 'income',
                });
            } else {
                const result = await response.json().catch(() => ({}));
                window.financeAlert({
                    title: 'Verification Failed',
                    message: result.message || 'Gagal memverifikasi user.',
                    variant: 'danger',
                });
            }
        }
    }"
>

    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-3xl font-bold">Verify Users</h1>
            <p class="mt-1 text-sm text-muted">Verifikasi user yang mendaftar untuk mengaktifkan akun mereka.</p>
        </div>
    </div>


    {{-- Card table --}}
    <x-card class="mt-8 overflow-hidden">

        {{-- Header + search --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-4 sm:px-6">
            <div>
                <h2 class="text-xl font-bold">Unverified Users</h2>
                <p class="mt-0.5 text-xs text-muted" x-text="filtered.length + ' users menunggu verifikasi'"></p>
            </div>

            <div class="relative w-full sm:w-64">
                <input
                    type="text"
                    x-model="search"
                    placeholder="Search by name or email..."
                    class="w-full rounded-lg border border-line bg-white py-2 pl-9 pr-3 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                >
                <x-icon name="search" size="h-4 w-4"
                    class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
            </div>
        </div>

        {{-- Table header (desktop only) --}}
        <div class="border-t border-line">
            <div x-show="filtered.length > 0" class="hidden grid-cols-[2fr_2.4fr_1.8fr_1fr] items-center gap-4 bg-gray-50 px-6 py-3 text-xs font-semibold text-muted md:grid">
                <span>Name</span>
                <span>Email</span>
                <span>Registered</span>
                <span>Action</span>
            </div>

            {{-- Table body / Mobile list --}}
            <template x-for="u in filtered" :key="u.id">
                <div class="flex flex-col gap-2 border-b border-line p-4 text-sm sm:px-6 md:grid md:grid-cols-[2fr_2.4fr_1.8fr_1fr] md:items-center md:gap-4 md:border-b-0 md:border-t md:py-3.5">

                    {{-- Row 1: Avatar + Name (left) and Verify Action (right on mobile) --}}
                    <div class="flex items-start justify-between gap-3 min-w-0 md:items-center md:justify-start">
                        <div class="flex items-start gap-3 min-w-0 flex-1 md:items-center">
                            <template x-if="u.avatar">
                                <img :src="u.avatar" :alt="u.name" class="h-8 w-8 shrink-0 rounded-full object-cover ring-1 ring-line mt-0.5 md:mt-0">
                            </template>
                            <template x-if="!u.avatar">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-warn-soft text-xs font-semibold text-warn mt-0.5 md:mt-0"
                                      x-text="u.name ? u.name.charAt(0).toUpperCase() : '?'"></span>
                            </template>
                            <span class="font-medium text-ink break-words leading-snug" :title="u.name" x-text="u.name"></span>
                        </div>

                        {{-- Mobile action button --}}
                        <div class="md:hidden shrink-0 self-start">
                            <button type="button" title="Verify User" x-on:click="verifyUser(u)"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-income-soft px-3 py-1.5 text-xs font-semibold text-income hover:bg-income hover:text-white transition active:scale-95">
                                <x-icon name="check" size="h-3.5 w-3.5" />
                                <span>Verify</span>
                            </button>
                        </div>
                    </div>

                    {{-- Row 2: Email --}}
                    <div class="min-w-0">
                        <span class="truncate block text-xs sm:text-sm text-muted md:text-ink" :title="u.email" x-text="u.email"></span>
                    </div>

                    {{-- Row 3: Registered Time --}}
                    <div class="text-xs text-muted flex flex-wrap items-center gap-1.5 md:block">
                        <span class="font-medium text-ink/80 md:font-normal md:text-muted" x-text="u.created_diff"></span>
                        <span class="md:hidden text-gray-300">•</span>
                        <span class="text-muted" x-text="u.created_at"></span>
                    </div>

                    {{-- Desktop action button --}}
                    <div class="hidden md:flex items-center gap-2">
                        <button type="button" title="Verify User" x-on:click="verifyUser(u)"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-income-soft px-3 py-1.5 text-xs font-semibold text-income hover:bg-income hover:text-white transition active:scale-95">
                            <x-icon name="check" size="h-3.5 w-3.5" />
                            <span>Verify</span>
                        </button>
                    </div>

                </div>
            </template>
        </div>

        {{-- Empty state --}}
        <div x-show="filtered.length === 0" class="border-t border-line px-6 py-10 text-center text-sm text-muted">
            <template x-if="search">
                <p>No users found matching your search.</p>
            </template>
            <template x-if="!search">
                <p>No users waiting for verification.</p>
            </template>
        </div>

    </x-card>

</div>

@endsection
