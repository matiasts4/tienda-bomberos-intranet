"use client"

import { useCallback, useEffect, useState } from "react"
import { api } from "@/lib/api"
import { Badge } from "@/components/ui/badge"
import { NotasGestion } from "@/components/prospeccion/NotasGestion"
import {
  History, Pencil, Phone, RefreshCw, Star, Trash2, UserPlus, Link2,
} from "lucide-react"
import { cn } from "@/lib/utils"

/** Entrada de la bitácora de auditoría de un contacto (solo administración). */
export type ActividadContacto = {
  id: number
  accion: string
  descripcion?: string | null
  cambios?: Record<string, any> | null
  origen?: string | null
  created_at: string
  user_nombre?: string | null
  user?: { id: number; name: string } | null
}

const ACCION_META: Record<string, { label: string; icon: React.ElementType; badge: string; color: string }> = {
  creado: { label: "Contacto creado", icon: UserPlus, badge: "bg-emerald-100 border-emerald-200", color: "text-emerald-600" },
  actualizado: { label: "Datos editados", icon: Pencil, badge: "bg-blue-100 border-blue-200", color: "text-blue-600" },
  eliminado: { label: "Contacto eliminado", icon: Trash2, badge: "bg-red-100 border-red-200", color: "text-red-600" },
  vinculos_actualizados: { label: "Vínculos actualizados", icon: Link2, badge: "bg-amber-100 border-amber-200", color: "text-amber-600" },
  gestion: { label: "Gestión comercial", icon: Phone, badge: "bg-slate-100 border-slate-200", color: "text-slate-600" },
  calificado: { label: "Contacto calificado", icon: Star, badge: "bg-emerald-100 border-emerald-200", color: "text-emerald-600" },
}

/** Acciones de prospección cuyas `cambios.notas` son lo que el ejecutivo anotó. */
const ACCIONES_CON_NOTAS = ["gestion", "calificado"]

/** Traduce el JSON de `cambios` a líneas legibles según el tipo de acción. */
export function describirCambios(accion: string, cambios?: Record<string, any> | null): string[] {
  if (!cambios) return []

  if (accion === "actualizado") {
    return Object.entries(cambios).map(([campo, valor]: [string, any]) => {
      const vacio = (v: any) => v === null || v === undefined || v === ""
      const antes = vacio(valor?.antes) ? "—" : String(valor.antes)
      const despues = vacio(valor?.despues) ? "—" : String(valor.despues)
      return `${campo}: ${antes} → ${despues}`
    })
  }

  if (accion === "vinculos_actualizados") {
    return Object.entries(cambios).flatMap(([relacion, valor]: [string, any]) => {
      const lineas: string[] = []
      if (valor?.agregados?.length) lineas.push(`${relacion}: + ${valor.agregados.join(", ")}`)
      if (valor?.quitados?.length) lineas.push(`${relacion}: − ${valor.quitados.join(", ")}`)
      return lineas
    })
  }

  return Object.entries(cambios)
    .filter(([, valor]) => valor !== null && valor !== undefined && valor !== "")
    .map(([clave, valor]) => `${clave}: ${typeof valor === "object" ? JSON.stringify(valor) : String(valor)}`)
}

type Props = {
  contactoId: number
  /** Si es false no se consulta nada (la bitácora es solo para administración). */
  enabled?: boolean
  className?: string
}

/**
 * Línea de tiempo de auditoría de un contacto: quién lo creó, editó, vinculó
 * o gestionó. El endpoint responde 403 a quien no sea superadmin/admin.
 */
