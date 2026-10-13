import { apiGet, apiSend } from "@/lib/http";
import type {
  AuditLogEntry,
  DistribusiEntry,
  ForecastSeriesPoint,
  HasilForecast,
  Komoditas,
  KonsumsiEntry,
  ModelConfig,
  Notifikasi,
  Rekomendasi,
  Role,
  StatusProyeksi,
  StokEntry,
  User,
  Wilayah,
} from "@/types/domain";

/**
 * Lapisan integrasi ke REST API Laravel (/api/v1, lihat dokumen Desain Arsitektur Backend Bagian 9).
 * Kontrak tipe untuk hooks/halaman TIDAK berubah: fungsi di sini memetakan JSON backend
 * (mis. `name`, `quantity`) ke tipe frontend (mis. `nama_wilayah`, `jumlah`).
 * Id di backend berupa angka (bigint); di frontend dipakai sebagai string.
 */

type List<T> = { data: T[]; meta?: { page: number; per_page: number; total: number } };
type One<T> = { data: T };
const ALL = "?per_page=5000";
const sid = (v: number | string) => String(v);
const nid = (v: string) => Number(v);

// ---- Wilayah & Komoditas (FR-08) ------------------------------------------
interface WilayahApi { id: number; name: string; province: string; latitude: number; longitude: number }
const toWilayah = (w: WilayahApi): Wilayah => ({
  id: sid(w.id), nama_wilayah: w.name, provinsi: w.province, latitude: Number(w.latitude), longitude: Number(w.longitude),
});
const fromWilayah = (w: Partial<Omit<Wilayah, "id">>) => ({
  ...(w.nama_wilayah !== undefined && { name: w.nama_wilayah }),
  ...(w.provinsi !== undefined && { province: w.provinsi }),
  ...(w.latitude !== undefined && { latitude: w.latitude }),
  ...(w.longitude !== undefined && { longitude: w.longitude }),
});

export async function fetchWilayah(): Promise<Wilayah[]> {
  return (await apiGet<List<WilayahApi>>(`/wilayah${ALL}`)).data.map(toWilayah);
}
export async function createWilayah(input: Omit<Wilayah, "id">) {
  return apiSend("POST", "/wilayah", fromWilayah(input));
}
export async function editWilayah(id: string, patch: Partial<Omit<Wilayah, "id">>) {
  // PUT di backend memvalidasi semua field; lengkapi dari data terkini agar patch parsial tetap valid.
  const current = (await apiGet<One<WilayahApi>>(`/wilayah/${id}`)).data;
  return apiSend("PUT", `/wilayah/${id}`, { name: current.name, province: current.province, latitude: current.latitude, longitude: current.longitude, ...fromWilayah(patch) });
}
export async function removeWilayah(id: string) {
  return apiSend("DELETE", `/wilayah/${id}`);
}

interface KomoditasApi { id: number; name: string; unit: Komoditas["satuan"]; category: Komoditas["kategori"]; safe_stock: number }
const toKomoditas = (k: KomoditasApi): Komoditas => ({
  id: sid(k.id), nama_komoditas: k.name, kategori: k.category, satuan: k.unit, ambang_batas: Number(k.safe_stock),
});
const fromKomoditas = (k: Partial<Omit<Komoditas, "id">>) => ({
  ...(k.nama_komoditas !== undefined && { name: k.nama_komoditas }),
  ...(k.satuan !== undefined && { unit: k.satuan }),
  ...(k.kategori !== undefined && { category: k.kategori }),
  ...(k.ambang_batas !== undefined && { safe_stock: k.ambang_batas }),
});

export async function fetchKomoditas(): Promise<Komoditas[]> {
  return (await apiGet<List<KomoditasApi>>(`/komoditas${ALL}`)).data.map(toKomoditas);
}
export async function createKomoditas(input: Omit<Komoditas, "id">) {
  return apiSend("POST", "/komoditas", fromKomoditas(input));
}
export async function editKomoditas(id: string, patch: Partial<Omit<Komoditas, "id">>) {
  const current = (await apiGet<One<KomoditasApi>>(`/komoditas/${id}`)).data;
  return apiSend("PUT", `/komoditas/${id}`, { name: current.name, unit: current.unit, category: current.category, safe_stock: current.safe_stock, ...fromKomoditas(patch) });
}
export async function removeKomoditas(id: string) {
  return apiSend("DELETE", `/komoditas/${id}`);
}

