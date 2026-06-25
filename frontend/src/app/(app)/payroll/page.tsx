"use client";

import { useCallback, useEffect, useState } from "react";
import { api, money, ApiError, apiPaged } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import {
  PageHeader,
  Card,
  Table,
  Td,
  Badge,
  statusColor,
  Empty,
  Spinner,
  Alert,
  Modal,
} from "@/components/ui";

type Component = { id: number; code: string; name: string; type: string; is_taxable: boolean };
type Emp = { uuid: string; full_name: string; employee_code: string; id?: number };
type Run = {
  uuid: string;
  period_year: number;
  period_month: number;
  status: string;
  total_gross: number | null;
  total_deductions: number | null;
  total_net: number | null;
};
type Payslip = {
  uuid: string;
  gross: number;
  deductions: number;
  net: number;
  lop_days: number;
  employee?: { first_name: string; last_name: string | null };
};

const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

export default function PayrollPage() {
  const [tab, setTab] = useState<"runs" | "components" | "structures">("runs");

  return (
    <div>
      <PageHeader title="Payroll" subtitle="Salary structures and monthly payroll runs." />
      <div className="mb-4 flex gap-2">
        <button className={tab === "runs" ? "btn-primary" : "btn-ghost"} onClick={() => setTab("runs")}>
          Runs
        </button>
        <button className={tab === "components" ? "btn-primary" : "btn-ghost"} onClick={() => setTab("components")}>
          Components
        </button>
        <button className={tab === "structures" ? "btn-primary" : "btn-ghost"} onClick={() => setTab("structures")}>
          Salary structures
        </button>
      </div>
      {tab === "runs" && <Runs />}
      {tab === "components" && <Components />}
      {tab === "structures" && <Structures />}
    </div>
  );
}

