# FoodStock AI — Frontend (Next.js) terhubung ke Backend Laravel + MySQL

Frontend Next.js 15 / React 19 / Tailwind v4 / TanStack Query. Semua data kini berasal dari REST API Laravel
(`/api/v1`) yang menyimpan ke MySQL. **Panduan lengkap: `PANDUAN-INTEGRASI.md`.**

```bash
cp .env.local.example .env.local   # LARAVEL_API_URL=http://127.0.0.1:8000/api/v1
npm install
npm run dev                        # http://localhost:3000
```

## Cara terhubung
* Browser memanggil `/api/v1/*` (proxy `app/api/v1/[...path]/route.ts`), bukan Laravel langsung → tanpa CORS.
* Login: `app/api/auth/session` meneruskan email/password ke Laravel, lalu menyimpan token Sanctum di cookie
  httpOnly (`foodstock_token`) dan cookie sesi bertanda tangan (`foodstock_session`) yang dibaca `middleware.ts`.
* `src/services/api.ts` = satu-satunya lapisan data: memetakan JSON Laravel ⇄ tipe frontend (`name` ⇄ `nama_wilayah`, dst.).
* Yang tetap di sisi browser: tema (localStorage), preferensi notifikasi di Profil, ekspor CSV/Excel/PDF, cuaca Open-Meteo
  (`app/api/external/weather`), parsing berkas impor CSV/Excel (library `xlsx`) sebelum dikirim ke backend.
* Halaman Lupa/Reset Password masih tampilan saja (backend belum menyediakan token reset).

Tanpa akun demo: login dengan email di `FOODSTOCK_ADMIN_EMAILS` (backend) otomatis menjadi Admin Utama, yang lalu memberi role Dinas/BULOG/Distributor ke pengguna terdaftar.

Catatan tema terang & master data (dari revisi sebelumnya): `CATATAN_MASTER_DATA_DAN_TEMA.md` (bagian yang menyebut
localStorage/simulasi kini digantikan backend).