// ---- Forecast & Dashboard (FR-03 / FR-04) -----------------------------------
interface ForecastApi {
  id: number; wilayah_id: number; komoditas_id: number; periode: string; predicted_value: number; stock_value: number;
  status: StatusProyeksi; days_to_event: number | null; mape: number | null; created_at: string | null;
}
const NO_EVENT_DAYS = 999; // frontend menampilkan "-" bila > 30 hari

export async function fetchForecastResults(): Promise<HasilForecast[]> {
  const rows = (await apiGet<List<ForecastApi>>("/forecast")).data;
  return rows.map((f) => ({
    id: sid(f.id),
    id_wilayah: sid(f.wilayah_id),
    id_komoditas: sid(f.komoditas_id),
    nilai_proyeksi_ketersediaan: Number(f.stock_value),
    nilai_proyeksi_kebutuhan: Number(f.predicted_value),
    status: f.status,
    periode: f.periode,
    hari_menuju_kejadian: f.days_to_event ?? NO_EVENT_DAYS,
    mape: Number(f.mape ?? 0),
    createdAt: f.created_at ?? new Date(0).toISOString(),
  }));
}

export async function fetchForecastSeries(idWilayah: string, idKomoditas: string): Promise<ForecastSeriesPoint[]> {
  const rows = (
    await apiGet<List<{ periode: string; predicted_value: number; stock_value: number; status: StatusProyeksi }>>(
      `/forecast/series?wilayah_id=${idWilayah}&komoditas_id=${idKomoditas}`,
    )
  ).data;
  return rows.map((r) => ({
    tanggal: r.periode,
    proyeksi_ketersediaan: Number(r.stock_value),
    proyeksi_kebutuhan: Number(r.predicted_value),
    status: r.status,
  }));
}

export function describeForecastRun(r: ForecastRunSummary): string {
  const skip = r.skipped_total > 0 ? `, ${r.skipped_total} kombinasi dilewati (data < 30 bulan / belum lengkap)` : "";
  return `Forecast selesai: ${r.processed.length} kombinasi diproses${skip}. Peringatan baru: ${r.warnings_created}.`;
}

export interface ForecastRunSummary {
  processed: { wilayah: string; komoditas: string; mape: number }[];
  skipped: { wilayah: string; komoditas: string; reason: string; detail: string }[];
  skipped_total: number;
  skipped_by_reason: Record<string, number>;
  warnings_created: number;
  emails: { sent: number; failed: number };
}
// Parameter `aktor` dipertahankan agar pemanggil lama tetap valid; pelaku dicatat server dari token.
export async function runForecast(_aktor?: { nama: string; role: Role }): Promise<ForecastRunSummary> {
  return (await apiSend<One<ForecastRunSummary>>("POST", "/forecast/run")).data;
}

// ---- Rekomendasi realokasi (FR-06) ----------------------------------------
interface RekomendasiApi {
  id: number; forecast_id: number; komoditas_id: number; source_wilayah: { name: string }; target_wilayah: { name: string };
  quantity: number; distance: number; status: Rekomendasi["status"]; created_at: string;
}
export async function fetchRekomendasi(): Promise<Rekomendasi[]> {
  return (await apiGet<List<RekomendasiApi>>("/recommendations")).data.map((r) => ({
    id: sid(r.id), id_forecast: sid(r.forecast_id), id_komoditas: sid(r.komoditas_id),
    wilayah_asal: r.source_wilayah.name, wilayah_tujuan: r.target_wilayah.name,
    jumlah_saran: Number(r.quantity), jarak_km: Math.round(Number(r.distance)), status: r.status, createdAt: r.created_at,
  }));
}
export async function updateRekomendasiStatus(id: string, status: Rekomendasi["status"]) {
  return apiSend("PATCH", `/recommendations/${id}`, { status });
}

