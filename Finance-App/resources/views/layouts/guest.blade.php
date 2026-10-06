<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Finance App') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-page font-sans text-ink antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <a href="/" class="mb-6">
                <x-logo />
            </a>

            <div class="w-full max-w-[400px] rounded-2xl border border-line bg-white p-8 shadow-card">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
