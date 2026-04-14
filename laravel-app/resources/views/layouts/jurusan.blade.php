<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard Jurusan') - Informatika Universitas Jenderal Soedirman</title>
    <link rel="icon" type="image/png" href="{{ asset('logo-unsoed.png') }}">

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
                        <x-app.icon variant="o" name="home" class="w-4.5 h-4.5 text-unsoed-blue-800" />
                    </div>
                    <span class="text-lg font-bold text-white">Informatika UNSOED</span>
                </a>
                <button @click="sidebarOpen = false" class="lg:hidden text-unsoed-blue-300 hover:text-white">
                    <x-app.icon name="x-mark" class="w-6 h-6" />
                </button>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                <p class="px-3 mb-3 text-xs font-semibold text-unsoed-blue-400 uppercase tracking-wider">Menu Utama</p>

                <a href="{{ route('jurusan.dashboard') }}"
                    class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('jurusan.dashboard') ? 'bg-unsoed-blue-700 text-unsoed-gold-400' : 'text-unsoed-blue-200 hover:bg-unsoed-blue-700 hover:text-white' }}">
                    <x-app.icon variant="o" name="home" class="w-5 h-5 mr-3 {{ request()->routeIs('jurusan.dashboard') ? 'text-unsoed-gold-400' : 'text-unsoed-blue-400' }}" />
                    Dashboard
                </a>

                <a href="{{ route('jurusan.scraping.index') }}"
                    class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('jurusan.scraping.*') ? 'bg-unsoed-blue-700 text-unsoed-gold-400' : 'text-unsoed-blue-200 hover:bg-unsoed-blue-700 hover:text-white' }}">
                    <x-app.icon variant="o" name="cloud-arrow-down" class="w-5 h-5 mr-3 {{ request()->routeIs('jurusan.scraping.*') ? 'text-unsoed-gold-400' : 'text-unsoed-blue-400' }}" />
                    Scraping
                </a>

                <a href="{{ route('jurusan.skripsi.index') }}"
                    class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('jurusan.skripsi.*') ? 'bg-unsoed-blue-700 text-unsoed-gold-400' : 'text-unsoed-blue-200 hover:bg-unsoed-blue-700 hover:text-white' }}">
                    <x-app.icon variant="o" name="book-open" class="w-5 h-5 mr-3 {{ request()->routeIs('jurusan.skripsi.*') ? 'text-unsoed-gold-400' : 'text-unsoed-blue-400' }}" />
                    Data Skripsi
                </a>

                <a href="{{ route('jurusan.akun.index') }}"
                    class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('jurusan.akun.*') ? 'bg-unsoed-blue-700 text-unsoed-gold-400' : 'text-unsoed-blue-200 hover:bg-unsoed-blue-700 hover:text-white' }}">
                    <x-app.icon variant="o" name="users" class="w-5 h-5 mr-3 {{ request()->routeIs('jurusan.akun.*') ? 'text-unsoed-gold-400' : 'text-unsoed-blue-400' }}" />
                    Manajemen Akun
                </a>

                <p class="px-3 mt-6 mb-3 text-xs font-semibold text-unsoed-blue-400 uppercase tracking-wider">Analisis
                </p>

                <a href="{{ route('jurusan.topic-modeling') }}"
                    class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('jurusan.topic-modeling') ? 'bg-unsoed-blue-700 text-unsoed-gold-400' : 'text-unsoed-blue-200 hover:bg-unsoed-blue-700 hover:text-white' }}">
                    <x-app.icon variant="o" name="light-bulb" class="w-5 h-5 mr-3 {{ request()->routeIs('jurusan.topic-modeling') ? 'text-unsoed-gold-400' : 'text-unsoed-blue-400' }}" />
                    Analisis Topik
                </a>

                <a href="{{ route('jurusan.topic-curation') }}"
                    class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('jurusan.topic-curation') ? 'bg-unsoed-blue-700 text-unsoed-gold-400' : 'text-unsoed-blue-200 hover:bg-unsoed-blue-700 hover:text-white' }}">
                    <x-app.icon variant="o" name="tag" class="w-5 h-5 mr-3 {{ request()->routeIs('jurusan.topic-curation') ? 'text-unsoed-gold-400' : 'text-unsoed-blue-400' }}" />
                    Kurasi Topik
                </a>

                <a href="{{ route('jurusan.visualisasi') }}"
                    class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('jurusan.visualisasi') ? 'bg-unsoed-blue-700 text-unsoed-gold-400' : 'text-unsoed-blue-200 hover:bg-unsoed-blue-700 hover:text-white' }}">
                    <x-app.icon variant="o" name="chart-bar-square" class="w-5 h-5 mr-3 {{ request()->routeIs('jurusan.visualisasi') ? 'text-unsoed-gold-400' : 'text-unsoed-blue-400' }}" />
                    Visualisasi
                </a>
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
                            <x-app.icon name="ellipsis-vertical" class="w-5 h-5" />
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
                                <x-app.icon name="pencil-square" class="w-4 h-4 mr-2 text-gray-400" />
                                Edit Profil
                            </a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:text-red-700 hover:bg-red-50 focus:bg-red-50 font-medium transition-colors">
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

    {{-- Main Content Area --}}
    <div :class="sidebarCollapsed ? 'lg:ml-0' : 'lg:ml-64'"
        class="min-h-screen flex flex-col transition-all duration-300">
        {{-- Top Bar --}}
        <header
            class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-4 sm:px-6 lg:px-8 sticky top-0 z-30">
            <div class="flex items-center">
                {{-- Mobile hamburger --}}
                <button @click="sidebarOpen = true" class="lg:hidden text-gray-500 hover:text-gray-700 mr-3">
                    <x-app.icon name="bars-3" class="w-6 h-6" />
                </button>
                {{-- Desktop sidebar toggle --}}
                <button @click="sidebarCollapsed = !sidebarCollapsed"
                    class="hidden lg:flex items-center text-gray-500 hover:text-unsoed-blue-600 mr-3 p-1.5 rounded-lg hover:bg-gray-100 transition">
                    <x-app.icon name="chevron-double-left" x-show="!sidebarCollapsed" class="w-5 h-5" />
                    <x-app.icon name="chevron-double-right" x-show="sidebarCollapsed" x-cloak class="w-5 h-5" />
                </button>
                <h2 class="text-lg font-semibold text-gray-800">@yield('page-title', 'Dashboard')</h2>
            </div>
            <div class="flex items-center space-x-3">
                {{-- Notification --}}
                <div x-data="{ notifOpen: false }" class="relative">
                    <button @click="notifOpen = !notifOpen"
                        class="relative p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                        <x-app.icon name="bell" class="w-5 h-5" />
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
                        <x-app.icon variant="o" name="check-circle" class="w-5 h-5 mr-2 text-green-500" />
                        <span class="text-sm font-medium">{{ session('success') }}</span>
                    </div>
                    <button @click="show = false" class="text-green-500 hover:text-green-700">
                        <x-app.icon name="x-mark" class="w-4 h-4" />
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
                        <x-app.icon variant="o" name="x-circle" class="w-5 h-5 mr-2 text-red-500" />
                        <span class="text-sm font-medium">{{ session('error') }}</span>
                    </div>
                    <button @click="show = false" class="text-red-500 hover:text-red-700">
                        <x-app.icon name="x-mark" class="w-4 h-4" />
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
                &copy; {{ date('Y') }} Informatika Universitas Jenderal Soedirman
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
                        <x-app.icon variant="o" name="check-circle" class="w-5 h-5 text-green-500" />
                    </template>
                    <template x-if="toast.type === 'error'">
                        <x-app.icon variant="o" name="x-circle" class="w-5 h-5 text-red-500" />
                    </template>
                    <template x-if="toast.type === 'warning'">
                        <x-app.icon variant="o" name="exclamation-triangle" class="w-5 h-5 text-amber-400" />
                    </template>
                    <template x-if="toast.type === 'info'">
                        <x-app.icon variant="o" name="information-circle" class="w-5 h-5 text-blue-500" />
                    </template>
                </div>

                {{-- Message --}}
                <p class="flex-1 text-sm text-gray-800 font-medium leading-snug" x-text="toast.message"></p>

                {{-- Close --}}
                <button @click="remove(toast.id)" class="flex-shrink-0 text-gray-400 hover:text-gray-600 mt-0.5">
                    <x-app.icon name="x-mark" class="w-4 h-4" />
                </button>
            </div>
        </template>
    </div>

</body>

</html>
