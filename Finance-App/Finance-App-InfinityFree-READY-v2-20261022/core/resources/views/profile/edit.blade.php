@extends('layouts.main')

@section('title', 'Profile Settings')

@section('content')

<div>
    <h1 class="text-3xl font-bold">Profile Settings</h1>
    <p class="mt-1 text-sm text-muted">Manage your account information and security.</p>
</div>

<div class="mt-8 space-y-6">
    <x-card class="p-8">
        <div class="max-w-xl">
            @include('profile.partials.update-profile-information-form')
        </div>
    </x-card>

    <x-card class="p-8">
        <div class="max-w-xl">
            @include('profile.partials.update-password-form')
        </div>
    </x-card>

    <x-card class="p-8">
        <div class="max-w-xl">
            @include('profile.partials.delete-user-form')
        </div>
    </x-card>
</div>

@endsection
