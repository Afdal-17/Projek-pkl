@extends('layouts.main')

@section('title', __('account.title'))

@section('content')

<div
    x-data="{
        active: 'profile',
        saved: false,
        showPopup: false,
        form: {
            name: @js($user->nama),
            email: @js($user->email),
            phone: @js($user->nomor_telepon),
            location: @js($user->lokasi),
            currency: @js($user->mata_uang),
            startOfWeek: @js($user->awal_minggu),
        },
        avatarUrl: @js($avatarUrl),
        csrfToken: @js(csrf_token()),

        async save() {
            const response = await fetch(@js(route('account.update')), {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                body: new URLSearchParams({
                    name: this.form.name,
                    email: this.form.email,
                    phone: this.form.phone,
                    location: this.form.location,
                    mata_uang: this.form.currency,
                    awal_minggu: this.form.startOfWeek,
                }),
            });

            if (response.ok) {
                this.saved = true;
                this.showPopup = true;
                setTimeout(() => window.location.reload(), 1200);
            } else {
                const result = await response.json();
                alert(Object.values(result.errors ?? {}).flat()[0] ?? 'Profile could not be saved.');
            }
        },

        async uploadAvatar(event) {
            const file = event.target.files[0];
            if (!file) return;

            const data = new FormData();
            data.append('avatar', file);

            const response = await fetch(@js(route('account.avatar.update')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                body: data,
            });

            if (response.ok) {
                const res = await response.json();
                this.avatarUrl = res.avatar_url;
                setTimeout(() => window.location.reload(), 300);
            } else {
                const result = await response.json();
                alert(Object.values(result.errors ?? {}).flat()[0] ?? 'Photo could not be uploaded.');
            }

            event.target.value = '';
        },

        async removeAvatar() {
            const response = await fetch(@js(route('account.avatar.destroy')), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
            });

            if (response.ok) this.avatarUrl = null;
        }
    }"
