# Deploy FoodStock AI ke Railway (3 layanan dari 1 repo GitHub)

Prasyarat: proyek sudah ada di GitHub (file .env TIDAK ikut). Login ke railway.com dengan akun GitHub.

## 0. Buat project
New Project -> Deploy from GitHub repo -> pilih repo foodstock-ai.
(Railway akan membuat 1 layanan otomatis; kita jadikan itu BACKEND. Kalau gagal build, abaikan dulu.)

## 1. Database MySQL
+ New -> Database -> Add MySQL. Biarkan namanya "MySQL" (kalau diganti, sesuaikan referensi di bawah).

## 2. Layanan BACKEND (Laravel)
Settings:
- Root Directory: kosongkan (akar repo)
- Variables (tab Variables -> Raw Editor, tempel, lalu ganti nilai yang perlu):
```
RAILWAY_DOCKERFILE_PATH=docker/backend/Dockerfile
PORT=8000
APP_ENV=production
APP_DEBUG=false
APP_LOCALE=id
APP_KEY=base64:ISI_DENGAN_HASIL_PEMBUATAN_KUNCI
APP_URL=https://DOMAIN-BACKEND-NANTI
DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
MAIL_MAILER=log
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
FOODSTOCK_ADMIN_EMAILS=emailanda@gmail.com
```
- Buat APP_KEY di laptop: docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32));"
- Settings -> Networking -> Generate Domain (port 8000). Salin domainnya, mis. https://backend-production-xxxx.up.railway.app
- Isi APP_URL dengan domain itu. Deploy. Cek: DOMAIN-BACKEND/api/v1/health -> "status":"ok"

## 3. Layanan FRONTEND (Next.js)
+ New -> GitHub Repo -> repo yang sama.
Settings:
- Root Directory: foodstock-frontend
- Variables:
```
PORT=3000
SESSION_SECRET=string-acak-panjang-buatan-anda
LARAVEL_API_URL=https://DOMAIN-BACKEND/api/v1
COOKIE_SECURE=true
```
- Settings -> Networking -> Generate Domain (port 3000). Itulah LINK aplikasi Anda.

## 4. Pertama kali
Buka link frontend /login, LANGSUNG login dengan email admin + password pilihan Anda (membuat Admin Utama).
Lalu menu Model AI -> Jalankan Ulang Model.

## Masalah umum
- Build backend gagal/lama: normal, composer mengunduh Laravel (5-10 menit). Lihat tab Deployments -> Logs.
- Login "gagal/Failed": cek LARAVEL_API_URL (https, ada /api/v1, tanpa slash di akhir) lalu redeploy frontend.
- Perubahan SESSION_SECRET butuh redeploy frontend (nilainya dipakai saat build).

## Akun per peran @foodstock.com

Tambahkan di service **backend** (tab Variables):

```
FOODSTOCK_ADMIN_EMAILS=admin@foodstock.com      # (boleh ditambah email lain, pisahkan koma)
FOODSTOCK_ROLE_PASSWORD=<password-pilihanmu-min-8>
```

Setelah redeploy: `dinas@`, `bulog@`, `distributor@foodstock.com` otomatis dibuat (password = `FOODSTOCK_ROLE_PASSWORD`).
`admin@foodstock.com` dibuat saat login pertama (password yang kamu ketik saat itu = password admin). Jangan commit password ke GitHub.

## Akun belum diverifikasi & reset pengguna

- Akun yang baru registrasi (status pending) boleh login, tetapi hanya melihat halaman `/menunggu` (alert + notifikasi). Setelah Admin memverifikasi dan menetapkan peran di Kelola Pengguna, halaman itu otomatis membuka dashboard sesuai peran (dicek tiap 15 detik).
- Migrasi `2026_10_15_000001_reset_users` menghapus semua pengguna lama satu kali saat deploy. Setelah itu: `admin@foodstock.com` dibuat saat login pertama, dan `dinas@`, `bulog@`, `distributor@foodstock.com` dibuat otomatis dari `FOODSTOCK_ROLE_PASSWORD`.
