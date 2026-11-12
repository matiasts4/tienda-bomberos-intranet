"use client";

import React, { createContext, useContext, useMemo } from "react";
import { useAuth, type AuthUser } from "@/hooks/useAuth";

type AuthContextValue = {
  user: AuthUser | null;
  loading: boolean;
  error: string | null;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshMe: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const auth = useAuth();

  const value = useMemo<AuthContextValue>(
    () => ({
      user: auth.user,
      loading: auth.loading,
      error: auth.error,
      login: auth.login,
      logout: auth.logout,
      refreshMe: auth.refreshMe,
    }),
    [auth.user, auth.loading, auth.error, auth.login, auth.logout, auth.refreshMe]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuthContext() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuthContext debe usarse dentro de <AuthProvider>");
  return ctx;
}
