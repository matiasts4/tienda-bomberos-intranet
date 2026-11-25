"use client"

import { useMemo, type ComponentType } from "react"
import Link from "next/link"
import {
  ArrowUpRight, ChevronRight, CircleGauge, Factory, PackageCheck, Scissors, ShoppingCart, Truck, Users,
} from "lucide-react"

import { AppHeader } from "@/components/app-header"
import { useAuthContext } from "@/components/auth-provider"

type Accent = "red" | "slate" | "emerald" | "indigo" | "amber"
type ModuleItem = {
  title: string; description: string; detail: string; href: string
  icon: ComponentType<{ className?: string; strokeWidth?: number }>
  permission: string; accent: Accent; badge?: string; featured?: boolean; adminOnly?: boolean
}
type ModuleSection = {
  id: string; title: string; description: string
  icon: ComponentType<{ className?: string }>; items: ModuleItem[]
}

const accentStyles: Record<Accent, { icon: string; dot: string; hover: string }> = {
  red: { icon: "bg-red-50 text-[#d8322e]", dot: "bg-[#E53935]", hover: "group-hover:text-[#c92d29]" },
  slate: { icon: "bg-slate-100 text-slate-700", dot: "bg-slate-500", hover: "group-hover:text-slate-950" },
  emerald: { icon: "bg-emerald-50 text-emerald-700", dot: "bg-emerald-500", hover: "group-hover:text-emerald-800" },
  indigo: { icon: "bg-indigo-50 text-indigo-700", dot: "bg-indigo-500", hover: "group-hover:text-indigo-800" },
  amber: { icon: "bg-amber-50 text-amber-700", dot: "bg-amber-500", hover: "group-hover:text-amber-800" },
}

const sections: ModuleSection[] = [
  {
    id: "comercial", title: "Comercial & Ventas", description: "Del primer contacto al cierre de la oportunidad.", icon: CircleGauge,
    items: [
      { title: "Cotizaciones", description: "Pipeline comercial, propuestas y seguimiento de cierres.", detail: "Oportunidades activas", href: "/comercial/cotizaciones", icon: ShoppingCart, permission: "Comercial", accent: "red", featured: true },
      { title: "Clientes & Cuerpos", description: "Directorio institucional, compañías y contactos clave.", detail: "Base comercial", href: "/comercial/clientes", icon: Users, permission: "Comercial", accent: "amber" },
    ],
  },
  {
    id: "operaciones", title: "Operaciones & Fábrica", description: "Producción, abastecimiento y entrega bajo una misma trazabilidad.", icon: Factory,
    items: [
      { title: "Fábrica & Corte", description: "Órdenes de producción, corte, fichas técnicas y calidad.", detail: "En confección", href: "/fabrica", icon: Factory, permission: "Fábrica", accent: "indigo", featured: true },
      { title: "Inventario PTE", description: "Stock disponible de productos terminados por bodega.", detail: "SKU y movimientos", href: "/inventario", icon: PackageCheck, permission: "Inventario", accent: "emerald" },
      { title: "Despacho", description: "Empaque, transporte y seguimiento de entregas.", detail: "Salida logística", href: "/despacho", icon: Truck, permission: "Despacho", accent: "slate" },
    ],
  },
]

function ModuleCard({ item }: { item: ModuleItem }) {
  const Icon = item.icon
  const styles = accentStyles[item.accent]
  return (
    <Link href={item.href} className={`group relative flex min-h-44 flex-col justify-between overflow-hidden rounded-2xl bg-white p-5 shadow-[0_1px_2px_rgba(15,23,42,0.06),0_8px_24px_rgba(15,23,42,0.04)] transition duration-200 ease-out hover:-translate-y-1 hover:shadow-[0_12px_30px_rgba(15,23,42,0.10)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E53935]/60 focus-visible:ring-offset-2 ${item.featured ? "sm:col-span-2 lg:min-h-52" : ""}`}>
      <div className="flex items-start justify-between gap-4">
        <div className={`flex size-11 items-center justify-center rounded-xl ${styles.icon}`}><Icon className="size-5" strokeWidth={1.8} /></div>
        <div className="flex items-center gap-2">
          {item.badge && <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">{item.badge}</span>}
          <ArrowUpRight className="size-4 text-slate-300 transition duration-200 group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-slate-700" />
        </div>
      </div>
      <div className="mt-7">
        <div className="mb-2 flex items-center gap-2 text-xs font-medium text-slate-500"><span className={`size-1.5 rounded-full ${styles.dot}`} />{item.detail}</div>
        <h3 className={`text-lg font-semibold tracking-[-0.02em] text-slate-900 transition-colors ${styles.hover}`}>{item.title}</h3>
        <p className="mt-1.5 max-w-xl text-sm leading-6 text-slate-500">{item.description}</p>
      </div>
    </Link>
  )
}

export default function ModulesPage() {
  const { user } = useAuthContext()
  const role = user?.role ?? "usuario"
  const isSuperadmin = role === "superadmin"

  const canAccess = (permission: string) => {
    if (isSuperadmin) return true
    if (!Array.isArray(user?.modulos)) return false
    return user.modulos.includes(permission)
  }

  const visibleSections = useMemo(() => sections
    .map((section) => ({ ...section, items: section.items.filter((item) => item.adminOnly ? isSuperadmin : canAccess(item.permission)) }))
    .filter((section) => section.items.length > 0), [isSuperadmin, user?.modulos])

  const firstName = user?.name?.trim().split(/\s+/)[0] || "Usuario"

  return (
    <div className="flex h-full flex-col bg-[#f5f6f8] text-slate-950 selection:bg-slate-200 selection:text-slate-950">
      <AppHeader title="Dashboard General" />
      <main className="flex-1 overflow-auto">
        <div className="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
          <h1 className="text-3xl font-semibold tracking-[-0.03em] text-slate-950 sm:text-4xl">
            Buenos días, {firstName}.
          </h1>

          <div className="mt-10 space-y-14 pb-10">
            {visibleSections.map((section) => {
              const SectionIcon = section.icon
              return <section key={section.id} aria-labelledby={`section-${section.id}`}>
                <div className="mb-5 flex items-end justify-between gap-5">
                  <div className="flex items-start gap-3"><div className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white shadow-sm"><SectionIcon className="size-4" /></div><div><h2 id={`section-${section.id}`} className="text-xl font-semibold tracking-[-0.025em] text-slate-950">{section.title}</h2><p className="mt-1 text-sm text-slate-500">{section.description}</p></div></div>
                  <span className="hidden items-center gap-1 text-xs font-medium text-slate-400 sm:flex">{section.items.length} {section.items.length === 1 ? "acceso" : "accesos"}<ChevronRight className="size-3.5" /></span>
                </div>
                <div className="grid auto-rows-fr gap-4 sm:grid-cols-2 lg:grid-cols-4">{section.items.map((item) => <ModuleCard key={`${section.id}-${item.title}`} item={item} />)}</div>
              </section>
            })}
          </div>

          {visibleSections.length === 0 && <div className="my-16 rounded-2xl bg-white px-6 py-14 text-center shadow-sm"><Scissors className="mx-auto size-7 text-slate-400" /><h2 className="mt-4 text-lg font-semibold text-slate-900">Aún no tienes módulos asignados</h2><p className="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Solicita a un administrador que habilite las áreas necesarias para tu trabajo.</p></div>}
        </div>
      </main>
    </div>
  )
}
