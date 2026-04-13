# Docker Deployment Guide

Panduan ini untuk menjalankan stack production:
- Reverse Proxy (Nginx + Let's Encrypt)
- Laravel (web)
- FastAPI (ML service)
- MySQL

## 1) Siapkan Environment File

Di root project:

```bash
cp .env.server .env.server.local
```

Lalu edit `.env.server.local` sesuai server Anda:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://domain-anda`
- password MySQL wajib diganti
- `PUBLIC_DOMAIN=domain-anda`
- `LETSENCRYPT_EMAIL=email-anda`

Siapkan env aplikasi server:

```bash
cp laravel-app/.env.server laravel-app/.env.server.local
cp fastapi/.env.server fastapi/.env.server.local
```

Di `.env.server.local`, set juga:
- `LARAVEL_ENV_FILE=./laravel-app/.env.server.local`
- `FASTAPI_ENV_FILE=./fastapi/.env.server.local`

Di `laravel-app/.env.server.local`, pastikan minimal:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` sama dengan `APP_URL` di root `.env.server.local`
- `DB_HOST=mysql`
- `DB_PORT=3306`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` sesuai root `.env.server.local`
- `FASTAPI_API_KEY` wajib sama dengan `FASTAPI_API_KEY` di `fastapi/.env.server.local`
- `GOOGLE_REDIRECT_URI` sesuai URL publik Anda, contoh `https://domain-anda/auth/google/callback`

Di `fastapi/.env.server.local`, pastikan:
- `FASTAPI_API_KEY` terisi strong random key
- `FASTAPI_REQUIRE_API_KEY=true`

Catatan DNS dan firewall:
- `A record` domain harus mengarah ke IP server.
- Port `80` dan `443` harus terbuka dari internet.

## 2) Jalankan Deploy

```bash
docker compose --env-file .env.server.local -f docker-compose.yml up -d --build
```

## 3) Verifikasi

```bash
docker compose -f docker-compose.yml ps
docker compose -f docker-compose.yml logs -f reverse-proxy acme-companion mysql fastapi laravel
```

Health check yang aktif:
- MySQL ping
- FastAPI: `/api/v1/health`
- Laravel: `/login`

Verifikasi nilai runtime Laravel:

```bash
docker exec skripsi-laravel printenv | grep -E "APP_URL|GOOGLE_REDIRECT_URI"
```

## 4) Update Aplikasi

Jika ada perubahan code:

```bash
git pull
docker compose --env-file .env.server.local -f docker-compose.yml up -d --build
```

Opsional clear cache Laravel setelah update:

```bash
docker exec skripsi-laravel php artisan optimize:clear
docker exec skripsi-laravel php artisan view:cache
```

## 5) Catatan Keamanan

- Secara default, port MySQL dan FastAPI dibind ke `127.0.0.1` (tidak terbuka publik).
- Akses publik masuk lewat reverse proxy (`PROXY_HTTP_PORT` / `PROXY_HTTPS_PORT`).
- Laravel bisa tetap dipublish ke `127.0.0.1:8080` untuk menerima proxy internal.
