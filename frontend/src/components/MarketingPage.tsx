import Link from "next/link";

/**
 * Shared chrome (header + footer) for static marketing pages: About, Privacy,
 * Terms. Keeps the public site visually consistent with the landing page.
 */
export default function MarketingPage({ title, updated, children }: { title: string; updated?: string; children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-white text-slate-800">
      <header className="sticky top-0 z-40 border-b border-slate-100 bg-white/80 backdrop-blur">
        <div className="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
          <Link href="/" className="flex items-center gap-2 font-extrabold text-slate-900">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-emerald-500 text-sm text-white">
              HR
            </span>
            HRMS SaaS
          </Link>
          <div className="flex items-center gap-2">
            <Link href="/login" className="btn-ghost text-sm">Sign in</Link>
            <Link href="/register" className="btn-primary text-sm">Start free</Link>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-3xl px-6 py-16">
        <h1 className="text-4xl font-extrabold tracking-tight text-slate-900">{title}</h1>
        {updated && <p className="mt-2 text-sm text-slate-400">Last updated: {updated}</p>}
        <div className="prose mt-8 max-w-none text-slate-600 [&_h2]:mt-8 [&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-slate-900 [&_p]:mt-3 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:pl-6 [&_li]:mt-1">
          {children}
        </div>
      </main>

      <footer className="border-t border-slate-100 py-10">
        <div className="mx-auto flex max-w-4xl flex-col items-center justify-between gap-4 px-6 text-sm text-slate-400 md:flex-row">
          <div>© {new Date().getFullYear()} HRMS SaaS. All rights reserved.</div>
          <div className="flex flex-wrap gap-5">
            <Link href="/about" className="hover:text-slate-600">About</Link>
            <Link href="/privacy" className="hover:text-slate-600">Privacy</Link>
            <Link href="/terms" className="hover:text-slate-600">Terms</Link>
            <Link href="/" className="hover:text-slate-600">Home</Link>
          </div>
        </div>
      </footer>
    </div>
  );
}
