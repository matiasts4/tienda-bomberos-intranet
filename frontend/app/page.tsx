"use client"

import React, { useState, useEffect } from "react"
import { useRouter, useSearchParams } from "next/navigation"
import { useAuthContext } from "@/components/auth-provider"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Flame, Shield, Lock, Mail, AlertTriangle } from "lucide-react"
import { Card, CardContent, CardHeader } from "@/components/ui/card"

const LoginContent = () => {
  const searchParams = useSearchParams()
  const router = useRouter()
  // Default redirect to /modulos if no 'next' param is provided
  const next = searchParams.get("next") || "/modulos"

  const { login, loading, error, user } = useAuthContext()

  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [remember, setRemember] = useState(false)
  const [localError, setLocalError] = useState<string | null>(null)

  // Redirect if already authenticated
  useEffect(() => {
    if (user && !loading) {
      router.replace(next)
    }
  }, [user, loading, next, router])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setLocalError(null)

    try {
      await login(email, password)
      // Slight delay to allow cookies/state to propagate
      setTimeout(() => {
        router.replace(next)
      }, 100)
    } catch (err) {
      setLocalError(err instanceof Error ? err.message : "Error de autenticación")
    }
  }

  const msg = localError || error

  return (
    <div className="min-h-screen w-full flex items-center justify-center bg-[#0f172a] relative overflow-hidden">
      {/* Background Effects */}
      <div className="absolute inset-0 z-0">
        <div className="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-red-900/20 via-slate-900 to-slate-900" />
        <div className="absolute -top-[20%] -right-[10%] w-[600px] h-[600px] bg-orange-600/10 rounded-full blur-3xl opacity-50" />
        <div className="absolute -bottom-[20%] -left-[10%] w-[500px] h-[500px] bg-red-700/10 rounded-full blur-3xl opacity-40" />
      </div>

      <div className="w-full max-w-md z-10 p-4">
        {/* Header / Logo */}
        <div className="text-center mb-8 space-y-2">
          <div className="inline-flex items-center justify-center p-3 rounded-full bg-gradient-to-tr from-red-600 to-orange-500 shadow-lg shadow-red-900/50 mb-4 ring-4 ring-red-900/30">
            <Flame className="w-10 h-10 text-white fill-white/20" strokeWidth={2} />
          </div>
          <h1 className="text-3xl font-bold tracking-tight text-white drop-shadow-sm">
            Tienda Bomberos
          </h1>
          <p className="text-slate-400 text-sm font-medium uppercase tracking-wider">
            Sistema de Gestión Interno
          </p>
        </div>

        {/* Login Card */}
        <Card className="border-red-900/30 bg-slate-900/60 backdrop-blur-xl shadow-2xl ring-1 ring-white/10">
          <CardHeader className="pb-2">
            <div className="mx-auto w-12 h-1 bg-red-600/50 rounded-full mb-4" />
          </CardHeader>
          <CardContent className="space-y-6 pt-2">

            {msg && (
              <div className="p-3 rounded-md bg-red-500/10 border border-red-500/20 text-red-200 text-sm flex items-center gap-2 animate-in fade-in slide-in-from-top-1">
                <AlertTriangle className="w-4 h-4 text-red-400" />
                <span>{msg}</span>
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-5">
              <div className="space-y-2">
                <Label htmlFor="email" className="text-slate-200 text-xs uppercase tracking-wide font-semibold">
                  Credencial de Acceso
                </Label>
                <div className="relative group">
                  <Mail className="absolute left-3 top-3 w-4 h-4 text-slate-500 group-focus-within:text-red-400 transition-colors" />
                  <Input
                    id="email"
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="pl-10 bg-slate-950/50 border-slate-800 text-white placeholder:text-slate-600 focus:border-red-500/50 focus:ring-red-500/20 h-11 transition-all"
                    placeholder="usuario@bomberos.cl"
                    required
                  />
                </div>
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label htmlFor="password" className="text-slate-200 text-xs uppercase tracking-wide font-semibold">
                    Contraseña
                  </Label>
                </div>
                <div className="relative group">
                  <Lock className="absolute left-3 top-3 w-4 h-4 text-slate-500 group-focus-within:text-red-400 transition-colors" />
                  <Input
                    id="password"
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="pl-10 bg-slate-950/50 border-slate-800 text-white placeholder:text-slate-600 focus:border-red-500/50 focus:ring-red-500/20 h-11 transition-all"
                    placeholder="••••••••"
                    required
                  />
                </div>
              </div>

              <div className="flex items-center gap-2 py-1">
                <input
                  type="checkbox"
                  id="remember"
                  className="w-4 h-4 rounded border-slate-700 bg-slate-900 text-red-600 focus:ring-red-900 focus:ring-offset-slate-900"
                  checked={remember}
                  onChange={(e) => setRemember(e.target.checked)}
                />
                <Label htmlFor="remember" className="text-sm text-slate-400 font-normal cursor-pointer hover:text-slate-300">
                  Recordarme en este dispositivo
                </Label>
              </div>

              <Button
                type="submit"
                className="w-full bg-gradient-to-r from-red-700 to-red-600 hover:from-red-600 hover:to-red-500 text-white font-bold h-12 shadow-lg shadow-red-900/20 border-t border-white/10 transition-all hover:scale-[1.01] active:scale-[0.99]"
                disabled={loading}
              >
                {loading ? (
                  <span className="flex items-center gap-2">
                    <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                    Autenticando...
                  </span>
                ) : (
                  <span className="flex items-center gap-2">
                    Ingresar al Sistema <Shield className="w-4 h-4" />
                  </span>
                )}
              </Button>
            </form>
          </CardContent>
        </Card>

        {/* Footer */}
        <div className="text-center mt-8 text-slate-600 text-xs">
          <p>© 2026 Tienda Bomberos. Acceso restringido.</p>
          <div className="flex justify-center gap-2 mt-2 opacity-50">
            <div className="w-2 h-2 rounded-full bg-red-800" />
            <div className="w-2 h-2 rounded-full bg-orange-700" />
            <div className="w-2 h-2 rounded-full bg-slate-700" />
          </div>
        </div>
      </div>
    </div>
  )
}

export default function LoginPage() {
  return (
    <React.Suspense fallback={<div className="min-h-screen bg-[#0f172a]" />}>
      <LoginContent />
    </React.Suspense>
  )
}
