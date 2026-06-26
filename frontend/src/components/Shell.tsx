"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { useAuth } from "@/lib/auth";
import { useTheme } from "@/lib/theme";
import { api } from "@/lib/api";
import { Badge, statusColor } from "./ui";

type NavItem = {
  href: string;
  label: string;
  icon: string;
  show: (a: ReturnType<typeof useAuth>) => boolean;
};

const NAV: NavItem[] = [
  { href: "/dashboard", label: "Dashboard", icon: "▦", show: (a) => !a.user?.is_super_admin },
  {
    href: "/employees",
    label: "Employees",
    icon: "👥",
    show: (a) => !a.user?.is_super_admin && a.can("employee.profile.view"),
  },
  {
    href: "/attendance",
    label: "Attendance",
    icon: "🕒",
    show: (a) => !a.user?.is_super_admin && a.hasModule("attendance"),
  },
  {
    href: "/leave",
    label: "Leave",
    icon: "🌴",
    show: (a) => !a.user?.is_super_admin && a.hasModule("leave"),
  },
  {
    href: "/payroll",
    label: "Payroll",
    icon: "💰",
    show: (a) => !a.user?.is_super_admin && a.hasModule("payroll"),
  },
  {
    href: "/billing",
    label: "Subscription & Billing",
    icon: "🧾",
    show: (a) => !a.user?.is_super_admin && (a.can("company.subscription.manage") || a.can("company.billing.manage")),
  },
  {
    href: "/reports",
    label: "Reports",
    icon: "📊",
    show: (a) =>
      !a.user?.is_super_admin &&
      (a.can("attendance.report.view") || a.can("payroll.report.view") || a.can("employee.profile.view")),
  },
  // Super Admin (platform owner) console
  { href: "/admin", label: "Owner Dashboard", icon: "🛰️", show: (a) => !!a.user?.is_super_admin },
  { href: "/admin/companies", label: "Companies", icon: "🏢", show: (a) => !!a.user?.is_super_admin },
  { href: "/admin/plans", label: "Plans & Pricing", icon: "💲", show: (a) => !!a.user?.is_super_admin },
  { href: "/admin/contacts", label: "Inquiries", icon: "✉️", show: (a) => !!a.user?.is_super_admin },
  {
    href: "/notifications",
    label: "Notifications",
    icon: "🔔",
    show: () => true,
  },
];

export default function Shell({ children }: { children: React.ReactNode }) {
  const auth = useAuth();
  const { theme, toggle } = useTheme();
  const pathname = usePathname();
  const router = useRouter();
  const [unread, setUnread] = useState(0);

  const items = NAV.filter((n) => n.show(auth));

  useEffect(() => {
    let active = true;
    const poll = () =>
      api<{ unread: number }>("/notifications/unread-count")
        .then((r) => active && setUnread(r.unread))
        .catch(() => {});
    poll();
    const id = setInterval(poll, 30000);
    return () => {
      active = false;
      clearInterval(id);
    };
  }, [pathname]);

  async function handleLogout() {
    await auth.logout();
    router.push("/login");
  }

  return (
    <div className="flex min-h-screen">
      {/* Sidebar */}
      <aside className="hidden w-64 shrink-0 flex-col border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 md:flex">
        <div className="flex items-center gap-2 px-5 py-5">
          <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-emerald-500 text-sm font-bold text-white">
            HR
          </span>
          <div>
            <div className="text-sm font-bold text-slate-900 dark:text-white">HRMS SaaS</div>
            <div className="text-[11px] uppercase tracking-wide text-slate-400">
              {auth.user?.is_super_admin ? "Platform" : "Company"}
            </div>
          </div>
        </div>
        <nav className="flex-1 space-y-1 px-3 py-2">
          {items.map((n) => {
            const active = pathname === n.href || pathname.startsWith(n.href + "/");
            return (
              <Link
                key={n.href}
                href={n.href}
                className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition ${
                  active
                    ? "bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-100"
                    : "text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
                }`}
              >
                <span className="text-base">{n.icon}</span>
                {n.label}
              </Link>
            );
          })}
        </nav>
        {auth.entitlement && (
          <div className="m-3 rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-800">
            <div className="mb-1 flex items-center justify-between">
              <span className="font-semibold text-slate-600 dark:text-slate-300">Subscription</span>
              <Badge color={statusColor(auth.entitlement.status)}>{auth.entitlement.status}</Badge>
            </div>
            <div className="text-slate-500 dark:text-slate-400">
              {auth.entitlement.modules.length} module(s) active
            </div>
          </div>
        )}
      </aside>

      {/* Main */}
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3 dark:border-slate-800 dark:bg-slate-900">
          <div className="md:hidden text-sm font-bold text-slate-900 dark:text-white">HRMS SaaS</div>
          <div className="flex-1" />
          <div className="flex items-center gap-3">
            <button
              onClick={toggle}
              className="flex h-9 w-9 items-center justify-center rounded-lg text-lg hover:bg-slate-100 dark:hover:bg-slate-800"
              title="Toggle theme"
            >
              {theme === "dark" ? "☀️" : "🌙"}
            </button>
            <Link
              href="/notifications"
              className="relative flex h-9 w-9 items-center justify-center rounded-lg text-lg hover:bg-slate-100 dark:hover:bg-slate-800"
              title="Notifications"
            >
              🔔
              {unread > 0 && (
                <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                  {unread > 9 ? "9+" : unread}
                </span>
              )}
            </Link>
            <div className="text-right">
              <div className="text-sm font-semibold text-slate-800 dark:text-slate-100">{auth.user?.name}</div>
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
