<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Finance App')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-page px-4 font-sans text-ink antialiased">
    <div class="w-full max-w-[400px] rounded-2xl border border-line bg-white p-8 shadow-card">
        @yield('content')
    </div>
</body>
</html>