"use client"

import { useEffect, useMemo, useState } from "react"
import { format } from "date-fns"
import { es } from "date-fns/locale"
import { CheckCircle2, ClipboardList, Factory, Loader2, Truck } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { cn, formatCLP } from "@/lib/utils"
import {
  HISTORY_EVENT_LABELS,
  fetchInstitutionalHistory,
  type HistoryEventType,
  type InstitutionKind,
  type InstitutionalHistoryResponse,
} from "@/lib/api/institutional-history"
import { STAGE_LABELS, workshopStageOf } from "@/lib/workshop-stages"

const EVENT_STYLES: Record<HistoryEventType, { icon: typeof ClipboardList; tone: string }> = {
  approved_quote: { icon: ClipboardList, tone: "bg-green-100 text-green-800" },
  workshop_order: { icon: Factory, tone: "bg-blue-100 text-blue-800" },
  delivered_order: { icon: Truck, tone: "bg-violet-100 text-violet-800" },
}

const ALL_YEARS = "todos"

function eventDate(value: string | null) {
  if (!value) return "—"
  const date = new Date(value.length === 10 ? `${value}T00:00:00` : value)
  return Number.isNaN(date.getTime()) ? value : format(date, "dd MMM yyyy", { locale: es })
}

function statusLabel(type: HistoryEventType, status: string | null) {
  if (!status) return "—"
  if (type !== "workshop_order") return status
  const stage = workshopStageOf(status)
  return stage ? STAGE_LABELS[stage] : status === "order-received" ? "Por iniciar" : status
}

/**
 * Historial unificado de compras institucionales de un Cuerpo o Compañía:
 * cotizaciones aprobadas, órdenes de taller y pedidos entregados en orden cronológico.
 */
export function InstitutionalOrdersHistory({ kind, id }: { kind: InstitutionKind; id: string }) {
  const [data, setData] = useState<InstitutionalHistoryResponse | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [year, setYear] = useState<string>(ALL_YEARS)

  const years = useMemo(() => {
    const current = new Date().getFullYear()
    return Array.from({ length: 6 }, (_, index) => String(current - index))
  }, [])

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)
    fetchInstitutionalHistory(kind, id, year === ALL_YEARS ? {} : { year: Number(year) })
      .then(response => { if (!cancelled) setData(response) })
      .catch(err => { if (!cancelled) setError(err?.message || "No se pudo cargar el historial institucional") })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [kind, id, year])

  const summary = data?.summary
  const timeline = data?.timeline ?? []

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-muted-foreground">
          {kind === "cuerpo" ? "Incluye todas las compañías del cuerpo." : "Compras y órdenes registradas para la compañía."}
        </p>
        <Select value={year} onValueChange={setYear}>
          <SelectTrigger className="w-[150px]" aria-label="Filtrar por año"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL_YEARS}>Todos los años</SelectItem>
            {years.map(option => <SelectItem key={option} value={option}>{option}</SelectItem>)}
          </SelectContent>
        </Select>
      </div>

      {summary && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <SummaryTile label="Cotizaciones aprobadas" value={summary.total_approved_quotes} />
          <SummaryTile label="Órdenes de taller" value={summary.total_workshop_orders} detail={`${summary.completed_workshop_orders} terminadas`} />
          <SummaryTile label="Pedidos entregados" value={summary.delivered_orders} />
          <SummaryTile label="Monto aprobado" value={formatCLP(summary.total_amount)} />
        </div>
      )}

      <div className="h-[360px] overflow-y-auto rounded-md border">
        {loading ? (
          <div className="flex h-full items-center justify-center gap-2 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> Cargando historial…
          </div>
        ) : error ? (
          <p role="alert" className="p-6 text-center text-sm text-red-700">{error}</p>
        ) : timeline.length === 0 ? (
          <p className="p-6 text-center text-sm text-muted-foreground">No hay compras ni órdenes registradas para este período.</p>
        ) : (
          <ol className="divide-y">
            {timeline.map(event => {
              const { icon: Icon, tone } = EVENT_STYLES[event.type]
              return (
                <li key={`${event.type}-${event.id}`} className="flex gap-3 px-4 py-3">
                  <span className={cn("mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full", tone)}>
                    <Icon className="h-4 w-4" aria-hidden="true" />
                  </span>
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <p className="font-semibold">
                        {event.reference}
                        <span className="ml-2 text-xs font-normal text-muted-foreground">{HISTORY_EVENT_LABELS[event.type]}</span>
                      </p>
                      <span className="text-sm text-muted-foreground">{eventDate(event.timestamp ?? event.date)}</span>
                    </div>
                    <p className="truncate text-sm">{event.title || "—"}</p>
                    <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                      <Badge variant="outline" className="gap-1">
                        {event.type === "delivered_order" && <CheckCircle2 className="h-3 w-3" aria-hidden="true" />}
                        {statusLabel(event.type, event.status)}
                      </Badge>
                      {event.total_quantity > 0 && <span>{event.total_quantity} unidades</span>}
                      {event.amount ? <span>{formatCLP(event.amount)}</span> : null}
                      {event.garments.length > 0 && <span className="truncate">{event.garments.slice(0, 3).join(", ")}{event.garments.length > 3 ? "…" : ""}</span>}
                    </div>
                  </div>
                </li>
              )
            })}
          </ol>
        )}
      </div>
    </div>
  )
}

function SummaryTile({ label, value, detail }: { label: string; value: number | string; detail?: string }) {
  return (
    <div className="rounded-lg border bg-muted/30 p-3">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="text-xl font-semibold tabular-nums">{value}</p>
      {detail && <p className="text-xs text-muted-foreground">{detail}</p>}
    </div>
  )
}
