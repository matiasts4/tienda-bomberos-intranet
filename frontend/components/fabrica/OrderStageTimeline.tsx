"use client"

import { format } from "date-fns"
import { es } from "date-fns/locale"
import { CheckCircle2, Circle, Clock, Loader2, UserRound } from "lucide-react"
import { cn } from "@/lib/utils"
import { useCuttingOrderTimeline } from "@/hooks/use-cutting-order-log"
import type { StageHistoryEntry } from "@/lib/api/cutting-orders"
import { STAGE_LABELS, STAGE_STYLES, WORKSHOP_STAGES, workshopStageOf, type WorkshopStage } from "@/lib/workshop-stages"

interface OrderStageTimelineProps {
  orderId: string | number
  /** Intervalo de actualización en vivo (ms). */
  refreshInterval?: number
}

function formatStamp(value: string) {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return format(date, "dd/MM/yyyy · HH:mm", { locale: es })
}

function stageLabel(raw?: string | null) {
  if (!raw) return "Registro inicial"
  if (raw === "order-received") return "Recibida"
  const stage = workshopStageOf(raw)
  return stage ? STAGE_LABELS[stage] : raw
}

/** Último ingreso registrado a cada etapa (fecha, hora y responsable). */
function stageEntries(history: StageHistoryEntry[]) {
  const entries = new Map<WorkshopStage, StageHistoryEntry>()
  for (const entry of history) {
    const stage = workshopStageOf(entry.etapa_nueva)
    if (stage) entries.set(stage, entry)
  }
  return entries
}

/**
 * Línea de tiempo de trazabilidad de una orden de taller: muestra cada etapa
 * (Corte, Confección, Empaque, Terminado) con la fecha, hora y usuario que la registró.
 */
export function OrderStageTimeline({ orderId, refreshInterval = 10000 }: OrderStageTimelineProps) {
  const { timeline, stageHistory, error, isLoading, isValidating } = useCuttingOrderTimeline(orderId, refreshInterval)

  if (isLoading && !timeline) {
    return (
      <div className="flex items-center gap-2 p-6 text-muted-foreground">
        <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
        Cargando trazabilidad…
      </div>
    )
  }

  if (error && !timeline) {
    return <p role="alert" className="p-6 text-sm text-red-700">No se pudo cargar la línea de tiempo de la orden.</p>
  }

  if (!timeline) return null

  const current = workshopStageOf(timeline.current_stage)
  const currentIndex = current ? WORKSHOP_STAGES.indexOf(current) : -1
  const reached = stageEntries(stageHistory)

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-lg font-bold">OC-{timeline.code}</p>
          <p className="text-sm text-muted-foreground">
            Etapa actual: <span className="font-medium text-foreground">{current ? STAGE_LABELS[current] : stageLabel(timeline.status)}</span>
          </p>
        </div>
        <span className="flex items-center gap-1.5 text-xs text-muted-foreground" aria-live="polite">
          <span className={cn("h-2 w-2 rounded-full bg-emerald-500", isValidating && "animate-pulse")} aria-hidden="true" />
          En vivo
        </span>
      </div>

      <ol className="relative space-y-0" aria-label="Etapas de producción">
        {WORKSHOP_STAGES.map((stage, index) => {
          const entry = reached.get(stage)
          const done = index < currentIndex || (index === currentIndex && stage === "terminado")
          const active = index === currentIndex && stage !== "terminado"
          const isLast = index === WORKSHOP_STAGES.length - 1
          return (
            <li key={stage} className="relative flex gap-4 pb-6 last:pb-0">
              {!isLast && (
                <span
                  className={cn("absolute left-[15px] top-8 h-[calc(100%-2rem)] w-0.5", index < currentIndex ? STAGE_STYLES[stage].dot : "bg-border")}
                  aria-hidden="true"
                />
              )}
              <span className="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-background">
                {done ? (
                  <CheckCircle2 className="h-8 w-8 text-emerald-600" aria-hidden="true" />
                ) : active ? (
                  <span className={cn("flex h-8 w-8 items-center justify-center rounded-full ring-4 ring-offset-2", STAGE_STYLES[stage].dot, "ring-current/20")}>
                    <span className="h-3 w-3 animate-pulse rounded-full bg-white" />
                  </span>
                ) : (
                  <Circle className="h-8 w-8 text-muted-foreground/40" aria-hidden="true" />
                )}
              </span>
              <div className="min-w-0 pt-0.5">
                <p className={cn("font-semibold", !done && !active && "text-muted-foreground")}>
                  {STAGE_LABELS[stage]}
                  {active && <span className="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs font-medium">En curso</span>}
                </p>
                {entry ? (
                  <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                    <span className="flex items-center gap-1"><Clock className="h-3.5 w-3.5" aria-hidden="true" />{formatStamp(entry.created_at)}</span>
                    <span className="flex items-center gap-1"><UserRound className="h-3.5 w-3.5" aria-hidden="true" />{entry.user?.name ?? "Sistema"}</span>
                  </div>
                ) : (
                  <p className="mt-1 text-sm text-muted-foreground">{index > currentIndex ? "Pendiente" : "Sin registro de ingreso"}</p>
                )}
                {entry?.observaciones && <p className="mt-1 text-sm italic">“{entry.observaciones}”</p>}
              </div>
            </li>
          )
        })}
      </ol>

      {stageHistory.length > 0 && (
        <div>
          <h3 className="mb-2 text-sm font-semibold">Bitácora de cambios de etapa</h3>
          <ul className="divide-y rounded-lg border text-sm">
            {[...stageHistory].reverse().map(entry => (
              <li key={entry.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                <span>
                  {stageLabel(entry.etapa_anterior)} → <span className="font-medium">{stageLabel(entry.etapa_nueva)}</span>
                </span>
                <span className="text-muted-foreground">
                  {formatStamp(entry.created_at)} · {entry.user?.name ?? "Sistema"}
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}
