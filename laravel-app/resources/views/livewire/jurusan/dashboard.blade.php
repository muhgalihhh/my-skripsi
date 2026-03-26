<div>
    @section('page-title', 'Dashboard')

    <div class="space-y-6">
        {{-- Page Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Dashboard Jurusan</h1>
                <p class="mt-1 text-sm text-gray-500">Overview data skripsi dan aktivitas scraping</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-400">Terakhir diperbarui</p>
                <p class="text-sm font-medium text-gray-600">{{ now()->format('d M Y, H:i') }}</p>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Total Skripsi --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
                x-data="{ count: 0, target: {{ $totalSkripsi }} }" x-init="let step = Math.ceil(target / 40);
                let interval = setInterval(() => {
                    count = Math.min(count + step, target);
                    if (count >= target) clearInterval(interval);
                }, 30);">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Skripsi</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1" x-text="count.toLocaleString('id-ID')">
                            {{ number_format($totalSkripsi) }}</p>
                    </div>
                    <div class="bg-unsoed-blue-100 rounded-xl p-3">
                        <svg class="w-6 h-6 text-unsoed-blue-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3 flex items-center text-xs text-gray-400">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                    Data dari Repository UNSOED
                </div>
            </div>

            {{-- Rentang Tahun --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Rentang Tahun</p>
                        @if ($skripsiPerYear->isNotEmpty())
                            <p class="text-3xl font-bold text-gray-900 mt-1">
                                {{ $skripsiPerYear->min('year') }} - {{ $skripsiPerYear->max('year') }}
                            </p>
                        @else
                            <p class="text-3xl font-bold text-gray-400 mt-1">-</p>
                        @endif
                    </div>
                    <div class="bg-cyan-100 rounded-xl p-3">
                        <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3 text-xs text-gray-400">
                    {{ $skripsiPerYear->count() }} tahun data tersedia
                </div>
            </div>

            {{-- Status Scraping Terakhir --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Scraping Terakhir</p>
                        @if ($latestScraping)
                            <p
                                class="text-lg font-bold mt-1
                                {{ $latestScraping->status === 'completed' ? 'text-green-600' : ($latestScraping->status === 'failed' ? 'text-red-600' : 'text-yellow-600') }}">
                                {{ ucfirst($latestScraping->status) }}
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ $latestScraping->completed_at?->diffForHumans() ?? $latestScraping->created_at->diffForHumans() }}
                            </p>
                        @else
                            <p class="text-lg font-bold text-gray-400 mt-1">Belum pernah</p>
                        @endif
                    </div>
                    <div class="bg-green-100 rounded-xl p-3">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Action + Distribution --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Quick Scraping --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-unsoed-blue-100 rounded-lg p-2 mr-3">
                        <svg class="w-5 h-5 text-unsoed-blue-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">Quick Scraping</h2>
                </div>
                <p class="text-sm text-gray-500 mb-4">
                    Jalankan scraping data skripsi dari Repository UNSOED secara manual.
                </p>
                <a href="{{ route('jurusan.scraping.index') }}"
                    class="inline-flex items-center w-full justify-center py-2.5 px-4 bg-unsoed-blue-600 hover:bg-unsoed-blue-700 text-white font-medium rounded-lg text-sm transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Buka Halaman Scraping
                </a>
            </div>

            {{-- Distribution per Year --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-unsoed-gold-100 rounded-lg p-2 mr-3">
                        <svg class="w-5 h-5 text-unsoed-gold-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">Distribusi per Tahun</h2>
                </div>
                @if ($skripsiPerYear->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($skripsiPerYear as $yearData)
                            <div class="flex items-center" x-data="{ width: 0 }" x-init="setTimeout(() => width = {{ $totalSkripsi > 0 ? round(($yearData->total / $totalSkripsi) * 100) : 0 }}, 200)">
                                <span class="text-sm font-medium text-gray-600 w-12">{{ $yearData->year }}</span>
                                <div class="flex-1 mx-3">
                                    <div class="bg-gray-100 rounded-full h-6 overflow-hidden">
                                        <div class="bg-gradient-to-r from-unsoed-blue-500 to-unsoed-blue-600 h-6 rounded-full transition-all duration-1000 ease-out flex items-center justify-end pr-2"
                                            :style="'width: ' + Math.max(width, 5) + '%'">
                                            <span class="text-xs text-white font-medium"
                                                x-show="width > 15">{{ $yearData->total }}</span>
                                        </div>
                                    </div>
                                </div>
                                <span class="text-sm font-bold text-gray-700 w-10 text-right"
                                    x-show="width <= 15">{{ $yearData->total }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-8 text-gray-400">
                        <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <p class="text-sm">Belum ada data. Lakukan scraping terlebih dahulu.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent Scraping Logs --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <div class="bg-amber-100 rounded-lg p-2 mr-3">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">Riwayat Scraping Terbaru</h2>
                </div>
                <a href="{{ route('jurusan.scraping.index') }}"
                    class="text-sm text-unsoed-blue-600 hover:text-unsoed-blue-800 font-medium flex items-center">
                    Lihat Semua
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            @if ($recentScrapingLogs->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-3 font-medium text-gray-500">Tanggal</th>
                                <th class="text-left py-3 px-3 font-medium text-gray-500">Trigger</th>
                                <th class="text-left py-3 px-3 font-medium text-gray-500">Status</th>
                                <th class="text-right py-3 px-3 font-medium text-gray-500">Baru</th>
                                <th class="text-right py-3 px-3 font-medium text-gray-500">Duplikat</th>
                                <th class="text-left py-3 px-3 font-medium text-gray-500">User</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentScrapingLogs as $log)
                                <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                    <td class="py-3 px-3 text-gray-600">
                                        {{ $log->created_at->format('d M Y H:i') }}
                                    </td>
                                    <td class="py-3 px-3">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $log->trigger_type === 'manual' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                            {{ ucfirst($log->trigger_type) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $log->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                            {{ $log->status === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                                            {{ $log->status === 'running' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                            {{ $log->status === 'pending' ? 'bg-gray-100 text-gray-700' : '' }}">
                                            @if ($log->status === 'running')
                                                <svg class="animate-spin -ml-0.5 mr-1 h-3 w-3"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                            @endif
                                            {{ ucfirst($log->status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right font-medium text-green-600">+{{ $log->new_added }}
                                    </td>
                                    <td class="py-3 px-3 text-right text-gray-400">{{ $log->duplicates_skipped }}</td>
                                    <td class="py-3 px-3 text-gray-600">{{ $log->user->name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-8 text-gray-400">
                    <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <p class="text-sm">Belum ada riwayat scraping.</p>
                </div>
            @endif
        </div>
    </div>
</div>
