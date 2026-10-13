"use client";

import { useMemo } from "react";
import {
  Activity, AlertTriangle, CheckCircle2, Clock3, Database, Mail,
  RefreshCw, Server, ShieldCheck, Boxes, Sparkles, ArrowUpRight,
} from "lucide-react";
import { TopBar } from "@/components/layout/TopBar";
import { useHealthDetailsQuery } from "@/hooks/useQueries";

type HealthState = "normal" | "perhatian" | "belum-diperiksa";

function StatusPill({ state, children }: { state: HealthState; children: React.ReactNode }) {
  const styles: Record<HealthState, string> = {
    normal: "bg-emerald-500/20 text-emerald-300 border border-emerald-500/30",
    perhatian: "bg-amber-500/20 text-amber-300 border border-amber-500/30",
    "belum-diperiksa": "bg-slate-800 text-slate-400 border border-slate-700",
  };
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${styles[state]}`}>
      <span className={`h-1.5 w-1.5 rounded-full ${state === "normal" ? "bg-emerald-400" : state === "perhatian" ? "bg-amber-400" : "bg-slate-500"}`} />
      {children}
    </span>
  );
}

function MetricCard({ icon: Icon, label, value, note, tone = "green" }: {
  icon: typeof Activity; label: string; value: string; note: string; tone?: "green" | "amber" | "blue" | "violet";
}) {
  const tones = {
    green: "bg-emerald-500/20 text-emerald-400 border border-emerald-500/30",
    amber: "bg-amber-500/20 text-amber-400 border border-amber-500/30",
    blue: "bg-teal-500/20 text-teal-400 border border-teal-500/30",
    violet: "bg-emerald-600/20 text-emerald-300 border border-emerald-500/30",
  };
  return (
    <article className="agri-card p-5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-slate-400">{label}</p>
          <p className="mt-3 text-2xl font-bold tracking-tight text-white">{value}</p>
        </div>
        <span className={`rounded-xl p-3 ${tones[tone]}`}><Icon className="h-5 w-5" /></span>
      </div>
      <p className="mt-3 text-xs leading-5 text-slate-400">{note}</p>
    </article>
  );
}

export default function HealthPage() {
  // Data nyata dari backend Laravel: GET /api/v1/health/details (Admin).
  const health = useHealthDetailsQuery();
  const hydrated = !health.isLoading;
  const last = health.data?.last_forecast_at ?? null;
  const stok = { length: health.data?.stok_count ?? 0 };
  const storageState: HealthState = health.isError ? "perhatian" : health.data ? "normal" : "belum-diperiksa";
  const checkedAt = health.dataUpdatedAt ? new Date(health.dataUpdatedAt) : null;

  const emailStats = useMemo(() => {
    const e = health.data?.email;
    return { total: e?.total ?? 0, sent: e?.sent ?? 0, failed: e?.failed ?? 0, waiting: 0 };
  }, [health.data]);

  const runCheck = () => { void health.refetch(); };

  const overallState: HealthState = !hydrated || storageState === "belum-diperiksa"
    ? "belum-diperiksa"
    : storageState === "perhatian" || emailStats.failed > 0
      ? "perhatian"
      : "normal";

  return (
    <div className="min-h-full">
      <TopBar title="Kesehatan Sistem" subtitle="Pantau kesiapan data dan status operasional FoodStock AI" />
      <main className="relative overflow-hidden px-4 py-5 md:px-8 md:py-8 space-y-6">
        <section className="relative overflow-hidden rounded-[28px] hero-panel p-6 shadow-xl border border-emerald-900/40 md:p-8">
          <div className="relative flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
            <div className="max-w-2xl">
              <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3.5 py-1.5 text-xs font-semibold tracking-wide text-emerald-300">
                <Sparkles className="h-3.5 w-3.5" /> FOODSTOCK AI · SYSTEM MONITOR
              </div>
              <h2 className="text-3xl font-bold leading-tight tracking-tight md:text-4xl">Semua data, tetap <span className="text-emerald-400">terpantau.</span></h2>
              <p className="mt-3 max-w-xl text-sm leading-6 text-slate-300 md:text-base">Ringkasan pemeriksaan langsung ke backend Laravel dan database MySQL: forecasting, data, dan pengiriman email.</p>
            </div>
            <div className="flex flex-col items-start gap-3 md:items-end">
              <StatusPill state={overallState}>{overallState === "normal" ? "Backend & database normal" : overallState === "perhatian" ? "Perlu perhatian" : "Menyiapkan pemeriksaan"}</StatusPill>
              <button onClick={runCheck} className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md transition">
                <RefreshCw className="h-4 w-4" /> Periksa ulang
              </button>
            </div>
          </div>
        </section>

        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <MetricCard icon={Activity} label="Forecast terakhir" value={last ? new Date(last).toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" }) : "Belum ada"} note={last ? `Dijalankan ${new Date(last).toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" })}` : "Jalankan forecasting untuk prediksi terbaru."} />
          <MetricCard icon={Boxes} label="Data stok tersimpan" value={hydrated ? stok.length.toLocaleString("id-ID") : "…"} note="Jumlah baris pada tabel stok di database MySQL." tone="blue" />
          <MetricCard icon={Mail} label="Email terkirim" value={`${emailStats.sent} / ${emailStats.total}`} note={`${emailStats.waiting} menunggu · ${emailStats.failed} gagal.`} tone={emailStats.failed ? "amber" : "green"} />
          <MetricCard icon={Database} label="Database MySQL" value={storageState === "normal" ? "Aktif" : storageState === "perhatian" ? "Tidak terjangkau" : "Memeriksa"} note="Backend menjalankan query ke database saat pemeriksaan." tone={storageState === "perhatian" ? "amber" : "violet"} />
        </section>

        <section className="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
          <div className="agri-card p-6">
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className="text-xs font-bold uppercase tracking-[.16em] text-emerald-400">Diagnostics</p>
                <h3 className="mt-1 text-xl font-bold text-white">Komponen yang dipantau</h3>
              </div>
              <Activity className="h-6 w-6 text-emerald-400" />
            </div>
            <div className="mt-5 divide-y divide-emerald-900/30">
              <div className="flex items-center gap-3 py-4">
                <span className="rounded-xl bg-emerald-500/20 p-2.5 text-emerald-400 border border-emerald-500/30"><ShieldCheck className="h-5 w-5" /></span>
                <div className="min-w-0 flex-1"><p className="font-semibold text-white">Status aplikasi</p><p className="mt-0.5 text-xs text-slate-400">Halaman kesehatan dapat dimuat dan interaksi aktif.</p></div>
                <StatusPill state="normal">Aktif</StatusPill>
              </div>
              <div className="flex items-center gap-3 py-4">
                <span className="rounded-xl bg-teal-500/20 p-2.5 text-teal-400 border border-teal-500/30"><Database className="h-5 w-5" /></span>
                <div className="min-w-0 flex-1"><p className="font-semibold text-white">Data aplikasi</p><p className="mt-0.5 text-xs text-slate-400">{hydrated ? (health.isError ? "Backend tidak dapat dihubungi." : "Data berhasil dibaca dari database.") : "Menunggu respons backend."}</p></div>
                <StatusPill state={!hydrated ? "belum-diperiksa" : health.isError ? "perhatian" : "normal"}>{!hydrated ? "Memuat" : health.isError ? "Gagal" : "Siap"}</StatusPill>
              </div>
              <div className="flex items-center gap-3 py-4">
                <span className="rounded-xl bg-emerald-600/20 p-2.5 text-emerald-300 border border-emerald-500/30"><Server className="h-5 w-5" /></span>
                <div className="min-w-0 flex-1"><p className="font-semibold text-white">Forecasting</p><p className="mt-0.5 text-xs text-slate-400">{last ? "Riwayat eksekusi terakhir tersedia." : "Belum ada riwayat eksekusi."}</p></div>
                <StatusPill state={last ? "normal" : "perhatian"}>{last ? "Ada riwayat" : "Belum berjalan"}</StatusPill>
              </div>
              <div className="flex items-center gap-3 py-4">
                <span className="rounded-xl bg-amber-500/20 p-2.5 text-amber-400 border border-amber-500/30"><Database className="h-5 w-5" /></span>
                <div className="min-w-0 flex-1"><p className="font-semibold text-white">Backup database</p><p className="mt-0.5 text-xs text-slate-400">Jadwalkan mysqldump berkala di server (tidak dipantau aplikasi).</p></div>
                <StatusPill state="perhatian">Belum terhubung</StatusPill>
              </div>
            </div>
          </div>

          <div className="agri-card flex flex-col justify-between p-6">
            <div>
              <p className="text-xs font-bold uppercase tracking-[.16em] text-emerald-400">Aktivitas pemeriksaan</p>
              <h3 className="mt-1 text-xl font-bold text-white">Pemeriksaan terbaru</h3>
              <p className="mt-2 text-sm leading-6 text-slate-300">Pemeriksaan ini memanggil backend dan mengecek koneksi database.</p>
            </div>
            <div className="space-y-4 my-6">
              <div className="flex gap-3">
                <CheckCircle2 className={`mt-0.5 h-5 w-5 shrink-0 ${hydrated ? "text-emerald-400" : "text-slate-600"}`} />
                <div><p className="text-sm font-semibold text-white">Backend Laravel</p><p className="text-xs text-slate-400">{health.data ? "Backend merespons." : health.isError ? "Backend tidak merespons." : "Menunggu respons."}</p></div>
              </div>
              <div className="flex gap-3">
                {storageState === "perhatian" ? <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-400" /> : <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" />}
                <div><p className="text-sm font-semibold text-white">Koneksi database</p><p className="text-xs text-slate-400">{storageState === "normal" ? "Query ke MySQL berhasil." : storageState === "perhatian" ? "Pemeriksaan gagal." : "Belum selesai diperiksa."}</p></div>
              </div>
              <div className="flex gap-3">
                <Clock3 className="mt-0.5 h-5 w-5 shrink-0 text-slate-400" />
                <div><p className="text-sm font-semibold text-white">Waktu pemeriksaan</p><p className="text-xs text-slate-400">{checkedAt ? checkedAt.toLocaleString("id-ID") : "Belum tersedia"}</p></div>
              </div>
            </div>
            <a href="/admin/audit-log" className="inline-flex items-center gap-2 text-sm font-semibold text-emerald-400 hover:text-emerald-300">Lihat audit log <ArrowUpRight className="h-4 w-4" /></a>
          </div>
        </section>
      </main>
    </div>
  );
}