@extends('layouts.auth')

@section('title', 'Sign in')

@section('content')
    <div class="text-center">
        @if (file_exists(public_path('images/welcome.png')))
            <img src="/images/welcome.png" alt="" class="mx-auto mb-2 h-16">
        @endif
        <h1 class="text-2xl font-bold">Welcome back</h1>
        <p class="mt-2 text-sm text-muted">Sign in to continue to your finances.</p>
    </div>

    {{-- Sementara tombol Sign in hanya link ke dashboard. Backend: ganti jadi <form method="POST"> + @csrf + type="submit" --}}
    <form class="mt-6 space-y-4">
        <x-input label="Email" name="email" type="email" placeholder="alex@example.com" />
        <x-input label="Password" name="password" type="password" placeholder="Enter your password" />
        <div class="text-right">
            <a href="#" class="text-sm text-brand hover:underline">Forgot password?</a>
        </div>
        <x-button href="/dashboard" class="w-full">Sign in</x-button>
    </form>

    @include('auth._social')

    <p class="mt-5 text-center text-sm text-muted">
        New to Finance App? <a href="/register" class="font-medium text-ink ">Create an account</a>
    </p>
@endsection