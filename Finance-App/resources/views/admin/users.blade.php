@extends('layouts.admin')

@section('title', 'Users Manage')

@section('content')

@php
    $userRows = $users->getCollection()->map(fn ($user) => [
        'id' => $user->id_user,
        'name' => $user->nama,
        'email' => $user->email,
        'status' => $user->email_verified_at === null && $user->role === 'user' ? 'Unverified' : ($user->banned_at ? 'Banned' : ($user->status ? 'Active' : 'Inactive')),
        'banned' => $user->banned_at !== null,
        'ban_reason' => $user->ban_reason,
        'banned_at' => $user->banned_at?->toIso8601String(),
        'ban_days_remaining' => $user->banned_at ? floor(max(0, 30 - $user->banned_at->diffInDays(now()))) : null,
        'ban_expires_at' => $user->banned_at?->addDays(30)->format('d M Y H:i'),
        'last_seen_at' => $user->last_seen_at?->toIso8601String(),
        'canToggle' => ! $user->isAdmin() && ! $user->is(auth()->user()) && $user->banned_at !== null,
        'canBan' => ! $user->isAdmin() && ! $user->is(auth()->user()) && $user->email_verified_at !== null,
        'canVerify' => $user->email_verified_at === null && $user->role === 'user',
        'canDelete' => ! $user->isAdmin() && ! $user->is(auth()->user()) && ($user->last_seen_at ?? $user->created_at)->diffInDays(now()) >= 365,
    ])->values();
@endphp

