<div wire:key="visualisasi-{{ $activeRun?->id ?? 'none' }}" x-data="topicVisualizationPage(@js($chartPayload), @js($skripsiMapping))" x-init="init()">
    @section('page-title', 'Visualisasi Topik')

    <div class="space-y-5 sm:space-y-6">
        {{-- Hero Header --}}
        <div class="rounded-2xl border border-unsoed-blue-700 bg-gradient-to-br from-unsoed-blue-800 via-unsoed-blue-700 to-unsoed-blue-600 p-5 text-white shadow-sm sm:p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-unsoed-gold-200">
                        Insight Visualisasi
                    </span>
                    <h1 class="mt-2 text-2xl font-bold">Visualisasi Topic Modeling</h1>
                    <p class="mt-1.5 text-sm text-unsoed-blue-100">
                        Eksplorasi hasil topik dari satu halaman: Word Cloud per topik, Dynamic Topic Modelling, serta perbandingan Emerging dan Declining.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2 text-[11px] font-medium">
                        <span class="rounded-full border border-white/20 bg-white/10 px-2.5 py-1">Word Cloud per Topik</span>
                        <span class="rounded-full border border-white/20 bg-white/10 px-2.5 py-1">Tooltip Interaktif</span>
                        <span class="rounded-full border border-white/20 bg-white/10 px-2.5 py-1">DTM per Tahun</span>
                    </div>
                </div>

                <div class="w-full rounded-xl border border-white/20 bg-white/10 p-3.5 text-left backdrop-blur-sm sm:max-w-xs lg:text-right">
                    <p class="text-[11px] uppercase tracking-wide text-unsoed-blue-100">Data terakhir</p>
                    <p class="mt-0.5 text-sm font-semibold text-white">{{ now()->format('d M Y, H:i') }}</p>
                    @if ($activeRun)
                        <p class="mt-2 text-xs text-unsoed-blue-100">Run aktif #{{ $activeRun->id }} • {{ strtoupper($activeRun->model_type ?? '-') }}</p>
                    @else
                        <p class="mt-2 text-xs text-unsoed-blue-100">Belum ada run aktif</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Filter Run --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="grid grid-cols-1 items-end gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Pilih Run Training</label>
                    <select wire:model.live="runFilter"
                        class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500">
                        @if ($completedRuns->isEmpty())
                            <option value="">Belum ada run selesai</option>
                        @else
                            @foreach ($completedRuns as $run)
                                <option value="{{ $run->id }}">
                                    Run #{{ $run->id }} - {{ strtoupper($run->model_type ?? '-') }}
                                    | {{ $run->num_topics ?? 0 }} topik
                                    @if ($run->completed_at)
                                        | {{ $run->completed_at->format('d/m/Y H:i') }}
                                    @endif
                                </option>
                            @endforeach
                        @endif
                    </select>
                    <div class="mt-2 flex flex-wrap gap-2 text-[11px] text-gray-500">
                        <span class="inline-flex items-center rounded-full border border-unsoed-blue-200 bg-unsoed-blue-50 px-2.5 py-1 text-unsoed-blue-700">
                            {{ $completedRuns->count() }} run completed
                        </span>
                        @if ($activeRun)
                            <span class="inline-flex items-center rounded-full border border-unsoed-gold-200 bg-unsoed-gold-50 px-2.5 py-1 text-unsoed-gold-700">
                                Model {{ strtoupper($activeRun->model_type ?? '-') }}
                            </span>
                        @endif
                    </div>
                </div>

                <div>
                    @if ($activeRun)
                        <div class="rounded-xl border border-unsoed-blue-200 bg-gradient-to-br from-unsoed-blue-50 to-white px-3.5 py-3 text-xs text-unsoed-blue-800">
                            <div class="font-semibold">Run aktif #{{ $activeRun->id }} ({{ strtoupper($activeRun->model_type ?? '-') }})</div>
                            <div class="mt-1">Coherence: {{ number_format((float) ($activeRun->coherence_cv ?? 0), 4) }}</div>
                            <div>Diversity: {{ number_format((float) ($activeRun->topic_diversity ?? 0), 4) }}</div>
                        </div>
                    @else
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-xs text-amber-700">
                            Belum ada run training dengan status completed.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($activeRun)
            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-unsoed-blue-100 bg-gradient-to-b from-unsoed-blue-50 to-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-unsoed-blue-700">Total Topik</p>
                            <p class="mt-1 text-3xl font-bold text-unsoed-blue-900">{{ number_format($topicList->count()) }}</p>
                        </div>
                        <div class="rounded-xl bg-unsoed-blue-100 p-2.5">
                            <x-app.icon name="list-bullet" class="h-5 w-5 text-unsoed-blue-700" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-unsoed-blue-700">Topik valid yang siap dieksplorasi</p>
                </div>

                <div class="rounded-2xl border border-emerald-100 bg-gradient-to-b from-emerald-50 to-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-emerald-700">Dokumen Terpetakan</p>
                            <p class="mt-1 text-3xl font-bold text-emerald-900">{{ number_format((int) ($chartPayload['dtm']['total_docs'] ?? 0)) }}</p>
                        </div>
                        <div class="rounded-xl bg-emerald-100 p-2.5">
                            <x-app.icon name="document-chart-bar" class="h-5 w-5 text-emerald-700" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-emerald-700">Dokumen yang berhasil dipetakan ke topik</p>
                </div>

                <div class="rounded-2xl border border-unsoed-gold-100 bg-gradient-to-b from-unsoed-gold-50 to-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-unsoed-gold-700">Rentang Tahun DTM</p>
                            <p class="mt-1 text-2xl font-bold text-unsoed-gold-800">
                                @if (($chartPayload['dtm']['year_min'] ?? null) !== null)
                                    {{ $chartPayload['dtm']['year_min'] }} - {{ $chartPayload['dtm']['year_max'] }}
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                        <div class="rounded-xl bg-unsoed-gold-100 p-2.5">
                            <x-app.icon name="calendar-days" class="h-5 w-5 text-unsoed-gold-700" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-unsoed-gold-700">Jendela waktu analisis trend topik</p>
                </div>
            </div>

            {{-- Tabs Visualisasi --}}
            <div class="space-y-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-2.5 shadow-sm">
                    <div class="flex snap-x snap-mandatory gap-2 overflow-x-auto pb-1 pr-1 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                        <button type="button" @click="setVizTab('wordcloud')"
                            :class="activeVizTab === 'wordcloud' ? 'bg-unsoed-blue-800 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="snap-start whitespace-nowrap rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors">
                            <span class="sm:hidden">WordCloud</span>
                            <span class="hidden sm:inline">Tab WordCloud</span>
                        </button>
                        <button type="button" @click="setVizTab('dtm')"
                            :class="activeVizTab === 'dtm' ? 'bg-unsoed-blue-800 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="snap-start whitespace-nowrap rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors">
                            <span class="sm:hidden">DTM</span>
                            <span class="hidden sm:inline">Tab DTM</span>
                        </button>
                        <button type="button" @click="setVizTab('trend')"
                            :class="activeVizTab === 'trend' ? 'bg-unsoed-blue-800 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="snap-start whitespace-nowrap rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors">
                            <span class="sm:hidden">Trend</span>
                            <span class="hidden sm:inline">Tab Emerging Declining</span>
                        </button>
                        <button type="button" @click="setVizTab('mapping')"
                            :class="activeVizTab === 'mapping' ? 'bg-unsoed-blue-800 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="snap-start whitespace-nowrap rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors">
                            <span class="sm:hidden">Mapping</span>
                            <span class="hidden sm:inline">Tab Mapping Skripsi</span>
                        </button>
                    </div>
                </div>

                <div x-show="activeVizTab === 'wordcloud'" x-cloak class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
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
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                            @foreach ($chartPayload['wordcloud_topics'] as $topicCloud)
                                <div class="min-w-0">
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <p class="truncate text-xs font-semibold text-gray-800" title="{{ $topicCloud['label'] }}">{{ $topicCloud['label'] }}</p>
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">{{ number_format((int) ($topicCloud['doc_count'] ?? 0)) }} dok</span>
                                    </div>

                                    <canvas data-topic-wordcloud-id="{{ $topicCloud['topic_id'] }}" data-wordcloud-index="{{ $loop->index }}" width="520" height="240" class="h-48 w-full !transform-none sm:h-56"></canvas>
                                </div>
                            @endforeach
                        </div>

                        <p class="mt-3 text-xs text-gray-500"
                            x-text="`Menampilkan semua topik (${(payload?.wordcloud_topics || []).length}) • maks ${wordCloudWordLimit} kata/topik.`">
                        </p>
                    @else
                        <div class="flex h-[18rem] flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-gray-400 sm:h-[22rem]">
                            <x-app.icon name="cloud" class="mb-3 h-12 w-12 text-gray-200" />
                            <p class="text-sm font-medium">Data word cloud per topik belum tersedia.</p>
                            <p class="mt-1 text-xs">Pastikan run memiliki daftar top_words pada topik.</p>
                        </div>
                    @endif
                </div>

                <div x-show="activeVizTab === 'dtm'" x-cloak class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-center gap-2.5">
                        <div class="rounded-lg bg-unsoed-blue-100 p-2">
                            <x-app.icon name="presentation-chart-line" class="h-4 w-4 text-unsoed-blue-700" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Dynamic Topic Modelling</h2>
                            <p class="text-xs text-gray-500">Line chart proporsi topik per tahun dengan tooltip interaktif.</p>
                        </div>
                    </div>

                    @if (!empty($chartPayload['dtm']['series']))
                        <div class="overflow-hidden rounded-xl border border-unsoed-blue-100 bg-unsoed-blue-50 p-3">
                            <canvas x-ref="dtmChartCanvas" class="block h-[20rem] w-full max-w-full !transform-none sm:h-[28rem] xl:h-[34rem]"></canvas>
                        </div>
                        <p x-show="dtmRenderError" class="mt-2 text-xs text-red-600" x-text="dtmRenderError"></p>
                    @else
                        <div class="flex h-[20rem] flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-gray-400 sm:h-[28rem] xl:h-[34rem]">
                            <x-app.icon name="presentation-chart-line" class="mb-3 h-12 w-12 text-gray-200" />
                            <p class="text-sm font-medium">Data DTM belum tersedia.</p>
                            <p class="mt-1 text-xs">{{ $chartPayload['dtm']['missing_reason'] ?? 'Perlu mapping dokumen-topik dan tahun skripsi yang valid.' }}</p>
                        </div>
                    @endif
                </div>

                <div x-show="activeVizTab === 'trend'" x-cloak class="grid grid-cols-1 gap-4 sm:gap-6 xl:grid-cols-2">
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex items-center gap-2.5">
                            <div class="rounded-lg bg-unsoed-blue-100 p-2">
                                <x-app.icon name="arrow-trending-up" class="h-4 w-4 text-unsoed-blue-700" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Topic Emerging</h2>
                                <p class="text-xs text-gray-500">Topik dengan kenaikan proporsi tertinggi.</p>
                            </div>
                        </div>

                        @if (!empty($chartPayload['trend']['emerging']))
                            <div class="overflow-hidden rounded-xl border border-unsoed-blue-100 bg-unsoed-blue-50 p-3">
                                <canvas x-ref="emergingChartCanvas" class="block h-[16rem] w-full max-w-full !transform-none sm:h-[20rem]"></canvas>
                            </div>
                            <p x-show="emergingRenderError" class="mt-2 text-xs text-red-600" x-text="emergingRenderError"></p>
                        @else
                            <div class="flex h-[16rem] flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-gray-400 sm:h-[20rem]">
                                <x-app.icon name="arrow-trending-up" class="mb-3 h-12 w-12 text-gray-200" />
                                <p class="text-sm font-medium">Belum ada topik emerging terdeteksi.</p>
                            </div>
                        @endif
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex items-center gap-2.5">
                            <div class="rounded-lg bg-unsoed-blue-100 p-2">
                                <x-app.icon name="arrow-trending-down" class="h-4 w-4 text-unsoed-blue-700" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Topic Declining</h2>
                                <p class="text-xs text-gray-500">Topik dengan penurunan proporsi tertinggi.</p>
                            </div>
                        </div>

                        @if (!empty($chartPayload['trend']['declining']))
                            <div class="overflow-hidden rounded-xl border border-unsoed-blue-100 bg-unsoed-blue-50 p-3">
                                <canvas x-ref="decliningChartCanvas" class="block h-[16rem] w-full max-w-full !transform-none sm:h-[20rem]"></canvas>
                            </div>
                            <p x-show="decliningRenderError" class="mt-2 text-xs text-red-600" x-text="decliningRenderError"></p>
                        @else
                            <div class="flex h-[16rem] flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-gray-400 sm:h-[20rem]">
                                <x-app.icon name="arrow-trending-down" class="mb-3 h-12 w-12 text-gray-200" />
                                <p class="text-sm font-medium">Belum ada topik declining terdeteksi.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div x-show="activeVizTab === 'mapping'" x-cloak class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-center gap-2.5">
                        <div class="rounded-lg bg-unsoed-blue-100 p-2">
                            <x-app.icon name="table-cells" class="h-4 w-4 text-unsoed-blue-700" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Mapping Skripsi ke Topik</h2>
                            <p class="text-xs text-gray-500">Cek assignment topik untuk setiap skripsi pada run aktif.</p>
                        </div>
                    </div>

                    @if (!empty($skripsiMapping['topic_summary']))
                        <div class="mb-4 overflow-hidden rounded-xl border border-unsoed-blue-100 bg-unsoed-blue-50 p-3">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-unsoed-blue-700">Distribusi Dokumen per Topik</p>
                                <p class="text-[11px] text-unsoed-blue-700">Top 12 topik berdasarkan jumlah dokumen</p>
                            </div>
                            <canvas x-ref="mappingDistributionChartCanvas" class="block h-[16rem] w-full max-w-full !transform-none sm:h-[20rem]"></canvas>
                            <p x-show="mappingRenderError" class="mt-2 text-xs text-red-600" x-text="mappingRenderError"></p>
                        </div>
                    @endif

                    @if (!empty($skripsiMapping['rows']))
                        <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
                            <span class="inline-flex items-center rounded-full border border-unsoed-blue-200 bg-unsoed-blue-50 px-2.5 py-1 font-medium text-unsoed-blue-700">
                                {{ number_format((int) ($skripsiMapping['displayed_rows'] ?? 0)) }} dari {{ number_format((int) ($skripsiMapping['total_rows'] ?? 0)) }} mapping
                            </span>
                            @if (!empty($skripsiMapping['is_truncated']))
                                <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 font-medium text-amber-700">
                                    Ditampilkan sebagian untuk menjaga performa
                                </span>
                            @endif
                        </div>

                        @if (!empty($skripsiMapping['topic_summary']))
                            <div class="mb-4 flex flex-wrap gap-1.5">
                                @foreach (array_slice($skripsiMapping['topic_summary'], 0, 12) as $summary)
                                    <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-medium text-gray-700">
                                        {{ $summary['topic_label'] ?? 'Topik' }} • {{ number_format((int) ($summary['doc_count'] ?? 0)) }} dok
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <div class="space-y-2 md:hidden">
                            @foreach ($skripsiMapping['rows'] as $index => $mapping)
                                <article class="rounded-xl border border-gray-200 bg-white p-3">
                                    <div class="mb-2 flex items-start justify-between gap-2">
                                        <span class="inline-flex items-center rounded-full bg-unsoed-blue-50 px-2 py-0.5 text-[11px] font-semibold text-unsoed-blue-700">
                                            {{ $mapping['topic_label'] ?? ('Topik ' . ($mapping['topic_id'] ?? '-')) }}
                                        </span>
                                        <span class="text-[11px] font-medium text-gray-500">#{{ $index + 1 }}</span>
                                    </div>
                                    <p class="text-xs font-semibold leading-relaxed text-gray-800">{{ $mapping['title'] ?? '-' }}</p>
                                    <div class="mt-2 grid grid-cols-2 gap-2 text-[11px] text-gray-600">
                                        <p><span class="font-medium text-gray-700">Penulis:</span> {{ $mapping['author'] ?? '-' }}</p>
                                        <p><span class="font-medium text-gray-700">Tahun:</span> {{ $mapping['year'] ?? '-' }}</p>
                                        <p class="col-span-2"><span class="font-medium text-gray-700">ID Skripsi:</span> {{ number_format((int) ($mapping['skripsi_id'] ?? 0)) }}</p>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <div class="hidden overflow-x-auto rounded-xl border border-gray-200 md:block">
                            <table class="w-full min-w-[900px] text-sm">
                                <thead class="border-b border-gray-200 bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">No</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Topik</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Judul Skripsi</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Penulis</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Tahun</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">ID Skripsi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($skripsiMapping['rows'] as $index => $mapping)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2.5 text-xs text-gray-500">{{ $index + 1 }}</td>
                                            <td class="px-4 py-2.5 align-top">
                                                <span class="inline-flex items-center rounded-full bg-unsoed-blue-50 px-2 py-0.5 text-xs font-semibold text-unsoed-blue-700">
                                                    {{ $mapping['topic_label'] ?? ('Topik ' . ($mapping['topic_id'] ?? '-')) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2.5 align-top text-xs text-gray-700">
                                                <div class="max-w-xl leading-relaxed">{{ $mapping['title'] ?? '-' }}</div>
                                            </td>
                                            <td class="px-4 py-2.5 align-top text-xs text-gray-700">{{ $mapping['author'] ?? '-' }}</td>
                                            <td class="px-4 py-2.5 text-center text-xs font-medium text-gray-700">{{ $mapping['year'] ?? '-' }}</td>
                                            <td class="px-4 py-2.5 text-right text-xs font-semibold text-gray-700">{{ number_format((int) ($mapping['skripsi_id'] ?? 0)) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="flex h-[18rem] flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-gray-400">
                            <x-app.icon name="table-cells" class="mb-3 h-12 w-12 text-gray-200" />
                            <p class="text-sm font-medium">Data mapping skripsi belum tersedia.</p>
                            <p class="mt-1 text-xs">{{ $skripsiMapping['missing_reason'] ?? 'Belum ada relasi dokumen-topik pada run ini.' }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- List Topic --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-unsoed-blue-100 p-2">
                            <x-app.icon name="list-bullet" class="h-4 w-4 text-unsoed-blue-700" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">List Topic</h2>
                            <p class="text-xs text-gray-500">Daftar topik berdasarkan jumlah dokumen.</p>
                        </div>
                    </div>
                    <span class="rounded-full bg-unsoed-blue-50 px-2.5 py-1 text-xs font-medium text-unsoed-blue-700">{{ $topicList->count() }} topik</span>
                </div>

                @if ($topicList->isNotEmpty())
                    <div class="space-y-2 p-3 md:hidden">
                        @foreach ($topicList as $topic)
                            <article class="rounded-xl border border-gray-200 bg-white p-3">
                                <div class="mb-2 flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-unsoed-blue-100 text-xs font-bold text-unsoed-blue-800">{{ $topic->topic_id }}</span>
                                        @if (filled($topic->custom_name))
                                            <p class="text-sm font-semibold text-gray-900">{{ $topic->custom_name }}</p>
                                        @else
                                            <p class="text-sm font-medium text-gray-700">Topik {{ $topic->topic_id }}</p>
                                        @endif
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700">{{ number_format($topic->count) }} dok</span>
                                </div>
                                <div class="mb-2 flex flex-wrap gap-1.5">
                                    @foreach (array_slice($topic->top_words ?? [], 0, 15) as $word)
                                        <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[11px] font-medium text-gray-700">{{ $word }}</span>
                                    @endforeach
                                </div>
                                @if (filled($topic->representation_description))
                                    <p class="line-clamp-3 text-xs leading-relaxed text-gray-600">{{ $topic->representation_description }}</p>
                                @else
                                    <p class="text-xs text-gray-400">Belum ada deskripsi kurasi.</p>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full text-sm">
                            <thead class="border-b border-gray-200 bg-unsoed-blue-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-unsoed-blue-700">Topik</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-unsoed-blue-700">Jumlah Dokumen</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-unsoed-blue-700">Kata Kunci</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-unsoed-blue-700">Deskripsi Kurasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($topicList as $topic)
                                    <tr class="even:bg-gray-50 hover:bg-unsoed-gold-50 transition-colors">
                                        <td class="px-4 py-3 align-top">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-unsoed-blue-100 text-xs font-bold text-unsoed-blue-800">{{ $topic->topic_id }}</span>
                                                <div>
                                                    @if (filled($topic->custom_name))
                                                        <p class="text-sm font-semibold text-gray-900">{{ $topic->custom_name }}</p>
                                                    @else
                                                        <p class="text-sm font-medium text-gray-700">Topik {{ $topic->topic_id }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-800 align-top">{{ number_format($topic->count) }}</td>
                                        <td class="px-4 py-3 align-top">
                                            <div class="max-w-xl flex flex-wrap gap-1.5">
                                                @foreach (array_slice($topic->top_words ?? [], 0, 15) as $word)
                                                    <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2 py-0.5 text-xs font-medium text-gray-700">{{ $word }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            @if (filled($topic->representation_description))
                                                <p class="line-clamp-3 text-xs leading-relaxed text-gray-600">{{ $topic->representation_description }}</p>
                                            @else
                                                <p class="text-xs text-gray-400">Belum ada deskripsi kurasi.</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-16 text-center text-gray-400">
                        <x-app.icon name="inbox-stack" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                        <p class="text-sm font-medium">Belum ada topik untuk divisualisasikan.</p>
                    </div>
                @endif
            </div>
        @else
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-5 text-sm text-amber-800 shadow-sm sm:px-5">
                Belum ada run completed yang bisa divisualisasikan. Jalankan training terlebih dahulu pada menu Topic Modeling.
            </div>
        @endif
    </div>

    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-chart-wordcloud@4.4.4/build/index.umd.min.js"></script>

        <script>
            function registerTopicVisualizationPage() {
                if (!window.Alpine) {
                    return;
                }

                window.Alpine.data('topicVisualizationPage', (payload, mappingPayload) => ({
                    payload,
                    mappingPayload,
                    dtmChart: null,
                    emergingChart: null,
                    decliningChart: null,
                    mappingDistributionChart: null,
                    wordCloudCharts: new Map(),
                    wordCloudRenderSignatures: new Map(),
                    activeVizTab: 'wordcloud',
                    wordCloudPageIndex: 0,
                    wordCloudPageSize: 4,
                    wordCloudWordLimit: 12,
                    dtmRenderError: '',
                    emergingRenderError: '',
                    decliningRenderError: '',
                    mappingRenderError: '',
                    renderAttempts: 0,

                    init() {
                        this.scheduleInitialRender();
                    },

                    scheduleInitialRender() {
                        this.setVizTab(this.activeVizTab);
                    },

                    setVizTab(tab) {
                        const validTabs = ['wordcloud', 'dtm', 'trend', 'mapping'];
                        if (!validTabs.includes(tab)) {
                            return;
                        }

                        this.activeVizTab = tab;

                        this.$nextTick(() => {
                            requestAnimationFrame(() => {
                                if (this.activeVizTab === 'wordcloud') {
                                    this.renderWordCloudTopics();
                                    return;
                                }

                                if (this.activeVizTab === 'dtm') {
                                    this.renderDtmChart();
                                    return;
                                }

                                if (this.activeVizTab === 'mapping') {
                                    this.renderMappingDistributionChart();
                                    return;
                                }

                                this.renderTrendCharts();
                            });
                        });
                    },

                    scheduleWordCloudRender() {
                        this.$nextTick(() => {
                            requestAnimationFrame(() => {
                                this.renderWordCloudTopics();
                            });
                        });
                    },

                    getWordCloudTopics() {
                        return Array.isArray(this.payload?.wordcloud_topics) ? this.payload.wordcloud_topics : [];
                    },

                    wordCloudTotalPages() {
                        const topics = this.getWordCloudTopics();
                        return topics.length > 0 ? Math.ceil(topics.length / this.wordCloudPageSize) : 1;
                    },

                    wordCloudRangeStart() {
                        const topics = this.getWordCloudTopics();
                        if (!topics.length) {
                            return 0;
                        }

                        return (this.wordCloudPageIndex * this.wordCloudPageSize) + 1;
                    },

                    wordCloudRangeEnd() {
                        const topics = this.getWordCloudTopics();
                        if (!topics.length) {
                            return 0;
                        }

                        return Math.min(topics.length, (this.wordCloudPageIndex + 1) * this.wordCloudPageSize);
                    },

                    isWordCloudCardVisible(index) {
                        return true;
                    },

                    clampWordCloudPageIndex() {
                        const totalPages = this.wordCloudTotalPages();
                        if (this.wordCloudPageIndex >= totalPages) {
                            this.wordCloudPageIndex = Math.max(0, totalPages - 1);
                        }
                        if (this.wordCloudPageIndex < 0) {
                            this.wordCloudPageIndex = 0;
                        }
                    },

                    setWordCloudPageSize(size) {
                        const normalizedSize = Number(size);
                        if (![3, 4].includes(normalizedSize)) {
                            return;
                        }

                        if (this.wordCloudPageSize === normalizedSize) {
                            return;
                        }

                        const currentFirstIndex = this.wordCloudPageIndex * this.wordCloudPageSize;
                        this.wordCloudPageSize = normalizedSize;
                        this.wordCloudPageIndex = Math.floor(currentFirstIndex / this.wordCloudPageSize);
                        this.clampWordCloudPageIndex();
                        this.scheduleWordCloudRender();
                    },

                    prevWordCloudPage() {
                        if (this.wordCloudPageIndex <= 0) {
                            return;
                        }

                        this.wordCloudPageIndex -= 1;
                        this.scheduleWordCloudRender();
                    },

                    nextWordCloudPage() {
                        if (this.wordCloudPageIndex >= (this.wordCloudTotalPages() - 1)) {
                            return;
                        }

                        this.wordCloudPageIndex += 1;
                        this.scheduleWordCloudRender();
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
                        return [
                            '#0f766e', '#1d4ed8', '#be123c', '#9333ea', '#b45309',
                            '#0369a1', '#15803d', '#c2410c', '#334155', '#4d7c0f',
                        ];
                    },

                    destroyWordCloudChart(index) {
                        const chart = this.wordCloudCharts.get(index);
                        if (chart) {
                            chart.destroy();
                        }
                        this.wordCloudCharts.delete(index);
                        this.wordCloudRenderSignatures.delete(index);
                    },

                    buildWordCloudSignature(topic, labels, rawWeights) {
                        const topicId = topic?.topic_id ?? '';
                        const wordsSignature = labels
                            .map((label, idx) => `${label}:${Number(rawWeights[idx] ?? 0).toFixed(8)}`)
                            .join('|');

                        return `${topicId}::${wordsSignature}`;
                    },

                    buildGradient(ctx, chartArea, startColor, endColor) {
                        if (!ctx || !chartArea) {
                            return startColor;
                        }

                        const gradient = ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0);
                        gradient.addColorStop(0, startColor);
                        gradient.addColorStop(1, endColor);
                        return gradient;
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
                        this.clampWordCloudPageIndex();

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
                            const seenWords = new Set();
                            const normalizedWords = words
                                .map((word) => {
                                    const text = typeof word?.text === 'string' ? word.text.trim() : '';
                                    const normalizedText = text.toLowerCase();

                                    if (normalizedText === '' || seenWords.has(normalizedText)) {
                                        return null;
                                    }

                                    seenWords.add(normalizedText);

                                    const rawWeight = Number.parseFloat(word?.raw_weight);
                                    const visualWeight = Number.parseFloat(word?.weight);
                                    const safeRaw = Number.isFinite(rawWeight) && rawWeight > 0
                                        ? rawWeight
                                        : Number.isFinite(visualWeight) && visualWeight > 0
                                            ? visualWeight
                                            : 0;

                                    return {
                                        text: normalizedText,
                                        rawWeight: safeRaw,
                                    };
                                })
                                .filter((word) => word !== null)
                                .filter((word) => word.text.length > 0 && Number.isFinite(word.rawWeight) && word.rawWeight > 0)
                                .sort((a, b) => b.rawWeight - a.rawWeight);

                            const limitedWords = normalizedWords.slice(0, this.wordCloudWordLimit);

                            if (!limitedWords.length) {
                                this.destroyWordCloudChart(index);
                                return;
                            }

                            const rawWeights = limitedWords.map((word) => word.rawWeight);
                            const minRaw = Math.min(...rawWeights);
                            const maxRaw = Math.max(...rawWeights);
                            const denominator = maxRaw - minRaw || 1;

                            const labels = limitedWords.map((word) => word.text);
                            const values = limitedWords.map((word) => {
                                const ratio = (word.rawWeight - minRaw) / denominator;
                                const scaled = 14 + (Math.pow(ratio, 1.15) * 42);
                                return Math.max(14, Math.round(scaled));
                            });

                            const signature = this.buildWordCloudSignature(topic, labels, rawWeights);
                            const existingSignature = this.wordCloudRenderSignatures.get(index);
                            const existingChart = this.wordCloudCharts.get(index);
                            if (existingChart && existingSignature === signature) {
                                return;
                            }

                            const rawWeightByWord = new Map(limitedWords.map((word) => [word.text, word.rawWeight]));
                            const colors = limitedWords.map((word, wordIndex) => {
                                const ratio = (word.rawWeight - minRaw) / denominator;
                                const band = Math.round(ratio * (colorPool.length - 1));
                                let hash = 0;
                                for (let i = 0; i < word.text.length; i += 1) {
                                    hash = ((hash << 5) - hash) + word.text.charCodeAt(i);
                                    hash |= 0;
                                }
                                const mixedIndex = Math.abs(hash + band + (wordIndex * 7)) % colorPool.length;
                                return colorPool[mixedIndex];
                            });

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
                                        padding: 1,
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
                                        padding: 8,
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
                                    elements: {
                                        word: {
                                            fit: false,
                                            minRotation: 0,
                                            maxRotation: 0,
                                            rotationSteps: 1,
                                            padding: 1,
                                        },
                                    },
                                },
                            });

                            this.wordCloudCharts.set(index, chart);
                            this.wordCloudRenderSignatures.set(index, signature);
                        });

                        this.destroyStaleWordCloudCharts(visibleIndexes);
                    },

                    renderDtmChart() {
                        const canvas = this.$refs.dtmChartCanvas;
                        const dtm = this.payload?.dtm ?? {};
                        const years = Array.isArray(dtm.years) ? dtm.years : [];
                        const series = Array.isArray(dtm.series) ? dtm.series : [];

                        if (!canvas || years.length === 0 || series.length === 0) {
                            return;
                        }

                        if (!canvas.isConnected) {
                            this.dtmRenderError = 'Canvas DTM belum terpasang ke DOM.';
                            return;
                        }

                        if (typeof window.Chart === 'undefined') {
                            this.dtmRenderError = 'Library Chart.js belum termuat, jadi grafik belum bisa dirender.';
                            return;
                        }

                        const ctx = typeof canvas.getContext === 'function' ? canvas.getContext('2d') : null;
                        if (!ctx) {
                            this.dtmRenderError = 'Canvas DTM belum siap (context null). Silakan refresh halaman.';
                            return;
                        }

                        const canvasWidth = canvas.clientWidth || canvas.offsetWidth || 0;
                        const canvasHeight = canvas.clientHeight || canvas.offsetHeight || 0;
                        if ((canvasWidth === 0 || canvasHeight === 0) && this.renderAttempts < 3) {
                            this.renderAttempts += 1;
                            requestAnimationFrame(() => this.renderDtmChart());
                            return;
                        }

                        this.renderAttempts = 0;

                        if (this.dtmChart) {
                            this.dtmChart.destroy();
                            this.dtmChart = null;
                        }

                        const colors = [
                            '#2563eb', '#06b6d4', '#22c55e', '#f59e0b', '#a855f7',
                            '#ef4444', '#0ea5e9', '#14b8a6', '#6366f1', '#f97316'
                        ];
                        const chartTextColor = '#334155';
                        const chartGridColor = 'rgba(148, 163, 184, 0.22)';

                        const hexToRgba = (hex, alpha) => {
                            const cleanHex = String(hex || '').replace('#', '');
                            const expandedHex = cleanHex.length === 3
                                ? cleanHex.split('').map((char) => char + char).join('')
                                : cleanHex;

                            const intValue = Number.parseInt(expandedHex, 16);
                            if (!Number.isFinite(intValue)) {
                                return `rgba(51, 65, 85, ${alpha})`;
                            }

                            const r = (intValue >> 16) & 255;
                            const g = (intValue >> 8) & 255;
                            const b = intValue & 255;
                            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
                        };

                        const allValues = series
                            .flatMap((item) => Array.isArray(item?.data) ? item.data : [])
                            .map((value) => Number(value))
                            .filter((value) => Number.isFinite(value) && value >= 0);

                        const maxPoint = allValues.length ? Math.max(...allValues) : 0;
                        const yPadding = maxPoint > 0 ? Math.max(1.5, maxPoint * 0.18) : 5;
                        const yMaxRaw = maxPoint + yPadding;
                        const yMax = maxPoint > 0
                            ? Math.min(100, Math.max(8, Math.ceil(yMaxRaw / 5) * 5))
                            : 10;
                        const yTickStep = yMax <= 20 ? 2 : yMax <= 50 ? 5 : 10;

                        const datasets = series.map((item, index) => {
                            const baseColor = colors[index % colors.length];

                            return {
                                label: item.label,
                                data: item.data,
                                borderColor: baseColor,
                                backgroundColor: hexToRgba(baseColor, 0.15),
                                fill: true,
                                tension: 0.32,
                                pointRadius: 0,
                                pointHoverRadius: 0,
                                pointHitRadius: 12,
                                pointBackgroundColor: baseColor,
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 1,
                                borderWidth: 2.4,
                            };
                        });

                        this.dtmChart = new window.Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: years,
                                datasets,
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                resizeDelay: 140,
                                animation: {
                                    duration: 260,
                                    easing: 'easeOutQuart',
                                },
                                transitions: {
                                    active: {
                                        animation: {
                                            duration: 0,
                                        },
                                    },
                                },
                                interaction: {
                                    mode: 'index',
                                    intersect: false,
                                },
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: {
                                            useBorderRadius: true,
                                            borderRadius: 3,
                                            boxWidth: 14,
                                            boxHeight: 10,
                                            padding: 14,
                                            color: chartTextColor,
                                            font: {
                                                size: 11.5,
                                                family: 'Inter, sans-serif',
                                                weight: '600',
                                            },
                                            generateLabels: (chart) => {
                                                const baseLabels = window.Chart.defaults.plugins.legend.labels.generateLabels(chart);

                                                return baseLabels.map((label) => {
                                                    const dataset = chart?.data?.datasets?.[label.datasetIndex] ?? {};
                                                    const strongColor = dataset.borderColor || label.strokeStyle || label.fillStyle || '#334155';

                                                    return {
                                                        ...label,
                                                        fillStyle: strongColor,
                                                        strokeStyle: strongColor,
                                                        lineWidth: 0,
                                                    };
                                                });
                                            },
                                        },
                                    },
                                    tooltip: {
                                        backgroundColor: '#0f172a',
                                        borderColor: 'rgba(255, 255, 255, 0.14)',
                                        borderWidth: 1,
                                        titleColor: '#f8fafc',
                                        bodyColor: '#e2e8f0',
                                        cornerRadius: 10,
                                        padding: 10,
                                        callbacks: {
                                            label: (context) => {
                                                const topicData = series[context.datasetIndex];
                                                const count = topicData?.counts?.[context.dataIndex] ?? 0;
                                                return `${context.dataset.label}: ${Number(context.parsed.y).toFixed(2)}% (${count} dokumen)`;
                                            },
                                        },
                                    },
                                },
                                scales: {
                                    x: {
                                        ticks: {
                                            color: chartTextColor,
                                            maxRotation: 0,
                                            autoSkip: true,
                                            maxTicksLimit: 12,
                                        },
                                        grid: {
                                            color: chartGridColor,
                                            drawBorder: false,
                                        },
                                        title: {
                                            display: true,
                                            text: 'Tahun',
                                            color: chartTextColor,
                                            font: {
                                                size: 12,
                                                weight: '600',
                                            },
                                        },
                                    },
                                    y: {
                                        beginAtZero: true,
                                        max: yMax,
                                        ticks: {
                                            color: chartTextColor,
                                            stepSize: yTickStep,
                                            callback: (value) => `${Number(value).toFixed(Number(value) < 10 ? 1 : 0)}%`,
                                        },
                                        grid: {
                                            color: chartGridColor,
                                            drawBorder: false,
                                        },
                                        title: {
                                            display: true,
                                            text: 'Proporsi Topik (%)',
                                            color: chartTextColor,
                                            font: {
                                                size: 12,
                                                weight: '600',
                                            },
                                        },
                                    },
                                },
                            },
                        });
                    },

                    renderTrendCharts() {
                        const trend = this.payload?.trend ?? {};
                        const emerging = Array.isArray(trend.emerging) ? trend.emerging : [];
                        const declining = Array.isArray(trend.declining) ? trend.declining : [];

                        if (this.emergingChart) {
                            this.emergingChart.destroy();
                            this.emergingChart = null;
                        }

                        if (this.decliningChart) {
                            this.decliningChart.destroy();
                            this.decliningChart = null;
                        }

                        this.emergingChart = this.createTrendChart(this.$refs.emergingChartCanvas, emerging, false, 'emergingRenderError');
                        this.decliningChart = this.createTrendChart(this.$refs.decliningChartCanvas, declining, true, 'decliningRenderError');
                    },

                    createTrendChart(canvas, rows, isDeclining, errorKey) {
                        if (!canvas || !Array.isArray(rows) || rows.length === 0) {
                            return null;
                        }

                        if (!canvas.isConnected) {
                            this[errorKey] = 'Canvas chart belum terpasang ke DOM.';
                            return null;
                        }

                        if (typeof window.Chart === 'undefined') {
                            this[errorKey] = 'Library Chart.js belum termuat, jadi grafik belum bisa dirender.';
                            return null;
                        }

                        const ctx = typeof canvas.getContext === 'function' ? canvas.getContext('2d') : null;
                        if (!ctx) {
                            this[errorKey] = 'Canvas chart belum siap (context null). Silakan refresh halaman.';
                            return null;
                        }

                        const labels = rows.map((row) => row.label);
                        const data = rows.map((row) => Math.abs(Number(row.delta || 0)));
                        const gradientPairs = isDeclining
                            ? [
                                ['#ef4444', '#b91c1c'],
                                ['#f97316', '#c2410c'],
                                ['#f59e0b', '#b45309'],
                                ['#a855f7', '#7e22ce'],
                                ['#ec4899', '#be185d'],
                            ]
                            : [
                                ['#3b82f6', '#1d4ed8'],
                                ['#06b6d4', '#0e7490'],
                                ['#22c55e', '#15803d'],
                                ['#6366f1', '#4338ca'],
                                ['#14b8a6', '#0f766e'],
                            ];
                        const chartTextColor = '#334155';
                        const chartGridColor = '#e2e8f0';

                        const chart = new window.Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels,
                                datasets: [{
                                    label: isDeclining ? 'Penurunan (%)' : 'Kenaikan (%)',
                                    data,
                                    backgroundColor: (context) => {
                                        const index = Number(context.dataIndex ?? 0);
                                        const pair = gradientPairs[index % gradientPairs.length];
                                        return this.buildGradient(context.chart?.ctx, context.chart?.chartArea, pair[0], pair[1]);
                                    },
                                    borderRadius: 6,
                                    borderSkipped: false,
                                    borderWidth: 1,
                                    borderColor: (context) => {
                                        const index = Number(context.dataIndex ?? 0);
                                        const pair = gradientPairs[index % gradientPairs.length];
                                        return pair[1];
                                    },
                                    hoverBorderWidth: 0,
                                    inflateAmount: 0,
                                }],
                            },
                            options: {
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                resizeDelay: 140,
                                animation: false,
                                transitions: {
                                    active: {
                                        animation: {
                                            duration: 0,
                                        },
                                    },
                                },
                                plugins: {
                                    legend: {
                                        display: false,
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: (context) => {
                                                const row = rows[context.dataIndex] || {};
                                                return `${Number(context.parsed.x).toFixed(2)}% (awal ${Number(row.start || 0).toFixed(2)}% → akhir ${Number(row.end || 0).toFixed(2)}%)`;
                                            },
                                        },
                                    },
                                },
                                scales: {
                                    x: {
                                        beginAtZero: true,
                                        ticks: {
                                            color: chartTextColor,
                                        },
                                        grid: {
                                            color: chartGridColor,
                                        },
                                        title: {
                                            display: true,
                                            text: isDeclining ? 'Magnitude Penurunan (%)' : 'Kenaikan (%)',
                                            color: chartTextColor,
                                        },
                                    },
                                    y: {
                                        ticks: {
                                            autoSkip: false,
                                            color: chartTextColor,
                                            callback: function(value) {
                                                const label = String(this.getLabelForValue(value) ?? '');
                                                return label.length > 28 ? `${label.slice(0, 28)}...` : label;
                                            },
                                        },
                                        grid: {
                                            display: false,
                                        },
                                    },
                                },
                            },
                        });
                        return chart;
                    },

                    getMappingTopicSummary() {
                        return Array.isArray(this.mappingPayload?.topic_summary) ? this.mappingPayload.topic_summary : [];
                    },

                    renderMappingDistributionChart() {
                        const canvas = this.$refs.mappingDistributionChartCanvas;
                        const topicSummary = this.getMappingTopicSummary()
                            .filter((row) => Number(row?.doc_count) > 0)
                            .slice(0, 12);

                        if (this.mappingDistributionChart) {
                            this.mappingDistributionChart.destroy();
                            this.mappingDistributionChart = null;
                        }

                        if (!canvas || topicSummary.length === 0) {
                            return;
                        }

                        if (!canvas.isConnected) {
                            this.mappingRenderError = 'Canvas chart mapping belum terpasang ke DOM.';
                            return;
                        }

                        if (typeof window.Chart === 'undefined') {
                            this.mappingRenderError = 'Library Chart.js belum termuat, jadi grafik mapping belum bisa dirender.';
                            return;
                        }

                        const ctx = typeof canvas.getContext === 'function' ? canvas.getContext('2d') : null;
                        if (!ctx) {
                            this.mappingRenderError = 'Canvas chart mapping belum siap (context null). Silakan refresh halaman.';
                            return;
                        }

                        const containerWidth = Math.floor(canvas.parentElement?.clientWidth || canvas.clientWidth || 0);
                        const containerHeight = Math.floor(canvas.clientHeight || 0);
                        const targetWidth = Math.max(300, containerWidth);
                        const targetHeight = Math.max(220, containerHeight);
                        if (targetWidth > 0 && targetHeight > 0) {
                            canvas.width = targetWidth;
                            canvas.height = targetHeight;
                        }

                        this.mappingRenderError = '';

                        const labels = topicSummary.map((row) => String(row?.topic_label ?? `Topik ${row?.topic_id ?? '-'}`));
                        const counts = topicSummary.map((row) => Number(row?.doc_count ?? 0));
                        const totalDocs = counts.reduce((sum, value) => sum + value, 0);

                        const gradientPairs = [
                            ['#3b82f6', '#1d4ed8'],
                            ['#06b6d4', '#0e7490'],
                            ['#22c55e', '#15803d'],
                            ['#f59e0b', '#b45309'],
                            ['#ef4444', '#b91c1c'],
                            ['#a855f7', '#7e22ce'],
                            ['#14b8a6', '#0f766e'],
                            ['#f97316', '#c2410c'],
                            ['#6366f1', '#4338ca'],
                            ['#ec4899', '#be185d'],
                            ['#0ea5e9', '#0369a1'],
                            ['#84cc16', '#4d7c0f'],
                        ];

                        this.mappingDistributionChart = new window.Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels,
                                datasets: [{
                                    label: 'Jumlah Skripsi',
                                    data: counts,
                                    borderRadius: 7,
                                    borderSkipped: false,
                                    categoryPercentage: 0.76,
                                    barPercentage: 0.82,
                                    borderWidth: 1,
                                    borderColor: (context) => {
                                        const index = Number(context.dataIndex ?? 0);
                                        const pair = gradientPairs[index % gradientPairs.length];
                                        return pair[1];
                                    },
                                    hoverBorderWidth: 1,
                                    inflateAmount: 0,
                                    backgroundColor: (context) => {
                                        const index = Number(context.dataIndex ?? 0);
                                        const pair = gradientPairs[index % gradientPairs.length];
                                        return this.buildGradient(context.chart?.ctx, context.chart?.chartArea, pair[0], pair[1]);
                                    },
                                    hoverBackgroundColor: (context) => {
                                        const index = Number(context.dataIndex ?? 0);
                                        const pair = gradientPairs[index % gradientPairs.length];
                                        return this.buildGradient(context.chart?.ctx, context.chart?.chartArea, pair[0], pair[1]);
                                    },
                                    hoverBorderColor: (context) => {
                                        const index = Number(context.dataIndex ?? 0);
                                        const pair = gradientPairs[index % gradientPairs.length];
                                        return pair[1];
                                    },
                                }],
                            },
                            options: {
                                indexAxis: 'y',
                                responsive: false,
                                maintainAspectRatio: false,
                                animation: false,
                                transitions: {
                                    active: {
                                        animation: {
                                            duration: 0,
                                        },
                                    },
                                },
                                interaction: {
                                    mode: 'nearest',
                                    axis: 'y',
                                    intersect: true,
                                },
                                plugins: {
                                    legend: {
                                        display: false,
                                    },
                                    tooltip: {
                                        backgroundColor: '#0f172a',
                                        borderColor: 'rgba(255, 255, 255, 0.14)',
                                        borderWidth: 1,
                                        titleColor: '#f8fafc',
                                        bodyColor: '#e2e8f0',
                                        cornerRadius: 10,
                                        padding: 10,
                                        callbacks: {
                                            label: (context) => {
                                                const value = Number(context.parsed.x || 0);
                                                const pct = totalDocs > 0 ? (value / totalDocs) * 100 : 0;
                                                return `${value} dokumen (${pct.toFixed(2)}%)`;
                                            },
                                        },
                                    },
                                },
                                scales: {
                                    x: {
                                        beginAtZero: true,
                                        ticks: {
                                            color: '#334155',
                                            precision: 0,
                                        },
                                        grid: {
                                            color: 'rgba(148, 163, 184, 0.22)',
                                            drawBorder: false,
                                        },
                                        title: {
                                            display: true,
                                            text: 'Jumlah Dokumen',
                                            color: '#334155',
                                            font: {
                                                size: 12,
                                                weight: '600',
                                            },
                                        },
                                    },
                                    y: {
                                        ticks: {
                                            color: '#334155',
                                            autoSkip: false,
                                            callback: function(value) {
                                                const label = String(this.getLabelForValue(value) ?? '');
                                                return label.length > 28 ? `${label.slice(0, 28)}...` : label;
                                            },
                                        },
                                        grid: {
                                            display: false,
                                        },
                                    },
                                },
                            },
                        });
                    },
                }));
            }

            document.addEventListener('alpine:init', registerTopicVisualizationPage);
            registerTopicVisualizationPage();
        </script>
    @endonce
</div>
