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

  async function selfAction(kind: "check-in" | "check-out") {
    setMsg(null);
    try {
      await api(`/attendance/${kind}`, { method: "POST" });
      setMsg({ kind: "success", text: `You have ${kind === "check-in" ? "checked in" : "checked out"}.` });
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
          <div className="flex gap-2">
            <button className="btn-primary" onClick={() => selfAction("check-in")}>
              Check in
            </button>
            <button className="btn-ghost" onClick={() => selfAction("check-out")}>
              Check out
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
    </div>
  );
}
