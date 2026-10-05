@extends('layouts.main')

@section('title', 'Notifications')

@section('content')

@php
    // DATA DUMMY: nanti diganti data dari backend
    $notifications = [
        ['id' => 1, 'type' => 'transaction', 'title' => 'Transaction success!', 'text' => 'Grocery shopping Rp 245.000 was recorded from DANA.', 'scope' => 'Across all wallets'],
        ['id' => 2, 'type' => 'transfer', 'title' => 'Transfer success!', 'text' => 'Rp 450.000 was moved from DANA to GoPay.', 'scope' => 'Across all wallets'],
        ['id' => 3, 'type' => 'transaction', 'title' => 'Transaction success!', 'text' => 'Morning coffee Rp 40.000 was recorded from Cash.', 'scope' => 'Across all wallets'],
        ['id' => 4, 'type' => 'transaction', 'title' => 'Transaction success!', 'text' => 'Internet bill Rp 325.000 was recorded from DANA.', 'scope' => 'Across all wallets'],
        ['id' => 5, 'type' => 'transaction', 'title' => 'Transaction success!', 'text' => 'Project payment Rp 5.000.000 was received in GoPay.', 'scope' => 'Across all wallets'],
        ['id' => 6, 'type' => 'saving', 'title' => 'Savings goal reached 🤩', 'text' => 'Congratulations, one of your saving targets is fully funded.', 'scope' => 'Across all wallets',
            'goal' => ['name' => 'New Laptop', 'note' => 'Work equipment', 'saved' => 15000000, 'target' => 15000000]],
        ['id' => 7, 'type' => 'transaction', 'title' => 'Transaction success!', 'text' => 'Dinner with friends Rp 420.000 was recorded from Cash.', 'scope' => 'Across all wallets'],
    ];
@endphp

<div
    x-data="{
        items: @js($notifications),
        read: JSON.parse(localStorage.getItem('readNotifications') || '[]'),
        filter: 'all',
        onlyUnread: false,
        open: null,

        get unreadCount() {
            return this.items.filter(i => !this.read.includes(i.id)).length;
        },

        get visible() {
            return this.items.filter(i =>
                (this.filter === 'all' || i.type === this.filter) &&
                (!this.onlyUnread || !this.read.includes(i.id))
            );
        },

        isRead(id) {
            return this.read.includes(id);
        },

        save() {
            localStorage.setItem('readNotifications', JSON.stringify(this.read));
            window.dispatchEvent(new CustomEvent('notifications-updated'));
        },

        markRead(id) {
            if (!this.read.includes(id)) {
                this.read.push(id);
                this.save();
            }
        },

        markAllRead() {
            this.read = this.items.map(i => i.id);
            this.save();
        },

        toggle(id) {
            this.open = this.open === id ? null : id;
            this.markRead(id);
        },

        rp(n) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
        },

        pct(g) {
            return g.target ? Math.min(100, Math.round(g.saved / g.target * 100)) : 0;
        }
    }"
