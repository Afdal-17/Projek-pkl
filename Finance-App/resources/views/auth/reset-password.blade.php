<x-guest-layout>
    <div class="mb-5 sm:mb-6">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-ink transition py-1.5 px-2 -ml-2 rounded-lg hover:bg-page active:bg-gray-100 group focus:outline-none focus:ring-2 focus:ring-brand/20">
            <x-icon name="arrow-left" size="h-4 w-4" class="transition-transform group-hover:-translate-x-0.5" />
            <span>Kembali ke Login</span>
        </a>
    </div>

    <div class="mb-6 text-center">
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Reset Password</h2>
        <p class="text-sm text-gray-500 mt-1.5">
            Masukkan password baru untuk akun Anda.
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if (session('error'))
        <div class="mb-4 p-4 rounded-xl border border-red-200 bg-red-50">
            <p class="text-sm text-red-800 break-words">{{ session('error') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('password.store') }}"
          class="w-full"
          x-data="{
              showPassword: false,
              showConfirm: false,
              password: '',
              password_confirmation: '',
              touchedPassword: false,
              touchedConfirm: false,
              serverErrors: {
                  email: @js($errors->first('email') ?? ''),
                  password: @js($errors->first('password') ?? ''),
                  password_confirmation: @js($errors->first('password_confirmation') ?? '')
              },
              get clientMismatch() {
                  return this.touchedConfirm && this.password_confirmation.length > 0 && this.password !== this.password_confirmation;
              },
              get clientMatch() {
                  return this.touchedConfirm && this.password_confirmation.length > 0 && this.password === this.password_confirmation;
              },
              handleSubmit(e) {
                  if (this.password !== this.password_confirmation) {
                      e.preventDefault();
                      this.touchedConfirm = true;
                      return false;
                  }
              }
          }"
          @submit="handleSubmit($event)">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') ?? $request->token ?? request()->route('token') }}">

        <!-- Email Address -->
        <div class="space-y-1.5">
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input id="email"
                   class="block w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50/70 focus:border-brand focus:ring-1 focus:ring-brand text-sm shadow-sm transition placeholder:text-gray-400"
                   type="email"
                   name="email"
                   value="{{ old('email', $request->email) }}"
                   required
                   autofocus
                   autocomplete="username"
                   placeholder="name@example.com"
                   @input="serverErrors.email = ''" />

            <div x-show="serverErrors.email" x-cloak class="mt-1">
                <p class="text-sm text-red-600 break-words" x-text="serverErrors.email"></p>
            </div>
            @if ($errors->has('email'))
                <noscript>
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </noscript>
            @endif
        </div>

        <!-- Password -->
        <div class="mt-4 space-y-1.5">
            <label for="password" class="block text-sm font-medium text-gray-700">Password Baru</label>
            <div class="relative">
                <input id="password"
                       class="block w-full px-4 py-3 pr-12 rounded-xl border border-gray-200 focus:border-brand focus:ring-1 focus:ring-brand text-sm shadow-sm transition placeholder:text-gray-400"
                       :type="showPassword ? 'text' : 'password'"
                       type="password"
                       name="password"
                       x-model="password"
                       required
                       autocomplete="new-password"
                       placeholder="Minimal 8 karakter"
                       @input="serverErrors.password = ''; touchedPassword = true" />
                <button type="button"
                        @click="showPassword = !showPassword"
                        class="absolute right-1 top-1/2 -translate-y-1/2 flex items-center justify-center h-10 w-10 text-gray-400 hover:text-gray-600 active:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand/20 rounded-lg transition"
                        aria-label="Toggle password visibility">
                    <svg x-show="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg x-show="showPassword" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                </button>
            </div>

            <!-- Server-side error message -->
            <div x-show="serverErrors.password" x-cloak class="mt-1">
                <p class="text-sm text-red-600 break-words" x-text="serverErrors.password"></p>
            </div>

            <!-- Live hint when password length is below 8 -->
            <div x-show="touchedPassword && password.length > 0 && password.length < 8" x-cloak class="mt-1">
                <p class="text-xs text-amber-600 flex items-center gap-1">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Password minimal harus 8 karakter.</span>
                </p>
            </div>

            @if ($errors->has('password'))
                <noscript>
                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
                </noscript>
            @endif
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <p class="mt-1 text-xs text-gray-500">Minimal 8 karakter, mengandung huruf dan angka.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4 space-y-1.5">
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Konfirmasi Password Baru</label>
            <div class="relative">
                <input id="password_confirmation"
                       class="block w-full px-4 py-3 pr-12 rounded-xl border border-gray-200 focus:border-brand focus:ring-1 focus:ring-brand text-sm shadow-sm transition placeholder:text-gray-400"
                       :type="showConfirm ? 'text' : 'password'"
                       type="password"
                       name="password_confirmation"
                       x-model="password_confirmation"
                       required
                       autocomplete="new-password"
                       placeholder="Ulangi password baru"
                       @input="serverErrors.password_confirmation = ''; touchedConfirm = true" />
                <button type="button"
                        @click="showConfirm = !showConfirm"
                        class="absolute right-1 top-1/2 -translate-y-1/2 flex items-center justify-center h-10 w-10 text-gray-400 hover:text-gray-600 active:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand/20 rounded-lg transition"
                        aria-label="Toggle confirm password visibility">
                    <svg x-show="!showConfirm" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg x-show="showConfirm" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                </button>
            </div>

            <!-- Server-side error message -->
            <div x-show="serverErrors.password_confirmation" x-cloak class="mt-1">
                <p class="text-sm text-red-600 break-words" x-text="serverErrors.password_confirmation"></p>
            </div>

            <!-- Live mismatch message (disappears immediately when matching) -->
            <div x-show="clientMismatch" x-cloak class="mt-1">
                <p class="text-xs text-red-600 flex items-center gap-1">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Konfirmasi password tidak cocok dengan password baru.</span>
                </p>
            </div>

            <!-- Live match indicator -->
            <div x-show="clientMatch" x-cloak class="mt-1">
                <p class="text-xs text-emerald-600 flex items-center gap-1">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Password cocok.</span>
                </p>
            </div>

            @if ($errors->has('password_confirmation'))
                <noscript>
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                </noscript>
            @endif
        </div>

        <div class="mt-6">
            <button type="submit"
                    class="w-full py-3 sm:py-3.5 px-4 bg-dark hover:bg-black active:bg-gray-900 text-white font-medium rounded-xl shadow-md transition duration-200 text-sm flex items-center justify-center gap-2 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-dark">
                <span>Reset Password</span>
            </button>
        </div>

        <div class="text-center mt-6">
            <p class="text-sm text-gray-500">
                Sudah ingat password Anda?
                <a href="{{ route('login') }}" class="font-semibold text-dark hover:underline">Masuk</a>
            </p>
        </div>
    </form>
</x-guest-layout>
