"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { api, apiPaged, ApiError, downloadFile, API_URL, tokenStore } from "@/lib/api";
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
  gender?: string | null;
  date_of_birth?: string | null;
  blood_group?: string | null;
  marital_status?: string | null;
  nationality?: string | null;
  emergency_contact_name?: string | null;
  emergency_contact_phone?: string | null;
  current_address?: string | null;
  permanent_address?: string | null;
  employment_type?: string | null;
  work_location?: string | null;
  bank_account_name?: string | null;
  bank_ifsc?: string | null;
  uan?: string | null;
  pf_number?: string | null;
  esi_number?: string | null;
};

export default function EmployeesPage() {
  const { can } = useAuth();
  const [rows, setRows] = useState<Employee[]>([]);
  const [meta, setMeta] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [modal, setModal] = useState<null | Employee>(null);
  const [showCreate, setShowCreate] = useState(false);
  const [showImport, setShowImport] = useState(false);

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
          <>
            {can("employee.import") && (
              <button className="btn-ghost" onClick={() => setShowImport(true)}>
                Import CSV
              </button>
            )}
            {can("employee.profile.create") && (
              <button className="btn-primary" onClick={() => setShowCreate(true)}>
                + Add employee
              </button>
            )}
          </>
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
      {showImport && (
        <ImportModal
          onClose={() => setShowImport(false)}
          onDone={() => {
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
  const [tab, setTab] = useState<"details" | "documents">("details");
  const [form, setForm] = useState({
    employee_code: employee?.employee_code || "",
    first_name: employee?.first_name || "",
    last_name: employee?.last_name || "",
    email: employee?.email || "",
    phone: employee?.phone || "",
    status: employee?.status || "active",
    gender: employee?.gender || "",
    date_of_birth: employee?.date_of_birth || "",
    blood_group: employee?.blood_group || "",
    marital_status: employee?.marital_status || "",
    nationality: employee?.nationality || "",
    emergency_contact_name: employee?.emergency_contact_name || "",
    emergency_contact_phone: employee?.emergency_contact_phone || "",
    current_address: employee?.current_address || "",
    permanent_address: employee?.permanent_address || "",
    employment_type: employee?.employment_type || "",
    work_location: employee?.work_location || "",
    bank_account_name: employee?.bank_account_name || "",
    bank_account_number: "",
    bank_ifsc: employee?.bank_ifsc || "",
    pan: "",
    aadhaar: "",
    uan: employee?.uan || "",
    pf_number: employee?.pf_number || "",
    esi_number: employee?.esi_number || "",
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
      // Drop empty optional fields so we don't send blanks (or overwrite encrypted values).
      Object.keys(body).forEach((k) => {
        if (k !== "employee_code" && k !== "first_name" && k !== "status" && body[k] === "") {
          delete body[k];
        }
      });
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

        {editing && (
          <div className="flex gap-2 border-b border-slate-200 pb-2 dark:border-slate-800">
            <button className={tab === "details" ? "btn-primary" : "btn-ghost"} onClick={() => setTab("details")}>
              Details
            </button>
            <button className={tab === "documents" ? "btn-primary" : "btn-ghost"} onClick={() => setTab("documents")}>
              Documents
            </button>
          </div>
        )}

        {tab === "documents" && editing ? (
          <DocumentsPanel employeeUuid={employee!.uuid} />
        ) : (
          <div className="max-h-[60vh] space-y-5 overflow-y-auto pr-1">
            <Section title="Identity">
              <Grid>
                <F label="Employee code"><input className="input" value={form.employee_code} onChange={(e) => set("employee_code", e.target.value)} /></F>
                <F label="Status">
                  <select className="input" value={form.status} onChange={(e) => set("status", e.target.value)}>
                    <option value="active">Active</option>
                    <option value="on_leave">On leave</option>
                    <option value="terminated">Terminated</option>
                  </select>
                </F>
                <F label="First name"><input className="input" value={form.first_name} onChange={(e) => set("first_name", e.target.value)} /></F>
                <F label="Last name"><input className="input" value={form.last_name} onChange={(e) => set("last_name", e.target.value)} /></F>
                <F label="Email"><input className="input" value={form.email} onChange={(e) => set("email", e.target.value)} /></F>
                <F label="Phone"><input className="input" value={form.phone} onChange={(e) => set("phone", e.target.value)} /></F>
              </Grid>
            </Section>

            <Section title="Personal">
              <Grid>
                <F label="Gender"><input className="input" value={form.gender} onChange={(e) => set("gender", e.target.value)} /></F>
                <F label="Date of birth"><input type="date" className="input" value={form.date_of_birth} onChange={(e) => set("date_of_birth", e.target.value)} /></F>
                <F label="Blood group"><input className="input" value={form.blood_group} onChange={(e) => set("blood_group", e.target.value)} /></F>
                <F label="Marital status"><input className="input" value={form.marital_status} onChange={(e) => set("marital_status", e.target.value)} /></F>
                <F label="Nationality"><input className="input" value={form.nationality} onChange={(e) => set("nationality", e.target.value)} /></F>
              </Grid>
            </Section>

            <Section title="Contact & emergency">
              <Grid>
                <F label="Emergency contact name"><input className="input" value={form.emergency_contact_name} onChange={(e) => set("emergency_contact_name", e.target.value)} /></F>
                <F label="Emergency contact phone"><input className="input" value={form.emergency_contact_phone} onChange={(e) => set("emergency_contact_phone", e.target.value)} /></F>
                <F label="Current address"><textarea className="input" rows={2} value={form.current_address} onChange={(e) => set("current_address", e.target.value)} /></F>
                <F label="Permanent address"><textarea className="input" rows={2} value={form.permanent_address} onChange={(e) => set("permanent_address", e.target.value)} /></F>
              </Grid>
            </Section>

            <Section title="Employment">
              <Grid>
                <F label="Employment type">
                  <select className="input" value={form.employment_type} onChange={(e) => set("employment_type", e.target.value)}>
                    <option value="">—</option>
                    <option value="full_time">Full time</option>
                    <option value="part_time">Part time</option>
                    <option value="contract">Contract</option>
                    <option value="intern">Intern</option>
                  </select>
                </F>
                <F label="Work location"><input className="input" value={form.work_location} onChange={(e) => set("work_location", e.target.value)} /></F>
              </Grid>
            </Section>

            <Section title="Salary & statutory (sensitive values are encrypted)">
              <Grid>
                <F label="Bank account name"><input className="input" value={form.bank_account_name} onChange={(e) => set("bank_account_name", e.target.value)} /></F>
                <F label="Bank account number"><input className="input" placeholder={editing ? "•••• (enter to change)" : ""} value={form.bank_account_number} onChange={(e) => set("bank_account_number", e.target.value)} /></F>
                <F label="IFSC"><input className="input" value={form.bank_ifsc} onChange={(e) => set("bank_ifsc", e.target.value)} /></F>
                <F label="PAN"><input className="input" placeholder={editing ? "•••• (enter to change)" : ""} value={form.pan} onChange={(e) => set("pan", e.target.value)} /></F>
                <F label="Aadhaar"><input className="input" placeholder={editing ? "•••• (enter to change)" : ""} value={form.aadhaar} onChange={(e) => set("aadhaar", e.target.value)} /></F>
                <F label="UAN"><input className="input" value={form.uan} onChange={(e) => set("uan", e.target.value)} /></F>
                <F label="PF number"><input className="input" value={form.pf_number} onChange={(e) => set("pf_number", e.target.value)} /></F>
                <F label="ESI number"><input className="input" value={form.esi_number} onChange={(e) => set("esi_number", e.target.value)} /></F>
              </Grid>
            </Section>
          </div>
        )}

        {tab === "details" && (
          <div className="flex justify-end gap-2 pt-2">
            <button className="btn-ghost" onClick={onClose}>Cancel</button>
            <button className="btn-primary" onClick={save} disabled={busy}>
              {busy ? "Saving…" : "Save"}
            </button>
          </div>
        )}
      </div>
    </Modal>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div>
      <h4 className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">{title}</h4>
      {children}
    </div>
  );
}

function Grid({ children }: { children: React.ReactNode }) {
  return <div className="grid gap-3 sm:grid-cols-2">{children}</div>;
}

function F({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="label">{label}</span>
      {children}
    </label>
  );
}


const DOC_TYPES = [
  "resume", "offer_letter", "contract", "aadhaar", "pan", "passport", "license",
  "education", "experience", "payslip", "bank_passbook", "photo", "other",
];

type Doc = {
  uuid: string;
  type: string;
  title: string;
  original_name: string;
  size: number;
  version: number;
  uploaded_at: string;
};

function DocumentsPanel({ employeeUuid }: { employeeUuid: string }) {
  const { can } = useAuth();
  const [docs, setDocs] = useState<Doc[]>([]);
  const [loading, setLoading] = useState(true);
  const [type, setType] = useState("resume");
  const [title, setTitle] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const fileRef = useRef<HTMLInputElement>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      setDocs((await api<Doc[]>(`/employees/${employeeUuid}/documents`)) || []);
    } finally {
      setLoading(false);
    }
  }, [employeeUuid]);

  useEffect(() => {
    load();
  }, [load]);

  async function upload() {
    setErr(null);
    const file = fileRef.current?.files?.[0];
    if (!file) {
      setErr("Choose a file to upload.");
      return;
    }
    setBusy(true);
    try {
      const fd = new FormData();
      fd.append("type", type);
      if (title) fd.append("title", title);
      fd.append("file", file);
      const res = await fetch(`${API_URL}/api/v1/employees/${employeeUuid}/documents`, {
        method: "POST",
        headers: tokenStore.access ? { Authorization: `Bearer ${tokenStore.access}`, Accept: "application/json" } : {},
        body: fd,
      });
      if (!res.ok) {
        const j = await res.json().catch(() => ({}));
        throw new Error(j?.error?.message || `Upload failed (${res.status})`);
      }
      if (fileRef.current) fileRef.current.value = "";
      setTitle("");
      load();
    } catch (e) {
      setErr((e as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function remove(d: Doc) {
    if (!confirm(`Delete "${d.title}" (v${d.version})?`)) return;
    await api(`/employee-documents/${d.uuid}`, { method: "DELETE" });
    load();
  }

  return (
    <div className="max-h-[60vh] space-y-4 overflow-y-auto pr-1">
      {err && <Alert kind="error">{err}</Alert>}

      {can("employee.document.manage") && (
        <div className="rounded-xl border border-slate-200 p-3 dark:border-slate-800">
          <div className="grid gap-2 sm:grid-cols-2">
            <label className="block">
              <span className="label">Type</span>
              <select className="input" value={type} onChange={(e) => setType(e.target.value)}>
                {DOC_TYPES.map((t) => (
                  <option key={t} value={t}>{t.replace(/_/g, " ")}</option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="label">Title (optional)</span>
              <input className="input" value={title} onChange={(e) => setTitle(e.target.value)} />
            </label>
          </div>
          <input ref={fileRef} type="file" className="mt-2 block w-full text-sm" />
          <button className="btn-primary mt-2" onClick={upload} disabled={busy}>
            {busy ? "Uploading…" : "Upload document"}
          </button>
          <p className="mt-1 text-xs text-slate-400">Max 10 MB. PDF, images, or office files. Re-uploading a type keeps a new version.</p>
        </div>
      )}

      {loading ? (
        <Spinner />
      ) : docs.length === 0 ? (
        <Empty message="No documents uploaded yet." />
      ) : (
        <Table head={["Type", "Title", "Ver", "Size", ""]}>
          {docs.map((d) => (
            <tr key={d.uuid}>
              <Td><Badge color="slate">{d.type.replace(/_/g, " ")}</Badge></Td>
              <Td className="font-medium text-slate-800 dark:text-slate-200">{d.title}</Td>
              <Td>v{d.version}</Td>
              <Td className="text-slate-500">{(d.size / 1024).toFixed(0)} KB</Td>
              <Td>
                <div className="flex gap-3">
                  <button className="text-sm font-medium text-brand-600 hover:underline" onClick={() => downloadFile(`/employee-documents/${d.uuid}/download`, d.original_name)}>
                    Download
                  </button>
                  {can("employee.document.manage") && (
                    <button className="text-sm font-medium text-red-600 hover:underline" onClick={() => remove(d)}>
                      Delete
                    </button>
                  )}
                </div>
              </Td>
            </tr>
          ))}
        </Table>
      )}
    </div>
  );
}


type ImportRow = { line: number; status: string; message?: string; employee_code?: string | null };

function ImportModal({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [result, setResult] = useState<{ summary: { total: number; created: number; failed: number }; rows: ImportRow[] } | null>(null);
  const fileRef = useRef<HTMLInputElement>(null);

  async function run() {
    setErr(null);
    const file = fileRef.current?.files?.[0];
    if (!file) {
      setErr("Choose a CSV file to import.");
      return;
    }
    setBusy(true);
    try {
      const fd = new FormData();
      fd.append("file", file);
      const res = await fetch(`${API_URL}/api/v1/employees/import`, {
        method: "POST",
        headers: tokenStore.access ? { Authorization: `Bearer ${tokenStore.access}`, Accept: "application/json" } : {},
        body: fd,
      });
      const j = await res.json().catch(() => ({}));
      if (!res.ok && res.status !== 207) {
        throw new Error(j?.error?.message || `Import failed (${res.status})`);
      }
      setResult(j.data);
      onDone();
    } catch (e) {
      setErr((e as Error).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal open title="Bulk import employees" onClose={onClose}>
      <div className="space-y-3">
        {err && <Alert kind="error">{err}</Alert>}
        {!result ? (
          <>
            <p className="text-sm text-slate-500 dark:text-slate-400">
              Upload a CSV with columns: <span className="font-mono text-xs">employee_code, first_name, last_name, email, phone, department, designation, date_of_joining, employment_type, gender, work_location</span>.
            </p>
            <button className="btn-ghost" onClick={() => downloadFile("/employees/import/template", "employee-import-template.csv")}>
              Download CSV template
            </button>
            <input ref={fileRef} type="file" accept=".csv,text/csv" className="block w-full text-sm" />
            <div className="flex justify-end gap-2 pt-2">
              <button className="btn-ghost" onClick={onClose}>Cancel</button>
              <button className="btn-primary" onClick={run} disabled={busy}>
                {busy ? "Importing…" : "Import"}
              </button>
            </div>
          </>
        ) : (
          <>
            <Alert kind={result.summary.failed > 0 ? "info" : "success"}>
              Imported {result.summary.created} of {result.summary.total} rows
              {result.summary.failed > 0 ? `, ${result.summary.failed} failed.` : "."}
            </Alert>
            <div className="max-h-72 overflow-y-auto">
              <Table head={["Line", "Code", "Result"]}>
                {result.rows.map((r) => (
                  <tr key={r.line}>
                    <Td>{r.line}</Td>
                    <Td className="font-mono text-xs">{r.employee_code || "—"}</Td>
                    <Td>
                      {r.status === "created" ? (
                        <Badge color="green">created</Badge>
                      ) : (
                        <span className="text-sm text-red-600">{r.message}</span>
                      )}
                    </Td>
                  </tr>
                ))}
              </Table>
            </div>
            <div className="flex justify-end pt-2">
              <button className="btn-primary" onClick={onClose}>Done</button>
            </div>
          </>
        )}
      </div>
    </Modal>
  );
}
