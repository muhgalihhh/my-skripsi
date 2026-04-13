<div x-data="mahasiswaTopicDashboard(@js($chartPayload), @js($topicCards))" x-init="init()">
    @section('page-title', 'Dashboard Mahasiswa')

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Dashboard Mahasiswa</h1>
                <p class="mt-1 text-sm text-gray-500">Eksplorasi topik skripsi, wordcloud, tren tahunan, dan smart search.</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-400">Terakhir diperbarui</p>
                <p class="text-sm font-medium text-gray-600">{{ now()->format('d M Y, H:i') }}</p>
            </div>
        </div>

        @if ($activeRun)
            <div class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm sm:p-4">
                <div class="flex flex-wrap gap-2" role="tablist" aria-label="Navigasi dashboard mahasiswa">
                    <button type="button" role="tab" @click="setTab('wordcloud')"
                        :aria-selected="isTab('wordcloud')"
                        :class="isTab('wordcloud')
                            ? 'border-unsoed-blue-700 bg-unsoed-blue-700 text-white'
                            : 'border-gray-200 bg-white text-gray-600 hover:border-unsoed-blue-300 hover:text-unsoed-blue-700'"
                        class="rounded-lg border px-3 py-2 text-xs font-semibold uppercase tracking-wide transition">
                        Word Cloud
                    </button>

                    <button type="button" role="tab" @click="setTab('mapping')"
                        :aria-selected="isTab('mapping')"
                        :class="isTab('mapping')
                            ? 'border-unsoed-blue-700 bg-unsoed-blue-700 text-white'
                            : 'border-gray-200 bg-white text-gray-600 hover:border-unsoed-blue-300 hover:text-unsoed-blue-700'"
                        class="rounded-lg border px-3 py-2 text-xs font-semibold uppercase tracking-wide transition">
                        Mapping Skripsi
                    </button>

                    <button type="button" role="tab" @click="setTab('topics')"
                        :aria-selected="isTab('topics')"
                        :class="isTab('topics')
                            ? 'border-unsoed-blue-700 bg-unsoed-blue-700 text-white'
                            : 'border-gray-200 bg-white text-gray-600 hover:border-unsoed-blue-300 hover:text-unsoed-blue-700'"
                        class="rounded-lg border px-3 py-2 text-xs font-semibold uppercase tracking-wide transition">
                        Daftar Topik
                    </button>
                </div>


            </div>

            @if (($smartSearchResult['status'] ?? 'idle') !== 'idle')
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-3">
                        <h2 class="text-lg font-semibold text-gray-900">Hasil Smart Search</h2>
                        <p class="text-xs text-gray-500">Hasil pencarian dari input Smart Search di topbar.</p>
                    </div>

                    <div class="space-y-3">
                        @if (!empty($smartSearchResult['message']))
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                {{ $smartSearchResult['message'] }}
                            </div>
                        @endif

                        @if (!empty($smartSearchResult['predicted_topic']))
                            <div class="rounded-xl border border-unsoed-blue-200 bg-unsoed-blue-50 px-3.5 py-3 text-xs text-unsoed-blue-800">
                                <p class="font-semibold">Prediksi Topik Utama: {{ $smartSearchResult['predicted_topic']['topic_label'] ?? '-' }}</p>
                                <p class="mt-1">Similarity: {{ number_format((float) ($smartSearchResult['predicted_topic']['similarity'] ?? 0), 4) }}</p>
                                @if (!empty($smartSearchResult['predicted_topic']['top_words']))
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($smartSearchResult['predicted_topic']['top_words'] as $word)
                                            <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-[11px] font-medium text-unsoed-blue-700">{{ $word }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if (!empty($smartSearchResult['topic_distribution']))
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($smartSearchResult['topic_distribution'] as $topicCandidate)
                                    <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-medium text-gray-700">
                                        Topik {{ $topicCandidate['topic_id'] }} • {{ number_format((float) ($topicCandidate['similarity'] ?? 0), 3) }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        @if (!empty($smartSearchResult['items']))
                            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                                <table class="w-full min-w-[880px] text-sm">
                                    <thead class="border-b border-gray-200 bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Topik</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Judul</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Penulis</th>
                                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Tahun</th>
                                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Skor</th>
                                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($smartSearchResult['items'] as $item)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-2.5 align-top">
                                                    <span class="inline-flex items-center rounded-full bg-unsoed-blue-50 px-2 py-0.5 text-xs font-semibold text-unsoed-blue-700">
                                                        {{ $item['topic_label'] ?? 'Topik' }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2.5 align-top text-xs text-gray-700">
                                                    <div class="max-w-xl leading-relaxed">{{ $item['title'] ?? '-' }}</div>
                                                </td>
                                                <td class="px-3 py-2.5 align-top text-xs text-gray-700">{{ $item['author'] ?? '-' }}</td>
                                                <td class="px-3 py-2.5 text-center text-xs text-gray-700">{{ $item['year'] ?? '-' }}</td>
                                                <td class="px-3 py-2.5 text-right text-xs font-semibold text-gray-800">{{ number_format((float) ($item['score'] ?? 0), 4) }}</td>
                                                <td class="px-3 py-2.5 text-center">
                                                    @if (!empty($item['url']))
                                                        <a href="{{ $item['url'] }}" target="_blank" rel="noopener"
                                                            class="inline-flex items-center rounded-lg border border-unsoed-blue-200 bg-unsoed-blue-50 px-2.5 py-1 text-xs font-semibold text-unsoed-blue-700 hover:bg-unsoed-blue-100">
                                                            <x-app.icon name="arrow-top-right-on-square" class="mr-1 h-3.5 w-3.5" />
                                                            Buka Skripsi
                                                        </a>
                                                    @else
                                                        <span class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500">
                                                            Link tidak tersedia
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-4 text-sm text-gray-500">
                                Belum ada hasil pencarian. Coba gunakan query yang lebih spesifik.
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div x-show="isTab('topics')" x-cloak x-transition.opacity.duration.150ms class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-3">
                        <h2 class="text-lg font-semibold text-gray-900">Distribusi Dokumen per Topik</h2>
                        <p class="text-xs text-gray-500">Topik dengan jumlah dokumen terbanyak pada run aktif.</p>
                    </div>

                    @if (!empty($chartPayload['distribution']['labels']))
                        <div class="overflow-hidden rounded-xl border border-unsoed-blue-100 bg-unsoed-blue-50 p-3">
                            <canvas x-ref="distributionCanvas" class="h-[18rem] w-full max-w-full"></canvas>
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
                        <p class="text-xs text-gray-500">Proporsi topik per tahun berdasarkan mapping dokumen.</p>
                    </div>

                    @if (!empty($chartPayload['dtm']['series']))
                        <div class="overflow-hidden rounded-xl border border-unsoed-blue-100 bg-unsoed-blue-50 p-3">
                            <canvas x-ref="dtmCanvas" class="h-[18rem] w-full max-w-full"></canvas>
                        </div>
                    @else
                        <div class="flex h-[18rem] items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-sm text-gray-400">
                            {{ $chartPayload['dtm']['missing_reason'] ?? 'Data tren belum tersedia.' }}
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
                        <p class="text-xs text-gray-500">Visual kata dominan pada setiap topik dari run BERTopic aktif.</p>
                    </div>
                </div>

                @if (!empty($chartPayload['wordcloud_topics']))
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        @foreach ($chartPayload['wordcloud_topics'] as $topicCloud)
                            <div class="min-w-0 rounded-xl border border-gray-200 bg-white p-3">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <p class="truncate text-xs font-semibold text-gray-800" title="{{ $topicCloud['label'] }}">{{ $topicCloud['label'] }}</p>
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">{{ number_format((int) ($topicCloud['doc_count'] ?? 0)) }} dok</span>
                                </div>
                                <canvas
                                    data-topic-wordcloud-id="{{ $topicCloud['topic_id'] }}"
                                    data-wordcloud-index="{{ $loop->index }}"
                                    width="520"
                                    height="220"
                                    class="h-40 w-full !transform-none sm:h-48"></canvas>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex h-[18rem] flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-gray-400">
                        <x-app.icon name="cloud" class="mb-3 h-12 w-12 text-gray-200" />
                        <p class="text-sm font-medium">Data wordcloud belum tersedia.</p>
                        <p class="mt-1 text-xs">Pastikan topik memiliki daftar kata kunci (top words).</p>
                    </div>
                @endif
            </div>

            <div x-show="isTab('mapping')" x-cloak x-transition.opacity.duration.150ms class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Mapping Skripsi per Topik</h2>
                    <p class="text-xs text-gray-500">Daftar topik beserta tombol untuk membuka detail list skripsi di modal.</p>
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
                        Data mapping topik belum tersedia pada run ini.
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
                                        <h3 class="text-base font-bold text-white" x-text="mappingModalTopic?.topic_label ?? 'Detail Mapping Topik'"></h3>
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
                                    Belum ada dokumen yang ter-mapping untuk topik ini.
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

            <div x-show="isTab('topics')" x-cloak x-transition.opacity.duration.150ms class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Daftar Topik</h2>
                    <p class="text-xs text-gray-500">Ringkasan topik berdasarkan jumlah dokumen.</p>
                </div>

                @if ($topicList->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-gray-200 bg-unsoed-blue-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-unsoed-blue-700">Topik</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-unsoed-blue-700">Jumlah</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-unsoed-blue-700">Kata Kunci</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($topicList as $topic)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 align-top text-sm font-semibold text-gray-800">
                                            @if (filled($topic->custom_name))
                                                T{{ $topic->topic_id }} - {{ $topic->custom_name }}
                                            @else
                                                Topik {{ $topic->topic_id }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right align-top text-sm font-semibold text-gray-800">{{ number_format((int) $topic->count) }}</td>
                                        <td class="px-4 py-3 align-top">
                                            <div class="flex max-w-xl flex-wrap gap-1.5">
                                                @foreach (array_slice($topic->top_words ?? [], 0, 15) as $word)
                                                    <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2 py-0.5 text-xs font-medium text-gray-700">{{ $word }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center text-sm text-gray-400">
                        Belum ada topik untuk run ini.
                    </div>
                @endif
            </div>
        @else
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-5 text-sm text-amber-800 shadow-sm sm:px-5">
                Belum ada run BERTopic completed yang bisa ditampilkan untuk mahasiswa.
            </div>
        @endif
    </div>

    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-chart-wordcloud@4.4.4/build/index.umd.min.js"></script>

        <script>
            function registerMahasiswaTopicDashboard() {
                if (!window.Alpine) {
                    return;
                }

                window.Alpine.data('mahasiswaTopicDashboard', (payload, mappingTopics = []) => ({
                    payload,
                    distributionChart: null,
                    dtmChart: null,
                    currentTab: 'topics',
                    mappingTopics: Array.isArray(mappingTopics) ? mappingTopics : [],
                    mappingModalOpen: false,
                    mappingModalTopic: null,
                    wordCloudCharts: new Map(),
                    wordCloudRenderSignatures: new Map(),
                    wordCloudWordLimit: 10,

                    init() {
                        this.$nextTick(() => {
                            requestAnimationFrame(() => {
                                this.renderDistributionChart();
                                this.renderDtmChart();
                            });
                        });
                    },

                    isTab(tabName) {
                        return this.currentTab === tabName;
                    },

                    setTab(tabName) {
                        this.currentTab = tabName;

                        if (tabName === 'wordcloud') {
                            this.$nextTick(() => {
                                requestAnimationFrame(() => {
                                    this.renderWordCloudTopics();
                                });
                            });
                            return;
                        }

                        if (tabName === 'topics') {
                            this.$nextTick(() => {
                                requestAnimationFrame(() => {
                                    this.renderDistributionChart();
                                    this.renderDtmChart();
                                });
                            });
                        }
                    },

                    openMappingModal(index) {
                        const topic = this.mappingTopics[index] ?? null;
                        if (!topic) {
                            return;
                        }

                        this.mappingModalTopic = topic;
                        this.mappingModalOpen = true;
                    },

                    closeMappingModal() {
                        this.mappingModalOpen = false;
                    },

                    getWordCloudTopics() {
                        return Array.isArray(this.payload?.wordcloud_topics) ? this.payload.wordcloud_topics : [];
                    },

                    supportsWordCloudType() {
                        if (typeof window.Chart === 'undefined') {
                            return false;
                        }

                        try {
                            return Boolean(window.Chart.registry?.getController?.('wordCloud'));
                        } catch (error) {
                            return false;
                        }
                    },

                    getWordCloudCanvasByIndex(index) {
                        if (!this.$root || typeof this.$root.querySelector !== 'function') {
                            return null;
                        }

                        return this.$root.querySelector(`canvas[data-wordcloud-index="${index}"]`);
                    },

                    getWordCloudColorPool() {
                        return ['#1d4ed8', '#0f766e', '#15803d', '#9333ea', '#b45309', '#0369a1', '#be123c', '#334155'];
                    },

                    buildWordCloudSignature(topic, labels, rawWeights) {
                        const topicId = topic?.topic_id ?? '';
                        const wordsSignature = labels
                            .map((label, idx) => `${label}:${Number(rawWeights[idx] ?? 0).toFixed(8)}`)
                            .join('|');

                        return `${topicId}::${wordsSignature}`;
                    },

                    destroyWordCloudChart(index) {
                        const chart = this.wordCloudCharts.get(index);
                        if (chart) {
                            chart.destroy();
                        }
                        this.wordCloudCharts.delete(index);
                        this.wordCloudRenderSignatures.delete(index);
                    },

                    destroyStaleWordCloudCharts(visibleIndexes) {
                        const staleIndexes = [];
                        this.wordCloudCharts.forEach((_, index) => {
                            if (!visibleIndexes.has(index)) {
                                staleIndexes.push(index);
                            }
                        });

                        staleIndexes.forEach((index) => {
                            this.destroyWordCloudChart(index);
                        });
                    },

                    renderWordCloudTopics() {
                        const topics = this.getWordCloudTopics();
                        const visibleIndexes = new Set();

                        if (!topics.length || typeof window.Chart === 'undefined' || !this.supportsWordCloudType()) {
                            this.destroyStaleWordCloudCharts(visibleIndexes);
                            return;
                        }

                        const colorPool = this.getWordCloudColorPool();

                        topics.forEach((topic, index) => {
                            visibleIndexes.add(index);

                            const canvas = this.getWordCloudCanvasByIndex(index);
                            if (!(canvas instanceof HTMLCanvasElement) || !canvas.isConnected) {
                                return;
                            }

                            const ctx = canvas.getContext('2d');
                            if (!ctx) {
                                return;
                            }

                            const words = Array.isArray(topic?.words) ? topic.words : [];
                            const limitedWords = words
                                .map((word) => {
                                    const text = typeof word?.text === 'string' ? word.text.trim() : '';
                                    const rawWeight = Number.parseFloat(word?.raw_weight);
                                    const visualWeight = Number.parseFloat(word?.weight);
                                    return {
                                        text,
                                        rawWeight: Number.isFinite(rawWeight) && rawWeight > 0
                                            ? rawWeight
                                            : Number.isFinite(visualWeight) && visualWeight > 0
                                                ? visualWeight
                                                : 0,
                                        visualWeight: Number.isFinite(visualWeight) && visualWeight > 0 ? visualWeight : 0,
                                    };
                                })
                                .filter((item) => item.text !== '' && item.rawWeight > 0)
                                .slice(0, this.wordCloudWordLimit);

                            if (!limitedWords.length) {
                                this.destroyWordCloudChart(index);
                                return;
                            }

                            const labels = limitedWords.map((item) => item.text);
                            const rawWeights = limitedWords.map((item) => item.rawWeight);
                            const minRaw = Math.min(...rawWeights);
                            const maxRaw = Math.max(...rawWeights);
                            const denominator = (maxRaw - minRaw) || 1;
                            const values = limitedWords.map((item) => {
                                const ratio = (item.rawWeight - minRaw) / denominator;
                                const scaled = 11 + (Math.pow(ratio, 1.1) * 22);
                                return Math.max(11, Math.round(scaled));
                            });
                            const colors = labels.map((_, wordIndex) => colorPool[wordIndex % colorPool.length]);

                            const signature = this.buildWordCloudSignature(topic, labels, rawWeights);
                            const existingSignature = this.wordCloudRenderSignatures.get(index);
                            const existingChart = this.wordCloudCharts.get(index);
                            if (existingChart && existingSignature === signature) {
                                return;
                            }

                            const rawWeightByWord = new Map(limitedWords.map((item) => [item.text, item.rawWeight]));

                            this.destroyWordCloudChart(index);

                            const chart = new window.Chart(ctx, {
                                type: 'wordCloud',
                                data: {
                                    labels,
                                    datasets: [{
                                        label: topic?.label ?? `Topik ${topic?.topic_id ?? index + 1}`,
                                        data: values,
                                        color: colors,
                                        fit: false,
                                        minRotation: 0,
                                        maxRotation: 0,
                                        rotationSteps: 1,
                                        padding: 3,
                                        autoGrow: {
                                            maxTries: 0,
                                            scalingFactor: 1,
                                        },
                                    }],
                                },
                                options: {
                                    animation: false,
                                    responsive: false,
                                    maintainAspectRatio: false,
                                    layout: {
                                        padding: 10,
                                    },
                                    plugins: {
                                        legend: {
                                            display: false,
                                        },
                                        tooltip: {
                                            callbacks: {
                                                title: () => topic?.label ?? `Topik ${topic?.topic_id ?? index + 1}`,
                                                label: (context) => {
                                                    const idx = Number(context?.dataIndex ?? -1);
                                                    const word = idx >= 0 ? labels[idx] : '';
                                                    if (!word) {
                                                        return 'Bobot: -';
                                                    }
                                                    const rawWeight = rawWeightByWord.get(word);
                                                    if (!Number.isFinite(rawWeight)) {
                                                        return `${word}: bobot -`;
                                                    }
                                                    return `${word}: bobot ${Number(rawWeight).toFixed(4)}`;
                                                },
                                            },
                                        },
                                    },
                                },
                            });

                            this.wordCloudCharts.set(index, chart);
                            this.wordCloudRenderSignatures.set(index, signature);
                        });

                        this.destroyStaleWordCloudCharts(visibleIndexes);
                    },

                    renderDistributionChart() {
                        const canvas = this.$refs.distributionCanvas;
                        const data = this.payload?.distribution ?? {};
                        const labels = Array.isArray(data.labels) ? data.labels : [];
                        const counts = Array.isArray(data.counts) ? data.counts : [];

                        if (!canvas || labels.length === 0 || counts.length === 0 || typeof window.Chart === 'undefined') {
                            return;
                        }

                        const ctx = canvas.getContext('2d');
                        if (!ctx) {
                            return;
                        }

                        if (this.distributionChart) {
                            this.distributionChart.destroy();
                        }

                        this.distributionChart = new window.Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels,
                                datasets: [{
                                    label: 'Jumlah Dokumen',
                                    data: counts,
                                    borderRadius: 6,
                                    borderSkipped: false,
                                    backgroundColor: '#2563eb',
                                    hoverBackgroundColor: '#1d4ed8',
                                }],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: false,
                                plugins: {
                                    legend: {
                                        display: false,
                                    },
                                },
                                scales: {
                                    x: {
                                        ticks: {
                                            color: '#334155',
                                            maxRotation: 0,
                                            autoSkip: true,
                                        },
                                        grid: {
                                            color: 'rgba(148, 163, 184, 0.16)',
                                            drawBorder: false,
                                        },
                                    },
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            color: '#334155',
                                        },
                                        grid: {
                                            color: 'rgba(148, 163, 184, 0.16)',
                                            drawBorder: false,
                                        },
                                    },
                                },
                            },
                        });
                    },

                    renderDtmChart() {
                        const canvas = this.$refs.dtmCanvas;
                        const dtm = this.payload?.dtm ?? {};
                        const years = Array.isArray(dtm.years) ? dtm.years : [];
                        const series = Array.isArray(dtm.series) ? dtm.series : [];

                        if (!canvas || years.length === 0 || series.length === 0 || typeof window.Chart === 'undefined') {
                            return;
                        }

                        const ctx = canvas.getContext('2d');
                        if (!ctx) {
                            return;
                        }

                        if (this.dtmChart) {
                            this.dtmChart.destroy();
                        }

                        const palette = ['#2563eb', '#06b6d4', '#22c55e', '#f59e0b', '#a855f7', '#ef4444', '#0ea5e9', '#14b8a6'];

                        this.dtmChart = new window.Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: years,
                                datasets: series.map((entry, index) => ({
                                    label: entry.label,
                                    data: entry.data,
                                    borderColor: palette[index % palette.length],
                                    backgroundColor: `${palette[index % palette.length]}22`,
                                    fill: true,
                                    tension: 0.28,
                                    pointRadius: 0,
                                    pointHoverRadius: 4,
                                    borderWidth: 2.2,
                                })),
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: false,
                                interaction: {
                                    mode: 'index',
                                    intersect: false,
                                },
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: {
                                            boxWidth: 12,
                                            color: '#334155',
                                            font: {
                                                size: 11,
                                                weight: '600',
                                            },
                                        },
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: (context) => `${context.dataset.label}: ${Number(context.parsed.y).toFixed(2)}%`,
                                        },
                                    },
                                },
                                scales: {
                                    x: {
                                        ticks: {
                                            color: '#334155',
                                            maxRotation: 0,
                                            autoSkip: true,
                                        },
                                        grid: {
                                            color: 'rgba(148, 163, 184, 0.16)',
                                            drawBorder: false,
                                        },
                                    },
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            color: '#334155',
                                            callback: (value) => `${Number(value).toFixed(0)}%`,
                                        },
                                        grid: {
                                            color: 'rgba(148, 163, 184, 0.16)',
                                            drawBorder: false,
                                        },
                                    },
                                },
                            },
                        });
                    },
                }));
            }

            registerMahasiswaTopicDashboard();
            document.addEventListener('alpine:init', registerMahasiswaTopicDashboard, {
                once: true,
            });
        </script>
    @endonce
</div>
