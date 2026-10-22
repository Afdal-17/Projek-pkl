@extends('layouts.admin')

@section('title', 'Profile')

@section('content')

<div
    class="max-w-4xl space-y-6"
    x-data="{
        saved: false,
        saving: false,
        form: {
            name: @js($user->nama),
            email: @js($user->email),
            phone: @js($user->nomor_telepon && $user->nomor_telepon !== 'null' ? $user->nomor_telepon : ''),
            location: @js($user->lokasi && $user->lokasi !== 'null' ? $user->lokasi : ''),
        },
        avatarUrl: @js($avatarUrl),
        csrfToken: @js(csrf_token()),

        async save() {
            if (this.saving) return;
            this.saving = true;
            try {
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
                    if (window.financeToast) {
                        window.financeToast({
                            message: 'Profile successfully updated.',
                            variant: 'income',
                        });
                    }
                    setTimeout(() => window.location.reload(), 700);
                } else {
                    const result = await response.json();
                    window.financeAlert({
                        title: 'Save Failed',
                        message: Object.values(result.errors ?? {}).flat()[0] ?? 'Profile could not be saved.',
                        variant: 'danger',
                    });
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
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                body: data,
            });

            if (response.ok) {
                this.avatarUrl = (await response.json()).avatar_url;
                window.financeToast({
                    message: 'Photo successfully updated.',
                    variant: 'income',
                });
            } else {
                const result = await response.json();
                window.financeAlert({
                    title: 'Upload Failed',
                    message: Object.values(result.errors ?? {}).flat()[0] ?? 'Photo could not be uploaded.',
                    variant: 'danger',
                });
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

    {{-- Header --}}
    <div>
        <h1 class="text-3xl font-bold">Profile</h1>
        <p class="mt-1 text-sm text-muted">Manage your admin account details.</p>
    </div>

    {{-- Photo Card --}}
    <x-card class="p-6">
        <h2 class="text-lg font-semibold">Photo</h2>

        <div class="mt-5 flex flex-wrap items-center gap-5">
            <img x-cloak x-show="avatarUrl" :src="avatarUrl" alt="Profile photo" class="h-20 w-20 rounded-full bg-brand-soft object-cover">
            <span x-show="!avatarUrl" class="flex h-20 w-20 items-center justify-center rounded-full bg-brand-soft text-2xl font-semibold text-brand">{{ strtoupper(substr(trim($user->nama), 0, 1)) }}</span>

            <div class="min-w-0">
                <p class="font-semibold" x-text="form.name"></p>
                <p class="mt-1 text-xs text-muted">JPG or PNG. Maximum file size 2 MB.</p>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <input x-ref="avatarInput" type="file" accept="image/jpeg,image/png" class="hidden" x-on:change="uploadAvatar($event)">
                    <x-button type="button" variant="outline" x-on:click="$refs.avatarInput.click()">Change photo</x-button>
                    <button type="button" x-on:click="removeAvatar()" class="text-xs font-medium text-expense hover:underline">Remove</button>
                </div>
            </div>
        </div>
    </x-card>

    {{-- About Card --}}
    <x-card class="p-6">
        <h2 class="text-lg font-semibold">About</h2>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <x-input label="Full name" name="full_name" x-model="form.name" />
            <x-input label="Email address" name="email" type="email" x-model="form.email" />
            <x-input label="Phone number" name="phone" placeholder="0812345678" x-model="form.phone" />
            <x-input label="Location" name="location" placeholder="Jakarta,Indonesian" x-model="form.location" />
        </div>
    </x-card>

    {{-- Save Changes di bawah card About --}}
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
</div>

{{-- Security / Change Password Card (setelah bagian About & Save Changes) --}}
<div class="mt-6 max-w-4xl">
    <x-card
        class="p-6"
        x-data="{
            passwordForm: {
                current_password: '',
                password: '',
                password_confirmation: '',
            },
            passwordLoading: false,
            passwordMessage: '',
            passwordSuccess: false,
            passwordErrors: {},
            showCurrent: false,
            showNew: false,
            showConfirm: false,
            csrfToken: '{{ csrf_token() }}',
            updateUrl: '{{ route('admin.password.update') }}',

            async updatePassword() {
                if (this.passwordLoading) return;
                this.passwordLoading = true;
                this.passwordMessage = '';
                this.passwordErrors = {};

                try {
                    const response = await fetch(this.updateUrl, {
                        method: 'PUT',
                        headers: {
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            current_password: this.passwordForm.current_password,
                            password: this.passwordForm.password,
                            password_confirmation: this.passwordForm.password_confirmation,
                        }),
                    });

                    const data = await response.json();

                    if (response.ok) {
                        this.passwordSuccess = true;
                        this.passwordMessage = data.message || 'Password successfully updated.';
                        this.passwordForm.current_password = '';
                        this.passwordForm.password = '';
                        this.passwordForm.password_confirmation = '';
                        if (window.financeToast) {
                            window.financeToast({
                                message: data.message || 'Password successfully updated.',
                                variant: 'income',
                            });
                        }
                    } else {
                        this.passwordSuccess = false;
                        this.passwordErrors = data.errors || {};
                        this.passwordMessage = data.message || Object.values(data.errors ?? {}).flat()[0] || 'Failed to update password.';
                        if (window.financeToast && this.passwordMessage) {
                            window.financeToast({
                                message: this.passwordMessage,
                                variant: 'danger',
                            });
                        }
                    }
                } catch (err) {
                    this.passwordSuccess = false;
                    this.passwordMessage = 'An unexpected error occurred. Please try again.';
                    if (window.financeToast) {
                        window.financeToast({
                            message: this.passwordMessage,
                            variant: 'danger',
                        });
                    }
                } finally {
                    this.passwordLoading = false;
                }
            }
        }"
    >
        <div class="flex items-center gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand shadow-sm">
                <x-icon name="shield" size="h-5 w-5" />
            </span>
            <div>
                <h2 class="text-lg font-semibold text-ink">Security / Change Password</h2>
                <p class="text-xs text-muted">Update your password to keep your admin account secure.</p>
            </div>
        </div>

        @if (session('status') === 'password-updated')
            <div class="mt-4 rounded-xl border border-income/20 bg-income-soft p-3.5 text-sm font-medium text-income flex items-center gap-2">
                <x-icon name="check" size="h-4 w-4 shrink-0" />
                <span>Password successfully updated.</span>
            </div>
        @endif

        @if ($errors->updatePassword->any())
            <div class="mt-4 rounded-xl border border-expense/20 bg-expense-soft p-3.5 text-sm font-medium text-expense">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->updatePassword->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Feedback message -->
        <template x-if="passwordMessage">
            <div
                class="mt-4 rounded-xl p-3.5 text-sm font-medium flex items-center gap-2"
                :class="passwordSuccess ? 'bg-income-soft text-income border border-income/20' : 'bg-expense-soft text-expense border border-expense/20'"
                x-transition
            >
                <template x-if="passwordSuccess">
                    <x-icon name="check" size="h-4 w-4 shrink-0" />
                </template>
                <template x-if="!passwordSuccess">
                    <x-icon name="ban" size="h-4 w-4 shrink-0" />
                </template>
                <span x-text="passwordMessage"></span>
            </div>
        </template>

        <form method="POST" action="{{ route('admin.password.update') }}" x-on:submit.prevent="updatePassword()" class="mt-6 max-w-xl space-y-4">
            @csrf
            @method('PUT')

            <!-- Current Password -->
            <div class="space-y-1.5">
                <label for="admin_current_password" class="block text-sm font-medium text-ink">Current Password</label>
                <div class="relative">
                    <input
                        id="admin_current_password"
                        name="current_password"
                        x-bind:type="showCurrent ? 'text' : 'password'"
                        x-model="passwordForm.current_password"
                        autocomplete="current-password"
                        placeholder="Enter current password"
                        class="w-full rounded-lg border border-line bg-white pl-3.5 pr-11 py-2.5 text-sm text-ink placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 transition"
                        required
                    />
                    <button
                        type="button"
                        x-on:click="showCurrent = !showCurrent"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-ink focus:outline-none transition p-0.5 rounded"
                        aria-label="Toggle current password visibility"
                    >
                        <svg x-show="!showCurrent" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg x-show="showCurrent" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
                <template x-if="passwordErrors.current_password">
                    <p class="mt-1 text-xs font-medium text-expense" x-text="passwordErrors.current_password[0]"></p>
                </template>
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1" />
            </div>

            <!-- New Password -->
            <div class="space-y-1.5">
                <label for="admin_password" class="block text-sm font-medium text-ink">New Password</label>
                <div class="relative">
                    <input
                        id="admin_password"
                        name="password"
                        x-bind:type="showNew ? 'text' : 'password'"
                        x-model="passwordForm.password"
                        autocomplete="new-password"
                        placeholder="Enter new password"
                        class="w-full rounded-lg border border-line bg-white pl-3.5 pr-11 py-2.5 text-sm text-ink placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 transition"
                        required
                    />
                    <button
                        type="button"
                        x-on:click="showNew = !showNew"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-ink focus:outline-none transition p-0.5 rounded"
                        aria-label="Toggle new password visibility"
                    >
                        <svg x-show="!showNew" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg x-show="showNew" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
                <template x-if="passwordErrors.password">
                    <p class="mt-1 text-xs font-medium text-expense" x-text="passwordErrors.password[0]"></p>
                </template>
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1" />
            </div>

            <!-- Confirm New Password -->
            <div class="space-y-1.5">
                <label for="admin_password_confirmation" class="block text-sm font-medium text-ink">Confirm New Password</label>
                <div class="relative">
                    <input
                        id="admin_password_confirmation"
                        name="password_confirmation"
                        x-bind:type="showConfirm ? 'text' : 'password'"
                        x-model="passwordForm.password_confirmation"
                        autocomplete="new-password"
                        placeholder="Confirm new password"
                        class="w-full rounded-lg border border-line bg-white pl-3.5 pr-11 py-2.5 text-sm text-ink placeholder:text-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 transition"
                        required
                    />
                    <button
                        type="button"
                        x-on:click="showConfirm = !showConfirm"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-ink focus:outline-none transition p-0.5 rounded"
                        aria-label="Toggle confirm password visibility"
                    >
                        <svg x-show="!showConfirm" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg x-show="showConfirm" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                    </button>
                </div>
                <template x-if="passwordErrors.password_confirmation">
                    <p class="mt-1 text-xs font-medium text-expense" x-text="passwordErrors.password_confirmation[0]"></p>
                </template>
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1" />
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button
                    type="submit"
                    :disabled="passwordLoading"
                    class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-lg bg-dark px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-black active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-dark/20 min-h-[42px]"
                >
                    <span x-show="!passwordLoading" class="inline-flex items-center justify-center gap-2 text-white">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <span>Update Password</span>
                    </span>
                    <span x-cloak x-show="passwordLoading" class="inline-flex items-center justify-center gap-2 text-white">
                        <svg class="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span>Updating...</span>
                    </span>
                </button>
            </div>
        </form>
    </x-card>
</div>

@endsection
