@extends('layouts.jurusan')

@section('title', 'Manajemen Scraping')

@section('content')
    <div class="space-y-6">
        {{-- Page Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Manajemen Scraping</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Kelola proses scraping data skripsi dari Repository UNSOED
                </p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Total di Database</p>
                <p class="text-2xl font-bold text-indigo-600">{{ number_format($totalSkripsi) }}</p>
            </div>
        </div>

        {{-- API Status --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center space-x-3">
                @if (($apiStatus['status'] ?? '') === 'ok')
                    <span class="inline-block w-3 h-3 bg-green-500 rounded-full animate-pulse"></span>
                    <span class="text-sm text-green-700 font-medium">FastAPI Service Online</span>
                    <span class="text-xs text-gray-400">{{ $apiStatus['app_name'] ?? '' }}</span>
                @else
                    <span class="inline-block w-3 h-3 bg-red-500 rounded-full"></span>
                    <span class="text-sm text-red-700 font-medium">FastAPI Service Offline</span>
                    <span
                        class="text-xs text-gray-400">{{ $apiStatus['message'] ?? 'Pastikan service berjalan di port 8000' }}</span>
                @endif
            </div>
        </div>

        {{-- Scraping Form --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">🚀 Jalankan Scraping Manual</h2>

            <form method="POST" action="{{ route('jurusan.scraping.start') }}"
                onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').innerText='⏳ Memproses...'; return confirm('Mulai proses scraping? Ini membutuhkan waktu beberapa menit.')">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Mulai</label>
                        <input type="number" name="start_year" value="2019" min="2000" max="2030"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Akhir</label>
                        <input type="number" name="end_year" value="2026" min="2000" max="2030"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                            class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition"
                            {{ ($apiStatus['status'] ?? '') !== 'ok' ? 'disabled' : '' }}>
                            🚀 Mulai Scrapping
                        </button>
                    </div>
                </div>

                @if (($apiStatus['status'] ?? '') !== 'ok')
                    <p class="text-xs text-red-500">
                        ⚠️ FastAPI service tidak aktif. Nyalakan terlebih dahulu dengan: <code
                            class="bg-gray-100 px-1 py-0.5 rounded">uvicorn app.main:app --reload</code>
                    </p>
                @endif
            </form>
        </div>

        {{-- Scraping History --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">📋 Riwayat Scraping</h2>

            @if ($logs->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-2 font-medium text-gray-500">#</th>
                                <th class="text-left py-3 px-2 font-medium text-gray-500">Tanggal</th>
                                <th class="text-left py-3 px-2 font-medium text-gray-500">Trigger</th>
                                <th class="text-left py-3 px-2 font-medium text-gray-500">Status</th>
                                <th class="text-right py-3 px-2 font-medium text-gray-500">Total</th>
                                <th class="text-right py-3 px-2 font-medium text-gray-500">Baru</th>
                                <th class="text-right py-3 px-2 font-medium text-gray-500">Duplikat</th>
                                <th class="text-left py-3 px-2 font-medium text-gray-500">User</th>
                                <th class="text-left py-3 px-2 font-medium text-gray-500">Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-2 text-gray-400">{{ $log->id }}</td>
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
                                    <td class="py-3 px-2 text-right font-medium text-gray-700">{{ $log->total_scraped }}
                                    </td>
                                    <td class="py-3 px-2 text-right font-medium text-green-600">+{{ $log->new_added }}</td>
                                    <td class="py-3 px-2 text-right text-gray-400">{{ $log->duplicates_skipped }}</td>
                                    <td class="py-3 px-2 text-gray-600">{{ $log->user->name ?? '-' }}</td>
                                    <td class="py-3 px-2 text-xs text-red-500 max-w-xs truncate">
                                        {{ $log->error_message ?? '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $logs->links() }}
                </div>
            @else
                <p class="text-sm text-gray-400 text-center py-8">Belum ada riwayat scraping.</p>
            @endif
        </div>
    </div>
@endsection
