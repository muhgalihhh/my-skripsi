<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Topic Modeling (BERTopic)</h1>
            <p class="mt-1 text-sm text-gray-600">Preprocessing → training → hasil tersimpan di database.</p>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" wire:click="buildPreview"
                class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                Refresh preview
            </button>

            <button type="button" wire:click="runPreprocessing" wire:loading.attr="disabled"
                class="inline-flex items-center rounded-md bg-unsoed-blue-800 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-unsoed-blue-900 disabled:opacity-60">
                Preprocessing
            </button>

            <button type="button" wire:click="startTraining" wire:loading.attr="disabled"
                class="inline-flex items-center rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 disabled:opacity-60">
                Start training
            </button>
        </div>
    </div>

    @if ($statusMessage)
        @php
            $statusClass = match ($statusType) {
                'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                'error' => 'border-red-200 bg-red-50 text-red-900',
                'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
                default => 'border-blue-200 bg-blue-50 text-blue-900',
            };
        @endphp
        <div class="rounded-lg border p-4 text-sm {{ $statusClass }}">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-xl border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Preview preprocessing (step-by-step)</h2>
                    <p class="mt-1 text-xs text-gray-500">Contoh 5 abstrak terakhir untuk transparansi pipeline (dibikin
                        tab biar ringkas).</p>
                </div>

                <div class="divide-y divide-gray-200">
                    @forelse($previewRows as $row)
                        <div class="px-5 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="text-sm font-semibold text-gray-900">#{{ $row['id'] }} —
                                    {{ $row['title'] }}</div>
                                <div class="text-xs text-gray-500">{{ $row['year'] }}</div>
                            </div>

                            <div class="mt-3" x-data="{ tab: 'final' }">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="tab='final'"
                                        class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
                                        :class="tab === 'final' ? 'bg-unsoed-blue-800 text-white ring-unsoed-blue-800' :
                                            'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50'">Final</button>
                                    <button type="button" @click="tab='raw'"
                                        class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
                                        :class="tab === 'raw' ? 'bg-unsoed-blue-800 text-white ring-unsoed-blue-800' :
                                            'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50'">Raw</button>
                                    <button type="button" @click="tab='cleaned'"
                                        class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
                                        :class="tab === 'cleaned' ? 'bg-unsoed-blue-800 text-white ring-unsoed-blue-800' :
                                            'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50'">Cleaned</button>
                                    <button type="button" @click="tab='tokens'"
                                        class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
                                        :class="tab === 'tokens' ? 'bg-unsoed-blue-800 text-white ring-unsoed-blue-800' :
                                            'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50'">Tokens</button>
                                    <button type="button" @click="tab='filtered'"
                                        class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
                                        :class="tab === 'filtered' ? 'bg-unsoed-blue-800 text-white ring-unsoed-blue-800' :
                                            'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50'">Filtered</button>
                                    <button type="button" @click="tab='nostop'"
                                        class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
                                        :class="tab === 'nostop' ? 'bg-unsoed-blue-800 text-white ring-unsoed-blue-800' :
                                            'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50'">No
                                        stopwords</button>
                                </div>

                                <div class="mt-3 rounded-lg bg-gray-50 p-4">
                                    <div x-show="tab==='final'" class="space-y-1">
                                        <div class="text-xs font-semibold text-gray-700">Final cleaned_text (untuk
                                            BERTopic embeddings)</div>
                                        <div class="text-xs text-gray-700 break-words">{{ $row['final_cleaned_text'] }}
                                        </div>
                                        <div class="mt-2 text-xs text-gray-500">
                                            Catatan: sesuai notebook, stopwords untuk BERTopic idealnya dipakai di <span
                                                class="font-semibold">c-TF-IDF (vectorizer)</span>, bukan dibuang dari
                                            input embedding.
                                        </div>
                                    </div>

                                    <div x-show="tab==='raw'" class="space-y-1">
                                        <div class="text-xs font-semibold text-gray-700">Raw</div>
                                        <div class="text-xs text-gray-700 break-words">{{ $row['raw'] }}</div>
                                    </div>

                                    <div x-show="tab==='cleaned'" class="space-y-1">
                                        <div class="text-xs font-semibold text-gray-700">Cleaned (lowercase + noise
                                            removed)</div>
                                        <div class="text-xs text-gray-700 break-words">{{ $row['cleaned'] }}</div>
                                    </div>

                                    <div x-show="tab==='tokens'" class="space-y-1">
                                        <div class="text-xs font-semibold text-gray-700">Tokenized (first 60)</div>
                                        <div class="text-xs text-gray-700 break-words">
                                            {{ implode(' ', array_slice($row['tokenized'], 0, 60)) }}@if (count($row['tokenized']) > 60)
                                                …
                                            @endif
                                        </div>
                                    </div>

                                    <div x-show="tab==='filtered'" class="space-y-1">
                                        <div class="text-xs font-semibold text-gray-700">Filtered tokens (min length,
                                            first 60)</div>
                                        <div class="text-xs text-gray-700 break-words">
                                            {{ implode(' ', array_slice($row['filtered_tokens'], 0, 60)) }}@if (count($row['filtered_tokens']) > 60)
                                                …
                                            @endif
                                        </div>
                                    </div>

                                    <div x-show="tab==='nostop'" class="space-y-1">
                                        <div class="text-xs font-semibold text-gray-700">Stopwords removed (first 60)
                                        </div>
                                        <div class="text-xs text-gray-700 break-words">
                                            {{ implode(' ', array_slice($row['stopwords_removed'], 0, 60)) }}
                                            @if (count($row['stopwords_removed']) > 60)
                                                …
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @empty
                            <div class="px-5 py-6 text-sm text-gray-600">Belum ada data preview.</div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h2 class="text-sm font-semibold text-gray-900">Hasil topic modeling</h2>
                        <p class="mt-1 text-xs text-gray-500">Diambil dari database (run aktif).</p>
                    </div>

                    <div class="px-5 py-4">
                        @if ($activeRun && $activeRun->status === 'completed')
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="rounded-lg bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Num topics</div>
                                    <div class="mt-1 text-sm font-semibold text-gray-900">
                                        {{ $activeRun->num_topics ?? '-' }}</div>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Coherence (c_v)</div>
                                    <div class="mt-1 text-sm font-semibold text-gray-900">
                                        {{ $activeRun->coherence_cv ?? '-' }}</div>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Topic diversity</div>
                                    <div class="mt-1 text-sm font-semibold text-gray-900">
                                        {{ $activeRun->topic_diversity ?? '-' }}</div>
                                </div>
                            </div>

                            <div class="mt-5 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Topic</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Count</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Top words
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        @foreach ($activeRun->topics->sortBy('topic_id') as $t)
                                            <tr>
                                                <td class="whitespace-nowrap px-3 py-2 font-semibold text-gray-900">
                                                    {{ $t->topic_id }}</td>
                                                <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $t->count }}
                                                </td>
                                                <td class="px-3 py-2 text-gray-700">
                                                    {{ implode(', ', array_slice($t->top_words ?? [], 0, 12)) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @elseif($activeRun && $activeRun->status === 'training')
                            <div class="text-sm text-gray-700">Training sedang berjalan…</div>
                        @else
                            <div class="text-sm text-gray-600">Belum ada hasil. Jalankan preprocessing lalu training.</div>
                        @endif
                    </div>
                </section>
            </div>

            <div class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h2 class="text-sm font-semibold text-gray-900">Settings</h2>
                    </div>

                    <div class="space-y-4 px-5 py-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">Remove stopwords</div>
                                <div class="text-xs text-gray-500">Untuk input BERTopic (cleaned_text).</div>
                            </div>
                            <input type="checkbox" class="h-4 w-4 rounded border-gray-300"
                                wire:model.live="removeStopwords" />
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-900">Min word length</label>
                            <input type="number" min="1" class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                wire:model.live="minWordLength" />
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-900">Language</label>
                            <select class="mt-1 w-full rounded-md border-gray-300 text-sm" wire:model.live="language">
                                <option value="indonesian">indonesian</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h2 class="text-sm font-semibold text-gray-900">BERTopic params (best preset)</h2>
                        <p class="mt-1 text-xs text-gray-500">Default di-load dari <code
                                class="font-mono">metadata.json</code>.</p>
                    </div>

                    <div class="space-y-4 px-5 py-4">
                        <div>
                            <label class="text-sm font-semibold text-gray-900">UMAP n_neighbors</label>
                            <input type="number" min="2" class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                wire:model.live="bertopicParams.umap_params.n_neighbors" />
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-900">UMAP n_components</label>
                            <input type="number" min="2" class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                wire:model.live="bertopicParams.umap_params.n_components" />
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-900">UMAP min_dist</label>
                            <input type="number" step="0.01" min="0" max="1"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                wire:model.live="bertopicParams.umap_params.min_dist" />
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-900">HDBSCAN min_cluster_size</label>
                            <input type="number" min="2" class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                wire:model.live="bertopicParams.hdbscan_params.min_cluster_size" />
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-900">min_topic_size</label>
                            <input type="number" min="2" class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                wire:model.live="bertopicParams.min_topic_size" />
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h2 class="text-sm font-semibold text-gray-900">Training status</h2>
                    </div>

                    <div class="px-5 py-4">
                        @if ($trainingJobId)
                            <div wire:poll.5s="pollTrainingProgress" class="space-y-2">
                                <div class="text-xs text-gray-500">Job ID</div>
                                <div class="rounded bg-gray-50 px-2 py-1 font-mono text-xs text-gray-700">
                                    {{ $trainingJobId }}</div>

                                <div class="text-xs text-gray-500">Progress</div>
                                <div class="h-2 w-full overflow-hidden rounded bg-gray-100">
                                    <div class="h-2 bg-unsoed-blue-800"
                                        style="width: {{ max(0, min(100, (int) $trainingProgress)) }}%"></div>
                                </div>
                                <div class="text-xs text-gray-600">{{ $trainingProgress }}% {{ $trainingMessage }}</div>
                            </div>
                        @else
                            <div class="text-sm text-gray-600">Belum ada job training.</div>
                        @endif
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h2 class="text-sm font-semibold text-gray-900">Riwayat run (10 terakhir)</h2>
                    </div>
                    <div class="divide-y divide-gray-200">
                        @foreach ($latestRuns as $r)
                            <div class="px-5 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900">Run #{{ $r->id }}</div>
                                        <div class="text-xs text-gray-500">{{ $r->created_at?->format('Y-m-d H:i') }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs font-semibold text-gray-700">{{ $r->status }}</div>
                                        <div class="text-xs text-gray-500">topics: {{ $r->topics_count }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>
    </div>
