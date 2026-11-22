"use client"

import { useEffect, useMemo, useState } from "react"
import { format, isBefore, startOfDay } from "date-fns"
import { es } from "date-fns/locale"
import { CalendarClock, CheckCircle2, Loader2, Shirt, UserRound, X } from "lucide-react"
import { cn } from "@/lib/utils"
import type { CuttingOrder } from "@/lib/api/cutting-orders"
import {
  ADVANCE_LABELS,
  NEXT_STAGE,
  STAGE_LABELS,
  STAGE_STYLES,
  isPendingStart,
  workshopStageOf,
  type WorkshopStage,
} from "@/lib/workshop-stages"

/** Tiempo que el botón permanece en modo "confirmar" antes de volver a su estado inicial. */
const CONFIRM_WINDOW_MS = 5000

interface WorkshopOrderCardProps {
  order: CuttingOrder
  advancing: boolean
  disabled?: boolean
  onAdvance: (order: CuttingOrder, target: WorkshopStage) => Promise<void> | void
}

function safeFormat(value: string | null | undefined, pattern: string) {
  if (!value) return null
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return null
  return format(date, pattern, { locale: es })
}

/**
 * Tarjeta táctil de una orden de corte para la tablet de planta (10").
 * El avance de etapa usa doble toque (tocar y confirmar) para evitar cambios
 * accidentales con guantes; todos los controles miden al menos 48x48 px.
 */
export function WorkshopOrderCard({ order, advancing, disabled, onAdvance }: WorkshopOrderCardProps) {
  const [confirming, setConfirming] = useState(false)

  const stage = workshopStageOf(order.status) ?? "corte"
  const pendingStart = isPendingStart(order.status)
  const target: WorkshopStage | null = pendingStart ? "corte" : NEXT_STAGE[stage]
  const styles = STAGE_STYLES[stage]

  useEffect(() => {
    if (!confirming) return
    const timer = window.setTimeout(() => setConfirming(false), CONFIRM_WINDOW_MS)
    return () => window.clearTimeout(timer)
  }, [confirming])

  useEffect(() => {
    setConfirming(false)
  }, [order.status])

  const sizeBreakdown = useMemo(() => {
    const totals = new Map<string, number>()
    for (const item of order.items ?? []) {
      const key = item.size?.trim() || "Única"
      totals.set(key, (totals.get(key) ?? 0) + Number(item.quantity || 0))
    }
    return Array.from(totals.entries())
  }, [order.items])

  const totalUnits = order.total_unidades
    ?? order.total_quantity
    ?? (order.items ?? []).reduce((sum, item) => sum + Number(item.quantity || 0), 0)

  const lastChange = order.historial_etapas?.length
    ? order.historial_etapas[order.historial_etapas.length - 1]
    : null

  const dueDate = order.fecha_compromiso ? new Date(`${order.fecha_compromiso}T00:00:00`) : null
  const overdue = Boolean(dueDate && stage !== "terminado" && isBefore(dueDate, startOfDay(new Date())))

  const institution = order.compania?.nombre
    ? `${order.compania.nombre}${order.cuerpo?.nombre ? ` · ${order.cuerpo.nombre}` : ""}`
    : order.cuerpo?.nombre || order.client || "Cliente sin asignar"

  const actionLabel = pendingStart ? "Iniciar Corte" : ADVANCE_LABELS[stage]

  async function handlePress() {
    if (!target || advancing || disabled) return
    if (!confirming) {
      setConfirming(true)
      return
    }
    setConfirming(false)
    await onAdvance(order, target)
  }

  return (
    <article
      className={cn(
        "rounded-xl border-2 border-l-8 bg-card p-4 shadow-sm transition-shadow",
        styles.accent,
        advancing && "opacity-70",
      )}
      aria-busy={advancing}
    >
      <header className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-xl font-bold tracking-tight">OC-{order.code}</p>
          <p className="truncate text-base font-medium" title={institution}>{institution}</p>
        </div>
        <span className={cn("shrink-0 rounded-full px-3 py-1 text-sm font-semibold", styles.soft)}>
          {pendingStart ? "Por iniciar" : STAGE_LABELS[stage]}
        </span>
      </header>

      <div className="mt-3 flex items-center gap-2 text-base">
        <Shirt className="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
        <span className="truncate" title={order.selected_product}>{order.selected_product || "Prenda sin especificar"}</span>
        <span className="ml-auto shrink-0 text-lg font-bold tabular-nums">{totalUnits} u.</span>
      </div>

      {sizeBreakdown.length > 0 && (
        <ul className="mt-3 flex flex-wrap gap-2" aria-label="Unidades por talla">
          {sizeBreakdown.map(([size, quantity]) => (
            <li key={size} className="rounded-md border bg-muted/60 px-2.5 py-1 text-sm font-medium tabular-nums">
              {size} × {quantity}
            </li>
          ))}
        </ul>
      )}

      <dl className="mt-3 grid grid-cols-1 gap-1.5 text-sm text-muted-foreground sm:grid-cols-2">
        {dueDate && (
          <div className={cn("flex items-center gap-1.5", overdue && "font-semibold text-red-700")}>
            <CalendarClock className="h-4 w-4" aria-hidden="true" />
            <dt className="sr-only">Fecha compromiso</dt>
            <dd>{overdue ? "Atrasada · " : "Entrega "}{format(dueDate, "dd MMM", { locale: es })}</dd>
          </div>
        )}
        {lastChange && (
          <div className="flex items-center gap-1.5">
            <UserRound className="h-4 w-4" aria-hidden="true" />
            <dt className="sr-only">Último movimiento</dt>
            <dd className="truncate">
              {safeFormat(lastChange.created_at, "dd/MM HH:mm")} · {lastChange.user?.name ?? "Sistema"}
            </dd>
          </div>
        )}
      </dl>

      {order.cotizacion?.numero && (
        <p className="mt-2 text-xs text-muted-foreground">Cotización {order.cotizacion.numero}</p>
      )}

      {target ? (
        <div className="mt-4 flex gap-2">
          <button
            type="button"
            onClick={handlePress}
            disabled={advancing || disabled}
            className={cn(
              "flex min-h-16 flex-1 items-center justify-center gap-2 rounded-lg px-4 text-lg font-semibold",
              "transition-transform duration-100 active:scale-[0.97] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-ring/50",
              "disabled:cursor-not-allowed disabled:opacity-60",
              confirming ? "bg-red-600 text-white hover:bg-red-700 active:bg-red-800 animate-pulse" : styles.button,
            )}
          >
            {advancing ? (
              <>
                <Loader2 className="h-6 w-6 animate-spin" aria-hidden="true" />
                Guardando…
              </>
            ) : confirming ? (
              <>
                <CheckCircle2 className="h-6 w-6" aria-hidden="true" />
                Tocar otra vez para confirmar
              </>
            ) : (
              actionLabel
            )}
          </button>
          {confirming && !advancing && (
            <button
              type="button"
              onClick={() => setConfirming(false)}
              className="flex min-h-16 min-w-16 items-center justify-center rounded-lg border-2 bg-background text-foreground transition-transform active:scale-[0.97] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-ring/50"
              aria-label="Cancelar cambio de etapa"
            >
              <X className="h-7 w-7" aria-hidden="true" />
            </button>
          )}
        </div>
      ) : (
        <p className="mt-4 flex min-h-12 items-center justify-center gap-2 rounded-lg bg-emerald-50 text-base font-semibold text-emerald-800">
          <CheckCircle2 className="h-5 w-5" aria-hidden="true" />
          Lista para despacho
        </p>
      )}
    </article>
  )
}
