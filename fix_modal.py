import sys

filepath = r'c:\Materi Kuliah\AYO KERJAIN SKRIPSI\- Sistem\laravel-app\resources\views\livewire\jurusan\skripsi-manager.blade.php'
lines = open(filepath, encoding='utf-8').readlines()

# Keep lines 0-241 (the table/non-modal part, up to but not including the Detail Modal comment)
keep = lines[:242]

new_modals = """    {{-- Detail Modal --}}
    <div
        x-data="{ open: @entangle('showDetailModal').live }"
        x-show="open"
        x-cloak
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60"
        @click.self="$wire.closeDetail()">

        <div
            x-show="open"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">

            {{-- Header --}}
            <div class="bg-unsoed-blue-600 px-6 py-4 flex items-center justify-between rounded-t-xl flex-shrink-0">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-white mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <h3 class="text-lg font-semibold text-white">Detail Skripsi</h3>
                </div>
                <button wire:click="closeDetail" class="text-white/80 hover:text-white transition">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5 overflow-y-auto flex-1 space-y-4">
                <div>
                    <h4 class="text-lg font-bold text-gray-900 leading-snug">{{ $selectedSkripsi['title'] ?? '-' }}</h4>
                    <div class="flex items-center space-x-3 mt-2">
                        @if ($selectedSkripsi['year'] ?? null)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-unsoed-blue-100 text-unsoed-blue-700">
                                {{ $selectedSkripsi['year'] }}
                            </span>
                        @endif
                        @if ($selectedSkripsi['type'] ?? null)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                {{ $selectedSkripsi['type'] }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Penulis</p>
                        <p class="text-gray-800 mt-0.5">{{ $selectedSkripsi['author'] ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">ID Code</p>
                        <p class="text-gray-800 mt-0.5">{{ $selectedSkripsi['id_code'] ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Divisions</p>
                        <p class="text-gray-800 mt-0.5">{{ $selectedSkripsi['divisions'] ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Subjects</p>
                        <p class="text-gray-800 mt-0.5">{{ $selectedSkripsi['subjects'] ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Tanggal Deposit</p>
                        <p class="text-gray-800 mt-0.5">{{ $selectedSkripsi['deposit_date'] ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Tanggal Modifikasi</p>
                        <p class="text-gray-800 mt-0.5">{{ $selectedSkripsi['modified_date'] ?? '-' }}</p>
                    </div>
                </div>

                @if ($selectedSkripsi['keywords'] ?? null)
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Kata Kunci</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach (explode(',', $selectedSkripsi['keywords']) as $keyword)
                                @if (trim($keyword))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">{{ trim($keyword) }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($selectedSkripsi['abstract'] ?? null)
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Abstrak</p>
                        <div class="bg-gray-50 rounded-lg p-3 text-sm text-gray-700 leading-relaxed max-h-40 overflow-y-auto">
                            {{ $selectedSkripsi['abstract'] }}
                        </div>
                    </div>
                @endif

                @if ($selectedSkripsi['conclusion'] ?? null)
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">
                            Kesimpulan
                            @if ($selectedSkripsi['conclusion_source'] ?? null)
                                <span class="text-gray-400 normal-case">({{ $selectedSkripsi['conclusion_source'] }})</span>
                            @endif
                        </p>
                        <div class="bg-green-50 rounded-lg p-3 text-sm text-gray-700 leading-relaxed max-h-40 overflow-y-auto">
                            {{ $selectedSkripsi['conclusion'] }}
                        </div>
                    </div>
                @endif

                @if (!empty($selectedSkripsi['pdf_documents']))
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Dokumen PDF</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            @foreach ($selectedSkripsi['pdf_documents'] as $docName => $docUrl)
                                <a href="{{ $docUrl }}" target="_blank"
                                    class="flex items-center px-3 py-2 bg-red-50 hover:bg-red-100 rounded-lg text-xs text-red-700 transition border border-red-100">
                                    <svg class="w-4 h-4 mr-1.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                    </svg>
                                    {{ $docName }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($selectedSkripsi['url'] ?? null)
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">URL Repository</p>
                        <a href="{{ $selectedSkripsi['url'] }}" target="_blank"
                            class="text-sm text-unsoed-blue-600 hover:text-unsoed-blue-800 underline break-all">
                            {{ $selectedSkripsi['url'] }}
                        </a>
                    </div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 px-6 py-3 flex justify-between items-center border-t rounded-b-xl flex-shrink-0">
                @if ($selectedSkripsiId)
                    <button wire:click="deleteSkripsi({{ $selectedSkripsiId }})"
                        wire:confirm="Yakin ingin menghapus data skripsi ini?"
                        class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-medium rounded-lg border border-red-200 transition flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Hapus
                    </button>
                    <div class="flex items-center space-x-2">
                        <button wire:click="openEdit({{ $selectedSkripsiId }})"
                            class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg transition flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </button>
                        <button wire:click="closeDetail"
                            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-lg transition">
                            Tutup
                        </button>
                    </div>
                @else
                    <div></div>
                    <button wire:click="closeDetail"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-lg transition">
                        Tutup
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div
        x-data="{ open: @entangle('showEditModal').live }"
        x-show="open"
        x-cloak
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60"
        @click.self="$wire.closeEdit()">

        <div
            x-show="open"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">

            {{-- Header --}}
            <div class="bg-amber-500 px-6 py-4 flex items-center justify-between rounded-t-xl flex-shrink-0">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-white mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <h3 class="text-lg font-semibold text-white">Edit Skripsi</h3>
                </div>
                <button wire:click="closeEdit" class="text-white/80 hover:text-white transition">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Judul <span class="text-red-500">*</span></label>
                        <input wire:model="editTitle" type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                        @error('editTitle') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Penulis</label>
                            <input wire:model="editAuthor" type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
                            <input wire:model="editYear" type="number" min="2000" max="2030"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                            @error('editYear') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
                            <input wire:model="editType" type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ID Code</label>
                            <input wire:model="editIdCode" type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kata Kunci</label>
                        <input wire:model="editKeywords" type="text" placeholder="Pisahkan dengan koma"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subjects</label>
                            <input wire:model="editSubjects" type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Divisions</label>
                            <input wire:model="editDivisions" type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Abstrak</label>
                        <textarea wire:model="editAbstract" rows="4"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition resize-y"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kesimpulan</label>
                        <textarea wire:model="editConclusion" rows="4"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition resize-y"></textarea>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-3 flex justify-end items-center space-x-2 border-t rounded-b-xl flex-shrink-0">
                    <button type="button" wire:click="closeEdit"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-lg transition">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg transition flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
"""

with open(filepath, 'w', encoding='utf-8', newline='') as f:
    f.writelines(keep)
    f.write(new_modals)

lines2 = open(filepath, encoding='utf-8').readlines()
print('New total lines:', len(lines2))
print('Last 3 lines:')
for l in lines2[-3:]:
    print(repr(l))
