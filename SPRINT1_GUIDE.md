# Sprint 1 - Arsitektur & Setup Guide

## Sistem Analisis Evolusi Topik Riset Skripsi - UNSOED

---

## 🏗️ Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────┐
│                    BROWSER (User: Jurusan)                   │
│                    http://localhost:8080                      │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              LARAVEL (Web Application)                        │
│              Port: 8080                                      │
│                                                              │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────────┐  │
│  │ Auth System  │  │  Dashboard   │  │ Scraping Manager   │  │
│  │ (Login)      │  │  (Stats)     │  │ (Trigger + Store)  │  │
│  └─────────────┘  └──────────────┘  └─────────┬──────────┘  │
│                                                │              │
│  ┌─────────────────────────────────────────────┼──────────┐  │
│  │ Database SQLite                             │          │  │
│  │ ┌──────────┐ ┌──────────┐ ┌──────────────┐ │          │  │
│  │ │  users   │ │ skripsi  │ │ scraping_logs│ │          │  │
│  │ │ (+role)  │ │ (data)   │ │ (history)    │ │          │  │
│  │ └──────────┘ └──────────┘ └──────────────┘ │          │  │
│  └─────────────────────────────────────────────┘          │  │
│              HTTP Client (Laravel Http Facade)  │          │  │
└──────────────────────────────────────────────────┼─────────┘
                                                   │
                                                   ▼
┌─────────────────────────────────────────────────────────────┐
│              FASTAPI (Microservice)                           │
│              Port: 8000                                      │
│                                                              │
│  ┌──────────────────┐  ┌─────────────────────────────────┐  │
│  │ /api/v1/scraping  │  │ /api/v1/health                 │  │
│  │   POST /start     │  │ /api/v1/preprocessing          │  │
│  │   GET /status     │  │ /api/v1/training               │  │
│  └────────┬─────────┘  │ /api/v1/evaluation              │  │
│           │             └─────────────────────────────────┘  │
│           ▼                                                  │
│  ┌──────────────────┐                                        │
│  │ ScrapingService   │ ──→ repository.unsoed.ac.id           │
│  │ (BeautifulSoup)   │    (scrape thesis data)               │
│  └──────────────────┘                                        │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 File yang Dibuat/Dimodifikasi (Sprint 1)

### Laravel (laravel-app/)

| File                                                                   | Deskripsi                                     |
| ---------------------------------------------------------------------- | --------------------------------------------- |
| `database/migrations/2026_03_08_000001_add_role_to_users_table.php`    | Menambah kolom `role` ke tabel users          |
| `database/migrations/2026_03_08_000002_create_skripsi_table.php`       | Tabel penyimpanan data skripsi                |
| `database/migrations/2026_03_08_000003_create_scraping_logs_table.php` | Tabel log/riwayat scraping                    |
| `database/seeders/JurusanSeeder.php`                                   | Seeder akun admin Jurusan                     |
| `app/Models/User.php`                                                  | ✏️ Ditambah: role, isJurusan(), isMahasiswa() |
| `app/Models/Skripsi.php`                                               | 🆕 Model data skripsi                         |
| `app/Models/ScrapingLog.php`                                           | 🆕 Model log scraping                         |
| `app/Http/Middleware/EnsureUserIsJurusan.php`                          | 🆕 Middleware cek role jurusan                |
| `app/Http/Controllers/Auth/LoginController.php`                        | 🆕 Login controller                           |
| `app/Http/Controllers/Jurusan/DashboardController.php`                 | 🆕 Dashboard jurusan                          |
| `app/Http/Controllers/Jurusan/ScrapingController.php`                  | 🆕 Scraping controller (trigger + store)      |
| `app/Services/FastApiService.php`                                      | 🆕 HTTP client ke FastAPI                     |
| `app/Console/Commands/RunScheduledScraping.php`                        | 🆕 Artisan command untuk cron                 |
| `routes/web.php`                                                       | ✏️ Routes auth + jurusan                      |
| `routes/console.php`                                                   | ✏️ Schedule scraping mingguan                 |
| `config/services.php`                                                  | ✏️ Tambah config FastAPI                      |
| `resources/views/layouts/app.blade.php`                                | 🆕 Base layout                                |
| `resources/views/layouts/jurusan.blade.php`                            | 🆕 Layout + navbar jurusan                    |
| `resources/views/auth/login.blade.php`                                 | 🆕 Halaman login                              |
| `resources/views/jurusan/dashboard.blade.php`                          | 🆕 Dashboard + quick scraping                 |
| `resources/views/jurusan/scraping/index.blade.php`                     | 🆕 Halaman manajemen scraping                 |

### FastAPI (fastapi/)

| File                         | Deskripsi                                     |
| ---------------------------- | --------------------------------------------- |
| `app/api/routes/scraping.py` | 🆕 Endpoint POST /start, GET /status          |
| `app/services/scraping.py`   | ✏️ Implementasi penuh dari scraping_unsoed.py |
| `app/main.py`                | ✏️ Register scraping router                   |
| `requirements.txt`           | ✏️ Tambah pdfplumber                          |

