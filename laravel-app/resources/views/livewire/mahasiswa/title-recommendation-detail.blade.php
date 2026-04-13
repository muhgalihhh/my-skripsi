@section('page-title', 'Detail Rekomendasi Judul')

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Detail Rekomendasi Judul Skripsi</h1>
            <p class="mt-1 text-sm text-gray-500">Gunakan konteks topik, mapping skripsi, dan prompt terarah untuk menghasilkan rekomendasi judul.</p>
        </div>

        <a href="{{ route('mahasiswa.rekomendasi-judul.index') }}"
            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
            <x-app.icon name="arrow-left" class="mr-1.5 h-4 w-4" />
            Kembali ke Daftar Topik
        </a>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold text-gray-900">{{ $topicContext['topic_label'] ?? 'Topik' }}</h2>
            <span class="rounded-full bg-unsoed-blue-50 px-2.5 py-1 text-xs font-semibold text-unsoed-blue-700">
                {{ number_format((int) ($topicContext['doc_count'] ?? 0)) }} dokumen
            </span>
        </div>

        <div class="flex flex-wrap gap-1.5">
            @foreach (($topicContext['top_words'] ?? []) as $word)
                <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-medium text-gray-700">{{ $word }}</span>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Generate Rekomendasi Judul</h2>
            <p class="text-xs text-gray-500">Prompt akan dicek dulu agar tetap berada dalam konteks topik skripsi.</p>
        </div>

        <form wire:submit.prevent="generateRecommendations" class="space-y-4">
            <div>
                <label for="prompt" class="mb-1 block text-sm font-semibold text-gray-700">Prompt</label>
                <textarea id="prompt" rows="4" wire:model.defer="userPrompt"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500"
                    placeholder="Contoh: Saya ingin judul skripsi tentang klasifikasi sentimen ulasan aplikasi mobile dengan pendekatan pembelajaran mesin."></textarea>
                @error('userPrompt')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="w-full sm:w-48">
                <label for="recommendationsCount" class="mb-1 block text-sm font-semibold text-gray-700">Jumlah Rekomendasi</label>
                <select id="recommendationsCount" wire:model.defer="recommendationsCount"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-unsoed-blue-500 focus:ring-2 focus:ring-unsoed-blue-500">
                    @for ($count = 3; $count <= 10; $count++)
                        <option value="{{ $count }}">{{ $count }} judul</option>
                    @endfor
                </select>
                @error('recommendationsCount')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-1">
                <x-ui.button type="submit" variant="primary"
                    wire:loading.attr="disabled"
                    wire:target="generateRecommendations">
                    <x-app.icon name="sparkles" class="mr-1.5 h-4 w-4" wire:loading.remove wire:target="generateRecommendations" />
                    <svg wire:loading wire:target="generateRecommendations" class="mr-1.5 h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a12 12 0 00-12 12h4z"></path>
                    </svg>
                    Generate dengan Gemini
                </x-ui.button>
            </div>
        </form>

        @if (!empty($recommendationError))
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $recommendationError }}
            </div>
        @endif
    </div>

    @if (!empty($recommendationItems))
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Hasil Rekomendasi Judul</h2>
                <p class="text-xs text-gray-500">Silakan gunakan sebagai referensi awal, lalu review kembali bersama dosen pembimbing.</p>
            </div>

            <div class="space-y-3">
                @foreach ($recommendationItems as $index => $item)
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="mb-1 flex items-start gap-2">
                            <span class="mt-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-unsoed-blue-700 px-1 text-[11px] font-semibold text-white">
                                {{ $index + 1 }}
                            </span>
                            <h3 class="text-sm font-semibold text-gray-900">{{ $item['title'] }}</h3>
                        </div>
                        @if (!empty($item['rationale']))
                            <p class="text-xs leading-relaxed text-gray-600">{{ $item['rationale'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-lg font-semibold text-gray-900">Mapping Skripsi Pada Topik</h2>
            <span class="text-xs text-gray-500">Menampilkan {{ count($mappedSkripsi) }} dokumen</span>
        </div>

        @if (!empty($mappedSkripsi))
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="w-full min-w-[840px] text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">ID</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Judul</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Penulis</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Tahun</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($mappedSkripsi as $doc)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2.5 text-xs text-gray-700">#{{ $doc['skripsi_id'] }}</td>
                                <td class="px-3 py-2.5 text-xs leading-relaxed text-gray-800">{{ $doc['title'] }}</td>
                                <td class="px-3 py-2.5 text-xs text-gray-700">{{ $doc['author'] ?? '-' }}</td>
                                <td class="px-3 py-2.5 text-center text-xs text-gray-700">{{ $doc['year'] ?? '-' }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    @if (!empty($doc['url']))
                                        <a href="{{ $doc['url'] }}" target="_blank" rel="noopener"
                                            class="inline-flex items-center rounded-lg border border-unsoed-blue-200 bg-unsoed-blue-50 px-2 py-0.5 text-[11px] font-semibold text-unsoed-blue-700 hover:bg-unsoed-blue-100">
                                            <x-app.icon name="arrow-top-right-on-square" class="mr-1 h-3.5 w-3.5" />
                                            Buka
                                        </a>
                                    @else
                                        <span class="text-[11px] text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-4 text-sm text-gray-500">
                Belum ada dokumen skripsi yang ter-mapping pada topik ini.
            </div>
        @endif
    </div>
</div>
