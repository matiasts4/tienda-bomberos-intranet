"use client"

import { memo, useMemo, useState } from "react"
import { api } from "@/lib/api"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"

type Estado = {
  id: number
  nombre: string
  color?: string | null
  scope?: string | null
  orden?: number | null
}

type Cotizacion = TarjetaNoVigente & {
  id: number
  numero?: string | null
  nombre?: string | null // Nuevo campo
  oportunidad_id?: number | null
  cliente_id?: string | null
  ejecutivo_id?: number | null
  user_id?: number | null
  creado_por_id?: number | null
  ejecutivo?: UsuarioRef | null
  creador?: UsuarioRef | null
  traspasos?: Traspaso[]
  tipo_destinatario?: TipoDestinatario | null
  estado_id: number
  fecha_creacion?: string | null // Para mostrar en la tarjeta
  fecha_vencimiento?: string | null
  plazo_produccion?: string | null
  fecha_venta?: string | null
  fecha_entrega?: string | null
  personalizacion_completada?: boolean
  estado_personalizacion?: string | null
  total?: number | null
  total_neto?: number | null
  iva?: number | null
  origen?: string | null
  cliente?: { id: string; nombre_empresa?: string | null } | null
  contacto?: { id: number; nombre: string } | null
  cuerpo?: { id: string; nombre: string } | null
  compania?: { id: string; nombre: string; cuerpo?: { id: string; nombre: string } | null } | null
  oportunidad?: { id: number; contacto?: { id: number; nombre: string } } | null
  detalles?: Array<{ id: number; sku: string; cantidad: number }> | null
  created_at?: string | null
  ultima_gestion?: {
    glosa: string
    fecha_gestion: string
    fecha_vencimiento_nueva: string
  } | null
  archivos?: Array<{
    id: number
    tipo: string
    nombre_archivo: string
    url: string
  }>
  motivo_rechazo?: string | null
  fecha_rechazo?: string | null
  gestiones?: Array<any> | null
}

function formatCLP(value?: number | null) {
  const n = typeof value === "number" ? value : 0
  return new Intl.NumberFormat("es-CL", { style: "currency", currency: "CLP", maximumFractionDigits: 0 }).format(n)
}

// Fechas `date` sin corrimiento por zona horaria (ver lib/vencimiento-cotizacion)
const formatDateDMY = (dateStr?: string | null) => leerFecha(dateStr)?.toLocaleDateString("es-CL") ?? null

import { useRouter } from "next/navigation"
import { CheckCircle, XCircle, Eye, Calendar, Palette, Package, ArrowRight, Lock } from "lucide-react" // Importamos iconos
import { cn } from "@/lib/utils"
import { ResponsableLinea } from "@/components/comercial/ResponsableLinea"
import type { Traspaso, UsuarioRef } from "@/lib/responsables"
import { DestinatarioBadge } from "@/components/comercial/DestinatarioBadge"
import { DESTINATARIO_NUMERO_CLASS, tipoDestinatarioDe, type TipoDestinatario } from "@/lib/destinatario"
import { MOTIVO_BADGE_CLASS, datosNoVigente, etiquetaMotivo, type TarjetaNoVigente } from "@/lib/no-vigentes"
import { estadoVencimiento, leerFecha, vencimientoDe } from "@/lib/vencimiento-cotizacion"

