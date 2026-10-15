"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import {
  AlertTriangle, Bell, CheckCircle2, Clock, Landmark, LogOut, Loader2, MailCheck, RefreshCcw, ShieldCheck, Truck, UserCog, Warehouse,
} from "lucide-react";
import { Logo } from "@/components/ui/Logo";
import { ThemeToggle } from "@/components/ui/ThemeToggle";
import { Button } from "@/components/ui/Basics";
import { useLogout } from "@/hooks/useAuth";
import { useSessionStore } from "@/store/useSessionStore";
import { useUIStore } from "@/store/useUIStore";
import { fetchMe } from "@/services/api";
import { RoleSchema } from "@/types/domain";

const ROLE_HOME: Record<string, string> = { admin: "/admin", dinas: "/dinas", bulog: "/bulog", distributor: "/distributor" };

const NOTIFICATIONS = [
  { id: 1, title: "Registrasi diterima", body: "Data akun Anda sudah tersimpan di sistem.", tone: "ok" },
  { id: 2, title: "Menunggu verifikasi", body: "Administrator Utama akan memeriksa akun Anda dan menetapkan peran (Dinas, Bulog, atau Distributor).", tone: "wait" },
  { id: 3, title: "Akses dashboard dikunci sementara", body: "Dashboard terbuka otomatis setelah akun diverifikasi. Halaman ini memeriksa status setiap 15 detik.", tone: "info" },
] as const;

const ROLES = [
  { icon: Landmark, title: "Dinas Ketahanan Pangan", desc: "Monitoring wilayah, forecasting, evaluasi wilayah, dan laporan kebijakan." },
  { icon: Warehouse, title: "Bulog / Badan Pangan", desc: "Cadangan gudang, notifikasi shortage, dan realokasi logistik." },
  { icon: Truck, title: "Distributor", desc: "Input distribusi, riwayat pengiriman, dan tren pasar." },
  { icon: UserCog, title: "Administrator Utama", desc: "Memverifikasi akun dan menetapkan peran pengguna." },
];

