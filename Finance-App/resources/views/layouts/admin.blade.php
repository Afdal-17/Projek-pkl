<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') - Finance App</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 font-sans text-ink antialiased" x-data="{ nav: false }" x-on:keydown.escape.window="nav = false">

    {{-- Top bar mobile --}}
    <div class="flex items-center justify-between border-b border-line bg-white px-4 py-3 sm:px-6 lg:hidden">
        <a href="{{ route('admin.dashboard') }}" class="min-w-0 shrink">
            <x-logo />
        </a>
        <button
            type="button"
            x-on:click="nav = true"
            :aria-expanded="nav.toString()"
            aria-label="Open menu"
            title="Menu"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-line bg-white text-muted hover:bg-page hover:text-ink transition active:scale-95 focus:outline-none focus:ring-2 focus:ring-brand/20"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    {{-- Mobile Overlay --}}
    <div
        x-cloak
        x-show="nav"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-black/40 lg:hidden"
        x-on:click="nav = false"
    ></div>

    {{-- Mobile Drawer (slides in from right, matching user drawer) --}}
    <aside
        x-cloak
        x-show="nav"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 z-50 flex w-72 max-w-[85vw] flex-col bg-white p-4 shadow-xl lg:hidden"
    >
        {{-- Mobile Header --}}
        <div class="flex items-center justify-between">
            <x-logo />
            <button
                type="button"
                x-on:click="nav = false"
                aria-label="Close menu"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-line bg-white text-muted transition hover:bg-page hover:text-ink focus:outline-none focus:ring-2 focus:ring-brand/20"
            >
                <x-icon name="x" size="h-4 w-4" />
            </button>
        </div>

        {{-- Mobile Nav Links --}}
        <nav class="mt-6 space-y-1 text-sm font-medium">
            @foreach ([
                ['Dashboard', route('admin.dashboard'), 'admin/dashboard*', 'layout'],
                ['Verify Users', route('admin.verify-users'), 'admin/verify-users*', 'user-check'],
                ['Manage Users', route('admin.users'), 'admin/users*', 'users'],
            ] as [$label, $url, $pattern, $icon])
                <a
                    href="{{ $url }}"
                    x-on:click="nav = false"
                    class="flex items-center gap-3 rounded-lg px-3 py-3 transition {{ request()->is($pattern) ? 'bg-brand-soft text-brand font-semibold' : 'text-muted hover:bg-page' }}"
                >
                    <x-icon :name="$icon" size="h-4 w-4" />
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        {{-- Mobile Admin Profile & Sign Out --}}
        <div class="mt-auto border-t border-line pt-4">
            <a href="{{ route('admin.profile') }}" class="flex items-center gap-3 rounded-lg p-2 hover:bg-page">
                @if (auth()->user()?->avatar_path)
                    <img src="{{ asset('storage/' . auth()->user()->avatar_path) }}" alt="{{ auth()->user()->nama }}" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';" class="h-10 w-10 shrink-0 rounded-full object-cover ring-1 ring-line" />
                    <span class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-brand">{{ strtoupper(substr(trim(auth()->user()?->nama ?? 'A'), 0, 1)) }}</span>
                @else
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-brand">{{ strtoupper(substr(trim(auth()->user()?->nama ?? 'A'), 0, 1)) }}</span>
                @endif
                <span class="min-w-0 leading-tight">
                    <span class="block truncate text-sm font-semibold">{{ auth()->user()?->nama ?? 'Admin' }}</span>
                    <span class="block truncate text-xs text-muted">{{ auth()->user()?->email ?? 'Administrator' }}</span>
                </span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="mt-2 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-expense hover:bg-page">
                    <x-icon name="logout" size="h-4 w-4" />
                    Sign out
                </button>
            </form>
        </div>
    </aside>

    {{-- Desktop Permanent Sidebar --}}
    <aside class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-30 lg:flex lg:w-[216px] lg:flex-col lg:border-r lg:border-line lg:bg-white lg:p-4">
        <div class="flex items-center px-2 pt-2">
            <a href="{{ route('admin.dashboard') }}"><x-logo /></a>
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

        {{-- Desktop Admin Profile & Sign Out --}}
        <div class="mt-auto border-t border-line pt-4">
            <a href="{{ route('admin.profile') }}" class="flex items-center gap-3 rounded-lg p-2 hover:bg-page">
                @if (auth()->user()?->avatar_path)
                    <img src="{{ asset('storage/' . auth()->user()->avatar_path) }}" alt="{{ auth()->user()->nama }}" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';" class="h-10 w-10 shrink-0 rounded-full object-cover ring-1 ring-line" />
                    <span class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-brand">{{ strtoupper(substr(trim(auth()->user()?->nama ?? 'A'), 0, 1)) }}</span>
                @else
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-brand">{{ strtoupper(substr(trim(auth()->user()?->nama ?? 'A'), 0, 1)) }}</span>
                @endif
                <span class="min-w-0 leading-tight">
                    <span class="block truncate text-sm font-semibold">{{ auth()->user()?->nama ?? 'Admin' }}</span>
                    <span class="block truncate text-xs text-muted">{{ auth()->user()?->email ?? 'Administrator' }}</span>
                </span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="mt-2 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-expense hover:bg-page">
                    <x-icon name="logout" size="h-4 w-4" />
                    Sign out
                </button>
            </form>
        </div>
    </aside>

    {{-- Main Content Area --}}
    <main class="p-4 sm:p-6 lg:ml-[216px] lg:p-8">
        @yield('content')
    </main>

    <x-pull-to-refresh />
    <x-custom-dialog />
</body>
</html>