>

    {{-- Pop-up notifikasi berhasil simpan profile --}}
    <div x-cloak x-show="showPopup" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-income-soft text-income">
                <x-icon name="check" size="h-6 w-6" />
            </div>
            <h3 class="mt-4 text-lg font-bold text-ink">{{ __('account.success_popup') }}</h3>
            <p class="mt-1 text-xs text-muted">Halaman akan segera dimuat ulang...</p>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-3xl font-bold">{{ __('account.title') }}</h1>
            <p class="mt-1 text-sm text-muted">{{ __('account.subtitle') }}</p>
        </div>

        <x-button x-on:click="save()">
            <span x-text="saved ? @js(__('account.saved')) : @js(__('account.save_changes'))"></span>
        </x-button>
    </div>


    <div class="mt-8 grid items-start gap-6 lg:grid-cols-[224px_1fr]">

        {{-- Menu kiri --}}
        <x-card class="p-2">
            <nav class="space-y-1 text-sm font-medium">

                @foreach ([
                    ['profile', __('account.profile'), 'user', '#profile'],
                    ['security', __('account.security'), 'shield', '#security'],
                    ['preferences', __('account.preferences'), 'settings', '#preferences'],
                ] as [$key, $label, $icon, $href])
                    <a href="{{ $href }}"
                       x-on:click="active = '{{ $key }}'"
                       class="flex items-center gap-3 rounded-lg px-3 py-2.5 transition"
                       :class="active === '{{ $key }}' ? 'bg-brand-soft text-brand' : 'text-muted hover:bg-page'">
                        <x-icon name="{{ $icon }}" size="h-4 w-4" />
                        {{ $label }}
                    </a>
                @endforeach

                <a href="/notifications" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-muted transition hover:bg-page">
                    <x-icon name="bell" size="h-4 w-4" />
                    {{ __('account.notifications') }}
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-expense transition hover:bg-page">
                        <x-icon name="logout" size="h-4 w-4" />
                        {{ __('account.sign_out') }}
                    </button>
                </form>

            </nav>
        </x-card>


        {{-- Konten kanan --}}
        <div class="space-y-6">

            {{-- Profile --}}
            <x-card id="profile" class="scroll-mt-6 p-6">

                <div class="flex items-start justify-between">
                    <h2 class="text-lg font-semibold">{{ __('account.profile') }}</h2>
                    <span class="text-xs text-muted">{{ __('account.public_details') }}</span>
                </div>

                <div class="mt-5 flex items-center gap-5">
                    <img x-show="avatarUrl" :src="avatarUrl" alt="Profile photo" class="h-20 w-20 rounded-full bg-brand-soft object-cover">
                    <span x-show="!avatarUrl" class="flex h-20 w-20 items-center justify-center rounded-full bg-brand-soft text-xl font-bold text-brand" x-text="(form.name || 'U').charAt(0).toUpperCase()"></span>

                    <div>
                        <p class="font-semibold" x-text="form.name"></p>
                        <p class="mt-1 text-xs text-muted">{{ __('account.photo_note') }}</p>

                        <div class="mt-3 flex items-center gap-3">
                            <input x-ref="avatarInput" type="file" accept="image/jpeg,image/png" class="hidden" x-on:change="uploadAvatar($event)">
                            <x-button type="button" variant="outline" x-on:click="$refs.avatarInput.click()">{{ __('account.change_photo') }}</x-button>
                            <button type="button" x-on:click="removeAvatar()" class="text-xs font-medium text-expense hover:underline">{{ __('account.remove') }}</button>
                        </div>
                    </div>
                </div>

            </x-card>


            {{-- About --}}
            <x-card class="p-6">

                <h2 class="text-lg font-semibold">{{ __('account.about') }}</h2>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <x-input label="{{ __('account.full_name') }}" name="full_name" x-model="form.name" />
                    <x-input label="{{ __('account.email_address') }}" name="email" type="email" x-model="form.email" />
                    <x-input label="{{ __('account.phone_number') }}" name="phone" x-model="form.phone" />
                    <x-input label="{{ __('account.location') }}" name="location" x-model="form.location" />
                </div>

            </x-card>


            {{-- Security + Preferences --}}
            <div class="grid items-start gap-6 md:grid-cols-2">

                <x-card id="security" class="scroll-mt-6 p-6">

                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-soft text-brand">
                        <x-icon name="shield" size="h-5 w-5" />
                    </span>

                    <h2 class="mt-4 text-lg font-semibold">{{ __('account.security') }}</h2>

                    <p class="mt-2 text-sm text-muted">
                        {{ __('account.security_desc') }}
                    </p>

                    {{-- Backend: arahkan ke form ganti password --}}
                    <x-button :href="route('profile.edit')" variant="outline" class="mt-4">{{ __('account.update_password') }}</x-button>

                </x-card>


                <x-card id="preferences" class="scroll-mt-6 p-6">

                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-soft text-brand">
                        <x-icon name="settings" size="h-5 w-5" />
                    </span>

                    <h2 class="mt-4 text-lg font-semibold">{{ __('account.preferences') }}</h2>

                    <dl class="mt-4 space-y-3 text-sm">

                        <div class="flex items-center justify-between">
                            <dt class="text-muted">{{ __('account.default_currency') }}</dt>
                            <dd>
                                <select x-model="form.currency" class="border-0 bg-transparent py-0 pl-0 pr-6 text-right text-sm font-semibold focus:ring-0">
                                    <option value="IDR">IDR (Rp)</option>
                                    <option value="USD">USD ($)</option>
                                    <option value="EUR">EUR (€)</option>
                                </select>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">{{ __('account.start_of_week') }}</dt>
                            <dd>
                                <select x-model="form.startOfWeek" class="border-0 bg-transparent py-0 pl-0 pr-6 text-right text-sm font-semibold focus:ring-0">
                                    <option value="monday">Monday</option>
                                    <option value="sunday">Sunday</option>
                                </select>
                            </dd>
                        </div>
                    </dl>

                </x-card>

            </div>

        </div>

    </div>

</div>

@endsection