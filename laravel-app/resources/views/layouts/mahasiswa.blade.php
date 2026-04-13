<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard Mahasiswa') - {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    @livewireStyles

    <style>
        [x-cloak] {
            display: none !important;
        }

        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen" x-data="{ sidebarOpen: false, sidebarCollapsed: false, topbarSearchOpen: false, topbarSearchQuery: '' }" @keydown.escape.window="topbarSearchOpen = false">
    <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"
        @click="sidebarOpen = false"></div>

    <aside
        :class="{
            'translate-x-0': sidebarOpen,
            '-translate-x-full': !sidebarOpen,
            'lg:translate-x-0': !sidebarCollapsed,
            'lg:-translate-x-full': sidebarCollapsed
        }"
        class="fixed inset-y-0 left-0 z-50 w-64 bg-unsoed-blue-800 transform transition-transform duration-300 ease-in-out">
        <div class="flex h-full flex-col">
            <div class="flex h-16 items-center justify-between border-b border-unsoed-blue-700 px-5">
                <a href="{{ route('mahasiswa.dashboard') }}" class="flex items-center space-x-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-unsoed-gold-400">
                        <x-app.icon variant="o" name="academic-cap" class="h-4.5 w-4.5 text-unsoed-blue-800" />
                    </div>
                    <span class="text-lg font-bold text-white">Mahasiswa</span>
                </a>

                <button @click="sidebarOpen = false" class="text-unsoed-blue-300 hover:text-white lg:hidden">
                    <x-app.icon name="x-mark" class="h-6 w-6" />
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-6">
                <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-unsoed-blue-400">Menu Utama</p>

                <a href="{{ route('mahasiswa.dashboard') }}"
                    class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium transition-colors
                    {{ request()->routeIs('mahasiswa.dashboard') ? 'bg-unsoed-blue-700 text-unsoed-gold-400' : 'text-unsoed-blue-200 hover:bg-unsoed-blue-700 hover:text-white' }}">
                    <x-app.icon variant="o" name="chart-bar-square"
                        class="mr-3 h-5 w-5 {{ request()->routeIs('mahasiswa.dashboard') ? 'text-unsoed-gold-400' : 'text-unsoed-blue-400' }}" />
                    Dashboard Topik
                </a>

                <a href="{{ route('mahasiswa.rekomendasi-judul.index') }}"
                    class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium transition-colors
                    {{ request()->routeIs('mahasiswa.rekomendasi-judul.*') ? 'bg-unsoed-blue-700 text-unsoed-gold-400' : 'text-unsoed-blue-200 hover:bg-unsoed-blue-700 hover:text-white' }}">
                    <x-app.icon variant="o" name="light-bulb"
                        class="mr-3 h-5 w-5 {{ request()->routeIs('mahasiswa.rekomendasi-judul.*') ? 'text-unsoed-gold-400' : 'text-unsoed-blue-400' }}" />
                    Rekomendasi Judul
                </a>
            </nav>

            <div class="border-t border-unsoed-blue-700 px-4 py-4">
                <div class="flex items-center space-x-3" x-data="{ showMenu: false }">
                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-unsoed-gold-400">
                        <span class="text-sm font-bold text-unsoed-blue-800">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-white">{{ Auth::user()->name }}</p>
                        <p class="truncate text-xs text-unsoed-blue-400">{{ ucfirst(Auth::user()->role) }}</p>
                    </div>
                    <div class="relative">
                        <button @click="showMenu = !showMenu" class="text-unsoed-blue-400 hover:text-white">
                            <x-app.icon name="ellipsis-vertical" class="w-5 h-5" />
                        </button>
                        <div x-show="showMenu" @click.away="showMenu = false"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute bottom-full right-0 mb-2 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg z-50">
                            <a href="{{ route('mahasiswa.profil') }}"
                                class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <x-app.icon name="pencil-square" class="w-4 h-4 mr-2 text-gray-400" />
                                Edit Profil
                            </a>
                            <div class="my-1 border-t border-gray-100"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="flex w-full items-center px-4 py-2 text-sm text-red-600 hover:text-red-700 hover:bg-red-50 focus:bg-red-50 font-medium transition-colors">
                                    <x-app.icon name="arrow-right-on-rectangle" class="w-4 h-4 mr-2" />
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <div :class="sidebarCollapsed ? 'lg:ml-0' : 'lg:ml-64'" class="min-h-screen flex flex-col transition-all duration-300">
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-8">
            <div class="flex items-center">
                <button @click="sidebarOpen = true" class="mr-3 text-gray-500 hover:text-gray-700 lg:hidden">
                    <x-app.icon name="bars-3" class="h-6 w-6" />
                </button>
                <button @click="sidebarCollapsed = !sidebarCollapsed"
                    class="hidden lg:flex items-center text-gray-500 hover:text-unsoed-blue-600 mr-3 p-1.5 rounded-lg hover:bg-gray-100 transition">
                    <x-app.icon name="chevron-double-left" x-show="!sidebarCollapsed" class="w-5 h-5" />
                    <x-app.icon name="chevron-double-right" x-show="sidebarCollapsed" x-cloak class="w-5 h-5" />
                </button>
                <h2 class="text-lg font-semibold text-gray-800">@yield('page-title', 'Dashboard Mahasiswa')</h2>
            </div>
            <div class="flex items-center space-x-3">
                @if (request()->routeIs('mahasiswa.dashboard'))
                    <div class="relative" @click.away="topbarSearchOpen = false">
                        <button type="button" aria-label="Toggle smart search"
                            @click="topbarSearchOpen = !topbarSearchOpen; if (topbarSearchOpen) { $nextTick(() => $refs.topbarSearchInput?.focus()) }"
                            :class="topbarSearchOpen ? 'border-unsoed-blue-300 bg-unsoed-blue-50 text-unsoed-blue-700' : 'border-gray-200 bg-white text-gray-600 hover:border-unsoed-blue-300 hover:text-unsoed-blue-700'"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border transition">
                            <x-app.icon name="magnifying-glass" class="h-4 w-4" />
                        </button>

                        <div x-show="topbarSearchOpen" x-cloak x-transition.opacity.duration.150ms
                            class="absolute right-0 top-11 z-40 w-[min(92vw,34rem)] rounded-xl border border-gray-200 bg-white p-3 shadow-lg">
                            <form @submit.prevent="
                                const query = (topbarSearchQuery || '').trim();
                                if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                                    window.Livewire.dispatch('mahasiswa-search-submit', { query });
                                }
                                topbarSearchOpen = false;
                            " class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto]">
                                <input type="text" x-ref="topbarSearchInput" x-model="topbarSearchQuery"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                                    placeholder="Cari topik skripsi...">
                                <x-ui.button type="submit" variant="primary" class="!px-3 !py-2 shrink-0">
                                    Cari
                                </x-ui.button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </header>

        <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

        <footer class="border-t border-gray-200 bg-white px-4 py-4 sm:px-6 lg:px-8">
            <p class="text-center text-xs text-gray-400">
                &copy; {{ date('Y') }} TopicModeling — Mahasiswa S1 Teknik Informatika UNSOED
            </p>
        </footer>
    </div>

    @livewireScripts
</body>

</html>
