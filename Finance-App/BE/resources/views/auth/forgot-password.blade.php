<x-guest-layout>
    <a href="{{ route('login') }}" class="inline-flex items-center gap-2 text-sm text-muted hover:text-ink transition mb-4">
        <x-icon name="arrow-left" size="h-4 w-4" /> {{ __('Back to login') }}
    </a>

    <div class="mb-6 text-center">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ __('Forgot password?') }}</h2>
        <p class="text-sm text-gray-500 mt-1">
            {{ __('No problem. Enter your email address and we will send you a password reset link.') }}
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div class="space-y-1">
            <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
            <input id="email" class="block w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-1 focus:ring-brand text-sm shadow-sm transition placeholder:text-gray-400" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="name@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div class="mt-6">
            <button type="submit" class="w-full py-3 px-4 bg-dark hover:bg-black text-white font-medium rounded-xl shadow-md transition duration-200 text-sm">
                {{ __('Email Password Reset Link') }}
            </button>
        </div>

        <div class="text-center mt-6">
            <p class="text-sm text-gray-500">
                {{ __('Remember your password?') }}
                <a href="{{ route('login') }}" class="font-semibold text-dark hover:underline">{{ __('Sign in') }}</a>
            </p>
        </div>
    </form>
</x-guest-layout>
