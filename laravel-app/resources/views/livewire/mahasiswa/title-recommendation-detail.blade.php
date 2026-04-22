@section('page-title', 'Detail Wawasan Rumpun Penelitian')

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Detail Wawasan Rumpun Penelitian</h1>
            <p class="mt-1 text-sm text-gray-500">Transformasikan konteks area penelusuran serta rekam jejak literatur menjadi perumusan ide luaran akademis melalui instruksi yang terukur.</p>
        </div>

        <a href="{{ route('mahasiswa.rekomendasi-judul.index') }}"
            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
            <x-app.icon name="arrow-left" class="mr-1.5 h-4 w-4" />
            Kembali ke Direktori Domain
        </a>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold text-gray-900">{{ $topicContext['topic_label'] ?? 'Topik' }}</h2>
            <span class="rounded-full bg-unsoed-blue-50 px-2.5 py-1 text-xs font-semibold text-unsoed-blue-700">
                {{ number_format((int) ($topicContext['doc_count'] ?? 0)) }} dokumen
            </span>
        </div>

        <div class="flex flex-wrap gap-1.5">
            @foreach (($topicContext['top_words'] ?? []) as $word)
                <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-medium text-gray-700">{{ $word }}</span>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Hasilkan Gagasan Topik Tesis</h2>
            <p class="text-xs text-gray-500">Instruksi akan divalidasi guna memastikan substansinya bersinggungan langsung pada orientasi bidang keilmuan bersangkutan.</p>
        </div>

        <form wire:submit.prevent="generateRecommendations" class="space-y-4">
            <div>
                <label for="prompt" class="mb-1 block text-sm font-semibold text-gray-700">Instruksi Deskriptif Khusus</label>
                <textarea id="prompt" rows="4" wire:model.defer="userPrompt"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                    placeholder="Saya berminat menginisiasi evaluasi terhadap ketepatan opini publik perihal isu regulasi transportasi..."></textarea>
                @error('userPrompt')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="w-full sm:w-48">
                <label for="recommendationsCount" class="mb-1 block text-sm font-semibold text-gray-700">Jumlah Target Ideasi</label>
                <select id="recommendationsCount" wire:model.defer="recommendationsCount"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500">
                    @for ($count = 3; $count <= 10; $count++)
                        <option value="{{ $count }}">{{ $count }} rintisan gagasan</option>
                    @endfor
                </select>
                @error('recommendationsCount')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-1">
                <x-ui.button type="submit" variant="primary"
                    wire:loading.attr="disabled"
                    wire:target="generateRecommendations">
                    <x-app.icon name="sparkles" class="mr-1.5 h-4 w-4" wire:loading.remove wire:target="generateRecommendations" />
                    <svg wire:loading wire:target="generateRecommendations" class="mr-1.5 h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a12 12 0 00-12 12h4z"></path>
                    </svg>
                    Sintesiskan Gagasan Riset
                </x-ui.button>
            </div>
        </form>

        @if (!empty($recommendationError))
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $recommendationError }}
            </div>
        @endif
    </div>

    @if (!empty($recommendationItems))
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Luaran Representasi Ide Riset</h2>
                <p class="text-xs text-gray-500">Silakan gunakan sebagai instrumen acuan awal, yang mana selanjutnya tetap wajib didiskusikan dengan dewan pembimbing.</p>
            </div>

            <div class="space-y-3">
                @foreach ($recommendationItems as $index => $item)
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="mb-1 flex items-start gap-2">
                            <span class="mt-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-unsoed-blue-700 px-1 text-[11px] font-semibold text-white">
                                {{ $index + 1 }}
                            </span>
                            <h3 class="text-sm font-semibold text-gray-900">{{ $item['title'] }}</h3>
                        </div>
                        @if (!empty($item['rationale']))
                            <p class="text-xs leading-relaxed text-gray-600">{{ $item['rationale'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-lg font-semibold text-gray-900">Distribusi Pustaka pada Rumpun Topik</h2>
            <span class="text-xs text-gray-500">Menampilkan {{ count($mappedSkripsi) }} literatur</span>
        </div>

        @if (!empty($mappedSkripsi))
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="w-full min-w-[840px] text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nomor Registrasi</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Gagasan Penelitan Terkait</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Penulis</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Tahun Tersimpan</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($mappedSkripsi as $doc)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2.5 text-xs text-gray-700">#{{ $doc['skripsi_id'] }}</td>
                                <td class="px-3 py-2.5 text-xs leading-relaxed text-gray-800">{{ $doc['title'] }}</td>
                                <td class="px-3 py-2.5 text-xs text-gray-700">{{ $doc['author'] ?? '-' }}</td>
                                <td class="px-3 py-2.5 text-center text-xs text-gray-700">{{ $doc['year'] ?? '-' }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    <button
                                        type="button"
                                        wire:click="showMappedSkripsiDetail({{ (int) ($doc['skripsi_id'] ?? 0) }})"
                                        class="inline-flex items-center rounded-lg border border-unsoed-blue-200 bg-unsoed-blue-50 px-2 py-0.5 text-[11px] font-semibold text-unsoed-blue-700 hover:bg-unsoed-blue-100"
                                    >
                                        <x-app.icon name="eye" class="mr-1 h-3.5 w-3.5" />
                                        Tinjau
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-4 text-sm text-gray-500">
                Belum terdapat artefak penelitian yang berkorelasi lurus pada rentang keilmuan ini.
            </div>
        @endif
    </div>

    <div x-data="{ open: @entangle('showDetailModal').live }" x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog"
        aria-modal="true">
        <div x-show="open" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.closeMappedSkripsiDetail()"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[88vh] flex flex-col overflow-hidden">

                <div class="bg-gradient-to-r from-unsoed-blue-700 to-unsoed-blue-600 px-6 py-4 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                            <x-app.icon variant="s" name="document-text" class="w-4 h-4 text-white" />
                        </div>
                        <h3 class="text-base font-bold text-white">Tinjauan Literatur Skripsi</h3>
                    </div>
                    <button wire:click="closeMappedSkripsiDetail"
                        class="w-8 h-8 bg-white/10 hover:bg-white/25 rounded-lg flex items-center justify-center text-white transition"
                        aria-label="Tutup detail skripsi">
                        <x-app.icon name="x-mark" class="w-4 h-4" />
                    </button>
                </div>

                <div class="px-6 py-5 overflow-y-auto flex-1 space-y-5">
                    <div>
                        <h4 class="text-base font-bold text-gray-900 leading-snug">
                            {{ $selectedSkripsi['title'] ?? '-' }}</h4>
                        <div class="flex flex-wrap gap-2 mt-2">
                            @if ($selectedSkripsi['year'] ?? null)
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-unsoed-blue-100 text-unsoed-blue-700">
                                    {{ $selectedSkripsi['year'] }}
                                </span>
                            @endif
                            @if ($selectedSkripsi['type'] ?? null)
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">
                                    {{ $selectedSkripsi['type'] }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        @foreach ([['Peneliti/Penulis', $selectedSkripsi['author'] ?? '-'], ['Nomor Identitas', $selectedSkripsi['id_code'] ?? '-'], ['Bidang Ilmu', $selectedSkripsi['divisions'] ?? '-'], ['Pokok Kajian', $selectedSkripsi['subjects'] ?? '-'], ['Tanggal Simpan', $selectedSkripsi['deposit_date'] ?? '-'], ['Pembaruan Data', $selectedSkripsi['modified_date'] ?? '-']] as [$label, $value])
                            <div class="bg-gray-50 rounded-xl p-3">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">
                                    {{ $label }}</p>
                                <p class="text-sm text-gray-800">{{ $value }}</p>
                            </div>
                        @endforeach
                    </div>

                    @if ($selectedSkripsi['keywords'] ?? null)
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Terminologi Kajian</p>
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
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Ikhtisar Riset</p>
                            <div
                                class="bg-gray-50 border border-gray-100 rounded-xl p-4 text-sm text-gray-700 leading-relaxed max-h-36 overflow-y-auto">
                                {{ $selectedSkripsi['abstract'] }}
                            </div>
                        </div>
                    @endif

                    @if ($selectedSkripsi['conclusion'] ?? null)
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">
                                Intisari Riset
                                @if ($selectedSkripsi['conclusion_source'] ?? null)
                                    <span
                                        class="text-gray-300 normal-case font-normal">({{ collect([
                                            'model' => 'Ekstrak Analitik',
                                            'scrape' => 'Ekstrak Dokumen'
                                        ])->get($selectedSkripsi['conclusion_source'], 'Tidak Teridentifikasi') }})</span>
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
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Manuskrip Skripsi</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach ($selectedSkripsi['pdf_documents'] as $docName => $docUrl)
                                    <a href="{{ $docUrl }}" target="_blank" rel="noopener"
                                        class="flex items-center px-3 py-2 bg-red-50 hover:bg-red-100 rounded-xl text-xs font-medium text-red-700 transition border border-red-100 gap-1.5">
                                        <x-app.icon name="document-text" class="w-4 h-4 flex-shrink-0" />
                                        {{ str_replace('_', ' ', $docName) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($selectedSkripsi['url'] ?? null)
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Pustaka Digital Institusi</p>
                            <a href="{{ $selectedSkripsi['url'] }}" target="_blank" rel="noopener"
                                class="text-sm text-unsoed-blue-600 hover:underline break-all inline-flex items-center gap-1">
                                {{ $selectedSkripsi['url'] }}
                                <x-app.icon name="arrow-top-right-on-square" class="w-3 h-3 flex-shrink-0" />
                            </a>
                        </div>
                    @endif
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end shrink-0 rounded-b-2xl">
                    <button wire:click="closeMappedSkripsiDetail"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-gray-200">
                        Tutup Jendela Modul
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
