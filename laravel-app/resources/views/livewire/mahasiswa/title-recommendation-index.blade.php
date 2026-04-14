@section('page-title', 'Rekomendasi Judul Skripsi')

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Rekomendasi Judul Skripsi</h1>
            <p class="mt-1 text-sm text-gray-500">Pilih topik untuk membuka halaman detail dan menghasilkan rekomendasi judul berbasis Gemini.</p>
        </div>

        <div class="w-full sm:w-80">
            <label for="topic-search" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Cari Topik</label>
            <input id="topic-search" type="text" wire:model.live.debounce.300ms="search"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                placeholder="Cari nama topik atau ID...">
        </div>
    </div>

    @if ($activeRun)
        <div class="rounded-2xl border border-unsoed-blue-100 bg-unsoed-blue-50 px-4 py-3 text-sm text-unsoed-blue-800">
            <span class="font-semibold">Run Aktif:</span>
            #{{ $activeRun->id }} • BERTopic •
            {{ $activeRun->completed_at?->format('d M Y H:i') ?? '-' }}
        </div>

        @if (!empty($topicCards))
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Topik</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Jumlah Dokumen</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kata Kunci</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($topicCards as $topic)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 align-top text-sm font-semibold text-gray-800">{{ $topic['topic_label'] }}</td>
                                <td class="px-4 py-3 align-top text-right text-sm font-semibold text-gray-700">{{ number_format((int) ($topic['doc_count'] ?? 0)) }}</td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex max-w-xl flex-wrap gap-1.5">
                                        @foreach (array_slice($topic['top_words'] ?? [], 0, 15) as $word)
                                            <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-medium text-gray-700">{{ $word }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top text-center">
                                    <a href="{{ route('mahasiswa.rekomendasi-judul.detail', ['topicRowId' => $topic['topic_row_id']]) }}"
                                        class="inline-flex items-center rounded-lg border border-unsoed-blue-200 bg-unsoed-blue-50 px-2.5 py-1 text-xs font-semibold text-unsoed-blue-700 transition hover:bg-unsoed-blue-100">
                                        <x-app.icon name="light-bulb" class="mr-1 h-3.5 w-3.5" />
                                        Buka Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-sm text-gray-500">
                Topik tidak ditemukan untuk kata kunci pencarian saat ini.
            </div>
        @endif
    @else
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-5 text-sm text-amber-800 shadow-sm">
            Belum ada run BERTopic selesai yang bisa dipakai untuk rekomendasi judul.
        </div>
    @endif
</div>
