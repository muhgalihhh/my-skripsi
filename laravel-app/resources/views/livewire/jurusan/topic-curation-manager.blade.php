<div>
    @section('page-title', 'Manajemen Topik')

    <div class="space-y-5">
        {{-- ── Header ─────────────────────────────────────── --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Manajemen Topik</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Beri nama topik dan tambahkan deskripsi representasi untuk hasil ekstraksi BERTopic/LDA.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-right shadow-sm">
                    <p class="text-[11px] text-gray-500">Run Selesai</p>
                    <p class="text-xl font-bold text-unsoed-blue-700">{{ $completedRuns->count() }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-right shadow-sm">
                    <p class="text-[11px] text-emerald-600">Topik Terkurasi</p>
                    <p class="text-xl font-bold text-emerald-700">{{ $curatedCount }}</p>
                </div>
            </div>
        </div>

        {{-- ── Filter ─────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Cari</label>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="Cari nama/deskripsi topik, nomor topik, atau ID run..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Run</label>
                    <select
                        wire:model.live="runFilter"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                    >
                        <option value="">Semua run selesai</option>
                        @foreach ($completedRuns as $run)
                            <option value="{{ $run->id }}">
                                Run #{{ $run->id }} - {{ strtoupper($run->model_type ?? '-') }}
                                ({{ $run->num_topics ?? 0 }} topik)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Per Halaman</label>
                    <select
                        wire:model.live="perPage"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                    >
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>

            <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-500">
                    Topik diambil dari run dengan status <strong>completed</strong> milik akun jurusan yang sedang login.
                </p>
                <button
                    wire:click="clearFilters"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                >
                    Reset Filter
                </button>
            </div>
        </div>

        {{-- ── Table ──────────────────────────────────────── --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            @if ($topics->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Run</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Topik</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kata Kunci Ekstraksi</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nama Topik</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Deskripsi Representasi</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($topics as $topic)
                                @php
                                    $isCurated = filled($topic->custom_name) || filled($topic->representation_description);
                                @endphp
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 align-top">
                                        <div class="text-xs font-semibold text-gray-800">Run #{{ $topic->topic_model_run_id }}</div>
                                        <div class="mt-0.5 text-[11px] text-gray-500">
                                            {{ strtoupper($topic->run?->model_type ?? '-') }}
                                            @if ($topic->run?->completed_at)
                                                • {{ $topic->run->completed_at->format('d/m/Y H:i') }}
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 align-top">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-unsoed-blue-100 text-xs font-bold text-unsoed-blue-800">
                                            {{ $topic->topic_id }}
                                        </span>
                                        <div class="mt-1 text-[11px] text-gray-500">{{ $topic->count }} dokumen</div>
                                    </td>

                                    <td class="px-4 py-3 align-top">
                                        <div class="flex max-w-xs flex-wrap gap-1">
                                            @foreach (array_slice($topic->top_words ?? [], 0, 15) as $word)
                                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ $word }}</span>
                                            @endforeach
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 align-top">
                                        @if (filled($topic->custom_name))
                                            <div class="text-sm font-semibold text-unsoed-blue-800">{{ $topic->custom_name }}</div>
                                        @else
                                            <div class="text-xs text-gray-400">Belum diberi nama</div>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 align-top">
                                        @if (filled($topic->representation_description))
                                            <div class="max-w-sm text-xs leading-relaxed text-gray-700 line-clamp-3">
                                                {{ $topic->representation_description }}
                                            </div>
                                        @else
                                            <div class="text-xs text-gray-400">Belum ada deskripsi representasi</div>
                                        @endif
                                        <div class="mt-2">
                                            @if ($isCurated)
                                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Sudah dikurasi</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">Belum dikurasi</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 align-top">
                                        <button
                                            type="button"
                                            wire:click="openEditModal({{ $topic->id }})"
                                            class="inline-flex items-center rounded-lg border border-unsoed-blue-200 bg-unsoed-blue-50 px-3 py-1.5 text-xs font-semibold text-unsoed-blue-700 transition hover:bg-unsoed-blue-100"
                                        >
                                            Edit Kurasi
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-100 px-4 py-3 bg-gray-50/50">
                    {{ $topics->links() }}
                </div>
            @else
                <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                    <x-app.icon name="inbox-stack" class="mb-4 h-14 w-14 text-gray-200" />
                    <p class="text-sm font-semibold text-gray-500">Belum ada topik yang dapat dikurasi</p>
                    <p class="mt-1 text-xs text-gray-400">
                        Jalankan training dan pastikan status run sudah <strong>completed</strong>.
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════
         MODAL: Edit Kurasi Topik
    ═════════════════════════════════════════════════════ --}}
    <div
        x-data="{ open: @entangle('showEditModal').live }"
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
            @click="$wire.closeEditModal()"
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
                class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl"
            >
                <div class="bg-gradient-to-r from-unsoed-blue-700 to-unsoed-blue-600 px-6 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-bold text-white">Edit Kurasi Topik</h3>
                            <p class="mt-0.5 text-xs text-unsoed-blue-100">
                                Run #{{ $editRunId ?? '-' }} • Topik {{ $editTopicId ?? '-' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="closeEditModal"
                            class="rounded-lg bg-white/10 p-2 text-white transition hover:bg-white/20"
                        >
                            <x-app.icon name="x-mark" class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div class="space-y-4 px-6 py-5">
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Kata Kunci Ekstraksi</p>
                        <div class="flex flex-wrap gap-1.5 rounded-lg border border-gray-200 bg-gray-50 p-3">
                            @forelse ($editTopWords as $word)
                                <span class="rounded-full bg-white px-2 py-0.5 text-xs text-gray-700 shadow-sm">{{ $word }}</span>
                            @empty
                                <span class="text-xs text-gray-400">Tidak ada kata kunci.</span>
                            @endforelse
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="generateAiSuggestion"
                                wire:loading.attr="disabled"
                                wire:target="generateAiSuggestion"
                                class="inline-flex items-center rounded-lg border border-cyan-300 bg-cyan-50 px-3 py-2 text-sm font-medium text-cyan-700 transition hover:bg-cyan-100 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 2a1 1 0 0 1 .95.684l1.1 3.383a1 1 0 0 0 .63.63l3.383 1.1a1 1 0 0 1 0 1.903l-3.383 1.1a1 1 0 0 0-.63.63l-1.1 3.383a1 1 0 0 1-1.903 0l-1.1-3.383a1 1 0 0 0-.63-.63l-3.383-1.1a1 1 0 0 1 0-1.903l3.383-1.1a1 1 0 0 0 .63-.63l1.1-3.383A1 1 0 0 1 10 2Z" />
                                </svg>
                                Generate AI (Gemini)
                            </button>
                            <span wire:loading wire:target="generateAiSuggestion" class="text-xs text-gray-500">
                                Memproses saran AI...
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Nama Topik (Manager)
                        </label>
                        <input
                            wire:model.defer="editCustomName"
                            type="text"
                            maxlength="150"
                            placeholder="Contoh: Topik Sistem Rekomendasi Akademik"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                        >
                        @error('editCustomName')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Deskripsi Representasi Topik
                        </label>
                        <textarea
                            wire:model.defer="editRepresentationDescription"
                            rows="5"
                            maxlength="2000"
                            placeholder="Jelaskan secara ringkas makna topik berdasarkan kata kunci dan dokumen yang terklaster."
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm leading-relaxed focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                        ></textarea>
                        @error('editRepresentationDescription')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-6 py-4">
                    <button
                        type="button"
                        wire:click="closeEditModal"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        wire:click="saveTopicCuration"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-unsoed-blue-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-unsoed-blue-800 disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="saveTopicCuration">Simpan Kurasi</span>
                        <span wire:loading wire:target="saveTopicCuration">Menyimpan...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
