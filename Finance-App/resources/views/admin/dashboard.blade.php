@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')

@php
    $chartMaximum = max(1, ...$chart['total'], ...$chart['fresh']);
    $chartMaximum = (int) (ceil($chartMaximum / 5) * 5);
    $chartTicks = array_unique(array_map(fn ($step) => max(1, (int) round($chartMaximum * $step / 4)), [1, 2, 3, 4]));
    $userRows = $users->map(fn ($user) => [
        'id' => $user->id_user,
        'name' => $user->nama,
        'email' => $user->email,
        'status' => $user->status ? 'Active' : 'Inactive',
        'canToggle' => ! $user->isAdmin() && ! $user->is(auth()->user()),
        'canBan' => ! $user->isAdmin() && ! $user->is(auth()->user()) && $user->email_verified_at !== null,
        'canManage' => ! $user->isAdmin() && ! $user->is(auth()->user()) && $user->email_verified_at !== null,
        'canVerify' => $user->email_verified_at === null && $user->role === 'user',
        'ban_days_remaining' => $user->banned_at ? floor(max(0, 30 - $user->banned_at->diffInDays(now()))) : null,
        'ban_expires_at' => $user->banned_at ? $user->banned_at->addDays(30)->format('d M Y H:i') : null,
        'ban_reason' => $user->ban_reason,
    ])->values();
@endphp

<div
    x-data="{
        users: @js($userRows),
        total: @js($chart['total']),
        fresh: @js($chart['fresh']),
        days: @js($days),
        csrfToken: @js(csrf_token()),
        statusUrl: @js(route('admin.users.status', ['user' => '__USER__'])),
        banUrl: @js(route('admin.users.ban', ['user' => '__USER__'])),
        verifyUrl: @js(route('admin.users.verify', ['user' => '__USER__'])),

        W: 800, H: 240, padL: 48, padR: 16, padT: 16, padB: 28, max: {{ $chartMaximum }},

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

        async setStatus(user, status) {
            if (!user.canToggle) return;
            if (user.status === status) return;

            const response = await fetch(this.statusUrl.replace('__USER__', user.id), {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
            });

            if (response.ok) user.status = status;
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
            <p class="mt-3 text-4xl font-bold">{{ number_format($summary['total_user']) }}</p>
            <p class="mt-3 text-sm text-muted">Across all platforms</p>
        </x-card>

        <x-card class="p-6">
            <div class="flex items-start justify-between">
                <p class="text-sm text-muted">Online Users</p>
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-income-soft">
                    <span class="h-2.5 w-2.5 rounded-full bg-income"></span>
                </span>
            </div>
            <p class="mt-3 text-4xl font-bold">{{ number_format($summary['user_online']) }}</p>
            <p class="mt-3 text-sm text-muted">Currently active</p>
        </x-card>

        <x-card class="p-6">
            <div class="flex items-start justify-between">
                <p class="text-sm text-muted">New Users This Week</p>
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-soft text-brand">
                    <x-icon name="trending-up" size="h-4 w-4" />
                </span>
            </div>
            <p class="mt-3 text-4xl font-bold">{{ number_format($summary['user_baru_minggu_ini']) }}</p>
            <p class="mt-3 text-sm text-muted">Created in the last 7 days</p>
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
            @foreach ($chartTicks as $tick)
                <line x1="48" x2="784" :y1="py({{ $tick }})" :y2="py({{ $tick }})" stroke="#e5e7eb" />
                <text x="8" :y="py({{ $tick }}) - 4" font-size="11" fill="#6b7280">{{ $tick }}</text>
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

        

            <div class="grid grid-cols-[2fr_2.6fr_1fr] gap-4 bg-gray-50 px-4 py-3 text-xs font-semibold text-muted">
                <span>Name</span>
                <span>Email</span>
                <span>Status</span>
            </div>

            <template x-for="u in users" :key="u.id">
                <div class="grid grid-cols-[2fr_2.6fr_1fr] items-center gap-4 border-t border-line px-4 py-3 text-sm">

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
                </div>
            </template>

        </div>

    </x-card>

</div>

@endsection