"use client";

import { useEffect, useState } from "react";
import { api, money } from "@/lib/api";
import { PageHeader, Card, Stat, Spinner, Badge, statusColor, Table, Td, Empty } from "@/components/ui";

type Dash = {
  companies: { total: number; active: number; suspended: number; trial: number; expired: number };
  subscriptions: { active: number; by_status: Record<string, number> };
  revenue: { mrr: number; arr: number; total_collected: number; currency: string };
  employees: number;
  payroll: { runs_completed: number; total_net: number };
  attendance: { records: number; present: number };
  popular_plan: { name: string; count: number } | null;
  module_usage: { module: string; companies: number }[];
  upcoming_renewals: { company: string; renews_at: string }[];
  failed_payments: number;
  growth: { month: string; companies: number }[];
  recent_companies: { uuid: string; name: string; status: string; created_at: string }[];
  system_health: { database: boolean; cache: boolean };
};

export default function AdminDashboard() {
  const [d, setD] = useState<Dash | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api<Dash>("/admin/dashboard")
      .then(setD)
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <Spinner />;
  if (!d) return <Empty message="Could not load platform metrics." />;

  const maxGrowth = Math.max(1, ...d.growth.map((g) => g.companies));

  return (
    <div>
      <PageHeader title="Owner Dashboard" subtitle="Platform-wide analytics across all companies." />

      {/* KPI cards */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label="Companies" value={d.companies.total} hint={`${d.companies.active} active · ${d.companies.trial} trial`} />
        <Stat label="MRR" value={money(d.revenue.mrr, d.revenue.currency)} hint={`ARR ${money(d.revenue.arr, d.revenue.currency)}`} />
        <Stat label="Revenue collected" value={money(d.revenue.total_collected, d.revenue.currency)} />
        <Stat label="Active subscriptions" value={d.subscriptions.active} />
        <Stat label="Employees (all)" value={d.employees} />
        <Stat label="Payroll runs" value={d.payroll.runs_completed} hint={`Net ${money(d.payroll.total_net)}`} />
        <Stat label="Suspended / Expired" value={`${d.companies.suspended} / ${d.companies.expired}`} />
        <Stat label="Failed payments" value={d.failed_payments} />
      </div>

      <div className="mt-6 grid gap-4 lg:grid-cols-2">
        {/* Company growth */}
        <Card>
          <h3 className="mb-4 font-semibold text-slate-800 dark:text-slate-100">Company growth (6 months)</h3>
          <div className="flex h-40 items-end gap-3">
            {d.growth.map((g) => (
              <div key={g.month} className="flex flex-1 flex-col items-center gap-2">
                <div
                  className="w-full rounded-t bg-brand-500"
                  style={{ height: `${(g.companies / maxGrowth) * 100}%`, minHeight: g.companies ? 6 : 2 }}
                  title={`${g.companies}`}
                />
                <span className="text-[10px] text-slate-400">{g.month.split(" ")[0]}</span>
              </div>
            ))}
          </div>
        </Card>

        {/* Module usage */}
        <Card>
          <h3 className="mb-4 font-semibold text-slate-800 dark:text-slate-100">Module adoption</h3>
          {d.module_usage.length === 0 ? (
            <p className="text-sm text-slate-400">No active subscriptions yet.</p>
          ) : (
            <div className="space-y-2">
              {d.module_usage.map((m) => {
                const max = Math.max(1, ...d.module_usage.map((x) => x.companies));
                return (
                  <div key={m.module} className="flex items-center gap-3">
                    <span className="w-24 text-sm capitalize text-slate-600 dark:text-slate-300">{m.module}</span>
                    <div className="h-2 flex-1 rounded-full bg-slate-100 dark:bg-slate-800">
                      <div className="h-2 rounded-full bg-emerald-500" style={{ width: `${(m.companies / max) * 100}%` }} />
                    </div>
                    <span className="w-8 text-right text-sm text-slate-500">{m.companies}</span>
                  </div>
                );
              })}
            </div>
          )}
          {d.popular_plan && (
            <p className="mt-4 text-sm text-slate-500 dark:text-slate-400">
              Most popular plan: <b className="text-slate-700 dark:text-slate-200">{d.popular_plan.name}</b> ({d.popular_plan.count})
            </p>
          )}
        </Card>
      </div>

      <div className="mt-6 grid gap-4 lg:grid-cols-2">
        <Card>
          <h3 className="mb-3 font-semibold text-slate-800 dark:text-slate-100">Upcoming renewals (30 days)</h3>
          {d.upcoming_renewals.length === 0 ? (
            <p className="text-sm text-slate-400">No renewals due soon.</p>
          ) : (
            <ul className="space-y-2 text-sm">
              {d.upcoming_renewals.map((r, i) => (
                <li key={i} className="flex justify-between">
                  <span className="text-slate-700 dark:text-slate-200">{r.company}</span>
                  <span className="text-slate-400">{r.renews_at?.slice(0, 10)}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
        <Card>
          <h3 className="mb-3 font-semibold text-slate-800 dark:text-slate-100">System health</h3>
          <div className="flex gap-3">
            <Badge color={d.system_health.database ? "green" : "red"}>
              Database {d.system_health.database ? "OK" : "DOWN"}
            </Badge>
            <Badge color={d.system_health.cache ? "green" : "red"}>
              Cache {d.system_health.cache ? "OK" : "DOWN"}
            </Badge>
          </div>
        </Card>
      </div>

      <div className="mt-6">
        <h3 className="mb-3 font-semibold text-slate-800 dark:text-slate-100">Recent companies</h3>
        {d.recent_companies.length === 0 ? (
          <Empty message="No companies yet." />
        ) : (
          <Table head={["Company", "Status", "Joined"]}>
            {d.recent_companies.map((c) => (
              <tr key={c.uuid}>
                <Td className="font-medium text-slate-800 dark:text-slate-100">{c.name}</Td>
                <Td>
                  <Badge color={statusColor(c.status)}>{c.status}</Badge>
                </Td>
                <Td className="text-slate-500">{c.created_at?.slice(0, 10)}</Td>
              </tr>
            ))}
          </Table>
        )}
      </div>
    </div>
  );
}
