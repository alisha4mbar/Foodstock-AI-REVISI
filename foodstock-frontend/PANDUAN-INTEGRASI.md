# Panduan Integrasi FoodStock AI (Frontend Revisi) — Next.js + Laravel + MySQL (XAMPP)

Dua ZIP: **`foodstock-backend-overlay.zip`** (kode Laravel) dan **`foodstock-frontend-terintegrasi.zip`** (frontend revisi teman Anda,
sudah disambungkan). Jalur di bawah memakai `D:\` karena XAMPP Anda di drive D.

## 1. Arsitektur singkat
```
Browser ─► Next.js :3000 ──proxy /api/v1/*──► Laravel API :8000 ──► MySQL (XAMPP)
             cookie httpOnly: foodstock_token (Sanctum) + foodstock_session (role, untuk middleware.ts)
```
Frontend tetap Next.js (tampilan dari teman Anda tidak diubah). Hanya lapisan data yang diganti: localStorage → API Laravel.

## 2. Persiapan (sekali saja)
1. **PHP ≥ 8.2** (punya Anda 8.2.12 ✔), **Composer**, **Node.js LTS ≥ 20**.
2. **Ekstensi PHP** — buka `D:\xampp\php\php.ini` (XAMPP Control Panel → Apache → Config → PHP (php.ini)). Cari dan hapus `;` di depan:
   `extension=zip`, `extension=fileinfo`, `extension=mbstring`, `extension=openssl`, `extension=pdo_mysql`, `extension=curl`.
   *Kalau baris `extension=zip` tidak ada sama sekali:* ketik manual `extension=zip` di bagian daftar `extension=...` lalu simpan.
   Cek dengan `D:\xampp\php\php.exe -m`.
3. **PATH**: tambahkan `D:\xampp\php` ke Environment Variables → Path, buka CMD baru, `php -v`.
4. **Composer**: pasang `Composer-Setup.exe` (arahkan ke `D:\xampp\php\php.exe`). **Node.js**: pasang versi LTS.
5. XAMPP Control Panel → **Start MySQL**. phpMyAdmin → buat database **`foodstock_ai`** (`utf8mb4_general_ci`).

## 3. Backend (Laravel)
```bat
cd D:\FoodStockAI
composer create-project laravel/laravel foodstock-backend
cd foodstock-backend
php artisan install:api          (jika ditanya migrasi: jawab No)
```
Edit `.env` (tambahkan juga `FOODSTOCK_ADMIN_EMAILS=emailadmin@anda.com`):
```
APP_LOCALE=id
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=foodstock_ai
DB_USERNAME=root
DB_PASSWORD=
MAIL_MAILER=log
```
Salin **isi** `backend-overlay\` (app, bootstrap, config, database, resources, routes, tests, docs) ke `foodstock-backend\`, pilih *Replace*.
```bat
php artisan migrate --seed
php artisan serve                (biarkan terbuka)
```
Cek: `http://127.0.0.1:8000/api/v1/health` → `"status":"ok"`.

## 4. Frontend
```bat
cd D:\FoodStockAI\<folder-frontend>
copy .env.local.example .env.local
npm install
npm run dev
```
Buka `http://localhost:3000`. Tidak ada akun demo: login dengan email yang Anda isi di `FOODSTOCK_ADMIN_EMAILS` (`.env` backend) + password pilihan Anda (min. 8 karakter). Login pertama otomatis membuat **Admin Utama**; login berikutnya memakai password yang sama.

## 5. Uji integrasi
1. Login Admin → **Model AI** → *Jalankan Ulang Model*. Data demo hanya ada untuk **Jawa Timur & Jawa Tengah × Beras Medium & Cabai Rawit Merah**.
   Hasil yang diharapkan: Jawa Timur–Beras **shortage**, Jawa Tengah–Beras **surplus**, dua cabai **aman**, 1 rekomendasi (Jateng → Jatim, ±235 km).
   Toast menampilkan "N kombinasi dilewati": 946 dari 950 kombinasi memang belum punya data (normal, lihat bagian 6).
2. Pengguna mendaftar di `/register` (tanpa memilih role) → Admin Utama → Kelola Pengguna → pilih role (Dinas/BULOG/Distributor) → setujui → pengguna bisa login.
3. Master Data: tambah/ubah/hapus wilayah & komoditas. Input Stok & Konsumsi: tambah, edit, hapus.
4. Isi email peringatan: `foodstock-backend\storage\logs\laravel.log` (karena `MAIL_MAILER=log`).

## 6. Data: 38 provinsi × 25 komoditas
Master data (38 + 25) di-seed. Forecast butuh **≥ 30 bulan konsumsi berurutan tanpa bulan kosong** per kombinasi; kombinasi lain dilewati dan
dihitung di toast. Untuk data nyata: Input Stok → **Impor Data Awal** (CSV/Excel: `jenis,wilayah,komoditas,jumlah,tanggal`; konsumsi
bulanan `YYYY-MM`). Contoh berkas: `docs/template_impor_foodstock.csv`. Impor dikirim bertahap 2000 baris/permintaan; setelah impor, jalankan forecast.

## 7. Peta endpoint
| Frontend | Endpoint Laravel |
|---|---|
| Login/logout | `POST /auth/login`, `POST /auth/logout` (lewat `/api/auth/session`) |
| Registrasi, profil, ganti password | `POST /auth/register`, `GET /auth/me`, `PUT /auth/profile`, `PUT /auth/password` |
| Kelola pengguna | `/users`, `/users/{id}/verify`, `PUT /users/{id}`, `/users/{id}/toggle-lock`, `DELETE` |
| Master data | `/wilayah`, `/komoditas` (CRUD; baca semua role, tulis Admin) |
| Stok / konsumsi | `GET/POST /stok`, `PUT/DELETE /stok/{id}`; idem `/konsumsi` (tulis: Admin, Dinas; stok juga Bulog) |
| Impor | `POST /import/rows` (JSON dari browser), `POST /import/{stok,konsumsi}` (CSV mentah) |
| Distribusi | `GET/POST /distribusi`, `PATCH /distribusi/{id}` |
| Forecast & dashboard | `GET /forecast`, `GET /forecast/series`, `POST /forecast/run` (Admin, Dinas), `GET /forecast/accuracy` |
| Notifikasi | `GET /warnings`, `POST /warnings/{id}/follow-up` (catatan & hasil), `POST /warnings/{id}/retry-email` (Admin) |
| Rekomendasi | `GET /recommendations`, `PATCH /recommendations/{id}` |
| Audit, ambang, kesehatan | `GET /audit-logs`, `GET/PUT /settings/model`, `GET /health/details` (Admin), `GET /health` |

## 8. Perubahan terhadap frontend teman Anda
* Dihapus: `useDataStore`, `mock-data.ts`, `forecast.ts` (simulasi). Ditambah: `lib/http.ts`, `lib/laravel.ts`, proxy `app/api/v1`, `.env.local.example`.
* Diubah: `services/api.ts`, `hooks/*`, `app/api/auth/session`, `QueryProvider`, `domain.ts`, dan halaman yang membaca store langsung
  (TopBar, Profil, Kesehatan Sistem, Notifikasi Admin, Peta Wilayah, Forecast vs Aktual, Input Stok).
* **Grafik forecast kini bulanan (3 titik)**, bukan harian; horizon tetap 3 bulan (desain backend). Teks landing "30 hari" → "3 bulan".
* Password minimal 8 karakter; role tidak dipilih saat registrasi (diberikan Admin Utama); 5× salah login → akun terkunci.
* Bug kecil di Profil: teks `[cite: 11]` ikut tercetak di halaman — dihapus.

## 9. Perbedaan dengan dokumen desain (perlu disepakati tim)
1. **MySQL**, bukan PostgreSQL (dokumen menyebut `pg_dump`; ganti `mysqldump`).
2. `POST /forecast/run` boleh **Admin dan Dinas** (UI Dinas punya tombolnya). Ubah di `routes/api.php` bila hanya Admin.
3. Tambahan di luar dokumen: tabel `distribusi`, `settings`; kolom `users.instansi/status`, `hasil_forecast.stock_value/run_at/followup_*`;
   endpoint register, profile, retry-email, health/details, import/rows.
4. Status dihitung dari **stok terakhir** vs proyeksi kebutuhan bulan ke-1/2/3 (belum ada model pasokan masuk — celah dokumen).
5. Email dikirim **satu per kombinasi** (periode terdekat), bukan per bulan proyeksi. WhatsApp tidak ada (ditunda, sesuai dokumen).
6. Lupa/Reset Password: UI saja. Ekspor CSV/Excel/PDF dan cuaca masih di sisi Next.js.
7. Daftar stok/konsumsi diambil maksimal 5000 baris terbaru per permintaan; untuk data nasional penuh perlu pagination server.

## 10. Status pengujian (jujur)
Lolos: `php -l` semua file PHP; logika Holt-Winters/MAPE/status/Haversine dijalankan terpisah; frontend `tsc --noEmit` dan `next build`
(37 halaman); alur login → cookie → proxy → Bearer → proteksi role → logout diuji terhadap server Laravel **tiruan**.
**Belum**: kode Laravel + MySQL dijalankan sungguhan (lingkungan penyusun tidak bisa mengunduh paket Composer). Kirim pesan error bila
`php artisan migrate --seed` atau `php artisan test` gagal.

## 11. Pemecahan masalah
| Gejala | Solusi |
|---|---|
| "Server backend tidak dapat dihubungi" | `php artisan serve` belum jalan / `LARAVEL_API_URL` salah (restart `npm run dev` setelah ubah `.env.local`) |
| `could not find driver` | aktifkan `extension=pdo_mysql` |
| `Access denied` | `DB_*` di `.env` salah / MySQL belum Start |
| Tabel sudah ada | `php artisan migrate:fresh --seed` |
| Import timeout | pastikan `max_execution_time` php.ini ≥ 120 |
| Dashboard kosong | jalankan forecast; cek toast "dilewati" |
