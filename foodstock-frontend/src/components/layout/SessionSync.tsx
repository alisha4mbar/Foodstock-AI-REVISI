"use client";

import { useEffect } from "react";
import { useSessionStore } from "@/store/useSessionStore";
import { RoleSchema } from "@/types/domain";

/** Menyelaraskan store sesi klien dengan cookie sesi server di setiap dashboard. */
export function SessionSync() {
  const setSession = useSessionStore((s) => s.setSession);
  useEffect(() => {
    let alive = true;
    fetch("/api/auth/session", { cache: "no-store" })
      .then(async (res) => {
        if (!alive) return;
        if (res.status === 401) {
          setSession(null);
          window.location.href = "/login?redirected=1";
          return;
        }
        const json = await res.json().catch(() => null);
        const role = RoleSchema.safeParse(json?.session?.role);
        if (role.success) {
          setSession({ ...json.session, role: role.data });
          if (json.session.status === "pending") window.location.href = "/menunggu";
        }
      })
      .catch(() => undefined);
    return () => {
      alive = false;
    };
  }, [setSession]);
  return null;
}
