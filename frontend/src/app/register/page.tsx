"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth";
import { Alert } from "@/components/ui";

const PLANS = [
  { code: "starter", name: "Starter", price: "₹999/mo", blurb: "25 employees · Attendance + Leave" },
  { code: "growth", name: "Growth", price: "₹2,999/mo", blurb: "100 employees · + Payroll" },
  { code: "enterprise", name: "Enterprise", price: "₹9,999/mo", blurb: "500 employees · All modules" },
];

export default function RegisterPage() {
  const { registerCompany } = useAuth();
  const router = useRouter();
  const [form, setForm] = useState({
    company_name: "",
    admin_name: "",
    email: "",
    password: "",
    password_confirmation: "",
    plan_code: "growth",
  });
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  // Honour ?plan=<code> from the public pricing page links.
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const plan = params.get("plan");
    if (plan && ["starter", "growth", "enterprise"].includes(plan)) {
      setForm((f) => ({ ...f, plan_code: plan }));
    }
  }, []);

  function set(k: string, v: string) {
    setForm((f) => ({ ...f, [k]: v }));
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await registerCompany(form);
      router.push("/dashboard");
    } catch (err: any) {
      const details = err?.details ? Object.values(err.details).flat().join(" ") : "";
      setError(`${err?.message || "Registration failed."} ${details}`.trim());
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-50 to-slate-100 p-4">
      <div className="w-full max-w-xl">
        <div className="mb-6 text-center">
          <h1 className="text-2xl font-bold text-slate-900">Create your company workspace</h1>
          <p className="text-sm text-slate-500">Start a free trial — no card required</p>
        </div>
        <form onSubmit={onSubmit} className="card space-y-4">
          {error && <Alert kind="error">{error}</Alert>}

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block">
              <span className="label">Company name</span>
              <input className="input" value={form.company_name} onChange={(e) => set("company_name", e.target.value)} required />
            </label>
            <label className="block">
              <span className="label">Your name (admin)</span>
              <input className="input" value={form.admin_name} onChange={(e) => set("admin_name", e.target.value)} required />
            </label>
          </div>

          <label className="block">
            <span className="label">Work email</span>
            <input type="email" className="input" value={form.email} onChange={(e) => set("email", e.target.value)} required />
          </label>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block">
              <span className="label">Password</span>
              <input type="password" className="input" value={form.password} onChange={(e) => set("password", e.target.value)} minLength={8} required />
            </label>
            <label className="block">
              <span className="label">Confirm password</span>
              <input type="password" className="input" value={form.password_confirmation} onChange={(e) => set("password_confirmation", e.target.value)} minLength={8} required />
            </label>
          </div>

          <div>
            <span className="label">Choose a plan</span>
            <div className="grid gap-3 sm:grid-cols-3">
              {PLANS.map((p) => (
                <button
                  type="button"
                  key={p.code}
                  onClick={() => set("plan_code", p.code)}
                  className={`rounded-xl border p-3 text-left transition ${
                    form.plan_code === p.code
                      ? "border-brand-500 ring-2 ring-brand-100"
                      : "border-slate-200 hover:border-slate-300"
                  }`}
                >
                  <div className="text-sm font-bold text-slate-900">{p.name}</div>
                  <div className="text-sm text-brand-600">{p.price}</div>
                  <div className="mt-1 text-xs text-slate-500">{p.blurb}</div>
                </button>
              ))}
            </div>
          </div>

          <button type="submit" className="btn-primary w-full" disabled={busy}>
            {busy ? "Creating…" : "Create workspace & start trial"}
          </button>
        </form>
        <p className="mt-4 text-center text-sm text-slate-500">
          Already have an account?{" "}
          <Link href="/login" className="font-semibold text-brand-600 hover:underline">
            Sign in
          </Link>
        </p>
      </div>
    </div>
  );
}
