<div wire:key="topic-explorer" class="space-y-6" x-data="{
    activeVizTab: 'wordcloud',
    isTab(tab) { return this.activeVizTab === tab; },
    setVizTab(tab) { this.activeVizTab = tab; },
    mappingTopics: @js($topicCards ?? []),
    mappingModalOpen: false,
    mappingModalTopic: null,
    openMappingModal(index) {
        const topic = this.mappingTopics[index] ?? null;
        if (!topic) return;
        this.mappingModalTopic = topic;
        this.mappingModalOpen = true;
        document.body.style.overflow = 'hidden';
    },
    closeMappingModal() {
        this.mappingModalOpen = false;
        document.body.style.overflow = '';
    }
}">
    @section('page-title', 'Eksplorasi Ruang Lingkup Riset')

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Eksplorasi Ruang Lingkup Riset</h1>
                <p class="mt-1 text-sm text-gray-500">Visualisasi data untuk membantu menemukan arah kebaruan penelitian.</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-400">Terakhir diperbarui</p>
                <p class="text-sm font-medium text-gray-600">{{ now()->format('d M Y, H:i') }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="mb-3">
                <h2 class="text-lg font-semibold text-gray-900">Cek Kedekatan Topik</h2>
                <p class="text-xs text-gray-500">Masukkan rencana judul dan abstrak riset Anda untuk melihat prediksi klaster kemiripan topik.</p>
            </div>

            <form wire:submit.prevent="checkReferenceCluster" class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Judul</label>
                    <input
                        type="text"
                        wire:model.defer="referenceTitle"
                        maxlength="350"
                        placeholder="Contoh: Analisis Sentimen ..."
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Abstrak</label>
                    <textarea
                        wire:model.defer="referenceAbstract"
                        rows="5"
                        maxlength="4650"
                        placeholder="Tuliskan gambaran ringkas riset Anda..."
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                    ></textarea>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg bg-unsoed-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-unsoed-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
                        wire:loading.attr="disabled"
                        wire:target="checkReferenceCluster"
                        @disabled(!$activeRun)
                    >
                        <x-app.icon name="magnifying-glass" class="mr-1.5 h-4 w-4" />
                        <span wire:loading.remove wire:target="checkReferenceCluster">Cek Topik</span>
                        <span wire:loading wire:target="checkReferenceCluster">Memproses…</span>
                    </button>

                    <button
                        type="button"
                        wire:click="resetClusterCheckForm"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                    >
                        Reset
                    </button>
                </div>
            </form>

            @if (!$activeRun)
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                    Model mesin analitik belum berjalan. Fitur ini memerlukan aktivasi dari pengelola.
                </div>
            @endif

            @if (($clusterCheckResult['status'] ?? 'idle') !== 'idle')
                <div class="mt-4 space-y-3">
                    @if (!empty($clusterCheckResult['message']))
                        <div @class([
                            'rounded-xl border px-3 py-2 text-xs',
                            'border-red-200 bg-red-50 text-red-700' => ($clusterCheckResult['status'] ?? '') === 'error',
                            'border-amber-200 bg-amber-50 text-amber-700' => ($clusterCheckResult['status'] ?? '') === 'warning',
                            'border-green-200 bg-green-50 text-green-700' => ($clusterCheckResult['status'] ?? '') === 'ok',
                        ])>
                            {{ $clusterCheckResult['message'] }}
                        </div>
                    @endif

                    @if (!empty($clusterCheckResult['predicted_topic']))
                        <div class="rounded-xl border border-unsoed-blue-200 bg-unsoed-blue-50 px-3.5 py-3 text-xs text-unsoed-blue-800">
                            <p class="font-semibold">Klaster Prediksi: {{ $clusterCheckResult['predicted_topic']['topic_label'] ?? '-' }}</p>
                            <p class="mt-1">Kemiripan: {{ number_format((float) ($clusterCheckResult['predicted_topic']['similarity'] ?? 0), 4) }}</p>
                            @if (!empty($clusterCheckResult['predicted_topic']['top_words']))
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($clusterCheckResult['predicted_topic']['top_words'] as $word)
                                        <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-[11px] font-medium text-unsoed-blue-700">{{ $word }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    @if (!empty($clusterCheckResult['topic_distribution']))
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($clusterCheckResult['topic_distribution'] as $topicCandidate)
                                <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-medium text-gray-700">
                                    {{ $topicCandidate['topic_label'] ?? ('Topik ' . ($topicCandidate['topic_id'] ?? '-')) }}
                                    • {{ number_format((float) ($topicCandidate['similarity'] ?? 0), 3) }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        @if ($activeRun)
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Topik Terdeteksi</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format((int) ($summaryStats['topic_count'] ?? 0)) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Jumlah pengelompokan topik pada model saat ini.</p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Skripsi Diproses</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format((int) ($summaryStats['mapped_document_count'] ?? 0)) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Jumlah literatur yang berhasil dikelompokkan ke dalam topik-topik tersebut.</p>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm sm:p-4">
                <div class="flex flex-wrap gap-2" role="tablist" aria-label="Navigasi dashboard mahasiswa">
                    <button type="button" role="tab" @click="setVizTab('wordcloud')"
                        :aria-selected="isTab('wordcloud')"
                        :class="isTab('wordcloud')
                            ? 'border-unsoed-blue-700 bg-unsoed-blue-700 text-white'
                            : 'border-gray-200 bg-white text-gray-600 hover:border-unsoed-blue-300 hover:text-unsoed-blue-700'"
                        class="rounded-lg border px-3 py-2 text-xs font-semibold uppercase tracking-wide transition">
                        Word Cloud
                    </button>

                    <button type="button" role="tab" @click="setVizTab('mapping')"
                        :aria-selected="isTab('mapping')"
                        :class="isTab('mapping')
                            ? 'border-unsoed-blue-700 bg-unsoed-blue-700 text-white'
                            : 'border-gray-200 bg-white text-gray-600 hover:border-unsoed-blue-300 hover:text-unsoed-blue-700'"
                        class="rounded-lg border px-3 py-2 text-xs font-semibold uppercase tracking-wide transition">
                        Pemetaan Skripsi
                    </button>

                    <button type="button" role="tab" @click="setVizTab('topics')"
                        :aria-selected="isTab('topics')"
                        :class="isTab('topics')
                            ? 'border-unsoed-blue-700 bg-unsoed-blue-700 text-white'
                            : 'border-gray-200 bg-white text-gray-600 hover:border-unsoed-blue-300 hover:text-unsoed-blue-700'"
                        class="rounded-lg border px-3 py-2 text-xs font-semibold uppercase tracking-wide transition">
                        Daftar Topik
                    </button>
                </div>
            </div>
            
            <div x-show="isTab('topics')" x-cloak x-transition.opacity.duration.150ms class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-3">
                        <h2 class="text-lg font-semibold text-gray-900">Distribusi Dokumen per Topik</h2>
                        <p class="text-xs text-gray-500">Topik dengan jumlah dokumen terbanyak pada run aktif.</p>
                    </div>
                    @if (!empty($chartPayload['distribution']))
                        <div class="rounded-xl border border-unsoed-blue-100 bg-unsoed-blue-50 p-4 shadow-sm">
                            <x-charts.bar type="distribution" :chart-data="$chartPayload['distribution']" title="Distribusi Dokumen per Topik" height="h-[18rem] sm:h-[22rem]" />
                        </div>
                    @else
                        <div class="flex h-[18rem] items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-sm text-gray-400">
                            Data distribusi belum tersedia.
                        </div>
                    @endif
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-3">
                        <h2 class="text-lg font-semibold text-gray-900">Tren Topik per Tahun</h2>
                        <p class="text-xs text-gray-500">Proporsi topik per tahun berdasarkan pemetaan dokumen.</p>
                    </div>
                    @if (!empty($chartPayload['dtm']['series']))
                        <div class="rounded-xl border border-unsoed-blue-100 bg-unsoed-blue-50 p-4 shadow-sm">
                            <x-charts.line :chart-data="$chartPayload['dtm']" height="h-[20rem] sm:h-[28rem] xl:h-[34rem]" />
                        </div>
                    @else
                        <div class="flex h-[18rem] items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-sm text-gray-400">
                            {{ $chartPayload['dtm']['missing_reason'] ?? 'Data tren belum tersedia.' }}
                        </div>
                    @endif
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5 xl:col-span-2">
                    <div class="mb-3">
                        <h2 class="text-lg font-semibold text-gray-900">Dynamic Topic Analysis (DTA)</h2>
                        <p class="text-xs text-gray-500">Bar ke kanan menandakan topik menguat (Emerging), ke kiri melemah (Declining).</p>
                    </div>
                    @if (!empty($chartPayload['trend']))
                        <div class="rounded-xl border border-unsoed-blue-100 bg-unsoed-blue-50 p-4 shadow-sm">
                            <x-charts.bar type="trend" :chart-data="$chartPayload['trend']" height="h-[24rem] sm:h-[30rem]" />
                        </div>
                    @else
                        <div class="flex h-[18rem] flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-gray-400">
                            <x-app.icon name="arrows-right-left" class="mb-3 h-12 w-12 text-gray-200" />
                            <p class="text-sm font-medium">Belum ada data trend topik.</p>
                            <p class="mt-1 text-xs">Pastikan data tren DTM tersedia minimal di beberapa tahun.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div x-show="isTab('wordcloud')" x-cloak x-transition.opacity.duration.150ms class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="rounded-lg bg-unsoed-blue-100 p-2">
                        <x-app.icon name="cloud" class="h-4 w-4 text-unsoed-blue-700" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Word Cloud Per Topik</h2>
                        <p class="text-xs text-gray-500">Setiap cloud merepresentasikan satu topik. Hover kata untuk melihat bobot.</p>
                    </div>
                </div>

                @if (!empty($chartPayload['wordcloud_topics']))
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($chartPayload['wordcloud_topics'] as $topicCloud)
                            <x-charts.wordcloud 
                                :topic-id="$topicCloud['topic_id'] ?? null"
                                :label="$topicCloud['label'] ?? ''"
                                :doc-count="$topicCloud['doc_count'] ?? 0"
                                :words="$topicCloud['words'] ?? []"
                            />
                        @endforeach
                    </div>
                @else
                    <div class="flex h-[18rem] flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-gray-400 sm:h-[22rem]">
                        <x-app.icon name="cloud" class="mb-3 h-12 w-12 text-gray-200" />
                        <p class="text-sm font-medium">Data word cloud per topik belum tersedia.</p>
                        <p class="mt-1 text-xs">Pastikan run memiliki daftar top_words pada topik.</p>
                    </div>
                @endif
            </div>

            <div x-show="isTab('mapping')" x-cloak x-transition.opacity.duration.150ms class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Pemetaan Skripsi per Topik</h2>
                    <p class="text-xs text-gray-500">Daftar topik beserta tombol untuk membuka daftar detail skripsi di modal.</p>
                </div>

                @if (!empty($topicCards))
                    <div class="overflow-hidden rounded-xl border border-gray-200">
                        <table class="w-full text-sm">
                            <thead class="border-b border-gray-200 bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Topik</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Jumlah Dokumen</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kata Kunci</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Detail</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($topicCards as $topicCard)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2.5 align-top text-sm font-semibold text-gray-800">{{ $topicCard['topic_label'] }}</td>
                                        <td class="px-3 py-2.5 align-top text-right text-sm font-semibold text-gray-700">{{ number_format((int) ($topicCard['doc_count'] ?? 0)) }}</td>
                                        <td class="px-3 py-2.5 align-top">
                                            <div class="flex max-w-xl flex-wrap gap-1.5">
                                                @foreach (array_slice($topicCard['top_words'] ?? [], 0, 15) as $word)
                                                    <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-medium text-gray-700">{{ $word }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 align-top text-center">
                                            <button type="button"
                                                @click="openMappingModal({{ $loop->index }})"
                                                class="inline-flex items-center rounded-lg border border-unsoed-blue-200 bg-unsoed-blue-50 px-2.5 py-1 text-xs font-semibold text-unsoed-blue-700 hover:bg-unsoed-blue-100">
                                                <x-app.icon name="list-bullet" class="mr-1 h-3.5 w-3.5" />
                                                Detail Skripsi
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-4 text-sm text-gray-500">
                        Data pemetaan topik belum tersedia pada run ini.
                    </div>
                @endif
            </div>

            <div x-show="mappingModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" @keydown.escape.window="closeMappingModal()">
                <div x-show="mappingModalOpen" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeMappingModal()"></div>

                <div class="flex min-h-full items-center justify-center p-4">
                    <div x-show="mappingModalOpen" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95" class="relative w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl"
                        @click.stop>
                        <div class="bg-gradient-to-r from-unsoed-blue-700 to-unsoed-blue-600 px-4 py-4 sm:px-6">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/20">
                                            <x-app.icon variant="s" name="book-open" class="h-4 w-4 text-white" />
                                        </div>
                                        <h3 class="text-base font-bold text-white" x-text="mappingModalTopic?.topic_label ?? 'Detail Pemetaan Topik'"></h3>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1.5" x-show="Array.isArray(mappingModalTopic?.top_words) && mappingModalTopic.top_words.length > 0">
                                        <template x-for="word in (mappingModalTopic?.top_words ?? []).slice(0, 15)" :key="word">
                                            <span class="rounded-full bg-white/15 px-2 py-0.5 text-xs font-medium text-white" x-text="word"></span>
                                        </template>
                                    </div>
                                </div>
                                <x-ui.button variant="ghost" size="icon" type="button" x-on:click="closeMappingModal()"
                                    class="!bg-white/10 !text-white hover:!bg-white/25"
                                    aria-label="Tutup modal">
                                    <x-app.icon name="x-mark" class="h-4 w-4" />
                                </x-ui.button>
                            </div>
                        </div>

                        <div class="max-h-[70vh] overflow-auto px-4 py-4 sm:px-6 sm:py-5">
                            <template x-if="!mappingModalTopic || !Array.isArray(mappingModalTopic.documents) || mappingModalTopic.documents.length === 0">
                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-500">
                                    Belum ada dokumen yang termapping untuk topik ini.
                                </div>
                            </template>

                            <template x-if="mappingModalTopic && Array.isArray(mappingModalTopic.documents) && mappingModalTopic.documents.length > 0">
                                <div>
                                    <div class="mb-3 text-xs text-gray-500">Total dokumen: <span class="font-semibold text-gray-700" x-text="mappingModalTopic.documents.length"></span></div>
                                    <div class="overflow-hidden rounded-xl border border-gray-200">
                                        <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="w-24 px-2.5 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-3">ID</th>
                                                    <th class="w-20 px-2.5 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-3">Tahun</th>
                                                    <th class="px-2.5 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-3">Judul Skripsi</th>
                                                    <th class="w-24 px-2.5 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-3">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 bg-white">
                                                <template x-for="doc in mappingModalTopic.documents" :key="doc.skripsi_id + '-' + (doc.url || '')">
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-2.5 py-2 text-gray-700 sm:px-3" x-text="'#' + (doc.skripsi_id ?? '-')"></td>
                                                        <td class="px-2.5 py-2 text-gray-600 sm:px-3" x-text="doc.year ?? '-'"></td>
                                                        <td class="px-2.5 py-2 text-gray-800 sm:px-3" x-text="doc.title ?? '-'"></td>
                                                        <td class="px-2.5 py-2 text-center sm:px-3">
                                                            <template x-if="doc.url">
                                                                <a :href="doc.url" target="_blank" rel="noopener"
                                                                    class="inline-flex items-center rounded-md border border-unsoed-blue-200 bg-unsoed-blue-50 px-2 py-0.5 text-[11px] font-semibold text-unsoed-blue-700 hover:bg-unsoed-blue-100">
                                                                    <x-app.icon name="link" class="mr-1 h-3.5 w-3.5" />
                                                                    Link
                                                                </a>
                                                            </template>
                                                            <template x-if="!doc.url">
                                                                <span class="text-[11px] text-gray-400">-</span>
                                                            </template>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-5 text-sm text-amber-800 shadow-sm sm:px-5">
                Belum ada run BERTopic selesai yang bisa ditampilkan untuk mahasiswa.
            </div>
        @endif
    </div>

    @once
    @endonce
</div>
