"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth";
import { Badge, statusColor } from "./ui";

type NavItem = {
  href: string;
  label: string;
  icon: string;
  show: (a: ReturnType<typeof useAuth>) => boolean;
};

const NAV: NavItem[] = [
  { href: "/dashboard", label: "Dashboard", icon: "▦", show: () => true },
  {
    href: "/employees",
    label: "Employees",
    icon: "👥",
    show: (a) => a.can("employee.profile.view"),
  },
  {
    href: "/attendance",
    label: "Attendance",
    icon: "🕒",
    show: (a) => a.hasModule("attendance"),
  },
  {
    href: "/leave",
    label: "Leave",
    icon: "🌴",
    show: (a) => a.hasModule("leave"),
  },
  {
    href: "/payroll",
    label: "Payroll",
    icon: "💰",
    show: (a) => a.hasModule("payroll"),
  },
  {
    href: "/billing",
    label: "Subscription & Billing",
    icon: "🧾",
    show: (a) => a.can("company.subscription.manage") || a.can("company.billing.manage"),
  },
];

export default function Shell({ children }: { children: React.ReactNode }) {
  const auth = useAuth();
  const pathname = usePathname();
  const router = useRouter();

  const items = NAV.filter((n) => n.show(auth));

  async function handleLogout() {
    await auth.logout();
    router.push("/login");
  }

  return (
    <div className="flex min-h-screen">
      {/* Sidebar */}
      <aside className="hidden w-64 shrink-0 flex-col border-r border-slate-200 bg-white md:flex">
        <div className="flex items-center gap-2 px-5 py-5">
          <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-emerald-500 text-sm font-bold text-white">
            HR
          </span>
          <div>
            <div className="text-sm font-bold text-slate-900">HRMS SaaS</div>
            <div className="text-[11px] uppercase tracking-wide text-slate-400">
              {auth.user?.is_super_admin ? "Platform" : "Company"}
            </div>
          </div>
        </div>
        <nav className="flex-1 space-y-1 px-3 py-2">
          {items.map((n) => {
            const active = pathname.startsWith(n.href);
            return (
              <Link
                key={n.href}
                href={n.href}
                className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition ${
                  active ? "bg-brand-50 text-brand-700" : "text-slate-600 hover:bg-slate-50"
                }`}
              >
                <span className="text-base">{n.icon}</span>
                {n.label}
              </Link>
            );
          })}
        </nav>
        {auth.entitlement && (
          <div className="m-3 rounded-xl bg-slate-50 p-3 text-xs">
            <div className="mb-1 flex items-center justify-between">
              <span className="font-semibold text-slate-600">Subscription</span>
              <Badge color={statusColor(auth.entitlement.status)}>{auth.entitlement.status}</Badge>
            </div>
            <div className="text-slate-500">
              {auth.entitlement.modules.length} module(s) active
            </div>
          </div>
        )}
      </aside>

      {/* Main */}
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3">
          <div className="md:hidden text-sm font-bold text-slate-900">HRMS SaaS</div>
          <div className="flex-1" />
          <div className="flex items-center gap-3">
            <div className="text-right">
              <div className="text-sm font-semibold text-slate-800">{auth.user?.name}</div>
              <div className="text-xs text-slate-400">{auth.user?.roles?.join(", ")}</div>
            </div>
            <button onClick={handleLogout} className="btn-ghost text-xs">
              Sign out
            </button>
          </div>
        </header>
        <main className="flex-1 p-6">{children}</main>
      </div>
    </div>
  );
}
