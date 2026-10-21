<x-guest-layout>
    <div class="mb-6 text-center">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Create an account</h2>
        <p class="text-sm text-gray-500 mt-1">Start building better money habits today.</p>
    </div>

    @if (session('error'))
        <div class="mb-4 p-4 rounded-xl border border-red-200 bg-red-50">
            <p class="text-sm text-red-800">{{ session('error') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" x-data="{ mode: @js(old('phone') ? 'phone' : 'email'), showPassword: false, showConfirm: false }">
        @csrf

        <!-- Name -->
        <div class="space-y-1">
            <label for="nama" class="block text-sm font-medium text-gray-700">Full Name</label>
            <input id="nama" class="block w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-1 focus:ring-brand text-sm shadow-sm transition placeholder:text-gray-400" type="text" name="nama" :value="old('nama', old('name'))" required autofocus autocomplete="name" placeholder="Alex Johnson" />
            <x-input-error :messages="$errors->get('nama')" class="mt-1" />
        </div>

        <!-- Email Address (Email mode) -->
        <div class="mt-4 space-y-1" x-show="mode === 'email'">
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input id="email" x-ref="emailInput" class="block w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-1 focus:ring-brand text-sm shadow-sm transition placeholder:text-gray-400" type="email" name="email" :value="old('email')" autocomplete="username" placeholder="alex@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <!-- Phone Number (Phone mode) -->
        <div class="mt-4 space-y-1" x-show="mode === 'phone'" x-cloak>
            <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
            <input id="phone" x-ref="phoneInput" class="block w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand focus:ring-1 focus:ring-brand text-sm shadow-sm transition placeholder:text-gray-400" type="tel" name="phone" :value="old('phone')" autocomplete="tel" placeholder="08123456789 or +628123456789" />
            <x-input-error :messages="$errors->get('phone')" class="mt-1" />
            <x-input-error :messages="$errors->get('nomor_telepon')" class="mt-1" />
        </div>

        <!-- Password -->
        <div class="mt-4 space-y-1">
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <div class="relative">
                <input id="password" class="block w-full px-4 py-3 pr-10 rounded-xl border border-gray-200 focus:border-brand focus:ring-1 focus:ring-brand text-sm shadow-sm transition placeholder:text-gray-400"
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                required autocomplete="new-password"
                                placeholder="Create a password" />
                <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                    <svg x-show="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg x-show="showPassword" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                </button>
            </div>
            <p class="mt-1 text-xs text-gray-500">Minimal 8 karakter, mengandung huruf dan angka.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4 space-y-1">
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
            <div class="relative">
                <input id="password_confirmation" class="block w-full px-4 py-3 pr-10 rounded-xl border border-gray-200 focus:border-brand focus:ring-1 focus:ring-brand text-sm shadow-sm transition placeholder:text-gray-400"
                                :type="showConfirm ? 'text' : 'password'"
                                name="password_confirmation" required autocomplete="new-password"
                                placeholder="Confirm your password" />
                <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                    <svg x-show="!showConfirm" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg x-show="showConfirm" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
        </div>

        <div class="mt-6">
            <button type="submit" class="w-full py-3 px-4 bg-dark hover:bg-black text-white font-medium rounded-xl shadow-md transition duration-200 text-sm">
                Create account
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
            <a href="{{ route('auth.google', ['mode' => 'register']) }}" class="w-full flex items-center justify-center py-3 px-4 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition shadow-sm">
                <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24"><path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.11-6.72-4.95H1.2v3.15C3.18 21.31 7.23 24 12 24z"/><path fill="#FBBC05" d="M5.28 14.25c-.25-.72-.38-1.49-.38-2.25s.13-1.53.38-2.25V6.6H1.2C.44 8.13 0 9.87 0 12s.44 3.87 1.2 5.4l4.08-3.15z"/><path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.23 0 3.18 2.69 1.2 6.6l4.08 3.15c.95-2.84 3.6-4.95 6.72-4.95z"/></svg>
                Sign up with Google
            </a>
            <button type="button" x-show="mode === 'email'" @click="mode = 'phone'; $nextTick(() => $refs.phoneInput?.focus())" class="w-full flex items-center justify-center py-3 px-4 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition shadow-sm">
                <svg class="h-5 w-5 mr-2 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Continue with Phone
            </button>
            <button type="button" x-show="mode === 'phone'" x-cloak @click="mode = 'email'; $nextTick(() => $refs.emailInput?.focus())" class="w-full flex items-center justify-center py-3 px-4 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition shadow-sm">
                <svg class="h-5 w-5 mr-2 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Continue with Email
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
