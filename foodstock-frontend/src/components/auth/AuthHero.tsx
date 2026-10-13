import type { ReactNode } from "react";
import { Cpu, ShieldCheck } from "lucide-react";

/**
 * Panel gambar/hero untuk halaman login & register.
 * Desktop: kolom kiri penuh. Smartphone: banner di atas form (gambar tetap tampil).
 */
export function AuthHero({
  image,
  badge,
  title,
  description,
  footerNote,
  children,
}: {
  image: string;
  badge: ReactNode;
  title: ReactNode;
  description: string;
  footerNote: string;
  children?: ReactNode;
}) {
  return (
    <section className="hero-panel relative flex flex-col justify-between overflow-hidden border-b border-emerald-900/30 p-6 pt-16 sm:p-10 sm:pt-16 lg:border-b-0 lg:border-r lg:p-12">
      <div
        className="pointer-events-none absolute inset-0 bg-cover bg-center opacity-30 mix-blend-overlay"
        style={{ backgroundImage: `url('${image}')` }}
      />
      <div className="pointer-events-none absolute -right-20 -top-20 h-72 w-72 rounded-full bg-emerald-500/10 blur-3xl lg:h-96 lg:w-96" />
      <div className="pointer-events-none absolute -bottom-20 -left-20 h-72 w-72 rounded-full bg-teal-500/10 blur-3xl lg:h-96 lg:w-96" />

      <div className="relative z-10 flex items-center gap-3">
        <div className="rounded-2xl border border-emerald-500/30 bg-emerald-500/20 p-2.5 shadow-lg shadow-emerald-500/10">
          <Cpu className="h-6 w-6 text-emerald-400" />
        </div>
        <span className="text-xl font-extrabold tracking-wider text-white">FOODSTOCK<span className="text-emerald-400">.AI</span></span>
      </div>

      <div className="relative z-10 my-auto max-w-lg py-6 lg:py-12">
        <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3.5 py-1.5 text-xs font-semibold text-emerald-300 backdrop-blur-md lg:mb-6">
          {badge}
        </div>
        <h1 className="text-2xl font-extrabold leading-tight tracking-tight sm:text-3xl lg:text-4xl xl:text-5xl">{title}</h1>
        <p className="mt-3 text-sm leading-relaxed text-slate-300 lg:mt-4 lg:text-base">{description}</p>
        {children}
      </div>

      <div className="relative z-10 hidden items-center justify-between text-xs text-slate-400 lg:flex">
        <span>© 2026 FoodStock AI System</span>
        <span className="flex items-center gap-1.5 font-semibold text-emerald-400"><ShieldCheck className="h-4 w-4" /> {footerNote}</span>
      </div>
    </section>
  );
}
