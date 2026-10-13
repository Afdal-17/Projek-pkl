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
            if (!confirm('Verifikasi user ' + user.name + '?')) return;

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
                alert('User berhasil diverifikasi dan dipindahkan ke Manage User.');
            } else {
                const result = await response.json().catch(() => ({}));
                alert(result.message || 'Gagal memverifikasi user.');
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
        <div class="flex items-center justify-between border-b border-line px-6 py-4">
            <div>
                <h2 class="text-xl font-bold">Unverified Users</h2>
                <p class="mt-0.5 text-xs text-muted" x-text="filtered.length + ' users menunggu verifikasi'"></p>
            </div>

            <div class="relative w-64">
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

        {{-- Table header --}}
        <div x-show="filtered.length > 0" class="grid grid-cols-[2fr_2.6fr_1.5fr_1fr] gap-4 bg-gray-50 px-6 py-3 text-xs font-semibold text-muted">
            <span>Name</span>
            <span>Email</span>
            <span>Registered</span>
            <span>Action</span>
        </div>

        {{-- Table body --}}
        <template x-for="u in filtered" :key="u.id">
            <div class="grid grid-cols-[2fr_2.6fr_1.5fr_1fr] items-center gap-4 border-t border-line px-6 py-3 text-sm">

                <div class="flex items-center gap-3">
                    <template x-if="u.avatar">
                        <img :src="u.avatar" :alt="u.name" class="h-8 w-8 rounded-full object-cover ring-1 ring-line">
                    </template>
                    <template x-if="!u.avatar">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-warn-soft text-xs font-semibold text-warn"
                              x-text="u.name.charAt(0).toUpperCase()"></span>
                    </template>
                    <span class="font-medium" x-text="u.name"></span>
                </div>

                <span class="text-muted" x-text="u.email"></span>

                <div>
                    <span class="text-muted" x-text="u.created_diff"></span>
                    <div class="text-xs text-muted" x-text="u.created_at"></div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" title="Verify User" x-on:click="verifyUser(u)"
                            class="flex h-8 w-8 items-center justify-center rounded-md bg-income-soft text-income hover:opacity-80">
                        <x-icon name="check" size="h-4 w-4" />
                    </button>
                </div>

            </div>
        </template>

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
