"use client"

import { useCallback, useEffect, useRef, useState } from "react"
import { api } from "@/lib/api"

export type AuthUser = {
  id: number
  name?: string
  email: string
  telefono?: string;
  role: "superadmin" | "admin" | "ejecutivo" | "vendedor" | "bodega" | "fabrica";
  modulos?: string[]; // Array de módulos permitidos
}

type AuthState = {
  user: AuthUser | null
  loading: boolean
  error: string | null
}

export function useAuth() {
  const [state, setState] = useState<AuthState>({
    user: null,
    loading: true,
    error: null,
  })

  const didInit = useRef(false)

  const refreshMe = useCallback(async () => {
    try {
      setState((s) => ({ ...s, loading: true, error: null }))
      const me = await api.get<AuthUser>("/api/me")
      setState({ user: me, loading: false, error: null })
    } catch (e) {
      const err = e as Error & { status?: number }
      // 401 = no autenticado (normal si no hay sesión)
      if (err.status === 401) {
        setState({ user: null, loading: false, error: null })
        return
      }
      setState({ user: null, loading: false, error: err.message || "Error al obtener sesión" })
    }
  }, [])

  const login = useCallback(async (email: string, password: string) => {
    try {
      setState((s) => ({ ...s, loading: true, error: null }))

      console.log("[AUTH] Starting login process...")

      // 1) CSRF cookie
      console.log("[AUTH] Getting CSRF cookie...")
      await api.get("/sanctum/csrf-cookie")

      // 2) Login - extract token from response
      console.log("[AUTH] Sending login request...")
      const result = await api.post<{ ok: boolean, token?: string }>("/api/auth/login", { email, password })

      // 3) Save Bearer token to localStorage (persists across sessions)
      if (result?.token && typeof window !== 'undefined') {
        localStorage.setItem('auth_token', result.token)
        console.log("[AUTH] Token saved to localStorage")
      } else {
        console.warn("[AUTH] No token in login response:", result)
      }

      // 4) Cargar usuario
      console.log("[AUTH] Loading user data...")
      await refreshMe()

      console.log("[AUTH] Login successful!")

    } catch (error) {
      console.error("[AUTH] Login failed:", error)
      setState((s) => ({ ...s, loading: false, error: error instanceof Error ? error.message : "Error en login" }))
      throw error
    }
  }, [refreshMe])

  const logout = useCallback(async () => {
    try {
      await api.post("/api/auth/logout", {})
    } finally {
      // Clear auth token from localStorage
      if (typeof window !== 'undefined') {
        localStorage.removeItem('auth_token')
      }
      setState({ user: null, loading: false, error: null })
    }
  }, [])

  // Persistencia: al montar, intenta /api/me una vez
  useEffect(() => {
    if (didInit.current) return
    if (
      typeof window !== "undefined" &&
      (window.location.pathname.startsWith("/m/") ||
        window.location.pathname === "/m" ||
        window.location.pathname.startsWith("/encuesta"))
    ) {
      setState({ user: null, loading: false, error: null })
      return
    }
    didInit.current = true
    refreshMe()
  }, [refreshMe])

  return {
    user: state.user,
    loading: state.loading,
    error: state.error,
    login,
    logout,
    refreshMe,
  }
}
