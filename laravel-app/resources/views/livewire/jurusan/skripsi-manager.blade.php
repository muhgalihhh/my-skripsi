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

        {{-- ── Pencarian & Filter ───────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div class="sm:col-span-2 relative">
                    <x-app.icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
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
                    <x-ui.button variant="ghost-primary" size="sm" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv"
                        class="!border !border-emerald-200 !bg-emerald-50 !text-emerald-700 hover:!bg-emerald-100 disabled:opacity-60 transition">
                        <x-app.icon name="arrow-down-tray" class="mr-1.5 h-3.5 w-3.5" />
                        <span wire:loading.remove wire:target="exportCsv">Ekspor CSV</span>
                        <span wire:loading wire:target="exportCsv">Menyiapkan...</span>
                    </x-ui.button>

                    <x-ui.button variant="ghost-primary" size="sm" wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel"
                        class="!border !border-blue-200 !bg-blue-50 !text-blue-700 hover:!bg-blue-100 disabled:opacity-60 transition">
                        <x-app.icon name="arrow-down-tray" class="mr-1.5 h-3.5 w-3.5" />
                        <span wire:loading.remove wire:target="exportExcel">Ekspor Excel</span>
                        <span wire:loading wire:target="exportExcel">Menyiapkan...</span>
                    </x-ui.button>
                </div>
            </div>

            <div class="mt-3 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Import Data Skripsi CSV</div>
                <p class="mt-1 text-xs text-gray-500">
                    Upload CSV ke tabel skripsi dengan header mengikuti format raw_data.csv
                    (ID, Judul, Penulis, Tahun, Tipe, ID Code, Kata Kunci, Subjects, Divisions, Abstrak,
                    Kesimpulan, Sumber Kesimpulan, URL, Tanggal Deposit, Tanggal Modifikasi).
                </p>
                <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <input
                        type="file"
                        accept=".csv,text/csv"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                        wire:model="skripsiCsvFile"
                    />
                    <x-ui.button variant="primary" size="sm" wire:click="importSkripsiCsv" wire:loading.attr="disabled" class="sm:w-auto">
                        <x-app.icon name="arrow-up-tray" class="mr-1.5 h-3.5 w-3.5" />
                        <span wire:loading.remove wire:target="importSkripsiCsv">Import CSV</span>
                        <span wire:loading wire:target="importSkripsiCsv">Mengimpor...</span>
                    </x-ui.button>
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
                        <x-app.icon variant="o" name="x-circle" class="w-4 h-4 text-red-600" />
                    </div>
                    <span class="text-sm font-semibold text-red-700">{{ count($selectedIds) }} data dipilih</span>
                </div>
                <div class="flex items-center space-x-2">
                    <x-ui.button variant="secondary" size="sm" wire:click="$set('selectedIds', [])">
                        Batal Pilih
                    </x-ui.button>
                    <x-ui.button variant="danger" size="sm" wire:click="openBulkDeleteConfirm"
                        class="flex items-center shadow-sm">
                        <x-app.icon name="trash" class="w-3.5 h-3.5 mr-1.5" />
                        Hapus {{ count($selectedIds) }} Data
                    </x-ui.button>
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
                                            <x-app.icon variant="o" name="arrows-up-down" class="w-3 h-3 text-unsoed-blue-500" />
                                        @endif
                                    </div>
                                </th>
                                <th class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide cursor-pointer hover:text-gray-800 select-none"
                                    wire:click="sortBy('author')">
                                    <div class="flex items-center gap-1">Penulis
                                        @if ($sortField === 'author')
                                            <x-app.icon variant="o" name="arrows-up-down" class="w-3 h-3 text-unsoed-blue-500" />
                                        @endif
                                    </div>
                                </th>
                                <th class="text-center py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide cursor-pointer hover:text-gray-800 select-none w-20"
                                    wire:click="sortBy('year')">
                                    <div class="flex items-center justify-center gap-1">Tahun
                                        @if ($sortField === 'year')
                                            <x-app.icon variant="o" name="arrows-up-down" class="w-3 h-3 text-unsoed-blue-500" />
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
                                                <x-app.icon name="eye" class="w-4 h-4" />
                                            </button>
                                            <button wire:click="openEdit({{ $skripsi->id }})"
                                                class="p-1.5 text-amber-600 hover:bg-amber-100 rounded-lg transition"
                                                title="Edit">
                                                <x-app.icon name="pencil-square" class="w-4 h-4" />
                                            </button>
                                            <button wire:click="confirmDelete({{ $skripsi->id }})"
                                                class="p-1.5 text-red-500 hover:bg-red-100 rounded-lg transition"
                                                title="Hapus">
                                                <x-app.icon name="trash" class="w-4 h-4" />
                                            </button>
                                            @if ($skripsi->url)
                                                <a href="{{ $skripsi->url }}" target="_blank"
                                                    class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition"
                                                    title="Buka Repository">
                                                    <x-app.icon name="arrow-top-right-on-square" class="w-4 h-4" />
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
                    <x-app.icon name="document-text" class="w-16 h-16 mb-4 text-gray-200" />
                    @if ($search || $yearFilter)
                        <p class="text-sm font-semibold text-gray-500">Tidak ada skripsi yang ditemukan</p>
                        <p class="text-xs mt-1">Coba ubah filter atau kata kunci pencarian</p>
                    @else
                        <p class="text-sm font-semibold text-gray-500">Belum ada data skripsi</p>
                        <p class="text-xs mt-1">Jalankan scraping untuk mengambil data dari Repository UNSOED</p>
                        <a href="{{ route('jurusan.scraping.index') }}"
                            class="mt-4 px-4 py-2 bg-unsoed-blue-600 hover:bg-unsoed-blue-700 text-white text-sm font-medium rounded-lg transition">
                            Ke Halaman Pengambilan Data
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
                            <x-app.icon variant="s" name="document-text" class="w-4 h-4 text-white" />
                        </div>
                        <h3 class="text-base font-bold text-white">Detail Skripsi</h3>
                    </div>
                    <button wire:click="closeDetail"
                        class="w-8 h-8 bg-white/10 hover:bg-white/25 rounded-lg flex items-center justify-center text-white transition">
                        <x-app.icon name="x-mark" class="w-4 h-4" />
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
                        @foreach ([['Penulis', $selectedSkripsi['author'] ?? '-'], ['Kode ID', $selectedSkripsi['id_code'] ?? '-'], ['Divisi', $selectedSkripsi['divisions'] ?? '-'], ['Subjek', $selectedSkripsi['subjects'] ?? '-'], ['Tanggal Deposit', $selectedSkripsi['deposit_date'] ?? '-'], ['Tanggal Modifikasi', $selectedSkripsi['modified_date'] ?? '-']] as [$label, $value])
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
                                        class="text-gray-300 normal-case font-normal">({{ $selectedSkripsi['conclusion_source'] === 'model' ? 'Model' : $selectedSkripsi['conclusion_source'] }})</span>
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
                                        <x-app.icon name="document-text" class="w-4 h-4 flex-shrink-0" />
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
                                <x-app.icon name="arrow-top-right-on-square" class="w-3 h-3 flex-shrink-0" />
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div
                    class="px-6 py-4 border-t border-gray-100 bg-gray-50/60 flex justify-between items-center flex-shrink-0">
                    @if ($selectedSkripsiId)
                        <x-ui.button variant="ghost-danger" size="sm" wire:click="confirmDelete({{ $selectedSkripsiId }})"
                            class="!rounded-xl border border-red-200 bg-white">
                            <x-app.icon name="trash" class="w-4 h-4" />
                            Hapus
                        </x-ui.button>
                        <div class="flex items-center gap-2">
                            <x-ui.button variant="warning" size="md" wire:click="openEdit({{ $selectedSkripsiId }})"
                                class="!rounded-xl flex items-center gap-1.5 shadow-sm">
                                <x-app.icon name="pencil-square" class="w-4 h-4" />
                                Edit
                            </x-ui.button>
                            <x-ui.button variant="light" size="md" wire:click="closeDetail"
                                class="!rounded-xl">
                                Tutup
                            </x-ui.button>
                        </div>
                    @else
                        <div></div>
                        <x-ui.button variant="light" size="md" wire:click="closeDetail"
                            class="!rounded-xl">
                            Tutup
                        </x-ui.button>
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
                            <x-app.icon variant="s" name="pencil-square" class="w-4 h-4 text-white" />
                        </div>
                        <h3 class="text-base font-bold text-white">Edit Skripsi</h3>
                    </div>
                    <button wire:click="closeEdit"
                        class="w-8 h-8 bg-white/10 hover:bg-white/25 rounded-lg flex items-center justify-center text-white transition">
                        <x-app.icon name="x-mark" class="w-4 h-4" />
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
                                    ID</label>
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
                                    class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Subjek</label>
                                <input wire:model="editSubjects" type="text"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition">
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Divisi</label>
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
                            <x-app.icon name="arrow-path" wire:loading wire:target="saveEdit" class="animate-spin w-4 h-4" />
                            <x-app.icon name="check" wire:loading.remove wire:target="saveEdit" class="w-4 h-4" />
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
