@extends('layouts.main')

@section('title', __('notifications.title'))

@section('content')

<div
    x-data="{
        items: @js($notifications),
        read: @js($readIds),
        status: 'all',
        category: 'all',
        search: '',
        open: null,
        csrfToken: @js(csrf_token()),
        readUrl: @js(route('notifications.read', ['notifikasi' => '__ID__'])),
        readAllUrl: @js(route('notifications.read-all')),

        get unreadCount() {
            return this.items.filter(i => !this.read.includes(i.id)).length;
        },

        get visible() {
            const q = this.search.trim().toLowerCase();
            return this.items.filter(i => {
                // 1. Status Filter (All / Unread / Read)
                const isItemRead = this.read.includes(i.id);
                const matchesStatus =
                    this.status === 'all' ||
                    (this.status === 'unread' && !isItemRead) ||
                    (this.status === 'read' && isItemRead);

                // 2. Category Filter (All / Goal / Transfer / Transaction / Verification / System)
                const matchesCategory =
                    this.category === 'all' ||
                    (this.category === 'goal' && (i.type === 'goal' || i.type === 'saving' || i.goal !== null || i.type === 'target')) ||
                    (this.category === 'transfer' && i.type === 'transfer') ||
                    (this.category === 'transaction' && i.type === 'transaction') ||
                    (this.category === 'verification' && i.type === 'verification') ||
                    (this.category === 'system' && i.type === 'system');

                // 3. Search Filter
                const matchesSearch = !q ||
                    (i.title && i.title.toLowerCase().includes(q)) ||
                    (i.text && i.text.toLowerCase().includes(q)) ||
                    (i.scope && i.scope.toLowerCase().includes(q)) ||
                    (i.goal && (
                        (i.goal.name && i.goal.name.toLowerCase().includes(q)) ||
                        (i.goal.note && i.goal.note.toLowerCase().includes(q))
                    ));

                return matchesStatus && matchesCategory && matchesSearch;
            });
        },

        isRead(id) {
            return this.read.includes(id);
        },

        async markRead(id) {
            if (this.read.includes(id)) return;

            const response = await fetch(this.readUrl.replace('__ID__', id), {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
            });

            if (response.ok) {
                this.read.push(id);
                window.dispatchEvent(new CustomEvent('notifications-updated', { detail: { unread: this.unreadCount } }));
            }
        },

        async markAllRead() {
            const response = await fetch(this.readAllUrl, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
            });

            if (response.ok) {
                this.read = this.items.map(i => i.id);
                window.dispatchEvent(new CustomEvent('notifications-updated', { detail: { unread: 0 } }));
            }
        },

        toggle(id) {
            this.open = this.open === id ? null : id;
            this.markRead(id);
        },

        rp(n) {
            return window.financeMoney?.format ? window.financeMoney.format(n) : ('Rp ' + new Intl.NumberFormat('id-ID').format(n));
        },

        pct(g) {
            return window.financeMoney.compare(g.target, '0') > 0
                ? Math.min(100, window.financeMoney.percent(g.saved, g.target))
                : 100;
        }
    }"
