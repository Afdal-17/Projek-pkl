<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') - Finance App</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 font-sans text-ink antialiased" x-data="{ nav: false }" x-on:keydown.escape.window="nav = false">

    {{-- Top bar mobile --}}
    <div class="flex items-center justify-between border-b border-line bg-white p-4 lg:hidden">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
            <x-logo />
        </a>
        <button type="button" x-on:click="nav = true" class="flex h-10 w-10 items-center justify-center rounded-lg border border-line bg-white text-muted hover:bg-page">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    {{-- Overlay --}}
    <div x-cloak x-show="nav" x-transition.opacity class="fixed inset-0 z-40 bg-black/40 lg:hidden" x-on:click="nav = false"></div>

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] flex-col border-r border-line bg-white p-4 shadow-xl transition-transform duration-200 lg:w-[216px] lg:translate-x-0 lg:shadow-none"
           :class="nav ? 'translate-x-0' : '-translate-x-full'">

        <div class="flex items-center justify-between px-2 pt-2">
            <a href="{{ route('admin.dashboard') }}"><x-logo /></a>
            <button type="button" x-on:click="nav = false" class="flex h-8 w-8 items-center justify-center rounded-lg bg-page text-muted hover:bg-gray-100 lg:hidden">
                <x-icon name="x" size="h-4 w-4" />
            </button>
        </div>

        <nav class="mt-8 space-y-1 text-sm font-medium">
            @foreach ([
                ['Dashboard', route('admin.dashboard'), 'admin/dashboard*', 'layout'],
                ['Verify Users', route('admin.verify-users'), 'admin/verify-users*', 'user-check'],
                ['Manage Users', route('admin.users'), 'admin/users*', 'users'],
            ] as [$label, $url, $pattern, $icon])
                <a href="{{ $url }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 transition {{ request()->is($pattern) ? 'bg-brand-soft text-brand font-semibold' : 'text-muted hover:bg-page hover:text-ink' }}">
                    <x-icon :name="$icon" size="h-4 w-4" />
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        {{-- Profil admin --}}
        <div class="relative mt-auto" x-data="{ open: false }">

            <div x-cloak x-show="open" x-on:click.outside="open = false"
                 class="absolute bottom-full left-0 mb-2 w-full rounded-xl border border-line bg-white p-1 text-sm shadow-lg">
                <a href="{{ route('admin.profile') }}" class="block rounded-lg px-3 py-2 hover:bg-page">Profile</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-expense hover:bg-page">
                        <x-icon name="logout" size="h-4 w-4" /> Sign out
                    </button>
                </form>
            </div>

            <button type="button" x-on:click="open = !open" class="flex w-full items-center gap-3 rounded-lg p-2 text-left hover:bg-page">
                @if (auth()->user()?->avatar_path)
                    <img src="{{ asset('storage/' . auth()->user()->avatar_path) }}" alt="{{ auth()->user()->nama }}" class="h-10 w-10 shrink-0 rounded-full object-cover ring-1 ring-line" />
                @else
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-brand">{{ strtoupper(substr(trim(auth()->user()?->nama ?? 'A'), 0, 1)) }}</span>
                @endif
                <span class="min-w-0 leading-tight">
                    <span class="block truncate text-sm font-semibold">{{ auth()->user()?->nama ?? 'Admin' }}</span>
                    <span class="block truncate text-xs text-muted">Admin</span>
                </span>
            </button>
        </div>

    </aside>

    {{-- Konten --}}
    <main class="p-4 sm:p-6 lg:ml-[216px] lg:p-8">
        @yield('content')
    </main>

</body>
</html>