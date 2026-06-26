"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { api, apiPaged, tokenStore, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { PageHeader, Table, Td, Badge, statusColor, Empty, Spinner, Alert } from "@/components/ui";

type Company = {
  uuid: string;
  name: string;
  status: string;
  employees: number;
  subscription: { status: string; plan: string | null; seats: number } | null;
  created_at: string;
};

export default function AdminCompanies() {
  const { refreshMe } = useAuth();
  const router = useRouter();
  const [rows, setRows] = useState<Company[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [msg, setMsg] = useState<{ kind: "success" | "error"; text: string } | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const qs = new URLSearchParams();
      if (search) qs.set("search", search);
      if (status) qs.set("status", status);
      qs.set("per_page", "50");
      const r = await apiPaged<Company[]>(`/admin/companies?${qs.toString()}`);
      setRows(r.data || []);
    } finally {
      setLoading(false);
    }
  }, [search, status]);

  useEffect(() => {
    load();
  }, [load]);

  async function act(c: Company, action: "suspend" | "activate") {
    setMsg(null);
    try {
      await api(`/admin/companies/${c.uuid}/${action}`, { method: "POST", body: {} });
      setMsg({ kind: "success", text: `${c.name} ${action}d.` });
      load();
    } catch (err) {
      setMsg({ kind: "error", text: (err as ApiError).message });
    }
  }

  async function resetPassword(c: Company) {
    setMsg(null);
    try {
      const r = await api<{ email: string; temporary_password: string }>(
        `/admin/companies/${c.uuid}/reset-password`,
        { method: "POST", body: {} },
      );
      setMsg({ kind: "success", text: `Temp password for ${r.email}: ${r.temporary_password}` });
    } catch (err) {
      setMsg({ kind: "error", text: (err as ApiError).message });
    }
  }

  async function impersonate(c: Company) {
    if (!confirm(`Log in as ${c.name}? Your current admin session will be replaced.`)) return;
    try {
      const r = await api<{ tokens: { access_token: string; refresh_token: string } }>(
        `/admin/companies/${c.uuid}/impersonate`,
        { method: "POST", body: {} },
      );
      tokenStore.set(r.tokens.access_token, r.tokens.refresh_token);
      await refreshMe();
      router.push("/dashboard");
    } catch (err) {
      setMsg({ kind: "error", text: (err as ApiError).message });
    }
  }

  return (
    <div>
      <PageHeader title="Companies" subtitle="Every tenant on the platform." />

      {msg && (
        <div className="mb-4">
          <Alert kind={msg.kind}>{msg.text}</Alert>
        </div>
      )}

      <div className="mb-4 flex flex-wrap gap-2">
        <input className="input max-w-xs" placeholder="Search…" value={search} onChange={(e) => setSearch(e.target.value)} />
        <select className="input max-w-[180px]" value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">All statuses</option>
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      {loading ? (
        <Spinner />
      ) : rows.length === 0 ? (
        <Empty message="No companies match." />
      ) : (
        <Table head={["Company", "Plan", "Subscription", "Employees", "Status", "Actions"]}>
          {rows.map((c) => (
            <tr key={c.uuid} className="hover:bg-slate-50 dark:hover:bg-slate-800/50">
              <Td className="font-medium text-slate-800 dark:text-slate-100">{c.name}</Td>
              <Td>{c.subscription?.plan || "—"}</Td>
              <Td>{c.subscription ? <Badge color={statusColor(c.subscription.status)}>{c.subscription.status}</Badge> : "—"}</Td>
              <Td>{c.employees}</Td>
              <Td>
                <Badge color={statusColor(c.status)}>{c.status}</Badge>
              </Td>
              <Td>
                <div className="flex flex-wrap gap-2 text-sm">
                  {c.status === "active" ? (
                    <button onClick={() => act(c, "suspend")} className="font-medium text-amber-600 hover:underline">
                      Suspend
                    </button>
                  ) : (
                    <button onClick={() => act(c, "activate")} className="font-medium text-emerald-600 hover:underline">
                      Activate
                    </button>
                  )}
                  <button onClick={() => impersonate(c)} className="font-medium text-brand-600 hover:underline">
                    Login as
                  </button>
                  <button onClick={() => resetPassword(c)} className="font-medium text-slate-500 hover:underline">
                    Reset pw
                  </button>
                </div>
              </Td>
            </tr>
          ))}
        </Table>
      )}
    </div>
  );
}
