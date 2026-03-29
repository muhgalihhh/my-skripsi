<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard Jurusan') - {{ config('app.name') }}</title>

    <!-- Fonts -->
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

<body class="bg-gray-50 min-h-screen" x-data="{ sidebarOpen: false, sidebarCollapsed: false }">

    {{-- Mobile Sidebar Overlay --}}
    <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"
        @click="sidebarOpen = false">
    </div>

    {{-- Sidebar --}}
    <aside
        :class="{
            'translate-x-0': sidebarOpen,
            '-translate-x-full': !sidebarOpen,
            'lg:translate-x-0': !sidebarCollapsed,
            'lg:-translate-x-full': sidebarCollapsed
        }"
        class="fixed inset-y-0 left-0 z-50 w-64 bg-unsoed-blue-800 transform transition-transform duration-300 ease-in-out">
        <div class="flex flex-col h-full">
            {{-- Logo --}}
            <div class="flex items-center justify-between h-16 px-5 border-b border-unsoed-blue-700">
                <a href="{{ route('jurusan.dashboard') }}" class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-unsoed-gold-400 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4.5 h-4.5 text-unsoed-blue-800" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <span class="text-lg font-bold text-white">TopicModeling</span>
                </a>
                <button @click="sidebarOpen = false" class="lg:hidden text-unsoed-blue-300 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                <p class="px-3 mb-3 text-xs font-semibold text-unsoed-blue-400 uppercase tracking-wider">Menu Utama</p>

                <a href="{{ route('jurusan.dashboard') }}"
                    class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('jurusan.dashboard') ? 'bg-unsoed-blue-700 text-unsoed-gold-400' : 'text-unsoed-blue-200 hover:bg-unsoed-blue-700 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3 {{ request()->routeIs('jurusan.dashboard') ? 'text-unsoed-gold-400' : 'text-unsoed-blue-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Dashboard
                </a>

                <a href="{{ route('jurusan.scraping.index') }}"
                    class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('jurusan.scraping.*') ? 'bg-unsoed-blue-700 text-unsoed-gold-400' : 'text-unsoed-blue-200 hover:bg-unsoed-blue-700 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3 {{ request()->routeIs('jurusan.scraping.*') ? 'text-unsoed-gold-400' : 'text-unsoed-blue-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Scraping
                </a>

                <a href="{{ route('jurusan.skripsi.index') }}"
                    class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('jurusan.skripsi.*') ? 'bg-unsoed-blue-700 text-unsoed-gold-400' : 'text-unsoed-blue-200 hover:bg-unsoed-blue-700 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3 {{ request()->routeIs('jurusan.skripsi.*') ? 'text-unsoed-gold-400' : 'text-unsoed-blue-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    Data Skripsi
                </a>

                <a href="{{ route('jurusan.akun.index') }}"
                    class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('jurusan.akun.*') ? 'bg-unsoed-blue-700 text-unsoed-gold-400' : 'text-unsoed-blue-200 hover:bg-unsoed-blue-700 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3 {{ request()->routeIs('jurusan.akun.*') ? 'text-unsoed-gold-400' : 'text-unsoed-blue-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Manajemen Akun
                </a>

                <p class="px-3 mt-6 mb-3 text-xs font-semibold text-unsoed-blue-400 uppercase tracking-wider">Analisis
                </p>

                <a href="{{ route('jurusan.topic-modeling') }}"
                    class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('jurusan.topic-modeling') ? 'bg-unsoed-blue-700 text-unsoed-gold-400' : 'text-unsoed-blue-200 hover:bg-unsoed-blue-700 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3 {{ request()->routeIs('jurusan.topic-modeling') ? 'text-unsoed-gold-400' : 'text-unsoed-blue-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                    Topic Modeling
                </a>

                <span
                    class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium text-unsoed-blue-500 cursor-not-allowed">
                    <svg class="w-5 h-5 mr-3 text-unsoed-blue-500" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Visualisasi
                    <span
                        class="ml-auto text-xs bg-unsoed-blue-700 text-unsoed-blue-300 px-2 py-0.5 rounded-full">Soon</span>
                </span>
            </nav>

            {{-- User Card --}}
            <div class="px-4 py-4 border-t border-unsoed-blue-700">
                <div class="flex items-center space-x-3" x-data="{ showMenu: false }">
                    <div
                        class="flex-shrink-0 w-9 h-9 rounded-full bg-unsoed-gold-400 flex items-center justify-center">
                        <span class="text-sm font-bold text-unsoed-blue-800">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-unsoed-blue-400 truncate">{{ ucfirst(Auth::user()->role) }}</p>
                    </div>
                    <div class="relative">
                        <button @click="showMenu = !showMenu" class="text-unsoed-blue-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                            </svg>
                        </button>
                        <div x-show="showMenu" @click.away="showMenu = false"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute bottom-full right-0 mb-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                            <a href="{{ route('jurusan.profil') }}"
                                class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Edit Profil
                            </a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    {{-- Main Content Area --}}
    <div :class="sidebarCollapsed ? 'lg:ml-0' : 'lg:ml-64'"
        class="min-h-screen flex flex-col transition-all duration-300">
        {{-- Top Bar --}}
        <header
            class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-4 sm:px-6 lg:px-8 sticky top-0 z-30">
            <div class="flex items-center">
                {{-- Mobile hamburger --}}
                <button @click="sidebarOpen = true" class="lg:hidden text-gray-500 hover:text-gray-700 mr-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                {{-- Desktop sidebar toggle --}}
                <button @click="sidebarCollapsed = !sidebarCollapsed"
                    class="hidden lg:flex items-center text-gray-500 hover:text-unsoed-blue-600 mr-3 p-1.5 rounded-lg hover:bg-gray-100 transition">
                    <svg x-show="!sidebarCollapsed" class="w-5 h-5" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                    <svg x-show="sidebarCollapsed" x-cloak class="w-5 h-5" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                    </svg>
                </button>
                <h2 class="text-lg font-semibold text-gray-800">@yield('page-title', 'Dashboard')</h2>
            </div>
            <div class="flex items-center space-x-3">
                {{-- Notification --}}
                <div x-data="{ notifOpen: false }" class="relative">
                    <button @click="notifOpen = !notifOpen"
                        class="relative p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </button>
                    <div x-show="notifOpen" @click.away="notifOpen = false" x-transition
                        class="absolute right-0 mt-2 w-72 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50">
                        <p class="px-4 py-3 text-sm text-gray-500 text-center">Tidak ada notifikasi baru</p>
                    </div>
                </div>
            </div>
        </header>

        {{-- Flash Messages with Alpine.js auto-dismiss --}}
        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-2" class="mx-4 sm:mx-6 lg:mx-8 mt-4">
                <div
                    class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm font-medium">{{ session('success') }}</span>
                    </div>
                    <button @click="show = false" class="text-green-500 hover:text-green-700">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div x-data="{ show: true }" x-show="show" x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="mx-4 sm:mx-6 lg:mx-8 mt-4">
                <div
                    class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm font-medium">{{ session('error') }}</span>
                    </div>
                    <button @click="show = false" class="text-red-500 hover:text-red-700">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        {{-- Main Content --}}
        <main class="flex-1 px-4 sm:px-6 lg:px-8 py-8">
            {{ $slot }}
        </main>

        {{-- Footer --}}
        <footer class="border-t border-gray-200 bg-white px-4 sm:px-6 lg:px-8 py-4">
            <p class="text-xs text-gray-400 text-center">
                &copy; {{ date('Y') }} TopicModeling — S1 Teknik Informatika UNSOED
            </p>
        </footer>
    </div>

    @livewireScripts

    {{-- ═══════════════════════════════════════════════════
         Global Toast Notification System
         Livewire v4: dispatch('toast', type: '...', message: '...')
         Menggunakan Livewire.on() (paling reliable di v4)
    ════════════════════════════════════════════════════ --}}
    <div x-data="{
        toasts: [],
        add(type, message) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type, message, visible: true });
            setTimeout(() => this.remove(id), 4500);
        },
        remove(id) {
            const t = this.toasts.find(t => t.id === id);
            if (t) t.visible = false;
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 400);
        },
        init() {
            // Livewire v3/v4: $this->dispatch('toast', type: 'success', message: 'hello')
            // Livewire.on callback receives a single object: { type, message }
            Livewire.on('toast', (data) => {
                console.log('[toast-debug] raw data:', JSON.stringify(data));
                let type = 'success';
                let message = '';
                if (data && typeof data === 'object') {
                    type = data.type || 'success';
                    message = data.message || '';
                } else if (typeof data === 'string') {
                    message = data;
                }
                console.log('[toast-debug] parsed:', type, message);
                if (message) this.add(type, message);
            });
        }
    }" class="fixed top-5 right-5 z-[9999] flex flex-col gap-2.5 pointer-events-none"
        style="width: 20rem;" aria-live="polite">

        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.visible" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-8"
                :class="{
                    'bg-white border-l-4 border-green-500': toast.type === 'success',
                    'bg-white border-l-4 border-red-500': toast.type === 'error',
                    'bg-white border-l-4 border-amber-400': toast.type === 'warning',
                    'bg-white border-l-4 border-blue-500': toast.type === 'info',
                }"
                class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-lg shadow-lg">

                {{-- Icon --}}
                <div class="flex-shrink-0 mt-0.5">
                    <template x-if="toast.type === 'success'">
                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                    </template>
                    <template x-if="toast.type === 'error'">
                        <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd" />
                        </svg>
                    </template>
                    <template x-if="toast.type === 'warning'">
                        <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                    </template>
                    <template x-if="toast.type === 'info'">
                        <svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd" />
                        </svg>
                    </template>
                </div>

                {{-- Message --}}
                <p class="flex-1 text-sm text-gray-800 font-medium leading-snug" x-text="toast.message"></p>

                {{-- Close --}}
                <button @click="remove(toast.id)" class="flex-shrink-0 text-gray-400 hover:text-gray-600 mt-0.5">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </template>
    </div>

</body>

</html>
