<div>
    @section('page-title', 'Topic Modeling')

    <div class="space-y-5 sm:space-y-6" x-data="{ activeTab: 'overview', activePreviewTab: 'final', activePreviewIdx: 0 }">

        {{-- ── Page Header ──────────────────────────────────── --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center rounded-full bg-unsoed-blue-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-unsoed-blue-700">FastAPI Pipeline</span>
                    <h1 class="mt-2 text-2xl font-bold text-gray-900">Topic Modeling</h1>
                    <p class="mt-1.5 text-sm text-gray-500">
                        Pipeline FastAPI-Laravel untuk BERTopic & LDA (preprocessing + training) pada analisis topik skripsi UNSOED.
                    </p>
                </div>
                <div class="w-full rounded-xl border border-gray-200 bg-gray-50 p-2 sm:p-2.5 xl:w-auto">
                    <div class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap xl:w-auto">
                        <div class="w-full sm:w-[200px]">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-500">Model Training</label>
                            <select
                                class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                                wire:model.live="modelType"
                            >
                                <option value="bertopic">BERTopic</option>
                                <option value="lda">LDA</option>
                            </select>
                        </div>
                        <x-ui.button wire:click="buildPreview" wire:loading.attr="disabled" variant="secondary" class="w-full sm:w-auto">
                            <x-app.icon name="arrow-path" class="h-4 w-4" />
                            Refresh preview
                        </x-ui.button>
                        <x-ui.button wire:click="openRunPreprocessingConfirm" wire:loading.attr="disabled" variant="primary"
                            :disabled="(($apiStatus['status'] ?? '') !== 'ok')" class="w-full sm:w-auto">
                            <x-app.icon name="funnel" class="h-4 w-4" />
                            <span wire:loading.remove wire:target="runPreprocessing">Preprocessing</span>
                            <span wire:loading wire:target="runPreprocessing">Processing…</span>
                        </x-ui.button>
                        <x-ui.button type="button" wire:click="openStartTrainingConfirm" wire:loading.attr="disabled" variant="success"
                            :disabled="(($apiStatus['status'] ?? '') !== 'ok') || (!$activeRun) || (!in_array($activeRun?->status ?? '', ['pending','completed','failed']))"
                            class="w-full sm:w-auto">
                            <x-app.icon name="play-circle" class="h-4 w-4" />
                            <span wire:loading.remove wire:target="startTraining">Start Training {{ strtoupper($modelType) }}</span>
                            <span wire:loading wire:target="startTraining">Starting…</span>
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Tabs (reduce scrolling) ─────────────────────────────── --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-2.5 shadow-sm">
            <div class="flex snap-x snap-mandatory gap-2 overflow-x-auto pb-1 pr-1 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                @php
                    $tabs = [
                        'overview' => 'Overview',
                        'preview' => 'Preview',
                        'database' => 'Database',
                        'hasil' => 'Hasil',
                        'pengaturan' => 'Pengaturan',
                    ];
                @endphp
                @foreach ($tabs as $key => $label)
                    <button type="button" @click="activeTab='{{ $key }}'"
                        :class="activeTab === '{{ $key }}' ? 'bg-unsoed-blue-800 text-white shadow-sm' :
                            'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        class="snap-start whitespace-nowrap rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        @if (($apiStatus['status'] ?? '') !== 'ok')
            <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                <x-app.icon variant="o" name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                <div>
                    FastAPI service tidak aktif. Pastikan container <code
                        class="mx-1 rounded bg-amber-100 px-1.5 py-0.5 font-mono">skripsi-fastapi</code>
                    berjalan.
                </div>
            </div>
        @endif

        {{-- ========================== TAB: OVERVIEW ========================== --}}
        <div x-show="activeTab === 'overview'" x-cloak class="space-y-6">

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

            {{-- ── API Status Card (selaras dengan Scraping Manager) ───────── --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 xl:col-span-1">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
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
                        class="self-start font-medium flex items-center transition sm:self-auto !px-2"
                        wire:loading.class="opacity-50" wire:target="checkApiStatus">
                        <x-app.icon name="arrow-path" class="w-4 h-4 mr-1" wire:loading.class="animate-spin" wire:target="checkApiStatus" />
                        Refresh Status
                    </x-ui.button>
                </div>
            </div>

            {{-- ── Dataset Readiness Card ──────────────────────────────── --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 xl:col-span-2">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Kesiapan Dataset</div>
                        <div class="mt-0.5 text-xs text-gray-500">Ringkasan data hasil preprocessing yang siap
                            untuk
                            training.</div>
                    </div>
                    <x-ui.button variant="ghost-primary" size="sm" wire:click="loadDatasetSummary"
                        class="self-start font-medium flex items-center transition sm:self-auto !px-2"
                        wire:loading.class="opacity-50" wire:target="loadDatasetSummary">
                        <x-app.icon name="arrow-path" class="w-4 h-4 mr-1" wire:loading.class="animate-spin" wire:target="loadDatasetSummary" />
                        Refresh
                    </x-ui.button>
                </div>

                @if (($datasetSummary['status'] ?? '') === 'ok')
                    <div class="mt-4 grid grid-cols-2 gap-3 text-center sm:grid-cols-3 xl:grid-cols-6">
                        <div class="rounded-lg border border-gray-200 bg-white p-2.5">
                            <p class="text-xs text-gray-500">Total Skripsi</p>
                            <p class="text-lg font-bold text-gray-800">{{ (int) ($datasetSummary['source_total'] ?? $datasetSummary['total'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white p-2.5">
                            <p class="text-xs text-gray-500">Dataset Training</p>
                            <p class="text-lg font-bold text-gray-800">{{ (int) ($datasetSummary['dataset_total'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-lg border border-unsoed-blue-200 bg-unsoed-blue-50/80 p-2.5">
                            <p class="text-xs text-unsoed-blue-600">Valid BERTopic</p>
                            <p class="text-lg font-bold text-unsoed-blue-800">
                                {{ (int) ($datasetSummary['valid_bertopic'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50/80 p-2.5">
                            <p class="text-xs text-emerald-600">Valid LDA</p>
                            <p class="text-lg font-bold text-emerald-700">
                                {{ (int) ($datasetSummary['valid_lda'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50/80 p-2.5">
                            <p class="text-xs text-amber-600">Ter-drop (dari sumber)</p>
                            <p class="text-lg font-bold text-amber-700">{{ (int) ($datasetSummary['dropped_from_source'] ?? $datasetSummary['dropped'] ?? 0) }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white p-2.5">
                            <p class="text-xs text-gray-500">Rentang Tahun</p>
                            <p class="text-lg font-bold text-gray-800">
                                @if (($datasetSummary['year_min'] ?? null) && ($datasetSummary['year_max'] ?? null))
                                    {{ (int) $datasetSummary['year_min'] }}–{{ (int) $datasetSummary['year_max'] }}
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                    </div>

                    @if (isset($datasetSummary['dropped_within_dataset']))
                        <div class="mt-2 text-[11px] text-gray-500">
                            Drop internal dataset (cleaned/processed kosong):
                            <span class="font-semibold text-gray-700">{{ (int) ($datasetSummary['dropped_within_dataset'] ?? 0) }}</span>
                        </div>
                    @endif

                    @if (((int) ($datasetSummary['dataset_total'] ?? 0)) === 0)
                        <div
                            class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                            Dataset training masih kosong. Jalankan <strong>Preprocessing</strong> dulu.
                        </div>
                    @endif
                @elseif(($datasetSummary['status'] ?? '') === 'unreachable')
                    <div class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        {{ $datasetSummary['message'] ?? 'FastAPI tidak dapat dihubungi. Summary dataset belum bisa ditampilkan.' }}
                    </div>
                @elseif(($datasetSummary['status'] ?? '') === 'error')
                    <div class="mt-3 text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                        {{ $datasetSummary['message'] ?? 'Gagal mengambil summary dataset.' }}
                    </div>
                @else
                    <div class="mt-3 text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                        Summary dataset belum tersedia. Klik <strong>Refresh</strong> untuk memuat ulang.
                    </div>
                @endif
            </div>
            </div>

            @if (!empty($preprocessingDroppedReport))
                @php
                    $dropSummary = collect($preprocessingDroppedReport['dropped_summary'] ?? [])->filter(fn($v) => (int) $v > 0);
                    $dropSamples = $preprocessingDroppedReport['dropped_records_sample'] ?? [];
                    $dropTotal = (int) ($preprocessingDroppedReport['dropped_records_total'] ?? 0);
                    $dropJobId = $preprocessingDroppedReport['job_id'] ?? ($activeRun->fastapi_preprocessing_job_id ?? '-');
                    $reasonLabels = [
                        'dropna_abstract' => 'Abstract kosong/null',
                        'short_abstract' => 'Abstract terlalu pendek',
                        'duplicate_abstract' => 'Duplikat abstract',
                        'year_out_of_range' => 'Tahun di luar rentang',
                        'empty_after_preprocessing' => 'Kosong setelah preprocessing',
                    ];
                @endphp

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Laporan Data Ter-drop (Preprocessing)</div>
                            <div class="mt-0.5 text-xs text-gray-500">
                                Job: <span class="font-mono">{{ $dropJobId }}</span>
                            </div>
                        </div>
                        <button wire:click="loadPreprocessingDroppedReport"
                            class="self-start text-xs text-unsoed-blue-600 hover:text-unsoed-blue-800 font-medium flex items-center transition sm:self-auto"
                            wire:loading.class="opacity-50" wire:target="loadPreprocessingDroppedReport">
                            <x-app.icon name="arrow-path" class="w-4 h-4 mr-1" wire:loading.class="animate-spin" wire:target="loadPreprocessingDroppedReport" />
                            Refresh
                        </button>
                    </div>

                    @if ($dropTotal === 0)
                        <div class="mt-3 text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
                            Tidak ada data yang ter-drop pada preprocessing terakhir.
                        </div>
                    @else
                        <div class="mt-3 grid grid-cols-2 gap-2 text-center sm:grid-cols-6">
                            <div class="col-span-2 rounded-lg border border-amber-200 bg-amber-50 p-2 sm:col-span-1">
                                <p class="text-[11px] text-amber-600">Total Drop</p>
                                <p class="text-lg font-bold text-amber-700">{{ $dropTotal }}</p>
                            </div>
                            @foreach ($dropSummary as $reason => $count)
                                <div class="rounded-lg border border-gray-200 bg-white p-2">
                                    <p class="text-[11px] text-gray-500">{{ $reasonLabels[$reason] ?? $reason }}</p>
                                    <p class="text-lg font-bold text-gray-700">{{ (int) $count }}</p>
                                </div>
                            @endforeach
                        </div>

                        @if (count($dropSamples) > 0)
                            <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-xs">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-gray-500">ID</th>
                                            <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-gray-500">Tahun</th>
                                            <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-gray-500">Reason</th>
                                            <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-gray-500">Judul</th>
                                            <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-gray-500">Abstract Preview</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        @foreach (array_slice($dropSamples, 0, 25) as $s)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-2 text-gray-700">{{ $s['id'] ?? '-' }}</td>
                                                <td class="px-3 py-2 text-gray-700">{{ $s['year'] ?? '-' }}</td>
                                                <td class="px-3 py-2 text-gray-700">{{ $reasonLabels[$s['reason'] ?? ''] ?? ($s['reason'] ?? '-') }}</td>
                                                <td class="px-3 py-2 text-gray-700">{{ $s['title'] ?? '-' }}</td>
                                                <td class="px-3 py-2 text-gray-600">{{ $s['abstract_preview'] ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if (count($dropSamples) > 25)
                                <div class="mt-2 text-xs text-gray-500">
                                    Menampilkan 25 sample pertama dari total sample yang tersimpan di FastAPI.
                                </div>
                            @endif
                        @endif
                    @endif
                </div>
            @endif

            {{-- ---- Pipeline Progress Steps ---- --}}
            <x-ui.card>
                <h2 class="mb-4 text-sm font-semibold text-gray-900">Status Pipeline</h2>
                <div class="flex items-start gap-0">
                    @php
                        $steps = [
                            [
                                'label' => 'Preprocessing',
                                'desc' => 'Clean + tokenize + stem',
                                'statuses' => ['preprocessing', 'pending', 'training', 'completed'],
                            ],
                            [
                                'label' => 'Training',
                                'desc' => 'BERTopic (UMAP/HDBSCAN) atau LDA',
                                'statuses' => ['training', 'completed'],
                            ],
                            ['label' => 'Selesai', 'desc' => 'Hasil tersimpan di DB', 'statuses' => ['completed']],
                        ];
                        $currentStatus = $activeRun?->status ?? 'idle';
                    @endphp
                    @foreach ($steps as $i => $step)
                        @php $active = in_array($currentStatus, $step['statuses']); @endphp
                        <div class="flex flex-1 flex-col items-center text-center">
                            <div @class([
                                'flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold transition-all duration-300',
                                'bg-unsoed-blue-800 text-white shadow-md ring-4 ring-unsoed-blue-100' => $active,
                                'bg-gray-100 text-gray-400' => !$active,
                            ])>{{ $i + 1 }}</div>
                            <div
                                class="mt-2 text-xs font-semibold {{ $active ? 'text-unsoed-blue-800' : 'text-gray-400' }}">
                                {{ $step['label'] }}
                            </div>
                            <div class="text-xs text-gray-400">{{ $step['desc'] }}</div>
                        </div>
                        @if (!$loop->last)
                            <div
                                class="mt-4 h-0.5 flex-1 {{ in_array($currentStatus, $steps[$i + 1]['statuses']) ? 'bg-unsoed-blue-300' : 'bg-gray-200' }} transition-all duration-500">
                            </div>
                        @endif
                    @endforeach
                </div>
            </x-ui.card>

        </div>

        {{-- ===== STATUS ALERT ===== --}}
        @if ($statusMessage)
            <x-ui.alert type="{{ $statusType }}" message="{{ $statusMessage }}" />
        @endif

        {{-- ========================== TAB: PREVIEW ========================== --}}
        <div x-show="activeTab === 'preview'" x-cloak class="space-y-6">

            <x-ui.card no-padding>
                <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Preview Preprocessing Pipeline</h2>
                        <p class="mt-0.5 text-xs text-gray-500">
                            5 sampel abstrak dari tabel <code class="font-mono">topic_model_datasets</code> — menampilkan setiap langkah pipeline secara transparan.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-gray-50 p-1">
                        @foreach (['final' => 'BERTopic Input', 'lda' => 'LDA Input', 'raw' => 'Raw', 'cleaned' => 'Cleaned', 'tokens' => 'Tokens'] as $tab => $label)
                            <button type="button" @click="activePreviewTab = '{{ $tab }}'"
                                :class="activePreviewTab === '{{ $tab }}'
                                    ?
                                    'bg-unsoed-blue-800 text-white' :
                                    'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                class="rounded-full px-2.5 py-1.5 text-xs font-medium transition-colors">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    @forelse($previewRows as $idx => $row)
                        <div class="px-5 py-4">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <div class="text-xs font-semibold text-gray-800 line-clamp-1">
                                    #{{ $row['id'] }} — {{ $row['title'] }}
                                </div>
                                <span
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{{ $row['year'] }}</span>
                            </div>

                            <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-700 leading-relaxed">
                                <div x-show="activePreviewTab === 'raw'" class="break-words">
                                    <span
                                        class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">Teks
                                        Abstrak Asli</span>
                                    {{ Str::limit($row['raw'], 350) }}
                                </div>
                                <div x-show="activePreviewTab === 'cleaned'" class="break-words">
                                    <span
                                        class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">Setelah
                                        lowercase + hapus URL/angka/tanda baca</span>
                                    {{ Str::limit($row['cleaned'], 350) }}
                                </div>
                                <div x-show="activePreviewTab === 'tokens'" class="break-words">
                                    <span
                                        class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">Tokenized
                                        (50 pertama)
                                    </span>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach (array_slice($row['tokenized'], 0, 50) as $tok)
                                            <span
                                                class="rounded bg-white px-1.5 py-0.5 ring-1 ring-gray-200">{{ $tok }}</span>
                                        @endforeach
                                        @if (count($row['tokenized']) > 50)
                                            <span class="text-gray-400">+{{ count($row['tokenized']) - 50 }}
                                                lagi…</span>
                                        @endif
                                    </div>
                                </div>
                                <div x-show="activePreviewTab === 'final'" class="break-words">
                                    <span
                                        class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                                        BERTopic Input — Soft clean (natural, tanpa stemming/stopword removal)
                                    </span>
                                    {{ Str::limit($row['final_cleaned_text'], 350) }}
                                    <div
                                        class="mt-2 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-[10px] text-blue-700">
                                        IndoSBERT butuh teks natural — stopword & stemming dihandle oleh c-TF-IDF
                                        vectorizer di dalam BERTopic.
                                    </div>
                                </div>
                                <div x-show="activePreviewTab === 'lda'" class="break-words">
                                    <span
                                        class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                                        LDA Input — Tokenized + stopword removal (tanpa stemming lokal)
                                    </span>
                                    {{ Str::limit($row['final_processed_text'], 350) }}
                                    <div
                                        class="mt-2 rounded-md border border-emerald-100 bg-emerald-50 px-3 py-2 text-[10px] text-emerald-700">
                                        Stemming & finalisasi processed_text tetap dikerjakan di FastAPI preprocessing.
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-gray-400">
                            <x-app.icon name="funnel" class="mx-auto mb-3 h-10 w-10 text-gray-300" />
                            Belum ada data abstrak di topic_model_datasets. Jalankan preprocessing terlebih dahulu.
                        </div>
                    @endforelse
                </div>
            </x-ui.card>

        </div>

        {{-- ========================== TAB: DATABASE ========================== --}}
        <div x-show="activeTab === 'database'" x-cloak class="space-y-6">

            <x-ui.card no-padding>
                <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Hasil Preprocessing (Database)</h2>
                        <p class="mt-0.5 text-xs text-gray-500">
                            Data <code class="font-mono">cleaned_text</code> (BERTopic) dan <code
                                class="font-mono">processed_text</code> yang sudah tersimpan di tabel
                            <code class="font-mono">skripsi</code>.
                        </p>
                    </div>
                    <button wire:click="loadDbPreprocessedRows" wire:loading.attr="disabled"
                        class="self-start text-xs text-unsoed-blue-600 hover:text-unsoed-blue-800 font-medium flex items-center transition sm:self-auto"
                        wire:loading.class="opacity-50" wire:target="loadDbPreprocessedRows">
                        <x-app.icon name="arrow-path" class="w-4 h-4 mr-1" wire:loading.class="animate-spin" wire:target="loadDbPreprocessedRows" />
                        Refresh
                    </button>
                </div>

                @if (count($dbPreprocessedRows) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="w-20 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-4">
                                        ID</th>
                                    <th
                                        class="w-20 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-4">
                                        Tahun</th>
                                    <th
                                        class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-4">
                                        Judul</th>
                                    <th
                                        class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-4">
                                        cleaned_text (BERTopic)</th>
                                    <th
                                        class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-4">
                                        processed_text</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($dbPreprocessedRows as $r)
                                    <tr class="hover:bg-gray-50 transition-colors align-top">
                                        <td class="px-3 py-3 text-gray-600 sm:px-4">{{ $r['id'] }}</td>
                                        <td class="px-3 py-3 text-gray-600 sm:px-4">{{ $r['year'] ?? '-' }}</td>
                                        <td class="px-3 py-3 sm:px-4">
                                            <div class="text-xs font-semibold text-gray-800 line-clamp-2">
                                                {{ $r['title'] ?? '-' }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 sm:px-4">
                                            <div class="text-xs text-gray-700 whitespace-pre-wrap break-words">
                                                {{ Str::limit($r['cleaned_text'] ?? '', 250) }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 sm:px-4">
                                            <div class="text-xs text-gray-700 whitespace-pre-wrap break-words">
                                                {{ Str::limit($r['processed_text'] ?? '', 250) }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between px-5 py-3 border-t border-gray-200">
                        <div class="text-xs text-gray-500">
                            Menampilkan {{ count($dbPreprocessedRows) }} data terbaru.
                        </div>
                        @if ($dbPreprocessedHasMore)
                            <button wire:click="loadMoreDbPreprocessedRows" wire:loading.attr="disabled"
                                class="px-3 py-1.5 bg-unsoed-blue-50 hover:bg-unsoed-blue-100 text-unsoed-blue-600 text-xs font-medium rounded-lg border border-unsoed-blue-200 transition"
                                wire:loading.class="opacity-50" wire:target="loadMoreDbPreprocessedRows">
                                Load more
                            </button>
                        @endif
                    </div>
                @else
                    <div class="px-5 py-8 text-center text-sm text-gray-400">
                        <x-app.icon name="funnel" class="mx-auto mb-3 h-10 w-10 text-gray-300" />
                        Belum ada hasil preprocessing tersimpan di database.
                        <div class="mt-1 text-xs">Jalankan <strong>Preprocessing</strong> dulu.</div>
                    </div>
                @endif
            </x-ui.card>

        </div>

        {{-- ========================== TAB: HASIL ========================== --}}
        <div x-show="activeTab === 'hasil'" x-cloak class="space-y-6">

            <x-ui.card title="Hasil Topic Modeling" description="Run aktif — topik tersimpan di database.">
                @if ($activeRun && $activeRun->status === 'completed')
                    {{-- Model actions (FastAPI artifacts) --}}
                    @if ($activeRun->fastapi_training_job_id)
                        <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <x-ui.button wire:click="downloadModel" wire:loading.attr="disabled" variant="secondary">
                                <x-app.icon name="arrow-down-tray" class="h-4 w-4" />
                                Download Model
                            </x-ui.button>
                            @if (($activeRun->model_type ?? '') === 'bertopic')
                                <x-ui.button wire:click="openTestModelWithDatasetConfirm" wire:loading.attr="disabled" variant="secondary">
                                    <x-app.icon name="beaker" class="h-4 w-4" />
                                    <span wire:loading.remove wire:target="testModelWithDataset">Test Model (Dataset)</span>
                                    <span wire:loading wire:target="testModelWithDataset">Testing…</span>
                                </x-ui.button>
                            @endif
                            <div class="text-xs text-gray-500 sm:ml-auto">Job ID: {{ $activeRun->fastapi_training_job_id }}</div>
                        </div>

                        @if (($activeRun->model_type ?? '') === 'bertopic' && !empty($modelTestDatasetResult) && !isset($modelTestDatasetResult['status']))
                            @php
                                $stored = $modelTestDatasetResult['stored_metrics'] ?? [];
                                $retest = $modelTestDatasetResult['retest_metrics'] ?? [];
                                $same = $modelTestDatasetResult['same'] ?? [];
                                $dataset = $modelTestDatasetResult['dataset'] ?? [];
                                $ok = ($same['coherence_cv'] ?? false) === true && ($same['topic_diversity'] ?? false) === true;
                            @endphp
                            <div class="mb-5 rounded-xl border {{ $ok ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="text-sm font-semibold {{ $ok ? 'text-emerald-800' : 'text-amber-800' }}">
                                        {{ $ok ? 'Hasil test: sama dengan training' : 'Hasil test: ada perbedaan dari training' }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Dataset: {{ (int) ($dataset['used'] ?? 0) }}/{{ (int) ($dataset['total'] ?? 0) }} dipakai
                                    </div>
                                </div>
                                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3 text-xs">
                                    <div class="rounded-lg bg-white/70 border border-gray-200 p-3">
                                        <div class="font-semibold text-gray-700">Training (stored)</div>
                                        <div class="mt-1 text-gray-600">Coherence (C_v): <span class="font-mono">{{ $stored['coherence_cv'] ?? '-' }}</span></div>
                                        <div class="text-gray-600">Diversity: <span class="font-mono">{{ $stored['topic_diversity'] ?? '-' }}</span></div>
                                        <div class="text-gray-600">#Topik: <span class="font-mono">{{ $stored['num_topics'] ?? '-' }}</span></div>
                                    </div>
                                    <div class="rounded-lg bg-white/70 border border-gray-200 p-3">
                                        <div class="font-semibold text-gray-700">Re-test (dataset sekarang)</div>
                                        <div class="mt-1 text-gray-600">Coherence (C_v): <span class="font-mono">{{ $retest['coherence_cv'] ?? '-' }}</span></div>
                                        <div class="text-gray-600">Diversity: <span class="font-mono">{{ $retest['topic_diversity'] ?? '-' }}</span></div>
                                        <div class="text-gray-600">#Topik: <span class="font-mono">{{ $retest['num_topics'] ?? '-' }}</span></div>
                                    </div>
                                    <div class="rounded-lg bg-white/70 border border-gray-200 p-3">
                                        <div class="font-semibold text-gray-700">Kecocokan</div>
                                        <div class="mt-1 text-gray-600">Coherence match: <span class="font-mono">{{ ($same['coherence_cv'] ?? null) === true ? 'yes' : 'no' }}</span></div>
                                        <div class="text-gray-600">Diversity match: <span class="font-mono">{{ ($same['topic_diversity'] ?? null) === true ? 'yes' : 'no' }}</span></div>
                                        <div class="text-gray-600">Keyword match: <span class="font-mono">{{ isset($same['keyword_match_ratio']) ? round(((float) $same['keyword_match_ratio']) * 100) . '%' : '-' }}</span></div>
                                    </div>
                                </div>
                                <div class="mt-2 text-xs text-gray-500">
                                    Catatan: jika dataset di DB berubah setelah training, hasil re-test bisa ikut berubah.
                                </div>
                            </div>
                        @endif
                    @endif

                    {{-- Stats row --}}
                    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
                        @php
                            $resultStats = [
                                [
                                    'label' => 'Topik',
                                    'value' => $activeRun->num_topics ?? '-',
                                    'tone' => 'primary',
                                ],
                                [
                                    'label' => 'Dokumen',
                                    'value' => $activeRun->total_documents ?? '-',
                                    'tone' => 'default',
                                ],
                                [
                                    'label' => 'Outlier',
                                    'value' => $activeRun->num_outliers ?? '-',
                                    'tone' => 'default',
                                ],
                                [
                                    'label' => 'Coherence (C_v)',
                                    'value' => $activeRun->coherence_cv !== null ? number_format((float) $activeRun->coherence_cv, 4) : '-',
                                    'tone' => 'success',
                                ],
                                [
                                    'label' => 'Topic Diversity',
                                    'value' => $activeRun->topic_diversity !== null ? number_format((float) $activeRun->topic_diversity, 4) : '-',
                                    'tone' => 'default',
                                ],
                                [
                                    'label' => 'Durasi',
                                    'value' => $activeRun->training_duration_seconds ? round($activeRun->training_duration_seconds) . 's' : '-',
                                    'tone' => 'default',
                                ],
                            ];
                        @endphp

                        @foreach ($resultStats as $stat)
                            <div @class([
                                'flex h-full flex-col justify-center rounded-xl border p-3 text-center shadow-sm',
                                'border-unsoed-blue-200 bg-unsoed-blue-50/80' => $stat['tone'] === 'primary',
                                'border-emerald-200 bg-emerald-50/80' => $stat['tone'] === 'success',
                                'border-gray-200 bg-white' => $stat['tone'] === 'default',
                            ])>
                                <div @class([
                                    'text-2xl font-bold',
                                    'text-unsoed-blue-800' => $stat['tone'] === 'primary',
                                    'text-emerald-700' => $stat['tone'] === 'success',
                                    'text-gray-800' => $stat['tone'] === 'default',
                                ])>
                                    {{ $stat['value'] }}
                                </div>
                                <div @class([
                                    'mt-0.5 text-xs',
                                    'text-unsoed-blue-600' => $stat['tone'] === 'primary',
                                    'text-emerald-600' => $stat['tone'] === 'success',
                                    'text-gray-500' => $stat['tone'] === 'default',
                                ])>
                                    {{ $stat['label'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Topics table --}}
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="w-16 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-4">
                                        Topik</th>
                                    <th
                                        class="w-20 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-4">
                                        Dokumen</th>
                                    <th
                                        class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-4">
                                        Kata Kunci Utama</th>
                                    <th
                                        class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-4">
                                        Skripsi Terkait</th>
                                    <th
                                        class="w-28 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-4">
                                        Mapping</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($activeRun->topics->sortBy('topic_id') as $t)
                                    @php
                                        $linkedDocs = $t->documentLinks->take(5);
                                    @endphp
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-3 py-3 sm:px-4">
                                            <span
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-unsoed-blue-100 text-xs font-bold text-unsoed-blue-800">
                                                {{ $t->topic_id }}
                                            </span>
                                            @if (filled($t->custom_name))
                                                <div class="mt-1 text-xs font-semibold text-unsoed-blue-800 line-clamp-1" title="{{ $t->custom_name }}">
                                                    {{ $t->custom_name }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-gray-600 sm:px-4">{{ $t->count }}</td>
                                        <td class="px-3 py-3 sm:px-4">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach (array_slice($t->top_words ?? [], 0, 15) as $word)
                                                    <span
                                                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ $word }}</span>
                                                @endforeach
                                            </div>
                                            @if (filled($t->representation_description))
                                                <div class="mt-1.5 max-w-xs text-xs leading-relaxed text-gray-500 line-clamp-2" title="{{ $t->representation_description }}">
                                                    {{ $t->representation_description }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 sm:px-4">
                                            @if ($linkedDocs->isEmpty())
                                                <div class="text-xs text-gray-400">Belum ada mapping dokumen</div>
                                            @else
                                                <div class="space-y-1">
                                                    @foreach ($linkedDocs as $link)
                                                        <div class="text-xs text-gray-700 line-clamp-1">
                                                            #{{ $link->skripsi_id }}
                                                            @if ($link->skripsi)
                                                                - {{ $link->skripsi->title }}
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                    @if ($t->documentLinks->count() > 5)
                                                        <div class="text-[11px] text-gray-400">+{{ $t->documentLinks->count() - 5 }} skripsi lain</div>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 sm:px-4">
                                            @if ($t->documentLinks->isNotEmpty())
                                                <button
                                                    type="button"
                                                    wire:click="openTopicMappingsModal({{ $t->id }})"
                                                    class="rounded-lg border border-unsoed-blue-200 bg-unsoed-blue-50 px-2.5 py-1 text-xs font-medium text-unsoed-blue-700 transition hover:bg-unsoed-blue-100"
                                                >
                                                    Lihat List
                                                </button>
                                            @else
                                                <span class="text-xs text-gray-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif($activeRun && $activeRun->status === 'training')
                    <div class="flex flex-col items-center py-8 text-center">
                        <div
                            class="mb-4 h-10 w-10 animate-spin rounded-full border-4 border-gray-200 border-t-unsoed-blue-800">
                        </div>
                        <div class="text-sm font-medium text-gray-700">Training sedang berjalan…</div>
                        <div class="mt-1 text-xs text-gray-500">{{ $trainingMessage }}</div>
                    </div>
                @elseif($activeRun && $activeRun->status === 'failed')
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <div class="font-semibold">Training gagal</div>
                        <div class="mt-1 text-xs">{{ $activeRun->error_message }}</div>
                    </div>
                @else
                    <div class="flex flex-col items-center py-10 text-center">
                        <x-app.icon name="funnel" class="mb-3 h-12 w-12 text-gray-200" />
                        <div class="text-sm font-medium text-gray-500">Belum ada hasil training</div>
                        <div class="mt-1 text-xs text-gray-400">Jalankan Preprocessing → Start Training untuk
                            memulai.</div>
                    </div>
                @endif
            </x-ui.card>

            {{-- ---- Riwayat Run ---- --}}
            <x-ui.card no-padding>
                <div class="border-b border-gray-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Riwayat Run</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($latestRuns as $r)
                        @php
                            $badgeType = match ($r->status) {
                                'completed' => 'success',
                                'training' => 'info',
                                'preprocessing' => 'warning',
                                'failed' => 'error',
                                default => 'default',
                            };
                        @endphp
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <div>
                                <div class="text-xs font-semibold text-gray-800">Run #{{ $r->id }}</div>
                                <div class="text-xs text-gray-400">{{ $r->created_at?->format('d/m/Y H:i') }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $r->num_topics ?? 0 }} topik | C_v:
                                    {{ $r->coherence_cv !== null ? number_format((float) $r->coherence_cv, 4) : '-' }}
                                    | TD:
                                    {{ $r->topic_diversity !== null ? number_format((float) $r->topic_diversity, 4) : '-' }}
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <x-ui.badge type="{{ $badgeType }}">{{ ucfirst($r->status) }}</x-ui.badge>
                                <div class="text-xs text-gray-400">{{ $r->topics_count }} topik</div>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-6 text-center text-xs text-gray-400">Belum ada riwayat run.</div>
                    @endforelse
                </div>
            </x-ui.card>
        </div>
{{-- ========================== TAB: PENGATURAN ========================== --}}
<div x-show="activeTab === 'pengaturan'" x-cloak class="space-y-6">

    {{-- ---- BERTopic Settings ---- --}}
    <x-ui.card no-padding>
        <div class="border-b border-gray-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Konfigurasi BERTopic</h2>
            <p class="mt-0.5 text-xs text-gray-500">Disederhanakan mengikuti rekomendasi official BERTopic parameter tuning.</p>
        </div>
        <div class="space-y-5 px-5 py-4">
            <x-ui.alert type="info">
                Parameter tuning yang aktif mengikuti halaman official BERTopic: <strong>top_n_words</strong>,
                <strong>n_gram_range</strong>, <strong>min_topic_size</strong>, <strong>nr_topics</strong>,
                UMAP (<strong>n_neighbors</strong>, <strong>n_components</strong>, <strong>metric</strong>),
                dan HDBSCAN (<strong>min_cluster_size</strong>, <strong>min_samples</strong>, <strong>metric</strong>).
            </x-ui.alert>

            <div>
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">BERTopic</div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">min_topic_size</label>
                        <input type="number" min="2"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="bertopicParams.min_topic_size" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">nr_topics</label>
                        <input type="text" placeholder="auto | minimal 8 (contoh: auto | 8 | 12 | 16)"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="bertopicParams.nr_topics" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">top_n_words</label>
                        <input type="number" min="15"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="bertopicParams.top_n_words" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">n_gram_range_min</label>
                        <input type="number" min="1"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="bertopicParams.n_gram_range.0" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">n_gram_range_max</label>
                        <input type="number" min="1"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="bertopicParams.n_gram_range.1" />
                    </div>
                </div>
            </div>

            <div>
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">UMAP</div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">n_neighbors</label>
                        <input type="number" min="2"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="bertopicParams.umap_params.n_neighbors" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">n_components</label>
                        <input type="number" min="2"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="bertopicParams.umap_params.n_components" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">metric</label>
                        <input type="text"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="bertopicParams.umap_params.metric" />
                    </div>
                </div>
            </div>

            <div>
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">HDBSCAN</div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">min_cluster_size</label>
                        <input type="number" min="2"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="bertopicParams.hdbscan_params.min_cluster_size" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">min_samples</label>
                        <input type="number" min="1"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="bertopicParams.hdbscan_params.min_samples" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">metric</label>
                        <input type="text"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="bertopicParams.hdbscan_params.metric" />
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-3">
                <div class="text-xs text-gray-400">Simpan sebagai default di database (BERTopic per akun).</div>
                <div class="flex items-center gap-2">
                    <x-ui.button wire:click="resetTrainingParamsToNotebookBest" wire:loading.attr="disabled" variant="secondary">
                        <x-app.icon name="arrow-uturn-left" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="resetTrainingParamsToNotebookBest">Reset ke Best Eksperimen</span>
                        <span wire:loading wire:target="resetTrainingParamsToNotebookBest">Reset…</span>
                    </x-ui.button>

                    <x-ui.button wire:click="saveTrainingParams" wire:loading.attr="disabled" variant="secondary">
                        <x-app.icon name="bookmark-square" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="saveTrainingParams">Simpan Parameter</span>
                        <span wire:loading wire:target="saveTrainingParams">Menyimpan…</span>
                    </x-ui.button>
                </div>
            </div>

            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Upload JSON Params</div>
                        <div class="mt-1 text-xs text-gray-500">
                            Upload JSON untuk update default BERTopic di database (tanpa menjalankan training).
                        </div>
                    </div>
                    <a
                        class="inline-flex items-center gap-2 rounded-lg border border-unsoed-blue-200 bg-white px-3 py-2 text-xs font-semibold text-unsoed-blue-700 hover:bg-unsoed-blue-50"
                        href="{{ route('jurusan.topic-modeling.settings.template.download') }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <x-app.icon name="arrow-down-tray" class="h-4 w-4" />
                        Download Template JSON
                    </a>
                </div>
                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input
                        type="file"
                        accept="application/json,.json"
                        class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                        wire:model="bertopicParamsJsonFile"
                    />
                    <x-ui.button wire:click="uploadBertopicParamsJson" wire:loading.attr="disabled" variant="primary" class="sm:w-auto">
                        <x-app.icon name="arrow-up-tray" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="uploadBertopicParamsJson">Upload JSON</span>
                        <span wire:loading wire:target="uploadBertopicParamsJson">Mengunggah...</span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- ---- LDA Settings ---- --}}
    <x-ui.card no-padding>
        <div class="border-b border-gray-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Konfigurasi LDA</h2>
            <p class="mt-0.5 text-xs text-gray-500">Parameter dasar LDA (Gensim) untuk training topik.</p>
        </div>
        <div class="space-y-5 px-5 py-4">
            <x-ui.alert type="info">
                Pastikan preprocessing menghasilkan <strong>processed_text</strong> sebelum training LDA.
            </x-ui.alert>

            <div>
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">LDA</div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">num_topics</label>
                        <input type="number" min="2"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="ldaParams.num_topics" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">passes</label>
                        <input type="number" min="1"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="ldaParams.passes" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">iterations</label>
                        <input type="number" min="1"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="ldaParams.iterations" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">chunksize</label>
                        <input type="number" min="1"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="ldaParams.chunksize" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">alpha</label>
                        <input type="text" placeholder="asymmetric | symmetric"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="ldaParams.alpha" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">eta</label>
                        <input type="text" placeholder="symmetric | null"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="ldaParams.eta" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">no_below</label>
                        <input type="number" min="1"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="ldaParams.no_below" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">no_above</label>
                        <input type="number" min="0.0" max="1.0" step="0.01"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="ldaParams.no_above" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">random_state</label>
                        <input type="number" min="0"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                            wire:model.live="ldaParams.random_state" />
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-3">
                <div class="text-xs text-gray-400">Simpan sebagai default di database (LDA per akun).</div>
                <div class="flex items-center gap-2">
                    <x-ui.button wire:click="resetTrainingParamsToNotebookBest" wire:loading.attr="disabled" variant="secondary">
                        <x-app.icon name="arrow-uturn-left" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="resetTrainingParamsToNotebookBest">Reset ke Best Eksperimen</span>
                        <span wire:loading wire:target="resetTrainingParamsToNotebookBest">Reset…</span>
                    </x-ui.button>

                    <x-ui.button wire:click="saveTrainingParams" wire:loading.attr="disabled" variant="secondary">
                        <x-app.icon name="bookmark-square" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="saveTrainingParams">Simpan Parameter</span>
                        <span wire:loading wire:target="saveTrainingParams">Menyimpan…</span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- ---- Preprocessing Note ---- --}}
    <x-ui.alert type="warning">
        <div class="font-semibold">Strategi preprocessing:</div>
        <ul class="mt-1 space-y-0.5 text-amber-700">
            <li>• <strong>BERTopic:</strong> soft clean — tidak hapus stopword, tidak stemming</li>
            <li>• <strong>LDA:</strong> tokenized + stopword removal + stemming (di FastAPI preprocessing)</li>
        </ul>
    </x-ui.alert>

</div>

{{-- ========================== STICKY JOB STATUS (only when running) ========================== --}}
@php
    $showPreprocessingCard = (bool) $preprocessingJobId || (($activeRun?->status ?? '') === 'preprocessing');
    $showTrainingCard = (((bool) $trainingJobId || (($activeRun?->status ?? '') === 'training'))
        && (($activeRun?->status ?? '') !== 'failed'));
    $stickyCardCount = ($showPreprocessingCard ? 1 : 0) + ($showTrainingCard ? 1 : 0);
@endphp
@if ($stickyCardCount > 0)
    <div @class([
        'grid grid-cols-1 gap-4',
        'xl:grid-cols-2' => $stickyCardCount > 1,
    ])>
        @if ($showPreprocessingCard)
            <div class="bg-white rounded-xl shadow-sm border-2 border-unsoed-blue-200 p-6"
                wire:poll.2s="pollPreprocessingProgress">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center">
                        <div class="bg-unsoed-blue-100 rounded-lg p-2 mr-3">
                            <x-app.icon variant="o" name="arrow-path" class="w-5 h-5 text-unsoed-blue-600 animate-spin" />
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Preprocessing Sedang Berjalan</h2>
                            <p class="text-xs text-gray-400">Job ID: {{ $preprocessingJobId !== '' ? $preprocessingJobId : 'Menunggu sinkronisasi...' }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="openCancelPreprocessingConfirm"
                        class="w-full justify-center px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-medium rounded-lg border border-red-200 transition flex items-center sm:w-auto"
                        title="Batalkan Preprocessing">
                        <x-app.icon name="stop-circle" class="w-4 h-4 mr-1" />
                        Batalkan
                    </button>
                </div>
                <div class="mb-3">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600 font-medium">Progress</span>
                        <span class="text-unsoed-blue-600 font-bold">{{ $preprocessingProgress }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                        <div class="h-3 rounded-full transition-all duration-500 ease-out bg-gradient-to-r from-unsoed-blue-500 to-unsoed-blue-600"
                            style="width: {{ max(0, min(100, (int) $preprocessingProgress)) }}%">
                            @if ($preprocessingProgress > 5 && $preprocessingProgress < 100)
                                <div
                                    class="h-full w-full bg-gradient-to-r from-transparent via-white/20 to-transparent animate-pulse">
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500 flex items-center">
                    <x-app.icon variant="o" name="arrow-path" class="w-3 h-3 mr-1 text-unsoed-blue-500 animate-pulse" />
                    {{ $preprocessingMessage ?: 'Memproses…' }}
                </p>
            </div>
        @endif

        @if ($showTrainingCard)
            <div class="bg-white rounded-xl shadow-sm border-2 border-unsoed-blue-200 p-6"
                wire:poll.5s="pollTrainingProgress">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center">
                        <div class="bg-unsoed-blue-100 rounded-lg p-2 mr-3">
                            <x-app.icon variant="o" name="arrow-path" class="w-5 h-5 text-unsoed-blue-600 animate-spin" />
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Training Sedang Berjalan</h2>
                            <p class="text-xs text-gray-400">Job ID: {{ $trainingJobId !== '' ? $trainingJobId : 'Menunggu sinkronisasi...' }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="openCancelTrainingConfirm"
                        class="w-full justify-center px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-medium rounded-lg border border-red-200 transition flex items-center sm:w-auto"
                        title="Batalkan Training">
                        <x-app.icon name="stop-circle" class="w-4 h-4 mr-1" />
                        Batalkan
                    </button>
                </div>

                <div class="mb-3">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600 font-medium">Progress</span>
                        <span class="text-unsoed-blue-600 font-bold">{{ $trainingProgress }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                        <div class="h-3 rounded-full transition-all duration-500 ease-out
                                    {{ $trainingProgress < 100 ? 'bg-gradient-to-r from-unsoed-blue-500 to-unsoed-blue-600' : 'bg-green-500' }}"
                            style="width: {{ max(0, min(100, (int) $trainingProgress)) }}%">
                            @if ($trainingProgress > 5 && $trainingProgress < 100)
                                <div
                                    class="h-full w-full bg-gradient-to-r from-transparent via-white/20 to-transparent animate-pulse">
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500 flex items-center">
                    <x-app.icon variant="o" name="arrow-path" class="w-3 h-3 mr-1 text-unsoed-blue-500 animate-pulse" />
                    {{ $trainingMessage ?: 'Memproses…' }}
                </p>
            </div>
        @endif
    </div>
@endif

<div
    x-data="{ open: @entangle('showTopicMappingsModal').live }"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
>
    <div
        x-show="open"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm"
        @click="$wire.closeTopicMappingsModal()"
    ></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div
            x-show="open"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl"
            @click.stop
        >
            <div class="bg-gradient-to-r from-unsoed-blue-700 to-unsoed-blue-600 px-4 py-4 sm:px-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/20">
                                <x-app.icon variant="s" name="book-open" class="h-4 w-4 text-white" />
                            </div>
                            <h3 class="text-base font-bold text-white">Mapping Skripsi Topik {{ $selectedTopicModalTopicId }}</h3>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($selectedTopicModalTopWords as $word)
                                <span class="rounded-full bg-white/15 px-2 py-0.5 text-xs font-medium text-white">{{ $word }}</span>
                            @endforeach
                        </div>
                    </div>
                    <button
                        type="button"
                        wire:click="closeTopicMappingsModal"
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-white transition hover:bg-white/25"
                        aria-label="Tutup modal"
                    >
                        <x-app.icon name="x-mark" class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div class="max-h-[65vh] overflow-auto px-4 py-4 sm:px-6 sm:py-5">
                @if (count($selectedTopicModalDocs) === 0)
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-500">
                        Belum ada dokumen yang ter-mapping untuk topik ini.
                    </div>
                @else
                    <div class="mb-3 text-xs text-gray-500">Total dokumen: <span class="font-semibold text-gray-700">{{ count($selectedTopicModalDocs) }}</span></div>
                    <div class="overflow-hidden rounded-xl border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-24 px-2.5 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-3">ID</th>
                                    <th class="w-20 px-2.5 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-3">Tahun</th>
                                    <th class="px-2.5 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-3">Judul Skripsi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($selectedTopicModalDocs as $doc)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-2.5 py-2 text-gray-700 sm:px-3">#{{ $doc['skripsi_id'] }}</td>
                                        <td class="px-2.5 py-2 text-gray-600 sm:px-3">{{ $doc['year'] ?? '-' }}</td>
                                        <td class="px-2.5 py-2 text-gray-800 sm:px-3">{{ $doc['title'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════
     Confirm: Jalankan Preprocessing
═════════════════════════════════════════════════════ --}}
<x-confirm-modal wireModel="showRunPreprocessingConfirm" type="warning" title="Jalankan Preprocessing?"
    message="Preprocessing akan menyiapkan dataset untuk training dan dapat memakan waktu serta resource server. Lanjutkan?"
    confirmLabel="Ya, Jalankan" confirmWire="runPreprocessing" closeWire="closeRunPreprocessingConfirm" />

{{-- ════════════════════════════════════════════════════
     Confirm: Mulai Training
═════════════════════════════════════════════════════ --}}
<x-confirm-modal wireModel="showStartTrainingConfirm" type="warning" title="Mulai Training {{ strtoupper($modelType) }}?"
    message="Training model dapat berjalan beberapa menit dan membutuhkan komputasi yang cukup besar. Lanjutkan proses training?"
    confirmLabel="Ya, Mulai Training" confirmWire="startTraining" closeWire="closeStartTrainingConfirm" />

{{-- ════════════════════════════════════════════════════
     Confirm: Test Model Dataset
═════════════════════════════════════════════════════ --}}
<x-confirm-modal wireModel="showTestModelWithDatasetConfirm" type="warning" title="Jalankan Test Model Dataset?"
    message="Pengujian model terhadap dataset akan menambah beban komputasi sementara. Lanjutkan pengujian sekarang?"
    confirmLabel="Ya, Jalankan Test" confirmWire="testModelWithDataset" closeWire="closeTestModelWithDatasetConfirm" />

{{-- ════════════════════════════════════════════════════
     Confirm: Batalkan Preprocessing
═════════════════════════════════════════════════════ --}}
<x-confirm-modal wireModel="showCancelPreprocessingConfirm" type="warning" title="Batalkan Preprocessing?"
    message="Proses preprocessing yang sedang berjalan akan dihentikan. Data yang sudah diproses sebelum pembatalan tetap tersimpan."
    confirmLabel="Ya, Batalkan" confirmWire="cancelPreprocessing" closeWire="closeCancelPreprocessingConfirm" />

{{-- ════════════════════════════════════════════════════
     Confirm: Batalkan Training
═════════════════════════════════════════════════════ --}}
<x-confirm-modal wireModel="showCancelTrainingConfirm" type="warning" title="Batalkan Training?"
    message="Proses training yang sedang berjalan akan dihentikan. Hasil training yang belum selesai tidak akan disimpan sebagai run selesai."
    confirmLabel="Ya, Batalkan" confirmWire="cancelTraining" closeWire="closeCancelTrainingConfirm" />

</div>
</div>
