// Konfigurasi koneksi server-side ke backend Laravel (dipakai route handler Next.js saja).
export const LARAVEL_API_URL = (process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api/v1").replace(/\/+$/, "");

export const BACKEND_UNREACHABLE = {
  code: "BACKEND_UNREACHABLE",
  message: "Server backend tidak dapat dihubungi. Pastikan Laravel berjalan (php artisan serve) dan LARAVEL_API_URL benar.",
  errors: null,
};
