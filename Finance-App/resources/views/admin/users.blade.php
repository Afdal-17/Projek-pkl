@extends('layouts.admin')

@section('title', __('admin.title'))

@section('content')

@php
    $userRows = $users->getCollection()->map(fn ($user) => [
        'id' => $user->id_user,
        'name' => $user->nama,
        'email' => $user->email,
        'avatar' => $user->avatar_path ? asset('storage/' . $user->avatar_path) : null,
        'status' => $user->banned_at ? __('admin.banned') : ($user->status ? __('admin.active') : 'Inactive'),
        'banned' => $user->banned_at !== null,
        'ban_reason' => $user->ban_reason,
        'banned_at' => $user->banned_at?->toIso8601String(),
        'ban_days_remaining' => $user->banned_at ? floor(max(0, 30 - $user->banned_at->diffInDays(now()))) : null,
        'ban_expires_at' => $user->banned_at?->addDays(30)->format('d M Y H:i'),
        'last_seen_at' => $user->last_seen_at?->toIso8601String(),
        'canToggle' => ! $user->isAdmin() && ! $user->is(auth()->user()) && $user->banned_at !== null,
        'canBan' => ! $user->isAdmin() && ! $user->is(auth()->user()) && $user->status,
        'canDelete' => ! $user->isAdmin() && ! $user->is(auth()->user()),
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
                window.financeAlert({
                    title: 'Suspend Failed',
                    message: Object.values(result.errors ?? {}).flat()[0] || result.message || 'User could not be updated.',
                    variant: 'danger',
                });
            }
        },

        async remove(id) {
            const user = this.users.find(u => u.id === id);
            if (!user?.canDelete) return;

            const confirmed = await window.financeConfirm({
                title: 'Hapus User?',
                message: 'Apakah Anda yakin ingin menghapus akun ' + user.name + '? User ini tidak login selama 1 tahun. Tindakan ini tidak dapat dibatalkan.',
                confirmText: 'Hapus User',
                variant: 'danger',
            });
            if (!confirmed) return;

            const response = await fetch(this.deleteUrl.replace('__USER__', id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
            });

            if (response.ok) {
                this.users = this.users.filter(u => u.id !== id);
                window.financeToast({
                    message: 'User ' + user.name + ' berhasil dihapus.',
                    variant: 'income',
                });
            } else {
                const result = await response.json();
                window.financeAlert({
                    title: 'Delete Failed',
                    message: result.message ?? 'User could not be deleted.',
                    variant: 'danger',
                });
            }
        }
    }"
