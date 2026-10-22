@php
    $currentUser = auth()->user();

    // User data dari backend
    $headerAvatarUrl = $currentUser && $currentUser->avatar_path
        ? asset('storage/' . $currentUser->avatar_path)
        : null;

    $headerInitials = $currentUser && $currentUser->nama
        ? strtoupper(substr(trim($currentUser->nama), 0, 1))
        : '?';

    // Notification dari backend
    $popupNotifications = $currentUser
        ? $currentUser->notifikasi()
            ->latest('id_notifikasi')
            ->limit(1)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id_notifikasi,
                'title' => $n->display_title,
                'text' => $n->formatted_pesan,
                'scope' => $n->tanggal->diffForHumans(),
                'read' => (bool) $n->sudah_dibaca,
                'type' => $n->display_type,
            ])
        : collect();

    $unreadCount = $currentUser
        ? $currentUser->notifikasi()
            ->where('sudah_dibaca', false)
            ->count()
        : 0;
@endphp

<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="finance-currency" content="{{ $currentUser?->mata_uang ?? 'IDR' }}">
    <meta name="finance-rates" content="{{ json_encode(\App\Services\CurrencyService::rates()) }}">

    <title>@yield('title', 'Finance App')</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-page font-sans text-ink antialiased overflow-x-hidden">

    <header
        class="sticky top-0 z-30 border-b border-line bg-white"
        x-data="{ nav: false }"
        x-on:keydown.escape.window="nav = false"
    >

        <div class="mx-auto flex h-16 max-w-[1312px] items-center justify-between gap-3 px-4 sm:px-8">

            {{-- Logo --}}
            <a
                href="/dashboard"
                class="min-w-0 shrink"
            >
                <x-logo />
            </a>


            {{-- Desktop Navigation --}}
            <nav class="hidden items-center gap-1 text-sm font-medium sm:flex">

                @foreach ([
                    ['Dashboard', '/dashboard', ['dashboard*']],
                    ['Transactions', '/transactions', ['transactions*', 'transfer*']],
                    ['Saving', '/saving', ['saving*']],
                ] as [$label, $url, $patterns])

                    <a
                        href="{{ $url }}"
                        class="rounded-lg px-4 py-2 transition
                        {{ request()->is(...$patterns)
                            ? 'bg-brand-soft text-brand'
                            : 'text-muted hover:text-ink' }}"
                    >
                        {{ $label }}
                    </a>

                @endforeach

            </nav>

            {{-- Right Header --}}
            <div class="flex min-w-0 shrink-0 items-center gap-2 sm:gap-4">


                {{-- Notification --}}
                <div
                    class="relative"
                    x-data="{
                        open: false,
                        unread: {{ $unreadCount }},
                        items: @js($popupNotifications),
                        csrfToken: '{{ csrf_token() }}',

                        refresh(event) {
                            this.unread = event?.detail?.unread ?? this.unread;
                        },

                        async markAsRead(item) {
                            if (item.read) return;

                            item.read = true;
                            this.unread = Math.max(0, this.unread - 1);

                            try {
                                await fetch('/notifications/' + item.id + '/read', {
                                    method: 'PATCH',
                                    headers: {
                                        'X-CSRF-TOKEN': this.csrfToken,
                                        'Accept': 'application/json',
                                    }
                                });

                                window.dispatchEvent(
                                    new CustomEvent('notifications-updated', {
                                        detail: {
                                            unread: this.unread
                                        }
                                    })
                                );
                            } catch (e) {}
                        },

                        async markAllAsRead() {
                            this.items.forEach(item => item.read = true);
                            this.unread = 0;

                            try {
                                await fetch('/notifications/read-all', {
                                    method: 'PATCH',
                                    headers: {
                                        'X-CSRF-TOKEN': this.csrfToken,
                                        'Accept': 'application/json',
                                    }
                                });

                                window.dispatchEvent(
                                    new CustomEvent('notifications-updated', {
                                        detail: {
                                            unread: 0
                                        }
                                    })
                                );
                            } catch (e) {}
                        }
                    }"
                    x-on:notifications-updated.window="refresh($event)"
                >

                    {{-- Bell --}}
                    <button
                        type="button"
                        @click="open = !open"
                        aria-label="Notifications"
                        class="relative flex h-10 w-10 items-center justify-center rounded-lg bg-page text-ink transition hover:bg-line/40"
                    >

                        <x-icon name="bell" />

                        {{-- Notification Indicator --}}
                        <span
                            x-cloak
                            x-show="unread > 0"
                            class="absolute right-2 top-2 flex h-2.5 w-2.5"
                        >
                            <span
                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75 motion-reduce:animate-none"
                            ></span>

                            <span
                                class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-600"
                            ></span>
                        </span>

                    </button>


                    {{-- Notification Dropdown --}}
                    <div
                        x-cloak
                        x-show="open"
                        @click.outside="open = false"
                        x-transition.opacity.scale.origin.top.right
                        class="absolute right-0 z-50 mt-2 w-72 overflow-hidden rounded-xl border border-line bg-white shadow-lg sm:w-80"
                    >

                        {{-- Header --}}
                        <div class="flex items-center justify-between border-b border-line bg-white px-3.5 py-2.5">

                            <div class="flex items-center gap-1.5">

                                <h3 class="text-xs font-bold text-ink">
                                    Notification
                                </h3>

                                <template x-if="unread > 0">
                                    <span
                                        class="rounded-full bg-brand-soft px-1.5 py-0.5 text-[10px] font-semibold text-brand"
                                        x-text="unread"
                                    ></span>
                                </template>

                            </div>

                            <template x-if="unread > 0">
                                <button
                                    type="button"
                                    @click="markAllAsRead()"
                                    class="text-[11px] text-brand hover:underline"
                                >
                                    Mark as read
                                </button>
                            </template>

                        </div>


                        {{-- Notification Items --}}
                        <div>

                            <template
                                x-for="item in items"
                                :key="item.id"
                            >

                                <div
                                    @click="markAsRead(item)"
                                    class="flex cursor-pointer items-start gap-2.5 p-3 transition hover:bg-page"
                                    :class="!item.read ? 'bg-brand-soft/20' : ''"
                                >

                                    {{-- Icon --}}
                                    <span
                                        class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                                        :class="
                                            item.type === 'saving'
                                                ? 'bg-income-soft text-income'
                                                : (
                                                    item.type === 'transfer'
                                                        ? 'bg-brand-soft text-brand'
                                                        : 'bg-expense-soft text-expense'
                                                )
                                        "
                                    >

                                        <x-icon
                                            name="target"
                                            size="h-3.5 w-3.5"
                                            x-show="item.type === 'saving'"
                                        />

                                        <x-icon
                                            name="transfer"
                                            size="h-3.5 w-3.5"
                                            x-show="item.type === 'transfer'"
                                            x-cloak
                                        />

                                        <x-icon
                                            name="bell"
                                            size="h-3.5 w-3.5"
                                            x-show="item.type !== 'saving' && item.type !== 'transfer'"
                                            x-cloak
                                        />

                                    </span>


                                    {{-- Text --}}
                                    <div class="min-w-0 flex-1 leading-tight">

                                        <div class="flex items-center justify-between gap-1">

                                            <p
                                                class="truncate text-xs font-semibold text-ink"
                                                x-text="item.title"
                                            ></p>

                                            <span
                                                class="shrink-0 text-[10px] text-muted"
                                                x-text="item.scope"
                                            ></span>

                                        </div>

                                        <p
                                            class="mt-1 line-clamp-2 text-[11px] leading-relaxed text-muted"
                                            x-text="item.text"
                                        ></p>

                                    </div>


                                    {{-- Unread Dot --}}
                                    <span
                                        x-show="!item.read"
                                        class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand"
                                    ></span>

                                </div>

                            </template>


                            {{-- Empty --}}
                            <template x-if="items.length === 0">

                                <div class="px-4 py-5 text-center text-xs text-muted">
                                    No new notifications.
                                </div>

                            </template>

                        </div>


                        {{-- Footer --}}
                        <div class="border-t border-line bg-page/50 px-3 py-2 text-center">

                            <a
                                href="/notifications"
                                @click="open = false"
                                class="inline-flex items-center justify-center gap-1 text-[11px] font-semibold text-brand hover:underline"
                            >
                                <span>View all</span>
                                <x-icon name="arrow-right" size="h-3 w-3" />
                            </a>

                        </div>

                    </div>

                </div>


                {{-- Account Desktop --}}
                <div
                    class="relative hidden sm:block"
                    x-data="{ open: false }"
                >

                    <button
                        type="button"
                        x-on:click="open = !open"
                        aria-label="Account menu"
                        class="flex min-w-0 items-center gap-3"
                    >

                        {{-- Avatar --}}
                        @if ($headerAvatarUrl)

                            <img
                                src="{{ $headerAvatarUrl }}"
                                alt="{{ $currentUser->nama }}"
                                class="h-10 w-10 shrink-0 rounded-full object-cover ring-1 ring-line"
                            >

                        @else

                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-brand">
                                {{ $headerInitials }}
                            </span>

                        @endif
                        {{-- User Info --}}
                        <span class="hidden min-w-0 text-left leading-tight sm:block">
                            <span class="block truncate text-sm font-semibold">
                                {{ $currentUser?->nama ?? 'User' }}
                            </span>
                            <span class="block max-w-[160px] truncate text-xs text-muted">
                                {{ $currentUser?->email ?? 'Personal account' }}
                            </span>
                        </span>

                        {{-- Arrow --}}
                        <svg
                            class="hidden h-4 w-4 shrink-0 text-muted sm:block"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>


                    {{-- Account Dropdown --}}
                    <div
                        x-cloak
                        x-show="open"
                        x-on:click.outside="open = false"
                        class="absolute right-0 z-50 mt-2 w-44 rounded-xl border border-line bg-white p-1 text-sm shadow-lg"
                    >

                        <a
                            href="/account"
                            class="block rounded-lg px-3 py-2 hover:bg-page"
                        >
                            Account
                        </a>

                        <form
                            method="POST"
                            action="{{ route('logout') }}"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="block w-full rounded-lg px-3 py-2 text-left text-expense hover:bg-page"
                            >
                                Sign out
                            </button>

                        </form>

                    </div>

                </div>


                {{-- Hamburger Mobile --}}
                <button
                    type="button"
                    x-on:click="nav = true"
                    :aria-expanded="nav.toString()"
                    aria-label="Open menu"
                    title="Menu"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-line bg-white text-muted transition hover:bg-page hover:text-ink active:scale-95 focus:outline-none focus:ring-2 focus:ring-brand/20 sm:hidden"
                >

                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                    >
                        <path d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>

                </button>

            </div>

        </div>


        {{-- Mobile Navigation --}}
        <div
            x-cloak
            x-show="nav"
            x-transition.opacity
            class="fixed inset-0 z-40 sm:hidden"
        >

            {{-- Overlay --}}
            <div
                class="absolute inset-0 bg-black/40"
                x-on:click="nav = false"
            ></div>


            {{-- Side Menu --}}
            <aside
                class="absolute inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col bg-white p-4 shadow-xl"
                x-show="nav"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
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


                {{-- Mobile Navigation --}}
                <nav class="mt-6 space-y-1 text-sm font-medium">

                    @foreach ([
                        ['Dashboard', '/dashboard', ['dashboard*'], 'layout'],
                        ['Transactions', '/transactions', ['transactions*', 'transfer*'], 'transfer'],
                        ['Saving', '/saving', ['saving*'], 'wallet'],
                    ] as [$label, $url, $patterns, $icon])

                        <a
                            href="{{ $url }}"
                            x-on:click="nav = false"
                            class="flex items-center gap-3 rounded-lg px-3 py-3 transition
                            {{ request()->is(...$patterns)
                                ? 'bg-brand-soft text-brand'
                                : 'text-muted hover:bg-page' }}"
                        >

                            <x-icon
                                :name="$icon"
                                size="h-4 w-4"
                            />

                            {{ $label }}

                        </a>

                    @endforeach

                </nav>


                {{-- Mobile Account --}}
                <div class="mt-auto border-t border-line pt-4">

                    <a
                        href="/account"
                        class="flex items-center gap-3 rounded-lg p-2 hover:bg-page"
                    >

                        {{-- Avatar --}}
                        @if ($headerAvatarUrl)

                            <img
                                src="{{ $headerAvatarUrl }}"
                                alt="{{ $currentUser->nama }}"
                                class="h-10 w-10 shrink-0 rounded-full object-cover ring-1 ring-line"
                            >

                        @else

                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-brand">
                                {{ $headerInitials }}
                            </span>

                        @endif


                        <span class="min-w-0 leading-tight">

                            <span class="block truncate text-sm font-semibold">
                                {{ $currentUser?->nama ?? 'User' }}
                            </span>

                            <span class="block truncate text-xs text-muted">
                                {{ $currentUser?->email ?? 'Personal account' }}
                            </span>

                        </span>

                    </a>


                    {{-- Logout --}}
                    <form
                        method="POST"
                        action="{{ route('logout') }}"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="mt-2 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-expense hover:bg-page"
                        >

                            <x-icon
                                name="logout"
                                size="h-4 w-4"
                            />

                            Sign out

                        </button>

                    </form>

                </div>

            </aside>

        </div>

    </header>

    {{-- Main Content --}}
    <main class="mx-auto max-w-[1312px] px-4 py-6 sm:px-8 sm:py-10 w-full min-w-0 overflow-x-hidden">
        @yield('content')
    </main>


    {{-- Notification Popup dari Backend --}}
    @if (session('notif_popup'))

        <div
            x-cloak
            x-data="{ show: false }"
            x-init="
                setTimeout(() => show = true, 80);
                setTimeout(() => show = false, 6080);
            "
            x-show="show"
            x-transition:enter="transition-transform duration-500 ease-out"
            x-transition:enter-start="-translate-y-[250%]"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition-transform duration-400 ease-in"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="-translate-y-[250%]"
            class="fixed top-5 left-1/2 z-[100] flex w-[calc(100%-2rem)] max-w-sm -translate-x-1/2 items-center gap-3 rounded-2xl border border-line bg-white p-4 shadow-2xl sm:top-8 sm:left-auto sm:right-8 sm:translate-x-0"
        >

            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand">
                <x-icon name="bell" size="h-5 w-5" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-dark">
                    Notification
                </p>
                <p class="mt-0.5 text-sm text-muted line-clamp-2">
                    {{ \App\Models\Notifikasi::formatMessage(session('notif_popup')) }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <a
                    href="/notifications"
                    class="rounded-lg px-2 py-1.5 text-sm font-medium text-brand hover:bg-brand-soft"
                >
                    View
                </a>

                <button
                    type="button"
                    @click="show = false"
                    class="flex h-8 w-8 items-center justify-center rounded-full text-muted hover:bg-page hover:text-dark"
                >
                    <x-icon name="x" size="h-4 w-4" />
                </button>
            </div>

        </div>

    @endif

    <x-pull-to-refresh />
    <x-custom-dialog />
</body>
</html>