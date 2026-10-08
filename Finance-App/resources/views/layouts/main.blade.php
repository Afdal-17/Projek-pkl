@php
    $currentUser = auth()->user();
    $headerAvatarUrl = $currentUser && $currentUser->avatar_path ? asset('storage/' . $currentUser->avatar_path) : null;
    $headerInitials = $currentUser && $currentUser->nama ? strtoupper(substr(trim($currentUser->nama), 0, 1)) : '?';
    $popupNotifications = $currentUser
        ? $currentUser->notifikasi()
            ->latest('id_notifikasi')
            ->limit(1)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id_notifikasi,
                'title' => $n->tipe === 'target' ? 'Savings goal reached' : (str_starts_with($n->pesan, 'Transfer ')
                    || str_starts_with($n->pesan, 'Anda di transfer oleh ') ? 'Transfer success!' : 'Transaction update'),
                'text' => $n->pesan,
                'scope' => $n->tanggal->diffForHumans(),
                'read' => (bool) $n->sudah_dibaca,
                'type' => $n->tipe === 'target' ? 'saving' : (str_starts_with($n->pesan, 'Transfer ')
                    || str_starts_with($n->pesan, 'Anda di transfer oleh ') ? 'transfer' : 'transaction'),
            ])
        : collect();
    $unreadCount = $currentUser ? $currentUser->notifikasi()->where('sudah_dibaca', false)->count() : 0;
@endphp

