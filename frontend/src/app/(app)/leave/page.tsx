"use client";

import { useCallback, useEffect, useState } from "react";
import { api, apiPaged, ApiError } from "@/lib/api";
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

type LeaveType = { id: number; code: string; name: string; is_paid: boolean; annual_quota: number };
type Emp = { uuid: string; full_name: string; employee_code: string };
type Req = {
  uuid: string;
  start_date: string;
  end_date: string;
  days: number;
  status: string;
  reason: string | null;
  leave_type?: { code: string; name: string };
  employee?: { uuid: string; first_name: string; last_name: string | null };
};

const today = () => new Date().toISOString().slice(0, 10);

export default function LeavePage() {
  const { can } = useAuth();
  const [tab, setTab] = useState<"requests" | "types">("requests");
  const [types, setTypes] = useState<LeaveType[]>([]);
  const [employees, setEmployees] = useState<Emp[]>([]);
  const [requests, setRequests] = useState<Req[]>([]);
  const [loading, setLoading] = useState(true);
  const [showReq, setShowReq] = useState(false);
  const [showType, setShowType] = useState(false);
  const [msg, setMsg] = useState<{ kind: "success" | "error"; text: string } | null>(null);

  const loadAll = useCallback(async () => {
    setLoading(true);
    try {
      const [t, r] = await Promise.all([
        api<LeaveType[]>("/leave/types"),
        apiPaged<Req[]>("/leave/requests?per_page=50"),
      ]);
      setTypes(t || []);
      setRequests(r.data || []);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadAll();
    apiPaged<Emp[]>("/employees?per_page=100").then((r) => setEmployees(r.data || [])).catch(() => {});
  }, [loadAll]);

  async function decide(uuid: string, action: "approve" | "reject") {
    setMsg(null);
    try {
      await api(`/leave/requests/${uuid}/${action}`, { method: "POST", body: {} });
      setMsg({ kind: "success", text: `Request ${action}d.` });
      loadAll();
    } catch (err) {
      setMsg({ kind: "error", text: (err as ApiError).message });
    }
  }

  return (
    <div>
      <PageHeader
        title="Leave"
        subtitle="Requests, balances and policies."
        actions={
          <>
            {can("leave.request.create") && (
              <button className="btn-primary" onClick={() => setShowReq(true)} disabled={types.length === 0}>
                + Request leave
              </button>
            )}
            {can("leave.type.manage") && (
              <button className="btn-ghost" onClick={() => setShowType(true)}>
                + Leave type
              </button>
            )}
          </>
        }
      />

      {msg && (
        <div className="mb-4">
          <Alert kind={msg.kind}>{msg.text}</Alert>
        </div>
      )}

      <div className="mb-4 flex gap-2">
        <button className={tab === "requests" ? "btn-primary" : "btn-ghost"} onClick={() => setTab("requests")}>
          Requests
        </button>
        <button className={tab === "types" ? "btn-primary" : "btn-ghost"} onClick={() => setTab("types")}>
          Leave types
        </button>
      </div>

      {loading ? (
        <Spinner />
      ) : tab === "requests" ? (
        requests.length === 0 ? (
          <Empty message="No leave requests yet." />
        ) : (
          <Table head={["Employee", "Type", "From", "To", "Days", "Status", ""]}>
            {requests.map((r) => (
              <tr key={r.uuid} className="hover:bg-slate-50">
                <Td className="font-medium text-slate-800">
                  {r.employee ? `${r.employee.first_name} ${r.employee.last_name || ""}` : "—"}
                </Td>
                <Td>{r.leave_type?.name || "—"}</Td>
                <Td>{r.start_date}</Td>
                <Td>{r.end_date}</Td>
                <Td>{r.days}</Td>
                <Td>
                  <Badge color={statusColor(r.status)}>{r.status}</Badge>
                </Td>
                <Td>
                  {r.status === "pending" && can("leave.request.approve") && (
                    <div className="flex gap-2">
                      <button onClick={() => decide(r.uuid, "approve")} className="text-sm font-medium text-emerald-600 hover:underline">
                        Approve
                      </button>
                      <button onClick={() => decide(r.uuid, "reject")} className="text-sm font-medium text-red-600 hover:underline">
                        Reject
                      </button>
                    </div>
                  )}
                </Td>
              </tr>
            ))}
          </Table>
        )
      ) : types.length === 0 ? (
        <Empty message="No leave types defined. Create one to start accepting requests." />
      ) : (
        <Table head={["Code", "Name", "Paid", "Annual quota"]}>
          {types.map((t) => (
            <tr key={t.id} className="hover:bg-slate-50">
              <Td className="font-mono text-xs">{t.code}</Td>
              <Td className="font-medium text-slate-800">{t.name}</Td>
              <Td>{t.is_paid ? <Badge color="green">Paid</Badge> : <Badge color="slate">Unpaid</Badge>}</Td>
              <Td>{t.annual_quota} days</Td>
            </tr>
          ))}
        </Table>
      )}

      {showReq && (
        <RequestForm
          employees={employees}
          types={types}
          onClose={() => setShowReq(false)}
          onSaved={() => {
            setShowReq(false);
            loadAll();
          }}
        />
      )}
      {showType && (
        <TypeForm
          onClose={() => setShowType(false)}
          onSaved={() => {
            setShowType(false);
            loadAll();
          }}
        />
      )}
    </div>
  );
}

function RequestForm({
  employees,
  types,
  onClose,
  onSaved,
}: {
  employees: Emp[];
  types: LeaveType[];
  onClose: () => void;
  onSaved: () => void;
}) {
  const [form, setForm] = useState({
    employee: employees[0]?.uuid || "",
    leave_type: types[0]?.code || "",
    start_date: today(),
    end_date: today(),
    reason: "",
  });
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const set = (k: string, v: string) => setForm((f) => ({ ...f, [k]: v }));

  async function save() {
    setError(null);
    setBusy(true);
    try {
      const body: any = { ...form };
      if (!body.employee) delete body.employee;
      if (!body.reason) delete body.reason;
      await api("/leave/requests", { method: "POST", body });
      onSaved();
    } catch (err) {
      const e = err as ApiError;
      const d = e.details ? Object.values(e.details).flat().join(" ") : "";
      setError(`${e.message} ${d}`.trim());
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal open title="Request leave" onClose={onClose}>
      <div className="space-y-3">
        {error && <Alert kind="error">{error}</Alert>}
        <label className="block">
          <span className="label">Employee</span>
          <select className="input" value={form.employee} onChange={(e) => set("employee", e.target.value)}>
            {employees.map((e) => (
              <option key={e.uuid} value={e.uuid}>
                {e.full_name} ({e.employee_code})
              </option>
            ))}
          </select>
        </label>
        <label className="block">
          <span className="label">Leave type</span>
          <select className="input" value={form.leave_type} onChange={(e) => set("leave_type", e.target.value)}>
            {types.map((t) => (
              <option key={t.code} value={t.code}>
                {t.name}
              </option>
            ))}
          </select>
        </label>
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="block">
            <span className="label">From</span>
            <input type="date" className="input" value={form.start_date} onChange={(e) => set("start_date", e.target.value)} />
          </label>
          <label className="block">
            <span className="label">To</span>
            <input type="date" className="input" value={form.end_date} onChange={(e) => set("end_date", e.target.value)} />
          </label>
        </div>
        <label className="block">
          <span className="label">Reason</span>
          <textarea className="input" rows={2} value={form.reason} onChange={(e) => set("reason", e.target.value)} />
        </label>
        <div className="flex justify-end gap-2 pt-2">
          <button className="btn-ghost" onClick={onClose}>
            Cancel
          </button>
          <button className="btn-primary" onClick={save} disabled={busy}>
            {busy ? "Submitting…" : "Submit"}
          </button>
        </div>
      </div>
    </Modal>
  );
}

function TypeForm({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
  const [form, setForm] = useState({ code: "", name: "", is_paid: true, annual_quota: 12 });
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function save() {
    setError(null);
    setBusy(true);
    try {
      await api("/leave/types", { method: "POST", body: form });
      onSaved();
    } catch (err) {
      setError((err as ApiError).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal open title="New leave type" onClose={onClose}>
      <div className="space-y-3">
        {error && <Alert kind="error">{error}</Alert>}
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="block">
            <span className="label">Code</span>
            <input className="input" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} placeholder="casual" />
          </label>
          <label className="block">
            <span className="label">Name</span>
            <input className="input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Casual Leave" />
          </label>
          <label className="block">
            <span className="label">Annual quota (days)</span>
            <input type="number" className="input" value={form.annual_quota} onChange={(e) => setForm({ ...form, annual_quota: Number(e.target.value) })} />
          </label>
          <label className="flex items-center gap-2 pt-7">
            <input type="checkbox" checked={form.is_paid} onChange={(e) => setForm({ ...form, is_paid: e.target.checked })} />
            <span className="text-sm text-slate-700">Paid leave</span>
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
