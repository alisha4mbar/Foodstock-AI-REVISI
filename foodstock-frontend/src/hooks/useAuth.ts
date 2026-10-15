"use client";

import { useMutation } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useSessionStore } from "@/store/useSessionStore";
import { ApiError } from "@/lib/http";
import { registerAccount } from "@/services/api";
import { RoleSchema, type LoginFormData, type RegisterFormData } from "@/types/domain";

const ROLE_HOME: Record<string, string> = {
  admin: "/admin",
  dinas: "/dinas",
  bulog: "/bulog",
  distributor: "/distributor",
};

export function useLoginMutation() {
  const router = useRouter();
  const setSession = useSessionStore((s) => s.setSession);

  return useMutation({
    meta: { silent: true },
    // Kredensial diverifikasi Laravel lewat /api/auth/session (yang juga menanam cookie httpOnly).
    mutationFn: async (input: LoginFormData) => {
      const res = await fetch("/api/auth/session", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(input),
      });
      const json = await res.json().catch(() => null);
      if (!res.ok) throw new ApiError(json?.message ?? "Login gagal. Coba lagi.", res.status, json?.code);

      const role = RoleSchema.parse(json.session.role);
      setSession({ ...json.session, role });
      return { role, status: (json.session.status === "pending" ? "pending" : "aktif") as "aktif" | "pending" };
    },
    onSuccess: ({ role, status }) => router.push(status === "pending" ? "/menunggu" : (ROLE_HOME[role] ?? "/")),
  });
}

export function useRegisterMutation() {
  const router = useRouter();

  return useMutation({
    meta: { silent: true },
    mutationFn: (input: RegisterFormData) =>
      registerAccount({ nama: input.nama, email: input.email, instansi: input.instansi, password: input.password }),
    onSuccess: () => router.push("/login?registered=1"),
  });
}

export function useLogout() {
  const router = useRouter();
  const setSession = useSessionStore((s) => s.setSession);

  return async () => {
    await fetch("/api/auth/session", { method: "DELETE" });
    setSession(null);
    router.push("/login");
  };
}