>

    {{-- Header --}}
    <div>
        <h1 class="text-3xl font-bold text-ink">{{ __('notifications.title') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ __('notifications.subtitle') }}</p>
    </div>

    {{-- Baris 1: Filter Status (Segmented Control: All | Unread | Read) --}}
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <div class="inline-flex items-center gap-1 rounded-xl border border-line bg-white p-1 text-sm shadow-sm">
            {{-- All Tab --}}
            <button
                type="button"
                @click="status = 'all'"
                class="flex items-center gap-2 rounded-lg px-3 sm:px-3.5 py-1.5 text-xs sm:text-sm font-semibold transition cursor-pointer"
                :class="status === 'all' ? 'bg-brand-soft text-brand font-bold shadow-xs' : 'text-muted hover:text-ink hover:bg-page font-medium'"
            >
                <span>All</span>
                <span
                    x-show="items.length > 0"
                    x-text="items.length > 99 ? '99+' : items.length"
                    class="flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[10px] font-bold transition"
                    :class="status === 'all' ? 'bg-brand text-white' : 'bg-page text-muted'"
                ></span>
            </button>

            <span class="h-4 w-px bg-line"></span>

            {{-- Unread Tab --}}
            <button
                type="button"
                @click="status = 'unread'"
                class="flex items-center gap-2 rounded-lg px-3 sm:px-3.5 py-1.5 text-xs sm:text-sm font-semibold transition cursor-pointer"
                :class="status === 'unread' ? 'bg-brand-soft text-brand font-bold shadow-xs' : 'text-muted hover:text-ink hover:bg-page font-medium'"
            >
                <span>Unread</span>
                <span
                    x-show="unreadCount > 0"
                    x-text="unreadCount > 9 ? '9+' : unreadCount"
                    class="flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white shadow-xs"
                ></span>
            </button>

            <span class="h-4 w-px bg-line"></span>

            {{-- Read Tab --}}
            <button
                type="button"
                @click="status = 'read'"
                class="flex items-center gap-2 rounded-lg px-3 sm:px-3.5 py-1.5 text-xs sm:text-sm font-semibold transition cursor-pointer"
                :class="status === 'read' ? 'bg-brand-soft text-brand font-bold shadow-xs' : 'text-muted hover:text-ink hover:bg-page font-medium'"
            >
                <span>Read</span>
            </button>
        </div>

        {{-- Mark all as read button --}}
        <button
            type="button"
            x-show="unreadCount > 0"
            x-cloak
            x-on:click="markAllRead()"
            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs sm:text-sm font-medium text-brand hover:text-brand-dark hover:bg-brand-soft/60 transition cursor-pointer"
        >
            <x-icon name="check" size="h-4 w-4" />
            <span>{{ __('notifications.mark_all_read') }}</span>
        </button>
    </div>

    {{-- Baris 2: Search notifications... (kiri) | Category ▼ (kanan) --}}
    <div class="mt-4 flex items-center gap-2 sm:gap-3">
        {{-- Search Input (Kiri) --}}
        <div class="relative flex-1 min-w-0">
            <x-icon
                name="search"
                size="h-4 w-4"
                class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted"
            />
            <input
                type="text"
                x-model="search"
                placeholder="Search notifications..."
                class="w-full rounded-xl border border-line bg-white py-2.5 pl-10 pr-9 text-sm text-ink placeholder:text-muted/70 shadow-sm transition hover:border-brand/40 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
            />
            <button
                x-show="search.length > 0"
                x-cloak
                @click="search = ''"
                type="button"
                aria-label="Clear search"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-ink cursor-pointer"
            >
                <x-icon name="x" size="h-4 w-4" />
            </button>
        </div>

        {{-- Dropdown Category (Kanan) --}}
        <div
            class="relative shrink-0"
            x-data="{
                open: false,
                dropUp: false,
                categories: [
                    { key: 'all', label: 'All' },
                    { key: 'goal', label: 'Goal' },
                    { key: 'transfer', label: 'Transfer' },
                    { key: 'transaction', label: 'Transaction' },
                    { key: 'verification', label: 'Verification' },
                    { key: 'system', label: 'System' }
                ],
                toggle(el) {
                    this.open = !this.open;
                    if (this.open) {
                        const rect = el.getBoundingClientRect();
                        this.dropUp = (window.innerHeight - rect.bottom) < 270;
                    }
                }
            }"
            @click.outside="open = false"
            @keydown.escape.window="open = false"
        >
            <button
                type="button"
                @click="toggle($el)"
                class="flex items-center justify-between gap-2 sm:gap-3 rounded-xl border border-line bg-white py-2.5 px-3 sm:px-4 text-sm font-medium text-ink shadow-sm transition hover:border-brand/40 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 min-w-[105px] sm:w-36 cursor-pointer"
                :class="open ? 'border-brand ring-2 ring-brand/20' : ''"
            >
                <span x-text="category === 'all' ? 'Category' : (categories.find(c => c.key === category)?.label ?? 'Category')" class="truncate"></span>
                <x-icon name="chevron-down" size="h-3.5 w-3.5" class="text-muted shrink-0 transition-transform duration-200" x-bind:class="open ? 'rotate-180' : ''" />
            </button>

            <div
                x-show="open"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="absolute right-0 z-50 w-40 rounded-xl border border-line bg-white p-1 shadow-xl ring-1 ring-black/5"
                :class="dropUp ? 'bottom-full mb-2 origin-bottom-right' : 'top-full mt-2 origin-top-right'"
            >
                <template x-for="c in categories" :key="c.key">
                    <button
                        type="button"
                        @click="category = c.key; open = false"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm transition text-left cursor-pointer"
                        :class="category === c.key ? 'bg-brand-soft text-brand font-semibold' : 'text-ink hover:bg-page font-medium'"
                    >
                        <span x-text="c.label"></span>
                        <x-icon x-show="category === c.key" name="check" size="h-3.5 w-3.5" class="text-brand shrink-0" />
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- Daftar notifikasi --}}
    <x-card class="mt-6 overflow-hidden">

        <template x-for="n in visible" :key="n.id">

            <div class="border-b border-line last:border-b-0">

                {{-- Baris utama --}}
                <button
                    type="button"
                    x-on:click="toggle(n.id)"
                    class="flex w-full items-start gap-4 px-6 py-5 text-left transition hover:bg-page/60 cursor-pointer"
                >

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span
                                x-show="!isRead(n.id)"
                                class="h-2 w-2 rounded-full bg-brand shrink-0"
                            ></span>

                            <h2
                                class="text-base sm:text-lg"
                                :class="isRead(n.id) ? 'font-medium' : 'font-bold'"
                                x-text="n.title"
                            ></h2>

                            <template x-if="n.goal">
                                <span class="rounded-full bg-brand-soft px-2.5 py-0.5 text-xs font-semibold text-brand">
                                    Goal
                                </span>
                            </template>
                        </div>

                        <p class="mt-1.5 text-sm text-muted" x-text="n.text"></p>
                    </div>

                    <div class="flex flex-col items-end gap-4 shrink-0">
                        <span class="text-xs text-muted" x-text="n.scope"></span>

                        <span class="text-muted">
                            <x-icon name="chevron-down" size="h-4 w-4" x-show="open !== n.id" />
                            <x-icon name="chevron-up" size="h-4 w-4" x-show="open === n.id" x-cloak />
                        </span>
                    </div>

                </button>

                {{-- Detail --}}
                <div x-cloak x-show="open === n.id" x-transition class="px-4 pb-4">

                    <div class="rounded-2xl px-5 py-4 transition-all" :class="n.goal ? 'bg-slate-200/80 border border-slate-300/60' : 'bg-page'">

                        {{-- Savings goal detail --}}
                        <template x-if="n.goal">
                            <div class="flex flex-col gap-3.5 sm:grid sm:grid-cols-[auto_1fr_auto] sm:items-center sm:gap-6">

                                {{-- Goal icon and title --}}
                                <div class="flex items-center justify-between sm:justify-start gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-brand shadow-sm ring-1 ring-black/5">
                                            <x-icon name="target" size="h-5 w-5" />
                                        </span>
                                        <div class="leading-tight truncate">
                                            <p class="text-sm font-bold text-ink truncate" x-text="n.goal.name"></p>
                                            <p class="text-xs text-muted truncate" x-text="n.goal.note"></p>
                                        </div>
                                    </div>
                                    {{-- Goal completion badge on mobile --}}
                                    <span class="sm:hidden shrink-0 rounded-full bg-white px-2.5 py-0.5 text-xs font-bold text-brand shadow-xs ring-1 ring-black/5"
                                          x-text="pct(n.goal) + '%'"></span>
                                </div>

                                {{-- Progress bar and saved amount --}}
                                <div class="w-full">
                                    <div class="flex items-center justify-between text-xs text-muted">
                                        <span class="font-medium"                                         x-text="rp(n.goal.saved) + ' saved'"></span>
                                        <span class="hidden sm:inline-block rounded-full bg-white px-2.5 py-0.5 text-xs font-bold text-brand shadow-xs ring-1 ring-black/5"
                                              x-text="pct(n.goal) + '%'"></span>
                                    </div>
                                    <div class="mt-2 h-2 w-full rounded-full bg-white/80 overflow-hidden ring-1 ring-black/5">
                                        <div class="h-full rounded-full bg-brand transition-all duration-300" :style="'width: ' + pct(n.goal) + '%'"></div>
                                    </div>
                                </div>

                                {{-- Goal amount and completion icon --}}
                                <div class="flex items-center justify-between sm:justify-end gap-3 pt-2 sm:pt-0 border-t border-black/5 sm:border-t-0">
                                    <div class="text-left sm:text-right leading-tight">
                                        <p class="text-sm sm:text-base font-bold text-ink" x-text="rp(n.goal.saved)"></p>
                                        <p class="text-xs text-muted" x-text="'of ' + rp(n.goal.target)"></p>
                                    </div>
                                    <x-icon name="check" size="h-6 w-6" class="text-emerald-500 shrink-0 stroke-[2.5]" />
                                </div>

                            </div>
                        </template>

                        {{-- Detail notifikasi biasa --}}
                        <template x-if="!n.goal">
                            <p class="text-sm text-muted">
                                This activity was added to your history. You can view it again in Transactions.
                            </p>
                        </template>

                    </div>

                </div>

            </div>

        </template>

        {{-- Empty state --}}
        <template x-if="visible.length === 0">
            <div class="px-6 py-12 text-center text-sm text-muted">
                <template x-if="search.trim()">
                    <p>No notifications match "<span class="font-semibold text-ink" x-text="search"></span>".</p>
                </template>
                <template x-if="!search.trim()">
                    <p>There are no notifications to display.</p>
                </template>
            </div>
        </template>

    </x-card>

</div>

@endsection