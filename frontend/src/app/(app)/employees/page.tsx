"use client";

import { useCallback, useEffect, useState } from "react";
import { api, apiPaged, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import {
  PageHeader,
  Table,
  Td,
  Badge,
  statusColor,
  Empty,
  Spinner,
  Modal,
  Alert,
} from "@/components/ui";

type Employee = {
  uuid: string;
  employee_code: string;
  full_name: string;
  first_name: string;
  last_name: string | null;
  email: string | null;
  phone: string | null;
  status: string;
};

export default function EmployeesPage() {
  const { can } = useAuth();
  const [rows, setRows] = useState<Employee[]>([]);
  const [meta, setMeta] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [modal, setModal] = useState<null | Employee>(null);
  const [showCreate, setShowCreate] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const r = await apiPaged<Employee[]>(`/employees?per_page=50${search ? `&search=${encodeURIComponent(search)}` : ""}`);
      setRows(r.data || []);
      setMeta(r.meta);
    } finally {
      setLoading(false);
    }
  }, [search]);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <div>
      <PageHeader
        title="Employees"
        subtitle={meta ? `${meta.total} employee(s)` : undefined}
        actions={
          can("employee.profile.create") && (
            <button className="btn-primary" onClick={() => setShowCreate(true)}>
              + Add employee
            </button>
          )
        }
      />

      <div className="mb-4 flex gap-2">
        <input
          className="input max-w-xs"
          placeholder="Search name or code…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
      </div>

      {loading ? (
        <Spinner />
      ) : rows.length === 0 ? (
        <Empty message="No employees yet. Add your first employee to get started." />
      ) : (
        <Table head={["Code", "Name", "Email", "Status", ""]}>
          {rows.map((e) => (
            <tr key={e.uuid} className="hover:bg-slate-50">
              <Td className="font-mono text-xs">{e.employee_code}</Td>
              <Td className="font-medium text-slate-800">{e.full_name}</Td>
              <Td className="text-slate-500">{e.email || "—"}</Td>
              <Td>
                <Badge color={statusColor(e.status)}>{e.status}</Badge>
              </Td>
              <Td>
                {can("employee.profile.update") && (
                  <button onClick={() => setModal(e)} className="text-sm font-medium text-brand-600 hover:underline">
                    Edit
                  </button>
                )}
              </Td>
            </tr>
          ))}
        </Table>
      )}

      {showCreate && (
        <EmployeeForm
          onClose={() => setShowCreate(false)}
          onSaved={() => {
            setShowCreate(false);
            load();
          }}
        />
      )}
      {modal && (
        <EmployeeForm
          employee={modal}
          onClose={() => setModal(null)}
          onSaved={() => {
            setModal(null);
            load();
          }}
        />
      )}
    </div>
  );
}

function EmployeeForm({
  employee,
  onClose,
  onSaved,
}: {
  employee?: Employee;
  onClose: () => void;
  onSaved: () => void;
}) {
  const editing = !!employee;
  const [form, setForm] = useState({
    employee_code: employee?.employee_code || "",
    first_name: employee?.first_name || "",
    last_name: employee?.last_name || "",
    email: employee?.email || "",
    phone: employee?.phone || "",
    status: employee?.status || "active",
  });
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  function set(k: string, v: string) {
    setForm((f) => ({ ...f, [k]: v }));
  }

  async function save() {
    setError(null);
    setBusy(true);
    try {
      const body: any = { ...form };
      if (!body.email) delete body.email;
      if (!body.phone) delete body.phone;
      if (!body.last_name) delete body.last_name;
      if (editing) {
        await api(`/employees/${employee!.uuid}`, { method: "PATCH", body });
      } else {
        await api("/employees", { method: "POST", body });
      }
      onSaved();
    } catch (err) {
      if (err instanceof ApiError && err.code === "SEAT_LIMIT_REACHED") {
        setError("Seat limit reached for your plan. Upgrade or buy more seats to add employees.");
      } else {
        const e = err as ApiError;
        const d = e.details ? Object.values(e.details).flat().join(" ") : "";
        setError(`${e.message} ${d}`.trim());
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal open title={editing ? "Edit employee" : "Add employee"} onClose={onClose}>
      <div className="space-y-3">
        {error && <Alert kind="error">{error}</Alert>}
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="block">
            <span className="label">Employee code</span>
            <input className="input" value={form.employee_code} onChange={(e) => set("employee_code", e.target.value)} />
          </label>
          <label className="block">
            <span className="label">Status</span>
            <select className="input" value={form.status} onChange={(e) => set("status", e.target.value)}>
              <option value="active">Active</option>
              <option value="on_leave">On leave</option>
              <option value="terminated">Terminated</option>
            </select>
          </label>
          <label className="block">
            <span className="label">First name</span>
            <input className="input" value={form.first_name} onChange={(e) => set("first_name", e.target.value)} />
          </label>
          <label className="block">
            <span className="label">Last name</span>
            <input className="input" value={form.last_name} onChange={(e) => set("last_name", e.target.value)} />
          </label>
          <label className="block">
            <span className="label">Email</span>
            <input className="input" value={form.email} onChange={(e) => set("email", e.target.value)} />
          </label>
          <label className="block">
            <span className="label">Phone</span>
            <input className="input" value={form.phone} onChange={(e) => set("phone", e.target.value)} />
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
