"use client";

import { ThemeToggle } from "@/components/ui/ThemeToggle";
import { useEffect, useState, type FormEvent } from "react";
import Link from "next/link";
import { AlertCircle, ArrowRight, CheckCircle2, Lock, Mail, Sparkles } from "lucide-react";
import { Button, Input, PasswordInput } from "@/components/ui/Basics";
import { AuthHero } from "@/components/auth/AuthHero";
import { useLoginMutation } from "@/hooks/useAuth";
import { LoginSchema } from "@/types/domain";

export default function LoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const loginMutation = useLoginMutation();

  useEffect(() => {
    const q = new URLSearchParams(window.location.search);
    if (q.get("registered")) setNotice("Pendaftaran berhasil. Akun Anda menunggu verifikasi dan penetapan peran oleh Administrator Utama.");
    else if (q.get("redirected")) setNotice("Silakan masuk terlebih dahulu untuk mengakses dashboard.");
  }, []);

  // Login asli: kredensial diverifikasi terhadap data pengguna, lalu server
  // menerbitkan cookie sesi httpOnly yang diperiksa middleware.ts.
  const handleLogin = (e: FormEvent) => {
    e.preventDefault();
    setFieldError(null);
    const parsed = LoginSchema.safeParse({ email, password });
    if (!parsed.success) {
      setFieldError(parsed.error.issues[0]?.message ?? "Data login tidak valid.");
      return;
    }
    loginMutation.mutate(parsed.data);
  };

  return (
    <div className="app-shell-bg dash-shell relative flex min-h-screen w-full flex-col text-slate-100 lg:grid lg:grid-cols-2">
      <div className="absolute right-4 top-4 z-30"><ThemeToggle /></div>

      <AuthHero
        image="https://images.unsplash.com/photo-1586771107445-d3ca888129ff?q=80&w=1200&auto=format&fit=crop"
        badge={<><Sparkles className="h-3.5 w-3.5" /> SMART NATIONAL FOOD SECURITY</>}
        title={<>Modern Solutions for <span className="bg-gradient-to-r from-emerald-400 via-teal-300 to-emerald-200 bg-clip-text text-transparent">National Food Resilience.</span></>}
        description="Platform pemantauan stok pangan nasional berbasis kecerdasan buatan, proyeksi time-series, mitigasi dini shortage, dan koordinasi realokasi logistik antar-wilayah."
        footerNote="Secure Auth & HMAC Log"
      >
        <div className="mt-6 hidden grid-cols-3 gap-4 border-t border-emerald-900/50 pt-6 sm:grid">
          {[["99.8%", "Akurasi Forecast"], ["38", "Provinsi Terpantau"], ["Real-time", "Early Warning"]].map(([v, l]) => (
            <div key={l} className="rounded-xl border border-emerald-900/40 bg-[#081c16]/80 p-4 text-center">
              <p className="text-xl font-extrabold text-emerald-400 lg:text-2xl">{v}</p>
              <p className="mt-1 text-[11px] text-slate-400">{l}</p>
            </div>
          ))}
        </div>
      </AuthHero>

      <div className="relative flex flex-1 flex-col items-center justify-center bg-[#051410] p-5 sm:p-12 lg:p-16">
        <div className="w-full max-w-md">
          <div className="rounded-2xl border border-emerald-900/40 bg-[#081c16]/80 p-6 backdrop-blur-xl sm:p-10">
            <div className="mb-6 sm:mb-8">
              <h2 className="text-2xl font-bold tracking-tight text-white">Masuk Portal Sistem</h2>
              <p className="mt-1.5 text-xs text-slate-400">Masukkan kredensial akun instansi Anda untuk mengakses layanan.</p>
            </div>

            {notice && (
              <p className="mb-4 flex items-start gap-1.5 rounded-lg border border-emerald-500/30 bg-emerald-950/30 px-3 py-2 text-xs text-emerald-300" role="status">
                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" /> {notice}
              </p>
            )}

            <form onSubmit={handleLogin} className="space-y-4">
              <div>
                <label htmlFor="login-email" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-400">Email Instansi</label>
                <div className="relative">
                  <Mail className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-500" />
                  <Input id="login-email" type="email" required autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="nama@foodstock.com" className="pl-10 text-base sm:text-sm" />
                </div>
              </div>

              <div>
                <label htmlFor="login-password" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-400">Password</label>
                <div className="relative">
                  <Lock className="absolute left-3.5 top-3.5 z-10 h-4 w-4 text-slate-500" />
                  <PasswordInput id="login-password" required autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="••••••••" className="pl-10 text-base sm:text-sm" />
                </div>
                <div className="mt-2 text-right">
                  <Link href="/auth/forgot-password" className="text-xs text-emerald-400 transition hover:text-emerald-300">Lupa password?</Link>
                </div>
              </div>

              {(fieldError || loginMutation.isError) && (
                <p className="flex items-center gap-1.5 rounded-lg border border-rose-500/30 bg-rose-950/30 px-3 py-2 text-xs text-rose-300" role="alert">
                  <AlertCircle className="h-4 w-4 shrink-0" /> {fieldError ?? (loginMutation.error as Error).message}
                </p>
              )}

              <Button type="submit" loading={loginMutation.isPending} className="mt-2 flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 py-3 font-bold text-white shadow-lg shadow-emerald-950/50 transition duration-200 hover:from-emerald-500 hover:to-teal-500">
                Masuk Sistem <ArrowRight className="h-4 w-4" />
              </Button>
            </form>

            <div className="mt-8 text-center text-xs text-slate-400">
              Belum terdaftar? <Link href="/register" className="font-bold text-emerald-400 hover:underline">Registrasi Akun Baru</Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
