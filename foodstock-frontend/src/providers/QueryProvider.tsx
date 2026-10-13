"use client";

import { useState, type ReactNode } from "react";
import { MutationCache, QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { ApiError } from "@/lib/http";
import { useUIStore } from "@/store/useUIStore";

export function QueryProvider({ children }: { children: ReactNode }) {
  const [client] = useState(
    () =>
      new QueryClient({
        // Error dari backend (validasi, 403, dll.) tampil sebagai toast. Form login/register
        // menampilkan error-nya sendiri (meta.silent), dan 401 sudah mengarahkan ke /login.
        mutationCache: new MutationCache({
          onError: (error, _vars, _ctx, mutation) => {
            if (mutation.meta?.silent) return;
            if (error instanceof ApiError && error.status === 401) return;
            useUIStore.getState().showToast(error.message || "Terjadi kesalahan pada server.");
          },
        }),
        defaultOptions: {
          queries: { retry: 1, refetchOnWindowFocus: false },
        },
      }),
  );

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}