// ---- Notifikasi / peringatan (FR-05 / FR-10) --------------------------------
interface WarningApi {
  id: number; forecast_id: number; wilayah_id: number; komoditas_id: number; status: StatusProyeksi; message: string;
  recommendation_text: string; channels: Notifikasi["kanal"]; delivery_status: Notifikasi["status_kirim"];
  followed_up: boolean; created_at: string | null; followup_note: string | null; followup_result: string | null; followed_up_by: string | null;
}
export async function fetchNotifikasi(): Promise<Notifikasi[]> {
  return (await apiGet<List<WarningApi>>("/warnings")).data.map((n) => ({
    id: sid(n.id), id_forecast: sid(n.forecast_id), id_wilayah: sid(n.wilayah_id), id_komoditas: sid(n.komoditas_id),
    penerima_role: ["dinas", "bulog", "distributor"] as Role[], kanal: n.channels, pesan: n.message,
    rekomendasi_tindakan: n.recommendation_text, status: n.status, status_kirim: n.delivery_status,
    tindak_lanjut: n.followed_up ? "sudah_ditindaklanjuti" : "menunggu_tindak_lanjut", waktu: n.created_at ?? new Date(0).toISOString(),
    catatan_tindak_lanjut: n.followup_note ?? undefined, ditangani_oleh: n.followed_up_by ?? undefined, hasil_tindak_lanjut: n.followup_result ?? undefined,
  }));
}
export async function tindakLanjutNotifikasi(id: string) {
  return apiSend("POST", `/warnings/${id}/follow-up`);
}
export async function saveNotificationFollowup(id: string, catatan: string, hasil: string) {
  return apiSend("POST", `/warnings/${id}/follow-up`, { note: catatan, result: hasil });
}
export async function retryEmailNotification(id: string) {
  return apiSend<One<{ sent: number; failed: number }>>("POST", `/warnings/${id}/retry-email`);
}

// ---- Data historis (FR-02) -------------------------------------------------
interface StokApi { id: number; wilayah_id: number; komoditas_id: number; quantity: number; recorded_at: string; source: StokEntry["sumber"]; recorded_by: string | null }
export async function fetchStokEntries(): Promise<StokEntry[]> {
  return (await apiGet<List<StokApi>>(`/stok${ALL}`)).data.map((s) => ({
    id: sid(s.id), id_wilayah: sid(s.wilayah_id), id_komoditas: sid(s.komoditas_id), jumlah: Number(s.quantity),
    tanggal: s.recorded_at, sumber: s.source, dicatatOleh: s.recorded_by ?? "-",
  }));
}
export async function submitStokEntry(input: Omit<StokEntry, "id">) {
  // `sumber` & pencatat ditentukan server (role Bulog otomatis = gudang_bulog).
  return apiSend("POST", "/stok", { wilayah_id: nid(input.id_wilayah), komoditas_id: nid(input.id_komoditas), quantity: input.jumlah, recorded_at: input.tanggal });
}
export async function editStokEntry(id: string, jumlah: number) {
  return apiSend("PUT", `/stok/${id}`, { quantity: jumlah });
}
export async function removeStokEntry(id: string) {
  return apiSend("DELETE", `/stok/${id}`);
}
export async function editKonsumsiEntry(id: string, jumlah: number) {
  return apiSend("PUT", `/konsumsi/${id}`, { quantity: jumlah });
}
export async function removeKonsumsiEntry(id: string) {
  return apiSend("DELETE", `/konsumsi/${id}`);
}

interface KonsumsiApi { id: number; wilayah_id: number; komoditas_id: number; quantity: number; recorded_at: string; source: "manual" | "bps_bapanas" | "simulasi" }
export async function fetchKonsumsiEntries(): Promise<KonsumsiEntry[]> {
  return (await apiGet<List<KonsumsiApi>>(`/konsumsi${ALL}`)).data.map((k) => ({
    id: sid(k.id), id_wilayah: sid(k.wilayah_id), id_komoditas: sid(k.komoditas_id), jumlah: Number(k.quantity),
    periode: k.recorded_at.slice(0, 7), sumber: k.source,
  }));
}

