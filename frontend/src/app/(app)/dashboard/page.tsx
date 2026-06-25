"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useAuth } from "@/lib/auth";
import { apiPaged } from "@/lib/api";
import { PageHeader, Stat, Card, Badge, statusColor } from "@/components/ui";

const MODULE_LABELS: Record<string, string> = {
  attendance: "Attendance",
  leave: "Leave",
  payroll: "Payroll",
  recruitment: "Recruitment",
  assets: "Assets",
  performance: "Performance",
  expenses: "Expenses",
};

export default function DashboardPage() {
  const { user, entitlement, can } = useAuth();
  const [employeeCount, setEmployeeCount] = useState<number | null>(null);

  useEffect(() => {
    if (can("employee.profile.view")) {
      apiPaged("/employees?per_page=1")
        .then((r) => setEmployeeCount(r.meta?.total ?? null))
        .catch(() => setEmployeeCount(null));
    }
  }, [can]);

  const seats = entitlement?.seats;
  const seatLimit = seats?.limit ?? null;

  return (
    <div>
      <PageHeader
        title={`Welcome, ${user?.name?.split(" ")[0] || "there"}`}
        subtitle={
          user?.is_super_admin
            ? "Platform administration"
            : "Here's an overview of your company workspace."
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {entitlement && (
          <Card>
            <div className="text-sm font-medium text-slate-500">Subscription</div>
            <div className="mt-2">
              <Badge color={statusColor(entitlement.status)}>{entitlement.status}</Badge>
            </div>
            <div className="mt-2 text-xs text-slate-400">
              {entitlement.access_granted ? "Access active" : "Access blocked"}
            </div>
          </Card>
        )}
        {can("employee.profile.view") && (
          <Stat
            label="Employees"
            value={employeeCount ?? "—"}
            hint={seatLimit ? `Seat limit: ${seatLimit}` : "Unlimited seats"}
          />
        )}
        {entitlement && (
          <Stat label="Active modules" value={entitlement.modules.length} />
        )}
        {seats && (
          <Stat
            label="Seats"
            value={`${seats.included + (seats.purchased || 0)}`}
            hint={`${seats.included} included + ${seats.purchased || 0} purchased`}
          />
        )}
      </div>

      {entitlement && (
        <div className="mt-8">
          <h2 className="mb-3 text-lg font-bold text-slate-900">Your modules</h2>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {entitlement.modules.map((m) => (
              <Card key={m}>
                <div className="flex items-center justify-between">
                  <div className="font-semibold text-slate-800">{MODULE_LABELS[m] || m}</div>
                  <Badge color="green">licensed</Badge>
                </div>
                {m === "attendance" && (
                  <Link href="/attendance" className="mt-3 inline-block text-sm font-medium text-brand-600 hover:underline">
                    Open attendance →
                  </Link>
                )}
                {m === "leave" && (
                  <Link href="/leave" className="mt-3 inline-block text-sm font-medium text-brand-600 hover:underline">
                    Open leave →
                  </Link>
                )}
                {m === "payroll" && (
                  <Link href="/payroll" className="mt-3 inline-block text-sm font-medium text-brand-600 hover:underline">
                    Open payroll →
                  </Link>
                )}
              </Card>
            ))}
          </div>
        </div>
      )}

      {user?.is_super_admin && (
        <div className="mt-8">
          <Card>
            <h2 className="text-lg font-bold text-slate-900">Platform administration</h2>
            <p className="mt-1 text-sm text-slate-500">
              You are signed in as a Super Admin. Cross-tenant company, plan and billing management
              endpoints are part of the platform API; this console focuses on the company-facing
              experience.
            </p>
          </Card>
        </div>
      )}
    </div>
  );
}
