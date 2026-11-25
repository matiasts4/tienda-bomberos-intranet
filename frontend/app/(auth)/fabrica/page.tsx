"use client"

import { useMemo, useState } from "react"
import Link from "next/link"
import { format } from "date-fns"
import { es } from "date-fns/locale"
import { FileText, RefreshCw, Search } from "lucide-react"
import { AppHeader } from "@/components/app-header"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Input } from "@/components/ui/input"
import { WorkshopOrderCard } from "@/components/fabrica/WorkshopOrderCard"
import { useCuttingOrders } from "@/hooks/use-cutting-orders"
import { useToast } from "@/hooks/use-toast"
import { cn } from "@/lib/utils"
import type { CuttingOrder } from "@/lib/api/cutting-orders"
import {
  ACTIVE_WORKSHOP_STAGES,
  STAGE_DESCRIPTIONS,
  STAGE_LABELS,
  STAGE_STYLES,
  WORKSHOP_STAGES,
  workshopStageOf,
  type WorkshopStage,
} from "@/lib/workshop-stages"

/** Refresco automático para que todas las tablets de planta vean el mismo tablero. */
const REFRESH_MS = 15000
/** Cantidad de órdenes terminadas visibles en la sección de cierre. */
const FINISHED_VISIBLE = 12

export default function FabricaPage() {
  const { toast } = useToast()
  const { orders, error, isLoading, refresh, advanceStage, advancingId } = useCuttingOrders({ refreshInterval: REFRESH_MS })
  const [search, setSearch] = useState("")
  const [selectedStage, setSelectedStage] = useState<WorkshopStage>("corte")
  const [refreshing, setRefreshing] = useState(false)
  const [lastSync, setLastSync] = useState<Date | null>(null)

  const filteredOrders = useMemo(() => {
    const term = search.trim().toLowerCase()
    if (!term) return orders
    return orders.filter(order =>
      [order.code, order.client, ...(order.items ?? []).map((item) => item.product_type), order.compania?.nombre, order.cuerpo?.nombre]
        .filter(Boolean)
        .some(value => String(value).toLowerCase().includes(term))
    )
  }, [orders, search])

  const byStage = useMemo(() => {
    const groups: Record<WorkshopStage, CuttingOrder[]> = { corte: [], confeccion: [], empaque: [], terminado: [] }
    for (const order of filteredOrders) {
      const stage = workshopStageOf(order.status)
      if (stage) groups[stage].push(order)
    }
    // Las órdenes con fecha compromiso más próxima quedan primero en cada etapa activa.
    for (const stage of ACTIVE_WORKSHOP_STAGES) {
      groups[stage].sort((a, b) => (a.fecha_compromiso ?? "9999").localeCompare(b.fecha_compromiso ?? "9999"))
    }
    return groups
  }, [filteredOrders])

  async function handleRefresh() {
    setRefreshing(true)
    try {
      await refresh()
      setLastSync(new Date())
    } finally {
      setRefreshing(false)
    }
  }

  async function handleAdvance(order: CuttingOrder, target: WorkshopStage) {
    if (!order.id) return
    try {
      await advanceStage(String(order.id), target)
      setLastSync(new Date())
      toast({
        title: `OC-${order.code} → ${STAGE_LABELS[target]}`,
        description: "Cambio registrado con fecha, hora y responsable.",
      })
    } catch (err) {
      toast({
        variant: "destructive",
        title: "No se pudo cambiar la etapa",
        description: err instanceof Error ? err.message : "Intenta nuevamente.",
      })
      void refresh()
    }
  }

  const renderColumn = (stage: WorkshopStage) => {
    const list = byStage[stage]
    return (
      <section key={stage} aria-labelledby={`stage-${stage}`} className="flex min-h-0 flex-col">
        <div className={cn("mb-3 flex items-center justify-between rounded-lg px-4 py-3", STAGE_STYLES[stage].soft)}>
          <div>
            <h2 id={`stage-${stage}`} className="text-xl font-bold">{STAGE_LABELS[stage]}</h2>
            <p className="text-sm opacity-80">{STAGE_DESCRIPTIONS[stage]}</p>
          </div>
          <span className="text-3xl font-bold tabular-nums">{list.length}</span>
        </div>
        <div className="flex flex-col gap-4">
          {list.map(order => (
            <WorkshopOrderCard
              key={order.id}
              order={order}
              advancing={advancingId === String(order.id)}
              disabled={advancingId !== null && advancingId !== String(order.id)}
              onAdvance={handleAdvance}
            />
          ))}
          {list.length === 0 && !isLoading && (
            <div className="rounded-xl border-2 border-dashed p-8 text-center text-base text-muted-foreground">
              Sin órdenes en {STAGE_LABELS[stage].toLowerCase()}
            </div>
          )}
        </div>
      </section>
    )
  }

  return (
    <div className="flex h-full flex-col">
      <AppHeader />

      <div className="border-b bg-muted/50 px-6 py-3">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href="/">Tienda Bomberos</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbLink href="/">Módulos</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Taller</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
      </div>

      <div className="border-b bg-background px-4 py-4 sm:px-6">
        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="text-2xl font-bold sm:text-3xl">Taller · Producción</h1>
            <p className="text-base text-muted-foreground">
              Toca el botón de cada orden para avanzarla de etapa. El tablero se actualiza cada {REFRESH_MS / 1000} segundos.
            </p>
          </div>
          <div className="flex items-center gap-3">
            <Link
              href="/fabrica/bitacora"
              className="inline-flex min-h-12 items-center gap-2 rounded-lg border-2 px-4 text-base font-medium transition-transform active:scale-[0.97] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-ring/50"
            >
              <FileText className="h-5 w-5" aria-hidden="true" />
              Bitácora
            </Link>
            <button
              type="button"
              onClick={() => void handleRefresh()}
              disabled={refreshing}
              aria-label="Actualizar tablero"
              className="inline-flex min-h-12 min-w-12 items-center justify-center rounded-lg border-2 transition-transform active:scale-[0.97] disabled:opacity-60 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-ring/50"
            >
              <RefreshCw className={cn("h-6 w-6", refreshing && "animate-spin")} aria-hidden="true" />
            </button>
          </div>
        </div>

        <div className="relative mt-4 max-w-xl">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
          <Input
            value={search}
            onChange={event => setSearch(event.target.value)}
            placeholder="Buscar por N° de orden, compañía o prenda"
            className="h-12 pl-11 text-base"
            aria-label="Buscar órdenes"
          />
        </div>

        {/* Resumen por etapa; en pantallas angostas funciona como selector de pestañas. */}
        <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4" role="tablist" aria-label="Etapas del taller">
          {WORKSHOP_STAGES.map(stage => {
            const active = selectedStage === stage
            return (
              <button
                key={stage}
                type="button"
                role="tab"
                aria-selected={active}
                onClick={() => setSelectedStage(stage)}
                className={cn(
                  "flex min-h-16 items-center justify-between gap-2 rounded-lg border-2 px-4 text-left transition-transform active:scale-[0.97]",
                  "focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-ring/50",
                  active ? cn(STAGE_STYLES[stage].accent, STAGE_STYLES[stage].soft) : "border-border bg-card",
                )}
              >
                <span className="flex items-center gap-2 text-base font-semibold">
                  <span className={cn("h-3 w-3 rounded-full", STAGE_STYLES[stage].dot)} aria-hidden="true" />
                  {STAGE_LABELS[stage]}
                </span>
                <span className="text-2xl font-bold tabular-nums">{byStage[stage].length}</span>
              </button>
            )
          })}
        </div>
        {lastSync && (
          <p className="mt-2 text-xs text-muted-foreground">
            Última actualización manual: {format(lastSync, "HH:mm:ss", { locale: es })}
          </p>
        )}
      </div>

      <main className="flex-1 overflow-auto p-4 sm:p-6">
        {error && (
          <div role="alert" className="mb-4 flex flex-wrap items-center gap-3 rounded-lg border-2 border-red-300 bg-red-50 p-4 text-base text-red-900">
            No se pudieron cargar las órdenes del taller.
            <button
              type="button"
              onClick={() => void handleRefresh()}
              className="min-h-12 rounded-lg border-2 border-red-300 bg-white px-4 font-medium active:scale-[0.97]"
            >
              Reintentar
            </button>
          </div>
        )}

        {isLoading && orders.length === 0 ? (
          <p className="py-12 text-center text-lg text-muted-foreground">Cargando órdenes del taller…</p>
        ) : (
          <>
            {/* Tablet horizontal (≥1024 px): tres columnas Corte · Confección · Empaque. */}
            <div className="hidden gap-6 lg:grid lg:grid-cols-3">
              {ACTIVE_WORKSHOP_STAGES.map(renderColumn)}
            </div>

            {/* Tablet vertical: una etapa a la vez según la pestaña seleccionada. */}
            <div className="lg:hidden">{renderColumn(selectedStage)}</div>

            {byStage.terminado.length > 0 && (
              <section className="mt-8 hidden lg:block" aria-labelledby="stage-terminado-resumen">
                <h2 id="stage-terminado-resumen" className="mb-3 text-xl font-bold">
                  Terminadas · listas para despacho ({byStage.terminado.length})
                </h2>
                <div className="grid grid-cols-3 gap-4">
                  {byStage.terminado.slice(0, FINISHED_VISIBLE).map(order => (
                    <WorkshopOrderCard key={order.id} order={order} advancing={false} onAdvance={handleAdvance} />
                  ))}
                </div>
              </section>
            )}
          </>
        )}
      </main>
    </div>
  )
}
