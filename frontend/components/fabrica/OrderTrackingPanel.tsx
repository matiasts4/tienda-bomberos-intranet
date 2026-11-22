"use client"

import { useMemo, useState } from "react"
import { Search } from "lucide-react"
import { Input } from "@/components/ui/input"
import { ScrollArea } from "@/components/ui/scroll-area"
import { cn } from "@/lib/utils"
import { useCuttingOrders } from "@/hooks/use-cutting-orders"
import { OrderStageTimeline } from "@/components/fabrica/OrderStageTimeline"
import { STAGE_LABELS, STAGE_STYLES, WORKSHOP_STAGES, isPendingStart, workshopStageOf, type WorkshopStage } from "@/lib/workshop-stages"

/** Frecuencia de actualización del seguimiento en tiempo real. */
const LIVE_REFRESH_MS = 10000

type StageFilter = WorkshopStage | "todas"

/**
 * Panel de seguimiento de órdenes de taller: listado con progreso por etapa
 * y línea de tiempo detallada de la orden seleccionada, actualizado en vivo.
 */
export function OrderTrackingPanel() {
  const { orders, error, isLoading } = useCuttingOrders({ refreshInterval: LIVE_REFRESH_MS })
  const [search, setSearch] = useState("")
  const [stageFilter, setStageFilter] = useState<StageFilter>("todas")
  const [selectedId, setSelectedId] = useState<string | null>(null)

  const visible = useMemo(() => {
    const term = search.trim().toLowerCase()
    return orders.filter(order => {
      const stage = workshopStageOf(order.status)
      if (stageFilter !== "todas" && stage !== stageFilter) return false
      if (!term) return true
      return [order.code, order.client, order.selected_product, order.compania?.nombre, order.cuerpo?.nombre]
        .filter(Boolean)
        .some(value => String(value).toLowerCase().includes(term))
    })
  }, [orders, search, stageFilter])

  const activeId = selectedId ?? (visible[0]?.id ? String(visible[0].id) : null)

  return (
    <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
      <section aria-label="Órdenes de taller" className="rounded-xl border bg-card">
        <div className="space-y-3 border-b p-4">
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
            <Input
              value={search}
              onChange={event => setSearch(event.target.value)}
              placeholder="Buscar orden, compañía o prenda"
              className="h-11 pl-9"
              aria-label="Buscar órdenes en seguimiento"
            />
          </div>
          <div className="flex flex-wrap gap-2" role="group" aria-label="Filtrar por etapa">
            {(["todas", ...WORKSHOP_STAGES] as StageFilter[]).map(option => (
              <button
                key={option}
                type="button"
                onClick={() => setStageFilter(option)}
                aria-pressed={stageFilter === option}
                className={cn(
                  "min-h-11 rounded-full border px-4 text-sm font-medium transition-colors",
                  stageFilter === option ? "border-foreground bg-foreground text-background" : "bg-background hover:bg-muted",
                )}
              >
                {option === "todas" ? "Todas" : STAGE_LABELS[option]}
              </button>
            ))}
          </div>
        </div>

        <ScrollArea className="h-[calc(100vh-380px)] min-h-[320px]">
          {error && <p role="alert" className="p-4 text-sm text-red-700">No se pudieron cargar las órdenes.</p>}
          {isLoading && orders.length === 0 && <p className="p-4 text-sm text-muted-foreground">Cargando órdenes…</p>}
          {!isLoading && visible.length === 0 && !error && (
            <p className="p-6 text-center text-sm text-muted-foreground">No hay órdenes para este filtro.</p>
          )}
          <ul className="divide-y">
            {visible.map(order => {
              const stage = workshopStageOf(order.status) ?? "corte"
              const step = isPendingStart(order.status) ? 0 : WORKSHOP_STAGES.indexOf(stage) + 1
              const id = String(order.id)
              const selected = id === activeId
              return (
                <li key={id}>
                  <button
                    type="button"
                    onClick={() => setSelectedId(id)}
                    aria-current={selected}
                    className={cn("w-full px-4 py-3 text-left transition-colors hover:bg-muted/60", selected && "bg-muted")}
                  >
                    <div className="flex items-center justify-between gap-2">
                      <span className="font-semibold">OC-{order.code}</span>
                      <span className={cn("rounded-full px-2 py-0.5 text-xs font-semibold", STAGE_STYLES[stage].soft)}>
                        {isPendingStart(order.status) ? "Por iniciar" : STAGE_LABELS[stage]}
                      </span>
                    </div>
                    <p className="truncate text-sm text-muted-foreground">
                      {order.compania?.nombre || order.cuerpo?.nombre || order.client || "Sin cliente"} · {order.selected_product}
                    </p>
                    <div className="mt-2 flex gap-1" aria-label={`Avance ${step} de ${WORKSHOP_STAGES.length} etapas`}>
                      {WORKSHOP_STAGES.map((s, index) => (
                        <span key={s} className={cn("h-1.5 flex-1 rounded-full", index < step ? STAGE_STYLES[s].dot : "bg-border")} />
                      ))}
                    </div>
                  </button>
                </li>
              )
            })}
          </ul>
        </ScrollArea>
      </section>

      <section aria-label="Línea de tiempo de la orden" className="rounded-xl border bg-card p-5">
        {activeId ? (
          <OrderStageTimeline orderId={activeId} refreshInterval={LIVE_REFRESH_MS} />
        ) : (
          <p className="py-12 text-center text-sm text-muted-foreground">Selecciona una orden para ver su trazabilidad.</p>
        )}
      </section>
    </div>
  )
}