export const CotizacionKanbanCard = memo(function CotizacionKanbanCard({
  cotizacion,
  estadosCotizacion,
  onEstadoUpdated,
  onViewDetail,
  onViewGestion,
  onViewArchivos,
  onViewNoVigente,
  onViewPersonalizacion,
  onSendPersonalizacion,
  reactivacionBloqueada = false,
}: {
  cotizacion: Cotizacion
  estadosCotizacion: Estado[]
  onEstadoUpdated?: () => void
  onViewDetail?: (c: Cotizacion) => void
  onViewGestion?: (c: Cotizacion) => void
  onViewArchivos?: (c: Cotizacion) => void
  onViewNoVigente?: (c: Cotizacion) => void
  onViewPersonalizacion?: (c: Cotizacion) => void
  onSendPersonalizacion?: (c: Cotizacion) => void
  /** No Vigente que este usuario no puede devolver al flujo (finalización administrativa). */
  reactivacionBloqueada?: boolean
}) {
  const router = useRouter()
  const organizacion = cotizacion.cuerpo?.nombre || cotizacion.compania?.nombre || cotizacion.cliente?.nombre_empresa
  const contactoCotizacion = cotizacion.contacto?.nombre || cotizacion.oportunidad?.contacto?.nombre
  // Sin organización va a nombre de la persona (persona natural): ella es el cliente, como en el PDF
  const nombreEmpresa = organizacion || contactoCotizacion || `Cotización #${cotizacion.id}`
  const nombreContacto = organizacion ? contactoCotizacion : null

  // ✅ cantidad de líneas (items)
  const itemsCount = cotizacion.detalles?.length ?? 0

  // ✅ total de artículos (suma de cantidad)
  const articulosCount = useMemo(() => {
    const dets = cotizacion.detalles
    if (!Array.isArray(dets)) return 0
    return dets.reduce((acc, d) => acc + (Number(d?.cantidad) || 0), 0)
  }, [cotizacion.detalles])

  const titulo = cotizacion.numero ? cotizacion.numero : `CT-${String(cotizacion.id).padStart(4, "0")}`

  // Lógica de Vencimiento y Colores
  const vencimientoDateStr = vencimientoDe(cotizacion)
  const vencimiento = formatDateDMY(vencimientoDateStr) ?? "sin fecha"

  const estadoGestion = useMemo(
    () => estadoVencimiento(cotizacion),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [vencimientoDateStr, cotizacion.ultima_gestion]
  )

  const footerColorClass = {
    normal: "bg-gray-100 text-muted-foreground hover:bg-gray-200", // Darker for visibility
    gestionada: "bg-yellow-100 text-yellow-800 hover:bg-yellow-200",
    por_vencer: "bg-orange-100 text-orange-800 hover:bg-orange-200",
    vencida: "bg-red-100 text-red-800 hover:bg-red-200",
  }[estadoGestion]

  const footerText = useMemo(() => {
    // Labels solicitados: "Vencida", "X° Reagendada", "Nuevo"
    const numReagendada = cotizacion.gestiones?.length || (cotizacion.ultima_gestion ? 1 : 0)
    const labelGestion = numReagendada > 0 ? `${numReagendada}° Reagendada` : "Nuevo"

    if (estadoGestion === "vencida") return `Vencida - Venció el ${vencimiento}`

    // Si tiene gestión (Reagendada)
    if (cotizacion.ultima_gestion) {
      return `${labelGestion} - Vence ${vencimiento}`
    }

    // Si no tiene gestión (Nuevo) pero está por vencer
    if (estadoGestion === "por_vencer") {
      return `Nuevo - Vence ${vencimiento}`
    }

    // Normal (Nuevo)
    return `Nuevo - Vence ${vencimiento}`
  }, [estadoGestion, cotizacion.ultima_gestion, cotizacion.gestiones, vencimiento])

  // Plazo de entrega (Ventas): fecha_entrega = fecha_venta + plazo de producción
  const entrega = useMemo(() => {
    if (!cotizacion.fecha_entrega) return null
    const now = new Date()
    now.setHours(0, 0, 0, 0)
    const [y, m, d] = cotizacion.fecha_entrega.slice(0, 10).split("-").map(Number)
    const date = new Date(y, m - 1, d)
    const diffDays = Math.round((date.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
    const fecha = date.toLocaleDateString("es-CL")

    if (diffDays < 0) {
      const n = Math.abs(diffDays)
      return { estado: "atrasada", texto: `Entrega atrasada ${n} ${n === 1 ? "día" : "días"} - ${fecha}` }
    }
    if (diffDays === 0) return { estado: "por_vencer", texto: `Entrega hoy - ${fecha}` }
    if (diffDays <= 5) return { estado: "por_vencer", texto: `Entrega en ${diffDays} ${diffDays === 1 ? "día" : "días"} - ${fecha}` }
    return { estado: "a_tiempo", texto: `Entrega en ${diffDays} días - ${fecha}` }
  }, [cotizacion.fecha_entrega])

  const entregaColorClass = entrega && {
    a_tiempo: "bg-gray-100 text-muted-foreground",
    por_vencer: "bg-orange-100 text-orange-800",
    atrasada: "bg-red-100 text-red-800",
  }[entrega.estado]

  // Verificar estado actual
  const estadoActual = estadosCotizacion.find(e => e.id === cotizacion.estado_id)?.nombre
  const noVigente = datosNoVigente(cotizacion, estadoActual)

  return (
    <div
      className="rounded-xl border bg-white p-4 shadow-sm hover:border-primary/50 hover:shadow-md transition-all cursor-pointer relative overflow-hidden flex flex-col justify-between min-h-[160px]"
      onClick={() => onViewDetail?.(cotizacion)}
    >
      <div>
        <div className="flex flex-col min-w-0">
          {/* Línea 1: Número | Fecha Creación */}
          <div className="flex items-center justify-between gap-2">
            <span className={cn("text-sm font-semibold whitespace-nowrap", DESTINATARIO_NUMERO_CLASS[tipoDestinatarioDe(cotizacion)])}>{titulo}</span>
            <span className="text-xs text-muted-foreground whitespace-nowrap flex-shrink-0">
              {formatDateDMY(cotizacion.fecha_creacion || cotizacion.created_at)}
            </span>
          </div>

          {/* Línea 2: Cliente (Cuerpo/Compañía) */}
          <div className="mt-1 flex items-center justify-between gap-2">
            <div className="truncate text-sm font-semibold text-slate-800">{nombreEmpresa}</div>
            <DestinatarioBadge tipo={tipoDestinatarioDe(cotizacion)} />
          </div>

          {/* Línea 3: Contacto (Si existe) */}
          {nombreContacto && (
            <div className="truncate text-[13px] text-slate-600 mt-0.5">
              {nombreContacto}
            </div>
          )}

          {/* Línea 4: Nombre de la Cotización */}
          <div className="truncate text-xs font-medium text-slate-500 mt-0.5">
            {cotizacion.nombre || "Sin nombre"}
          </div>

          {/* Línea 5: ejecutivo responsable (y quien la creó, si es otro) */}
          <ResponsableLinea tarjeta={cotizacion} className="mt-1" />
        </div>

        <div className="mt-2 space-y-1 text-sm text-muted-foreground">
          <div className="flex items-center justify-between">
            {/* ✅ ahora mostramos total de artículos */}
            <span>
              {itemsCount} ítems
              <span className="text-muted-foreground"> • {articulosCount} artículos</span>
            </span>

            <span className="font-semibold text-foreground">{formatCLP(
              (typeof cotizacion.total === "number" && cotizacion.total > 0)
                ? cotizacion.total
                : (typeof cotizacion.total_neto === "number" && cotizacion.total_neto > 0)
                  ? cotizacion.total_neto
                  : (cotizacion.detalles ?? []).reduce((acc, it) => acc + (it.cantidad ?? 0) * 0, 0) // fallback mínimo
            )}
            </span>
          </div>
        </div>
      </div>

      {/* ✅ Status Bar for "Cotización Enviada" */}
      {estadoActual === "Cotización Enviada" && (
        <div
          className={cn(
            "mt-3 -mx-4 -mb-4 px-4 py-2 text-xs font-medium text-center cursor-pointer transition-colors flex items-center justify-center gap-1",
            footerColorClass
          )}
          onClick={(e) => {
            e.stopPropagation()
            onViewGestion?.(cotizacion)
          }}
        >
          {footerText}
        </div>
      )}

      {/* BÓTON DE PERSONALIZACIÓN SÓLO PARA "Ventas" */}
      {(estadoActual === "Aprobada por Cliente" || estadoActual === "Ventas") && (
        <div className="mt-3 -mx-4 -mb-4 flex flex-col font-sans">
          {entrega && (
            <div
              className={cn("px-3 py-2 flex items-center justify-center gap-1 text-xs font-medium", entregaColorClass)}
              title={`Plazo: ${cotizacion.plazo_produccion?.trim() || "20 dias"}`}
            >
              <Calendar className="h-3.5 w-3.5" />
              <span>{entrega.texto}</span>
            </div>
          )}
          <div
            className="px-3 py-2 bg-slate-100 border-t flex items-center justify-between text-xs cursor-pointer hover:bg-slate-200 transition-colors"
            onClick={(e) => {
              e.stopPropagation()
              onViewArchivos?.(cotizacion)
            }}
          >
            {/* Checklist Items */}
            {[
              { key: 'orden_venta', label: 'OV' },
              { key: 'facturacion', label: 'FAC' },
              { key: 'pago', label: 'PAGO' }
            ].map(doc => {
              const uploaded = cotizacion.archivos?.some(a => a.tipo === doc.key)
              return (
                <div key={doc.key} className="flex items-center gap-1.5" title={uploaded ? "Adjuntado" : "Pendiente"}>
                  {uploaded ? (
                    <CheckCircle className="h-3.5 w-3.5 text-green-600" />
                  ) : (
                    <XCircle className="h-3.5 w-3.5 text-gray-400" />
                  )}
                  <span className={cn("font-medium", uploaded ? "text-green-700" : "text-gray-500")}>
                    {doc.label}
                  </span>
                </div>
              )
            })}
          </div>
          {/* Botón / Estado de Personalización */}
          <div
            className={cn(
              "px-3 py-2 border-t flex flex-col gap-1 text-xs transition-colors",
              cotizacion.estado_personalizacion === 'sent_to_personalization'
                ? "bg-emerald-50 text-emerald-700 border-emerald-100"
                : cotizacion.estado_personalizacion === 'direct_delivery'
                  ? "bg-teal-50 text-teal-700 border-teal-100"
                  : cotizacion.estado_personalizacion === 'configured'
                    ? "bg-indigo-50 text-indigo-700 border-indigo-100"
                    : "bg-amber-50 text-amber-700 border-amber-100"
            )}
          >
            {cotizacion.estado_personalizacion === 'direct_delivery' ? (
              <div className="flex flex-col gap-1.5 py-0.5">
                {/* Botón directo para ir a Despacho */}
                <button
                  type="button"
                  className="w-full flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-md bg-teal-600 hover:bg-teal-700 text-white font-medium shadow-sm active:scale-95 transition-all text-xs"
                  onClick={(e) => {
                    e.stopPropagation()
                    router.push('/despacho')
                  }}
                >
                  <Package className="h-3.5 w-3.5" />
                  <span>Ir a Despacho en Bodega</span>
                  <ArrowRight className="h-3.5 w-3.5 ml-0.5 opacity-80" />
                </button>
                {/* Enlace secundario para revisar la configuración si se desea */}
                <div
                  className="text-[10px] text-center text-teal-700/80 hover:text-teal-900 cursor-pointer hover:underline"
                  onClick={(e) => {
                    e.stopPropagation()
                    onViewPersonalizacion?.(cotizacion)
                  }}
                >
                  Entrega Directa (Sin Taller) · Ver detalle
                </div>
              </div>
            ) : (
              <>
                {/* Fila de Estado / Configurar */}
                <div
                  className="flex items-center justify-center gap-1.5 font-semibold cursor-pointer hover:underline"
                  onClick={(e) => {
                    e.stopPropagation()
                    onViewPersonalizacion?.(cotizacion)
                  }}
                >
                  {cotizacion.estado_personalizacion === 'sent_to_personalization' || cotizacion.estado_personalizacion === 'configured' ? (
                    <>
                      <CheckCircle className="h-4 w-4" />
                      <span>Personalización Configurada</span>
                    </>
                  ) : (
                    <>
                      <Palette className="h-4 w-4" />
                      <span>Configurar Personalización</span>
                    </>
                  )}
                </div>

                {/* Fila de Enviar (Sólo si está configurada con prendas para taller) */}
                {cotizacion.estado_personalizacion === 'configured' && (
                  <button
                    className="mt-1 w-full flex items-center justify-center gap-1 py-1.5 rounded-md bg-indigo-600 text-white font-medium shadow-sm hover:bg-indigo-700 active:scale-95 transition-all disabled:opacity-50"
                    onClick={(e) => {
                      e.stopPropagation()
                      onSendPersonalizacion?.(cotizacion)
                    }}
                  >
                    Enviar a Personalización
                  </button>
                )}

                {/* Label de Enviado a Taller */}
                {cotizacion.estado_personalizacion === 'sent_to_personalization' && (
                  <div className="mt-1 flex items-center justify-center gap-1 text-[10px] font-medium text-emerald-600">
                    ✔️ Enviado a Módulo Personalización
                  </div>
                )}
              </>
            )}
          </div>
        </div>
      )}

      {/* Barra de "No Vigentes": motivo y fecha en que salió del flujo */}
      {noVigente && (
        <div
          className="mt-3 -mx-4 -mb-4 px-3 py-2 bg-slate-50 border-t flex items-center justify-between gap-2 text-xs cursor-pointer hover:bg-slate-100 transition-colors"
          onClick={(e) => {
            e.stopPropagation()
            onViewNoVigente?.(cotizacion)
          }}
        >
          <span className={cn("truncate rounded-full px-2 py-0.5 font-medium", MOTIVO_BADGE_CLASS[noVigente.motivo])}>
            {etiquetaMotivo(noVigente)}
          </span>
          <span className="flex items-center gap-1 whitespace-nowrap text-muted-foreground">
            {reactivacionBloqueada && (
              <span title="Solo un superadmin puede devolverla al flujo" aria-label="Solo un superadmin puede devolverla al flujo">
                <Lock className="h-3 w-3" />
              </span>
            )}
            {formatDateDMY(noVigente.fecha) || "--"}
          </span>
          <div className="flex items-center gap-1 whitespace-nowrap text-muted-foreground hover:text-foreground">
            <Eye className="h-3.5 w-3.5" />
            <span>Ver motivo</span>
          </div>
        </div>
      )}
    </div>
  )
})
