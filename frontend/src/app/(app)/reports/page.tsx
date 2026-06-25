"use client";

import { useCallback, useEffect, useState } from "react";
import { api, downloadFile, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { PageHeader, Card, Table, Td, Empty, Spinner, Alert } from "@/components/ui";

type Report = "employees" | "attendance" | "leave";

const todayMonthStart = () => {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10);
};
const todayMonthEnd = () => {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10);
};

export default function ReportsPage() {
  const { can, hasModule } = useAuth();

  const available: { key: Report; label: string; show: boolean }[] = [
    { key: "employees", label: "Employee directory", show: can("employee.profile.view") },
    { key: "attendance", label: "Attendance summary", show: hasModule("attendance") && can("attendance.report.view") },
    { key: "leave", label: "Leave balances", show: hasModule("leave") && can("leave.view") },
  ];
  const first = available.find((a) => a.show)?.key || "employees";

  const [report, setReport] = useState<Report>(first);
  const [from, setFrom] = useState(todayMonthStart());
  const [to, setTo] = useState(todayMonthEnd());
  const [year, setYear] = useState(new Date().getFullYear());
  const [rows, setRows] = useState<any[]>([]);
  const [cols, setCols] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const path = useCallback(() => {
    if (report === "attendance") return `/reports/attendance-summary?from=${from}&to=${to}`;
    if (report === "leave") return `/reports/leave-balances?year=${year}`;
    return "/reports/employees";
  }, [report, from, to, year]);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api<any[]>(path());
      setRows(data || []);
      setCols(data && data.length ? Object.keys(data[0]) : []);
    } catch (err) {
      setError((err as ApiError).message);
      setRows([]);
      setCols([]);
    } finally {
      setLoading(false);
    }
  }, [path]);

  useEffect(() => {
    load();
  }, [load]);

  async function exportCsv() {
    const sep = path().includes("?") ? "&" : "?";
    try {
      await downloadFile(`${path()}${sep}format=csv`, `${report}-report.csv`);
    } catch (err) {
      setError((err as ApiError).message);
    }
  }

  return (
    <div>
      <PageHeader
        title="Reports"
        subtitle="View and export operational reports."
        actions={
          rows.length > 0 && (
            <button className="btn-ghost" onClick={exportCsv}>
              ⬇ Download CSV
            </button>
          )
        }
      />

      <div className="mb-4 flex flex-wrap gap-2">
        {available
          .filter((a) => a.show)
          .map((a) => (
            <button
              key={a.key}
              className={report === a.key ? "btn-primary" : "btn-ghost"}
              onClick={() => setReport(a.key)}
            >
              {a.label}
            </button>
          ))}
      </div>

      <Card className="mb-4">
        <div className="flex flex-wrap items-end gap-3">
          {report === "attendance" && (
            <>
              <label className="block">
                <span className="label">From</span>
                <input type="date" className="input" value={from} onChange={(e) => setFrom(e.target.value)} />
              </label>
              <label className="block">
                <span className="label">To</span>
                <input type="date" className="input" value={to} onChange={(e) => setTo(e.target.value)} />
              </label>
            </>
          )}
          {report === "leave" && (
            <label className="block">
              <span className="label">Year</span>
              <input type="number" className="input w-28" value={year} onChange={(e) => setYear(Number(e.target.value))} />
            </label>
          )}
          <button className="btn-primary" onClick={load}>
            Run report
          </button>
        </div>
      </Card>

      {error && (
        <div className="mb-4">
          <Alert kind="error">{error}</Alert>
        </div>
      )}

      {loading ? (
        <Spinner />
      ) : rows.length === 0 ? (
        <Empty message="No data for the selected report." />
      ) : (
        <Table head={cols.map((c) => c.replace(/_/g, " "))}>
          {rows.map((r, i) => (
            <tr key={i} className="hover:bg-slate-50">
              {cols.map((c) => (
                <Td key={c}>{String(r[c] ?? "—")}</Td>
              ))}
            </tr>
          ))}
        </Table>
      )}
    </div>
  );
}