<!DOCTYPE html>
<html lang="{{ $currentUser?->awal_minggu === 'sunday' ? 'en-US' : 'en-GB' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="finance-currency" content="{{ $currentUser?->mata_uang ?? 'IDR' }}">
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

                {{-- Lonceng Pop-up Dropdown --}}
                <div class="relative"
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
                                 window.dispatchEvent(new CustomEvent('notifications-updated', { detail: { unread: this.unread } }));
                             } catch (e) {}
                         },
                         async markAllAsRead() {
                             this.items.forEach(i => i.read = true);
                             this.unread = 0;
                             try {
                                 await fetch('/notifications/read-all', {
                                     method: 'PATCH',
                                     headers: {
                                         'X-CSRF-TOKEN': this.csrfToken,
                                         'Accept': 'application/json',
                                     }
                                 });
                                 window.dispatchEvent(new CustomEvent('notifications-updated', { detail: { unread: 0 } }));
                             } catch (e) {}
                         }
                     }"
                     x-on:notifications-updated.window="refresh($event)">

                    <button type="button"
                            @click="open = !open"
                            class="relative flex h-10 w-10 items-center justify-center rounded-lg bg-page text-ink transition hover:bg-line/40">
                        <x-icon name="bell" />

                        <span x-cloak x-show="unread > 0" class="absolute right-2 top-2 flex h-2.5 w-2.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75 motion-reduce:animate-none"></span>
                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-600"></span>
                        </span>
                    </button>

                    {{-- Pop-up Modal / Dropdown Container (Kompak & Minimalis) --}}
                    <div x-cloak x-show="open" @click.outside="open = false" x-transition.opacity.scale.origin.top.right
                         class="absolute right-0 z-30 mt-2 w-72 sm:w-80 rounded-xl border border-line bg-white shadow-lg overflow-hidden">
                        
                        {{-- Header Pop-up --}}
                        <div class="flex items-center justify-between border-b border-line px-3.5 py-2.5 bg-white">
                            <div class="flex items-center gap-1.5">
                                <h3 class="text-xs font-bold text-ink">Notification</h3>
                                <template x-if="unread > 0">
                                    <span class="rounded-full bg-brand-soft px-1.5 py-0.2 text-[10px] font-semibold text-brand" x-text="unread"></span>
                                </template>
                            </div>
                            <template x-if="unread > 0">
                                <button type="button" @click="markAllAsRead()" class="text-[11px] text-brand hover:underline">
                                    Tandai dibaca
                                </button>
                            </template>
                        </div>

                        {{-- Pesan Terbaru Saja --}}
                        <div>
                            <template x-for="item in items" :key="item.id">
                                <div @click="markAsRead(item)"
                                     class="flex items-start gap-2.5 p-3 transition cursor-pointer hover:bg-page"
                                     :class="!item.read ? 'bg-brand-soft/20' : ''">
                                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                                          :class="item.type === 'saving' ? 'bg-income-soft text-income' : (item.type === 'transfer' ? 'bg-brand-soft text-brand' : 'bg-expense-soft text-expense')">
                                        <x-icon name="target" size="h-3.5 w-3.5" x-show="item.type === 'saving'" />
                                        <x-icon name="transfer" size="h-3.5 w-3.5" x-show="item.type === 'transfer'" x-cloak />
                                        <x-icon name="bell" size="h-3.5 w-3.5" x-show="item.type !== 'saving' && item.type !== 'transfer'" x-cloak />
                                    </span>
                                    <div class="min-w-0 flex-1 leading-tight">
                                        <div class="flex items-center justify-between gap-1">
                                            <p class="text-xs font-semibold text-ink truncate" x-text="item.title"></p>
                                            <span class="text-[10px] text-muted shrink-0" x-text="item.scope"></span>
                                        </div>
                                        <p class="mt-1 text-[11px] text-muted line-clamp-2 leading-relaxed" x-text="item.text"></p>
                                    </div>
                                    <span x-show="!item.read" class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand"></span>
                                </div>
                            </template>

                            <template x-if="items.length === 0">
                                <div class="px-4 py-5 text-center text-xs text-muted">
                                    Belum ada notifikasi baru.
                                </div>
                            </template>
                        </div>

                        {{-- Footer Pop-up "Lihat semua" --}}
                        <div class="border-t border-line bg-page/50 px-3 py-2 text-center">
                            <a href="/notifications" @click="open = false" class="inline-flex items-center justify-center gap-1 text-[11px] font-semibold text-brand hover:underline">
                                <span>Lihat semua</span>
                                <x-icon name="arrow-right" size="h-3 w-3" />
                            </a>
                        </div>
                    </div>
                </div>

                <div class="relative" x-data="{ open: false }">
                    <button type="button" x-on:click="open = !open" class="flex items-center gap-3">
                        @if ($headerAvatarUrl)
                            <img src="{{ $headerAvatarUrl }}" alt="{{ $currentUser->nama }}" class="h-10 w-10 rounded-full object-cover ring-1 ring-line" />
                        @else
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-brand">{{ $headerInitials }}</span>
                        @endif
                        <span class="text-left leading-tight">
                               <span class="block text-sm font-semibold">{{ $currentUser?->nama }}</span>
                               <span class="block text-xs text-muted">{{ $currentUser?->email }}</span>
                        </span>
                        <svg class="h-4 w-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-cloak x-show="open" x-on:click.outside="open = false"
                         class="absolute right-0 z-20 mt-2 w-44 rounded-xl border border-line bg-white p-1 text-sm shadow-lg">
                        <a href="/account" class="block rounded-lg px-3 py-2 hover:bg-page">Account</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-expense hover:bg-page">Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-[1312px] px-8 py-10">
        @yield('content')
    </main>

    @if (session('notif_popup'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 6000)" x-show="show" x-transition.opacity
             class="fixed bottom-6 right-6 z-50 flex w-full max-w-sm items-start gap-3 rounded-xl border border-line bg-white p-4 shadow-xl">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-income-soft text-income">
                <x-icon name="bell" size="h-4 w-4" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">Notification</p>
                <p class="mt-0.5 text-xs text-muted">{{ session('notif_popup') }}</p>
            </div>
            <a href="/notifications" class="text-xs font-medium text-brand hover:underline">View</a>
            <button type="button" @click="show = false" class="flex h-6 w-6 items-center justify-center rounded-md text-muted hover:bg-page">
                <x-icon name="x" size="h-3 w-3" />
            </button>
        </div>
    @endif

</body>
</html>