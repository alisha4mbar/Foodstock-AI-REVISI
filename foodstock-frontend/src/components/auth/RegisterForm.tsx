"use client";

import { useState, type FormEvent } from "react";
import { RegisterSchema } from "@/types/domain";
import { useRegisterMutation } from "@/hooks/useAuth";
import { Button, FormField, Input, PasswordInput } from "@/components/ui/Basics";
import { AlertCircle } from "lucide-react";

export function RegisterForm() {
  const [form, setForm] = useState({
    nama: "",
    email: "",
    instansi: "",
    password: "",
    konfirmasiPassword: "",
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const registerMutation = useRegisterMutation();

  const update = (patch: Partial<typeof form>) => setForm((f) => ({ ...f, ...patch }));

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    setErrors({});
    const result = RegisterSchema.safeParse(form);
    if (!result.success) {
      const flat = result.error.flatten().fieldErrors;
      const mapped: Record<string, string> = {};
      for (const [k, v] of Object.entries(flat)) if (v?.[0]) mapped[k] = v[0];
      setErrors(mapped);
      return;
    }
    registerMutation.mutate(result.data);
  };

  return (
    <div className="w-full max-w-md rounded-2xl border border-emerald-100 bg-white p-8 shadow-lg">
      <h1 className="text-center text-2xl font-bold text-slate-800">Daftar Akun Baru</h1>
      <p className="text-center text-sm text-emerald-700">Akses Sistem Informasi Ketahanan Pangan</p>

      <form onSubmit={handleSubmit} className="mt-6 space-y-4">
        <FormField label="Nama Lengkap" htmlFor="reg-nama" error={errors.nama}>
          <Input id="reg-nama" placeholder="Budi Santoso" value={form.nama} onChange={(e) => update({ nama: e.target.value })} />
        </FormField>
        <FormField label="Email Instansi" htmlFor="reg-email" error={errors.email}>
          <Input id="reg-email" type="email" placeholder="budi@pangan.go.id" value={form.email} onChange={(e) => update({ email: e.target.value })} />
        </FormField>
        <FormField label="Instansi / Wilayah Tugas" htmlFor="reg-instansi" error={errors.instansi}>
          <Input id="reg-instansi" placeholder="Dinas Ketahanan Pangan Jawa Tengah" value={form.instansi} onChange={(e) => update({ instansi: e.target.value })} />
        </FormField>
        <FormField label="Kata Sandi" htmlFor="reg-password" error={errors.password}>
          <PasswordInput id="reg-password" value={form.password} onChange={(e) => update({ password: e.target.value })} />
        </FormField>
        <FormField label="Konfirmasi Kata Sandi" htmlFor="reg-konfirmasi" error={errors.konfirmasiPassword}>
          <PasswordInput id="reg-konfirmasi" value={form.konfirmasiPassword} onChange={(e) => update({ konfirmasiPassword: e.target.value })} />
        </FormField>

        {registerMutation.isError && (
          <p className="flex items-center gap-1.5 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">
            <AlertCircle className="h-4 w-4" /> {(registerMutation.error as Error).message}
          </p>
        )}

        <p className="text-xs text-slate-400">
          Akun akan berstatus <strong>menunggu verifikasi</strong>. Administrator Utama yang menentukan peran (Dinas, BULOG, atau Distributor) sebelum akun dapat digunakan untuk masuk.
        </p>

        <Button type="submit" className="w-full" loading={registerMutation.isPending}>
          Buat Akun
        </Button>
      </form>

      <p className="mt-4 text-center text-sm text-slate-500">
        Sudah punya akun?{" "}
        <a href="/login" className="font-semibold text-emerald-700 underline">
          Masuk di sini
        </a>
      </p>
    </div>
  );
}
