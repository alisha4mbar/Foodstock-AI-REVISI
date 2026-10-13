# Catatan Perubahan: Master Data Nasional & Perbaikan Tema Terang

## 1. Master data bawaan (src/lib/mock-data.ts)

**38 provinsi** (pasca pemekaran Papua 2022): Aceh, Sumatera Utara, Sumatera Barat, Riau, Jambi, Sumatera Selatan,
Bengkulu, Lampung, Kep. Bangka Belitung, Kep. Riau, DKI Jakarta, Jawa Barat, Jawa Tengah, DI Yogyakarta, Jawa Timur,
Banten, Bali, NTB, NTT, Kalimantan Barat/Tengah/Selatan/Timur/Utara, Sulawesi Utara/Tengah/Selatan/Tenggara/Barat,
Gorontalo, Maluku, Maluku Utara, Papua, Papua Barat, Papua Barat Daya, Papua Tengah, Papua Selatan, Papua Pegunungan.
Koordinat = titik pusat provinsi (perkiraan, untuk peta). ID lama (`w-jatim`, `w-dki`, dst.) dipertahankan.

**25 komoditas** yang paling banyak dibeli rumah tangga (mengacu pada komoditas Panel Harga Pangan Bapanas / bapokting):
- Pokok: Beras Medium, Beras Premium, Jagung Pipilan Kering, Kedelai Biji Kering, Tepung Terigu, Gula Pasir,
  Minyak Goreng Curah, Minyak Goreng Kemasan, Garam Beryodium, Mi Instan
- Hortikultura: Cabai Rawit Merah, Cabai Merah Keriting, Bawang Merah, Bawang Putih, Tomat, Kentang, Kubis
- Protein: Daging Ayam Ras, Telur Ayam Ras, Daging Sapi, Ikan Kembung, Ikan Tongkol, Tahu, Tempe
- Lainnya: Susu Kental Manis

> `ambang_batas` dan stok/konsumsi awal adalah **angka simulasi** (ditandai `simulasi`), bukan data resmi.
> Ganti dengan data BPS/Bapanas lewat fitur impor. Daftar bisa diubah di menu Admin > Master Data.

Total kombinasi: 38 x 25 = **950** hasil forecast.

### Migrasi otomatis untuk pengguna lama
Penyimpanan browser naik ke versi 1. Pengguna yang sudah punya data (12 wilayah x 7 komoditas) otomatis
mendapat wilayah/komoditas baru + data simulasi **hanya untuk kombinasi baru**. Input manual, impor, suntingan
nama, dan data yang sudah dihapus tidak disentuh. Forecast dihitung ulang sekali setelah migrasi.

### Penyesuaian performa
- Seri harian forecast tidak lagi disimpan di localStorage (950 x 30 titik terlalu besar); dihitung ulang saat dibuka.
  Ukuran penyimpanan sekarang sekitar 1 MB.
- `runForecastAll` memakai indeks O(n) (sebelumnya menyaring seluruh entri untuk tiap kombinasi).

## 2. Perbaikan tema terang

Penyebab elemen yang "masih gelap" ternyata ada beberapa, bukan satu:

1. **Tema terpental ke gelap karena error hidrasi React #418** pada `/admin`, `/bulog`, `/dinas/laporan`, `/bulog/laporan`.
   Sumbernya `Math.random()` pada data grafik dan `new Date()` pada "Dicetak pada" saat render. Setelah error,
   React me-render ulang `<html data-theme="dark">` dan menimpa pilihan terang. Perbaikan: derau deterministik
   (`src/lib/pseudo-noise.ts`), waktu cetak hanya di klien (`PrintedAt`), dan atribut `data-theme` dihapus dari JSX `<html>`
   sehingga hanya diatur oleh skrip inline + tombol tema.
2. **Login, Register, Lupa/Reset Password** tidak memakai `.dash-shell` dan tidak punya tombol tema, sehingga selalu gelap.
   Sekarang ikut tema dan punya tombol tema. Panel kiri login/register kini mengikuti tema (`hero-panel`).
3. **Sidebar**: latar dan teksnya sekarang sama-sama mengikuti tema (sebelumnya teks dikecualikan dari override).
4. **Celah kelas Tailwind**: override terang ditulis manual per kelas sehingga variasi seperti `text-amber-400/90`,
   `bg-[#061a14]`, `bg-emerald-950/30` terlewat. Skrip baru `scripts/gen-light-theme.mjs` memindai semua kelas di kode dan
   menghasilkan aturan terang yang belum ada (blok `GENERATED` di `app/globals.css`).
   **Setelah menambah kelas warna gelap baru, jalankan:** `npm run theme:gen`
5. **Tombol** `bg-emerald-600 text-white` tertutup gradien bawaan varian primary (teks putih di atas hijau muda,
   kontras rendah). Sekarang gradien dihapus bila pemanggil memberi warna latar sendiri.
6. Halaman `/forecast/accuracy`: TopBar berada di luar `.dash-shell`; dipindah ke dalam.
7. Kontras teks hijau/abu kecil dinaikkan (mis. `text-emerald-400` jadi #047857 pada tema terang).

Hasil pemeriksaan otomatis (mode terang, 32 halaman, 4 peran + halaman publik): 0 elemen berlatar gelap / berkontras < 3:1,
0 error hidrasi, tema tetap terang setelah halaman dimuat. Mode gelap diperiksa visual dan tidak berubah.

Teks statis yang diperbarui: "34 provinsi" (login) dan "12 provinsi / 7 komoditas" (landing) menjadi 38 / 25.

## 3. Catatan yang perlu Anda ketahui
- Hasil simulasi forecast bawaan sangat condong ke *surplus* (stok awal jauh di atas kebutuhan 7 hari), sehingga hampir
  semua dari 950 kombinasi memunculkan peringatan. Ini perilaku simulator yang sudah ada, tidak diubah di sini.
  Setelah data BPS/Bapanas diimpor, hasilnya akan mengikuti data tersebut.
- Teks "99.8% Akurasi Forecast" di halaman login adalah angka statis dekoratif, bukan hasil pengukuran.