>

    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-3xl font-bold">Notifications</h1>
            <p class="mt-1 text-sm text-muted">Stay up to date with your money activity.</p>
        </div>

        <div class="flex items-center gap-3">

            {{-- Filter jenis --}}
            <div class="relative">
                <select
                    x-model="filter"
                    class="appearance-none rounded-lg border border-line bg-white py-2.5 pl-4 pr-10 text-sm font-medium focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                >
                    <option value="all">All</option>
                    <option value="transaction">Transactions</option>
                    <option value="transfer">Transfers</option>
                    <option value="saving">Savings</option>
                </select>

                <x-icon name="chevron-down" size="h-4 w-4"
                    class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted" />
            </div>

            {{-- Read / Unread --}}
            <div class="flex items-center gap-1 rounded-lg border border-line bg-white p-1 text-sm">

                <button
                    type="button"
                    x-on:click="markAllRead()"
                    class="flex items-center gap-1.5 rounded-md px-3 py-1.5 font-semibold hover:bg-page"
                >
                    Read <x-icon name="check" size="h-4 w-4" />
                </button>

                <span class="h-5 w-px bg-line"></span>

                <button
                    type="button"
                    x-on:click="onlyUnread = !onlyUnread"
                    class="flex items-center gap-2 rounded-md px-3 py-1.5 transition"
                    :class="onlyUnread ? 'bg-brand-soft font-semibold text-brand' : 'text-muted hover:bg-page'"
                >
                    Unread
                    <span
                        x-show="unreadCount > 0"
                        x-text="unreadCount > 9 ? '9+' : unreadCount"
                        class="flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white"
                    ></span>
                </button>

            </div>

        </div>
    </div>


    {{-- Daftar notifikasi --}}
    <x-card class="mt-8 overflow-hidden">

        <template x-for="n in visible" :key="n.id">

            <div class="border-b border-line last:border-b-0">

                {{-- Baris utama --}}
                <button
                    type="button"
                    x-on:click="toggle(n.id)"
                    class="flex w-full items-start gap-4 px-6 py-5 text-left transition hover:bg-page/60"
                >

                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span
                                x-show="!isRead(n.id)"
                                class="h-2 w-2 rounded-full bg-brand"
                            ></span>

                            <h2
                                class="text-lg"
                                :class="isRead(n.id) ? 'font-medium' : 'font-bold'"
                                x-text="n.title"
                            ></h2>
                        </div>

                        <p class="mt-1.5 text-sm text-muted" x-text="n.text"></p>
                    </div>

                    <div class="flex flex-col items-end gap-4">
                        <span class="text-xs text-muted" x-text="n.scope"></span>

                        <span class="text-muted">
                            <x-icon name="chevron-down" size="h-4 w-4" x-show="open !== n.id" />
                            <x-icon name="chevron-up" size="h-4 w-4" x-show="open === n.id" x-cloak />
                        </span>
                    </div>

                </button>

                {{-- Detail --}}
                <div x-cloak x-show="open === n.id" x-transition class="px-4 pb-4">

                    <div class="rounded-xl px-6 py-5" :class="n.goal ? 'bg-[#c8c8c8]' : 'bg-page'">

                        {{-- Detail target tabungan --}}
                        <template x-if="n.goal">
                            <div class="grid grid-cols-[1.4fr_3fr_1.4fr] items-center gap-6">

                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-soft text-brand">
                                        <x-icon name="target" size="h-4 w-4" />
                                    </span>
                                    <div class="leading-tight">
                                        <p class="text-sm font-semibold" x-text="n.goal.name"></p>
                                        <p class="text-xs text-muted" x-text="n.goal.note"></p>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between text-xs text-muted">
                                        <span x-text="rp(n.goal.saved) + ' saved'"></span>
                                        <span class="rounded-full bg-white px-2.5 py-1 font-medium text-brand"
                                              x-text="pct(n.goal) + '%'"></span>
                                    </div>
                                    <div class="mt-2 h-2 rounded-full bg-white/60">
                                        <div class="h-2 rounded-full bg-brand" :style="'width: ' + pct(n.goal) + '%'"></div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-3">
                                    <div class="text-right leading-tight">
                                        <p class="text-sm font-semibold" x-text="rp(n.goal.saved)"></p>
                                        <p class="text-xs text-muted" x-text="'of ' + rp(n.goal.target)"></p>
                                    </div>
                                    <x-icon name="check" class="text-income" />
                                </div>

                            </div>
                        </template>

                        {{-- Detail notifikasi biasa --}}
                        <template x-if="!n.goal">
                            <p class="text-sm text-muted">
                                This activity was added to your history. You can review it any time in Transactions.
                            </p>
                        </template>

                    </div>

                </div>

            </div>

        </template>

        {{-- Kosong --}}
        <template x-if="visible.length === 0">
            <p class="px-6 py-12 text-center text-sm text-muted">No notifications to show.</p>
        </template>

    </x-card>

</div>

@endsection