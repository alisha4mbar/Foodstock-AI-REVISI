// Klien HTTP sisi browser. Semua request ke Laravel lewat proxy Next.js (/api/v1/*).
export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public code?: string,
    public errors?: Record<string, string[]> | null,
  ) {
    super(message);
  }
}

export async function apiFetch<T = unknown>(path: string, init: RequestInit = {}): Promise<T> {
  const isForm = typeof FormData !== "undefined" && init.body instanceof FormData;
  const res = await fetch(`/api/v1${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      ...(init.body && !isForm ? { "Content-Type": "application/json" } : {}),
      ...init.headers,
    },
  });

  const json = await res.json().catch(() => null);

  if (res.status === 401 && typeof window !== "undefined") {
    // Token kedaluwarsa/dicabut: bersihkan cookie sesi lalu kembali ke halaman login.
    await fetch("/api/auth/session", { method: "DELETE" }).catch(() => undefined);
    window.location.href = "/login?redirected=1";
    throw new ApiError("Sesi berakhir. Silakan masuk kembali.", 401, "UNAUTHENTICATED");
  }

  if (!res.ok) {
    const firstFieldError = json?.errors ? (Object.values(json.errors)[0] as string[] | undefined)?.[0] : undefined;
    throw new ApiError(firstFieldError ?? json?.message ?? `Permintaan gagal (${res.status}).`, res.status, json?.code, json?.errors);
  }
  return json as T;
}

export const apiGet = <T>(path: string) => apiFetch<T>(path);
export const apiSend = <T>(method: "POST" | "PUT" | "PATCH" | "DELETE", path: string, body?: unknown) =>
  apiFetch<T>(path, { method, body: body === undefined ? undefined : JSON.stringify(body) });