export interface ImportReport { tersimpan: number; diperbarui: number; gagal: Array<{ baris: number; alasan: string }> }
type ImportRow = { jenis: "stok" | "konsumsi"; id_wilayah: string; id_komoditas: string; jumlah: number; tanggal: string; sumber: "bps_bapanas" };
// Dikirim bertahap (2000 baris/permintaan) agar tidak terkena batas waktu PHP pada impor nasional (puluhan ribu baris).
export async function importHistoricalRows(rows: ImportRow[], _aktor?: string): Promise<ImportReport> {
  const BATCH = 2000;
  const total: ImportReport = { tersimpan: 0, diperbarui: 0, gagal: [] };
  for (let i = 0; i < rows.length; i += BATCH) {
    const chunk = rows.slice(i, i + BATCH).map((r) => ({
      jenis: r.jenis, wilayah: r.id_wilayah, komoditas: r.id_komoditas, jumlah: String(r.jumlah), tanggal: r.tanggal,
    }));
    const res = (await apiSend<One<{ inserted: number; updated: number; errors: { row: number; reason: string }[] }>>("POST", "/import/rows", { rows: chunk, source: "bps_bapanas" })).data;
    total.tersimpan += res.inserted;
    total.diperbarui += res.updated;
    // backend menomori baris relatif terhadap paket; tambahkan offset agar cocok dengan baris di berkas
    total.gagal.push(...res.errors.map((e) => ({ baris: e.row + i, alasan: e.reason })));
  }
  return total;
}

export async function submitKonsumsiEntry(input: Omit<KonsumsiEntry, "id">) {
  // `periode` berformat YYYY-MM; backend menyimpan satu nilai per bulan (tanggal 1).
  return apiSend("POST", "/konsumsi", { wilayah_id: nid(input.id_wilayah), komoditas_id: nid(input.id_komoditas), quantity: input.jumlah, recorded_at: `${input.periode}-01` });
}

interface DistribusiApi {
  id: number; origin_wilayah_id: number; destination_wilayah_id: number; komoditas_id: number; quantity: number;
  distributed_at: string; status: DistribusiEntry["status"]; recorded_by: string | null;
}
export async function fetchDistribusiEntries(): Promise<DistribusiEntry[]> {
  return (await apiGet<List<DistribusiApi>>(`/distribusi${ALL}`)).data.map((d) => ({
    id: sid(d.id), id_wilayah_asal: sid(d.origin_wilayah_id), id_wilayah_tujuan: sid(d.destination_wilayah_id),
    id_komoditas: sid(d.komoditas_id), jumlah: Number(d.quantity), tanggal: d.distributed_at, status: d.status, dicatatOleh: d.recorded_by ?? "-",
  }));
}
export async function submitDistribusiEntry(input: Omit<DistribusiEntry, "id">) {
  return apiSend("POST", "/distribusi", {
    origin_wilayah_id: nid(input.id_wilayah_asal), destination_wilayah_id: nid(input.id_wilayah_tujuan),
    komoditas_id: nid(input.id_komoditas), quantity: input.jumlah, distributed_at: input.tanggal, status: input.status,
  });
}
export async function updateDistribusiStatus(id: string, status: DistribusiEntry["status"]) {
  return apiSend("PATCH", `/distribusi/${id}`, { status });
}

// ---- Pengguna (Admin, FR-01) ---------------------------------------------
interface UserApi { id: number; name: string; email: string; role: Role; instansi: string | null; status: User["status"]; created_at: string }
export async function fetchUsers(): Promise<User[]> {
  return (await apiGet<List<UserApi>>(`/users${ALL}`)).data.map((u) => ({
    id: sid(u.id), nama: u.name, email: u.email, role: u.role, instansi: u.instansi ?? "", status: u.status, percobaanGagal: 0, createdAt: u.created_at,
  }));
}
export async function verifyUserAccount(id: string, approve: boolean) {
  return apiSend("POST", `/users/${id}/verify`, { approve });
}
export async function changeUserRole(id: string, role: Role) {
  return apiSend("PUT", `/users/${id}`, { role });
}
export async function toggleLockUserAccount(id: string) {
  return apiSend("POST", `/users/${id}/toggle-lock`);
}
export async function deleteUserAccount(id: string) {
  return apiSend("DELETE", `/users/${id}`);
}

