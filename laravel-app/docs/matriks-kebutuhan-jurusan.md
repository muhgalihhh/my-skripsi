# Matriks Kebutuhan Fitur Role Jurusan

Tanggal audit: 2026-04-09
Ruang lingkup: modul Laravel role Jurusan pada aplikasi Topic Modeling Skripsi.

## Catatan
- Dokumen ini adalah matriks implementasi teknis untuk membantu jejak-bukti skripsi.
- Sumber kebutuhan detail tetap mengacu ke dokumen skripsi utama (BAB kebutuhan sistem).

## Matriks Kebutuhan

| Kebutuhan Fitur Jurusan | Implementasi Saat Ini | Status | Bukti Implementasi |
| --- | --- | --- | --- |
| Login dan proteksi akses khusus Jurusan | Middleware role + route group jurusan | Terpenuhi | `routes/web.php`, `app/Http/Middleware/EnsureUserIsJurusan.php` |
| Dashboard ringkasan data skripsi dan scraping | Livewire Dashboard (total, distribusi tahun, log scraping) | Terpenuhi | `app/Livewire/Jurusan/Dashboard.php`, `resources/views/livewire/jurusan/dashboard.blade.php` |
| Scraping repository skripsi (start, monitor, cancel, reset) | FastAPI async orchestration + monitoring + reset guard | Terpenuhi | `app/Livewire/Jurusan/ScrapingManager.php`, `resources/views/livewire/jurusan/scraping-manager.blade.php` |
| Manajemen data skripsi (cari, filter, edit, hapus, export) | CRUD + bulk action + export CSV/Excel | Terpenuhi | `app/Livewire/Jurusan/SkripsiManager.php`, `resources/views/livewire/jurusan/skripsi-manager.blade.php` |
| Topic Modeling (preprocessing, training, status, unduh model) | Pipeline preprocessing + training + polling + download + modal mapping | Terpenuhi | `app/Livewire/Jurusan/TopicModelingManager.php`, `resources/views/livewire/jurusan/topic-modeling-manager.blade.php` |
| Kurasi topik (nama topik + deskripsi) | Edit metadata topik per run completed | Terpenuhi | `app/Livewire/Jurusan/TopicCurationManager.php`, `resources/views/livewire/jurusan/topic-curation-manager.blade.php` |
| Visualisasi topik (wordcloud, DTM, trend, mapping skripsi-topik) | Tab visualisasi + chart + tabel/card mapping | Terpenuhi | `app/Livewire/Jurusan/VisualizationManager.php`, `resources/views/livewire/jurusan/visualization-manager.blade.php` |
| Manajemen akun user | Tambah, edit, hapus, bulk delete | Terpenuhi | `app/Livewire/Jurusan/AccountManager.php`, `resources/views/livewire/jurusan/account-manager.blade.php` |
| Keamanan role akun sendiri | Guard anti demote role akun Jurusan sendiri | Terpenuhi | `app/Livewire/Jurusan/AccountManager.php` |
| Validasi otomatis lewat test | Baseline feature tests akses Jurusan + guard account manager | Parsial | `tests/Feature/JurusanAccessTest.php`, `tests/Feature/JurusanAccountManagerTest.php` |

## Gap Lanjutan (Prioritas)
1. Tambah test skenario E2E per modul (scraping, topic modeling, visualisasi) agar coverage fitur kritikal meningkat.
2. Turunkan setiap butir kebutuhan skripsi ke acceptance criteria terukur untuk penilaian akhir.
3. Tambahkan checklist UAT per role dan bukti screenshot per fitur untuk lampiran skripsi.