export function BitacoraContacto({ contactoId, enabled = true, className }: Props) {
  const [items, setItems] = useState<ActividadContacto[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const fetchBitacora = useCallback(async () => {
    if (!enabled) return
    setLoading(true)
    setError(null)
    try {
      const data = await api.get<ActividadContacto[]>(`/api/contactos/${contactoId}/actividades`)
      setItems(Array.isArray(data) ? data : [])
    } catch (e: any) {
      setItems([])
      setError(e?.status === 403 ? "Solo la administración puede ver la bitácora." : (e?.message || "No se pudo cargar la bitácora."))
    } finally {
      setLoading(false)
    }
  }, [contactoId, enabled])

  useEffect(() => {
    fetchBitacora()
  }, [fetchBitacora])

  return (
    <div className={className}>
      <div className="flex items-center justify-between mb-4">
        <p className="text-xs text-slate-400 font-medium">
          Quién creó, editó, vinculó o gestionó este contacto.
        </p>
        <button
          onClick={fetchBitacora}
          disabled={loading}
          className="text-xs font-bold text-slate-500 hover:text-slate-800 transition-colors uppercase tracking-widest flex items-center gap-1.5 disabled:opacity-50"
        >
          <RefreshCw className={cn("size-3.5", loading && "animate-spin")} />
          Actualizar
        </button>
      </div>

      {loading ? (
        <div className="text-center py-10 border border-dashed rounded-xl bg-slate-50/50">
          <p className="text-sm text-slate-400 italic font-medium">Cargando bitácora…</p>
        </div>
      ) : error ? (
        <div className="text-center py-10 border border-dashed rounded-xl bg-slate-50/50">
          <p className="text-sm text-slate-400 italic font-medium">{error}</p>
        </div>
      ) : items.length === 0 ? (
        <div className="text-center py-10 border border-dashed rounded-xl bg-slate-50/50">
          <p className="text-sm text-slate-400 italic font-medium">Sin acciones registradas para este contacto.</p>
        </div>
      ) : (
        <div className="space-y-4">
          {items.map((item) => {
            const meta = ACCION_META[item.accion] || { label: item.accion, icon: History, badge: "bg-slate-100 border-slate-200", color: "text-slate-600" }
            const Icon = meta.icon
            // Las notas de una gestión (p. ej. el resumen de una calificación) se
            // muestran como lista aparte, no como una línea más de "clave: valor".
            const { notas, ...resto }: Record<string, any> = item.cambios ?? {}
            const notasGestion = ACCIONES_CON_NOTAS.includes(item.accion) && typeof notas === "string" ? notas : null
            const detalles = describirCambios(item.accion, notasGestion ? resto : item.cambios)
            const ejecutivo = item.user?.name || item.user_nombre || "Sistema"
            return (
              <div key={item.id} className="flex items-start gap-4 p-4 bg-slate-50/30 border border-slate-100 rounded-2xl transition-all hover:bg-white hover:shadow-md hover:border-slate-200">
                <div className={cn("size-12 rounded-full flex items-center justify-center shrink-0 shadow-sm border", meta.badge)}>
                  <Icon className={cn("size-5", meta.color)} />
                </div>
                <div className="min-w-0 flex-1 pt-0.5">
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-xs font-bold text-slate-800 uppercase tracking-wider">{meta.label}</p>
                    <span className="text-[11px] text-slate-400 font-bold whitespace-nowrap uppercase">
                      {new Date(item.created_at).toLocaleString("es-CL", { day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit" })}
                    </span>
                  </div>
                  <p className="text-sm text-slate-600 mt-1.5 font-semibold">{ejecutivo}</p>
                  {item.descripcion && (
                    <p className="text-sm text-slate-500 mt-1 font-medium leading-relaxed">{item.descripcion}</p>
                  )}
                  <NotasGestion notas={notasGestion} className="mt-3" />
                  {detalles.length > 0 && (
                    <ul className="mt-3 space-y-1">
                      {detalles.map((linea, i) => (
                        <li key={i} className="text-[11px] text-slate-500 font-mono bg-white border border-slate-100 rounded-lg px-2 py-1 break-words">
                          {linea}
                        </li>
                      ))}
                    </ul>
                  )}
                  {item.origen && (
                    <Badge variant="outline" className="mt-3 text-[10px] uppercase font-bold text-slate-500">
                      {item.origen}
                    </Badge>
                  )}
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
