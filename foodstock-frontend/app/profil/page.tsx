"use client";

import { useEffect, useState, type FormEvent } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { AlertCircle, ArrowLeft, CheckCircle2, KeyRound, User2, Bell } from "lucide-react";
import { useSessionStore } from "@/store/useSessionStore";
import { changePassword, fetchMe, updateProfile } from "@/services/api";
import { useWilayahQuery, useKomoditasQuery } from "@/hooks/useQueries";
import { ROLE_LABEL } from "@/types/domain";
import { Button, Card, FormField, Input } from "@/components/ui/Basics";

const ROLE_HOME: Record<string, string> = { admin: "/admin", dinas: "/dinas", bulog: "/bulog", distributor: "/distributor" };

export default function ProfilPage() {
  const session = useSessionStore((s) => s.session);
  const qc = useQueryClient();
  // Profil nyata dari backend: GET /auth/me, simpan lewat PUT /auth/profile dan PUT /auth/password.
  const me = useQuery({ queryKey: ["me"], queryFn: fetchMe });
  const user = me.data ? { ...me.data, createdAt: "" } : undefined;

  const [nama, setNama] = useState("");
  const [instansi, setInstansi] = useState("");
  const [passwordLama, setPasswordLama] = useState("");
  const [passwordBaru, setPasswordBaru] = useState("");
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  useEffect(() => { if (me.data) { setNama(me.data.nama); setInstansi(me.data.instansi); } }, [me.data]);
  useEffect(() => {
    if (!me.data) return;
    try { const raw = window.localStorage.getItem(`foodstock-notif-pref-${me.data.id}`); if (raw) { const p = JSON.parse(raw); setKanal(p.kanal ?? ["dashboard", "email"]); setWilayahIkuti(p.wilayah ?? ""); setKomoditasIkuti(p.komoditas ?? ""); } } catch { /* abaikan */ }
  }, [me.data]);
  const [kanal, setKanal] = useState<string[]>(["dashboard", "email"]);
  const [wilayahIkuti, setWilayahIkuti] = useState("");
  const [komoditasIkuti, setKomoditasIkuti] = useState("");
  const [prefsSaved, setPrefsSaved] = useState(false);
  const wilayahOpts = useWilayahQuery().data ?? [];
  const komoditasOpts = useKomoditasQuery().data ?? [];

  if (!session || !user) {
    return <div className="flex min-h-screen items-center justify-center text-sm text-slate-400 bg-[#051410]">{me.isError ? "Gagal memuat profil." : "Memuat profil…"}</div>;
  }

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      await updateProfile({ nama, instansi });
      if (passwordBaru) {
        if (!passwordLama) throw new Error("Isi kata sandi saat ini untuk mengganti kata sandi.");
        await changePassword({ current_password: passwordLama, new_password: passwordBaru });
      }
      useSessionStore.getState().setSession({ ...session, nama, instansi });
      if (typeof window !== "undefined") window.localStorage.setItem(`foodstock-notif-pref-${user.id}`, JSON.stringify({ kanal, wilayah: wilayahIkuti, komoditas: komoditasIkuti }));
      await qc.invalidateQueries({ queryKey: ["me"] });
      setPasswordLama("");
      setPasswordBaru("");
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="app-shell-bg dash-shell mx-auto min-h-screen max-w-2xl px-5 py-10 text-slate-100">
      <Link href={ROLE_HOME[user.role] ?? "/"} className="mb-6 inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-emerald-400 transition">
        <ArrowLeft className="h-4 w-4" /> Kembali ke Dashboard
      </Link>

      <Card padding="lg" className="agri-card border border-emerald-900/40 bg-[#081c16]/80 backdrop-blur-xl p-8">
        <div className="flex items-center gap-4">
          <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 text-lg font-bold">
            {nama.split(" ").slice(0, 2).map((w) => w[0]).join("").toUpperCase()}
          </span>
          <div>
            <h1 className="text-xl font-bold text-white">{nama}</h1>
            <p className="text-sm font-semibold text-emerald-400 mt-0.5">{ROLE_LABEL[user.role]}</p>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="mt-8 space-y-4">
          <FormField label="Nama Lengkap" htmlFor="profil-nama">
            <Input id="profil-nama" value={nama} onChange={(e) => setNama(e.target.value)} className="bg-[#051410] border-emerald-900/50 text-white" />
          </FormField>
          <FormField label="Email" htmlFor="profil-email" hint="Email tidak dapat diubah.">
            <Input id="profil-email" value={user.email} disabled className="bg-[#051410]/50 border-emerald-900/30 text-slate-500" />
          </FormField>
          <FormField label="Instansi / Wilayah Tugas" htmlFor="profil-instansi">
            <Input id="profil-instansi" value={instansi} onChange={(e) => setInstansi(e.target.value)} className="bg-[#051410] border-emerald-900/50 text-white" />
          </FormField>
          <FormField label="Kata Sandi Saat Ini" htmlFor="profil-password-lama" hint="Hanya diisi jika ingin mengganti kata sandi.">
            <Input id="profil-password-lama" type="password" value={passwordLama} onChange={(e) => setPasswordLama(e.target.value)} placeholder="********" className="bg-[#051410] border-emerald-900/50 text-white placeholder:text-slate-600" />
          </FormField>
          <FormField label="Kata Sandi Baru" htmlFor="profil-password" hint="Minimal 8 karakter. Kosongkan jika tidak ingin mengubah password.">
            <Input id="profil-password" type="password" value={passwordBaru} onChange={(e) => setPasswordBaru(e.target.value)} placeholder="********" className="bg-[#051410] border-emerald-900/50 text-white placeholder:text-slate-600" />
          </FormField>

          {error && (
            <p className="flex items-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-950/20 px-3 py-2 text-xs text-rose-300">
              <AlertCircle className="h-4 w-4" /> {error}
            </p>
          )}
          {saved && (
            <p className="flex items-center gap-1.5 rounded-xl border border-emerald-500/30 bg-emerald-950/20 px-3 py-2 text-xs text-emerald-300">
              <CheckCircle2 className="h-4 w-4 text-emerald-400" /> Profil berhasil diperbarui.
            </p>
          )}

          <div className="flex gap-3 pt-2">
            <Button type="submit" loading={saving} className="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl py-2.5 shadow-lg shadow-emerald-950/50 flex items-center gap-2">
              <User2 className="h-4 w-4" /> Simpan Perubahan
            </Button>
          </div>
        </form>

        <section className="mt-8 border-t border-emerald-900/40 pt-6">
          <h2 className="flex items-center gap-2 font-bold text-white"><Bell className="h-4 w-4 text-emerald-400"/> Preferensi Notifikasi</h2>
          <p className="mt-1 text-xs text-slate-400">Pilihan disimpan secara lokal pada browser prototipe ini.</p>
          <div className="mt-3 flex flex-wrap gap-4 text-sm text-slate-300">
            {["dashboard", "email", "whatsapp"].map((ch) => (
              <label key={ch} className="flex items-center gap-2 cursor-pointer capitalize">
                <input 
                  type="checkbox" 
                  checked={kanal.includes(ch)} 
                  onChange={(e) => setKanal((old) => e.target.checked ? [...old, ch] : old.filter((x) => x !== ch))}
                  className="rounded border-emerald-900 bg-[#051410] text-emerald-600 focus:ring-emerald-500"
                />
                {ch}
              </label>
            ))}
          </div>
          <div className="mt-4 grid gap-3 sm:grid-cols-2">
            <FormField label="Wilayah yang diikuti" htmlFor="pref-wilayah">
              <Input id="pref-wilayah" list="pref-wilayah-list" value={wilayahIkuti} onChange={(e) => setWilayahIkuti(e.target.value)} placeholder="Contoh: Jawa Timur" className="bg-[#051410] border-emerald-900/50 text-white placeholder:text-slate-600" />
            </FormField>
            <FormField label="Komoditas yang diikuti" htmlFor="pref-komoditas">
              <Input id="pref-komoditas" list="pref-komoditas-list" value={komoditasIkuti} onChange={(e) => setKomoditasIkuti(e.target.value)} placeholder="Contoh: Beras Medium" className="bg-[#051410] border-emerald-900/50 text-white placeholder:text-slate-600" />
            </FormField>
          </div>
          <datalist id="pref-wilayah-list">{wilayahOpts.map((w) => <option key={w.id} value={w.nama_wilayah} />)}</datalist>
          <datalist id="pref-komoditas-list">{komoditasOpts.map((k) => <option key={k.id} value={k.nama_komoditas} />)}</datalist>
          <div className="mt-4 flex items-center gap-3">
            <Button 
              type="button" 
              variant="outline" 
              onClick={() => { 
                window.localStorage.setItem(`foodstock-notif-pref-${user.id}`, JSON.stringify({ kanal, wilayah: wilayahIkuti, komoditas: komoditasIkuti })); 
                setPrefsSaved(true); 
                setTimeout(() => setPrefsSaved(false), 2500); 
              }}
              className="border-emerald-800 text-emerald-300 hover:bg-emerald-950 rounded-xl"
            >
              Simpan Preferensi
            </Button>
            {prefsSaved && <span className="text-xs text-emerald-400">Preferensi tersimpan.</span>}
          </div>
        </section>

        <div className="mt-8 flex items-center gap-2 rounded-xl border border-emerald-900/40 bg-[#051410] px-4 py-3 text-xs text-slate-400">
          <KeyRound className="h-4 w-4 text-emerald-400 shrink-0" /> Akun terhubung ke backend: {user.email}.
        </div>
      </Card>
    </div>
  );
}