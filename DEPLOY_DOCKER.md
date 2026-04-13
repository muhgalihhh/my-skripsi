# Docker Deployment Guide

Panduan ini untuk menjalankan stack production:
- Laravel (web)
- FastAPI (ML service)
- MySQL

## 1) Siapkan Environment File

Di root project:

```bash
cp .env.example .env
```

Lalu edit `.env` sesuai server Anda:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` ke domain/IP publik
- password MySQL wajib diganti

Siapkan env aplikasi jika belum ada:

```bash
cp laravel-app/.env.example laravel-app/.env
cp fastapi/.env.example fastapi/.env
```

Di `laravel-app/.env`, pastikan minimal:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` sama dengan `APP_URL` di root `.env`
- `DB_HOST=mysql`
- `DB_PORT=3306`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` sesuai root `.env`
- `FASTAPI_BASE_URL=http://fastapi:8000`
- `GOOGLE_REDIRECT_URI` sesuai URL publik Anda, contoh `https://domain-anda/auth/google/callback`

## 2) Jalankan Deploy

```bash
docker compose --env-file .env up -d --build
```

## 3) Verifikasi

```bash
docker compose ps
docker compose logs -f mysql fastapi laravel
```

Health check yang aktif:
- MySQL ping
- FastAPI: `/api/v1/health`
- Laravel: `/login`

## 4) Update Aplikasi

Jika ada perubahan code:

```bash
git pull
docker compose --env-file .env up -d --build
```

Opsional clear cache Laravel setelah update:

```bash
docker exec skripsi-laravel php artisan optimize:clear
docker exec skripsi-laravel php artisan view:cache
```

## 5) Catatan Keamanan

- Secara default, port MySQL dan FastAPI dibind ke `127.0.0.1` (tidak terbuka publik).
- Hanya Laravel yang dipublish ke luar (`LARAVEL_PORT`).
- Untuk HTTPS production, tempatkan reverse proxy (Nginx/Caddy/Traefik) di depan Laravel.
