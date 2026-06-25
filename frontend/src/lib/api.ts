"use client";

/**
 * Thin API client for the HRMS Laravel backend.
 *
 * - Attaches the JWT access token as a Bearer header.
 * - On a 401, transparently tries to refresh once using the stored refresh
 *   token, then retries the original request.
 * - Unwraps the standard response envelope: { data, meta } or { error }.
 */

export const API_URL =
  (typeof process !== "undefined" && process.env.NEXT_PUBLIC_API_URL) ||
  "http://localhost:8000";

const ACCESS_KEY = "hrms_access_token";
const REFRESH_KEY = "hrms_refresh_token";

export const tokenStore = {
  get access() {
    if (typeof window === "undefined") return null;
    return window.localStorage.getItem(ACCESS_KEY);
  },
  get refresh() {
    if (typeof window === "undefined") return null;
    return window.localStorage.getItem(REFRESH_KEY);
  },
  set(access: string, refresh?: string) {
    if (typeof window === "undefined") return;
    window.localStorage.setItem(ACCESS_KEY, access);
    if (refresh) window.localStorage.setItem(REFRESH_KEY, refresh);
  },
  clear() {
    if (typeof window === "undefined") return;
    window.localStorage.removeItem(ACCESS_KEY);
    window.localStorage.removeItem(REFRESH_KEY);
  },
};

export class ApiError extends Error {
  code: string;
  status: number;
  details: any;
  constructor(status: number, code: string, message: string, details?: any) {
    super(message);
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

type Options = {
  method?: string;
  body?: any;
  auth?: boolean;
  _retried?: boolean;
};

async function refreshTokens(): Promise<boolean> {
  const refresh = tokenStore.refresh;
  if (!refresh) return false;
  try {
    const res = await fetch(`${API_URL}/api/v1/auth/refresh`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ refresh_token: refresh }),
    });
    if (!res.ok) return false;
    const json = await res.json();
    const tokens = json?.data?.tokens;
    if (tokens?.access_token) {
      tokenStore.set(tokens.access_token, tokens.refresh_token);
      return true;
    }
    return false;
  } catch {
    return false;
  }
}

export async function api<T = any>(path: string, opts: Options = {}): Promise<T> {
  const { method = "GET", body, auth = true } = opts;
  const headers: Record<string, string> = {
    Accept: "application/json",
  };
  if (body !== undefined) headers["Content-Type"] = "application/json";
  if (auth && tokenStore.access) headers["Authorization"] = `Bearer ${tokenStore.access}`;

  const res = await fetch(`${API_URL}/api/v1${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });

  // Transparent single refresh on auth failure.
  if (res.status === 401 && auth && !opts._retried) {
    const ok = await refreshTokens();
    if (ok) return api<T>(path, { ...opts, _retried: true });
    tokenStore.clear();
  }

  let json: any = null;
  const text = await res.text();
  if (text) {
    try {
      json = JSON.parse(text);
    } catch {
      json = null;
    }
  }

  if (!res.ok) {
    const err = json?.error;
    throw new ApiError(
      res.status,
      err?.code || "ERROR",
      err?.message || `Request failed (${res.status})`,
      err?.details,
    );
  }

  return (json?.data !== undefined ? json.data : json) as T;
}

/** Returns the full envelope (data + meta) for paginated list endpoints. */
export async function apiPaged<T = any>(
  path: string,
): Promise<{ data: T; meta: any }> {
  const headers: Record<string, string> = { Accept: "application/json" };
  if (tokenStore.access) headers["Authorization"] = `Bearer ${tokenStore.access}`;
  const res = await fetch(`${API_URL}/api/v1${path}`, { headers });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new ApiError(res.status, json?.error?.code || "ERROR", json?.error?.message || "Failed");
  }
  return { data: json?.data, meta: json?.meta };
}

/** Format minor units (paise) as a currency string. */
export function money(minor: number | null | undefined, currency = "INR"): string {
  if (minor === null || minor === undefined) return "—";
  const symbol = currency === "INR" ? "₹" : "";
  return `${symbol}${(minor / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
}
