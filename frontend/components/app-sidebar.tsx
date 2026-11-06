"use client"

import Link from "next/link"
import { usePathname, useRouter } from "next/navigation"
import {
  LayoutDashboard,
  ShoppingCart,
  Package,
  Factory,
  Palette,
  Settings,
  ChevronRight,
  LogOut,
  Warehouse,
  AlertTriangle,
  Calendar,
  Sparkles,
  PhoneCall,
  Truck,
  QrCode,
  Users,
  Store,
  MapPinned,
} from "lucide-react"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { useAuthContext } from "@/components/auth-provider"

function makeInitials(value: string) {
  const parts = value
    .trim()
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
  if (parts.length === 0) return "U"
  return parts.map((p) => p[0]?.toUpperCase()).join("")
}

export function AppSidebar() {
  const pathname = usePathname()
  const router = useRouter()
  const { user, logout, loading } = useAuthContext()

  const displayName = user?.name || user?.email || "Usuario"
  const displayRole = user?.role || "Usuario"
  const initials = makeInitials(displayName)
  const isSuperadmin = displayRole === "superadmin"

  // Helper para verificar permiso de módulo
  const canAccess = (moduleId: string) => {
    if (isSuperadmin) return true
    if (!Array.isArray(user?.modulos)) return true // legacy fallback
    return user.modulos.includes(moduleId)
  }

  const handleLogout = async () => {
    try {
      await logout()
    } finally {
      router.replace("/login")
      router.refresh()
    }
  }

  return (
    <Sidebar>
      <SidebarHeader className="border-b border-sidebar-border">
        <div className="flex items-center gap-2 px-2 py-4">
          <div className="flex size-14 items-center justify-center">
            <img src="/tb-bomberos-logo.png" alt="Tienda Bomberos" className="max-h-full max-w-full object-contain" />
          </div>
          <div className="flex flex-col">
            <span className="text-sm font-semibold">Tienda Bomberos</span>
            <span className="text-xs text-muted-foreground">Sistema de Gestión</span>
          </div>
        </div>
      </SidebarHeader>

      <SidebarContent className="space-y-1">

        {/* 1. PRINCIPAL / GENERAL */}
        <SidebarGroup>
          <SidebarGroupLabel>Principal</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              <SidebarMenuItem key="dashboard">
                <SidebarMenuButton asChild isActive={pathname === "/modulos"}>
                  <Link href="/modulos">
                    <LayoutDashboard className="size-4 text-slate-700" />
                    <span>Dashboard General</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>

              {canAccess("Centro de Alertas") && (
                <SidebarMenuItem key="alertas">
                  <SidebarMenuButton asChild isActive={pathname === "/alertas"}>
                    <Link href="/alertas">
                      <AlertTriangle className="size-4 text-orange-500" />
                      <span>Centro de Alertas</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              )}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        {/* 2. COMERCIAL & VENTAS */}
        {canAccess("Comercial") && (
          <SidebarGroup>
            <SidebarGroupLabel>Comercial</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                <SidebarMenuItem key="cotizaciones">
                  <SidebarMenuButton asChild isActive={pathname === "/comercial/cotizaciones" || pathname === "/comercial"}>
                    <Link href="/comercial/cotizaciones">
                      <ShoppingCart className="size-4 text-blue-600" />
                      <span>Cotizaciones</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>

                <SidebarMenuItem key="clientes">
                  <SidebarMenuButton asChild isActive={pathname === "/comercial/clientes"}>
                    <Link href="/comercial/clientes">
                      <Users className="size-4 text-amber-600" />
                      <span>Clientes & Cuerpos</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>

                <SidebarMenuItem key="agenda">
                  <SidebarMenuButton asChild isActive={pathname === "/comercial/agenda"}>
                    <Link href="/comercial/agenda">
                      <Calendar className="size-4 text-purple-600" />
                      <span>Agenda Comercial</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>

                <SidebarMenuItem key="prospeccion">
                  <SidebarMenuButton asChild isActive={pathname.startsWith("/comercial/prospeccion")}>
                    <Link href="/comercial/prospeccion">
                      <PhoneCall className="size-4 text-emerald-600" />
                      <span>Prospección</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>

                <SidebarMenuItem key="red-bomberos">
                  <SidebarMenuButton asChild isActive={pathname.startsWith("/comercial/red-bomberos")}>
                    <Link href="/comercial/red-bomberos">
                      <MapPinned className="size-4 text-red-600" />
                      <span>Red de Bomberos</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>

                <SidebarMenuItem key="qr-manual">
                  <SidebarMenuButton asChild isActive={pathname === "/qr-manual"}>
                    <Link href="/qr-manual">
                      <QrCode className="size-4 text-slate-600" />
                      <span>QR Manual</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        )}

        {/* 3. PORTALES BOMBEROS (MARKETPLACES) */}
        {canAccess("Marketplaces") && (
          <SidebarGroup>
            <SidebarGroupLabel>Portales Bomberos</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                <SidebarMenuItem key="marketplaces">
                  <SidebarMenuButton asChild isActive={pathname.startsWith("/marketplaces")}>
                    <Link href="/marketplaces">
                      <Store className="size-4 text-red-600" />
                      <span>Marketplaces</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        )}

        {/* 4. PRODUCCIÓN & OPERACIONES */}
        {(canAccess("Fábrica") || canAccess("Inventario") || canAccess("Inventario MP") || canAccess("Despacho")) && (
          <SidebarGroup>
            <SidebarGroupLabel>Operaciones & Fábrica</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {canAccess("Fábrica") && (
                  <SidebarMenuItem key="fabrica">
                    <SidebarMenuButton asChild isActive={pathname.startsWith("/fabrica")}>
                      <Link href="/fabrica">
                        <Factory className="size-4 text-indigo-600" />
                        <span>Fábrica & Corte</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                )}

                {canAccess("Inventario") && (
                  <SidebarMenuItem key="inventario-pte">
                    <SidebarMenuButton asChild isActive={pathname.startsWith("/inventario") && !pathname.startsWith("/inventarioMP")}>
                      <Link href="/inventario">
                        <Package className="size-4 text-teal-600" />
                        <span>Inventario PTE</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                )}

                {canAccess("Inventario MP") && (
                  <SidebarMenuItem key="inventario-mp">
                    <SidebarMenuButton asChild isActive={pathname.startsWith("/inventarioMP")}>
                      <Link href="/inventarioMP">
                        <Warehouse className="size-4 text-amber-700" />
                        <span>Inventario MP</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                )}

                {canAccess("Despacho") && (
                  <SidebarMenuItem key="despacho">
                    <SidebarMenuButton asChild isActive={pathname.startsWith("/despacho")}>
                      <Link href="/despacho">
                        <Truck className="size-4 text-cyan-600" />
                        <span>Despacho</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                )}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        )}

        {/* 5. PERSONALIZACIÓN & TALLER */}
        {canAccess("Personalización") && (
          <SidebarGroup>
            <SidebarGroupLabel>Personalización</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                <SidebarMenuItem key="pre-venta">
                  <SidebarMenuButton asChild isActive={pathname === "/personalizacion/pre-venta"}>
                    <Link href="/personalizacion/pre-venta">
                      <Sparkles className="size-4 text-rose-500" />
                      <span>Pre-Venta</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>

                <SidebarMenuItem key="personalizacion-hub">
                  <SidebarMenuButton asChild isActive={pathname === "/personalizacion"}>
                    <Link href="/personalizacion">
                      <Palette className="size-4 text-rose-600" />
                      <span>Taller Personalización</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>

                {["Bordado", "Estampado"].map((proceso) => {
                  const url = `/personalizacion/${proceso.toLowerCase()}`
                  return (
                    <SidebarMenuItem key={proceso}>
                      <SidebarMenuButton asChild isActive={pathname === url}>
                        <Link href={url}>
                          <Palette className="size-4 text-slate-500" />
                          <span>{proceso}</span>
                        </Link>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  )
                })}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        )}

        {/* 6. SISTEMA / ADMINISTRACIÓN */}
        {(isSuperadmin || displayRole === "admin") && (
          <SidebarGroup>
            <SidebarGroupLabel>Sistema</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                <SidebarMenuItem key="administracion">
                  <SidebarMenuButton asChild isActive={pathname.startsWith("/administracion")}>
                    <Link href="/administracion">
                      <Settings className="size-4 text-slate-600" />
                      <span>Administración</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        )}

      </SidebarContent>

      <SidebarFooter className="border-t border-sidebar-border">
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              size="lg"
              className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
              asChild
            >
              <Link href="/administracion">
                <Avatar className="size-8 rounded-lg">
                  <AvatarImage src="/placeholder-user.jpg" alt={displayName} />
                  <AvatarFallback className="rounded-lg bg-[#E53935] text-white">{initials}</AvatarFallback>
                </Avatar>
                <div className="grid flex-1 text-left text-sm leading-tight">
                  <span className="truncate font-semibold">{displayName}</span>
                  <span className="truncate text-xs text-muted-foreground">{displayRole}</span>
                </div>
                <ChevronRight className="ml-auto size-4" />
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>

          <SidebarMenuItem>
            <SidebarMenuButton onClick={handleLogout} disabled={loading} className="text-destructive">
              <LogOut className="size-4" />
              <span>{loading ? "Cerrando sesión..." : "Cerrar sesión"}</span>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
    </Sidebar>
  )
}