#!/bin/sh
set -e
cd /var/www/html

# APP_KEY: pakai dari environment; bila kosong buat otomatis (disarankan diisi permanen di .env).
if [ -z "$APP_KEY" ]; then
  export APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
  echo "[foodstock] APP_KEY dibuat otomatis (isi APP_KEY di .env agar tetap sama setelah container dibuat ulang)."
fi

# Migrasi token Sanctum bawaan dibuat dengan nama bertanggal build (berubah tiap build) dan bentrok dengan
# tabel yang sudah ada. Buang; versi bernama tetap (2026_10_12_000002_*) yang dipakai.
rm -f database/migrations/*_create_personal_access_tokens_table.php

# Tunggu MySQL siap lalu migrasi + seed master data (idempoten).
i=0
until php artisan migrate --force --seed; do
  i=$((i+1))
  [ "$i" -ge 30 ] && echo "[foodstock] Migrasi gagal setelah 30 percobaan." && exit 1
  echo "[foodstock] Menunggu database... ($i)"; sleep 3
done

# Akun per peran @foodstock.com (opsional; password dari env FOODSTOCK_ROLE_PASSWORD).
php artisan foodstock:provision-accounts || true

php artisan config:cache >/dev/null 2>&1 || true
echo "[foodstock] Backend siap di :${PORT:-8000}"
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
