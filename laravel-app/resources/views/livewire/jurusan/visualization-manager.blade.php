<div wire:key="visualisasi-{{ $activeRun?->id ?? 'none' }}" x-data="{ activeVizTab: 'wordcloud', setVizTab(t) { this.activeVizTab = t; } }">
    @section('page-title', 'Visualisasi Topik')

    <div class="space-y-5 sm:space-y-6">
        {{-- Hero Header --}}
        <div class="rounded-2xl border border-unsoed-blue-700 bg-gradient-to-br from-unsoed-blue-800 via-unsoed-blue-700 to-unsoed-blue-600 p-5 text-white shadow-sm sm:p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-unsoed-gold-200">
                        Insight Visualisasi
                    </span>
                    <h1 class="mt-2 text-2xl font-bold">Visualisasi Analisis Topik</h1>
                    <p class="mt-1.5 text-sm text-unsoed-blue-100">
                        Visualisasi tren topik penelitian melalui Word Cloud, Grafik Tren, dan Pemetaan Skripsi.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2 text-[11px] font-medium">
                        <span class="rounded-full border border-white/20 bg-white/10 px-2.5 py-1">Word Cloud per Topik</span>
                        <span class="rounded-full border border-white/20 bg-white/10 px-2.5 py-1">Tooltip Interaktif</span>
                        <span class="rounded-full border border-white/20 bg-white/10 px-2.5 py-1">Tren Topik per Tahun</span>
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
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Pilih Run Pelatihan</label>
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
                            {{ $completedRuns->count() }} run selesai
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
                            Belum ada run pelatihan dengan status selesai.
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
                    <p class="mt-3 text-xs text-unsoed-blue-700">Jumlah topik yang terbentuk</p>
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
                    <p class="mt-3 text-xs text-emerald-700">Jumlah skripsi yang dianalisis</p>
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
                    <p class="mt-3 text-xs text-unsoed-gold-700">Rentang waktu data skripsi</p>
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
                            <span class="hidden sm:inline">WordCloud</span>
                        </button>
                        <button type="button" @click="setVizTab('dtm')"
                            :class="activeVizTab === 'dtm' ? 'bg-unsoed-blue-800 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="snap-start whitespace-nowrap rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors">
                            <span class="sm:hidden">DTM</span>
                            <span class="hidden sm:inline">Tren Tahunan</span>
                        </button>
                        <button type="button" @click="setVizTab('trend')"
                            :class="activeVizTab === 'trend' ? 'bg-unsoed-blue-800 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="snap-start whitespace-nowrap rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors">
                            <span class="sm:hidden">Trend</span>
                            <span class="hidden sm:inline">Tab Naik Turun</span>
                        </button>
                        <button type="button" @click="setVizTab('mapping')"
                            :class="activeVizTab === 'mapping' ? 'bg-unsoed-blue-800 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="snap-start whitespace-nowrap rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors">
                            <span class="sm:hidden">Peta</span>
                            <span class="hidden sm:inline">Pemetaan Skripsi</span>
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

                <div x-show="activeVizTab === 'dtm'" x-cloak class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-center gap-2.5">
                        <div class="rounded-lg bg-unsoed-blue-100 p-2">
                            <x-app.icon name="presentation-chart-line" class="h-4 w-4 text-unsoed-blue-700" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Tren Topik Berdasarkan Tahun</h2>
                            <p class="text-xs text-gray-500">Line chart proporsi topik per tahun dengan tooltip interaktif.</p>
                        </div>
                    </div>

                    @if (!empty($chartPayload['dtm']['series']))
                        <div class="rounded-xl border border-unsoed-blue-100 bg-unsoed-blue-50 p-4 shadow-sm">
                            <x-charts.line :chart-data="$chartPayload['dtm']" height="h-[20rem] sm:h-[28rem] xl:h-[34rem]" />
                        </div>
                    @else
                        <div class="flex h-[20rem] flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-gray-400 sm:h-[28rem] xl:h-[34rem]">
                            <x-app.icon name="presentation-chart-line" class="mb-3 h-12 w-12 text-gray-200" />
                            <p class="text-sm font-medium">Data DTM belum tersedia.</p>
                            <p class="mt-1 text-xs">{{ $chartPayload['dtm']['missing_reason'] ?? 'Perlu pemetaan dokumen-topik dan tahun skripsi yang valid.' }}</p>
                        </div>
                    @endif
                </div>

                <div x-show="activeVizTab === 'trend'" x-cloak class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-center gap-2.5">
                        <div class="rounded-lg bg-unsoed-blue-100 p-2">
                            <x-app.icon name="arrows-right-left" class="h-4 w-4 text-unsoed-blue-700" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Analisis Topik Naik/Turun</h2>
                            <p class="text-xs text-gray-500">Menampilkan topik yang trennya sedang naik (kanan) atau turun (kiri).</p>
                        </div>
                    </div>

                    @if (!empty($chartPayload['trend']))
                        <div class="rounded-xl border border-unsoed-blue-100 bg-unsoed-blue-50 p-4 shadow-sm">
                            <x-charts.bar type="trend" :chart-data="$chartPayload['trend']" height="h-[24rem] sm:h-[30rem]" />
                        </div>
                    @else
                        <div class="flex h-[16rem] flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-gray-400 sm:h-[20rem]">
                            <x-app.icon name="arrows-right-left" class="mb-3 h-12 w-12 text-gray-200" />
                            <p class="text-sm font-medium">Belum ada data trend terdeteksi.</p>
                        </div>
                    @endif
                </div>

                <div x-show="activeVizTab === 'mapping'" x-cloak class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-center gap-2.5">
                        <div class="rounded-lg bg-unsoed-blue-100 p-2">
                            <x-app.icon name="table-cells" class="h-4 w-4 text-unsoed-blue-700" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Pemetaan Skripsi ke Topik</h2>
                            <p class="text-xs text-gray-500">Melihat hasil klasifikasi topik untuk masing-masing skripsi.</p>
                        </div>
                    </div>

                    @if (!empty($skripsiMapping['topic_summary']))
                        <div class="mb-4 rounded-xl border border-unsoed-blue-100 bg-unsoed-blue-50 p-4 shadow-sm">
                            <x-charts.bar type="distribution" :chart-data="$skripsiMapping['topic_summary']" title="Distribusi Dokumen per Topik" height="h-[16rem] sm:h-[20rem]" />
                        </div>
                    @endif

                    @if (!empty($skripsiMapping['rows']))
                        <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
                            <span class="inline-flex items-center rounded-full border border-unsoed-blue-200 bg-unsoed-blue-50 px-2.5 py-1 font-medium text-unsoed-blue-700">
                                {{ number_format((int) ($skripsiMapping['displayed_rows'] ?? 0)) }} dari {{ number_format((int) ($skripsiMapping['total_rows'] ?? 0)) }} pemetaan
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
                            <p class="text-sm font-medium">Data pemetaan skripsi belum tersedia.</p>
                            <p class="mt-1 text-xs">{{ $skripsiMapping['missing_reason'] ?? 'Belum ada relasi dokumen-topik pada run ini.' }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Daftar Topik --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-lg bg-unsoed-blue-100 p-2">
                            <x-app.icon name="list-bullet" class="h-4 w-4 text-unsoed-blue-700" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Daftar Topik</h2>
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
                Belum ada run selesai yang bisa divisualisasikan. Jalankan training terlebih dahulu pada menu Analisis Topik.
            </div>
        @endif
    </div>

    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-chart-wordcloud@4.4.4/build/index.umd.min.js"></script>

    @endonce
</div>
