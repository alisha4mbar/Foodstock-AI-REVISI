"use client";

import { ThemeToggle } from "@/components/ui/ThemeToggle";
import { useState, type FormEvent } from "react";
import Link from "next/link";
import { AlertCircle, ArrowRight, Building, Lock, Mail, User, UserCheck } from "lucide-react";
import { Button, Input, PasswordInput } from "@/components/ui/Basics";
import { AuthHero } from "@/components/auth/AuthHero";
import { useRegisterMutation } from "@/hooks/useAuth";
import { RegisterSchema } from "@/types/domain";

const LABEL = "mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-400";

export default function RegisterPage() {
  const [form, setForm] = useState({ nama: "", email: "", instansi: "", password: "", konfirmasiPassword: "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const mutation = useRegisterMutation();
  const update = (patch: Partial<typeof form>) => setForm((f) => ({ ...f, ...patch }));

  // Terhubung ke API sungguhan (POST /auth/register). Tidak ada pilihan peran:
  // Administrator Utama yang menetapkan peran setelah akun didaftarkan.
  const handleRegister = (e: FormEvent) => {
    e.preventDefault();
    setErrors({});
    const parsed = RegisterSchema.safeParse(form);
    if (!parsed.success) {
      const flat = parsed.error.flatten().fieldErrors;
      const mapped: Record<string, string> = {};
      for (const [k, v] of Object.entries(flat)) if (v?.[0]) mapped[k] = v[0];
      setErrors(mapped);
      return;
    }
    mutation.mutate(parsed.data);
  };

  const err = (k: string) => errors[k] && <p className="mt-1 text-xs text-rose-300">{errors[k]}</p>;

  return (
    <div className="app-shell-bg dash-shell relative flex min-h-screen w-full flex-col text-slate-100 lg:grid lg:grid-cols-2">
      <div className="absolute right-4 top-4 z-30"><ThemeToggle /></div>

      <AuthHero
        image="https://images.unsplash.com/photo-1625246333195-78d9c38ad449?q=80&w=1200&auto=format&fit=crop"
        badge={<><UserCheck className="h-3.5 w-3.5" /> Pendaftaran Pengguna Terverifikasi</>}
        title={<>Bergabung dalam Ekosistem <span className="text-emerald-400">Ketahanan Pangan.</span></>}
        description="Daftarkan akun Anda untuk mendapatkan akses analisis stok, prediksi AI, dan alur alokasi bahan pangan strategis. Peran (Dinas, Bulog, atau Distributor) ditetapkan oleh Administrator Utama setelah verifikasi."
        footerNote="Akun Wajib Verifikasi Admin"
      />

      <div className="relative flex flex-1 flex-col items-center justify-center bg-[#051410] p-5 sm:p-12 lg:p-16">
        <div className="w-full max-w-md">
          <div className="rounded-2xl border border-emerald-900/40 bg-[#081c16]/80 p-6 backdrop-blur-xl sm:p-10">
            <div className="mb-6">
              <h2 className="text-2xl font-bold tracking-tight text-white">Pendaftaran Akun Baru</h2>
              <p className="mt-1 text-xs text-slate-400">Lengkapi data Anda untuk mengajukan akses ke FoodStock AI.</p>
            </div>

            <form onSubmit={handleRegister} className="space-y-4" noValidate>
              <div>
                <label htmlFor="reg-nama" className={LABEL}>Nama Lengkap</label>
                <div className="relative">
                  <User className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-500" />
                  <Input id="reg-nama" autoComplete="name" value={form.nama} onChange={(e) => update({ nama: e.target.value })} placeholder="Budi Santoso, S.T." className="pl-10 text-base sm:text-sm" />
                </div>
                {err("nama")}
              </div>

              <div>
                <label htmlFor="reg-email" className={LABEL}>Email</label>
                <div className="relative">
                  <Mail className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-500" />
                  <Input id="reg-email" type="email" autoComplete="email" value={form.email} onChange={(e) => update({ email: e.target.value })} placeholder="budi@instansi.go.id" className="pl-10 text-base sm:text-sm" />
                </div>
                {err("email")}
              </div>

              <div>
                <label htmlFor="reg-instansi" className={LABEL}>Nama Instansi / Wilayah</label>
                <div className="relative">
                  <Building className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-500" />
                  <Input id="reg-instansi" value={form.instansi} onChange={(e) => update({ instansi: e.target.value })} placeholder="Dinas Ketahanan Pangan Prov. Jateng" className="pl-10 text-base sm:text-sm" />
                </div>
                {err("instansi")}
              </div>

              <div>
                <label htmlFor="reg-password" className={LABEL}>Password</label>
                <div className="relative">
                  <Lock className="absolute left-3.5 top-3.5 z-10 h-4 w-4 text-slate-500" />
                  <PasswordInput id="reg-password" autoComplete="new-password" value={form.password} onChange={(e) => update({ password: e.target.value })} placeholder="Minimal 8 karakter" className="pl-10 text-base sm:text-sm" />
                </div>
                {err("password")}
              </div>

              <div>
                <label htmlFor="reg-konfirmasi" className={LABEL}>Konfirmasi Password</label>
                <div className="relative">
                  <Lock className="absolute left-3.5 top-3.5 z-10 h-4 w-4 text-slate-500" />
                  <PasswordInput id="reg-konfirmasi" autoComplete="new-password" value={form.konfirmasiPassword} onChange={(e) => update({ konfirmasiPassword: e.target.value })} placeholder="Ulangi password" className="pl-10 text-base sm:text-sm" />
                </div>
                {err("konfirmasiPassword")}
              </div>

              {mutation.isError && (
                <p className="flex items-center gap-1.5 rounded-lg border border-rose-500/30 bg-rose-950/30 px-3 py-2 text-xs text-rose-300" role="alert">
                  <AlertCircle className="h-4 w-4 shrink-0" /> {(mutation.error as Error).message}
                </p>
              )}

              <Button type="submit" loading={mutation.isPending} className="mt-2 flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 py-3 font-bold text-white shadow-lg shadow-emerald-950/50 hover:from-emerald-500 hover:to-teal-500">
                Daftarkan Akun <ArrowRight className="h-4 w-4" />
              </Button>
            </form>

            <div className="mt-6 text-center text-xs text-slate-400">
              Sudah punya akun? <Link href="/login" className="font-bold text-emerald-400 hover:underline">Masuk Ke Sistem</Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
