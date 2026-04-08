<div>
    @section('page-title', 'Manajemen Scraping')

    {{-- Livewire polling: poll every 3 seconds while scraping is active --}}
    @if ($isProcessing && $activeJobId)
        <div wire:poll.3s="pollJobProgress">
        @else
            <div>
    @endif

    <div class="space-y-6">
        {{-- ── Page Header ──────────────────────────────────── --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Manajemen Scraping</h1>
                <p class="mt-1 text-sm text-gray-500">Kelola proses scraping data skripsi dari Repository UNSOED</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-400">Total di Database</p>
                <p class="text-2xl font-bold text-unsoed-blue-600">{{ number_format($totalSkripsi) }}</p>
            </div>
        </div>

        {{-- ── API Status Card ──────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    @if (($apiStatus['status'] ?? '') === 'ok')
                        <span class="relative flex h-3 w-3">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                        <span class="text-sm text-green-700 font-medium">FastAPI Service Online</span>
                        <span class="text-xs text-gray-400">{{ $apiStatus['app_name'] ?? '' }}
                            v{{ $apiStatus['version'] ?? '' }}</span>
                    @else
                        <span class="relative flex h-3 w-3">
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                        </span>
                        <span class="text-sm text-red-700 font-medium">FastAPI Service Offline</span>
                        <span
                            class="text-xs text-gray-400">{{ $apiStatus['message'] ?? 'Tidak dapat terhubung ke FastAPI service.' }}</span>
                    @endif
                </div>
                <button wire:click="checkApiStatus"
                    class="text-xs text-unsoed-blue-600 hover:text-unsoed-blue-800 font-medium flex items-center transition"
                    wire:loading.class="opacity-50" wire:target="checkApiStatus">
                    <svg class="w-4 h-4 mr-1" wire:loading.class="animate-spin" wire:target="checkApiStatus"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh Status
                </button>
            </div>
        </div>

        {{-- ── Active Scraping Progress Card ───────────────── --}}
        @if ($isProcessing && $activeJobId)
            <div class="bg-white rounded-xl shadow-sm border-2 border-unsoed-blue-200 p-6" wire:key="progress-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="bg-unsoed-blue-100 rounded-lg p-2 mr-3">
                            <svg class="w-5 h-5 text-unsoed-blue-600 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Scraping Sedang Berjalan</h2>
                            <p class="text-xs text-gray-400">Job ID: {{ $activeJobId }}</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button wire:click="openMonitoring"
                            class="px-3 py-1.5 bg-unsoed-blue-50 hover:bg-unsoed-blue-100 text-unsoed-blue-600 text-xs font-medium rounded-lg border border-unsoed-blue-200 transition flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Pantau Detail
                        </button>
                        <button wire:click="openCancelConfirm"
                            class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-medium rounded-lg border border-red-200 transition flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Batalkan
                        </button>
                    </div>
                </div>

                {{-- Progress Bar --}}
                <div class="mb-3">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600 font-medium">Progress</span>
                        <span class="text-unsoed-blue-600 font-bold">{{ $jobProgress }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                        <div class="h-3 rounded-full transition-all duration-500 ease-out
                            {{ $jobProgress < 100 ? 'bg-gradient-to-r from-unsoed-blue-500 to-unsoed-blue-600' : 'bg-green-500' }}"
                            style="width: {{ $jobProgress }}%">
                            @if ($jobProgress > 5 && $jobProgress < 100)
                                <div
                                    class="h-full w-full bg-gradient-to-r from-transparent via-white/20 to-transparent animate-pulse">
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Progress Details --}}
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center">
                    <div class="bg-gray-50 rounded-lg p-2">
                        <p class="text-xs text-gray-500">URL Ditemukan</p>
                        <p class="text-lg font-bold text-gray-800">{{ $jobTotalUrls }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2">
                        <p class="text-xs text-gray-500">Sudah Di-scrape</p>
                        <p class="text-lg font-bold text-unsoed-blue-600">{{ $jobScrapedCount }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2">
                        <p class="text-xs text-gray-500">Sisa</p>
                        <p class="text-lg font-bold text-gray-600">
                            {{ max(0, $jobTotalUrls - $jobScrapedCount) }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2">
                        <p class="text-xs text-gray-500">Gagal Parse</p>
                        <p class="text-lg font-bold text-red-500">{{ $jobSkippedCount }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2">
                        <p class="text-xs text-gray-500">Di-filter Tahun</p>
                        <p class="text-lg font-bold text-amber-500">{{ $jobFilteredCount }}</p>
                    </div>
                </div>

                @if ($jobStep)
                    <p class="mt-3 text-xs text-gray-500 flex items-center">
                        <svg class="w-3 h-3 mr-1 text-unsoed-blue-500 animate-pulse" fill="currentColor"
                            viewBox="0 0 8 8">
                            <circle cx="4" cy="4" r="3" />
                        </svg>
                        {{ $jobStep }}
                    </p>
                @endif
            </div>
        @endif

        {{-- ── Status Message ────────────────────────────────── --}}
        @if ($statusMessage && !($isProcessing && $activeJobId))
            <div x-data="{ show: true }" x-show="show" x-transition
                class="rounded-xl border px-4 py-3 flex items-center justify-between
                    {{ $statusType === 'success' ? 'bg-green-50 border-green-200 text-green-700' : '' }}
                    {{ $statusType === 'error' ? 'bg-red-50 border-red-200 text-red-700' : '' }}
                    {{ $statusType === 'info' ? 'bg-blue-50 border-blue-200 text-blue-700' : '' }}">
                <div class="flex items-center">
                    @if ($statusType === 'success')
                        <svg class="w-5 h-5 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                    @elseif($statusType === 'error')
                        <svg class="w-5 h-5 mr-2 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd" />
                        </svg>
                    @else
                        <svg class="w-5 h-5 mr-2 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    @endif
                    <span class="text-sm font-medium">{{ $statusMessage }}</span>
                </div>
                <button @click="show = false" class="ml-3 opacity-60 hover:opacity-100">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        @endif

        {{-- ── Scraping Form ────────────────────────────────── --}}
        <div
            class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 {{ $isProcessing ? 'opacity-60 pointer-events-none' : '' }}">
            <div class="flex items-start justify-between mb-4 gap-3">
                <div class="flex items-center">
                    <div class="bg-unsoed-blue-100 rounded-lg p-2 mr-3">
                        <svg class="w-5 h-5 text-unsoed-blue-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Jalankan Scraping Manual</h2>
                        <p class="text-xs text-gray-400">Data yang sudah ada (URL sama) akan otomatis diperbarui dengan
                            data terbaru</p>
                    </div>
                </div>
                <button type="button" wire:click="openResetSkripsiConfirm"
                    class="inline-flex items-center px-3 py-2 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    @disabled($isProcessing || $totalSkripsi === 0)>
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Reset Database Skripsi
                </button>
            </div>

            <form wire:submit="startScraping">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Mulai</label>
                        <input wire:model="startYear" type="number" min="2000" max="2030"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 transition">
                        @error('startYear')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Akhir</label>
                        <input wire:model="endYear" type="number" min="2000" max="2030"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 transition">
                        @error('endYear')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                            class="w-full py-2.5 px-4 bg-unsoed-blue-600 hover:bg-unsoed-blue-700 text-white font-medium rounded-lg text-sm transition flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                            wire:loading.attr="disabled" @if ($isProcessing || ($apiStatus['status'] ?? '') !== 'ok') disabled @endif>
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                            </svg>
                            Mulai Scraping
                        </button>
                    </div>
                </div>

                @if (($apiStatus['status'] ?? '') !== 'ok' && !$isProcessing)
                    <div
                        class="flex items-center text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        <svg class="w-4 h-4 mr-1.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        FastAPI service tidak aktif. Pastikan container <code
                            class="bg-amber-100 px-1.5 py-0.5 rounded mx-1 font-mono">skripsi-fastapi</code>
                        berjalan.
                    </div>
                @endif
            </form>
        </div>

        {{-- ── Scraping History Table ────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            {{-- History Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="bg-amber-100 rounded-lg p-2">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">Riwayat Scraping</h2>
                </div>
            </div>

            {{-- Bulk Action Bar untuk History --}}
            @if (count($selectedLogIds) > 0)
                <div x-data x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="bg-red-50 border-b border-red-200 px-6 py-3 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <span class="text-sm font-semibold text-red-700">{{ count($selectedLogIds) }} riwayat
                            dipilih</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button wire:click="$set('selectedLogIds', [])"
                            class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            Batal Pilih
                        </button>
                        <button wire:click="openBulkDeleteLogsConfirm"
                            class="px-4 py-1.5 text-xs font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition flex items-center shadow-sm">
                            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Hapus {{ count($selectedLogIds) }} Riwayat
                        </button>
                    </div>
                </div>
            @endif

            @if ($logs->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="py-3 px-4 w-10">
                                    <input type="checkbox" wire:model.live="selectAllLogs"
                                        wire:change="toggleSelectAllLogs"
                                        class="rounded border-gray-300 text-red-500 focus:ring-red-400 cursor-pointer">
                                </th>
                                <th
                                    class="text-left py-3 px-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                    #</th>
                                <th
                                    class="text-left py-3 px-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                    Tanggal</th>
                                <th
                                    class="text-left py-3 px-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                    Trigger</th>
                                <th
                                    class="text-left py-3 px-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                    Status</th>
                                <th
                                    class="text-right py-3 px-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                    Total</th>
                                <th
                                    class="text-right py-3 px-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                    Baru</th>
                                <th
                                    class="text-right py-3 px-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                    Diperbarui</th>
                                <th
                                    class="text-left py-3 px-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                    User</th>
                                <th
                                    class="text-left py-3 px-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                    Error</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($logs as $log)
                                <tr
                                    class="hover:bg-gray-50 transition-colors {{ in_array((string) $log->id, $selectedLogIds) ? 'bg-red-50/50' : '' }}">
                                    <td class="py-3 px-4">
                                        <input type="checkbox" wire:model.live="selectedLogIds"
                                            value="{{ $log->id }}"
                                            class="rounded border-gray-300 text-red-500 focus:ring-red-400 cursor-pointer">
                                    </td>
                                    <td class="py-3 px-3 text-gray-400 font-mono text-xs">{{ $log->id }}</td>
                                    <td class="py-3 px-3 text-gray-600 whitespace-nowrap">
                                        {{ $log->created_at->format('d M Y H:i') }}</td>
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
                                                <svg class="animate-spin -ml-0.5 mr-1 h-3 w-3" fill="none"
                                                    viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4">
                                                    </circle>
                                                    <path class="opacity-75" fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                            @endif
                                            {{ ucfirst($log->status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right font-medium text-gray-700">
                                        {{ $log->total_scraped }}</td>
                                    <td class="py-3 px-3 text-right font-medium text-green-600">
                                        +{{ $log->new_added }}</td>
                                    <td class="py-3 px-3 text-right font-medium text-blue-600">
                                        {{ $log->data_updated ?? 0 }}</td>
                                    <td class="py-3 px-3 text-gray-600">{{ $log->user->name ?? '-' }}</td>
                                    <td class="py-3 px-3 text-xs text-red-500 max-w-[200px] truncate"
                                        title="{{ $log->error_message }}">
                                        {{ $log->error_message ?? '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50">
                    {{ $logs->links() }}
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-16 text-gray-400">
                    <svg class="w-14 h-14 mb-3 text-gray-200" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <p class="text-sm font-semibold text-gray-500">Belum ada riwayat scraping</p>
                    <p class="text-xs mt-1">Mulai scraping pertama Anda di form di atas</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════
         Confirm: Batalkan Scraping
    ═════════════════════════════════════════════════════ --}}
    <x-confirm-modal wireModel="showCancelConfirm" type="warning" title="Batalkan Scraping?"
        message="Proses scraping yang sedang berjalan akan dihentikan. Data yang sudah di-scrape sebelum dibatalkan tetap tersimpan."
        confirmLabel="Ya, Batalkan" confirmWire="cancelScraping" closeWire="closeCancelConfirm" />

    {{-- ════════════════════════════════════════════════════
         Confirm: Bulk Delete Riwayat
    ═════════════════════════════════════════════════════ --}}
    <x-confirm-modal wireModel="showBulkDeleteLogsConfirm" type="danger"
        title="Hapus {{ count($selectedLogIds) }} Riwayat?"
        message="Riwayat scraping yang dipilih akan dihapus secara permanen dan tidak dapat dikembalikan."
        confirmLabel="Ya, Hapus Semua" confirmWire="bulkDeleteLogs" closeWire="closeBulkDeleteLogsConfirm" />

    {{-- ════════════════════════════════════════════════════
         Confirm: Reset Database Skripsi
    ═════════════════════════════════════════════════════ --}}
    <x-confirm-modal wireModel="showResetSkripsiConfirm" type="danger" title="Reset Database Skripsi?"
        message="Semua data pada tabel skripsi akan dihapus permanen agar proses scraping bisa dimulai ulang dari nol. Data turunan dataset/topic document terkait juga akan ikut terhapus."
        confirmLabel="Ya, Reset Database" confirmWire="resetSkripsiData" closeWire="closeResetSkripsiConfirm" />

    {{-- ════════════════════════════════════════════════════
         MODAL: Pantau Detail Scraping (Monitoring)
    ═════════════════════════════════════════════════════ --}}
    <div x-data="{ open: @entangle('showMonitoringModal').live }" x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog"
        aria-modal="true">

        <div x-show="open" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.closeMonitoring()">
        </div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[88vh] flex flex-col overflow-hidden">

                {{-- Header (gradient style) --}}
                <div
                    class="bg-gradient-to-r from-unsoed-blue-700 to-unsoed-blue-600 px-6 py-4 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white">Pantau Detail Scraping</h3>
                            <p class="text-xs text-white/70">Job: {{ $activeJobId }}</p>
                        </div>
                    </div>
                    <button wire:click="closeMonitoring"
                        class="w-8 h-8 bg-white/10 hover:bg-white/25 rounded-lg flex items-center justify-center text-white transition">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                {{-- Progress Summary --}}
                <div class="px-6 py-4 bg-gray-50 border-b flex-shrink-0">
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-600 font-medium">Progress Keseluruhan</span>
                        <span class="text-unsoed-blue-600 font-bold">{{ $jobProgress }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden mb-3">
                        <div class="h-2.5 rounded-full transition-all duration-500 ease-out bg-gradient-to-r from-unsoed-blue-500 to-unsoed-blue-600"
                            style="width: {{ $jobProgress }}%"></div>
                    </div>
                    <div class="grid grid-cols-4 gap-3 text-center">
                        <div class="bg-white rounded-xl p-3 border border-gray-100 shadow-sm">
                            <p class="text-xs text-gray-400 font-medium">URL Ditemukan</p>
                            <p class="text-base font-bold text-gray-800 mt-0.5">
                                {{ $monitoringFoundTotal ?: $jobTotalUrls }}</p>
                        </div>
                        <div class="bg-white rounded-xl p-3 border border-gray-100 shadow-sm">
                            <p class="text-xs text-gray-400 font-medium">Sudah Di-scrape</p>
                            <p class="text-base font-bold text-unsoed-blue-600 mt-0.5">
                                {{ $monitoringScrapedTotal ?: $jobScrapedCount }}</p>
                        </div>
                        <div class="bg-white rounded-xl p-3 border border-gray-100 shadow-sm">
                            <p class="text-xs text-gray-400 font-medium">Gagal Parse</p>
                            <p class="text-base font-bold text-red-500 mt-0.5">{{ $jobSkippedCount }}</p>
                        </div>
                        <div class="bg-white rounded-xl p-3 border border-gray-100 shadow-sm">
                            <p class="text-xs text-gray-400 font-medium">Di-filter</p>
                            <p class="text-base font-bold text-amber-500 mt-0.5">{{ $jobFilteredCount }}</p>
                        </div>
                    </div>
                </div>

                {{-- Tabs --}}
                <div class="px-6 py-4 overflow-y-auto flex-1" x-data="{ activeTab: 'scraped' }">
                    <div class="flex border-b border-gray-200 mb-4">
                        <button @click="activeTab = 'scraped'"
                            :class="activeTab === 'scraped' ?
                                'border-unsoed-blue-500 text-unsoed-blue-600' :
                                'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-2 px-4 text-sm font-medium border-b-2 transition flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Data Di-scrape
                            ({{ $monitoringScrapedTotal ?: count($monitoringScrapedItems) }})
                        </button>
                        <button @click="activeTab = 'found'"
                            :class="activeTab === 'found' ?
                                'border-unsoed-blue-500 text-unsoed-blue-600' :
                                'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-2 px-4 text-sm font-medium border-b-2 transition flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            URL Ditemukan ({{ $monitoringFoundTotal ?: count($monitoringData) }})
                        </button>
                    </div>

                    {{-- Scraped Items --}}
                    <div x-show="activeTab === 'scraped'" class="max-h-72 overflow-y-auto">
                        @if (count($monitoringScrapedItems) > 0)
                            <table class="w-full text-xs">
                                <thead class="sticky top-0 bg-white shadow-sm">
                                    <tr class="border-b">
                                        <th
                                            class="text-left py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">
                                            #</th>
                                        <th
                                            class="text-left py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">
                                            Judul</th>
                                        <th
                                            class="text-left py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">
                                            Penulis</th>
                                        <th
                                            class="text-left py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">
                                            Tahun</th>
                                        <th
                                            class="text-left py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">
                                            Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($monitoringScrapedItems as $index => $item)
                                        <tr class="border-b border-gray-50 hover:bg-gray-50">
                                            <td class="py-2 px-2 text-gray-400">{{ $index + 1 }}</td>
                                            <td class="py-2 px-2 text-gray-700 max-w-[300px] truncate"
                                                title="{{ $item['title'] ?? '' }}">
                                                {{ $item['title'] ?? '-' }}</td>
                                            <td class="py-2 px-2 text-gray-500">{{ $item['author'] ?? '-' }}
                                            </td>
                                            <td class="py-2 px-2 text-gray-500">{{ $item['year'] ?? '-' }}</td>
                                            <td class="py-2 px-2">
                                                @if (($item['status'] ?? '') === 'success')
                                                    <span
                                                        class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-green-100 text-green-700">✓
                                                        OK</span>
                                                @else
                                                    <span
                                                        class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-red-100 text-red-700">✗
                                                        Gagal</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($monitoringScrapedTotal > count($monitoringScrapedItems))
                                <p class="text-xs text-gray-400 text-center mt-2 py-1">Menampilkan
                                    {{ count($monitoringScrapedItems) }} dari {{ $monitoringScrapedTotal }} item
                                    terbaru</p>
                            @endif
                        @else
                            <div class="text-center py-10 text-gray-400">
                                <svg class="w-10 h-10 mx-auto mb-2 text-gray-200" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                <p class="text-sm font-medium text-gray-500">Belum ada data yang di-scrape</p>
                                <p class="text-xs mt-1">Data akan muncul saat proses scraping detail berjalan</p>
                            </div>
                        @endif
                    </div>

                    {{-- Found URLs --}}
                    <div x-show="activeTab === 'found'" x-cloak class="max-h-72 overflow-y-auto">
                        @if (count($monitoringData) > 0)
                            <table class="w-full text-xs">
                                <thead class="sticky top-0 bg-white shadow-sm">
                                    <tr class="border-b">
                                        <th
                                            class="text-left py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">
                                            #</th>
                                        <th
                                            class="text-left py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">
                                            Judul</th>
                                        <th
                                            class="text-left py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">
                                            Penulis</th>
                                        <th
                                            class="text-left py-2 px-2 font-semibold text-gray-400 uppercase tracking-wide">
                                            Tahun</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($monitoringData as $index => $item)
                                        <tr class="border-b border-gray-50 hover:bg-gray-50">
                                            <td class="py-2 px-2 text-gray-400">{{ $index + 1 }}</td>
                                            <td class="py-2 px-2 text-gray-700 max-w-[350px] truncate"
                                                title="{{ $item['title'] ?? '' }}">
                                                {{ $item['title'] ?? '-' }}</td>
                                            <td class="py-2 px-2 text-gray-500">{{ $item['author'] ?? '-' }}
                                            </td>
                                            <td class="py-2 px-2 text-gray-500">{{ $item['year'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($monitoringFoundTotal > count($monitoringData))
                                <p class="text-xs text-gray-400 text-center mt-2 py-1">Menampilkan
                                    {{ count($monitoringData) }} dari {{ $monitoringFoundTotal }} URL terbaru</p>
                            @endif
                        @else
                            <div class="text-center py-10 text-gray-400">
                                <svg class="w-10 h-10 mx-auto mb-2 text-gray-200" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <p class="text-sm font-medium text-gray-500">Belum ada URL yang ditemukan</p>
                                <p class="text-xs mt-1">URL akan muncul saat proses listing halaman berjalan</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Footer --}}
                <div
                    class="px-6 py-4 border-t border-gray-100 bg-gray-50/60 flex justify-between items-center flex-shrink-0">
                    <p class="text-xs text-gray-400">
                        @if ($jobStep)
                            <span class="inline-flex items-center">
                                <svg class="w-3 h-3 mr-1 text-unsoed-blue-500 animate-pulse" fill="currentColor"
                                    viewBox="0 0 8 8">
                                    <circle cx="4" cy="4" r="3" />
                                </svg>
                                {{ $jobStep }}
                            </span>
                        @endif
                    </p>
                    <button wire:click="closeMonitoring"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold rounded-xl transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
</div>
