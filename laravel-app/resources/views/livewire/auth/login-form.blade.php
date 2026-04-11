<div
    class="flex items-center justify-center min-h-screen bg-gradient-to-br from-unsoed-blue-100 via-white to-unsoed-gold-100">
    <div class="w-full max-w-md px-8 py-10 bg-white rounded-2xl shadow-xl" x-data="{ showPassword: false }">
        {{-- Header --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-unsoed-blue-600 rounded-2xl mb-4">
                <x-app.icon name="academic-cap" class="w-8 h-8 text-unsoed-gold-400" />
            </div>
            <h1 class="text-2xl font-bold text-gray-900">TopicModeling</h1>
            <p class="mt-2 text-sm text-gray-500">
                Sistem Analisis Evolusi Topik Riset Skripsi
            </p>
            <p class="mt-1 text-xs text-gray-400">
                S1 Teknik Informatika — UNSOED
            </p>
        </div>

        {{-- Error Message --}}
        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg"
                x-data="{ show: true }" x-show="show" x-transition>
                <div class="flex items-center justify-between">
                    <div>
                        @foreach ($errors->all() as $error)
                            <p class="flex items-center">
                                <x-app.icon name="exclamation-circle" class="w-4 h-4 mr-1.5 flex-shrink-0" />
                                {{ $error }}
                            </p>
                        @endforeach
                    </div>
                    <button @click="show = false" class="text-red-400 hover:text-red-600 ml-2">
                        <x-app.icon name="x-mark" class="w-4 h-4" />
                    </button>
                </div>
            </div>
        @endif

        <a href="{{ route('auth.google.redirect') }}"
            class="w-full mb-4 py-2.5 px-4 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium rounded-lg text-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-unsoed-blue-500 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="w-4.5 h-4.5 mr-2" aria-hidden="true">
                <path fill="#EA4335"
                    d="M24 9.5c3.54 0 6.72 1.22 9.22 3.61l6.89-6.89C35.93 2.35 30.44 0 24 0 14.64 0 6.56 5.38 2.6 13.22l8.01 6.22C12.48 13.12 17.76 9.5 24 9.5z" />
                <path fill="#4285F4"
                    d="M46.5 24.55c0-1.57-.14-3.09-.4-4.55H24v8.62h12.69c-.55 2.97-2.22 5.49-4.73 7.18l7.27 5.64c4.25-3.92 6.67-9.69 6.67-16.89z" />
                <path fill="#FBBC05"
                    d="M10.61 28.56a14.5 14.5 0 0 1 0-9.12L2.6 13.22a24 24 0 0 0 0 21.56l8.01-6.22z" />
                <path fill="#34A853"
                    d="M24 48c6.44 0 11.85-2.13 15.8-5.8l-7.27-5.64c-2.02 1.35-4.6 2.14-8.53 2.14-6.24 0-11.52-3.62-13.39-8.94L2.6 34.78C6.56 42.62 14.64 48 24 48z" />
            </svg>
            Masuk / Daftar dengan Google
        </a>
        @php
            $googleAllowedDomains = config('services.google.allowed_domains', []);
            if (!is_array($googleAllowedDomains) || empty($googleAllowedDomains)) {
                $googleAllowedDomains = [config('services.google.allowed_domain', 'mhs.unsoed.ac.id')];
            }
            $googleAllowedDomainsText = implode(' atau ', array_map(fn($domain) => '@' . trim((string) $domain), $googleAllowedDomains));
        @endphp
        <p class="mb-6 text-center text-xs text-gray-500">
            Hanya email domain <span class="font-semibold">{{ $googleAllowedDomainsText }}</span> yang diizinkan untuk akun mahasiswa.
        </p>

        <div class="relative mb-6">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-gray-200"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-white px-2 text-gray-400">Atau login manual</span>
            </div>
        </div>

        {{-- Login Form --}}
        <form wire:submit="login">
            {{-- Email --}}
            <div class="mb-5">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <x-app.icon name="envelope" class="w-5 h-5 text-gray-400" />
                    </div>
                    <input wire:model.blur="email" type="email" id="email" autofocus
                        class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 text-sm transition"
                        placeholder="admin@unsoed.ac.id">
                </div>
                @error('email')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div class="mb-5">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <x-app.icon name="key" class="w-5 h-5 text-gray-400" />
                    </div>
                    <input wire:model="password" :type="showPassword ? 'text' : 'password'" id="password"
                        class="w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 text-sm transition"
                        placeholder="••••••••">
                    <button type="button" @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                        <x-app.icon name="eye" x-show="!showPassword" class="w-5 h-5" />
                        <x-app.icon name="eye-slash" x-show="showPassword" x-cloak class="w-5 h-5" />
                    </button>
                </div>
                @error('password')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Remember Me --}}
            <div class="flex items-center justify-between mb-6">
                <label class="flex items-center text-sm text-gray-600 cursor-pointer">
                    <input wire:model="remember" type="checkbox"
                        class="mr-2 w-4 h-4 rounded border-gray-300 text-unsoed-blue-600 focus:ring-unsoed-blue-500">
                    Ingat saya
                </label>
            </div>

            {{-- Submit Button --}}
            <button type="submit"
                class="w-full py-2.5 px-4 bg-unsoed-blue-600 hover:bg-unsoed-blue-700 text-white font-medium rounded-lg text-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-unsoed-blue-500 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                wire:loading.attr="disabled">
                <x-app.icon variant="s" name="arrow-path" wire:loading wire:target="login" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" />
                <span wire:loading.remove wire:target="login">Masuk</span>
                <span wire:loading wire:target="login">Memproses...</span>
            </button>
        </form>

        {{-- Back to landing --}}
        <div class="mt-6 text-center">
            <a href="{{ route('landing') }}"
                class="text-sm text-unsoed-blue-600 hover:text-unsoed-blue-700 font-medium inline-flex items-center">
                <x-app.icon name="arrow-left" class="w-4 h-4 mr-1" />
                Kembali ke Beranda
            </a>
        </div>

        {{-- Footer --}}
        <div class="mt-6 text-center">
            <p class="text-xs text-gray-400">
                &copy; {{ date('Y') }} TopicModeling — Muhamad Galih
            </p>
        </div>
    </div>
</div>
