import { NextResponse, type NextRequest } from "next/server";
import { SESSION_COOKIE_NAME, verifySession } from "@/lib/session";
import type { Role } from "@/types/domain";

const ROLE_PREFIX: Record<Role, string> = {
  admin: "/admin",
  dinas: "/dinas",
  bulog: "/bulog",
  distributor: "/distributor",
};

const PROTECTED_PREFIXES = [...Object.values(ROLE_PREFIX), "/profil", "/menunggu"];

export async function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  // Pengguna yang sudah masuk tidak perlu melihat login/register lagi.
  if (pathname === "/login" || pathname === "/register") {
    const active = await verifySession(request.cookies.get(SESSION_COOKIE_NAME)?.value);
    if (!active) return NextResponse.next();
    return NextResponse.redirect(new URL(active.status === "pending" ? "/menunggu" : ROLE_PREFIX[active.role], request.url));
  }

  const isProtected = PROTECTED_PREFIXES.some((p) => pathname.startsWith(p));
  if (!isProtected) return NextResponse.next();

  const token = request.cookies.get(SESSION_COOKIE_NAME)?.value;
  const session = await verifySession(token);

  if (!session) {
    const loginUrl = new URL("/login", request.url);
    loginUrl.searchParams.set("redirected", "1");
    return NextResponse.redirect(loginUrl);
  }

  // Akun belum diverifikasi Admin: hanya boleh membuka halaman tunggu.
  if (session.status === "pending") {
    return pathname.startsWith("/menunggu") ? NextResponse.next() : NextResponse.redirect(new URL("/menunggu", request.url));
  }
  // Akun aktif tidak perlu halaman tunggu.
  if (pathname.startsWith("/menunggu")) return NextResponse.redirect(new URL(ROLE_PREFIX[session.role], request.url));

  if (pathname.startsWith("/profil")) return NextResponse.next();
  // Admin diberi akses lintas peran khusus ke halaman impor data historis.
  if (session.role === "admin" && pathname === "/dinas/input-stok") return NextResponse.next();

  const allowedPrefix = ROLE_PREFIX[session.role];
  if (!pathname.startsWith(allowedPrefix)) {
    // Role tidak cocok dengan area yang diminta -> arahkan ke dashboard miliknya sendiri.
    return NextResponse.redirect(new URL(allowedPrefix, request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/login", "/register", "/menunggu/:path*", "/admin/:path*", "/dinas/:path*", "/bulog/:path*", "/distributor/:path*", "/profil/:path*"],
};