<div
    x-data="{
        users: @js($userRows),
        search: '',
        banningId: null,
        banReason: '',
        csrfToken: @js(csrf_token()),
        statusUrl: @js(route('admin.users.status', ['user' => '__USER__'])),
        banUrl: @js(route('admin.users.ban', ['user' => '__USER__'])),
        deleteUrl: @js(route('admin.users.destroy', ['user' => '__USER__'])),
        verifyUrl: @js(route('admin.users.verify', ['user' => '__USER__'])),

        get filtered() {
            const keyword = this.search.toLowerCase().trim();

            if (!keyword) return this.users;

            return this.users.filter(u =>
                u.name.toLowerCase().includes(keyword) ||
                u.email.toLowerCase().includes(keyword)
            );
        },

        async toggleStatus(user) {
            if (!user.canToggle) return;

            const response = await fetch(this.statusUrl.replace('__USER__', user.id), {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
            });

            if (response.ok) {
                // Hanya untuk unban (mengaktifkan kembali akun yang dibanned)
                if (user.banned) {
                    user.banned = false;
                    user.status = 'Active';
                    user.ban_reason = null;
                }
            }
        },

        openBanModal(user) {
            if (!user.canBan) return;
            this.banningId = user.id;
            this.banReason = '';
        },

        async submitBan() {
            const user = this.users.find(u => u.id === this.banningId);
            if (!user) return;

            const banning = !user.banned;

            const body = new URLSearchParams({ _token: this.csrfToken });
            if (banning && this.banReason.trim()) {
                body.set('ban_reason', this.banReason.trim());
            }

            const response = await fetch(this.banUrl.replace('__USER__', user.id), {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
            });

            if (response.ok) {
                const result = await response.json();
                const bannedNow = result.status === 'banned';
                user.status = bannedNow ? 'Banned' : 'Active';
                user.banned = bannedNow;
                user.ban_reason = bannedNow ? this.banReason.trim() : null;
                user.canBan = true;
                this.banningId = null;
                this.banReason = '';
            } else {
                const result = await response.json();
                alert(Object.values(result.errors ?? {}).flat()[0] || result.message || 'User could not be updated.');
            }
        },

        async remove(id) {
            const user = this.users.find(u => u.id === id);
            if (!user?.canDelete) return;

            if (!confirm('Hapus user ini? User tidak login selama 1 tahun.')) return;

            const response = await fetch(this.deleteUrl.replace('__USER__', id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
            });

            if (response.ok) {
                this.users = this.users.filter(u => u.id !== id);
            } else {
                const result = await response.json();
                alert(result.message ?? 'User could not be deleted.');
            }
        },

        async verifyUser(user) {
            if (!user.canVerify) return;

            const response = await fetch(this.verifyUrl.replace('__USER__', user.id), {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
            });

            if (response.ok) {
                user.status = 'Active';
                user.canVerify = false;
                user.canBan = true;
            } else {
                const result = await response.json();
                alert(result.message ?? 'User could not be verified.');
            }
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
                            :class="u.status === 'Active' ? 'bg-income-soft text-income' : (u.status === 'Banned' ? 'bg-expense-soft text-expense' : (u.status === 'Unverified' ? 'bg-warn-soft text-warn' : 'bg-gray-100 text-muted'))"
                            x-text="u.status"
                        ></span>
                        <template x-if="u.banned && u.ban_days_remaining !== null">
                            <div class="mt-1 text-xs text-muted">
                                <span x-text="u.ban_days_remaining + ' hari tersisa'"></span>
                            </div>
                        </template>
                    </span>

                    <div class="flex items-center gap-2">

                        <button type="button" title="Verify" x-show="u.canVerify" x-on:click="verifyUser(u)"
                                class="flex h-8 w-8 items-center justify-center rounded-md bg-brand-soft text-brand hover:opacity-80">
                            <x-icon name="check" size="h-4 w-4" />
                        </button>

                        <button type="button" x-show="u.banned" title="Unban User" x-on:click="toggleStatus(u)" :disabled="!u.canToggle"
                                class="flex h-8 w-8 items-center justify-center rounded-md bg-income-soft text-income hover:opacity-80" x-cloak>
                            <x-icon name="check" size="h-4 w-4" />
                        </button>

                        <button type="button" x-show="!u.banned && u.canBan" title="Suspend User" x-on:click="openBanModal(u)"
                                class="flex h-8 w-8 items-center justify-center rounded-md bg-expense-soft text-expense hover:opacity-80">
                            <x-icon name="ban" size="h-4 w-4" />
                        </button>

                        <button type="button" title="View ban details" x-show="u.banned && u.ban_reason" x-on:click="alert('Alasan suspend: ' + u.ban_reason)"
                                class="flex h-8 w-8 items-center justify-center rounded-md bg-warn-soft text-warn hover:opacity-80" x-cloak>
                            <x-icon name="alert-circle" size="h-4 w-4" />
                        </button>

                        <button type="button" title="Delete User (1 tahun tidak login)" x-show="u.canDelete" x-on:click="remove(u.id)"
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
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-soft font-medium text-brand">{{ $users->currentPage() }}</span>
                @if ($users->hasMorePages())
                    <a href="{{ $users->nextPageUrl() }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-white">
                        <x-icon name="arrow-right" size="h-3.5 w-3.5" />
                    </a>
                @else
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-white">
                        <x-icon name="arrow-right" size="h-3.5 w-3.5" />
                    </span>
                @endif
            </div>

        </div>

    </x-card>


    {{-- Modal suspend user --}}
    <div x-cloak x-show="banningId !== null" x-transition.opacity
         x-on:keydown.escape.window="banningId = null"
         x-on:click.self="banningId = null"
         class="fixed inset-0 z-30 flex items-center justify-center bg-black/30 p-4">

        <div class="w-full max-w-sm rounded-2xl border border-line bg-white p-6 shadow-xl">

            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-2xl font-bold">Suspend User</h3>
                    <p class="mt-1 text-xs text-muted">Berikan alasan suspend untuk akun ini.</p>
                </div>

                <button type="button" x-on:click="banningId = null"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-line hover:bg-page">
                    <x-icon name="x" size="h-4 w-4" />
                </button>
            </div>

            <div class="mt-5 space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Alasan Suspend (30 hari)</label>
                    <textarea x-model="banReason" rows="4" required placeholder="Jelaskan alasan suspend akun ini..." class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-sm text-ink placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"></textarea>
                    <p class="mt-1 text-xs text-muted">User akan melihat: "Akun Anda telah diblokir selama 30 hari karena [alasan ini]"</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-button variant="outline" x-on:click="banningId = null">Cancel</x-button>
                <x-button variant="danger" x-on:click="submitBan()">Suspend User</x-button>
            </div>

        </div>

    </div>

</div>

@endsection