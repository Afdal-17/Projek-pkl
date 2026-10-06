@extends('layouts.admin')

@section('title', 'Profile')

@section('content')

<div
    x-data="{
        saved: false,
        form: {
            name: @js($user->nama),
            email: @js($user->email),
            phone: @js($user->nomor_telepon),
            location: @js($user->lokasi),
        },
        avatarUrl: @js($avatarUrl),
        csrfToken: @js(csrf_token()),

        async save() {
            const response = await fetch(@js(route('admin.profile.update')), {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                body: new URLSearchParams({
                    name: this.form.name,
                    email: this.form.email,
                    phone: this.form.phone,
                    location: this.form.location,
                }),
            });

            if (response.ok) {
                this.saved = true;
                setTimeout(() => window.location.reload(), 700);
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
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
            });

            if (response.ok) this.avatarUrl = null;
        }
    }"
>

    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-3xl font-bold">Profile</h1>
            <p class="mt-1 text-sm text-muted">Manage your admin account details.</p>
        </div>

        <x-button x-on:click="save()">
            <span x-text="saved ? 'Saved' : 'Save changes'"></span>
        </x-button>
    </div>

    <div class="mt-8 grid items-start gap-6 lg:grid-cols-[1fr_1fr]">
        <x-card class="p-6">
            <h2 class="text-lg font-semibold">Photo</h2>

            <div class="mt-5 flex items-center gap-5">
                <img x-cloak x-show="avatarUrl" :src="avatarUrl" alt="Profile photo" class="h-20 w-20 rounded-full bg-brand-soft object-cover">
                <span x-show="!avatarUrl" class="flex h-20 w-20 items-center justify-center rounded-full bg-brand-soft text-2xl font-semibold text-brand">{{ strtoupper(substr(trim($user->nama), 0, 1)) }}</span>

                <div>
                    <p class="font-semibold" x-text="form.name"></p>
                    <p class="mt-1 text-xs text-muted">JPG or PNG. Maximum file size 2 MB.</p>

                    <div class="mt-3 flex items-center gap-3">
                        <input x-ref="avatarInput" type="file" accept="image/jpeg,image/png" class="hidden" x-on:change="uploadAvatar($event)">
                        <x-button type="button" variant="outline" x-on:click="$refs.avatarInput.click()">Change photo</x-button>
                        <button type="button" x-on:click="removeAvatar()" class="text-xs font-medium text-expense hover:underline">Remove</button>
                    </div>
                </div>
            </div>
        </x-card>

        <x-card class="p-6">
            <h2 class="text-lg font-semibold">About</h2>

            <div class="mt-5 grid gap-4">
                <x-input label="Full name" name="full_name" x-model="form.name" />
                <x-input label="Email address" name="email" type="email" x-model="form.email" />
                <x-input label="Phone number" name="phone" x-model="form.phone" />
                <x-input label="Location" name="location" x-model="form.location" />
            </div>
        </x-card>
    </div>

</div>

@endsection
