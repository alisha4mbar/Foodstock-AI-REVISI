import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { SESSION_COOKIE_NAME, TOKEN_COOKIE_NAME, signSession, verifySession } from "@/lib/session";
import { BACKEND_UNREACHABLE, LARAVEL_API_URL } from "@/lib/laravel";
import { RoleSchema } from "@/types/domain";
import { z } from "zod";

// Login sungguhan: kredensial diverifikasi oleh Laravel (bcrypt + Sanctum). Route Handler ini
//  1) meneruskan email/password ke POST /api/v1/auth/login,
//  2) menyimpan token Sanctum di cookie httpOnly,
//  3) menerbitkan cookie sesi bertanda tangan (HMAC) yang dibaca middleware.ts untuk proteksi rute per role.
const LoginBody = z.object({ email: z.string().email(), password: z.string().min(1) });

const cookieBase = {
  httpOnly: true,
  sameSite: "lax" as const,
  secure: process.env.COOKIE_SECURE ? process.env.COOKIE_SECURE === "true" : process.env.NODE_ENV === "production",
  path: "/",
  maxAge: 60 * 60 * 8, // 8 jam
};

export async function POST(request: Request) {
  const body = LoginBody.safeParse(await request.json().catch(() => null));
  if (!body.success) {
    return NextResponse.json({ code: "VALIDATION_ERROR", message: "Email atau password tidak valid.", errors: null }, { status: 422 });
  }

  let upstream: Response;
  try {
    upstream = await fetch(`${LARAVEL_API_URL}/auth/login`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify(body.data),
      cache: "no-store",
    });
  } catch {
    return NextResponse.json(BACKEND_UNREACHABLE, { status: 502 });
  }

  const payload = await upstream.json().catch(() => null);
  if (!upstream.ok || !payload?.data?.token) {
    return NextResponse.json(
      payload?.code ? payload : { code: "LOGIN_FAILED", message: "Login gagal. Coba lagi.", errors: null },
      { status: upstream.status === 200 ? 502 : upstream.status },
    );
  }

  const { token, user } = payload.data as {
    token: string;
    user: { id: number | string; name: string; email: string; role: string; instansi: string | null; status?: string };
  };
  const role = RoleSchema.safeParse(user.role);
  const status = (user as { status?: string }).status === "pending" ? "pending" : "aktif";
  if (!role.success) {
    return NextResponse.json({ code: "INVALID_ROLE", message: "Role pengguna tidak dikenali.", errors: null }, { status: 500 });
  }

  const session = { userId: String(user.id), nama: user.name, role: role.data, instansi: user.instansi ?? "", status: status as "aktif" | "pending" };
  const res = NextResponse.json({ ok: true, session });
  res.cookies.set(TOKEN_COOKIE_NAME, token, cookieBase);
  res.cookies.set(SESSION_COOKIE_NAME, await signSession({ ...session, iat: Date.now() }), cookieBase);
  return res;
}

// Sesi aktif: cookie ditandatangani lalu disegarkan dari Laravel (/auth/me) supaya perubahan
// status/peran oleh Admin (mis. akun pending -> aktif) langsung terbaca tanpa login ulang.
export async function GET() {
  const jar = await cookies();
  const payload = await verifySession(jar.get(SESSION_COOKIE_NAME)?.value);
  const token = jar.get(TOKEN_COOKIE_NAME)?.value;
  const unauth = () => {
    const r = NextResponse.json({ code: "UNAUTHENTICATED", message: "Belum masuk.", errors: null }, { status: 401 });
    r.cookies.set(SESSION_COOKIE_NAME, "", { path: "/", maxAge: 0 });
    r.cookies.set(TOKEN_COOKIE_NAME, "", { path: "/", maxAge: 0 });
    return r;
  };
  if (!payload || !token) return unauth();

  let session = { userId: payload.userId, nama: payload.nama, role: payload.role, instansi: payload.instansi, status: payload.status };
  try {
    const me = await fetch(`${LARAVEL_API_URL}/auth/me`, {
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
      cache: "no-store",
    });
    if (me.status === 401) return unauth();
    if (me.ok) {
      const u = (await me.json().catch(() => null))?.data as
        | { id: number | string; name: string; role: string; instansi: string | null; status: string }
        | undefined;
      const role = RoleSchema.safeParse(u?.role);
      if (u && role.success) {
        // Akun ditolak/terkunci/nonaktif: sesi dicabut.
        if (u.status !== "aktif" && u.status !== "pending") return unauth();
        session = { userId: String(u.id), nama: u.name, role: role.data, instansi: u.instansi ?? "", status: u.status };
      }
    }
  } catch {
    // Backend tidak terjangkau: pakai data dari cookie.
  }

  const res = NextResponse.json({ ok: true, session }, { headers: { "Cache-Control": "no-store" } });
  res.cookies.set(SESSION_COOKIE_NAME, await signSession({ ...session, iat: Date.now() }), cookieBase);
  return res;
}

export async function DELETE() {
  const token = (await cookies()).get(TOKEN_COOKIE_NAME)?.value;
  if (token) {
    // Cabut token di Laravel (best-effort; cookie tetap dihapus walau backend tidak terjangkau).
    await fetch(`${LARAVEL_API_URL}/auth/logout`, {
      method: "POST",
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
      cache: "no-store",
    }).catch(() => undefined);
  }
  const res = NextResponse.json({ ok: true });
  res.cookies.set(SESSION_COOKIE_NAME, "", { path: "/", maxAge: 0 });
  res.cookies.set(TOKEN_COOKIE_NAME, "", { path: "/", maxAge: 0 });
  return res;
}
