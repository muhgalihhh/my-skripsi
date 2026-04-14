<div>
    @section('page-title', 'Dashboard')

    <div class="space-y-6">
        {{-- Page Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Dashboard Jurusan</h1>
                <p class="mt-1 text-sm text-gray-500">Ringkasan data skripsi dan aktivitas scraping</p>
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
                        <x-app.icon variant="o" name="book-open" class="w-6 h-6 text-unsoed-blue-600" />
                    </div>
                </div>
                <div class="mt-3 flex items-center text-xs text-gray-400">
                    <x-app.icon name="building-library" class="w-4 h-4 mr-1" />
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
                        <x-app.icon variant="o" name="calendar-days" class="w-6 h-6 text-cyan-600" />
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
                        <x-app.icon variant="o" name="check-circle" class="w-6 h-6 text-green-600" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Action + Distribution --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Aksi Cepat Scraping --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-unsoed-blue-100 rounded-lg p-2 mr-3">
                        <x-app.icon variant="o" name="cloud-arrow-down" class="w-5 h-5 text-unsoed-blue-600" />
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">Scraping Cepat</h2>
                </div>
                <p class="text-sm text-gray-500 mb-4">
                    Jalankan scraping data skripsi dari Repository UNSOED secara manual.
                </p>
                <a href="{{ route('jurusan.scraping.index') }}"
                    class="inline-flex items-center w-full justify-center py-2.5 px-4 bg-unsoed-blue-600 hover:bg-unsoed-blue-700 text-white font-medium rounded-lg text-sm transition">
                    <x-app.icon name="cloud-arrow-down" class="w-4 h-4 mr-2" />
                    Buka Halaman Pengambilan Data
                </a>
            </div>

            {{-- Distribusi per Tahun --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-unsoed-gold-100 rounded-lg p-2 mr-3">
                        <x-app.icon name="chart-pie" class="w-5 h-5 text-unsoed-gold-600" />
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
                        <x-app.icon name="cloud-arrow-down" class="w-12 h-12 mb-3" />
                        <p class="text-sm">Belum ada data. Lakukan scraping terlebih dahulu.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Riwayat Log Scraping --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <div class="bg-amber-100 rounded-lg p-2 mr-3">
                        <x-app.icon variant="o" name="cloud-arrow-down" class="w-5 h-5 text-amber-600" />
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">Riwayat Scraping Terbaru</h2>
                </div>
                <a href="{{ route('jurusan.scraping.index') }}"
                    class="text-sm text-unsoed-blue-600 hover:text-unsoed-blue-800 font-medium flex items-center">
                    Lihat Semua
                    <x-app.icon name="chevron-right" class="w-4 h-4 ml-1" />
                </a>
            </div>

            @if ($recentScrapingLogs->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-3 font-medium text-gray-500">Tanggal</th>
                                <th class="text-left py-3 px-3 font-medium text-gray-500">Pemicu</th>
                                <th class="text-left py-3 px-3 font-medium text-gray-500">Status</th>
                                <th class="text-right py-3 px-3 font-medium text-gray-500">Baru</th>
                                <th class="text-right py-3 px-3 font-medium text-gray-500">Duplikat</th>
                                <th class="text-left py-3 px-3 font-medium text-gray-500">Pengguna</th>
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
                                            {{ $log->trigger_type === 'manual' ? 'Manual' : ($log->trigger_type === 'scheduled' ? 'Terjadwal' : ucfirst($log->trigger_type)) }}
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
                                                <x-app.icon name="arrow-path" class="animate-spin -ml-0.5 mr-1 h-3 w-3" />
                                            @endif
                                            {{ $log->status === 'completed'
                                                ? 'Selesai'
                                                : ($log->status === 'failed'
                                                    ? 'Gagal'
                                                    : ($log->status === 'running'
                                                        ? 'Berjalan'
                                                        : ($log->status === 'pending' ? 'Menunggu' : ucfirst($log->status)))) }}
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
                    <x-app.icon name="cloud-arrow-down" class="w-12 h-12 mb-3" />
                    <p class="text-sm">Belum ada riwayat scraping.</p>
                </div>
            @endif
        </div>
    </div>
</div>
