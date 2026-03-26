# 🐳 Docker Guide - Topic Modeling Skripsi

## Arsitektur

```
┌─────────────────────────────────────────────────┐
│                  Docker Network                  │
│                 (skripsi-net)                     │
│                                                   │
│  ┌──────────┐  ┌──────────────┐  ┌────────────┐ │
│  │  MySQL    │  │   FastAPI    │  │  Laravel   │ │
│  │  :3306    │  │   :8000      │  │  :8080     │ │
│  │          │  │  (ML + API)  │  │ (PHP+Nginx)│ │
│  └──────────┘  └──────────────┘  └────────────┘ │
│       ▲               ▲               │          │
│       │               └───────────────┘          │
│       │            (FASTAPI_BASE_URL)            │
│       └───────────────────────────────┘          │
│                  (DB_HOST=mysql)                  │
└─────────────────────────────────────────────────┘
```

## Quick Start

### 🔧 Development Mode (recommended saat coding)

```bash
# Build & jalankan semua services
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build

# Lihat logs semua services
docker compose -f docker-compose.yml -f docker-compose.dev.yml logs -f

# Lihat log service tertentu
docker compose -f docker-compose.yml -f docker-compose.dev.yml logs -f fastapi
docker compose -f docker-compose.yml -f docker-compose.dev.yml logs -f laravel
docker compose -f docker-compose.yml -f docker-compose.dev.yml logs -f mysql

# Stop semua
docker compose -f docker-compose.yml -f docker-compose.dev.yml down
```

**Fitur Dev Mode:**

- ✅ FastAPI: auto-reload saat edit kode Python
- ✅ Laravel: source code di-mount (edit Blade/PHP langsung terlihat)
- ✅ Debug mode aktif
- ✅ Memory limit lebih besar untuk ML tasks

### 🚀 Production Mode

```bash
# Build & jalankan
docker compose up -d --build

# Lihat logs
docker compose logs -f

# Stop
docker compose down
```

## URLs

| Service       | URL                         | Keterangan            |
| ------------- | --------------------------- | --------------------- |
| Laravel       | http://localhost:8080       | Web Application       |
| FastAPI       | http://localhost:8000       | ML API                |
| FastAPI Docs  | http://localhost:8000/docs  | Swagger UI            |
| FastAPI ReDoc | http://localhost:8000/redoc | ReDoc                 |
| MySQL         | localhost:3306              | Database (via client) |

## Perintah Berguna

### Rebuild satu service saja

```bash
# Rebuild hanya FastAPI
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build fastapi

# Rebuild hanya Laravel
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build laravel
```

### Masuk ke container

```bash
# Masuk ke FastAPI container
docker exec -it skripsi-fastapi bash

# Masuk ke Laravel container
docker exec -it skripsi-laravel bash

# Masuk ke MySQL
docker exec -it skripsi-mysql mysql -u skripsi -pskripsi_password_ganti_ini skripsi_db
```

### Laravel Artisan di container

```bash
docker exec -it skripsi-laravel php artisan migrate
docker exec -it skripsi-laravel php artisan db:seed
docker exec -it skripsi-laravel php artisan tinker
```

### Cek status

```bash
# Lihat container yang berjalan
docker compose ps

# Lihat resource usage
docker stats --no-stream
```

## 🧹 Bersihkan Docker

```bash
# Stop & hapus containers (data tetap aman di volumes)
docker compose down

# Stop & hapus containers + volumes (⚠️ DATA HILANG)
docker compose down -v

# Hapus build cache
docker builder prune -f

# Hapus semua image yang tidak terpakai
docker image prune -a

# Nuclear option: hapus SEMUA (⚠️ BERBAHAYA)
docker system prune -a --volumes
```

## 🔧 Troubleshooting

### Port sudah dipakai

Edit `.env` dan ubah port:

```env
MYSQL_PORT=3307
FASTAPI_PORT=8001
LARAVEL_PORT=8081
```

### FastAPI timeout saat build

Koneksi internet lambat. Coba:

1. Pastikan koneksi internet stabil
2. Build ulang: `docker compose up -d --build fastapi`
3. Jika masih gagal, coba dengan VPN

### Laravel error "No application key"

```bash
docker exec -it skripsi-laravel php artisan key:generate --force
```

### MySQL connection refused

Tunggu MySQL selesai start (healthcheck 30 detik). Cek:

```bash
docker compose logs mysql
```

### Reset database

```bash
docker compose down
docker volume rm sistem_mysql-data
docker compose up -d
```

## 📁 Struktur Volume

| Volume          | Isi                           | Persistent |
| --------------- | ----------------------------- | ---------- |
| mysql-data      | Database MySQL                | ✅         |
| fastapi-data    | Data scraping & processed     | ✅         |
| fastapi-models  | Trained ML models             | ✅         |
| fastapi-logs    | FastAPI log files             | ✅         |
| laravel-storage | Laravel storage (logs, cache) | ✅         |
