"use client";

import { useCallback, useEffect, useState } from "react";
import { api, money, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import {
  PageHeader,
  Card,
  Stat,
  Table,
  Td,
  Badge,
  statusColor,
  Empty,
  Spinner,
  Alert,
} from "@/components/ui";

type Plan = {
  code: string;
  name: string;
  base_price: number;
  included_seats: number;
  per_seat_price: number;
  trial_days: number;
};
type Sub = {
  subscription: { uuid: string; status: string; seats: number; current_period_end: string | null; auto_renew: boolean };
  plan: { code: string; name: string; base_price: number; included_seats: number } | null;
  entitlement: { modules: string[]; status: string };
};
type Invoice = {
  uuid: string;
  number: string;
  status: string;
  subtotal: number;
  tax_total: number;
  total: number;
  currency: string;
  issued_at: string | null;
};

export default function BillingPage() {
  const { refreshMe } = useAuth();
  const [sub, setSub] = useState<Sub | null>(null);
  const [plans, setPlans] = useState<Plan[]>([]);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);
  const [msg, setMsg] = useState<{ kind: "success" | "error"; text: string } | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [s, p, inv] = await Promise.all([
        api<Sub>("/subscription"),
        api<Plan[]>("/plans"),
        api<Invoice[]>("/invoices").catch(() => []),
      ]);
      setSub(s);
      setPlans(p || []);
      setInvoices(inv || []);
    } catch (err) {
      setMsg({ kind: "error", text: (err as ApiError).message });
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function changePlan(planCode: string, direction: "upgrade" | "downgrade") {
    setMsg(null);
    try {
      await api(`/subscription/${direction}`, { method: "POST", body: { plan_code: planCode } });
      setMsg({ kind: "success", text: `Plan ${direction} requested.` });
      await load();
      await refreshMe();
    } catch (err) {
      setMsg({ kind: "error", text: (err as ApiError).message });
    }
  }

  async function lifecycle(action: "cancel" | "reactivate") {
    setMsg(null);
    try {
      await api(`/subscription/${action}`, { method: "POST", body: {} });
      setMsg({ kind: "success", text: `Subscription ${action === "cancel" ? "cancelled" : "reactivated"}.` });
      await load();
      await refreshMe();
    } catch (err) {
      setMsg({ kind: "error", text: (err as ApiError).message });
    }
  }

  async function pay(invoice: Invoice) {
    setMsg(null);
    try {
      const res = await api<{ order: any }>("/billing/checkout", { method: "POST", body: { invoice: invoice.uuid } });
      setMsg({
        kind: "success",
        text: `Checkout order created via ${res.order?.gateway || "gateway"} (order ${res.order?.order_id}). A real gateway would now open a hosted payment page.`,
      });
    } catch (err) {
      setMsg({ kind: "error", text: (err as ApiError).message });
    }
  }

  if (loading) return <Spinner />;

  const currentPlanCode = sub?.plan?.code;
  const currentPrice = sub?.plan?.base_price ?? 0;

  return (
    <div>
      <PageHeader title="Subscription & Billing" subtitle="Manage your plan, seats and invoices." />

      {msg && (
        <div className="mb-4">
          <Alert kind={msg.kind}>{msg.text}</Alert>
        </div>
      )}

      {sub && (
        <div className="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Card>
            <div className="text-sm font-medium text-slate-500">Status</div>
            <div className="mt-2">
              <Badge color={statusColor(sub.subscription.status)}>{sub.subscription.status}</Badge>
            </div>
          </Card>
          <Stat label="Current plan" value={sub.plan?.name || "—"} />
          <Stat label="Seats" value={sub.subscription.seats} />
          <Stat
            label="Renews"
            value={sub.subscription.current_period_end ? sub.subscription.current_period_end.slice(0, 10) : "—"}
            hint={sub.subscription.auto_renew ? "Auto-renew on" : "Auto-renew off"}
          />
        </div>
      )}

      <h2 className="mb-3 text-lg font-bold text-slate-900">Plans</h2>
      <div className="mb-8 grid gap-4 sm:grid-cols-3">
        {plans.map((p) => {
          const isCurrent = p.code === currentPlanCode;
          const isUpgrade = p.base_price > currentPrice;
          return (
            <Card key={p.code} className={isCurrent ? "ring-2 ring-brand-500" : ""}>
              <div className="flex items-center justify-between">
                <div className="text-lg font-bold text-slate-900">{p.name}</div>
                {isCurrent && <Badge color="blue">Current</Badge>}
              </div>
              <div className="mt-1 text-2xl font-bold text-brand-600">{money(p.base_price)}<span className="text-sm font-normal text-slate-400">/mo</span></div>
              <ul className="mt-3 space-y-1 text-sm text-slate-500">
                <li>{p.included_seats} employees included</li>
                <li>{money(p.per_seat_price)} per extra seat</li>
                <li>{p.trial_days}-day free trial</li>
              </ul>
              {!isCurrent && (
                <button
                  className={isUpgrade ? "btn-primary mt-4 w-full" : "btn-ghost mt-4 w-full"}
                  onClick={() => changePlan(p.code, isUpgrade ? "upgrade" : "downgrade")}
                >
                  {isUpgrade ? "Upgrade" : "Downgrade"}
                </button>
              )}
            </Card>
          );
        })}
      </div>

      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-lg font-bold text-slate-900">Invoices</h2>
        <div className="flex gap-2">
          {sub?.subscription.status === "cancelled" || sub?.subscription.status === "expired" ? (
            <button className="btn-primary" onClick={() => lifecycle("reactivate")}>
              Reactivate
            </button>
          ) : (
            <button className="btn-ghost" onClick={() => lifecycle("cancel")}>
              Cancel subscription
            </button>
          )}
        </div>
      </div>

      {invoices.length === 0 ? (
        <Empty message="No invoices yet. Invoices are generated on plan changes and renewals." />
      ) : (
        <Table head={["Number", "Status", "Subtotal", "Tax", "Total", ""]}>
          {invoices.map((inv) => (
            <tr key={inv.uuid}>
              <Td className="font-mono text-xs">{inv.number}</Td>
              <Td>
                <Badge color={statusColor(inv.status)}>{inv.status}</Badge>
              </Td>
              <Td>{money(inv.subtotal, inv.currency)}</Td>
              <Td>{money(inv.tax_total, inv.currency)}</Td>
              <Td className="font-semibold">{money(inv.total, inv.currency)}</Td>
              <Td>
                {inv.status === "open" && (
                  <button onClick={() => pay(inv)} className="text-sm font-medium text-brand-600 hover:underline">
                    Pay
                  </button>
                )}
              </Td>
            </tr>
          ))}
        </Table>
      )}
    </div>
  );
}
