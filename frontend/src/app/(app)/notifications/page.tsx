"use client";

import { useCallback, useEffect, useState } from "react";
import { api, apiPaged } from "@/lib/api";
import { PageHeader, Card, Empty, Spinner, Badge } from "@/components/ui";

type Notification = {
  uuid: string;
  type: string;
  title: string;
  body: string | null;
  read_at: string | null;
  created_at: string;
};

export default function NotificationsPage() {
  const [rows, setRows] = useState<Notification[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const r = await apiPaged<Notification[]>("/notifications?per_page=50");
      setRows(r.data || []);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function markRead(n: Notification) {
    if (n.read_at) return;
    await api(`/notifications/${n.uuid}/read`, { method: "POST", body: {} });
    setRows((rs) => rs.map((x) => (x.uuid === n.uuid ? { ...x, read_at: new Date().toISOString() } : x)));
  }

  async function markAll() {
    await api("/notifications/read-all", { method: "POST", body: {} });
    setRows((rs) => rs.map((x) => ({ ...x, read_at: x.read_at || new Date().toISOString() })));
  }

  const unread = rows.filter((r) => !r.read_at).length;

  return (
    <div>
      <PageHeader
        title="Notifications"
        subtitle={unread ? `${unread} unread` : "You're all caught up"}
        actions={
          unread > 0 && (
            <button className="btn-ghost" onClick={markAll}>
              Mark all as read
            </button>
          )
        }
      />

      {loading ? (
        <Spinner />
      ) : rows.length === 0 ? (
        <Empty message="No notifications yet." />
      ) : (
        <div className="space-y-2">
          {rows.map((n) => (
            <Card
              key={n.uuid}
              className={`cursor-pointer transition ${n.read_at ? "opacity-70" : "border-brand-200 bg-brand-50/40"}`}
            >
              <div onClick={() => markRead(n)} className="flex items-start justify-between gap-3">
                <div>
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-slate-800">{n.title}</span>
                    {!n.read_at && <Badge color="blue">new</Badge>}
                  </div>
                  {n.body && <p className="mt-1 text-sm text-slate-600">{n.body}</p>}
                  <div className="mt-1 text-xs text-slate-400">
                    {n.type} · {new Date(n.created_at).toLocaleString()}
                  </div>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