// ---- Profil sendiri (FR-01) ----------------------------------------------
export async function fetchMe() {
  const u = (await apiGet<One<UserApi>>("/auth/me")).data;
  return { id: sid(u.id), nama: u.name, email: u.email, role: u.role, instansi: u.instansi ?? "" };
}
export async function updateProfile(input: { nama: string; instansi: string }) {
  return apiSend("PUT", "/auth/profile", { name: input.nama, instansi: input.instansi });
}
export async function changePassword(input: { current_password: string; new_password: string }) {
  return apiSend("PUT", "/auth/password", { ...input, new_password_confirmation: input.new_password });
}
export async function registerAccount(input: { nama: string; email: string; instansi: string; password: string }) {
  return apiSend("POST", "/auth/register", {
    name: input.nama, email: input.email, instansi: input.instansi, password: input.password, password_confirmation: input.password,
  });
}

// ---- Audit log (NFR-10) -----------------------------------------------
interface AuditApi { id: number; actor_name: string; actor_role: Role | null; action: string; entity: string | null; entity_id: string | null; created_at: string }
export async function fetchAuditLog(): Promise<AuditLogEntry[]> {
  return (await apiGet<List<AuditApi>>(`/audit-logs${ALL}`)).data.map((a) => ({
    id: sid(a.id), aktor: a.actor_name, aktorRole: a.actor_role ?? "admin", aksi: a.action,
    target: [a.entity, a.entity_id].filter(Boolean).join(" #"), waktu: a.created_at,
  }));
}

// ---- Konfigurasi ambang early warning (Admin) ---------------------------
interface ModelApi { shortage_margin_pct: number; surplus_margin_pct: number; horizon_months: number; mape_average: number | null; last_run_at: string | null }
const toModelConfig = (m: ModelApi): ModelConfig => ({
  threshold_shortage_persen: Number(m.shortage_margin_pct),
  threshold_surplus_persen: Number(m.surplus_margin_pct),
  horizon_hari: m.horizon_months * 30,
  mape_current: Number(m.mape_average ?? 0),
  last_trained: m.last_run_at ?? "",
  status_model: "aktif",
});
export async function fetchModelConfig(): Promise<ModelConfig> {
  return toModelConfig((await apiGet<One<ModelApi>>("/settings/model")).data);
}
export async function saveModelConfig(patch: Partial<ModelConfig>) {
  return apiSend("PUT", "/settings/model", {
    ...(patch.threshold_shortage_persen !== undefined && { shortage_margin_pct: patch.threshold_shortage_persen }),
    ...(patch.threshold_surplus_persen !== undefined && { surplus_margin_pct: patch.threshold_surplus_persen }),
  });
}

// ---- Integrasi cuaca (FR-09 versi frontend): proxy Route Handler Open-Meteo ------
export interface WeatherReading {
  source: string;
  status: "live" | "fallback";
  suhu_c: number;
  curah_hujan_mm: number;
  kelembapan_persen: number;
  kategori_musim: string;
  waktu: string;
}
export async function fetchWeather(lat: number, lon: number): Promise<WeatherReading> {
  const res = await fetch(`/api/external/weather?lat=${lat}&lon=${lon}`);
  if (!res.ok) throw new Error("Gagal memuat data cuaca eksternal.");
  return res.json();
}

// ---- Kesehatan sistem (Admin) ----------------------------------------------
export interface HealthDetails {
  database: string; last_forecast_at: string | null; stok_count: number; konsumsi_count: number;
  email: { sent: number; failed: number; total: number }; users_pending: number; server_time: string;
}
export async function fetchHealthDetails(): Promise<HealthDetails> {
  return (await apiGet<One<HealthDetails>>("/health/details")).data;
}
