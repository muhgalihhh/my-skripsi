import sys

filepath = r'c:\Materi Kuliah\AYO KERJAIN SKRIPSI\- Sistem\laravel-app\resources\views\livewire\jurusan\scraping-manager.blade.php'
lines = open(filepath, encoding='utf-8').readlines()

# Find the line with "Monitoring Modal" comment
cut_at = None
for i, line in enumerate(lines):
    if '{{-- Monitoring Modal --}}' in line:
        cut_at = i
        break

print(f'Cutting at line {cut_at + 1} (0-indexed: {cut_at})')
print(f'That line: {repr(lines[cut_at])}')

keep = lines[:cut_at]

new_modal = """    {{-- Monitoring Modal --}}
    <div
        x-data="{ open: @entangle('showMonitoringModal').live }"
        x-show="open"
        x-cloak
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60"
        @click.self="$wire.closeMonitoring()">

        <div
            x-show="open"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col">

            {{-- Header --}}
            <div class="bg-unsoed-blue-600 px-6 py-4 flex items-center justify-between rounded-t-xl flex-shrink-0">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-white mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <h3 class="text-lg font-semibold text-white">Pantau Detail Scraping</h3>
                </div>
                <button wire:click="closeMonitoring" class="text-white/80 hover:text-white transition">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            {{-- Progress Summary in Modal --}}
            <div class="px-6 py-4 bg-gray-50 border-b flex-shrink-0">
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-600 font-medium">Progress Keseluruhan</span>
                    <span class="text-unsoed-blue-600 font-bold">{{ $jobProgress }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden mb-3">
                    <div class="h-2.5 rounded-full transition-all duration-500 ease-out bg-gradient-to-r from-unsoed-blue-500 to-unsoed-blue-600"
                        style="width: {{ $jobProgress }}%"></div>
                </div>
                <div class="grid grid-cols-4 gap-3 text-center text-xs">
                    <div class="bg-white rounded-lg p-2 border">
                        <p class="text-gray-500">URL Ditemukan</p>
                        <p class="text-base font-bold text-gray-800">{{ $monitoringFoundTotal ?: $jobTotalUrls }}</p>
                    </div>
                    <div class="bg-white rounded-lg p-2 border">
                        <p class="text-gray-500">Sudah Di-scrape</p>
                        <p class="text-base font-bold text-unsoed-blue-600">{{ $monitoringScrapedTotal ?: $jobScrapedCount }}</p>
                    </div>
                    <div class="bg-white rounded-lg p-2 border">
                        <p class="text-gray-500">Gagal Parse</p>
                        <p class="text-base font-bold text-red-500">{{ $jobSkippedCount }}</p>
                    </div>
                    <div class="bg-white rounded-lg p-2 border">
                        <p class="text-gray-500">Di-filter</p>
                        <p class="text-base font-bold text-amber-500">{{ $jobFilteredCount }}</p>
                    </div>
                </div>
            </div>

            {{-- Tabs: Found URLs & Scraped Items --}}
            <div class="px-6 py-4 overflow-y-auto flex-1" x-data="{ activeTab: 'scraped' }">
                <div class="flex border-b border-gray-200 mb-4">
                    <button @click="activeTab = 'scraped'"
                        :class="activeTab === 'scraped' ? 'border-unsoed-blue-500 text-unsoed-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-2 px-4 text-sm font-medium border-b-2 transition">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Data Sudah Di-scrape ({{ $monitoringScrapedTotal ?: count($monitoringScrapedItems) }})
                    </button>
                    <button @click="activeTab = 'found'"
                        :class="activeTab === 'found' ? 'border-unsoed-blue-500 text-unsoed-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-2 px-4 text-sm font-medium border-b-2 transition">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        URL Ditemukan ({{ $monitoringFoundTotal ?: count($monitoringData) }})
                    </button>
                </div>

                {{-- Scraped Items Tab --}}
                <div x-show="activeTab === 'scraped'" class="max-h-80 overflow-y-auto">
                    @if (count($monitoringScrapedItems) > 0)
                        <table class="w-full text-xs">
                            <thead class="sticky top-0 bg-white">
                                <tr class="border-b">
                                    <th class="text-left py-2 px-2 font-medium text-gray-500">#</th>
                                    <th class="text-left py-2 px-2 font-medium text-gray-500">Judul</th>
                                    <th class="text-left py-2 px-2 font-medium text-gray-500">Penulis</th>
                                    <th class="text-left py-2 px-2 font-medium text-gray-500">Tahun</th>
                                    <th class="text-left py-2 px-2 font-medium text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($monitoringScrapedItems as $index => $item)
                                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                                        <td class="py-2 px-2 text-gray-400">{{ $index + 1 }}</td>
                                        <td class="py-2 px-2 text-gray-700 max-w-[300px] truncate" title="{{ $item['title'] ?? '' }}">
                                            {{ $item['title'] ?? '-' }}
                                        </td>
                                        <td class="py-2 px-2 text-gray-500">{{ $item['author'] ?? '-' }}</td>
                                        <td class="py-2 px-2 text-gray-500">{{ $item['year'] ?? '-' }}</td>
                                        <td class="py-2 px-2">
                                            @if (($item['status'] ?? '') === 'success')
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-green-100 text-green-700">✓ OK</span>
                                            @else
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-red-100 text-red-700">✗ Gagal</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($monitoringScrapedTotal > count($monitoringScrapedItems))
                            <p class="text-xs text-gray-400 text-center mt-2">
                                Menampilkan {{ count($monitoringScrapedItems) }} dari {{ $monitoringScrapedTotal }} item terbaru
                            </p>
                        @endif
                    @else
                        <div class="text-center py-8 text-gray-400">
                            <svg class="w-10 h-10 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <p class="text-sm">Belum ada data yang di-scrape</p>
                            <p class="text-xs mt-1">Data akan muncul saat proses scraping detail berjalan</p>
                        </div>
                    @endif
                </div>

                {{-- Found URLs Tab --}}
                <div x-show="activeTab === 'found'" x-cloak class="max-h-80 overflow-y-auto">
                    @if (count($monitoringData) > 0)
                        <table class="w-full text-xs">
                            <thead class="sticky top-0 bg-white">
                                <tr class="border-b">
                                    <th class="text-left py-2 px-2 font-medium text-gray-500">#</th>
                                    <th class="text-left py-2 px-2 font-medium text-gray-500">Judul</th>
                                    <th class="text-left py-2 px-2 font-medium text-gray-500">Penulis</th>
                                    <th class="text-left py-2 px-2 font-medium text-gray-500">Tahun</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($monitoringData as $index => $item)
                                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                                        <td class="py-2 px-2 text-gray-400">{{ $index + 1 }}</td>
                                        <td class="py-2 px-2 text-gray-700 max-w-[350px] truncate" title="{{ $item['title'] ?? '' }}">
                                            {{ $item['title'] ?? '-' }}
                                        </td>
                                        <td class="py-2 px-2 text-gray-500">{{ $item['author'] ?? '-' }}</td>
                                        <td class="py-2 px-2 text-gray-500">{{ $item['year'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($monitoringFoundTotal > count($monitoringData))
                            <p class="text-xs text-gray-400 text-center mt-2">
                                Menampilkan {{ count($monitoringData) }} dari {{ $monitoringFoundTotal }} URL terbaru
                            </p>
                        @endif
                    @else
                        <div class="text-center py-8 text-gray-400">
                            <svg class="w-10 h-10 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <p class="text-sm">Belum ada URL yang ditemukan</p>
                            <p class="text-xs mt-1">URL akan muncul saat proses listing halaman berjalan</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 px-6 py-3 flex justify-between items-center border-t rounded-b-xl flex-shrink-0">
                <p class="text-xs text-gray-400">
                    @if ($jobStep)
                        <span class="inline-flex items-center">
                            <svg class="w-3 h-3 mr-1 text-unsoed-blue-500 animate-pulse" fill="currentColor" viewBox="0 0 8 8">
                                <circle cx="4" cy="4" r="3" />
                            </svg>
                            {{ $jobStep }}
                        </span>
                    @endif
                </p>
                <button wire:click="closeMonitoring"
                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-lg transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>
"""

with open(filepath, 'w', encoding='utf-8', newline='') as f:
    f.writelines(keep)
    f.write(new_modal)

lines2 = open(filepath, encoding='utf-8').readlines()
print('New total lines:', len(lines2))
print('Last 3 lines:')
for l in lines2[-3:]:
    print(repr(l))
