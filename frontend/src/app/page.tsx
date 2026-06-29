"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth";
import { api, money, ApiError } from "@/lib/api";
import { Spinner } from "@/components/ui";

type PublicPlan = {
  code: string;
  name: string;
  base_price: number;
  currency: string;
  included_seats: number;
  per_seat_price: number;
  trial_days: number;
  billing_cycle: string;
  modules: string[];
};

const FEATURES = [
  { icon: "🕒", title: "Attendance", body: "Self check-in/out, shifts, overtime, and corrections — or HR marks it." },
  { icon: "🌴", title: "Leave", body: "Policies, balances, and an approval workflow wired to attendance." },
  { icon: "💰", title: "Payroll", body: "Salary structures, attendance-driven loss-of-pay, and payslips." },
  { icon: "🧾", title: "Billing", body: "Plans, seats, GST invoices, and Razorpay payments." },
  { icon: "👥", title: "Employees", body: "A complete digital record for every employee." },
  { icon: "🔒", title: "Multi-tenant", body: "Every company's data is fully isolated and secure." },
];

const FAQS = [
  ["Is there a free trial?", "Yes — every plan starts with a free trial. No card required to begin."],
  ["Can I change plans later?", "Absolutely. Upgrade anytime (effective immediately) or downgrade at the next cycle."],
  ["What happens if I stop paying?", "Access pauses but your data is never deleted — it returns the moment you reactivate."],
  ["Do employees get their own login?", "Yes, when your plan includes self-attendance, each employee gets a self-service portal."],
];

const WHY = [
  { icon: "⚡", title: "Live in minutes", body: "Self-serve onboarding wizard and bulk CSV import get your whole team set up the same day." },
  { icon: "🧩", title: "Modular by design", body: "Turn modules on or off per plan. Pay only for attendance, leave, payroll, or the full suite." },
  { icon: "🔐", title: "Secure & isolated", body: "Strict multi-tenant isolation, role-based access, and encrypted statutory identifiers." },
  { icon: "🌍", title: "Built for India & beyond", body: "GST invoicing, PF/ESI/TDS-ready payroll, and multi-currency support out of the box." },
  { icon: "📈", title: "Scales with you", body: "From a 5-person startup to thousands of employees across departments and locations." },
  { icon: "🤝", title: "Real support", body: "Human help during onboarding and a clear migration path — never locked in." },
];

const COMPARISON: Array<[string, boolean | string, boolean | string, boolean | string]> = [
  // [feature, Starter, Growth, Enterprise]
  ["Employees & departments", true, true, true],
  ["Attendance & leave", true, true, true],
  ["Payroll & payslips", false, true, true],
  ["Statutory PF / ESI / TDS", false, true, true],
  ["GPS-tagged attendance", false, true, true],
  ["Biometric devices", false, false, true],
  ["Employee self-service login", false, true, true],
  ["Priority support & SLA", false, false, true],
];

const TESTIMONIALS = [
  { quote: "We replaced three tools and a pile of spreadsheets. Payroll that used to take two days now takes an hour.", name: "Operations Lead", role: "120-person services firm" },
  { quote: "The attendance-to-payroll flow just works. Loss-of-pay is calculated automatically and we trust the numbers.", name: "HR Manager", role: "Manufacturing SME" },
  { quote: "Onboarding was genuinely under 15 minutes. The bulk import saved us an entire afternoon.", name: "Founder", role: "Early-stage startup" },
];

