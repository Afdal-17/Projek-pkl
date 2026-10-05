<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') - Finance App</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 font-sans text-ink antialiased">

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 flex w-[216px] flex-col border-r border-line bg-white p-4">

        <a href="/admin/dashboard" class="px-2 pt-2"><x-logo /></a>

        <nav class="mt-8 space-y-1 text-sm font-medium">
            @foreach ([
                ['Dashboard', '/admin/dashboard', 'admin/dashboard*', 'layout'],
                ['Users Manage', '/admin/users', 'admin/users*', 'user'],
            ] as [$label, $url, $pattern, $icon])
                <a href="{{ $url }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 transition {{ request()->is($pattern) ? 'bg-brand-soft text-brand' : 'text-muted hover:bg-page' }}">
                    <x-icon :name="$icon" size="h-4 w-4" />
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        {{-- Profil admin --}}
        <div class="relative mt-auto" x-data="{ open: false }">

            <div x-cloak x-show="open" x-on:click.outside="open = false"
                 class="absolute bottom-full left-0 mb-2 w-full rounded-xl border border-line bg-white p-1 text-sm shadow-lg">
                {{-- Backend: ganti jadi form POST logout + @csrf --}}
                <a href="/login" class="flex items-center gap-2 rounded-lg px-3 py-2 text-expense hover:bg-page">
                    <x-icon name="logout" size="h-4 w-4" /> Sign out
                </a>
            </div>

            <button type="button" x-on:click="open = !open" class="flex w-full items-center gap-3 rounded-lg p-2 text-left hover:bg-page">
                <span class="h-10 w-10 rounded-full bg-brand-soft"></span>
                <span class="leading-tight">
                    <span class="block text-sm font-semibold">Maya Putri</span>
                    <span class="block text-xs text-muted">Admin</span>
                </span>
            </button>
        </div>

    </aside>

    {{-- Konten --}}
    <main class="ml-[216px] p-8">
        @yield('content')
    </main>

</body>
</html>