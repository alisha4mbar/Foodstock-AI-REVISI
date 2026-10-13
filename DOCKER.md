# Menjalankan FoodStock AI dengan Docker

Butuh: Docker Desktop (sudah running).

```bash
cp .env.example .env        # Windows CMD: copy .env.example .env
# edit .env -> isi FOODSTOCK_ADMIN_EMAILS dengan email Anda dan ganti SESSION_SECRET
docker compose up --build
```
Build pertama ±5–10 menit (mengunduh Laravel & paket npm). Setelah log menampilkan `Backend siap di :8000`:

- Aplikasi: http://localhost:3000
- Cek API:  http://localhost:8000/api/v1/health

**Login:** buka /login, masukkan email yang Anda isi di `FOODSTOCK_ADMIN_EMAILS` + password pilihan Anda (min. 8 karakter).
Login pertama otomatis membuat Admin Utama. Pengguna lain mendaftar di /register, lalu Admin memberi role & menyetujui di Kelola Pengguna.

Perintah berguna:
```bash
docker compose up -d --build    # jalan di latar belakang
docker compose logs -f backend  # lihat log backend
docker compose down             # berhenti (data tetap)
docker compose down -v          # berhenti + hapus database (mulai dari nol)
```
Catatan: bila Anda mengganti `FOODSTOCK_ADMIN_EMAILS` atau mengulang dari nol, pakai `docker compose down -v` lalu `up --build`.
