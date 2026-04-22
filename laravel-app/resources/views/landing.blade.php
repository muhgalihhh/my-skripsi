<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Informatika Universitas Jenderal Soedirman</title>
    <link rel="icon" type="image/png" href="{{ asset('logo-unsoed.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800|dm-serif-display:400" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <style>
        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .font-display {
            font-family: 'DM Serif Display', serif;
        }

        .nav-link {
            position: relative;
            transition: color 0.2s ease;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -0.35rem;
            width: 100%;
            height: 2px;
            background: #ffd700;
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.25s ease;
        }

        .nav-link:hover::after {
            transform: scaleX(1);
        }

        .hero-bg {
            height: 100%;
            width: 100%;
            background-image: linear-gradient(105deg, rgba(0, 33, 71, 0.9) 12%, rgba(0, 47, 94, 0.84) 54%, rgba(0, 61, 122, 0.65) 100%), url('{{ asset('hero image.jpeg') }}');
            background-size: cover;
            background-position: center;
            animation: hero-pan 18s ease-in-out infinite alternate;
        }

        .hero-orb {
            animation: float-orb 9s ease-in-out infinite;
        }

        .glass-panel {
            border: 1px solid rgba(255, 255, 255, 0.28);
            background: linear-gradient(150deg, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0.06));
            backdrop-filter: blur(9px);
            box-shadow: 0 24px 50px rgba(0, 23, 48, 0.3);
        }

        .feature-card {
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 18px 32px rgba(0, 33, 71, 0.12);
        }

        .reveal-scroll {
            opacity: 0;
            transform: translateY(22px) scale(0.985);
            transition: opacity 0.72s cubic-bezier(0.2, 0.8, 0.2, 1), transform 0.72s cubic-bezier(0.2, 0.8, 0.2, 1);
            will-change: opacity, transform;
        }

        .reveal-scroll.in-view {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .feature-stagger-item {
            transition-delay: var(--feature-stagger-delay, 0ms);
        }

        .delay-scroll-100 {
            transition-delay: 0.08s;
        }

        .delay-scroll-200 {
            transition-delay: 0.16s;
        }

        .delay-scroll-300 {
            transition-delay: 0.24s;
        }

        .delay-scroll-400 {
            transition-delay: 0.32s;
        }

        .reveal {
            opacity: 0;
            animation: reveal-up 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        .delay-100 {
            animation-delay: 0.1s;
        }

        .delay-200 {
            animation-delay: 0.2s;
        }

        .delay-300 {
            animation-delay: 0.3s;
        }

        .delay-400 {
            animation-delay: 0.4s;
        }

        .delay-500 {
            animation-delay: 0.5s;
        }

        @keyframes reveal-up {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float-orb {
            0% {
                transform: translateY(0) scale(1);
            }

            50% {
                transform: translateY(-16px) scale(1.04);
            }

            100% {
                transform: translateY(0) scale(1);
            }
        }

        @keyframes hero-pan {
            from {
                transform: scale(1.04) translateY(0);
            }

            to {
                transform: scale(1.1) translateY(-1.5%);
            }
        }

        @media (max-width: 768px) {
            .reveal-scroll {
                transform: translateY(14px) scale(0.992);
                transition-duration: 0.55s;
            }

            .feature-card:hover {
                transform: translateY(-3px);
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .hero-bg,
            .hero-orb,
            .reveal,
            .reveal-scroll {
                animation: none !important;
                transition: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
        }
    </style>
</head>

<body class="min-h-screen bg-unsoed-blue-50/30 text-gray-900 flex flex-col">
    {{-- Navbar --}}
    <nav class="sticky top-0 z-40 border-b border-unsoed-blue-100 bg-white/95 text-unsoed-blue-800 backdrop-blur">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('logo-unsoed.png') }}" alt="Logo Universitas Jenderal Soedirman"
                        class="h-10 w-10 rounded-full bg-white p-1 ring-2 ring-unsoed-gold-300/80 object-contain">
                    <div class="leading-tight">
                        <span class="block text-sm font-extrabold tracking-tight">Informatika</span>
                        <span class="block text-xs text-unsoed-blue-600 font-medium">Universitas Jenderal Soedirman</span>
                    </div>
                </div>

                <div class="hidden items-center gap-6 text-sm font-semibold md:flex">
                    <a href="#fitur" class="nav-link">Fitur Utama</a>
                    <a href="#alur" class="nav-link">Alur Analisis</a>
                    <a href="#about" class="nav-link">Tentang Sistem</a>
                </div>

                <div class="flex items-center space-x-3">
                    @auth
                        @php
                            $dashboardRoute = auth()->user()->isMahasiswa()
                                ? route('mahasiswa.dashboard')
                                : route('jurusan.dashboard');
                        @endphp
                        <a href="{{ $dashboardRoute }}"
                            class="bg-unsoed-gold-400 hover:bg-unsoed-gold-300 text-unsoed-blue-900 font-semibold px-4 py-2 rounded-lg text-sm transition">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                            class="bg-unsoed-gold-400 hover:bg-unsoed-gold-300 text-unsoed-blue-900 font-semibold px-4 py-2 rounded-lg text-sm transition">
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
            <div class="hero-bg"></div>
            <div class="hero-orb absolute -left-20 top-10 h-64 w-64 rounded-full bg-unsoed-gold-300/25 blur-3xl"></div>
            <div class="hero-orb absolute -right-16 bottom-0 h-72 w-72 rounded-full bg-unsoed-blue-200/20 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,215,0,0.2),transparent_45%)]"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:items-center">
                <div class="max-w-2xl">
                    <div
                        class="reveal delay-100 inline-flex items-center rounded-full border border-unsoed-gold-300/45 bg-unsoed-gold-400/20 px-4 py-1.5 text-sm font-medium text-unsoed-gold-200 mb-6">
                        <x-app.icon name="academic-cap" class="w-4 h-4 mr-2" />
                        Analisis Evolusi Topik Riset
                    </div>
                    <h1 class="reveal delay-200 font-display text-4xl lg:text-6xl leading-tight mb-6">
                        Skripsi Informatika<br>
                        <span class="text-unsoed-gold-400">Universitas Jenderal Soedirman</span>
                    </h1>
                    <p class="reveal delay-300 text-lg text-unsoed-blue-100/95 mb-8 max-w-xl">
                        Sistem pemodelan topik untuk memetakan tren penelitian. Membantu mahasiswa mencari referensi dan mendukung perumusan kebijakan akademik.
                    </p>
                    <div class="reveal delay-400 flex flex-col sm:flex-row gap-4">
                        @auth
                            @php
                                $dashboardRoute = auth()->user()->isMahasiswa()
                                    ? route('mahasiswa.dashboard')
                                    : route('jurusan.dashboard');
                            @endphp
                            <a href="{{ $dashboardRoute }}"
                                class="bg-unsoed-gold-400 hover:bg-unsoed-gold-300 text-unsoed-blue-900 font-bold px-8 py-3 rounded-xl text-sm transition inline-flex items-center justify-center">
                                <x-app.icon name="home" class="w-5 h-5 mr-2" />
                                Buka Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                                class="bg-unsoed-gold-400 hover:bg-unsoed-gold-300 text-unsoed-blue-900 font-bold px-8 py-3 rounded-xl text-sm transition inline-flex items-center justify-center">
                                <x-app.icon name="arrow-right-on-rectangle" class="w-5 h-5 mr-2" />
                                Masuk ke Sistem
                            </a>
                        @endauth

                        <a href="#about"
                            class="inline-flex items-center justify-center rounded-xl border border-white/35 px-8 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                            Tentang Sistem
                        </a>
                    </div>
                </div>

                <div class="reveal delay-500">
                    <div class="glass-panel rounded-2xl p-6 sm:p-7">
                        <p class="text-sm uppercase tracking-widest text-unsoed-blue-100/85">Fitur Utama</p>
                        <div class="mt-5 space-y-4">
                            <div class="rounded-xl bg-white/12 p-4">
                                <div class="flex items-start gap-3">
                                    <x-app.icon variant="o" name="cloud-arrow-down" class="h-6 w-6 text-unsoed-gold-300" />
                                    <div>
                                        <h3 class="text-base font-semibold">Pengumpulan Data</h3>
                                        <p class="mt-1 text-sm text-unsoed-blue-100/90">Menyatukan data abstrak skripsi menjadi satu dataset yang siap dianalisis.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-xl bg-white/12 p-4">
                                <div class="flex items-start gap-3">
                                    <x-app.icon variant="o" name="light-bulb" class="h-6 w-6 text-unsoed-gold-300" />
                                    <div>
                                        <h3 class="text-base font-semibold">Pemetaan Topik</h3>
                                        <p class="mt-1 text-sm text-unsoed-blue-100/90">Memvisualisasikan tren topik penelitian yang paling banyak diminati dari tahun ke tahun.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-xl bg-white/12 p-4">
                                <div class="flex items-start gap-3">
                                    <x-app.icon variant="o" name="book-open" class="h-6 w-6 text-unsoed-gold-300" />
                                    <div>
                                        <h3 class="text-base font-semibold">Rekomendasi Referensi</h3>
                                        <p class="mt-1 text-sm text-unsoed-blue-100/90">Membantu mahasiswa menemukan referensi skripsi terdahulu yang mirip dengan rencana penelitian mereka.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Features Section --}}
    <section id="fitur" class="py-16 lg:py-24 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="reveal-scroll text-center mb-12">
                <h2 class="font-display text-3xl text-unsoed-blue-800 mb-3">Fitur Utama Sistem</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Sistem ini dirancang untuk membantu analisis tren penelitian dan pencarian referensi skripsi di Informatika UNSOED.</p>
            </div>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                <div class="feature-card feature-stagger-item reveal-scroll rounded-2xl border border-gray-200 bg-white p-6">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-unsoed-blue-100">
                        <x-app.icon variant="o" name="cloud-arrow-down" class="w-6 h-6 text-unsoed-blue-600" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold text-gray-900">Pengumpulan Data</h3>
                    <p class="text-sm text-gray-600">
                        Mengumpulkan data abstrak skripsi secara otomatis dari repositori kampus.
                    </p>
                </div>

                <div class="feature-card feature-stagger-item reveal-scroll rounded-2xl border border-gray-200 bg-white p-6">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-unsoed-gold-100">
                        <x-app.icon name="light-bulb" class="w-6 h-6 text-unsoed-gold-600" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold text-gray-900">Pemodelan Topik</h3>
                    <p class="text-sm text-gray-600">
                        Mengelompokkan data skripsi ke dalam berbagai topik penelitian secara otomatis.
                    </p>
                </div>

                <div class="feature-card feature-stagger-item reveal-scroll rounded-2xl border border-gray-200 bg-white p-6">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-unsoed-blue-100">
                        <x-app.icon variant="o" name="chart-bar-square" class="w-6 h-6 text-unsoed-blue-600" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold text-gray-900">Visualisasi Tren</h3>
                    <p class="text-sm text-gray-600">
                        Menyajikan grafik interaktif untuk melihat perkembangan tren topik penelitian dari waktu ke waktu.
                    </p>
                </div>

                <div class="feature-card feature-stagger-item reveal-scroll rounded-2xl border border-gray-200 bg-white p-6">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-unsoed-gold-100">
                        <x-app.icon variant="o" name="book-open" class="w-6 h-6 text-unsoed-gold-600" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold text-gray-900">Rekomendasi Skripsi</h3>
                    <p class="text-sm text-gray-600">
                        Membantu pencarian referensi skripsi yang paling relevan dengan ide penelitian mahasiswa.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- How it Works Section --}}
    <section id="alur" class="py-16 lg:py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="reveal-scroll text-center mb-12">
                <h2 class="font-display text-3xl text-unsoed-blue-800 mb-3">Cara Kerja Sistem</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Proses dari pengumpulan data mentah hingga menjadi informasi tren penelitian.</p>
            </div>
            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                <div class="reveal-scroll delay-scroll-100 text-center">
                    <div
                        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-unsoed-blue-600 text-xl font-bold text-white">
                        1</div>
                    <h3 class="mb-2 text-lg font-semibold text-gray-900">Prapemrosesan Data</h3>
                    <p class="text-sm text-gray-600">Teks abstrak dibersihkan dari kata-kata yang tidak relevan agar siap dianalisis.</p>
                </div>

                <div class="reveal-scroll delay-scroll-200 text-center">
                    <div
                        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-unsoed-gold-400 text-xl font-bold text-unsoed-blue-800">
                        2</div>
                    <h3 class="mb-2 text-lg font-semibold text-gray-900">Pemodelan Topik</h3>
                    <p class="text-sm text-gray-600">Sistem menganalisis teks untuk menemukan pola topik tersembunyi dari abstrak skripsi.</p>
                </div>

                <div class="reveal-scroll delay-scroll-300 text-center">
                    <div
                        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-unsoed-blue-600 text-xl font-bold text-white">
                        3</div>
                    <h3 class="mb-2 text-lg font-semibold text-gray-900">Analisis Tren Topik</h3>
                    <p class="text-sm text-gray-600">Melihat grafik tren topik penelitian dari tahun ke tahun untuk mendukung pengambilan kebijakan akademik.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- About Website Section --}}
    <section id="about" class="bg-unsoed-blue-800 py-16 text-white lg:py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:items-center">
                <div class="reveal-scroll delay-scroll-100">
                    <p class="text-sm uppercase tracking-widest text-unsoed-gold-300">Tentang Sistem</p>
                    <h2 class="font-display mt-3 text-3xl leading-tight text-white lg:text-5xl">Sistem Analisis Topik</h2>
                    <p class="mt-5 max-w-xl text-unsoed-blue-100">
                        Sistem ini dikembangkan untuk memetakan tren penelitian di Program Studi Informatika Universitas Jenderal Soedirman. Kami mengumpulkan data skripsi terdahulu untuk dimodelkan menjadi informasi tren topik.
                    </p>
                    <p class="mt-4 max-w-xl text-unsoed-blue-100">
                        Tujuannya adalah membantu mahasiswa mencari referensi skripsi dan membantu program studi dalam mengembangkan kurikulum.
                    </p>
                </div>

                <div class="reveal-scroll delay-scroll-200 space-y-4">
                    <div class="rounded-2xl border border-unsoed-blue-600 bg-unsoed-blue-700/70 p-5 reveal-scroll delay-scroll-100">
                        <h3 class="text-lg font-semibold text-unsoed-gold-300">Bagi Mahasiswa</h3>
                        <p class="mt-2 text-sm text-unsoed-blue-100">Membantu mahasiswa menemukan referensi skripsi yang relevan untuk penyusunan proposal tugas akhir.</p>
                    </div>
                    <div class="rounded-2xl border border-unsoed-blue-600 bg-unsoed-blue-700/70 p-5 reveal-scroll delay-scroll-200">
                        <h3 class="text-lg font-semibold text-unsoed-gold-300">Bagi Program Studi</h3>
                        <p class="mt-2 text-sm text-unsoed-blue-100">Menyajikan grafik tren topik skripsi sebagai bahan pertimbangan untuk evaluasi kurikulum.</p>
                    </div>
                    <div class="rounded-2xl border border-unsoed-blue-600 bg-unsoed-blue-700/70 p-5 reveal-scroll delay-scroll-300">
                        <h3 class="text-lg font-semibold text-unsoed-gold-300">Fleksibilitas Sistem</h3>
                        <p class="mt-2 text-sm text-unsoed-blue-100">Sistem ini dapat diperbarui dengan data skripsi terbaru kapan saja.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="bg-unsoed-blue-800 text-white py-8 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <img src="{{ asset('logo-unsoed.png') }}" alt="Logo Universitas Jenderal Soedirman"
                        class="h-9 w-9 rounded-full bg-white p-1 object-contain">
                    <span class="text-sm font-semibold">Informatika Universitas Jenderal Soedirman</span>
                </div>

                <div class="flex items-center gap-5 text-sm text-unsoed-blue-200">
                    <a href="#fitur" class="hover:text-unsoed-gold-300 transition">Fitur Utama</a>
                    <a href="#alur" class="hover:text-unsoed-gold-300 transition">Alur Analisis</a>
                    <a href="#about" class="hover:text-unsoed-gold-300 transition">Tentang Sistem</a>
                </div>

                <p class="text-sm text-unsoed-blue-300 text-center md:text-right">
                    &copy; {{ date('Y') }} Sistem Analisis Evolusi Topik Riset Skripsi Informatika
                </p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const animatedElements = document.querySelectorAll('.reveal-scroll');
            const staggerCards = document.querySelectorAll('.feature-stagger-item');

            staggerCards.forEach((card, index) => {
                card.style.setProperty('--feature-stagger-delay', `${index * 110}ms`);
            });

            if (!animatedElements.length) {
                return;
            }

            if (!('IntersectionObserver' in window)) {
                animatedElements.forEach((element) => element.classList.add('in-view'));
                return;
            }

            const observer = new IntersectionObserver((entries, instance) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    entry.target.classList.add('in-view');
                    instance.unobserve(entry.target);
                });
            }, {
                root: null,
                rootMargin: '0px 0px -12% 0px',
                threshold: 0.12,
            });

            animatedElements.forEach((element) => observer.observe(element));
        });
    </script>
</body>

</html>
