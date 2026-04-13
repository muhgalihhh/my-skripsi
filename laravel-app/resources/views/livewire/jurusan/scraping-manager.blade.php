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
                <x-ui.button variant="ghost-primary" size="sm" wire:click="checkApiStatus"
                    class="font-medium flex items-center transition !px-2"
                    wire:loading.class="opacity-50" wire:target="checkApiStatus">
                    <x-app.icon name="arrow-path" class="w-4 h-4 mr-1" wire:loading.class="animate-spin" wire:target="checkApiStatus" />
                    Refresh Status
                </x-ui.button>
            </div>
        </div>

        {{-- ── Active Scraping Progress Card ───────────────── --}}
        @if ($isProcessing && $activeJobId)
            <div class="bg-white rounded-xl shadow-sm border-2 border-unsoed-blue-200 p-6" wire:key="progress-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="bg-unsoed-blue-100 rounded-lg p-2 mr-3">
                            <x-app.icon variant="o" name="arrow-path" class="w-5 h-5 text-unsoed-blue-600 animate-spin" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Scraping Sedang Berjalan</h2>
                            <p class="text-xs text-gray-400">Job ID: {{ $activeJobId }}</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <x-ui.button variant="primary" size="sm" wire:click="openMonitoring"
                            class="!bg-unsoed-blue-50 hover:!bg-unsoed-blue-100 !text-unsoed-blue-600 !border-unsoed-blue-200 shadow-none">
                            <x-app.icon name="eye" class="w-4 h-4 mr-1" />
                            Pantau Detail
                        </x-ui.button>
                        <x-ui.button variant="danger" size="sm" wire:click="openCancelConfirm"
                            class="!bg-red-50 hover:!bg-red-100 !text-red-600 !border-red-200 shadow-none">
                            <x-app.icon name="stop-circle" class="w-4 h-4 mr-1" />
                            Batalkan
                        </x-ui.button>
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
                        <x-app.icon variant="o" name="clock" class="w-3 h-3 mr-1 text-unsoed-blue-500 animate-pulse" />
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
                        <x-app.icon variant="o" name="check-circle" class="w-5 h-5 mr-2 text-green-500" />
                    @elseif($statusType === 'error')
                        <x-app.icon variant="o" name="x-circle" class="w-5 h-5 mr-2 text-red-500" />
                    @else
                        <x-app.icon variant="o" name="arrow-path" class="w-5 h-5 mr-2 text-blue-500 animate-spin" />
                    @endif
                    <span class="text-sm font-medium">{{ $statusMessage }}</span>
                </div>
                <button @click="show = false" class="ml-3 opacity-60 hover:opacity-100">
                    <x-app.icon name="x-mark" class="w-4 h-4" />
                </button>
            </div>
        @endif

        {{-- ── Scraping Form ────────────────────────────────── --}}
        <div
            class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 {{ $isProcessing ? 'opacity-60 pointer-events-none' : '' }}">
            <div class="flex items-start justify-between mb-4 gap-3">
                <div class="flex items-center">
                    <div class="bg-unsoed-blue-100 rounded-lg p-2 mr-3">
                        <x-app.icon variant="o" name="cloud-arrow-down" class="w-5 h-5 text-unsoed-blue-600" />
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
                    <x-app.icon name="trash" class="w-4 h-4 mr-1.5" />
                    Reset Database Skripsi
                </button>
            </div>

            <form wire:submit="openStartScrapingConfirm">
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
                            <x-app.icon name="cloud-arrow-down" class="w-4 h-4 mr-2" />
                            Mulai Scraping
                        </button>
                    </div>
                </div>

                @if (($apiStatus['status'] ?? '') !== 'ok' && !$isProcessing)
                    <div
                        class="flex items-center text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        <x-app.icon name="exclamation-triangle" class="w-4 h-4 mr-1.5 flex-shrink-0" />
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
                        <x-app.icon variant="o" name="cloud-arrow-down" class="w-5 h-5 text-amber-600" />
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
                        <x-ui.button variant="secondary" size="sm" wire:click="$set('selectedLogIds', [])">
                            Batal Pilih
                        </x-ui.button>
                        <x-ui.button variant="danger" size="sm" wire:click="openBulkDeleteLogsConfirm"
                            class="flex items-center shadow-sm">
                            <x-app.icon name="trash" class="w-3.5 h-3.5 mr-1.5" />
                            Hapus {{ count($selectedLogIds) }} Riwayat
                        </x-ui.button>
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
                                                <x-app.icon name="arrow-path" class="animate-spin -ml-0.5 mr-1 h-3 w-3" />
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
                    <x-app.icon name="cloud-arrow-down" class="w-14 h-14 mb-3 text-gray-200" />
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
         Confirm: Mulai Scraping
    ═════════════════════════════════════════════════════ --}}
    <x-confirm-modal wireModel="showStartScrapingConfirm" type="warning" title="Mulai Scraping Sekarang?"
        message="Proses scraping dapat memakan waktu dan resource server yang cukup besar. Lanjutkan menjalankan scraping dengan rentang tahun yang dipilih?"
        confirmLabel="Ya, Mulai Scraping" confirmWire="startScraping" closeWire="closeStartScrapingConfirm" />

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
                            <x-app.icon variant="s" name="eye" class="w-4 h-4 text-white" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white">Pantau Detail Scraping</h3>
                            <p class="text-xs text-white/70">Job: {{ $activeJobId }}</p>
                        </div>
                    </div>
                    <button wire:click="closeMonitoring"
                        class="w-8 h-8 bg-white/10 hover:bg-white/25 rounded-lg flex items-center justify-center text-white transition">
                        <x-app.icon name="x-mark" class="w-4 h-4" />
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
                            <x-app.icon name="document-text" class="w-4 h-4" />
                            Data Di-scrape
                            ({{ $monitoringScrapedTotal ?: count($monitoringScrapedItems) }})
                        </button>
                        <button @click="activeTab = 'found'"
                            :class="activeTab === 'found' ?
                                'border-unsoed-blue-500 text-unsoed-blue-600' :
                                'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-2 px-4 text-sm font-medium border-b-2 transition flex items-center gap-1.5">
                            <x-app.icon name="link" class="w-4 h-4" />
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
                                <x-app.icon name="cloud-arrow-down" class="w-10 h-10 mx-auto mb-2 text-gray-200" />
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
                                <x-app.icon name="link" class="w-10 h-10 mx-auto mb-2 text-gray-200" />
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
                                <x-app.icon variant="o" name="clock" class="w-3 h-3 mr-1 text-unsoed-blue-500 animate-pulse" />
                                {{ $jobStep }}
                            </span>
                        @endif
                    </p>
                    <x-ui.button variant="secondary" wire:click="closeMonitoring">
                        Tutup
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>

</div>
</div>
