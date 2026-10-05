@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')

@php
    // DATA DUMMY: nanti diganti data dari backend
    $users = [
        ['id' => 1, 'name' => 'Maya Putri',     'email' => 'maya.putri@financeapp.id',     'status' => 'Active'],
        ['id' => 2, 'name' => 'Rizky Pratama',  'email' => 'rizky.pratama@financeapp.id',  'status' => 'Active'],
        ['id' => 3, 'name' => 'Siti Aisyah',    'email' => 'siti.aisyah@financeapp.id',    'status' => 'Inactive'],
        ['id' => 4, 'name' => 'Budi Santoso',   'email' => 'budi.santoso@financeapp.id',   'status' => 'Active'],
        ['id' => 5, 'name' => 'Lestari Wijaya', 'email' => 'lestari.wijaya@financeapp.id', 'status' => 'Active'],
    ];
    $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
@endphp

<div
    x-data="{
        users: @js($users),

        {{-- Data grafik: nilai per hari --}}
        total: [320, 430, 560, 780, 980, 1100, 1200],
        fresh: [330, 460, 610, 800, 1010, 1120, 1200],

        W: 800, H: 240, padL: 48, padR: 16, padT: 16, padB: 28, max: 1300,

        px(i) { return this.padL + i * (this.W - this.padL - this.padR) / 6; },
        py(v) { return this.padT + (1 - v / this.max) * (this.H - this.padT - this.padB); },

        path(arr) {
            const p = arr.map((v, i) => [this.px(i), this.py(v)]);
            let d = 'M' + p[0][0] + ',' + p[0][1];

            for (let i = 0; i < p.length - 1; i++) {
                const p0 = p[i - 1] || p[i], p1 = p[i], p2 = p[i + 1], p3 = p[i + 2] || p2;
                const c1x = p1[0] + (p2[0] - p0[0]) / 6, c1y = p1[1] + (p2[1] - p0[1]) / 6;
                const c2x = p2[0] - (p3[0] - p1[0]) / 6, c2y = p2[1] - (p3[1] - p1[1]) / 6;
                d += ' C' + c1x + ',' + c1y + ' ' + c2x + ',' + c2y + ' ' + p2[0] + ',' + p2[1];
            }

            return d;
        },

        setStatus(u, status) { u.status = status; },

        remove(id) {
            if (!confirm('Delete this user?')) return;
            this.users = this.users.filter(u => u.id !== id);
        }
    }"
>

    {{-- Header --}}
    <div>
        <h1 class="text-3xl font-bold">Dashboard</h1>
        <p class="mt-1 text-sm text-muted">Monitor users, activity, and manage accounts.</p>
    </div>


    {{-- Tiga kartu statistik --}}
    <div class="mt-6 grid gap-6 md:grid-cols-3">

        <x-card class="p-6">
            <p class="text-sm text-muted">Total Users</p>
            <p class="mt-3 text-4xl font-bold">1,248</p>
            <p class="mt-3 text-sm text-muted">Across all platforms</p>
        </x-card>

        <x-card class="p-6">
            <div class="flex items-start justify-between">
                <p class="text-sm text-muted">Online Users</p>
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-income-soft">
                    <span class="h-2.5 w-2.5 rounded-full bg-income"></span>
                </span>
            </div>
            <p class="mt-3 text-4xl font-bold">312</p>
            <p class="mt-3 text-sm text-muted">Currently active</p>
        </x-card>

        <x-card class="p-6">
            <div class="flex items-start justify-between">
                <p class="text-sm text-muted">New Users This Week</p>
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-soft text-brand">
                    <x-icon name="trending-up" size="h-4 w-4" />
                </span>
            </div>
            <p class="mt-3 text-4xl font-bold">56</p>
            <p class="mt-3 text-sm text-muted">+12% vs last week</p>
        </x-card>

    </div>


    {{-- Grafik aktivitas --}}
    <x-card class="mt-6 p-6">

        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-xl font-bold">User Activity — Last 7 Days</h2>
                <p class="mt-1 text-sm text-muted">Total and new users by day</p>
            </div>

            <div class="flex items-center gap-4 text-sm text-muted">
                <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 bg-dark"></span> Total Users</span>
                <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 bg-brand"></span> New Users</span>
            </div>
        </div>

        <svg viewBox="0 0 800 240" class="mt-4 w-full rounded-lg border border-line" role="img" aria-label="User activity for the last 7 days">

            {{-- Garis bantu + label sumbu Y --}}
            @foreach ([300, 600, 900, 1200] as $v)
                <line x1="48" x2="784" :y1="py({{ $v }})" :y2="py({{ $v }})" stroke="#e5e7eb" />
                <text x="8" :y="py({{ $v }}) - 4" font-size="11" fill="#6b7280">{{ $v >= 1000 ? number_format($v / 1000, 1) . 'k' : $v }}</text>
            @endforeach

            {{-- Dua garis data --}}
            <path :d="path(total)" fill="none" stroke="#262626" stroke-width="2" stroke-linecap="round" />
            <path :d="path(fresh)" fill="none" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" />

            {{-- Label hari --}}
            @foreach ($days as $i => $day)
                <text :x="px({{ $i }})" y="234" text-anchor="middle" font-size="11" fill="#6b7280">{{ $day }}</text>
            @endforeach

        </svg>

    </x-card>


    {{-- Tabel user --}}
    <x-card class="mt-6 p-6">

        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-bold">Users Manage</h2>
                <span class="text-sm text-muted" x-text="users.length + ' users'"></span>
            </div>

            <a href="/admin/users" class="text-sm font-medium text-brand hover:underline">View all</a>
        </div>

        <div class="mt-4 overflow-hidden rounded-xl border border-line">

            <div class="grid grid-cols-[2fr_2.6fr_1fr_1.3fr] gap-4 bg-gray-50 px-4 py-3 text-xs font-semibold text-muted">
                <span>Name</span>
                <span>Email</span>
                <span>Status</span>
                <span>Actions</span>
            </div>

            <template x-for="u in users" :key="u.id">
                <div class="grid grid-cols-[2fr_2.6fr_1fr_1.3fr] items-center gap-4 border-t border-line px-4 py-3 text-sm">

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

                        <button type="button" title="Activate" x-on:click="setStatus(u, 'Active')"
                                class="flex h-8 w-8 items-center justify-center rounded-md bg-income-soft text-income hover:opacity-80">
                            <x-icon name="check" size="h-4 w-4" />
                        </button>

                        <button type="button" title="Deactivate" x-on:click="setStatus(u, 'Inactive')"
                                class="flex h-8 w-8 items-center justify-center rounded-md bg-warn-soft text-warn hover:opacity-80">
                            <x-icon name="pause" size="h-4 w-4" />
                        </button>

                        <button type="button" title="Delete" x-on:click="remove(u.id)"
                                class="flex h-8 w-8 items-center justify-center rounded-md bg-expense-soft text-expense hover:opacity-80">
                            <x-icon name="trash" size="h-4 w-4" />
                        </button>

                    </div>

                </div>
            </template>

        </div>

    </x-card>

</div>

@endsection