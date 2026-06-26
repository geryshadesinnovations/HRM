"use client";

import { useCallback, useEffect, useState } from "react";
import { api, money, ApiError } from "@/lib/api";
import { PageHeader, Table, Td, Badge, Empty, Spinner, Alert, Modal } from "@/components/ui";

type Plan = {
  id: number;
  code: string;
  name: string;
  billing_cycle: string;
  base_price: number;
  currency: string;
  included_seats: number;
  per_seat_price: number;
  trial_days: number;
  is_public: boolean;
  is_active: boolean;
  modules: { id: number; code: string; name: string }[];
};

const ALL_MODULES = ["attendance", "leave", "payroll", "recruitment", "assets", "performance", "expenses"];

export default function AdminPlans() {
  const [rows, setRows] = useState<Plan[]>([]);
  const [loading, setLoading] = useState(true);
  const [edit, setEdit] = useState<Plan | null>(null);
  const [creating, setCreating] = useState(false);
  const [msg, setMsg] = useState<{ kind: "success" | "error"; text: string } | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      setRows((await api<Plan[]>("/admin/plans")) || []);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function toggle(p: Plan) {
    await api(`/admin/plans/${p.id}/toggle`, { method: "POST", body: {} });
    load();
  }
  async function duplicate(p: Plan) {
    await api(`/admin/plans/${p.id}/duplicate`, { method: "POST", body: {} });
    load();
  }
  async function remove(p: Plan) {
    if (!confirm(`Delete plan "${p.name}"?`)) return;
    await api(`/admin/plans/${p.id}`, { method: "DELETE" });
    load();
  }

  return (
    <div>
      <PageHeader
        title="Plans & Pricing"
        subtitle="Changes here update the public pricing page automatically."
        actions={
          <button className="btn-primary" onClick={() => setCreating(true)}>
            + New plan
          </button>
        }
      />

      {msg && (
        <div className="mb-4">
          <Alert kind={msg.kind}>{msg.text}</Alert>
        </div>
      )}

      {loading ? (
        <Spinner />
      ) : rows.length === 0 ? (
        <Empty message="No plans yet." />
      ) : (
        <Table head={["Plan", "Price", "Seats", "Modules", "Visibility", "Actions"]}>
          {rows.map((p) => (
            <tr key={p.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/50">
              <Td className="font-medium text-slate-800 dark:text-slate-100">
                {p.name} <span className="ml-1 font-mono text-xs text-slate-400">{p.code}</span>
              </Td>
              <Td>
                {money(p.base_price, p.currency)}/{p.billing_cycle === "yearly" ? "yr" : "mo"}
              </Td>
              <Td>
                {p.included_seats} + {money(p.per_seat_price)}/seat
              </Td>
              <Td className="text-xs">{p.modules.map((m) => m.code).join(", ") || "—"}</Td>
              <Td>
                <div className="flex gap-1">
                  <Badge color={p.is_active ? "green" : "slate"}>{p.is_active ? "active" : "off"}</Badge>
                  {p.is_public && <Badge color="blue">public</Badge>}
                </div>
              </Td>
              <Td>
                <div className="flex flex-wrap gap-2 text-sm">
                  <button onClick={() => setEdit(p)} className="font-medium text-brand-600 hover:underline">
                    Edit
                  </button>
                  <button onClick={() => toggle(p)} className="font-medium text-amber-600 hover:underline">
                    {p.is_active ? "Disable" : "Enable"}
                  </button>
                  <button onClick={() => duplicate(p)} className="font-medium text-slate-500 hover:underline">
                    Duplicate
                  </button>
                  <button onClick={() => remove(p)} className="font-medium text-red-600 hover:underline">
                    Delete
                  </button>
                </div>
              </Td>
            </tr>
          ))}
        </Table>
      )}

      {(creating || edit) && (
        <PlanForm
          plan={edit}
          onClose={() => {
            setCreating(false);
            setEdit(null);
          }}
          onSaved={() => {
            setCreating(false);
            setEdit(null);
            load();
          }}
          onError={(t) => setMsg({ kind: "error", text: t })}
        />
      )}
    </div>
  );
}

function PlanForm({
  plan,
  onClose,
  onSaved,
  onError,
}: {
  plan: Plan | null;
  onClose: () => void;
  onSaved: () => void;
  onError: (t: string) => void;
}) {
  const [form, setForm] = useState({
    code: plan?.code || "",
    name: plan?.name || "",
    billing_cycle: plan?.billing_cycle || "monthly",
    base_price_rupees: plan ? plan.base_price / 100 : 0,
    included_seats: plan?.included_seats ?? 10,
    per_seat_price_rupees: plan ? plan.per_seat_price / 100 : 0,
    trial_days: plan?.trial_days ?? 14,
    is_public: plan?.is_public ?? true,
    is_active: plan?.is_active ?? true,
    modules: plan?.modules.map((m) => m.code) || ["attendance", "leave"],
  });
  const [busy, setBusy] = useState(false);

  function setF(k: string, v: any) {
    setForm((f) => ({ ...f, [k]: v }));
  }
  function toggleModule(code: string) {
    setForm((f) => ({
      ...f,
      modules: f.modules.includes(code) ? f.modules.filter((m) => m !== code) : [...f.modules, code],
    }));
  }

  async function save() {
    setBusy(true);
    try {
      const body = {
        code: form.code,
        name: form.name,
        billing_cycle: form.billing_cycle,
        base_price: Math.round(form.base_price_rupees * 100),
        included_seats: Number(form.included_seats),
        per_seat_price: Math.round(form.per_seat_price_rupees * 100),
        trial_days: Number(form.trial_days),
        is_public: form.is_public,
        is_active: form.is_active,
        modules: form.modules,
      };
      if (plan) await api(`/admin/plans/${plan.id}`, { method: "PUT", body });
      else await api("/admin/plans", { method: "POST", body });
      onSaved();
    } catch (err) {
      const e = err as ApiError;
      const d = e.details ? Object.values(e.details).flat().join(" ") : "";
      onError(`${e.message} ${d}`.trim());
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal open title={plan ? "Edit plan" : "New plan"} onClose={onClose}>
      <div className="space-y-3">
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="block">
            <span className="label">Code</span>
            <input className="input" value={form.code} onChange={(e) => setF("code", e.target.value)} disabled={!!plan} />
          </label>
          <label className="block">
            <span className="label">Name</span>
            <input className="input" value={form.name} onChange={(e) => setF("name", e.target.value)} />
          </label>
          <label className="block">
            <span className="label">Billing cycle</span>
            <select className="input" value={form.billing_cycle} onChange={(e) => setF("billing_cycle", e.target.value)}>
              <option value="monthly">Monthly</option>
              <option value="yearly">Yearly</option>
            </select>
          </label>
          <label className="block">
            <span className="label">Base price (₹)</span>
            <input type="number" className="input" value={form.base_price_rupees} onChange={(e) => setF("base_price_rupees", Number(e.target.value))} />
          </label>
          <label className="block">
            <span className="label">Included seats</span>
            <input type="number" className="input" value={form.included_seats} onChange={(e) => setF("included_seats", e.target.value)} />
          </label>
          <label className="block">
            <span className="label">Per-seat price (₹)</span>
            <input type="number" className="input" value={form.per_seat_price_rupees} onChange={(e) => setF("per_seat_price_rupees", Number(e.target.value))} />
          </label>
          <label className="block">
            <span className="label">Trial days</span>
            <input type="number" className="input" value={form.trial_days} onChange={(e) => setF("trial_days", e.target.value)} />
          </label>
        </div>
        <div>
          <span className="label">Modules</span>
          <div className="flex flex-wrap gap-2">
            {ALL_MODULES.map((m) => (
              <button
                type="button"
                key={m}
                onClick={() => toggleModule(m)}
                className={`rounded-lg px-3 py-1 text-xs font-semibold capitalize ${
                  form.modules.includes(m) ? "bg-brand-600 text-white" : "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                }`}
              >
                {m}
              </button>
            ))}
          </div>
        </div>
        <div className="flex gap-4">
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={form.is_public} onChange={(e) => setF("is_public", e.target.checked)} /> Public
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={form.is_active} onChange={(e) => setF("is_active", e.target.checked)} /> Active
          </label>
        </div>
        <div className="flex justify-end gap-2 pt-2">
          <button className="btn-ghost" onClick={onClose}>
            Cancel
          </button>
          <button className="btn-primary" onClick={save} disabled={busy}>
            {busy ? "Saving…" : "Save"}
          </button>
        </div>
      </div>
    </Modal>
  );
}
