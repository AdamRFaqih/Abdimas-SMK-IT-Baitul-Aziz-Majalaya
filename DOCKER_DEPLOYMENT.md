# 🚀 Panduan Deployment Docker: SMK IT Baitul Aziz

Dokumentasi ini menjelaskan arsitektur dan langkah-langkah menjalankan sistem secara penuh dalam satu kesatuan Docker stack (Aplikasi Laravel + Nginx Web Server + Database MySQL 8.0).

---

## 🏗️ Arsitektur Docker Stack

Deployment ini menyatukan seluruh stack dalam satu perintah orkestrasi `docker compose`:

```
+--------------------------------------------------------------+
|                     Host Machine / Server                    |
|                                                              |
|   Port 8000 (HTTP)                     Port 3306 (MySQL)    |
|         │                                      │             |
|         ▼                                      ▼             |
|   ┌─────────────┐                        ┌─────────────┐     |
|   │ abdimas_app │                        │ abdimas_db  │     |
|   │ ─────────── │                        │ ─────────── │     |
|   │ • Nginx     │                        │ • MySQL 8.0 │     |
|   │ • PHP 8.2   │                        │ • Healthcheck     |
|   │ • FPM       │◄─── [abdimas-net] ────►│ • utf8mb4   │     |
|   │ • Supervisord                        │             │     |
|   │ • Vite (JS) │                        └──────┬──────┘     |
|   └──────┬──────┘                               │            |
|          │                                      ▼            |
|          ▼                                [db_data volume]   |
|   [app_storage volume]                                       |
+--------------------------------------------------------------+
```

### Komponen Utama:
1. **Container `abdimas_app`**:
   - **Frontend**: Multi-stage build mengompilasi Vite SCSS/JS (`npm run build`).
   - **Backend**: PHP 8.2-FPM dengan ekstensi lengkap (`pdo_mysql`, `gd`, `zip`, `intl`, `mbstring`, `opcache`, `bcmath`, `pcntl`).
   - **Web Server**: Nginx yang dikonfigurasi untuk Laravel dengan Gzip, browser caching, dan upload limit 64MB.
   - **Process Manager**: Supervisord yang menjalankan Nginx dan PHP-FPM secara bersamaan.
   - **Entrypoint Otomatis**: Memeriksa koneksi database, membuat storage symlink, menjalankan migrasi, dan mengoptimalkan cache aplikasi.

2. **Container `abdimas_db`**:
   - MySQL 8.0 dengan volume persisten `db_data` (data tidak hilang saat container dimatikan).
   - Pengaturan karakter `utf8mb4` dan `max_allowed_packet=64M`.
   - Healthcheck bawaan yang memastikan database siap sebelum Laravel berjalan.

---

## ⚡ Quick Start (Deploy Satu Perintah)

Pastikan Docker Desktop atau Docker Engine sudah terpasang dan berjalan di server/komputer Anda.

### 1. Jalankan Skrip Deploy
Cukup jalankan perintah berikut di root folder project:

```bash
./deploy.sh
```

Skrip ini akan secara otomatis:
1. Menyiapkan file `.env` dari template jika belum ada.
2. Membangun image Docker (Vite build + Composer install).
3. Menjalankan container `abdimas_db` dan `abdimas_app`.
4. Menunggu database MySQL siap.
5. Menghasilkan `APP_KEY` jika belum dibuat.
6. Menjalankan migrasi database (`php artisan migrate --force`).
7. Membuat symlink penyimpanan dokumen (`php artisan storage:link`).
8. Mengoptimalkan cache konfigurasi dan rute (`php artisan optimize`).

Setelah selesai, aplikasi dapat diakses di:
👉 **http://localhost:8000**

---

## 🛠️ Perintah Manajemen (`./deploy.sh`)

Skrip `deploy.sh` dilengkapi utilitas lengkap untuk mempermudah operasional harian:

