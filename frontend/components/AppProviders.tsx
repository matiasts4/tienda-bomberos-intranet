"use client"

import type { ReactNode } from "react"
import { usePathname } from "next/navigation"
import { AuthProvider } from "@/components/auth-provider"
import { CatalogoProvider } from "@/app/(auth)/inventario/CatalogoContext"
import { DndProvider } from "@/components/dnd-provider"

// Eliminamos el LoadingWrapper global porque ahora la carga es bajo demanda.

export function AppProviders({ children }: { children: ReactNode }) {
  const pathname = usePathname()
  const isMarketplace = pathname === '/m' || pathname?.startsWith('/m/')

  // Rutas de marketplace están completamente aisladas del ERP interno.
  // AuthProvider ejecuta /api/me al montar — NO debe correr para visitantes del marketplace.
  // CatalogoProvider carga inventario interno — tampoco aplica en marketplace.
  if (isMarketplace) {
    return <DndProvider>{children}</DndProvider>
  }

  return (
    <AuthProvider>
      <CatalogoProvider>
        <DndProvider>
          {children}
        </DndProvider>
      </CatalogoProvider>
    </AuthProvider>
  )
}