"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { api } from "@/lib/api";

export type PaletteNavItem = { href: string; label: string; icon: string };

type ResultItem = { id: string; title: string; subtitle?: string; href: string; icon?: string };
type Group = { type: string; label: string; items: ResultItem[] };

/**
 * Global command palette (Cmd/Ctrl+K). Combines local navigation with remote
 * tenant-scoped search (GET /search). Keyboard-first: ↑/↓ to move, ↵ to open,
 * Esc to close. See docs/10-FRONTEND-UX.md.
 */
export default function CommandPalette({ navItems }: { navItems: PaletteNavItem[] }) {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [remote, setRemote] = useState<Group[]>([]);
  const [active, setActive] = useState(0);
  const inputRef = useRef<HTMLInputElement>(null);

  // Global hotkey: Cmd/Ctrl+K toggles the palette. Also opens on a custom event
  // (dispatched by the header search button).
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        setOpen((o) => !o);
      } else if (e.key === "Escape") {
        setOpen(false);
      }
    };
    const onOpen = () => setOpen(true);
    window.addEventListener("keydown", onKey);
    window.addEventListener("open-command-palette", onOpen);
    return () => {
      window.removeEventListener("keydown", onKey);
      window.removeEventListener("open-command-palette", onOpen);
    };
  }, []);

  useEffect(() => {
    if (open) {
      setQuery("");
      setRemote([]);
      setActive(0);
      setTimeout(() => inputRef.current?.focus(), 30);
    }
  }, [open]);

  // Debounced remote search.
  useEffect(() => {
    if (!open || query.trim().length < 2) {
      setRemote([]);
      return;
    }
    const t = setTimeout(() => {
      api<{ groups: Group[] }>(`/search?q=${encodeURIComponent(query.trim())}`)
        .then((r) => setRemote(r.groups || []))
        .catch(() => setRemote([]));
    }, 200);
    return () => clearTimeout(t);
  }, [query, open]);

  const navGroup = useMemo<Group>(() => {
    const q = query.trim().toLowerCase();
    const items = navItems
      .filter((n) => !q || n.label.toLowerCase().includes(q))
      .map((n) => ({ id: n.href, title: n.label, subtitle: "Go to page", href: n.href, icon: n.icon }));
    return { type: "nav", label: "Navigation", items };
  }, [navItems, query]);

  const groups = useMemo(() => [navGroup, ...remote].filter((g) => g.items.length > 0), [navGroup, remote]);
  const flat = useMemo(() => groups.flatMap((g) => g.items), [groups]);

  const go = useCallback(
    (href: string) => {
      setOpen(false);
      router.push(href);
    },
    [router],
  );

  function onKeyDown(e: React.KeyboardEvent) {
    if (e.key === "ArrowDown") {
      e.preventDefault();
      setActive((a) => Math.min(a + 1, flat.length - 1));
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      setActive((a) => Math.max(a - 1, 0));
    } else if (e.key === "Enter" && flat[active]) {
      e.preventDefault();
      go(flat[active].href);
    }
  }

  if (!open) return null;

  let runningIndex = -1;

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/40 p-4 pt-[12vh] backdrop-blur-sm" onClick={() => setOpen(false)}>
      <div
        className="w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
        onClick={(e) => e.stopPropagation()}
      >
        <input
          ref={inputRef}
          value={query}
          onChange={(e) => {
            setQuery(e.target.value);
            setActive(0);
          }}
          onKeyDown={onKeyDown}
          placeholder="Search people, departments, or jump to a page…"
          className="w-full border-b border-slate-200 bg-transparent px-5 py-4 text-sm text-slate-800 outline-none placeholder:text-slate-400 dark:border-slate-700 dark:text-slate-100"
        />
        <div className="max-h-[50vh] overflow-y-auto p-2">
          {flat.length === 0 ? (
            <div className="px-4 py-8 text-center text-sm text-slate-400">No matches.</div>
          ) : (
            groups.map((g) => (
              <div key={g.type} className="mb-2">
                <div className="px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{g.label}</div>
                {g.items.map((it) => {
                  runningIndex++;
                  const idx = runningIndex;
                  return (
                    <button
                      key={g.type + it.id}
                      onMouseEnter={() => setActive(idx)}
                      onClick={() => go(it.href)}
                      className={`flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm ${
                        idx === active
                          ? "bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-100"
                          : "text-slate-700 dark:text-slate-200"
                      }`}
                    >
                      <span className="text-base">{it.icon || "↳"}</span>
                      <span className="flex-1 truncate">{it.title}</span>
                      {it.subtitle && <span className="truncate text-xs text-slate-400">{it.subtitle}</span>}
                    </button>
                  );
                })}
              </div>
            ))
          )}
        </div>
        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-2 text-[11px] text-slate-400 dark:border-slate-800">
          <span>↑ ↓ to navigate · ↵ to open · Esc to close</span>
          <span className="rounded bg-slate-100 px-1.5 py-0.5 font-mono dark:bg-slate-800">⌘K</span>
        </div>
      </div>
    </div>
  );
}