| Perintah | Fungsi |
|---|---|
| `./deploy.sh` | Build dan deploy seluruh stack aplikasi + database |
| `./deploy.sh --seed` | Deploy sekaligus menjalankan seeder database lengkap |
| `./deploy.sh seed` | Menjalankan database seeders pada container yang sudah aktif |
| `./deploy.sh migrate` | Menjalankan migrasi database terbaru |
| `./deploy.sh logs` | Memantau log aplikasi secara real-time |
| `./deploy.sh logs db` | Memantau log database MySQL secara real-time |
| `./deploy.sh shell` | Masuk ke terminal bash di dalam container Laravel |
| `./deploy.sh shell db` | Masuk ke terminal container MySQL |
| `./deploy.sh status` | Menampilkan status container (`docker compose ps`) |
| `./deploy.sh restart` | Merestart seluruh container |
| `./deploy.sh stop` | Menghentikan container |
| `./deploy.sh backup` | Backup database MySQL otomatis ke folder `backups/` |
| `./deploy.sh restore <file.sql>` | Restore database dari file SQL |

---

## ⚙️ Kustomisasi Konfigurasi (`.env`)

Anda dapat menyesuaikan konfigurasi berikut di file `.env`:

```env
# Port aplikasi di host
APP_PORT=8000

# Port MySQL yang diekspos ke host (opsional)
DB_EXTERNAL_PORT=3306

# Kredensial Database
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=abdimas
DB_USERNAME=abdimas_user
DB_PASSWORD=abdimas_secret
DB_ROOT_PASSWORD=root_secret

# Auto migrasi saat container mulai
AUTO_MIGRATE=true
```

---

## 💾 Backup & Restore Database

### Membuat Backup:
```bash
./deploy.sh backup
```
File SQL akan tersimpan dengan timestamp di folder `backups/backup_abdimas_YYYYMMDD_HHMMSS.sql`.

### Restore Backup:
```bash
./deploy.sh restore backups/backup_abdimas_20261020_170000.sql
```

---

## 🔐 Akun Default Setelah Seeding

Jika Anda menjalankan `./deploy.sh seed`, akun administrator dibuat secara otomatis:
- **Username**: `admin`
- **Password**: `admin12345`
- **Role**: `Super Admin`

---

## ☁️ Menghubungkan ke Cloudflare Tunnel (cloudflared)

### Skenario 1: Cloudflared sudah terpasang di Server Host (Systemd / CLI)
Jika `cloudflared` berjalan langsung di OS host Anda:
1. Buka **Cloudflare Zero Trust Dashboard** > **Networks** > **Tunnels** > Pilih Tunnel Anda.
2. Tambahkan **Public Hostname**:
   - **Subdomain / Domain**: contoh `akademik.smkitbaitulaziz.sch.id`
   - **Type**: `HTTP`
   - **URL**: `localhost:8000` (atau port yang diisi pada `APP_PORT` di `.env`).
3. Di file `.env`, ubah:
   ```env
   APP_URL=https://akademik.smkitbaitulaziz.sch.id
   ```
4. Restart cache aplikasi:
   ```bash
   ./deploy.sh restart
   ```

---

### Skenario 2: Menjalankan Cloudflared di dalam Docker Compose
Jika Anda ingin cloudflared berjalan otomatis bersama aplikasi di Docker:
1. Tambahkan service berikut di `docker-compose.yml`:
   ```yaml
     tunnel:
       image: cloudflare/cloudflared:latest
       container_name: abdimas_tunnel
       restart: unless-stopped
       command: tunnel --no-autoupdate run
       environment:
         - TUNNEL_TOKEN=${CLOUDFLARE_TUNNEL_TOKEN}
       networks:
         - abdimas-net
   ```
2. Isi token tunnel di `.env`:
   ```env
   CLOUDFLARE_TUNNEL_TOKEN=eyJh...
   ```
3. Di **Cloudflare Dashboard**, arahkan **Public Hostname** ke:
   - **Type**: `HTTP`
   - **URL**: `app:80` (menggunakan nama service Docker internal tanpa perlu mengekspos port ke publik).
4. Jalankan ulang stack:
   ```bash
   ./deploy.sh
   ```

