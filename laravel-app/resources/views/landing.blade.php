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
                        <svg class="w-5 h-5 text-unsoed-blue-800" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <span class="text-lg font-bold">TopicModeling</span>
                </div>
                <div class="flex items-center space-x-4">
                    @auth
                        <a href="{{ route('jurusan.dashboard') }}"
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
    <section
        class="bg-gradient-to-br from-unsoed-blue-700 via-unsoed-blue-600 to-unsoed-blue-800 text-white py-20 lg:py-28">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <div
                        class="inline-flex items-center bg-unsoed-gold-400/20 text-unsoed-gold-300 text-sm font-medium px-4 py-1.5 rounded-full mb-6">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
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
                            <a href="{{ route('jurusan.dashboard') }}"
                                class="bg-unsoed-gold-400 hover:bg-unsoed-gold-300 text-unsoed-blue-800 font-bold px-8 py-3 rounded-xl text-sm transition inline-flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                Buka Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                                class="bg-unsoed-gold-400 hover:bg-unsoed-gold-300 text-unsoed-blue-800 font-bold px-8 py-3 rounded-xl text-sm transition inline-flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                </svg>
                                Masuk ke Sistem
                            </a>
                        @endauth
                    </div>
                </div>
                <div class="hidden lg:block">
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 border border-white/20">
                        {{-- Illustration: Abstract chart --}}
                        <div class="space-y-4">
                            <div class="flex items-end space-x-3 h-40">
                                <div class="flex-1 bg-unsoed-gold-400/30 rounded-t-lg" style="height: 40%"></div>
                                <div class="flex-1 bg-unsoed-gold-400/50 rounded-t-lg" style="height: 55%"></div>
                                <div class="flex-1 bg-unsoed-gold-400/60 rounded-t-lg" style="height: 70%"></div>
                                <div class="flex-1 bg-unsoed-gold-400/80 rounded-t-lg" style="height: 85%"></div>
                                <div class="flex-1 bg-unsoed-gold-400 rounded-t-lg" style="height: 100%"></div>
                                <div class="flex-1 bg-unsoed-gold-400/70 rounded-t-lg" style="height: 75%"></div>
                                <div class="flex-1 bg-unsoed-gold-400/50 rounded-t-lg" style="height: 60%"></div>
                            </div>
                            <div class="text-center text-sm text-white/60">Visualisasi Tren Topik Riset</div>
                        </div>
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
                        <svg class="w-6 h-6 text-unsoed-blue-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Web Scraping</h3>
                    <p class="text-sm text-gray-500">
                        Mengambil data skripsi secara otomatis dari Repository UNSOED dengan cepat dan efisien.
                    </p>
                </div>

                {{-- Feature 2: Topic Modeling --}}
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                    <div class="w-12 h-12 bg-unsoed-gold-100 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-unsoed-gold-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
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
                        <svg class="w-6 h-6 text-unsoed-blue-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
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
                        <svg class="w-4 h-4 text-unsoed-blue-800" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
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
