<div>
    @section('page-title', 'Manajemen Skripsi')

    <div class="space-y-5">

        {{-- ── Page Header ──────────────────────────────────── --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Manajemen Skripsi</h1>
                <p class="mt-1 text-sm text-gray-500">Data skripsi yang di-scrape dari Repository UNSOED</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-400">Total Data</p>
                <p class="text-2xl font-bold text-unsoed-blue-600">{{ number_format($totalSkripsi) }}</p>
            </div>
        </div>

        {{-- ── Search & Filter ──────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div class="sm:col-span-2 relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input wire:model.live.debounce.300ms="search" type="text"
                        placeholder="Cari judul, penulis, kata kunci..."
                        class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 transition">
                </div>
                <select wire:model.live="yearFilter"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 transition">
                    <option value="">Semua Tahun</option>
                    @foreach ($availableYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
                <select wire:model.live="perPage"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-unsoed-blue-500 focus:border-unsoed-blue-500 transition">
                    <option value="10">10 per halaman</option>
                    <option value="15">15 per halaman</option>
                    <option value="25">25 per halaman</option>
                    <option value="50">50 per halaman</option>
                </select>
            </div>

            <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-500">Export mengikuti filter dan pencarian yang sedang aktif.</p>
                <div class="flex flex-wrap gap-2">
                    <button wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv"
                        class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition disabled:opacity-60">
                        <svg class="mr-1.5 h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 16V4m0 12l-4-4m4 4l4-4M5 20h14" />
                        </svg>
                        <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                        <span wire:loading wire:target="exportCsv">Menyiapkan...</span>
                    </button>

                    <button wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel"
                        class="inline-flex items-center rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition disabled:opacity-60">
                        <svg class="mr-1.5 h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 16V4m0 12l-4-4m4 4l4-4M5 20h14" />
                        </svg>
                        <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
                        <span wire:loading wire:target="exportExcel">Menyiapkan...</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Bulk Action Bar ──────────────────────────────── --}}
        @if (count($selectedIds) > 0)
            <div x-data x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <span class="text-sm font-semibold text-red-700">{{ count($selectedIds) }} data dipilih</span>
                </div>
                <div class="flex items-center space-x-2">
                    <button wire:click="$set('selectedIds', [])"
                        class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Batal Pilih
                    </button>
                    <button wire:click="openBulkDeleteConfirm"
                        class="px-4 py-1.5 text-xs font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition flex items-center shadow-sm">
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Hapus {{ count($selectedIds) }} Data
                    </button>
                </div>
            </div>
        @endif

        {{-- ── Skripsi Table ────────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            @if ($skripsiList->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="py-3 px-4 w-10">
                                    <input type="checkbox" wire:model.live="selectAll" wire:change="toggleSelectAll"
                                        class="rounded border-gray-300 text-unsoed-blue-600 focus:ring-unsoed-blue-500 cursor-pointer">
                                </th>
                                <th
                                    class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide w-10">
                                    #</th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide cursor-pointer hover:text-gray-800 select-none"
                                    wire:click="sortBy('title')">
                                    <div class="flex items-center gap-1">Judul
                                        @if ($sortField === 'title')
                                            <svg class="w-3 h-3 text-unsoed-blue-500" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                @if ($sortDirection === 'asc')
                                                    <path
                                                        d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 6.414l-3.293 3.293a1 1 0 01-1.414 0z" />
                                                @else
                                                    <path
                                                        d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 13.586l3.293-3.293a1 1 0 011.414 0z" />
                                                @endif
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide cursor-pointer hover:text-gray-800 select-none"
                                    wire:click="sortBy('author')">
                                    <div class="flex items-center gap-1">Penulis
                                        @if ($sortField === 'author')
                                            <svg class="w-3 h-3 text-unsoed-blue-500" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                @if ($sortDirection === 'asc')
                                                    <path
                                                        d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 6.414l-3.293 3.293a1 1 0 01-1.414 0z" />
                                                @else
                                                    <path
                                                        d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 13.586l3.293-3.293a1 1 0 011.414 0z" />
                                                @endif
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th class="text-center py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide cursor-pointer hover:text-gray-800 select-none w-20"
                                    wire:click="sortBy('year')">
                                    <div class="flex items-center justify-center gap-1">Tahun
                                        @if ($sortField === 'year')
                                            <svg class="w-3 h-3 text-unsoed-blue-500" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                @if ($sortDirection === 'asc')
                                                    <path
                                                        d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 6.414l-3.293 3.293a1 1 0 01-1.414 0z" />
                                                @else
                                                    <path
                                                        d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 13.586l3.293-3.293a1 1 0 011.414 0z" />
                                                @endif
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th
                                    class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                    Kata Kunci</th>
                                <th
                                    class="text-center py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide w-28">
                                    Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($skripsiList as $index => $skripsi)
                                <tr
                                    class="hover:bg-blue-50/40 transition-colors {{ in_array((string) $skripsi->id, $selectedIds) ? 'bg-red-50/50' : '' }}">
                                    <td class="py-3 px-4">
                                        <input type="checkbox" wire:model.live="selectedIds" value="{{ $skripsi->id }}"
                                            class="rounded border-gray-300 text-unsoed-blue-600 focus:ring-unsoed-blue-500 cursor-pointer">
                                    </td>
                                    <td class="py-3 px-4 text-gray-400 text-xs font-mono">
                                        {{ $skripsiList->firstItem() + $index }}</td>
                                    <td class="py-3 px-4">
                                        <div class="max-w-[320px]">
                                            <p class="text-gray-800 font-medium text-sm line-clamp-2"
                                                title="{{ $skripsi->title }}">{{ $skripsi->title }}</p>
                                            @if ($skripsi->abstract)
                                                <p class="text-gray-400 text-xs mt-0.5 line-clamp-1">
                                                    {{ Str::limit($skripsi->abstract, 80) }}</p>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-gray-600 text-sm whitespace-nowrap">
                                        {{ $skripsi->author ?? '—' }}</td>
                                    <td class="py-3 px-4 text-center">
                                        @if ($skripsi->year)
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-unsoed-blue-100 text-unsoed-blue-700">{{ $skripsi->year }}</span>
                                        @else
                                            <span class="text-gray-300 text-xs">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-xs text-gray-500 max-w-[180px] truncate"
                                        title="{{ $skripsi->keywords }}">
                                        {{ Str::limit($skripsi->keywords, 50) ?? '—' }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button wire:click="showDetail({{ $skripsi->id }})"
                                                class="p-1.5 text-unsoed-blue-600 hover:bg-unsoed-blue-100 rounded-lg transition"
                                                title="Lihat Detail">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                            <button wire:click="openEdit({{ $skripsi->id }})"
                                                class="p-1.5 text-amber-600 hover:bg-amber-100 rounded-lg transition"
                                                title="Edit">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button wire:click="confirmDelete({{ $skripsi->id }})"
                                                class="p-1.5 text-red-500 hover:bg-red-100 rounded-lg transition"
                                                title="Hapus">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                            @if ($skripsi->url)
                                                <a href="{{ $skripsi->url }}" target="_blank"
                                                    class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition"
                                                    title="Buka Repository">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                    </svg>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50">
                    {{ $skripsiList->links() }}
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-20 text-gray-400">
                    <svg class="w-16 h-16 mb-4 text-gray-200" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    @if ($search || $yearFilter)
                        <p class="text-sm font-semibold text-gray-500">Tidak ada skripsi yang ditemukan</p>
                        <p class="text-xs mt-1">Coba ubah filter atau kata kunci pencarian</p>
                    @else
                        <p class="text-sm font-semibold text-gray-500">Belum ada data skripsi</p>
                        <p class="text-xs mt-1">Jalankan scraping untuk mengambil data dari Repository UNSOED</p>
                        <a href="{{ route('jurusan.scraping.index') }}"
                            class="mt-4 px-4 py-2 bg-unsoed-blue-600 hover:bg-unsoed-blue-700 text-white text-sm font-medium rounded-lg transition">
                            Ke Halaman Scraping
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════
         MODAL: Konfirmasi Hapus 1 Data
    ═════════════════════════════════════════════════════ --}}
    <x-confirm-modal wireModel="showDeleteConfirm" title="Hapus Data Skripsi?"
        message="Tindakan ini tidak dapat dibatalkan. Data akan dihapus secara permanen." confirmLabel="Ya, Hapus"
        confirmWire="executeDelete" type="danger" closeWire="closeDeleteConfirm" />

    {{-- ════════════════════════════════════════════════════
         MODAL: Konfirmasi Bulk Delete
    ═════════════════════════════════════════════════════ --}}
    <x-confirm-modal wireModel="showBulkDeleteConfirm" title="Hapus {{ count($selectedIds) }} Data?"
        message="Semua data yang dipilih akan dihapus secara permanen dan tidak bisa dikembalikan."
        confirmLabel="Ya, Hapus Semua" confirmWire="bulkDelete" type="danger" closeWire="closeBulkDeleteConfirm" />

    {{-- ════════════════════════════════════════════════════
         MODAL: Detail Skripsi
    ═════════════════════════════════════════════════════ --}}
    <div x-data="{ open: @entangle('showDetailModal').live }" x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog"
        aria-modal="true">
        <div x-show="open" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.closeDetail()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[88vh] flex flex-col overflow-hidden">

                {{-- Header --}}
                <div
                    class="bg-gradient-to-r from-unsoed-blue-700 to-unsoed-blue-600 px-6 py-4 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-white">Detail Skripsi</h3>
                    </div>
                    <button wire:click="closeDetail"
                        class="w-8 h-8 bg-white/10 hover:bg-white/25 rounded-lg flex items-center justify-center text-white transition">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="px-6 py-5 overflow-y-auto flex-1 space-y-5">
                    <div>
                        <h4 class="text-base font-bold text-gray-900 leading-snug">
                            {{ $selectedSkripsi['title'] ?? '-' }}</h4>
                        <div class="flex flex-wrap gap-2 mt-2">
                            @if ($selectedSkripsi['year'] ?? null)
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-unsoed-blue-100 text-unsoed-blue-700">📅
                                    {{ $selectedSkripsi['year'] }}</span>
                            @endif
                            @if ($selectedSkripsi['type'] ?? null)
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">{{ $selectedSkripsi['type'] }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        @foreach ([['Penulis', $selectedSkripsi['author'] ?? '-'], ['ID Code', $selectedSkripsi['id_code'] ?? '-'], ['Divisions', $selectedSkripsi['divisions'] ?? '-'], ['Subjects', $selectedSkripsi['subjects'] ?? '-'], ['Tanggal Deposit', $selectedSkripsi['deposit_date'] ?? '-'], ['Tanggal Modifikasi', $selectedSkripsi['modified_date'] ?? '-']] as [$label, $value])
                            <div class="bg-gray-50 rounded-xl p-3">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">
                                    {{ $label }}</p>
                                <p class="text-sm text-gray-800">{{ $value }}</p>
                            </div>
                        @endforeach
                    </div>

                    @if ($selectedSkripsi['keywords'] ?? null)
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Kata Kunci</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach (explode(',', $selectedSkripsi['keywords']) as $keyword)
                                    @if (trim($keyword))
                                        <span
                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">{{ trim($keyword) }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($selectedSkripsi['abstract'] ?? null)
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Abstrak</p>
                            <div
                                class="bg-gray-50 border border-gray-100 rounded-xl p-4 text-sm text-gray-700 leading-relaxed max-h-36 overflow-y-auto">
                                {{ $selectedSkripsi['abstract'] }}
                            </div>
                        </div>
                    @endif

                    @if ($selectedSkripsi['conclusion'] ?? null)
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">
                                Kesimpulan
                                @if ($selectedSkripsi['conclusion_source'] ?? null)
                                    <span
                                        class="text-gray-300 normal-case font-normal">({{ $selectedSkripsi['conclusion_source'] }})</span>
                                @endif
                            </p>
                            <div
                                class="bg-green-50 border border-green-100 rounded-xl p-4 text-sm text-gray-700 leading-relaxed max-h-36 overflow-y-auto">
                                {{ $selectedSkripsi['conclusion'] }}
                            </div>
                        </div>
                    @endif

                    @if (!empty($selectedSkripsi['pdf_documents']))
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Dokumen PDF</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach ($selectedSkripsi['pdf_documents'] as $docName => $docUrl)
                                    <a href="{{ $docUrl }}" target="_blank"
                                        class="flex items-center px-3 py-2 bg-red-50 hover:bg-red-100 rounded-xl text-xs font-medium text-red-700 transition border border-red-100 gap-1.5">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        {{ $docName }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($selectedSkripsi['url'] ?? null)
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">URL Repository
                            </p>
                            <a href="{{ $selectedSkripsi['url'] }}" target="_blank"
                                class="text-sm text-unsoed-blue-600 hover:underline break-all inline-flex items-center gap-1">
                                {{ $selectedSkripsi['url'] }}
                                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div
                    class="px-6 py-4 border-t border-gray-100 bg-gray-50/60 flex justify-between items-center flex-shrink-0">
                    @if ($selectedSkripsiId)
                        <button wire:click="confirmDelete({{ $selectedSkripsiId }})"
                            class="px-3 py-2 bg-white hover:bg-red-50 text-red-600 text-xs font-semibold rounded-xl border border-red-200 transition flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Hapus
                        </button>
                        <div class="flex items-center gap-2">
                            <button wire:click="openEdit({{ $selectedSkripsiId }})"
                                class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-xl transition flex items-center gap-1.5 shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Edit
                            </button>
                            <button wire:click="closeDetail"
                                class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold rounded-xl transition">
                                Tutup
                            </button>
                        </div>
                    @else
                        <div></div>
                        <button wire:click="closeDetail"
                            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold rounded-xl transition">
                            Tutup
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════
         MODAL: Edit Skripsi
    ═════════════════════════════════════════════════════ --}}
    <div x-data="{ open: @entangle('showEditModal').live }" x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog"
        aria-modal="true">
        <div x-show="open" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.closeEdit()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[88vh] flex flex-col overflow-hidden">

                {{-- Header --}}
                <div
                    class="bg-gradient-to-r from-amber-500 to-amber-400 px-6 py-4 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-white">Edit Skripsi</h3>
                    </div>
                    <button wire:click="closeEdit"
                        class="w-8 h-8 bg-white/10 hover:bg-white/25 rounded-lg flex items-center justify-center text-white transition">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                {{-- Body --}}
                <form wire:submit="saveEdit" class="flex flex-col flex-1 overflow-hidden">
                    <div class="px-6 py-5 overflow-y-auto flex-1 space-y-4">
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Judul
                                <span class="text-red-500 normal-case font-normal">*</span></label>
                            <input wire:model="editTitle" type="text"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                            @error('editTitle')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Penulis</label>
                                <input wire:model="editAuthor" type="text"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Tahun</label>
                                <input wire:model="editYear" type="number" min="2000" max="2030"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                                @error('editYear')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Tipe</label>
                                <input wire:model="editType" type="text"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">ID
                                    Code</label>
                                <input wire:model="editIdCode" type="text"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                            </div>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Kata
                                Kunci</label>
                            <input wire:model="editKeywords" type="text" placeholder="Pisahkan dengan koma"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Subjects</label>
                                <input wire:model="editSubjects" type="text"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Divisions</label>
                                <input wire:model="editDivisions" type="text"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                            </div>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Abstrak</label>
                            <textarea wire:model="editAbstract" rows="3"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition resize-y"></textarea>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Kesimpulan</label>
                            <textarea wire:model="editConclusion" rows="3"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition resize-y"></textarea>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50/60 border-t flex justify-end items-center gap-3 flex-shrink-0">
                        <button type="button" wire:click="closeEdit"
                            class="px-4 py-2.5 bg-white hover:bg-gray-100 text-gray-700 text-sm font-semibold rounded-xl border border-gray-200 transition">
                            Batal
                        </button>
                        <button type="submit"
                            class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-xl transition flex items-center gap-2 shadow-sm"
                            wire:loading.attr="disabled" wire:target="saveEdit">
                            <svg wire:loading wire:target="saveEdit" class="animate-spin w-4 h-4" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <svg wire:loading.remove wire:target="saveEdit" class="w-4 h-4" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