>

    {{-- Header --}}
    <div>
        <h1 class="text-3xl font-bold">{{ __('admin.title') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ __('admin.subtitle') }}</p>
    </div>


    <x-card class="mt-6 overflow-hidden">

        {{-- Judul + pencarian --}}
        <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">

            <div class="flex items-center gap-3">
                <h2 class="text-xl font-bold">All Users</h2>
                <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-muted"
                      x-text="users.length + ' users'"></span>
            </div>

            <div class="relative w-full sm:w-72">
                <x-icon name="search" size="h-4 w-4"
                    class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />

                <input
                    type="text"
                    x-model="search"
                    placeholder="{{ __('admin.search') }}"
                    class="w-full rounded-lg border border-line bg-white py-2.5 pl-9 pr-3 text-sm placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                >
            </div>

        </div>


        {{-- Tabel --}}
        <div class="border-t border-line">

            <div class="hidden grid-cols-[2fr_2.4fr_1.2fr_1.4fr] items-center gap-4 bg-gray-50 px-6 py-3 text-xs font-semibold text-muted md:grid">
                <span>{{ __('admin.name') }}</span>
                <span>{{ __('admin.email') }}</span>
                <span>{{ __('admin.status') }}</span>
                <span class="text-right md:text-left">{{ __('admin.actions') }}</span>
            </div>

            <template x-for="u in filtered" :key="u.id">
                <div class="border-b border-line p-4 transition hover:bg-gray-50/40 md:border-b-0 md:border-t md:px-6 md:py-3.5 md:grid md:grid-cols-[2fr_2.4fr_1.2fr_1.4fr] md:items-center md:gap-4">

                    {{-- Block 1: Avatar, Name & Email (Mobile: Top row with Status badge at right) --}}
                    <div class="flex items-start justify-between gap-3 min-w-0 md:items-center md:justify-start">
                        <div class="flex items-start gap-3 min-w-0 flex-1 md:items-center">
                            <template x-if="u.banned">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-200 text-xs font-semibold text-gray-500 mt-0.5 md:h-8 md:w-8 md:mt-0"
                                      x-text="u.name ? u.name.charAt(0).toUpperCase() : '?'"></span>
                            </template>
                            <template x-if="!u.banned && u.avatar">
                                <img :src="u.avatar" :alt="u.name" class="h-9 w-9 shrink-0 rounded-full object-cover ring-1 ring-line mt-0.5 md:h-8 md:w-8 md:mt-0">
                            </template>
                            <template x-if="!u.banned && !u.avatar">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-semibold text-brand mt-0.5 md:h-8 md:w-8 md:mt-0"
                                      x-text="u.name ? u.name.charAt(0).toUpperCase() : '?'"></span>
                            </template>

                            <div class="min-w-0 flex-1">
                                <span class="font-medium text-ink break-words leading-snug block text-sm" :title="u.name" x-text="u.name"></span>
                                <span class="text-xs text-muted truncate block mt-0.5 md:hidden" :title="u.email" x-text="u.email || '-'"></span>
                            </div>
                        </div>

                        {{-- Mobile Status Badge (top-right) --}}
                        <div class="md:hidden shrink-0 text-right">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                :class="u.status === 'Active' ? 'bg-income-soft text-income' : (u.status === 'Banned' ? 'bg-expense-soft text-expense' : 'bg-gray-100 text-muted')"
                                x-text="u.status"
                            ></span>
                            <template x-if="u.banned && u.ban_days_remaining !== null">
                                <div class="mt-0.5 text-[10px] text-muted font-medium">
                                    <span x-text="u.ban_days_remaining + ' hari tersisa'"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Block 2: Email (Desktop column) --}}
                    <div class="hidden md:block min-w-0">
                        <span class="truncate block text-sm text-ink" :title="u.email" x-text="u.email || '-'"></span>
                    </div>

                    {{-- Block 3: Status (Desktop column) --}}
                    <div class="hidden md:block">
                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold"
                            :class="u.status === 'Active' ? 'bg-income-soft text-income' : (u.status === 'Banned' ? 'bg-expense-soft text-expense' : 'bg-gray-100 text-muted')"
                            x-text="u.status"
                        ></span>
                        <template x-if="u.banned && u.ban_days_remaining !== null">
                            <div class="mt-1 text-xs text-muted">
                                <span x-text="u.ban_days_remaining + ' hari tersisa'"></span>
                            </div>
                        </template>
                    </div>

                    {{-- Block 4: Actions --}}
                    <div class="mt-3 flex flex-wrap items-center justify-end gap-2 border-t border-line/60 pt-2.5 md:mt-0 md:border-t-0 md:pt-0">

                        {{-- Unban button --}}
                        <button type="button" x-show="u.banned" title="Unban User" x-on:click="toggleStatus(u)" :disabled="!u.canToggle"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-income-soft px-2.5 py-1.5 text-xs font-semibold text-income hover:bg-income hover:text-white transition active:scale-95" x-cloak>
                            <x-icon name="check" size="h-3.5 w-3.5" />
                            <span class="md:hidden">Unban</span>
                        </button>

                        {{-- Suspend button (active users only) --}}
                        <button type="button" x-show="!u.banned && u.status === 'Active' && u.canBan" title="Suspend User" x-on:click="openBanModal(u)"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-expense-soft px-2.5 py-1.5 text-xs font-semibold text-expense hover:bg-expense hover:text-white transition active:scale-95">
                            <x-icon name="ban" size="h-3.5 w-3.5" />
                            <span class="md:hidden">Suspend</span>
                        </button>

                        {{-- View ban details button --}}
                        <button type="button" title="View ban details" x-show="u.banned && u.ban_reason" x-on:click="window.financeAlert({ title: 'Alasan Suspend (' + u.name + ')', message: u.ban_reason, variant: 'warn' })"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-warn-soft px-2.5 py-1.5 text-xs font-semibold text-warn hover:bg-warn hover:text-white transition active:scale-95" x-cloak>
                            <x-icon name="alert-circle" size="h-3.5 w-3.5" />
                            <span class="md:hidden">Alasan</span>
                        </button>

                        {{-- Delete button (inactive or banned users) --}}
                        <button type="button" title="Delete User" x-show="u.status === 'Inactive' || u.banned" x-on:click="remove(u.id)" :disabled="!u.canDelete"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-expense-soft px-2.5 py-1.5 text-xs font-semibold text-expense hover:bg-expense hover:text-white transition active:scale-95">
                            <x-icon name="trash" size="h-3.5 w-3.5" />
                            <span class="md:hidden">Hapus</span>
                        </button>

                    </div>

                </div>
            </template>

            <template x-if="filtered.length === 0">
                <p class="border-t border-line px-6 py-10 text-center text-sm text-muted">No users found.</p>
            </template>

        </div>


        {{-- Footer + pagination (statis dulu) --}}
        <div class="flex flex-col items-center justify-between gap-4 border-t border-line p-4 text-xs text-muted sm:flex-row sm:px-6">

            <span class="text-center sm:text-left" x-text="'Showing ' + filtered.length + ' of ' + users.length + ' users'"></span>

            <div class="w-full sm:w-auto">
                {{-- Desktop Pagination --}}
                <div class="hidden sm:flex flex-wrap items-center justify-center gap-2">
                    @if ($users->onFirstPage())
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-white opacity-50">
                            <x-icon name="arrow-left" size="h-3.5 w-3.5" />
                        </span>
                    @else
                        <a href="{{ $users->previousPageUrl() }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-white hover:bg-page">
                            <x-icon name="arrow-left" size="h-3.5 w-3.5" />
                        </a>
                    @endif
                    
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-soft font-medium text-brand">{{ $users->currentPage() }}</span>
                    
                    @if ($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-white hover:bg-page">
                            <x-icon name="arrow-right" size="h-3.5 w-3.5" />
                        </a>
                    @else
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-white opacity-50">
                            <x-icon name="arrow-right" size="h-3.5 w-3.5" />
                        </span>
                    @endif
                </div>

                {{-- Mobile Pagination (Dots) --}}
                @if (method_exists($users, 'lastPage') && $users->lastPage() > 1)
                <div class="flex sm:hidden items-center justify-center gap-1.5 overflow-x-auto py-2 snap-x hide-scrollbar">
                    @for ($i = 1; $i <= $users->lastPage(); $i++)
                        <a href="{{ $users->url($i) }}" class="h-2 shrink-0 rounded-full transition-all duration-300 snap-center {{ $i == $users->currentPage() ? 'w-4 bg-brand' : 'w-2 bg-gray-200' }}"></a>
                    @endfor
                </div>
                @else
                <div class="flex sm:hidden items-center justify-center gap-2">
                    @if (!$users->onFirstPage())
                        <a href="{{ $users->previousPageUrl() }}" class="h-2 w-2 rounded-full bg-gray-300"></a>
                    @endif
                    <span class="h-2 w-4 rounded-full bg-brand"></span>
                    @if ($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}" class="h-2 w-2 rounded-full bg-gray-300"></a>
                    @endif
                </div>
                @endif
            </div>

        </div>

    </x-card>


    {{-- Modal suspend user --}}
    <div x-cloak x-show="banningId !== null" x-transition.opacity
         x-on:keydown.escape.window="banningId = null"
         x-on:click.self="banningId = null"
         class="fixed inset-0 z-30 flex items-center justify-center bg-black/30 p-4">

        <div class="w-full max-w-sm max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl border border-line bg-white p-6 shadow-xl">

            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-2xl font-bold">Suspend User</h3>
                    <p class="mt-1 text-xs text-muted">Provide the reason for suspending this account.</p>
                </div>

                <button type="button" x-on:click="banningId = null"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-line hover:bg-page">
                    <x-icon name="x" size="h-4 w-4" />
                </button>
            </div>

            <div class="mt-5 space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Reason for Suspension (30 days)</label>
                    <textarea x-model="banReason" rows="4" required placeholder="Explain the suspending reason this acount..." class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-sm text-ink placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"></textarea>
                    <p class="mt-1 text-xs text-muted">The user will see: "Your account has been blocked for 30 days due to [this reason]"</p>
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