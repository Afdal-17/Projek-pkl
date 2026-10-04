@extends('layouts.app')

@section('title', 'Account')

@section('content')

<div
    x-data="{
        active: 'profile',
        saved: false,

        form: Object.assign({
            name: 'Alex Morgan',
            email: 'alex@example.com',
            phone: '+62 812 3456 7890',
            location: 'Jakarta, Indonesia',
        }, JSON.parse(localStorage.getItem('profile') || '{}')),

        save() {
            localStorage.setItem('profile', JSON.stringify(this.form));
            this.saved = true;
            setTimeout(() => this.saved = false, 2000);
        }
    }"
>

    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-3xl font-bold">Account</h1>
            <p class="mt-1 text-sm text-muted">Manage your profile, security, and finance preferences.</p>
        </div>

        <x-button x-on:click="save()">
            <span x-text="saved ? 'Saved' : 'Save changes'"></span>
        </x-button>
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

                <a href="/notifications" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-muted transition hover:bg-page">
                    <x-icon name="bell" size="h-4 w-4" />
                    Notifications
                </a>

                {{-- Backend: ganti jadi form POST logout + @csrf --}}
                <a href="/login" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-expense transition hover:bg-page">
                    <x-icon name="logout" size="h-4 w-4" />
                    Sign out
                </a>

            </nav>
        </x-card>


        {{-- Konten kanan --}}
        <div class="space-y-6">

            {{-- Profile --}}
            <x-card id="profile" class="scroll-mt-6 p-6">

                <div class="flex items-start justify-between">
                    <h2 class="text-lg font-semibold">Profile</h2>
                    <span class="text-xs text-muted">Your public account details</span>
                </div>

                <div class="mt-5 flex items-center gap-5">
                    <span class="h-20 w-20 rounded-full bg-brand-soft"></span>

                    <div>
                        <p class="font-semibold" x-text="form.name"></p>
                        <p class="mt-1 text-xs text-muted">JPG or PNG. Maximum file size 2 MB.</p>

                        <div class="mt-3 flex items-center gap-3">
                            <x-button variant="outline">Change photo</x-button>
                            <button type="button" class="text-xs font-medium text-expense hover:underline">Remove</button>
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


            {{-- Security + Preferences --}}
            <div class="grid items-start gap-6 md:grid-cols-2">

                <x-card id="security" class="scroll-mt-6 p-6">

                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-soft text-brand">
                        <x-icon name="shield" size="h-5 w-5" />
                    </span>

                    <h2 class="mt-4 text-lg font-semibold">Security</h2>

                    <p class="mt-2 text-sm text-muted">
                        Your password was last changed 3 months ago. Enable two-step verification for extra protection.
                    </p>

                    {{-- Backend: arahkan ke form ganti password --}}
                    <x-button variant="outline" class="mt-4">Update password</x-button>

                </x-card>


                <x-card id="preferences" class="scroll-mt-6 p-6">

                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-soft text-brand">
                        <x-icon name="settings" size="h-5 w-5" />
                    </span>

                    <h2 class="mt-4 text-lg font-semibold">Finance preferences</h2>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">Default currency</dt>
                            <dd class="font-semibold">IDR (Rp)</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">Start of week</dt>
                            <dd class="font-semibold">Monday</dd>
                        </div>
                    </dl>

                </x-card>

            </div>

        </div>

    </div>

</div>

@endsection