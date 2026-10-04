@extends('layouts.auth')

@section('title', 'Create account')

@section('content')
    <div class="text-center">
        <h1 class="text-2xl font-bold">Create your account</h1>
        <p class="mt-2 text-sm text-muted">Start building better money habits today.</p>
    </div>

    <form class="mt-6 space-y-4">
        <x-input label="Email" name="email" type="email" placeholder="alex@example.com" />
        <x-input label="Password" name="password" type="password" placeholder="Enter your password" />
        <label class="flex items-center gap-2 text-sm text-muted">
            <input type="checkbox" checked class="h-4 w-4 rounded accent-brand">
            I agree to the Terms of Service and Privacy Policy.
        </label>
        <x-button href="/dashboard" class="w-full">Create account</x-button>
    </form>

    @include('auth._social')

    <p class="mt-5 text-center text-sm text-muted">
        Already have an account? <a href="/login" class="font-medium">Sign in</a>
    </p>
@endsection