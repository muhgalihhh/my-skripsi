<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TopicModeling — Sistem Analisis Evolusi Topik Riset Skripsi UNSOED</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-white min-h-screen flex flex-col">
    {{-- Navbar --}}
    <nav class="bg-unsoed-blue-700 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-3">
                    {{-- Logo Icon --}}
                    <div class="w-9 h-9 bg-unsoed-gold-400 rounded-lg flex items-center justify-center">
                        <x-app.icon variant="o" name="chart-bar-square" class="w-5 h-5 text-unsoed-blue-800" />
                    </div>
                    <span class="text-lg font-bold">TopicModeling</span>
                </div>
                <div class="flex items-center space-x-4">
                    @auth
                        @php
                            $dashboardRoute = auth()->user()->isMahasiswa()
                                ? route('mahasiswa.dashboard')
                                : route('jurusan.dashboard');
                        @endphp
                        <a href="{{ $dashboardRoute }}"
                            class="bg-unsoed-gold-400 hover:bg-unsoed-gold-300 text-unsoed-blue-800 font-semibold px-4 py-2 rounded-lg text-sm transition">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                            class="bg-unsoed-gold-400 hover:bg-unsoed-gold-300 text-unsoed-blue-800 font-semibold px-4 py-2 rounded-lg text-sm transition">
                            Masuk
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    {{-- Hero Section --}}
    <section class="relative overflow-hidden py-20 text-white lg:py-28">
        <div class="absolute inset-0">
            <div class="h-full w-full bg-cover bg-center"
                style="background-image: url('{{ asset('hero image.jpeg') }}');"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-unsoed-blue-900/85 via-unsoed-blue-800/80 to-unsoed-blue-700/65"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <div>
                    <div
                        class="inline-flex items-center bg-unsoed-gold-400/20 text-unsoed-gold-300 text-sm font-medium px-4 py-1.5 rounded-full mb-6">
                        <x-app.icon name="academic-cap" class="w-4 h-4 mr-2" />
                        S1 Teknik Informatika — UNSOED
                    </div>
                    <h1 class="text-4xl lg:text-5xl font-extrabold leading-tight mb-6">
                        Sistem Analisis<br>
                        <span class="text-unsoed-gold-400">Evolusi Topik</span><br>
                        Riset Skripsi
                    </h1>
                    <p class="text-lg text-unsoed-blue-200 mb-8 max-w-lg">
                        Platform untuk menganalisis tren dan evolusi topik riset skripsi
                        di lingkungan Universitas Jenderal Soedirman menggunakan <strong
                            class="text-white">BERTopic</strong> dan <strong class="text-white">Topic Modeling</strong>.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        @auth
                            @php
                                $dashboardRoute = auth()->user()->isMahasiswa()
                                    ? route('mahasiswa.dashboard')
                                    : route('jurusan.dashboard');
                            @endphp
                            <a href="{{ $dashboardRoute }}"
                                class="bg-unsoed-gold-400 hover:bg-unsoed-gold-300 text-unsoed-blue-800 font-bold px-8 py-3 rounded-xl text-sm transition inline-flex items-center justify-center">
                                <x-app.icon name="home" class="w-5 h-5 mr-2" />
                                Buka Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                                class="bg-unsoed-gold-400 hover:bg-unsoed-gold-300 text-unsoed-blue-800 font-bold px-8 py-3 rounded-xl text-sm transition inline-flex items-center justify-center">
                                <x-app.icon name="arrow-right-on-rectangle" class="w-5 h-5 mr-2" />
                                Masuk ke Sistem
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Features Section --}}
    <section class="py-16 lg:py-24 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-3">Fitur Utama</h2>
                <p class="text-gray-500 max-w-2xl mx-auto">Sistem ini dirancang untuk membantu menganalisis tren
                    penelitian skripsi secara menyeluruh</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                {{-- Feature 1: Scraping --}}
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                    <div class="w-12 h-12 bg-unsoed-blue-100 rounded-xl flex items-center justify-center mb-4">
                        <x-app.icon variant="o" name="cloud-arrow-down" class="w-6 h-6 text-unsoed-blue-600" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Web Scraping</h3>
                    <p class="text-sm text-gray-500">
                        Mengambil data skripsi secara otomatis dari Repository UNSOED dengan cepat dan efisien.
                    </p>
                </div>

                {{-- Feature 2: Topic Modeling --}}
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                    <div class="w-12 h-12 bg-unsoed-gold-100 rounded-xl flex items-center justify-center mb-4">
                        <x-app.icon name="light-bulb" class="w-6 h-6 text-unsoed-gold-600" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Topic Modeling</h3>
                    <p class="text-sm text-gray-500">
                        Analisis topik menggunakan BERTopic untuk menemukan pola dan kluster topik penelitian.
                    </p>
                </div>

                {{-- Feature 3: Visualisasi --}}
                <div
                    class="bg-white rounded-xl p-6 shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                    <div class="w-12 h-12 bg-unsoed-blue-100 rounded-xl flex items-center justify-center mb-4">
                        <x-app.icon variant="o" name="chart-bar-square" class="w-6 h-6 text-unsoed-blue-600" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Visualisasi Data</h3>
                    <p class="text-sm text-gray-500">
                        Tampilan visual distribusi topik, tren per tahun, dan evolusi topik penelitian skripsi.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- How it Works Section --}}
    <section class="py-16 lg:py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-3">Cara Kerja</h2>
                <p class="text-gray-500 max-w-2xl mx-auto">Proses analisis topik riset skripsi dilakukan melalui 3
                    tahap utama</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                {{-- Step 1 --}}
                <div class="text-center">
                    <div
                        class="w-14 h-14 bg-unsoed-blue-600 text-white rounded-full flex items-center justify-center text-xl font-bold mx-auto mb-4">
                        1</div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Pengambilan Data</h3>
                    <p class="text-sm text-gray-500">Data skripsi diambil otomatis dari Repository UNSOED melalui
                        proses web scraping.</p>
                </div>
                {{-- Step 2 --}}
                <div class="text-center">
                    <div
                        class="w-14 h-14 bg-unsoed-gold-400 text-unsoed-blue-800 rounded-full flex items-center justify-center text-xl font-bold mx-auto mb-4">
                        2</div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Pemodelan Topik</h3>
                    <p class="text-sm text-gray-500">Data diproses menggunakan BERTopic untuk mengidentifikasi
                        topik-topik penelitian.</p>
                </div>
                {{-- Step 3 --}}
                <div class="text-center">
                    <div
                        class="w-14 h-14 bg-unsoed-blue-600 text-white rounded-full flex items-center justify-center text-xl font-bold mx-auto mb-4">
                        3</div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Visualisasi & Analisis</h3>
                    <p class="text-sm text-gray-500">Hasil analisis divisualisasikan untuk memahami tren dan evolusi
                        topik riset.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="bg-unsoed-blue-800 text-white py-8 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="flex items-center space-x-3 mb-4 md:mb-0">
                    <div class="w-8 h-8 bg-unsoed-gold-400 rounded-lg flex items-center justify-center">
                        <x-app.icon variant="o" name="chart-bar-square" class="w-4 h-4 text-unsoed-blue-800" />
                    </div>
                    <span class="text-sm font-semibold">TopicModeling</span>
                </div>
                <p class="text-sm text-unsoed-blue-300">
                    &copy; {{ date('Y') }} Muhamad Galih — S1 Teknik Informatika, Universitas Jenderal Soedirman
                </p>
            </div>
        </div>
    </footer>
</body>

</html>