---

## 📊 Skema Database

### Tabel `users` (modified)

| Kolom                   | Tipe                        | Keterangan         |
| ----------------------- | --------------------------- | ------------------ |
| id                      | bigint                      | PK, auto-increment |
| name                    | string                      | Nama user          |
| email                   | string                      | Email (unique)     |
| **role**                | enum('jurusan','mahasiswa') | **🆕 Role user**   |
| password                | string                      | Hashed password    |
| email_verified_at       | timestamp                   | nullable           |
| remember_token          | string                      | nullable           |
| created_at / updated_at | timestamps                  |                    |

### Tabel `skripsi` (new)

| Kolom                   | Tipe       | Keterangan              |
| ----------------------- | ---------- | ----------------------- |
| id                      | bigint     | PK                      |
| title                   | string     | Judul skripsi           |
| abstract                | text       | Abstrak (nullable)      |
| type                    | string     | Tipe dokumen (nullable) |
| id_code                 | string     | ID Code dari repository |
| keywords                | text       | Kata kunci              |
| subjects                | string     | Subjek                  |
| divisions               | string     | Divisi/fakultas         |
| author                  | string     | Nama penulis            |
| deposit_date            | string     | Tanggal deposit         |
| modified_date           | string     | Tanggal modifikasi      |
| uri                     | string     | URI repository          |
| **year**                | integer    | **Tahun (indexed)**     |
| pdf_documents           | json       | Link PDF per bab        |
| **url**                 | string     | **URL unik (unique)**   |
| conclusion              | text       | Kesimpulan              |
| conclusion_source       | string     | Sumber kesimpulan       |
| created_at / updated_at | timestamps |                         |

### Tabel `scraping_logs` (new)

| Kolom                   | Tipe                                           | Keterangan               |
| ----------------------- | ---------------------------------------------- | ------------------------ |
| id                      | bigint                                         | PK                       |
| user_id                 | foreignId                                      | FK ke users              |
| trigger_type            | enum('manual','scheduled')                     | Jenis trigger            |
| status                  | enum('pending','running','completed','failed') | Status proses            |
| total_scraped           | integer                                        | Total dokumen di-scrape  |
| new_added               | integer                                        | Dokumen baru ditambahkan |
| duplicates_skipped      | integer                                        | Duplikat dilewati        |
| error_message           | text                                           | Pesan error (nullable)   |
| started_at              | timestamp                                      | Waktu mulai              |
| completed_at            | timestamp                                      | Waktu selesai            |
| created_at / updated_at | timestamps                                     |                          |

---

## 🚀 Cara Menjalankan

### 1. Setup Laravel

```powershell
cd laravel-app

# Copy .env
copy .env.example .env

# Tambahkan ke .env:
# FASTAPI_BASE_URL=http://localhost:8000

# Generate key
php artisan key:generate

# Jalankan migrasi
php artisan migrate

# Seed akun jurusan
php artisan db:seed --class=JurusanSeeder

# Build frontend assets
npm install
npm run build

# Jalankan Laravel
php artisan serve --port=8080
```

### 2. Setup FastAPI

```powershell
cd fastapi

# Buat virtual environment
python -m venv venv
.\venv\Scripts\Activate

# Install dependencies
pip install -r requirements.txt

# Jalankan FastAPI
uvicorn app.main:app --reload --port 8000
```

### 3. Login

- Buka: `http://localhost:8080/login`
- **Email:** `jurusan@unsoed.ac.id`
- **Password:** `password`

### 4. Scraping

- Klik **"Mulai Scrapping"** di Dashboard, atau
- Buka halaman **Scraping** untuk konfigurasi lebih detail

### 5. Scheduled Scraping (Cron)

**Manual via CLI:**

```powershell
php artisan scraping:run --start-year=2019 --end-year=2026
```

**Otomatis (setiap Senin 02:00 WIB):**

Di Linux/Mac (crontab):

```
* * * * * cd /path/to/laravel-app && php artisan schedule:run >> /dev/null 2>&1
```

Di Windows (Task Scheduler):

- Buat task baru yang menjalankan setiap menit:
  ```
  php artisan schedule:run
  ```
- Atau buat task mingguan langsung:
  ```
  php artisan scraping:run
  ```

---

## 🔗 API Endpoints (FastAPI)

| Method | Endpoint                    | Deskripsi                 |
| ------ | --------------------------- | ------------------------- |
| GET    | `/api/v1/health`            | Health check              |
| POST   | `/api/v1/scraping/start`    | Mulai scraping            |
| GET    | `/api/v1/scraping/status`   | Status scraping           |
| POST   | `/api/v1/preprocessing/run` | Preprocessing (existing)  |
| POST   | `/api/v1/training/train`    | Training model (existing) |

Dokumentasi interaktif: `http://localhost:8000/docs`
