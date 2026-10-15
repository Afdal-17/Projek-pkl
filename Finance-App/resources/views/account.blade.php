@extends('layouts.main')

@section('title', 'Account')

@section('content')

<div
    x-data="{
        active: 'profile',
        saved: false,
        saving: false,
        form: {
            name: @js($user->nama),
            email: @js($user->email),
            phone: @js($user->nomor_telepon && $user->nomor_telepon !== 'null' ? $user->nomor_telepon : ''),
            location: @js($user->lokasi && $user->lokasi !== 'null' ? $user->lokasi : ''),
            currency: @js($user->mata_uang ?? 'IDR'),
        },
        currencies: [
            { value: 'IDR', label: 'IDR (Rp)', symbol: 'Rp', desc: 'Indonesian Rupiah' },
            { value: 'USD', label: 'USD ($)', symbol: '$', desc: 'US Dollar' },
            { value: 'EUR', label: 'EUR (€)', symbol: '€', desc: 'Euro' },
        ],
        avatarUrl: @js($avatarUrl),
        csrfToken: @js(csrf_token()),

        async save() {
            if (this.saving) return;
            this.saving = true;
            try {
                const response = await fetch(@js(route('account.update')), {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: new URLSearchParams({
                        name: this.form.name,
                        email: this.form.email,
                        phone: this.form.phone,
                        location: this.form.location,
                        currency: this.form.currency,
                    }),
                });

                if (response.ok) {
                    this.saved = true;
                    setTimeout(() => window.location.reload(), 700);
                } else {
                    const result = await response.json();
                    alert(Object.values(result.errors ?? {}).flat()[0] ?? 'Profile could not be saved.');
                }
            } finally {
                this.saving = false;
            }
        },

        async uploadAvatar(event) {
            const file = event.target.files[0];
            if (!file) return;

            const data = new FormData();
            data.append('avatar', file);

            const response = await fetch(@js(route('account.avatar.update')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json'
                },
                body: data,
            });

            if (response.ok) {
                this.avatarUrl = (await response.json()).avatar_url;
            } else {
                const result = await response.json();
                alert(Object.values(result.errors ?? {}).flat()[0] ?? 'Photo could not be uploaded.');
            }

            event.target.value = '';
        },

        async removeAvatar() {
            const response = await fetch(@js(route('account.avatar.destroy')), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json'
                },
            });

            if (response.ok) this.avatarUrl = null;
        }
    }"
