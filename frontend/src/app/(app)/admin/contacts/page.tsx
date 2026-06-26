"use client";

import { useCallback, useEffect, useState } from "react";
import { api, apiPaged } from "@/lib/api";
import { PageHeader, Table, Td, Badge, statusColor, Empty, Spinner } from "@/components/ui";

type Inquiry = {
  uuid: string;
  name: string;
  company_name: string | null;
  email: string;
  phone: string | null;
  subject: string | null;
  message: string;
  status: string;
  created_at: string;
};

export default function AdminContacts() {
  const [rows, setRows] = useState<Inquiry[]>([]);
  const [loading, setLoading] = useState(true);
  const [status, setStatus] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const qs = status ? `?status=${status}&per_page=50` : "?per_page=50";
      const r = await apiPaged<Inquiry[]>(`/admin/contact-inquiries${qs}`);
      setRows(r.data || []);
    } finally {
      setLoading(false);
    }
  }, [status]);

  useEffect(() => {
    load();
  }, [load]);

  async function setStatusFor(i: Inquiry, s: string) {
    await api(`/admin/contact-inquiries/${i.uuid}`, { method: "PATCH", body: { status: s } });
    load();
  }

  return (
    <div>
      <PageHeader title="Contact Inquiries" subtitle="Leads from the public contact form." />

      <div className="mb-4 flex gap-2">
        <select className="input max-w-[180px]" value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">All</option>
          <option value="new">New</option>
          <option value="contacted">Contacted</option>
          <option value="closed">Closed</option>
        </select>
      </div>

      {loading ? (
        <Spinner />
      ) : rows.length === 0 ? (
        <Empty message="No inquiries." />
      ) : (
        <Table head={["Name", "Company", "Email", "Message", "Status", "Date", "Actions"]}>
          {rows.map((i) => (
            <tr key={i.uuid} className="align-top hover:bg-slate-50 dark:hover:bg-slate-800/50">
              <Td className="font-medium text-slate-800 dark:text-slate-100">{i.name}</Td>
              <Td>{i.company_name || "—"}</Td>
              <Td className="text-slate-500">
                {i.email}
                {i.phone ? <div className="text-xs text-slate-400">{i.phone}</div> : null}
              </Td>
              <Td className="max-w-xs text-slate-500">
                {i.subject && <div className="font-medium text-slate-600 dark:text-slate-300">{i.subject}</div>}
                <div className="line-clamp-2 text-xs">{i.message}</div>
              </Td>
              <Td>
                <Badge color={statusColor(i.status)}>{i.status}</Badge>
              </Td>
              <Td className="text-xs text-slate-400">{i.created_at?.slice(0, 10)}</Td>
              <Td>
                <select
                  className="input text-xs"
                  value={i.status}
                  onChange={(e) => setStatusFor(i, e.target.value)}
                >
                  <option value="new">New</option>
                  <option value="contacted">Contacted</option>
                  <option value="closed">Closed</option>
                </select>
              </Td>
            </tr>
          ))}
        </Table>
      )}
    </div>
  );
}
