<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Finance App')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-page font-sans text-ink antialiased">

    <header class="border-b border-line bg-white">
        <div class="mx-auto flex h-16 max-w-[1312px] items-center justify-between px-8">
            <a href="/dashboard"><x-logo /></a>

            <nav class="flex items-center gap-1 text-sm font-medium">
                @foreach ([
                    ['Dashboard', '/dashboard', ['dashboard*', 'transfer*']],
                    ['Transactions', '/transactions', ['transactions*']],
                    ['Saving', '/saving', ['saving*']],
                ] as [$label, $url, $patterns])
                    <a href="{{ $url }}"
                       class="rounded-lg px-4 py-2 transition {{ request()->is(...$patterns) ? 'bg-brand-soft text-brand' : 'text-muted hover:text-ink' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            <div class="flex items-center gap-4">

                {{-- Lonceng + titik merah berkedip --}}
                <a href="/notifications"
                   x-data="{
                       unread: 0,
                       total: 7, {{-- Backend: ganti dengan jumlah notifikasi belum dibaca --}}
                       refresh() {
                           const read = JSON.parse(localStorage.getItem('readNotifications') || '[]');
                           this.unread = Math.max(0, this.total - read.length);
                       },
                       init() { this.refresh(); }
                   }"
                   x-on:notifications-updated.window="refresh()"
                   class="relative flex h-10 w-10 items-center justify-center rounded-lg bg-page text-ink">

                    <x-icon name="bell" />

                    <span x-cloak x-show="unread > 0" class="absolute right-2 top-2 flex h-2.5 w-2.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75 motion-reduce:animate-none"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-600"></span>
                    </span>
                </a>

                <div class="relative" x-data="{ open: false }">
                    <button type="button" x-on:click="open = !open" class="flex items-center gap-3">
                        <span class="h-10 w-10 rounded-full bg-brand-soft"></span>
                        <span class="text-left leading-tight">
                            <span class="block text-sm font-semibold">Alex Morgan</span>
                            <span class="block text-xs text-muted">Personal account</span>
                        </span>
                        <svg class="h-4 w-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-cloak x-show="open" x-on:click.outside="open = false"
                         class="absolute right-0 z-20 mt-2 w-44 rounded-xl border border-line bg-white p-1 text-sm shadow-lg">
                        <a href="/account" class="block rounded-lg px-3 py-2 hover:bg-page">Account</a>
                        {{-- Backend: ganti jadi form POST logout + @csrf --}}
                        <a href="/login" class="block rounded-lg px-3 py-2 text-expense hover:bg-page">Sign out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-[1312px] px-8 py-10">
        @yield('content')
    </main>

</body>
</html>