<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Terjadi Kesalahan') - {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="min-h-screen bg-gray-50">
    <main class="min-h-screen flex items-center justify-center px-4 py-12 sm:px-6">
        <div class="w-full max-w-2xl">
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-5 sm:px-8 sm:py-6 bg-unsoed-blue-800">
                    <div class="flex items-center gap-4">
                        <div
                            class="w-10 h-10 rounded-xl bg-unsoed-gold-400 flex items-center justify-center flex-shrink-0">
                            <x-app.icon variant="o" name="shield-exclamation" class="w-5 h-5 text-unsoed-blue-900" />
                        </div>
                        <div>
                            <p class="text-unsoed-gold-400 text-sm font-medium">{{ config('app.name') }}</p>
                            <h1 class="text-white text-xl sm:text-2xl font-bold leading-tight">@yield('headline')</h1>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-6 sm:px-8 sm:py-8">
                    <div class="flex flex-col sm:flex-row sm:items-start gap-4 sm:gap-6">
                        <div
                            class="text-5xl sm:text-6xl font-extrabold text-unsoed-blue-800 leading-none tracking-tight">
                            @yield('code')
                        </div>
                        <div class="flex-1">
                            <p class="text-gray-700 text-base sm:text-lg">@yield('message')</p>
                            <div class="mt-3 sm:mt-4 text-sm text-gray-500">
                                @yield('detail')
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 sm:mt-7 flex flex-col sm:flex-row sm:items-center gap-3">
                        <x-ui.button href="{{ url('/') }}" class="!py-2.5">
                            Kembali ke Beranda
                        </x-ui.button>
                        <x-ui.button variant="secondary" href="{{ url()->previous() }}" class="!py-2.5">
                            Kembali
                        </x-ui.button>
                        <div class="sm:ml-auto text-xs text-gray-400 sm:text-right">
                            Waktu: {{ now()->format('d M Y H:i') }}
                        </div>
                    </div>

                    @hasSection('extra')
                        <div class="mt-6">
                            @yield('extra')
                        </div>
                    @endif
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-gray-400">
                Jika masalah berlanjut, hubungi admin.
            </p>
        </div>
    </main>
</body>

</html>