function Runs() {
  const [runs, setRuns] = useState<Run[]>([]);
  const [loading, setLoading] = useState(true);
  const [msg, setMsg] = useState<{ kind: "success" | "error"; text: string } | null>(null);
  const now = new Date();
  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState(now.getMonth() + 1);
  const [payslips, setPayslips] = useState<Record<string, Payslip[]>>({});

  const load = useCallback(async () => {
    setLoading(true);
    try {
      // No list endpoint for runs; we track created ones in this session by re-creating idempotently.
      // Show payslips per run instead. Start empty and populate as actions occur.
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function createRun() {
    setMsg(null);
    try {
      const run = await api<Run>("/payroll/runs", { method: "POST", body: { year, month } });
      setRuns((r) => [run, ...r.filter((x) => x.uuid !== run.uuid)]);
      setMsg({ kind: "success", text: `Run for ${MONTHS[month - 1]} ${year} created (draft).` });
    } catch (err) {
      setMsg({ kind: "error", text: (err as ApiError).message });
    }
  }

  async function process(run: Run) {
    setMsg(null);
    try {
      const updated = await api<Run>(`/payroll/runs/${run.uuid}/process`, { method: "POST", body: {} });
      setRuns((r) => r.map((x) => (x.uuid === run.uuid ? updated : x)));
      const slips = await api<Payslip[]>(`/payroll/runs/${run.uuid}/payslips`);
      setPayslips((p) => ({ ...p, [run.uuid]: slips }));
      setMsg({ kind: "success", text: "Payroll processed — payslips generated." });
    } catch (err) {
      setMsg({ kind: "error", text: (err as ApiError).message });
    }
  }

  async function publish(run: Run) {
    setMsg(null);
    try {
      const updated = await api<Run>(`/payroll/runs/${run.uuid}/publish`, { method: "POST", body: {} });
      setRuns((r) => r.map((x) => (x.uuid === run.uuid ? updated : x)));
      setMsg({ kind: "success", text: "Run published & locked." });
    } catch (err) {
      setMsg({ kind: "error", text: (err as ApiError).message });
    }
  }

  return (
    <div>
      {msg && (
        <div className="mb-4">
          <Alert kind={msg.kind}>{msg.text}</Alert>
        </div>
      )}
      <Card className="mb-6">
        <h3 className="mb-3 font-semibold text-slate-800">Start a payroll run</h3>
        <div className="flex flex-wrap items-end gap-3">
          <label className="block">
            <span className="label">Year</span>
            <input type="number" className="input w-28" value={year} onChange={(e) => setYear(Number(e.target.value))} />
          </label>
          <label className="block">
            <span className="label">Month</span>
            <select className="input w-36" value={month} onChange={(e) => setMonth(Number(e.target.value))}>
              {MONTHS.map((m, i) => (
                <option key={m} value={i + 1}>
                  {m}
                </option>
              ))}
            </select>
          </label>
          <button className="btn-primary" onClick={createRun}>
            Create run
          </button>
        </div>
        <p className="mt-3 text-xs text-slate-400">
          Create the run, then Process it to compute payslips for every active employee with a salary
          structure (loss-of-pay is prorated from attendance). Publish locks the run.
        </p>
      </Card>

      {loading ? (
        <Spinner />
      ) : runs.length === 0 ? (
        <Empty message="No runs in this session yet. Create one above." />
      ) : (
        <div className="space-y-4">
          {runs.map((run) => (
            <Card key={run.uuid}>
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <div className="font-semibold text-slate-800">
                    {MONTHS[run.period_month - 1]} {run.period_year}
                  </div>
                  <div className="mt-1 flex items-center gap-3 text-sm text-slate-500">
                    <Badge color={statusColor(run.status)}>{run.status}</Badge>
                    {run.total_net != null && <span>Net: {money(run.total_net)}</span>}
                  </div>
                </div>
                <div className="flex gap-2">
                  {run.status !== "locked" && (
                    <button className="btn-ghost" onClick={() => process(run)}>
                      Process
                    </button>
                  )}
                  {run.status === "completed" && (
                    <button className="btn-primary" onClick={() => publish(run)}>
                      Publish
                    </button>
                  )}
                </div>
              </div>
              {payslips[run.uuid] && payslips[run.uuid].length > 0 && (
                <div className="mt-4">
                  <Table head={["Employee", "Gross", "Deductions", "LOP days", "Net"]}>
                    {payslips[run.uuid].map((s) => (
                      <tr key={s.uuid}>
                        <Td className="font-medium text-slate-800">
                          {s.employee ? `${s.employee.first_name} ${s.employee.last_name || ""}` : "—"}
                        </Td>
                        <Td>{money(s.gross)}</Td>
                        <Td>{money(s.deductions)}</Td>
                        <Td>{s.lop_days}</Td>
                        <Td className="font-semibold">{money(s.net)}</Td>
                      </tr>
                    ))}
                  </Table>
                </div>
              )}
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}

function Components() {
  const [rows, setRows] = useState<Component[]>([]);
  const [loading, setLoading] = useState(true);
  const [show, setShow] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      setRows((await api<Component[]>("/payroll/components")) || []);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <div>
      <div className="mb-4 flex justify-end">
        <button className="btn-primary" onClick={() => setShow(true)}>
          + Component
        </button>
      </div>
      {loading ? (
        <Spinner />
      ) : rows.length === 0 ? (
        <Empty message="No salary components. Add earnings (Basic, HRA) and deductions (PF, Tax)." />
      ) : (
        <Table head={["Code", "Name", "Type", "Taxable"]}>
          {rows.map((c) => (
            <tr key={c.id}>
              <Td className="font-mono text-xs">{c.code}</Td>
              <Td className="font-medium text-slate-800">{c.name}</Td>
              <Td>
                <Badge color={c.type === "earning" ? "green" : "amber"}>{c.type}</Badge>
              </Td>
              <Td>{c.is_taxable ? "Yes" : "No"}</Td>
            </tr>
          ))}
        </Table>
      )}
      {show && (
        <ComponentForm
          onClose={() => setShow(false)}
          onSaved={() => {
            setShow(false);
            load();
          }}
        />
      )}
    </div>
  );
}

function ComponentForm({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
  const [form, setForm] = useState({ code: "", name: "", type: "earning", is_taxable: true });
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  async function save() {
    setError(null);
    setBusy(true);
    try {
      await api("/payroll/components", { method: "POST", body: form });
      onSaved();
    } catch (err) {
      setError((err as ApiError).message);
    } finally {
      setBusy(false);
    }
  }
  return (
    <Modal open title="New salary component" onClose={onClose}>
      <div className="space-y-3">
        {error && <Alert kind="error">{error}</Alert>}
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="block">
            <span className="label">Code</span>
            <input className="input" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} placeholder="BASIC" />
          </label>
          <label className="block">
            <span className="label">Name</span>
            <input className="input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Basic" />
          </label>
          <label className="block">
            <span className="label">Type</span>
            <select className="input" value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
              <option value="earning">Earning</option>
              <option value="deduction">Deduction</option>
            </select>
          </label>
          <label className="flex items-center gap-2 pt-7">
            <input type="checkbox" checked={form.is_taxable} onChange={(e) => setForm({ ...form, is_taxable: e.target.checked })} />
            <span className="text-sm text-slate-700">Taxable</span>
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

function Structures() {
  const [employees, setEmployees] = useState<Emp[]>([]);
  const [components, setComponents] = useState<Component[]>([]);
  const [empUuid, setEmpUuid] = useState("");
  const [lines, setLines] = useState<{ component_code: string; amount: number }[]>([]);
  const [loading, setLoading] = useState(true);
  const [msg, setMsg] = useState<{ kind: "success" | "error"; text: string } | null>(null);

  useEffect(() => {
    Promise.all([
      apiPaged<Emp[]>("/employees?per_page=100"),
      api<Component[]>("/payroll/components"),
    ])
      .then(([e, c]) => {
        setEmployees(e.data || []);
        setComponents(c || []);
        if (e.data?.[0]) setEmpUuid(e.data[0].uuid);
      })
      .finally(() => setLoading(false));
  }, []);

  function addLine() {
    if (components[0]) setLines((l) => [...l, { component_code: components[0].code, amount: 0 }]);
  }

  async function save() {
    setMsg(null);
    try {
      const body = {
        lines: lines.map((l) => ({ component_code: l.component_code, amount: Math.round(l.amount * 100) })),
      };
      await api(`/payroll/structures/${empUuid}`, { method: "PUT", body });
      setMsg({ kind: "success", text: "Salary structure saved." });
    } catch (err) {
      const e = err as ApiError;
      const d = e.details ? Object.values(e.details).flat().join(" ") : "";
      setMsg({ kind: "error", text: `${e.message} ${d}`.trim() });
    }
  }

  if (loading) return <Spinner />;
  if (components.length === 0)
    return <Empty message="Add salary components first (Components tab), then build structures here." />;

  return (
    <Card>
      {msg && (
        <div className="mb-4">
          <Alert kind={msg.kind}>{msg.text}</Alert>
        </div>
      )}
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="block">
          <span className="label">Employee</span>
          <select className="input" value={empUuid} onChange={(e) => setEmpUuid(e.target.value)}>
            {employees.map((e) => (
              <option key={e.uuid} value={e.uuid}>
                {e.full_name} ({e.employee_code})
              </option>
            ))}
          </select>
        </label>
      </div>

      <div className="mt-4 space-y-2">
        {lines.map((l, i) => (
          <div key={i} className="flex items-center gap-2">
            <select
              className="input flex-1"
              value={l.component_code}
              onChange={(e) => {
                const v = e.target.value;
                setLines((ls) => ls.map((x, idx) => (idx === i ? { ...x, component_code: v } : x)));
              }}
            >
              {components.map((c) => (
                <option key={c.code} value={c.code}>
                  {c.name} ({c.type})
                </option>
              ))}
            </select>
            <input
              type="number"
              className="input w-40"
              placeholder="Amount (₹)"
              value={l.amount}
              onChange={(e) => {
                const v = Number(e.target.value);
                setLines((ls) => ls.map((x, idx) => (idx === i ? { ...x, amount: v } : x)));
              }}
            />
            <button className="btn-ghost" onClick={() => setLines((ls) => ls.filter((_, idx) => idx !== i))}>
              ✕
            </button>
          </div>
        ))}
      </div>

      <div className="mt-4 flex gap-2">
        <button className="btn-ghost" onClick={addLine}>
          + Add line
        </button>
        <button className="btn-primary" onClick={save} disabled={lines.length === 0 || !empUuid}>
          Save structure
        </button>
      </div>
      <p className="mt-3 text-xs text-slate-400">Amounts are monthly, entered in rupees.</p>
    </Card>
  );
}
