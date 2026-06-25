"use client";

import { createContext, useContext, useEffect, useState, useCallback } from "react";
import { api, tokenStore } from "./api";

export type User = {
  uuid: string;
  name: string;
  email: string;
  is_super_admin: boolean;
  company_id: number | null;
  roles: string[];
  permissions: string[];
};

export type Entitlement = {
  status: string;
  access_granted: boolean;
  modules: string[];
  features: Record<string, string>;
  seats?: { included: number; purchased: number; limit: number | null };
};

type AuthState = {
  user: User | null;
  entitlement: Entitlement | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  registerCompany: (payload: RegisterPayload) => Promise<void>;
  logout: () => Promise<void>;
  refreshMe: () => Promise<void>;
  can: (permission: string) => boolean;
  hasModule: (code: string) => boolean;
};

export type RegisterPayload = {
  company_name: string;
  admin_name: string;
  email: string;
  password: string;
  password_confirmation: string;
  plan_code?: string;
};

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [entitlement, setEntitlement] = useState<Entitlement | null>(null);
  const [loading, setLoading] = useState(true);

  const loadSession = useCallback(async () => {
    if (!tokenStore.access) {
      setUser(null);
      setEntitlement(null);
      setLoading(false);
      return;
    }
    try {
      const me = await api<User>("/me");
      setUser(me);
      if (!me.is_super_admin) {
        try {
          const ent = await api<Entitlement>("/me/entitlements");
          setEntitlement(ent);
        } catch {
          setEntitlement(null);
        }
      }
    } catch {
      tokenStore.clear();
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadSession();
  }, [loadSession]);

  const login = useCallback(
    async (email: string, password: string) => {
      const res = await api<{ tokens: any }>("/auth/login", {
        method: "POST",
        auth: false,
        body: { email, password },
      });
      tokenStore.set(res.tokens.access_token, res.tokens.refresh_token);
      await loadSession();
    },
    [loadSession],
  );

  const registerCompany = useCallback(
    async (payload: RegisterPayload) => {
      const res = await api<{ tokens: any }>("/auth/register-company", {
        method: "POST",
        auth: false,
        body: payload,
      });
      tokenStore.set(res.tokens.access_token, res.tokens.refresh_token);
      await loadSession();
    },
    [loadSession],
  );

  const logout = useCallback(async () => {
    try {
      await api("/auth/logout", { method: "POST" });
    } catch {
      /* ignore */
    }
    tokenStore.clear();
    setUser(null);
    setEntitlement(null);
  }, []);

  const can = useCallback(
    (permission: string) => {
      if (!user) return false;
      if (user.is_super_admin) return true;
      return user.permissions?.includes(permission) ?? false;
    },
    [user],
  );

  const hasModule = useCallback(
    (code: string) => {
      if (user?.is_super_admin) return true;
      return entitlement?.modules?.includes(code) ?? false;
    },
    [user, entitlement],
  );

  return (
    <AuthContext.Provider
      value={{
        user,
        entitlement,
        loading,
        login,
        registerCompany,
        logout,
        refreshMe: loadSession,
        can,
        hasModule,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
