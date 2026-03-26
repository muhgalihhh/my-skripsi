@extends('layouts.jurusan')

@section('title', 'Dashboard')

@section('content')
    <div class="space-y-6">
        {{-- Page Header --}}
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Dashboard Jurusan</h1>
            <p class="mt-1 text-sm text-gray-500">
                Overview data skripsi dan aktivitas scraping
            </p>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Total Skripsi --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Skripsi</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($totalSkripsi) }}</p>
                    </div>
                    <div class="bg-indigo-100 rounded-lg p-3">
                        <span class="text-2xl">📚</span>
                    </div>
                </div>
            </div>

            {{-- Rentang Tahun --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
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
                    <div class="bg-cyan-100 rounded-lg p-3">
                        <span class="text-2xl">📅</span>
                    </div>
                </div>
            </div>

            {{-- Status Scraping Terakhir --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
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
                    <div class="bg-green-100 rounded-lg p-3">
                        <span class="text-2xl">🔄</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Action + Distribution --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Quick Scraping --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">⚡ Quick Scraping</h2>
                <p class="text-sm text-gray-500 mb-4">
                    Jalankan scraping data skripsi dari Repository UNSOED secara manual.
                </p>
                <form method="POST" action="{{ route('jurusan.scraping.start') }}"
                    onsubmit="return confirm('Mulai proses scraping? Proses ini mungkin membutuhkan waktu beberapa menit.')">
                    @csrf
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tahun Mulai</label>
                            <input type="number" name="start_year" value="2019" min="2000" max="2030"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tahun Akhir</label>
                            <input type="number" name="end_year" value="2026" min="2000" max="2030"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    <button type="submit"
                        class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition">
                        🚀 Mulai Scrapping
                    </button>
                </form>
            </div>

            {{-- Distribution per Year --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">📊 Distribusi per Tahun</h2>
                @if ($skripsiPerYear->isNotEmpty())
                    <div class="space-y-2">
                        @foreach ($skripsiPerYear as $yearData)
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-600 w-16">{{ $yearData->year }}</span>
                                <div class="flex-1 mx-3">
                                    <div class="bg-gray-100 rounded-full h-5 overflow-hidden">
                                        <div class="bg-indigo-500 h-5 rounded-full transition-all"
                                            style="width: {{ $totalSkripsi > 0 ? ($yearData->total / $totalSkripsi) * 100 : 0 }}%">
                                        </div>
                                    </div>
                                </div>
                                <span class="text-sm font-bold text-gray-700 w-10 text-right">{{ $yearData->total }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400">Belum ada data. Lakukan scraping terlebih dahulu.</p>
                @endif
            </div>
        </div>

        {{-- Recent Scraping Logs --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">📋 Riwayat Scraping Terbaru</h2>
                <a href="{{ route('jurusan.scraping.index') }}"
                    class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    Lihat Semua →
                </a>
            </div>

            @if ($recentScrapingLogs->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-2 font-medium text-gray-500">Tanggal</th>
                                <th class="text-left py-3 px-2 font-medium text-gray-500">Trigger</th>
                                <th class="text-left py-3 px-2 font-medium text-gray-500">Status</th>
                                <th class="text-right py-3 px-2 font-medium text-gray-500">Baru</th>
                                <th class="text-right py-3 px-2 font-medium text-gray-500">Duplikat</th>
                                <th class="text-left py-3 px-2 font-medium text-gray-500">User</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentScrapingLogs as $log)
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-2 text-gray-600">
                                        {{ $log->created_at->format('d M Y H:i') }}
                                    </td>
                                    <td class="py-3 px-2">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        {{ $log->trigger_type === 'manual' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                            {{ ucfirst($log->trigger_type) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-2">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        {{ $log->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $log->status === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                                        {{ $log->status === 'running' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                        {{ $log->status === 'pending' ? 'bg-gray-100 text-gray-700' : '' }}">
                                            {{ ucfirst($log->status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-2 text-right font-medium text-green-600">+{{ $log->new_added }}</td>
                                    <td class="py-3 px-2 text-right text-gray-400">{{ $log->duplicates_skipped }}</td>
                                    <td class="py-3 px-2 text-gray-600">{{ $log->user->name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-400 text-center py-8">Belum ada riwayat scraping.</p>
            @endif
        </div>
    </div>
@endsection
