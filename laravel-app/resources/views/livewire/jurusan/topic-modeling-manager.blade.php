<div>
    @section('page-title', 'Analisis Topik')

    <div class="space-y-5 sm:space-y-6" x-data="{ activeTab: 'overview', activePreviewTab: 'final', activePreviewIdx: 0, showParameterInfoModal: false }">

        {{-- ── Page Header ──────────────────────────────────── --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center rounded-full bg-unsoed-blue-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-unsoed-blue-700">Mesin Analitik Teks</span>
                    <h1 class="mt-2 text-2xl font-bold text-gray-900">Analisis Topik</h1>
                    <p class="mt-1.5 text-sm text-gray-500">
                        Pemodelan topik (BERTopic & LDA) untuk memetakan tren penelitian dari data abstrak skripsi.
                    </p>
                </div>
                <div class="w-full rounded-xl border border-gray-200 bg-gray-50 p-3 sm:w-auto">
                    <div class="flex w-full flex-col gap-2.5 sm:flex-row sm:items-end">
                        <div class="w-full sm:w-[220px]">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-500">Model Pelatihan</label>
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
                            Muat Ulang Pratinjau
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>

        @if (($modelType ?? 'bertopic') === 'bertopic' && (($bertopicParamsSource ?? 'schema_default') === 'schema_default'))
            <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                <x-app.icon variant="o" name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                <div>
                    Training BERTopic dikunci karena parameter masih default.
                    Unggah JSON tuning notebook atau simpan parameter hasil tuning terlebih dahulu.
                </div>
            </div>
        @endif

        @if (($modelType ?? 'bertopic') === 'lda' && (($ldaParamsSource ?? 'schema_default') === 'schema_default'))
            <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                <x-app.icon variant="o" name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                <div>
                    Training LDA dikunci karena parameter masih default.
                    Simpan parameter tuning terlebih dahulu sebelum memulai training.
                </div>
            </div>
        @endif

        {{-- ── Pipeline Actions (dipisah agar tidak sesak) ─────────────────── --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Komando Pemrosesan</h2>
                    <p class="text-xs text-gray-500">Kontrol proses dari pembersihan teks hingga pelatihan model.</p>
                </div>
                <p class="text-[11px] text-gray-500">
                    Pilih antara <strong>Pelatihan Baru</strong> atau <strong>Impor Model</strong> yang sudah ada.
                </p>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-3.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Prapemrosesan Data</p>
                    <p class="mt-1 text-xs text-gray-500">Pembersihan dan standardisasi teks abstrak menggunakan modul NLP.</p>
                    <x-ui.button wire:click="openRunPreprocessingConfirm" wire:loading.attr="disabled" variant="primary"
                        :disabled="(($apiStatus['status'] ?? '') !== 'ok')" class="mt-3 w-full justify-center">
                        <x-app.icon name="funnel" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="runPreprocessing">Mulai Prapemrosesan</span>
                        <span wire:loading wire:target="runPreprocessing">Memproses Teks…</span>
                    </x-ui.button>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-3.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Pelatihan Mesin Pembelajaran</p>
                    <p class="mt-1 text-xs text-gray-500">Mulai proses pelatihan model {{ strtoupper($modelType) }} menggunakan data yang telah dibersihkan.</p>
                    <x-ui.button type="button" wire:click="openStartTrainingConfirm" wire:loading.attr="disabled" variant="success"
                        :disabled="(($apiStatus['status'] ?? '') !== 'ok') || (!$activeRun) || (!in_array($activeRun?->status ?? '', ['pending','completed','failed'])) || ((($modelType ?? 'bertopic') === 'bertopic') ? (($bertopicParamsSource ?? 'schema_default') === 'schema_default') : (($ldaParamsSource ?? 'schema_default') === 'schema_default'))"
                        class="mt-3 w-full justify-center">
                        <x-app.icon name="play-circle" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="startTraining">Jalankan Pelatihan {{ strtoupper($modelType) }}</span>
                        <span wire:loading wire:target="startTraining">Melatih…</span>
                    </x-ui.button>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-3.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Impor Model Eksternal</p>
                    <p class="mt-1 text-xs text-gray-500">Gunakan model yang sudah dilatih sebelumnya (*.tar.gz) untuk mempercepat proses analisis.</p>
                    <label class="mt-3 block text-[10px] font-semibold uppercase tracking-wide text-gray-500">Arsip Model</label>
                    <input
                        type="file"
                        accept=".tar.gz,.tgz,.tar,application/gzip,application/x-gzip,application/x-tar"
                        class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                        wire:model="modelArchiveFile"
                    />
                    <x-ui.button
                        type="button"
                        wire:click="importModelArchive"
                        wire:loading.attr="disabled"
                        wire:target="importModelArchive"
                        variant="secondary"
                        :disabled="(($apiStatus['status'] ?? '') !== 'ok')"
                        class="mt-3 w-full justify-center"
                    >
                        <x-app.icon name="arrow-up-tray" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="importModelArchive">Import Model</span>
                        <span wire:loading wire:target="importModelArchive">Mengimpor…</span>
                    </x-ui.button>
                </div>
            </div>
        </div>

        {{-- ── Tabs (reduce scrolling) ─────────────────────────────── --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-2.5 shadow-sm">
            <div class="flex snap-x snap-mandatory gap-2 overflow-x-auto pb-1 pr-1 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                @php
                    $tabs = [
                        'overview' => 'Ringkasan',
                        'preview' => 'Pratinjau',
                        'database' => 'Penyimpanan Data',
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
                    Layanan analitik tidak merespons. Pastikan sistem pendukung berjalan normal.
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
                            <span class="text-sm text-green-700 font-medium">Layanan Komputasi Analitik Aktif</span>
                            <span class="text-xs text-gray-400">{{ $apiStatus['app_name'] ?? '' }}
                                v{{ $apiStatus['version'] ?? '' }}</span>
                        @else
                            <span class="relative flex h-3 w-3">
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                            </span>
                            <span class="text-sm text-red-700 font-medium">Layanan Komputasi Analitik Tidak Merespons</span>
                            <span
                                class="text-xs text-gray-400">{{ $apiStatus['message'] ?? 'Mesin analitik sedang tidak dapat dijangkau.' }}</span>
                        @endif
                    </div>

                    <x-ui.button variant="ghost-primary" size="sm" wire:click="checkApiStatus"
                        class="self-start font-medium flex items-center transition sm:self-auto !px-2"
                        wire:loading.class="opacity-50" wire:target="checkApiStatus">
                        <x-app.icon name="arrow-path" class="w-4 h-4 mr-1" wire:loading.class="animate-spin" wire:target="checkApiStatus" />
                        Muat Ulang Status
                    </x-ui.button>
                </div>
            </div>

            {{-- ── Dataset Readiness Card ──────────────────────────────── --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 xl:col-span-2">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">Kesiapan Korpus Teks</div>
                        <div class="mt-0.5 text-xs text-gray-500">Ringkasan data abstrak yang siap digunakan untuk pelatihan.</div>
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
                            <p class="text-xs text-gray-500">Dataset Pelatihan</p>
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
                        {{ $datasetSummary['message'] ?? 'Layanan komputasi tidak dapat dihubungi. Ringkasan dataset belum bisa ditampilkan.' }}
                    </div>
                @elseif(($datasetSummary['status'] ?? '') === 'error')
                    <div class="mt-3 text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                        {{ $datasetSummary['message'] ?? 'Gagal mengambil ringkasan dataset.' }}
                    </div>
                @else
                    <div class="mt-3 text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                        Ringkasan dataset belum tersedia. Klik <strong>Refresh</strong> untuk memuat ulang.
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
                        'dropna_abstract' => 'Abstrak kosong/null',
                        'short_abstract' => 'Abstrak terlalu pendek',
                        'duplicate_abstract' => 'Duplikat abstrak',
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
                                            <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-gray-500">Alasan</th>
                                            <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-gray-500">Judul</th>
                                            <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-gray-500">Pratinjau Abstrak</th>
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
                                    Menampilkan 25 sampel pertama dari total sampel yang tidak lolos prapemrosesan.
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
                        <h2 class="text-sm font-semibold text-gray-900">Pratinjau Pipeline Preprocessing</h2>
                        <p class="mt-0.5 text-xs text-gray-500">
                            5 contoh data dari tabel <code class="font-mono">topic_model_datasets</code> beserta proses perubahannya.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-1 rounded-xl border border-gray-200 bg-gray-50 p-1">
                        @foreach (['final' => 'Input BERTopic', 'lda' => 'Input LDA', 'raw' => 'Asli', 'cleaned' => 'Bersih', 'tokens' => 'Token'] as $tab => $label)
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
                                    #{{ $row['id'] }} - {{ $row['title'] }}
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
                                        Input BERTopic (Pembersihan Ringan, tanpa stemming/stopword removal)
                                    </span>
                                    {{ Str::limit($row['final_cleaned_text'], 350) }}
                                    <div
                                        class="mt-2 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-[10px] text-blue-700">
                                        IndoSBERT membutuhkan teks natural, sehingga penghapusan stopword dan penyelarasan kata dasar ditangani oleh c-TF-IDF vectorizer di dalam BERTopic.
                                    </div>
                                </div>
                                <div x-show="activePreviewTab === 'lda'" class="break-words">
                                    <span
                                        class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                                        Input LDA (Tokenized dan Stopword Removal, tanpa stemming lokal)
                                    </span>
                                    {{ Str::limit($row['final_processed_text'], 350) }}
                                    <div
                                        class="mt-2 rounded-md border border-emerald-100 bg-emerald-50 px-3 py-2 text-[10px] text-emerald-700">
                                        Penyelarasan kata dasar dan finalisasi teks dikerjakan secara otomatis oleh modul analitik.
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
                        <h2 class="text-sm font-semibold text-gray-900">Hasil Prapemrosesan Data</h2>
                        <p class="mt-0.5 text-xs text-gray-500">
                            Data teks bersih yang siap digunakan untuk pemodelan.
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
                                Muat lebih banyak
                            </button>
                        @endif
                    </div>
                @else
                    <div class="px-5 py-8 text-center text-sm text-gray-400">
                        <x-app.icon name="funnel" class="mx-auto mb-3 h-10 w-10 text-gray-300" />
                        Belum ada hasil prapemrosesan teks yang tersimpan.
                        <div class="mt-1 text-xs">Jalankan <strong>Prapemrosesan</strong> terlebih dahulu.</div>
                    </div>
                @endif
            </x-ui.card>

        </div>

        {{-- ========================== TAB: HASIL ========================== --}}
        <div x-show="activeTab === 'hasil'" x-cloak class="space-y-6">

            <x-ui.card title="Hasil Analisis Topik" description="Proses selesai, topik berhasil didapatkan.">
                @if ($activeRun && $activeRun->status === 'completed')
                    {{-- Model actions --}}
                    @if ($activeRun->fastapi_training_job_id)
                        <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <x-ui.button wire:click="downloadModel" wire:loading.attr="disabled" variant="secondary">
                                <x-app.icon name="arrow-down-tray" class="h-4 w-4" />
                                Unduh Model
                            </x-ui.button>
                            @if (($activeRun->model_type ?? '') === 'bertopic')
                                <x-ui.button wire:click="openTestModelWithDatasetConfirm" wire:loading.attr="disabled" variant="secondary">
                                    <x-app.icon name="beaker" class="h-4 w-4" />
                                    <span wire:loading.remove wire:target="testModelWithDataset">Uji Model (Dataset)</span>
                                    <span wire:loading wire:target="testModelWithDataset">Menguji…</span>
                                </x-ui.button>
                            @endif
                            <div class="text-xs text-gray-500 sm:ml-auto">Job ID: {{ $activeRun->fastapi_training_job_id }}</div>
                            @if (!empty($lastImportedArchiveName))
                                <div class="w-full text-xs text-gray-500 sm:text-right">File import: <span class="font-medium text-gray-700">{{ $lastImportedArchiveName }}</span></div>
                            @endif
                        </div>

                        @if (($activeRun->model_type ?? '') === 'bertopic' && !empty($modelTestDatasetResult) && !isset($modelTestDatasetResult['status']))
                            @php
                                $stored = $modelTestDatasetResult['stored_metrics'] ?? [];
                                $retest = $modelTestDatasetResult['retest_metrics'] ?? [];
                                $same = $modelTestDatasetResult['same'] ?? [];
                                $delta = $modelTestDatasetResult['delta'] ?? [];
                                $quality = $modelTestDatasetResult['quality'] ?? [];
                                $dataset = $modelTestDatasetResult['dataset'] ?? [];
                                $hardMatch = ($same['coherence_cv'] ?? false) === true && ($same['topic_diversity'] ?? false) === true;
                                $deltaCvAbs = is_numeric($delta['coherence_cv'] ?? null) ? abs((float) $delta['coherence_cv']) : null;
                                $deltaTdAbs = is_numeric($delta['topic_diversity'] ?? null) ? abs((float) $delta['topic_diversity']) : null;
                                $nearMatchTolerance = 0.01;
                                $zeroMetricMatch = ($quality['zero_metric_match'] ?? false) === true;
                                $reliableHardMatch = $hardMatch && !$zeroMetricMatch;
                                $nearMatch = !$hardMatch
                                    && $deltaCvAbs !== null
                                    && $deltaTdAbs !== null
                                    && $deltaCvAbs <= $nearMatchTolerance
                                    && $deltaTdAbs <= $nearMatchTolerance;

                                $formatMetric = static function ($value): string {
                                    return is_numeric($value) ? number_format((float) $value, 4) : '-';
                                };

                                $storedCv = $formatMetric($stored['coherence_cv'] ?? null);
                                $storedTd = $formatMetric($stored['topic_diversity'] ?? null);
                                $storedTopics = is_numeric($stored['num_topics'] ?? null) ? (string) ((int) $stored['num_topics']) : '-';
                                $retestCv = $formatMetric($retest['coherence_cv'] ?? null);
                                $retestTd = $formatMetric($retest['topic_diversity'] ?? null);
                                $retestTopics = is_numeric($retest['num_topics'] ?? null) ? (string) ((int) $retest['num_topics']) : '-';
                                $sameCoherence = $same['coherence_cv'] ?? null;
                                $sameDiversity = $same['topic_diversity'] ?? null;

                                $bannerClass = $reliableHardMatch
                                    ? 'border-emerald-200 bg-emerald-50'
                                    : ($nearMatch ? 'border-sky-200 bg-sky-50' : 'border-amber-200 bg-amber-50');
                                $titleClass = $reliableHardMatch
                                    ? 'text-emerald-800'
                                    : ($nearMatch ? 'text-sky-800' : 'text-amber-800');
                            @endphp
                            <div class="mb-5 rounded-xl border {{ $bannerClass }} p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="text-sm font-semibold {{ $titleClass }}">
                                        @if ($reliableHardMatch)
                                            Hasil test: sama dengan training
                                        @elseif($zeroMetricMatch)
                                            Hasil test: match numerik, tetapi Cv/TD bernilai 0 (tidak representatif)
                                        @elseif($nearMatch)
                                            Hasil test: hampir sama (selisih kecil)
                                        @else
                                            Hasil test: ada perbedaan dari training
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Dataset: {{ (int) ($dataset['used'] ?? 0) }}/{{ (int) ($dataset['total'] ?? 0) }} dipakai
                                    </div>
                                </div>
                                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3 text-xs">
                                    <div class="rounded-lg bg-white/70 border border-gray-200 p-3">
                                        <div class="font-semibold text-gray-700">Training (tersimpan)</div>
                                        <div class="mt-1 text-gray-600">Coherence (C_v): <span class="font-mono">{{ $storedCv }}</span></div>
                                        <div class="text-gray-600">Keragaman: <span class="font-mono">{{ $storedTd }}</span></div>
                                        <div class="text-gray-600">#Topik: <span class="font-mono">{{ $storedTopics }}</span></div>
                                    </div>
                                    <div class="rounded-lg bg-white/70 border border-gray-200 p-3">
                                        <div class="font-semibold text-gray-700">Uji Ulang (dataset sekarang)</div>
                                        <div class="mt-1 text-gray-600">Coherence (C_v): <span class="font-mono">{{ $retestCv }}</span></div>
                                        <div class="text-gray-600">Keragaman: <span class="font-mono">{{ $retestTd }}</span></div>
                                        <div class="text-gray-600">#Topik: <span class="font-mono">{{ $retestTopics }}</span></div>
                                    </div>
                                    <div class="rounded-lg bg-white/70 border border-gray-200 p-3">
                                        <div class="font-semibold text-gray-700">Kecocokan</div>
                                        <div class="mt-1 text-gray-600">Coherence cocok: <span class="font-mono">{{ $sameCoherence === true ? 'ya' : ($sameCoherence === false ? 'tidak' : '-') }}</span></div>
                                        <div class="text-gray-600">Keragaman cocok: <span class="font-mono">{{ $sameDiversity === true ? 'ya' : ($sameDiversity === false ? 'tidak' : '-') }}</span></div>
                                        <div class="text-gray-600">ΔCoherence: <span class="font-mono">{{ (!$zeroMetricMatch && $deltaCvAbs !== null) ? number_format($deltaCvAbs, 4) : '-' }}</span></div>
                                        <div class="text-gray-600">ΔKeragaman: <span class="font-mono">{{ (!$zeroMetricMatch && $deltaTdAbs !== null) ? number_format($deltaTdAbs, 4) : '-' }}</span></div>
                                        <div class="text-gray-600">Kata kunci cocok: <span class="font-mono">{{ isset($same['keyword_match_ratio']) ? round(((float) $same['keyword_match_ratio']) * 100) . '%' : '-' }}</span></div>
                                    </div>
                                </div>
                                @if ($zeroMetricMatch)
                                    <div class="mt-2 text-xs text-amber-700">
                                        Catatan: Cv/TD di training dan uji ulang sama-sama 0.0000, jadi kecocokan ini bersifat numerik saja dan belum cukup merepresentasikan kualitas topik.
                                    </div>
                                @endif
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
                                    'label' => 'Keragaman Topik',
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
                                        Pemetaan</th>
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
                                                <div class="text-xs text-gray-400">Belum ada pemetaan dokumen</div>
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
                        <div class="text-sm font-medium text-gray-700">Pelatihan sedang berjalan…</div>
                        <div class="mt-1 text-xs text-gray-500">{{ $trainingMessage }}</div>
                    </div>
                @elseif($activeRun && $activeRun->status === 'failed')
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <div class="font-semibold">Pelatihan gagal</div>
                        <div class="mt-1 text-xs">{{ $activeRun->error_message }}</div>
                    </div>
                @else
                    <div class="flex flex-col items-center py-10 text-center">
                        <x-app.icon name="funnel" class="mb-3 h-12 w-12 text-gray-200" />
                        <div class="text-sm font-medium text-gray-500">Belum ada hasil pelatihan</div>
                        <div class="mt-1 text-xs text-gray-400">Jalankan Preprocessing → Mulai Pelatihan untuk
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
                            $statusLabel = match ($r->status) {
                                'completed' => 'Selesai',
                                'training' => 'Pelatihan',
                                'preprocessing' => 'Preprocessing',
                                'failed' => 'Gagal',
                                default => ucfirst($r->status),
                            };

                            $isLdaRun = ($r->model_type ?? 'bertopic') === 'lda';
                            $params = $isLdaRun
                                ? (is_array($r->lda_params) ? $r->lda_params : [])
                                : (is_array($r->bertopic_params) ? $r->bertopic_params : []);

                            $coreParams = $isLdaRun
                                ? [
                                    'num_topics' => $params['num_topics'] ?? null,
                                    'passes' => $params['passes'] ?? null,
                                    'iterations' => $params['iterations'] ?? null,
                                    'chunksize' => $params['chunksize'] ?? null,
                                    'alpha' => $params['alpha'] ?? null,
                                    'eta' => $params['eta'] ?? null,
                                    'no_below' => $params['no_below'] ?? null,
                                    'no_above' => $params['no_above'] ?? null,
                                    'random_state' => $params['random_state'] ?? null,
                                ]
                                : [
                                    'embedding_model' => $params['embedding_model'] ?? null,
                                    'min_topic_size' => $params['min_topic_size'] ?? null,
                                    'nr_topics' => $params['nr_topics'] ?? null,
                                    'top_n_words' => $params['top_n_words'] ?? null,
                                    'n_gram_range' => is_array($params['n_gram_range'] ?? null)
                                        ? implode('-', $params['n_gram_range'])
                                        : ($params['n_gram_range'] ?? null),
                                    'vectorizer_min_df' => $params['vectorizer_min_df'] ?? null,
                                    'vectorizer_max_df' => $params['vectorizer_max_df'] ?? null,
                                    'seed' => $params['seed'] ?? null,
                                ];

                            $umapParams = !$isLdaRun && is_array($params['umap_params'] ?? null)
                                ? $params['umap_params']
                                : [];
                            $hdbscanParams = !$isLdaRun && is_array($params['hdbscan_params'] ?? null)
                                ? $params['hdbscan_params']
                                : [];
                        @endphp
                        <div class="px-5 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs font-semibold text-gray-800">Run #{{ $r->id }}</div>
                                    <div class="text-xs text-gray-400">{{ $r->created_at?->format('d/m/Y H:i') }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Model {{ strtoupper($r->model_type ?? 'bertopic') }}
                                        | {{ $r->num_topics ?? 0 }} topik
                                        | C_v: {{ $r->coherence_cv !== null ? number_format((float) $r->coherence_cv, 4) : '-' }}
                                        | TD: {{ $r->topic_diversity !== null ? number_format((float) $r->topic_diversity, 4) : '-' }}
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    <x-ui.badge type="{{ $badgeType }}">{{ $statusLabel }}</x-ui.badge>
                                    <div class="text-xs text-gray-400">{{ $r->topics_count }} topik</div>
                                </div>
                            </div>

                            @if (!empty($params))
                                <details class="mt-2 rounded-lg border border-gray-200 bg-gray-50/70 p-2.5">
                                    <summary class="cursor-pointer text-xs font-semibold text-unsoed-blue-700">
                                        Lihat Detail Parameter Run
                                    </summary>

                                    <div class="mt-2 space-y-2">
                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                            @foreach ($coreParams as $paramKey => $paramValue)
                                                @if ($paramValue !== null && $paramValue !== '')
                                                    <div class="rounded-md border border-gray-200 bg-white px-2.5 py-2">
                                                        <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                                            {{ $paramKey }}
                                                        </div>
                                                        <div class="mt-0.5 break-words text-xs text-gray-800">
                                                            {{ is_bool($paramValue) ? ($paramValue ? 'true' : 'false') : $paramValue }}
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>

                                        @if (!$isLdaRun && (!empty($umapParams) || !empty($hdbscanParams)))
                                            <div class="grid grid-cols-1 gap-2 xl:grid-cols-2">
                                                @if (!empty($umapParams))
                                                    <div class="rounded-md border border-gray-200 bg-white px-2.5 py-2">
                                                        <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">umap_params</div>
                                                        <div class="mt-1 text-xs text-gray-700">
                                                            n_neighbors={{ $umapParams['n_neighbors'] ?? '-' }} |
                                                            n_components={{ $umapParams['n_components'] ?? '-' }} |
                                                            min_dist={{ $umapParams['min_dist'] ?? '-' }} |
                                                            metric={{ $umapParams['metric'] ?? '-' }} |
                                                            random_state={{ $umapParams['random_state'] ?? '-' }}
                                                        </div>
                                                    </div>
                                                @endif

                                                @if (!empty($hdbscanParams))
                                                    <div class="rounded-md border border-gray-200 bg-white px-2.5 py-2">
                                                        <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">hdbscan_params</div>
                                                        <div class="mt-1 text-xs text-gray-700">
                                                            min_cluster_size={{ $hdbscanParams['min_cluster_size'] ?? '-' }} |
                                                            min_samples={{ $hdbscanParams['min_samples'] ?? '-' }} |
                                                            metric={{ $hdbscanParams['metric'] ?? '-' }} |
                                                            cluster_selection_method={{ $hdbscanParams['cluster_selection_method'] ?? '-' }}
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </details>
                            @else
                                <div class="mt-2 text-[11px] text-gray-400">Parameter run tidak tersedia pada data ini.</div>
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-6 text-center text-xs text-gray-400">Belum ada riwayat run.</div>
                    @endforelse
                </div>
            </x-ui.card>
        </div>
{{-- ========================== TAB: PENGATURAN ========================== --}}
<div x-show="activeTab === 'pengaturan'" x-cloak x-data="{ selectedModel: @entangle('modelType').live }" class="space-y-6">

    <div class="rounded-xl border border-unsoed-blue-200 bg-unsoed-blue-50/70 px-4 py-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-unsoed-blue-900">Butuh Panduan Parameter?</h2>
                <p class="mt-0.5 text-xs text-unsoed-blue-700">
                    Buka info untuk melihat fungsi tiap parameter sebelum menyimpan atau menjalankan pelatihan.
                </p>
            </div>
            <x-ui.button type="button" variant="ghost-primary" size="sm" x-on:click="showParameterInfoModal = true"
                class="!border !border-unsoed-blue-200 !bg-white !text-unsoed-blue-700 hover:!bg-unsoed-blue-100">
                <x-app.icon name="information-circle" class="h-4 w-4" />
                Info Parameter
            </x-ui.button>
        </div>
    </div>

    {{-- ---- BERTopic Settings ---- --}}
    <div x-show="selectedModel === 'bertopic'" x-cloak>
    <x-ui.card no-padding>
        <div class="border-b border-gray-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Konfigurasi BERTopic</h2>
            <p class="mt-0.5 text-xs text-gray-500">Disederhanakan mengikuti rekomendasi resmi parameter tuning BERTopic.</p>
        </div>
        <div class="space-y-5 px-5 py-4">
            <x-ui.alert type="info">
                Parameter tuning yang aktif mengikuti rekomendasi resmi BERTopic: <strong>top_n_words</strong>,
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
                        <input type="number" min="1"
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
                <div class="text-xs text-gray-400">Simpan sebagai bawaan di basis data (BERTopic per akun).</div>
                <div class="flex items-center gap-2">
                    <x-ui.button wire:click="saveTrainingParams" wire:loading.attr="disabled" variant="secondary">
                        <x-app.icon name="bookmark-square" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="saveTrainingParams">Simpan sebagai Best</span>
                        <span wire:loading wire:target="saveTrainingParams">Menyimpan Best…</span>
                    </x-ui.button>
                </div>
            </div>

            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Unggah Parameter JSON</div>
                        <div class="mt-1 text-xs text-gray-500">
                            Unggah JSON untuk memperbarui bawaan BERTopic di basis data (tanpa menjalankan pelatihan).
                        </div>
                    </div>
                    <a
                        class="inline-flex items-center gap-2 rounded-lg border border-unsoed-blue-200 bg-white px-3 py-2 text-xs font-semibold text-unsoed-blue-700 hover:bg-unsoed-blue-50"
                        href="{{ route('jurusan.topic-modeling.settings.template.download') }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <x-app.icon name="arrow-down-tray" class="h-4 w-4" />
                        Unduh Template JSON
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
                        <span wire:loading.remove wire:target="uploadBertopicParamsJson">Unggah JSON</span>
                        <span wire:loading wire:target="uploadBertopicParamsJson">Mengunggah...</span>
                    </x-ui.button>
                </div>
            </div>

        </div>
    </x-ui.card>
    </div>

    {{-- ---- LDA Settings ---- --}}
    <div x-show="selectedModel === 'lda'" x-cloak>
    <x-ui.card no-padding>
        <div class="border-b border-gray-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Konfigurasi LDA</h2>
            <p class="mt-0.5 text-xs text-gray-500">Parameter dasar LDA (Gensim) untuk pelatihan topik.</p>
        </div>
        <div class="space-y-5 px-5 py-4">
            <x-ui.alert type="info">
                Pastikan preprocessing menghasilkan <strong>processed_text</strong> sebelum pelatihan LDA.
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
                <div class="text-xs text-gray-400">Simpan sebagai konfigurasi preferensi akun.</div>
                <div class="flex items-center gap-2">
                    <x-ui.button wire:click="saveTrainingParams" wire:loading.attr="disabled" variant="secondary">
                        <x-app.icon name="bookmark-square" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="saveTrainingParams">Simpan Konfigurasi</span>
                        <span wire:loading wire:target="saveTrainingParams">Menyimpan...</span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    </x-ui.card>
    </div>

    {{-- ---- Preprocessing Note ---- --}}
    <x-ui.alert type="warning">
        <div class="font-semibold">Strategi prapemrosesan:</div>
        <ul class="mt-1 space-y-0.5 text-amber-700">
            <li>• <strong>BERTopic:</strong> pembersihan ringan dengan mempertahankan tanda baca dan stopword, tanpa stemming</li>
            <li>• <strong>LDA:</strong> tokenisasi + penghapusan stopword + stemming (dilakukan pada tahap prapemrosesan teks)</li>
        </ul>
    </x-ui.alert>

</div>

<div x-show="showParameterInfoModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog"
    aria-modal="true" @keydown.escape.window="showParameterInfoModal = false">

    <div x-show="showParameterInfoModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="showParameterInfoModal = false">
    </div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="showParameterInfoModal" x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[88vh] flex flex-col overflow-hidden" @click.stop>

            <div class="bg-gradient-to-r from-unsoed-blue-700 to-unsoed-blue-600 px-6 py-4 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <x-app.icon variant="s" name="information-circle" class="w-4 h-4 text-white" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Info Parameter Analisis Topik</h3>
                        <p class="text-xs text-white/75">Versi ringkas fungsi parameter utama.</p>
                    </div>
                </div>
                <button type="button" x-on:click="showParameterInfoModal = false"
                    class="w-8 h-8 bg-white/10 hover:bg-white/25 rounded-lg flex items-center justify-center text-white transition"
                    aria-label="Tutup modal info parameter">
                    <x-app.icon name="x-mark" class="w-4 h-4" />
                </button>
            </div>

            <div class="px-6 py-5 overflow-y-auto flex-1 space-y-4">
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-xs text-amber-800">
                    Tips: ubah parameter sedikit demi sedikit, lalu bandingkan coherence dan topic diversity.
                </div>

                <div class="rounded-xl border border-gray-200 p-3.5">
                    <h4 class="text-sm font-semibold text-gray-900">BERTopic</h4>
                    <p class="mt-1 text-xs text-gray-600">min_topic_size mengatur ukuran minimum topik, nr_topics mengatur jumlah topik akhir, top_n_words menentukan banyak kata representatif, dan n_gram_range mengatur frasa kata.</p>
                </div>

                <div class="rounded-xl border border-gray-200 p-3.5">
                    <h4 class="text-sm font-semibold text-gray-900">UMAP</h4>
                    <p class="mt-1 text-xs text-gray-600">n_neighbors menyeimbangkan pola lokal/global, n_components menentukan dimensi reduksi, dan metric menentukan cara hitung jarak antar dokumen.</p>
                </div>

                <div class="rounded-xl border border-gray-200 p-3.5">
                    <h4 class="text-sm font-semibold text-gray-900">HDBSCAN</h4>
                    <p class="mt-1 text-xs text-gray-600">min_cluster_size menentukan ukuran minimum cluster, min_samples menentukan ketatnya deteksi noise, dan metric menentukan jarak saat clustering.</p>
                </div>

                <div class="rounded-xl border border-gray-200 p-3.5">
                    <h4 class="text-sm font-semibold text-gray-900">LDA</h4>
                    <p class="mt-1 text-xs text-gray-600">num_topics menentukan jumlah topik, passes/iterations memengaruhi kualitas dan durasi training, alpha/eta mengatur sebaran topik-kata, no_below/no_above menyaring kosakata, dan random_state untuk replikasi hasil.</p>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/60 flex justify-end items-center flex-shrink-0">
                <x-ui.button variant="light" size="md" type="button" x-on:click="showParameterInfoModal = false"
                    class="!rounded-xl">
                    Tutup
                </x-ui.button>
            </div>
        </div>
    </div>
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
                            <h3 class="text-base font-bold text-white">Pemetaan Skripsi Topik {{ $selectedTopicModalTopicId }}</h3>
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
                        Belum ada dokumen yang termapping untuk topik ini.
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
    Confirm: Uji Model Dataset
═════════════════════════════════════════════════════ --}}
<x-confirm-modal wireModel="showTestModelWithDatasetConfirm" type="warning" title="Jalankan Uji Model pada Dataset?"
    message="Pengujian model terhadap dataset akan menambah beban komputasi sementara. Lanjutkan pengujian sekarang?"
    confirmLabel="Ya, Jalankan Uji" confirmWire="testModelWithDataset" closeWire="closeTestModelWithDatasetConfirm" />

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
