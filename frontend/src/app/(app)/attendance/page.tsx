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
} from "@/components/ui";

type Emp = { uuid: string; full_name: string; employee_code: string };
type Record_ = {
  id: number;
  work_date: string;
  status: string;
  worked_minutes: number | null;
  overtime_minutes: number;
  source: string;
  employee?: { uuid: string; first_name: string; last_name: string | null };
};

const today = () => new Date().toISOString().slice(0, 10);

export default function AttendancePage() {
  const { can } = useAuth();
  const [employees, setEmployees] = useState<Emp[]>([]);
  const [records, setRecords] = useState<Record_[]>([]);
  const [loading, setLoading] = useState(true);
  const [msg, setMsg] = useState<{ kind: "success" | "error"; text: string } | null>(null);

  // mark form
  const [empUuid, setEmpUuid] = useState("");
  const [date, setDate] = useState(today());
  const [status, setStatus] = useState("present");

  const loadRecords = useCallback(async () => {
    setLoading(true);
    try {
      const r = await apiPaged<Record_[]>("/attendance?per_page=50");
      setRecords(r.data || []);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    apiPaged<Emp[]>("/employees?per_page=100")
      .then((r) => {
        setEmployees(r.data || []);
        if (r.data?.[0]) setEmpUuid(r.data[0].uuid);
      })
      .catch(() => {});
    loadRecords();
  }, [loadRecords]);

  async function mark() {
    setMsg(null);
    try {
      await api("/attendance/mark", {
        method: "POST",
        body: { employee: empUuid, work_date: date, status },
      });
      setMsg({ kind: "success", text: "Attendance recorded." });
      loadRecords();
    } catch (err) {
      setMsg({ kind: "error", text: (err as ApiError).message });
    }
  }

  async function selfAction(kind: "check-in" | "check-out" | "break-in" | "break-out") {
    setMsg(null);
    try {
      await api(`/attendance/${kind}`, { method: "POST" });
      const verb = { "check-in": "checked in", "check-out": "checked out", "break-in": "started a break", "break-out": "ended your break" }[kind];
      setMsg({ kind: "success", text: `You have ${verb}.` });
      loadRecords();
    } catch (err) {
      const e = err as ApiError;
      setMsg({
        kind: "error",
        text:
          e.status === 404
            ? "Your login isn't linked to an employee profile, so self check-in isn't available."
            : e.message,
      });
    }
  }

  return (
    <div>
      <PageHeader title="Attendance" subtitle="Mark attendance and track working time." />

      {msg && (
        <div className="mb-4">
          <Alert kind={msg.kind}>{msg.text}</Alert>
        </div>
      )}

      <div className="mb-6 grid gap-4 lg:grid-cols-2">
        <Card>
          <h3 className="mb-3 font-semibold text-slate-800">My attendance</h3>
          <p className="mb-3 text-sm text-slate-500">
            Check in when you start and out when you finish. Working hours and overtime are computed
            automatically.
          </p>
          <div className="flex flex-wrap gap-2">
            <button className="btn-primary" onClick={() => selfAction("check-in")}>
              Check in
            </button>
            <button className="btn-ghost" onClick={() => selfAction("check-out")}>
              Check out
            </button>
            <button className="btn-ghost" onClick={() => selfAction("break-in")}>
              Start break
            </button>
            <button className="btn-ghost" onClick={() => selfAction("break-out")}>
              End break
            </button>
          </div>
        </Card>

        {can("attendance.mark") && (
          <Card>
            <h3 className="mb-3 font-semibold text-slate-800">Mark attendance (HR)</h3>
            <div className="grid gap-3 sm:grid-cols-3">
              <label className="block sm:col-span-3">
                <span className="label">Employee</span>
                <select className="input" value={empUuid} onChange={(e) => setEmpUuid(e.target.value)}>
                  {employees.map((e) => (
                    <option key={e.uuid} value={e.uuid}>
                      {e.full_name} ({e.employee_code})
                    </option>
                  ))}
                </select>
              </label>
              <label className="block">
                <span className="label">Date</span>
                <input type="date" className="input" value={date} onChange={(e) => setDate(e.target.value)} />
              </label>
              <label className="block">
                <span className="label">Status</span>
                <select className="input" value={status} onChange={(e) => setStatus(e.target.value)}>
                  <option value="present">Present</option>
                  <option value="absent">Absent</option>
                  <option value="half_day">Half day</option>
                  <option value="leave">Leave</option>
                  <option value="holiday">Holiday</option>
                </select>
              </label>
              <div className="flex items-end">
                <button className="btn-primary w-full" onClick={mark} disabled={!empUuid}>
                  Save
                </button>
              </div>
            </div>
          </Card>
        )}
      </div>

      <h3 className="mb-3 font-semibold text-slate-800">Recent records</h3>
      {loading ? (
        <Spinner />
      ) : records.length === 0 ? (
        <Empty message="No attendance records yet." />
      ) : (
        <Table head={["Date", "Employee", "Status", "Worked", "Overtime", "Source"]}>
          {records.map((r) => (
            <tr key={r.id} className="hover:bg-slate-50">
              <Td>{r.work_date}</Td>
              <Td className="font-medium text-slate-800">
                {r.employee ? `${r.employee.first_name} ${r.employee.last_name || ""}` : "—"}
              </Td>
              <Td>
                <Badge color={statusColor(r.status)}>{r.status}</Badge>
              </Td>
              <Td>{r.worked_minutes != null ? `${Math.floor(r.worked_minutes / 60)}h ${r.worked_minutes % 60}m` : "—"}</Td>
              <Td>{r.overtime_minutes ? `${Math.floor(r.overtime_minutes / 60)}h ${r.overtime_minutes % 60}m` : "—"}</Td>
              <Td className="text-slate-500">{r.source}</Td>
            </tr>
          ))}
        </Table>
      )}

      <Corrections />
    </div>
  );
}

type Correction = {
  uuid: string;
  work_date: string;
  requested_status: string | null;
  reason: string;
  status: string;
  review_note: string | null;
  employee?: { first_name: string; last_name: string | null };
};

function Corrections() {
  const { can } = useAuth();
  const [rows, setRows] = useState<Correction[]>([]);
  const [loading, setLoading] = useState(true);
  const [msg, setMsg] = useState<{ kind: "success" | "error"; text: string } | null>(null);
  const [form, setForm] = useState({ work_date: today(), requested_status: "present", reason: "" });

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const r = await apiPaged<Correction[]>("/attendance/corrections?per_page=50");
      setRows(r.data || []);
    } catch {
      /* module may be gated */
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function raise() {
    setMsg(null);
    try {
      await api("/attendance/corrections", { method: "POST", body: form });
      setMsg({ kind: "success", text: "Correction request submitted." });
      setForm({ work_date: today(), requested_status: "present", reason: "" });
      load();
    } catch (err) {
      setMsg({ kind: "error", text: (err as ApiError).message });
    }
  }

  async function review(c: Correction, action: "approve" | "reject") {
    setMsg(null);
    try {
      await api(`/attendance/corrections/${c.uuid}/${action}`, { method: "POST", body: {} });
      setMsg({ kind: "success", text: `Request ${action}d.` });
      load();
    } catch (err) {
      setMsg({ kind: "error", text: (err as ApiError).message });
    }
  }

  return (
    <div className="mt-8">
      <h3 className="mb-3 font-semibold text-slate-800 dark:text-slate-200">Attendance corrections</h3>
      {msg && (
        <div className="mb-4">
          <Alert kind={msg.kind}>{msg.text}</Alert>
        </div>
      )}

      <Card className="mb-4">
        <h4 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Request a correction</h4>
        <div className="grid gap-3 sm:grid-cols-4">
          <label className="block">
            <span className="label">Date</span>
            <input type="date" className="input" value={form.work_date} onChange={(e) => setForm({ ...form, work_date: e.target.value })} />
          </label>
          <label className="block">
            <span className="label">Desired status</span>
            <select className="input" value={form.requested_status} onChange={(e) => setForm({ ...form, requested_status: e.target.value })}>
              <option value="present">Present</option>
              <option value="absent">Absent</option>
              <option value="half_day">Half day</option>
              <option value="leave">Leave</option>
              <option value="holiday">Holiday</option>
            </select>
          </label>
          <label className="block sm:col-span-2">
            <span className="label">Reason</span>
            <input className="input" value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} placeholder="Forgot to check in…" />
          </label>
        </div>
        <button className="btn-primary mt-3" onClick={raise} disabled={!form.reason}>
          Submit request
        </button>
      </Card>

      {loading ? (
        <Spinner />
      ) : rows.length === 0 ? (
        <Empty message="No correction requests." />
      ) : (
        <Table head={["Date", "Employee", "Wants", "Reason", "Status", ""]}>
          {rows.map((c) => (
            <tr key={c.uuid}>
              <Td>{c.work_date?.slice(0, 10)}</Td>
              <Td className="font-medium text-slate-800 dark:text-slate-200">
                {c.employee ? `${c.employee.first_name} ${c.employee.last_name || ""}` : "—"}
              </Td>
              <Td>{c.requested_status || "—"}</Td>
              <Td className="text-slate-500">{c.reason}</Td>
              <Td>
                <Badge color={statusColor(c.status)}>{c.status}</Badge>
              </Td>
              <Td>
                {c.status === "pending" && can("attendance.correction.approve") && (
                  <div className="flex gap-3">
                    <button className="text-sm font-medium text-emerald-600 hover:underline" onClick={() => review(c, "approve")}>
                      Approve
                    </button>
                    <button className="text-sm font-medium text-red-600 hover:underline" onClick={() => review(c, "reject")}>
                      Reject
                    </button>
                  </div>
                )}
              </Td>
            </tr>
          ))}
        </Table>
      )}
    </div>
  );
}