export default function MenungguPage() {
  const router = useRouter();
  const logout = useLogout();
  const session = useSessionStore((s) => s.session);
  const setSession = useSessionStore((s) => s.setSession);
  const showToast = useUIStore((s) => s.showToast);
  const [checking, setChecking] = useState(false);
  const [lastCheck, setLastCheck] = useState<Date | null>(null);
  const [showNotif, setShowNotif] = useState(false);
  const [unread, setUnread] = useState<number>(NOTIFICATIONS.length);
  const done = useRef(false);

  const me = useQuery({ queryKey: ["me-pending"], queryFn: fetchMe, retry: false });

  // Menyegarkan sesi dari server; bila Admin sudah memverifikasi, langsung masuk ke dashboard peran.
  const check = useCallback(async () => {
    setChecking(true);
    try {
      const res = await fetch("/api/auth/session", { cache: "no-store" });
      if (res.status === 401) {
        setSession(null);
        window.location.href = "/login?redirected=1";
        return;
      }
      const json = await res.json().catch(() => null);
      const role = RoleSchema.safeParse(json?.session?.role);
      if (role.success) {
        setSession({ ...json.session, role: role.data });
        if (json.session.status === "aktif" && !done.current) {
          done.current = true;
          showToast("Akun Anda sudah diverifikasi. Selamat datang!");
          router.replace(ROLE_HOME[role.data] ?? "/");
        }
      }
    } finally {
      setChecking(false);
      setLastCheck(new Date());
    }
  }, [router, setSession, showToast]);

  useEffect(() => {
    showToast("Akun Anda belum diverifikasi Administrator.");
  }, [showToast]);

  useEffect(() => {
    check();
    const t = setInterval(check, 15000);
    return () => clearInterval(t);
  }, [check]);

  const nama = me.data?.nama ?? session?.nama ?? "Pengguna";

  return (
    <div className="app-shell-bg dash-shell min-h-screen text-slate-100">
      <header className="sticky top-0 z-20 flex items-center justify-between border-b border-emerald-900/30 bg-[#051410]/80 px-5 py-4 backdrop-blur-xl md:px-8">
        <Logo />
        <div className="flex items-center gap-2 sm:gap-3">
          <ThemeToggle />
          <div className="relative">
            <button
              type="button"
              onClick={() => { setShowNotif((v) => !v); setUnread(0); }}
              aria-label="Buka notifikasi"
              className="relative rounded-xl border border-emerald-900/50 bg-white/[0.03] p-2 text-slate-300 transition hover:border-emerald-500/50 hover:text-emerald-300"
            >
              <Bell className="h-5 w-5" />
              {unread > 0 && <span className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-bold text-amber-950">{unread}</span>}
            </button>
            {showNotif && (
              <div className="absolute right-0 z-50 mt-2 w-[min(20rem,calc(100vw-2.5rem))] rounded-2xl border border-emerald-900/50 bg-[#081c16] p-3 shadow-2xl shadow-black/60">
                <p className="mb-2 font-semibold text-white">Notifikasi</p>
                {NOTIFICATIONS.map((n) => (
                  <div key={n.id} className="border-t border-emerald-900/30 py-2 text-xs text-slate-400">
                    <p className="font-medium text-slate-100">{n.title}</p>
                    <p>{n.body}</p>
                  </div>
                ))}
              </div>
            )}
          </div>
          <button type="button" onClick={logout} className="flex items-center gap-2 rounded-xl border border-rose-500/30 px-3 py-2 text-xs font-semibold text-rose-300 transition hover:bg-rose-500/10">
            <LogOut className="h-4 w-4" /> <span className="hidden sm:inline">Keluar</span>
          </button>
        </div>
      </header>

      <main className="mx-auto max-w-4xl space-y-6 px-5 py-8 md:px-8">
        <div role="alert" className="flex items-start gap-3 rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 text-amber-200">
          <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-400" />
          <div>
            <p className="font-bold">Akun Anda belum diverifikasi</p>
            <p className="mt-1 text-sm text-amber-100/80">
              Halo, {nama}. Akun sudah terdaftar, tetapi dashboard belum bisa dibuka sampai Administrator Utama memverifikasi akun dan menetapkan peran Anda.
            </p>
          </div>
        </div>

        <section className="rounded-2xl border border-emerald-900/40 bg-[#081c16]/80 p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h1 className="text-xl font-extrabold text-white">Menunggu Verifikasi Administrator</h1>
              <p className="mt-1 text-sm text-slate-400">Status diperiksa otomatis. Anda tidak perlu masuk ulang.</p>
            </div>
            <Button type="button" onClick={check} disabled={checking} className="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
              {checking ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCcw className="h-4 w-4" />} Periksa status
            </Button>
          </div>

          <ol className="mt-6 grid gap-3 sm:grid-cols-3">
            <li className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
              <CheckCircle2 className="h-5 w-5 text-emerald-400" />
              <p className="mt-2 text-sm font-semibold text-white">1. Registrasi</p>
              <p className="text-xs text-slate-400">Selesai</p>
            </li>
            <li className="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4">
              <Clock className="h-5 w-5 text-amber-400" />
              <p className="mt-2 text-sm font-semibold text-white">2. Verifikasi Admin</p>
              <p className="text-xs text-amber-200">Sedang menunggu</p>
            </li>
            <li className="rounded-xl border border-emerald-900/40 bg-white/[0.02] p-4 opacity-70">
              <ShieldCheck className="h-5 w-5 text-slate-500" />
              <p className="mt-2 text-sm font-semibold text-white">3. Akses dashboard</p>
              <p className="text-xs text-slate-400">Terbuka setelah diverifikasi</p>
            </li>
          </ol>

          <dl className="mt-6 grid gap-3 text-sm sm:grid-cols-2">
            <div className="rounded-xl bg-[#051410] p-3"><dt className="text-xs uppercase tracking-wider text-slate-500">Nama</dt><dd className="mt-1 text-white">{nama}</dd></div>
            <div className="rounded-xl bg-[#051410] p-3"><dt className="text-xs uppercase tracking-wider text-slate-500">Email</dt><dd className="mt-1 flex items-center gap-1.5 break-all text-white"><MailCheck className="h-4 w-4 shrink-0 text-emerald-400" />{me.data?.email ?? "-"}</dd></div>
            <div className="rounded-xl bg-[#051410] p-3"><dt className="text-xs uppercase tracking-wider text-slate-500">Instansi</dt><dd className="mt-1 text-white">{me.data?.instansi || session?.instansi || "-"}</dd></div>
            <div className="rounded-xl bg-[#051410] p-3"><dt className="text-xs uppercase tracking-wider text-slate-500">Status</dt><dd className="mt-1"><span className="rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-semibold text-amber-300">Menunggu verifikasi</span></dd></div>
          </dl>
          {lastCheck && <p className="mt-4 text-xs text-slate-500">Terakhir diperiksa: {lastCheck.toLocaleTimeString("id-ID")}</p>}
        </section>

        <section>
          <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-400">Tentang FoodStock AI</h2>
          <p className="mb-4 text-sm leading-relaxed text-slate-300">
            Platform pemantauan stok pangan nasional berbasis AI: proyeksi time-series, peringatan dini kekurangan stok, dan rekomendasi realokasi logistik antar-wilayah.
            Setelah diverifikasi, Anda akan masuk ke dashboard sesuai peran yang ditetapkan Administrator Utama.
          </p>
          <div className="grid gap-3 sm:grid-cols-2">
            {ROLES.map(({ icon: Icon, title, desc }) => (
              <div key={title} className="rounded-xl border border-emerald-900/40 bg-[#081c16]/80 p-4">
                <Icon className="h-5 w-5 text-emerald-400" />
                <p className="mt-2 text-sm font-semibold text-white">{title}</p>
                <p className="mt-1 text-xs text-slate-400">{desc}</p>
              </div>
            ))}
          </div>
        </section>
      </main>
    </div>
  );
}
