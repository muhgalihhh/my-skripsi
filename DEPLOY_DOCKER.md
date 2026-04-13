# Docker Deployment Guide

Panduan ini untuk menjalankan stack production:
- Laravel (web)
- FastAPI (ML service)
- MySQL

Best practice untuk Ubuntu server:
- Jalankan aplikasi via Docker Compose.
- Jalankan reverse proxy Nginx di host Ubuntu (di luar Docker) untuk HTTPS/domain.

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

## 2) Jalankan Deploy Aplikasi

```bash
docker compose --env-file .env.server.local -f docker-compose.yml up -d --build
```

## 3) Setup Nginx Reverse Proxy di Ubuntu Host

Install Nginx + Certbot:

```bash
sudo apt update
sudo apt install -y nginx certbot python3-certbot-nginx
```

Buat server block (contoh file ada di `ops/nginx/analitikskripsi.online.conf.example`):

```bash
sudo cp ops/nginx/analitikskripsi.online.conf.example /etc/nginx/sites-available/analitikskripsi.online
sudo ln -sf /etc/nginx/sites-available/analitikskripsi.online /etc/nginx/sites-enabled/analitikskripsi.online
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

Catatan:
- File contoh adalah bootstrap HTTP-only agar `nginx -t` lolos sebelum sertifikat tersedia.
- Jangan taruh `include /etc/letsencrypt/options-ssl-nginx.conf` sebelum Certbot dijalankan.

Aktifkan SSL otomatis:

```bash
sudo certbot --nginx -d analitikskripsi.online -d www.analitikskripsi.online
```

Jika DNS `www` belum ada, gunakan:

```bash
sudo certbot --nginx -d analitikskripsi.online
```

## 4) Verifikasi

```bash
docker compose -f docker-compose.yml ps
docker compose -f docker-compose.yml logs -f mysql fastapi laravel
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
- Laravel bisa tetap dibind ke `127.0.0.1:8080` dan hanya diakses Nginx host.
- FastAPI sebaiknya tetap internal (`127.0.0.1:8000`) kecuali memang butuh endpoint publik.
