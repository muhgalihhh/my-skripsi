<div class="space-y-6" x-data="{ activePreviewTab: 'final', activePreviewIdx: 0 }">

    {{-- ===== HEADER ===== --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Topic Modeling</h1>
            <p class="mt-1 text-sm text-gray-500">
                Pipeline BERTopic (IndoSBERT → UMAP → HDBSCAN → c-TF-IDF) untuk analisis topik skripsi UNSOED.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button wire:click="buildPreview" wire:loading.attr="disabled" variant="secondary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh preview
            </x-ui.button>
            <x-ui.button wire:click="runPreprocessing" wire:loading.attr="disabled" variant="primary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span wire:loading.remove wire:target="runPreprocessing">Preprocessing</span>
                <span wire:loading wire:target="runPreprocessing">Processing…</span>
            </x-ui.button>
            <button type="button" wire:click="startTraining" wire:loading.attr="disabled"
                @class([
                    'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors disabled:opacity-50',
                    'bg-emerald-600 hover:bg-emerald-700' => $activeRun && in_array($activeRun->status, ['pending','completed','failed']),
                    'bg-gray-400 cursor-not-allowed' => !$activeRun || !in_array($activeRun?->status ?? '', ['pending','completed','failed']),
                ])>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span wire:loading.remove wire:target="startTraining">Start Training</span>
                <span wire:loading wire:target="startTraining">Starting…</span>
            </button>
        </div>
    </div>

    {{-- ===== STATUS ALERT ===== --}}
    @if ($statusMessage)
        <x-ui.alert type="{{ $statusType }}" message="{{ $statusMessage }}" />
    @endif

    {{-- ===== MAIN GRID ===== --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        {{-- == LEFT: Main content (2/3) == --}}
        <div class="space-y-6 xl:col-span-2">

            {{-- ---- Pipeline Progress Steps ---- --}}
            <x-ui.card>
                <h2 class="mb-4 text-sm font-semibold text-gray-900">Status Pipeline</h2>
                <div class="flex items-start gap-0">
                    @php
                        $steps = [
                            ['label' => 'Preprocessing', 'desc' => 'Clean + tokenize + stem', 'statuses' => ['preprocessing', 'pending', 'training', 'completed']],
                            ['label' => 'Training', 'desc' => 'IndoSBERT → UMAP → HDBSCAN', 'statuses' => ['training', 'completed']],
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
                            <div class="mt-2 text-xs font-semibold {{ $active ? 'text-unsoed-blue-800' : 'text-gray-400' }}">
                                {{ $step['label'] }}
                            </div>
                            <div class="text-xs text-gray-400">{{ $step['desc'] }}</div>
                        </div>
                        @if (!$loop->last)
                            <div class="mt-4 h-0.5 flex-1 {{ in_array($currentStatus, $steps[$i+1]['statuses']) ? 'bg-unsoed-blue-300' : 'bg-gray-200' }} transition-all duration-500"></div>
                        @endif
                    @endforeach
                </div>
            </x-ui.card>

            {{-- ---- Preview Preprocessing ---- --}}
            <x-ui.card no-padding>
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Preview Preprocessing Pipeline</h2>
                        <p class="mt-0.5 text-xs text-gray-500">
                            5 sampel abstrak — menampilkan setiap langkah pipeline secara transparan.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (['final' => 'BERTopic Input', 'raw' => 'Raw', 'cleaned' => 'Cleaned', 'tokens' => 'Tokens', 'nostop' => 'LDA Input'] as $tab => $label)
                            <button type="button" @click="activePreviewTab = '{{ $tab }}'"
                                :class="activePreviewTab === '{{ $tab }}'
                                    ? 'bg-unsoed-blue-800 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                class="rounded-full px-2.5 py-1 text-xs font-medium transition-colors">
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
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{{ $row['year'] }}</span>
                            </div>

                            <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-700 leading-relaxed">
                                <div x-show="activePreviewTab === 'raw'" class="break-words">
                                    <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">Teks Abstrak Asli</span>
                                    {{ Str::limit($row['raw'], 350) }}
                                </div>
                                <div x-show="activePreviewTab === 'cleaned'" class="break-words">
                                    <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">Setelah lowercase + hapus URL/angka/tanda baca</span>
                                    {{ Str::limit($row['cleaned'], 350) }}
                                </div>
                                <div x-show="activePreviewTab === 'tokens'" class="break-words">
                                    <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">Tokenized (50 pertama)</span>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach(array_slice($row['tokenized'], 0, 50) as $tok)
                                            <span class="rounded bg-white px-1.5 py-0.5 ring-1 ring-gray-200">{{ $tok }}</span>
                                        @endforeach
                                        @if(count($row['tokenized']) > 50)
                                            <span class="text-gray-400">+{{ count($row['tokenized']) - 50 }} lagi…</span>
                                        @endif
                                    </div>
                                </div>
                                <div x-show="activePreviewTab === 'nostop'" class="break-words">
                                    <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">LDA Input — Stopwords removed (50 pertama)</span>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach(array_slice($row['stopwords_removed'], 0, 50) as $tok)
                                            <span class="rounded bg-white px-1.5 py-0.5 ring-1 ring-gray-200">{{ $tok }}</span>
                                        @endforeach
                                        @if(count($row['stopwords_removed']) > 50)
                                            <span class="text-gray-400">+{{ count($row['stopwords_removed']) - 50 }} lagi…</span>
                                        @endif
                                    </div>
                                </div>
                                <div x-show="activePreviewTab === 'final'" class="break-words">
                                    <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                                        BERTopic Input — Soft clean (natural, tanpa stemming/stopword removal)
                                    </span>
                                    {{ Str::limit($row['final_cleaned_text'], 350) }}
                                    <div class="mt-2 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-[10px] text-blue-700">
                                        💡 IndoSBERT butuh teks natural — stopword & stemming dihandle oleh c-TF-IDF vectorizer di dalam BERTopic.
                                    </div>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="px-5 py-8 text-center text-sm text-gray-400">
                            <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Belum ada data abstrak. Pastikan scraping sudah dijalankan.
                        </div>
                    @endforelse
                </div>
            </x-ui.card>

            {{-- ---- Hasil Topic Modeling ---- --}}
            <x-ui.card title="Hasil Topic Modeling" description="Run aktif — topik tersimpan di database.">
                @if($activeRun && $activeRun->status === 'completed')
                    {{-- Stats row --}}
                    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-xl bg-unsoed-blue-50 p-3 text-center">
                            <div class="text-2xl font-bold text-unsoed-blue-800">{{ $activeRun->num_topics ?? '-' }}</div>
                            <div class="mt-0.5 text-xs text-unsoed-blue-600">Topik</div>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3 text-center">
                            <div class="text-2xl font-bold text-gray-700">{{ $activeRun->total_documents ?? '-' }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">Dokumen</div>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3 text-center">
                            <div class="text-2xl font-bold text-gray-700">{{ $activeRun->num_outliers ?? '-' }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">Outlier</div>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3 text-center">
                            <div class="text-2xl font-bold text-gray-700">
                                {{ $activeRun->training_duration_seconds ? round($activeRun->training_duration_seconds) . 's' : '-' }}
                            </div>
                            <div class="mt-0.5 text-xs text-gray-500">Durasi</div>
                        </div>
                    </div>

                    {{-- Topics table --}}
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-16 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Topik</th>
                                    <th class="w-20 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Dokumen</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kata Kunci Utama</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($activeRun->topics->sortBy('topic_id') as $t)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3">
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-unsoed-blue-100 text-xs font-bold text-unsoed-blue-800">
                                                {{ $t->topic_id }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">{{ $t->count }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach(array_slice($t->top_words ?? [], 0, 8) as $word)
                                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ $word }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                @elseif($activeRun && $activeRun->status === 'training')
                    <div class="flex flex-col items-center py-8 text-center">
                        <div class="mb-4 h-10 w-10 animate-spin rounded-full border-4 border-gray-200 border-t-unsoed-blue-800"></div>
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
                        <svg class="mb-3 h-12 w-12 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                        <div class="text-sm font-medium text-gray-500">Belum ada hasil training</div>
                        <div class="mt-1 text-xs text-gray-400">Jalankan Preprocessing → Start Training untuk memulai.</div>
                    </div>
                @endif
            </x-ui.card>
        </div>

        {{-- == RIGHT: Sidebar (1/3) == --}}
        <div class="space-y-5">

            {{-- ---- Preprocessing Progress ---- --}}
            @if($preprocessingJobId)
                <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-5 shadow-sm" wire:poll.2s="pollPreprocessingProgress">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="h-2 w-2 animate-pulse rounded-full bg-yellow-500"></div>
                            <span class="text-sm font-semibold text-yellow-800">Preprocessing Berjalan</span>
                        </div>
                        <button type="button" wire:click="cancelPreprocessing" wire:confirm="Yakin ingin membatalkan preprocessing?" class="text-yellow-600 hover:text-yellow-800 p-1 rounded-md hover:bg-yellow-100 transition-colors" title="Batalkan Preprocessing">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <div class="mb-1 flex justify-between text-xs text-yellow-700">
                        <span>{{ $preprocessingMessage ?: 'Memproses…' }}</span>
                        <span class="font-semibold">{{ $preprocessingProgress }}%</span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-yellow-200">
                        <div class="h-2.5 rounded-full bg-yellow-600 transition-all duration-500"
                            style="width: {{ max(0, min(100, (int) $preprocessingProgress)) }}%"></div>
                    </div>
                    <div class="mt-3 rounded-lg bg-white px-3 py-2">
                        <div class="text-[10px] text-gray-400">Job ID</div>
                        <div class="font-mono text-xs text-gray-700">{{ $preprocessingJobId }}</div>
                    </div>
                </div>
            @endif

            {{-- ---- Training Progress ---- --}}
            @if($trainingJobId)
                <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm" wire:poll.5s="pollTrainingProgress">
                    <div class="mb-3 flex items-center gap-2">
                        <div class="h-2 w-2 animate-pulse rounded-full bg-blue-500"></div>
                        <span class="text-sm font-semibold text-blue-800">Training Berjalan</span>
                    </div>
                    <div class="mb-1 flex justify-between text-xs text-blue-700">
                        <span>{{ $trainingMessage ?: 'Memproses…' }}</span>
                        <span class="font-semibold">{{ $trainingProgress }}%</span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-blue-200">
                        <div class="h-2.5 rounded-full bg-blue-600 transition-all duration-500"
                            style="width: {{ max(0, min(100, (int) $trainingProgress)) }}%"></div>
                    </div>
                    <div class="mt-3 rounded-lg bg-white px-3 py-2">
                        <div class="text-[10px] text-gray-400">Job ID</div>
                        <div class="font-mono text-xs text-gray-700">{{ $trainingJobId }}</div>
                    </div>
                </div>
            @endif

            {{-- ---- BERTopic Settings ---- --}}
            <x-ui.card no-padding>
                <div class="border-b border-gray-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Konfigurasi BERTopic</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Best params dari eksperimen.</p>
                </div>
                <div class="space-y-4 px-5 py-4">
                    {{-- UMAP --}}
                    <div>
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">UMAP</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-gray-600">n_neighbors</label>
                                <input type="number" min="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-unsoed-blue-500 focus:ring-unsoed-blue-500" wire:model.live="bertopicParams.umap_params.n_neighbors" />
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">n_components</label>
                                <input type="number" min="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-unsoed-blue-500 focus:ring-unsoed-blue-500" wire:model.live="bertopicParams.umap_params.n_components" />
                            </div>
                            <div class="col-span-2">
                                <label class="text-xs text-gray-600">min_dist</label>
                                <input type="number" step="0.01" min="0" max="1" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-unsoed-blue-500 focus:ring-unsoed-blue-500" wire:model.live="bertopicParams.umap_params.min_dist" />
                            </div>
                        </div>
                    </div>

                    {{-- HDBSCAN --}}
                    <div>
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">HDBSCAN</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-gray-600">min_cluster_size</label>
                                <input type="number" min="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-unsoed-blue-500 focus:ring-unsoed-blue-500" wire:model.live="bertopicParams.hdbscan_params.min_cluster_size" />
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">min_samples</label>
                                <input type="number" min="1" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-unsoed-blue-500 focus:ring-unsoed-blue-500" wire:model.live="bertopicParams.hdbscan_params.min_samples" />
                            </div>
                        </div>
                    </div>

                    {{-- BERTopic --}}
                    <div>
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">BERTopic</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-gray-600">nr_topics</label>
                                <input type="number" min="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-unsoed-blue-500 focus:ring-unsoed-blue-500" wire:model.live="bertopicParams.nr_topics" />
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">min_topic_size</label>
                                <input type="number" min="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-unsoed-blue-500 focus:ring-unsoed-blue-500" wire:model.live="bertopicParams.min_topic_size" />
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            {{-- ---- Preprocessing Note ---- --}}
            <x-ui.alert type="warning">
                <div class="font-semibold">Strategi preprocessing dual-pipeline:</div>
                <ul class="mt-1 space-y-0.5 text-amber-700">
                    <li>• <strong>BERTopic:</strong> soft clean — tidak hapus stopword, tidak stemming</li>
                    <li>• <strong>LDA:</strong> full clean + stopword removal + stemming Sastrawi</li>
                </ul>
            </x-ui.alert>

            {{-- ---- Riwayat Run ---- --}}
            <x-ui.card no-padding>
                <div class="border-b border-gray-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Riwayat Run</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($latestRuns as $r)
                        @php
                            $badgeType = match($r->status) {
                                'completed'     => 'success',
                                'training'      => 'info',
                                'preprocessing' => 'warning',
                                'failed'        => 'error',
                                default         => 'default',
                            };
                        @endphp
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <div>
                                <div class="text-xs font-semibold text-gray-800">Run #{{ $r->id }}</div>
                                <div class="text-xs text-gray-400">{{ $r->created_at?->format('d/m/Y H:i') }}</div>
                                @if($r->num_topics)
                                    <div class="text-xs text-gray-500">{{ $r->num_topics }} topik | C_v: {{ $r->coherence_cv ? number_format($r->coherence_cv, 3) : '-' }}</div>
                                @endif
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

    </div>
</div>