export default function Landing() {
  const { user, loading } = useAuth();
  const router = useRouter();
  const [plans, setPlans] = useState<PublicPlan[]>([]);

  useEffect(() => {
    if (!loading && user) router.replace("/dashboard");
  }, [user, loading, router]);

  useEffect(() => {
    api<PublicPlan[]>("/public/plans", { auth: false })
      .then(setPlans)
      .catch(() => {});
  }, []);

  if (loading || user) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-white">
        <Spinner />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white text-slate-800">
      {/* Nav */}
      <header className="sticky top-0 z-40 border-b border-slate-100 bg-white/80 backdrop-blur">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
          <div className="flex items-center gap-2 font-extrabold text-slate-900">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-emerald-500 text-sm text-white">
              HR
            </span>
            HRMS SaaS
          </div>
          <nav className="hidden gap-6 text-sm font-medium text-slate-600 md:flex">
            <a href="#features" className="hover:text-brand-600">Features</a>
            <a href="#why" className="hover:text-brand-600">Why us</a>
            <a href="#pricing" className="hover:text-brand-600">Pricing</a>
            <a href="#faq" className="hover:text-brand-600">FAQ</a>
            <a href="#contact" className="hover:text-brand-600">Contact</a>
          </nav>
          <div className="flex items-center gap-2">
            <Link href="/login" className="btn-ghost text-sm">Sign in</Link>
            <Link href="/register" className="btn-primary text-sm">Start free</Link>
          </div>
        </div>
      </header>

      {/* Hero */}
      <section className="bg-gradient-to-br from-brand-50 via-white to-emerald-50">
        <div className="mx-auto max-w-6xl px-6 py-24 text-center">
          <span className="inline-block rounded-full bg-brand-100 px-4 py-1 text-xs font-semibold text-brand-700">
            Modern HR for growing companies
          </span>
          <h1 className="mx-auto mt-6 max-w-3xl text-5xl font-extrabold tracking-tight text-slate-900">
            Run HR, attendance &amp; payroll on one simple platform
          </h1>
          <p className="mx-auto mt-5 max-w-2xl text-lg text-slate-500">
            Stop juggling spreadsheets. Onboard your team, track attendance, approve leave and run
            payroll — in minutes, not days.
          </p>
          <div className="mt-8 flex justify-center gap-3">
            <Link href="/register" className="btn-primary px-6 py-3 text-base">Start your free trial</Link>
            <a href="#pricing" className="btn-ghost px-6 py-3 text-base">See pricing</a>
          </div>
        </div>
      </section>

      {/* Features */}
      <section id="features" className="mx-auto max-w-6xl px-6 py-20">
        <h2 className="text-center text-3xl font-bold text-slate-900">Everything HR, in one place</h2>
        <p className="mx-auto mt-3 max-w-xl text-center text-slate-500">
          A complete, modular suite — buy only what you need and add more as you grow.
        </p>
        <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {FEATURES.map((f) => (
            <div key={f.title} className="rounded-2xl border border-slate-100 p-6 shadow-sm">
              <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-xl">{f.icon}</div>
              <h3 className="mt-4 font-bold text-slate-900">{f.title}</h3>
              <p className="mt-1 text-sm text-slate-500">{f.body}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Why choose us */}
      <section id="why" className="bg-slate-900 py-20 text-white">
        <div className="mx-auto max-w-6xl px-6">
          <h2 className="text-center text-3xl font-bold">Why teams choose us</h2>
          <p className="mx-auto mt-3 max-w-xl text-center text-slate-300">
            Powerful where it matters, simple where it counts.
          </p>
          <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {WHY.map((w) => (
              <div key={w.title} className="rounded-2xl bg-white/5 p-6 ring-1 ring-white/10">
                <div className="text-2xl">{w.icon}</div>
                <h3 className="mt-3 font-bold">{w.title}</h3>
                <p className="mt-1 text-sm text-slate-300">{w.body}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Pricing (dynamic) */}
      <section id="pricing" className="bg-slate-50 py-20">
        <div className="mx-auto max-w-6xl px-6">
          <h2 className="text-center text-3xl font-bold text-slate-900">Simple, transparent pricing</h2>
          <p className="mx-auto mt-3 max-w-xl text-center text-slate-500">
            Every plan includes a free trial. Prices update live from our system.
          </p>
          <div className="mt-12 grid gap-6 md:grid-cols-3">
            {plans.length === 0 ? (
              <p className="col-span-3 text-center text-slate-400">Loading plans…</p>
            ) : (
              plans.map((p, i) => (
                <div
                  key={p.code}
                  className={`rounded-2xl border bg-white p-8 shadow-sm ${i === 1 ? "border-brand-500 ring-2 ring-brand-100" : "border-slate-100"}`}
                >
                  {i === 1 && (
                    <span className="mb-3 inline-block rounded-full bg-brand-600 px-3 py-1 text-xs font-semibold text-white">
                      Most popular
                    </span>
                  )}
                  <h3 className="text-xl font-bold text-slate-900">{p.name}</h3>
                  <div className="mt-2 text-4xl font-extrabold text-slate-900">
                    {money(p.base_price, p.currency)}
                    <span className="text-base font-normal text-slate-400">/{p.billing_cycle === "yearly" ? "yr" : "mo"}</span>
                  </div>
                  <ul className="mt-6 space-y-2 text-sm text-slate-600">
                    <li>✓ {p.included_seats} employees included</li>
                    <li>✓ {money(p.per_seat_price, p.currency)} per extra seat</li>
                    <li>✓ {p.trial_days}-day free trial</li>
                    <li className="capitalize">✓ {p.modules.join(", ")}</li>
                  </ul>
                  <Link href={`/register?plan=${p.code}`} className={`mt-8 block w-full text-center ${i === 1 ? "btn-primary" : "btn-ghost"}`}>
                    Choose {p.name}
                  </Link>
                </div>
              ))
            )}
          </div>
        </div>
      </section>

      {/* Plan comparison */}
      <section className="mx-auto max-w-5xl px-6 py-20">
        <h2 className="text-center text-3xl font-bold text-slate-900">Compare plans</h2>
        <p className="mx-auto mt-3 max-w-xl text-center text-slate-500">
          Every plan grows with you. Here&apos;s what&apos;s included where.
        </p>
        <div className="mt-10 overflow-x-auto">
          <table className="w-full border-collapse text-sm">
            <thead>
              <tr className="border-b border-slate-200 text-slate-900">
                <th className="py-3 text-left font-semibold">Capability</th>
                <th className="py-3 text-center font-semibold">Starter</th>
                <th className="py-3 text-center font-semibold text-brand-700">Growth</th>
                <th className="py-3 text-center font-semibold">Enterprise</th>
              </tr>
            </thead>
            <tbody>
              {COMPARISON.map(([feature, s, g, e]) => (
                <tr key={feature as string} className="border-b border-slate-100">
                  <td className="py-3 text-slate-600">{feature}</td>
                  <td className="py-3 text-center">{cell(s)}</td>
                  <td className="py-3 text-center">{cell(g)}</td>
                  <td className="py-3 text-center">{cell(e)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      {/* Testimonials */}
      <section className="bg-slate-50 py-20">
        <div className="mx-auto max-w-6xl px-6">
          <h2 className="text-center text-3xl font-bold text-slate-900">Loved by HR teams</h2>
          <div className="mt-12 grid gap-6 md:grid-cols-3">
            {TESTIMONIALS.map((t) => (
              <figure key={t.name} className="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                <div className="text-2xl text-brand-400">&ldquo;</div>
                <blockquote className="mt-1 text-sm text-slate-600">{t.quote}</blockquote>
                <figcaption className="mt-4 text-sm">
                  <span className="font-semibold text-slate-900">{t.name}</span>
                  <span className="block text-xs text-slate-400">{t.role}</span>
                </figcaption>
              </figure>
            ))}
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section id="faq" className="mx-auto max-w-3xl px-6 py-20">
        <h2 className="text-center text-3xl font-bold text-slate-900">Frequently asked questions</h2>
        <div className="mt-10 space-y-3">
          {FAQS.map(([q, a]) => (
            <details key={q} className="rounded-xl border border-slate-100 p-4">
              <summary className="cursor-pointer font-semibold text-slate-800">{q}</summary>
              <p className="mt-2 text-sm text-slate-500">{a}</p>
            </details>
          ))}
        </div>
      </section>

      {/* Contact */}
      <ContactSection />

      {/* Footer */}
      <footer className="border-t border-slate-100 py-10">
        <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 text-sm text-slate-400 md:flex-row">
          <div>© {new Date().getFullYear()} HRMS SaaS. All rights reserved.</div>
          <div className="flex flex-wrap gap-5">
            <Link href="/about" className="hover:text-slate-600">About</Link>
            <Link href="/privacy" className="hover:text-slate-600">Privacy</Link>
            <Link href="/terms" className="hover:text-slate-600">Terms</Link>
            <a href="/docs/index.html" target="_blank" rel="noreferrer" className="hover:text-slate-600">Docs</a>
            <a href="#contact" className="hover:text-slate-600">Contact</a>
            <Link href="/login" className="hover:text-slate-600">Sign in</Link>
          </div>
        </div>
      </footer>
    </div>
  );
}

function cell(v: boolean | string) {
  if (typeof v === "string") return <span className="text-slate-600">{v}</span>;
  return v ? <span className="text-emerald-600">✓</span> : <span className="text-slate-300">—</span>;
}

function ContactSection() {
  const [form, setForm] = useState({ name: "", company_name: "", email: "", phone: "", subject: "", message: "" });
  const [done, setDone] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  function set(k: string, v: string) {
    setForm((f) => ({ ...f, [k]: v }));
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await api("/public/contact", { method: "POST", auth: false, body: form });
      setDone(true);
    } catch (err) {
      const e2 = err as ApiError;
      setError(e2.message || "Could not send. Please try again.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <section id="contact" className="bg-slate-900 py-20 text-white">
      <div className="mx-auto grid max-w-6xl gap-10 px-6 md:grid-cols-2">
        <div>
          <h2 className="text-3xl font-bold">Talk to us</h2>
          <p className="mt-3 max-w-md text-slate-300">
            Questions about plans, onboarding, or a custom enterprise setup? Send us a note and our
            team will get back to you.
          </p>
        </div>
        {done ? (
          <div className="flex items-center rounded-2xl bg-white/10 p-8 text-lg">
            ✅ Thanks! We&apos;ve received your message and will be in touch shortly.
          </div>
        ) : (
          <form onSubmit={submit} className="space-y-3 rounded-2xl bg-white p-6 text-slate-800">
            {error && <div className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
            <div className="grid gap-3 sm:grid-cols-2">
              <input className="input" placeholder="Your name *" value={form.name} onChange={(e) => set("name", e.target.value)} required />
              <input className="input" placeholder="Company" value={form.company_name} onChange={(e) => set("company_name", e.target.value)} />
              <input type="email" className="input" placeholder="Email *" value={form.email} onChange={(e) => set("email", e.target.value)} required />
              <input className="input" placeholder="Phone" value={form.phone} onChange={(e) => set("phone", e.target.value)} />
            </div>
            <input className="input" placeholder="Subject" value={form.subject} onChange={(e) => set("subject", e.target.value)} />
            <textarea className="input" rows={4} placeholder="Message *" value={form.message} onChange={(e) => set("message", e.target.value)} required />
            <button className="btn-primary w-full" disabled={busy}>
              {busy ? "Sending…" : "Send message"}
            </button>
          </form>
        )}
      </div>
    </section>
  );
}