>

    {{-- Header --}}
    <div>
        <h1 class="text-3xl font-bold">Account</h1>
        <p class="mt-1 text-sm text-muted">
            Manage your profile, security, and finance preferences.
        </p>
    </div>

    <div class="mt-8 grid items-start gap-6 lg:grid-cols-[224px_1fr]">

        {{-- Menu kiri --}}
        <x-card class="p-2">
            <nav class="space-y-1 text-sm font-medium">

                @foreach ([
                    ['profile', 'Profile', 'user', '#profile'],
                    ['security', 'Security', 'shield', '#security'],
                    ['preferences', 'Finance preferences', 'settings', '#preferences'],
                ] as [$key, $label, $icon, $href])
                    <a href="{{ $href }}"
                       x-on:click="active = '{{ $key }}'"
                       class="flex items-center gap-3 rounded-lg px-3 py-2.5 transition"
                       :class="active === '{{ $key }}' ? 'bg-brand-soft text-brand' : 'text-muted hover:bg-page'">
                        <x-icon name="{{ $icon }}" size="h-4 w-4" />
                        {{ $label }}
                    </a>
                @endforeach

                <a href="/notifications"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-muted transition hover:bg-page">
                    <x-icon name="bell" size="h-4 w-4" />
                    Notifications
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-expense transition hover:bg-page">
                        <x-icon name="logout" size="h-4 w-4" />
                        Sign out
                    </button>
                </form>

            </nav>
        </x-card>

        {{-- Konten kanan --}}
        <div class="space-y-6 pb-20">

            {{-- Profile --}}
            <x-card id="profile" class="scroll-mt-6 p-6">

                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold">Profile</h2>
                    <span class="text-xs text-muted">Your public account details</span>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-5">
                    <img
                        x-cloak
                        x-show="avatarUrl"
                        :src="avatarUrl"
                        alt="Profile photo"
                        class="h-20 w-20 rounded-full bg-brand-soft object-cover"
                    >

                    <span
                        x-show="!avatarUrl"
                        class="h-20 w-20 rounded-full bg-brand-soft"
                    ></span>

                    <div class="min-w-0">
                        <p class="font-semibold" x-text="form.name"></p>
                        <p class="mt-1 text-xs text-muted">
                            JPG or PNG. Maximum file size 2 MB.
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <input
                                x-ref="avatarInput"
                                type="file"
                                accept="image/jpeg,image/png"
                                class="hidden"
                                x-on:change="uploadAvatar($event)"
                            >

                            <x-button
                                type="button"
                                variant="outline"
                                x-on:click="$refs.avatarInput.click()"
                            >
                                Change photo
                            </x-button>

                            <button
                                type="button"
                                x-on:click="removeAvatar()"
                                class="text-xs font-medium text-expense hover:underline"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </div>

            </x-card>

            {{-- About --}}
            <x-card class="p-6">
                <h2 class="text-lg font-semibold">About</h2>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <x-input label="Full name" name="full_name" x-model="form.name" />
                    <x-input label="Email address" name="email" type="email" x-model="form.email" />
                    <x-input label="Phone number" name="phone" x-model="form.phone" />
                    <x-input label="Location" name="location" x-model="form.location" />
                </div>
            </x-card>

            {{-- Save Changes di luar card About --}}
            <div class="flex w-full justify-end">
                <x-button
                    x-on:click="save()"
                    x-bind:disabled="saving"
                    class="w-full justify-center sm:w-auto"
                >
                    <span x-show="saving">Saving...</span>
                    <span x-show="!saving" x-text="saved ? 'Saved' : 'Save changes'"></span>
                </x-button>
            </div>

            {{-- Security + Preferences --}}
            <div class="grid items-stretch gap-6 md:grid-cols-2">

                {{-- Security Card --}}
                <x-card id="security" class="scroll-mt-6 p-6 flex flex-col justify-between">

                    <div>
                        <div class="flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-soft text-brand shadow-sm">
                                <x-icon name="shield" size="h-5 w-5" />
                            </span>
                            <div>
                                <h2 class="text-lg font-semibold text-ink">Security</h2>
                                <p class="text-xs text-muted">Password & authentication settings</p>
                            </div>
                        </div>

                        <p class="mt-4 text-sm text-muted leading-relaxed">
                            Your password was last changed 3 months ago. Enable two-step verification for extra protection.
                        </p>
                    </div>

                    <div class="mt-6">
                        <x-button
                            :href="route('profile.edit')"
                            variant="outline"
                            class="w-full justify-center sm:w-auto"
                        >
                            Update password
                        </x-button>
                    </div>

                </x-card>

                {{-- Finance Preferences Card --}}
                <x-card id="preferences" class="scroll-mt-6 p-6 flex flex-col justify-between">

                    <div>
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-soft text-brand shadow-sm">
                                    <x-icon name="settings" size="h-5 w-5" />
                                </span>
                                <div>
                                    <h2 class="text-lg font-semibold text-ink">Finance preferences</h2>
                                    <p class="text-xs text-muted">Set your reporting currency</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 space-y-3.5">

                            {{-- Currency Row --}}
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-line/70 bg-page/40 p-3.5 transition hover:bg-page/60">
                                <div>
                                    <span class="text-sm font-semibold text-ink">Default currency</span>
                                    <p class="text-xs text-muted">Primary currency for transactions and balances</p>
                                </div>

                                <div
                                    class="relative shrink-0"
                                    x-data="{
                                        open: false,
                                        dropUp: false,
                                        toggle(el) {
                                            this.open = !this.open;
                                            if (this.open) {
                                                const rect = el.getBoundingClientRect();
                                                this.dropUp = (window.innerHeight - rect.bottom) < 220;
                                            }
                                        }
                                    }"
                                    @click.outside="open = false"
                                    @keydown.escape.window="open = false"
                                >
                                    <button
                                        type="button"
                                        @click="toggle($el)"
                                        class="flex items-center justify-between gap-3 min-w-[150px] rounded-xl border border-line bg-white px-3.5 py-2 text-sm font-semibold text-ink shadow-sm transition hover:border-brand/50 hover:bg-page/40 focus:outline-none focus:ring-2 focus:ring-brand/20 cursor-pointer"
                                    >
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span
                                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-brand-soft text-[11px] font-bold text-brand"
                                                x-text="currencies.find(c => c.value === form.currency)?.symbol ?? 'Rp'"
                                            ></span>
                                            <span
                                                class="truncate"
                                                x-text="currencies.find(c => c.value === form.currency)?.label ?? 'IDR (Rp)'"
                                            ></span>
                                        </div>
                                        <x-icon
                                            name="chevron-down"
                                            size="h-4 w-4"
                                            class="text-muted shrink-0 transition-transform duration-200"
                                            x-bind:class="open ? 'rotate-180' : ''"
                                        />
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
                                        class="absolute right-0 z-50 min-w-[210px] rounded-2xl border border-line bg-white p-1.5 shadow-2xl ring-1 ring-black/5"
                                        :class="dropUp ? 'bottom-full mb-2 origin-bottom-right' : 'top-full mt-2 origin-top-right'"
                                    >
                                        <div class="px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted">
                                            Select currency
                                        </div>
                                        <div class="space-y-0.5">
                                            <template x-for="item in currencies" :key="item.value">
                                                <button
                                                    type="button"
                                                    @click="form.currency = item.value; open = false"
                                                    class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-sm transition text-left cursor-pointer"
                                                    :class="form.currency === item.value ? 'bg-brand-soft text-brand font-semibold' : 'text-ink hover:bg-page font-medium'"
                                                >
                                                    <div class="flex items-center gap-2.5">
                                                        <span
                                                            class="flex h-6 w-6 items-center justify-center rounded-md text-xs font-bold transition"
                                                            :class="form.currency === item.value ? 'bg-brand text-white' : 'bg-page text-muted'"
                                                            x-text="item.symbol"
                                                        ></span>
                                                        <div>
                                                            <div class="font-medium" x-text="item.label"></div>
                                                            <div class="text-[11px] text-muted font-normal" x-text="item.desc"></div>
                                                        </div>
                                                    </div>
                                                    <x-icon
                                                        x-show="form.currency === item.value"
                                                        name="check"
                                                        size="h-4 w-4"
                                                        class="text-brand shrink-0"
                                                    />
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    <select x-model="form.currency" name="currency" class="sr-only" tabindex="-1" aria-hidden="true">
                                        <template x-for="item in currencies" :key="item.value">
                                            <option :value="item.value" x-text="item.label"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end">
                        <x-button
                            type="button"
                            x-on:click="save()"
                            x-bind:disabled="saving"
                            class="w-full justify-center sm:w-auto"
                        >
                            <span x-show="saving">Saving...</span>
                            <span x-show="!saving" x-text="saved ? 'Saved' : 'Save preferences'"></span>
                        </x-button>
                    </div>

                </x-card>

            </div>

        </div>

    </div>
</div>

@endsection