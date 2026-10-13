import { NextResponse, type NextRequest } from "next/server";
import { TOKEN_COOKIE_NAME } from "@/lib/session";
import { BACKEND_UNREACHABLE, LARAVEL_API_URL } from "@/lib/laravel";

/**
 * Proxy (BFF) dari browser -> Laravel. Browser hanya memanggil /api/v1/*; token Sanctum
 * diambil dari cookie httpOnly lalu ditempel sebagai header Authorization di sini.
 * Keuntungan: tidak ada masalah CORS, dan token tidak pernah terbaca oleh JavaScript klien.
 */
export const dynamic = "force-dynamic";

// Login/logout wajib lewat /api/auth/session agar cookie ikut diatur; jangan bocorkan token ke klien.
const BLOCKED = new Set(["auth/login", "auth/logout"]);

async function handle(req: NextRequest, ctx: { params: Promise<{ path: string[] }> }) {
  const { path } = await ctx.params;
  const joined = path.join("/");
  if (BLOCKED.has(joined)) {
    return NextResponse.json({ code: "NOT_FOUND", message: "Data tidak ditemukan.", errors: null }, { status: 404 });
  }

  const headers = new Headers({ Accept: "application/json" });
  const contentType = req.headers.get("content-type");
  if (contentType) headers.set("content-type", contentType);
  const token = req.cookies.get(TOKEN_COOKIE_NAME)?.value;
  if (token) headers.set("Authorization", `Bearer ${token}`);

  const hasBody = req.method !== "GET" && req.method !== "HEAD";
  try {
    const upstream = await fetch(`${LARAVEL_API_URL}/${joined}${req.nextUrl.search}`, {
      method: req.method,
      headers,
      body: hasBody ? await req.arrayBuffer() : undefined,
      cache: "no-store",
    });
    return new NextResponse(upstream.body, {
      status: upstream.status,
      headers: { "content-type": upstream.headers.get("content-type") ?? "application/json" },
    });
  } catch {
    return NextResponse.json(BACKEND_UNREACHABLE, { status: 502 });
  }
}

export { handle as GET, handle as POST, handle as PUT, handle as PATCH, handle as DELETE };
