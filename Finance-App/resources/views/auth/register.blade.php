<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Create an account</h2>
        <p class="text-sm text-gray-500 mt-1">Start building better money habits today.</p>
    </div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div class="space-y-1">
            <label for="nama" class="block text-sm font-medium text-gray-700">{{ __('Full Name') }}</label>
            <input id="nama" class="block w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-1 focus:ring-brand text-sm shadow-sm transition placeholder:text-gray-400" type="text" name="nama" :value="old('nama', old('name'))" required autofocus autocomplete="name" placeholder="Alex Johnson" />
            <x-input-error :messages="$errors->get('nama')" class="mt-1" />
        </div>

        <!-- Email Address -->
        <div class="mt-4 space-y-1">
            <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
            <input id="email" class="block w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-1 focus:ring-brand text-sm shadow-sm transition placeholder:text-gray-400" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="alex@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <!-- Password -->
        <div class="mt-4 space-y-1">
            <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
            <input id="password" class="block w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-1 focus:ring-brand text-sm shadow-sm transition placeholder:text-gray-400"
                            type="password"
                            name="password"
                            required autocomplete="new-password"
                            placeholder="Create a password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4 space-y-1">
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">{{ __('Confirm Password') }}</label>
            <input id="password_confirmation" class="block w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-1 focus:ring-brand text-sm shadow-sm transition placeholder:text-gray-400"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password"
                            placeholder="Confirm your password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
        </div>

        <div class="mt-6">
            <button type="submit" class="w-full py-3 px-4 bg-dark hover:bg-black text-white font-medium rounded-xl shadow-md transition duration-200 text-sm">
                {{ __('Create account') }}
            </button>
        </div>

        <div class="relative my-6">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-200"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-white px-3 text-gray-400 font-medium">or</span>
            </div>
        </div>

        <div class="space-y-3">
            <button type="button" class="w-full flex items-center justify-center py-3 px-4 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition shadow-sm">
                <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24"><path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.11-6.72-4.95H1.2v3.15C3.18 21.31 7.23 24 12 24z"/><path fill="#FBBC05" d="M5.28 14.25c-.25-.72-.38-1.49-.38-2.25s.13-1.53.38-2.25V6.6H1.2C.44 8.13 0 9.87 0 12s.44 3.87 1.2 5.4l4.08-3.15z"/><path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.23 0 3.18 2.69 1.2 6.6l4.08 3.15c.95-2.84 3.6-4.95 6.72-4.95z"/></svg>
                Continue with Google
            </button>
            <button type="button" class="w-full flex items-center justify-center py-3 px-4 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition shadow-sm">
                <svg class="h-5 w-5 mr-2 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Continue with Phone
            </button>
        </div>

        <div class="text-center mt-6">
            <p class="text-sm text-gray-500">
                Already have an account? 
                <a href="{{ route('login') }}" class="font-semibold text-dark hover:underline">Sign in</a>
            </p>
        </div>
    </form>
</x-guest-layout>
