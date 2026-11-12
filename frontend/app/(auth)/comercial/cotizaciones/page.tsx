"use client"

import { useMemo, useState, useEffect, useCallback, useRef } from "react"
import { useRouter } from "next/navigation"

import { AppHeader } from "@/components/app-header"
import { KanbanColumn } from "@/components/kanban-column"
import { CotizacionKanbanCard } from "@/components/CotizacionKanbanCard"
import { OportunidadDragPreview, OportunidadKanbanCard } from "@/components/OportunidadKanbanCard"
import { nombreUsuario } from "@/lib/responsables"
import { DestinatarioBadge } from "@/components/comercial/DestinatarioBadge"
import { DESTINATARIO_LABEL, DESTINATARIO_NUMERO_CLASS, tipoDestinatarioDe } from "@/lib/destinatario"
import { NuevaOportunidadDialog } from "../oportunidades/nueva/NuevaOportunidadDialog"

import { Button } from "@/components/ui/button"

import { Plus, Filter, Calendar, User, Search, Users, XCircle, Eye, CheckCircle, Ban, Kanban, LayoutGrid, Palette, FolderOpen, CalendarClock } from "lucide-react"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Badge } from "@/components/ui/badge"
import { EstadoBadge } from "@/components/comercial/EstadoBadge"
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table"
import { cn, formatCLP } from "@/lib/utils"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { ScrollArea, ScrollBar } from "@/components/ui/scroll-area"
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import {
  DndContext,
  DragOverlay,
  useSensor,
  useSensors,
  PointerSensor,
  DragEndEvent,
  DragStartEvent
} from "@dnd-kit/core"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"

import { DraggableCard } from "@/components/draggable-card"
import { api } from "@/lib/api"
import { toast } from "sonner"

import { ClientesDialog } from "@/components/ClientesDialog"
import { useComercialData } from "@/hooks/use-comercial-data"

import { CotizacionArchivosDialog } from "@/components/comercial/CotizacionArchivosDialog"

import { CotizacionNoVigenteDialog } from "@/components/comercial/CotizacionNoVigenteDialog"
import { CotizacionNoVigenteInfoDialog } from "@/components/comercial/CotizacionNoVigenteInfoDialog"
import { useAuth } from "@/hooks/useAuth"
import {
  MOTIVO_BADGE_CLASS,
  MOTIVO_LABEL,
  datosNoVigente,
  esEstadoNoVigente,
  etiquetaMotivo,
  payloadNoVigente,
  puedeSalirDeNoVigentes,
  type FormNoVigente,
  type MotivoTarjeta,
} from "@/lib/no-vigentes"
import { diasHasta, estadoVencimiento, formatoFechaDMY, vencimientoDe } from "@/lib/vencimiento-cotizacion"

// Importar dialogs globales para performance
import { CotizacionDetailDialog } from "@/components/comercial/CotizacionDetailDialog"
import { CotizacionGestionDialog } from "@/components/comercial/CotizacionGestionDialog"
import { OportunidadGestionDialog } from "@/components/comercial/OportunidadGestionDialog"

// Importamos iconos para reuso si necesario

// Fechas `date` sin corrimiento por zona horaria (ver lib/vencimiento-cotizacion)
const formatDateDMY = formatoFechaDMY

const GRUPO_LABEL: Record<string, string> = {
  enviada: "Cotización Enviada",
  ventas: "Ventas",
  no_vigente: "No Vigente",
  otra: "Otro estado",
}

const GRUPO_BADGE_CLASS: Record<string, string> = {
  enviada: "bg-blue-100 text-blue-800 hover:bg-blue-100",
  ventas: "bg-emerald-100 text-emerald-800 hover:bg-emerald-100",
  no_vigente: "bg-slate-200 text-slate-700 hover:bg-slate-200",
  otra: "bg-amber-100 text-amber-800 hover:bg-amber-100",
}

// Fecha en que salió del flujo (las anteriores la guardaban en fecha_rechazo), para ordenar.
function fechaSalida(c: any): number {
  const t = new Date(c?.fecha_no_vigente || c?.fecha_rechazo || 0).getTime()
  return Number.isNaN(t) ? 0 : t
}

// Próximo contacto de un lead: misma regla que la barra de gestión de OportunidadKanbanCard
// (vencimiento de la última gestión, o creación + 5 días por defecto).
function getLeadVencimiento(o: any): string | null {
  if (o?.ultima_gestion?.fecha_vencimiento_nueva) return o.ultima_gestion.fecha_vencimiento_nueva
  if (!o?.created_at) return null
  const target = new Date(o.created_at)
  target.setDate(target.getDate() + 5)
  return target.toISOString()
}


// Colores de "Vence" en la grilla: mismas reglas que el footer de cada tarjeta del kanban.
// Cotización: rojo vencida, naranjo ≤3 días, amarillo gestionada, gris nueva.
function vencimientoCotClass(dateStr?: string | null, gestionada?: boolean): string {
  const dias = diasHasta(dateStr)
  if (dias === null) return "text-muted-foreground"
  if (dias < 0) return "bg-red-100 text-red-800"
  if (dias <= 3) return "bg-orange-100 text-orange-800"
  if (gestionada) return "bg-yellow-100 text-yellow-800"
  return "bg-gray-100 text-muted-foreground"
}

// Lead: rojo atrasado, naranjo hoy o mañana, verde a futuro.
function vencimientoLeadClass(dateStr?: string | null): string {
  const dias = diasHasta(dateStr)
  if (dias === null) return "text-muted-foreground"
  if (dias < 0) return "bg-red-100 text-red-800"
  if (dias <= 1) return "bg-orange-100 text-orange-800"
  return "bg-green-100 text-green-800"
}

export default function ComercialPage() {
  const router = useRouter()
  const [mounted, setMounted] = useState(false)

  useEffect(() => {
    setMounted(true)
  }, [])

  const {
    leads: allLeads,
    leadsLoading,
    leadsError,
    estados,
    estadosError,
    estadosCot,
    estadosCotError,
    cotLoading,
    cotError,
    cotEnviadas,
    cotAprobadas,
    cotNoVigentes,
    cotizaciones,
    estadoIdsCot,
    loadLeads,
    loadCotizaciones,
    users,
    clientes,
  } = useComercialData()

  // El rol solo orienta la UI (qué motivos se ofrecen); el backend es quien lo exige.
  const { user } = useAuth()
  const esSuperadmin = user?.role === "superadmin"

  const nombreEstadoDe = useCallback(
    (c: any): string | undefined => c?.estado?.nombre ?? estadosCot.find((e) => e.id === c?.estado_id)?.nombre,
    [estadosCot]
  )

  // No Vigentes: las más recientes arriba, con filtro por motivo
  const [filtroMotivo, setFiltroMotivo] = useState<"all" | MotivoTarjeta>("all")
  const sortedCotNoVigentes = useMemo(
    () => [...cotNoVigentes].sort((a, b) => fechaSalida(b) - fechaSalida(a) || b.id - a.id),
    [cotNoVigentes]
  )
  const cotNoVigentesFiltradas = useMemo(() => {
    if (filtroMotivo === "all") return sortedCotNoVigentes
    return sortedCotNoVigentes.filter((c) => datosNoVigente(c, nombreEstadoDe(c))?.motivo === filtroMotivo)
  }, [sortedCotNoVigentes, filtroMotivo, nombreEstadoDe])

  // Vista del tablero de cotizaciones: kanban (columnas por estado) o grilla (tarjetas en grid)
  const [viewMode, setViewMode] = useState<'kanban' | 'grid'>('kanban')

  // Búsqueda por N° de cotización: lo escrito se aplica con un pequeño retardo
  // para no recargar el tablero en cada tecla.
  const [numeroInput, setNumeroInput] = useState("")
  const [numeroFiltro, setNumeroFiltro] = useState("")
  useEffect(() => {
    const t = setTimeout(() => setNumeroFiltro(numeroInput.trim()), 350)
    return () => clearTimeout(t)
  }, [numeroInput])
  // Los leads aún no tienen N° de cotización: mientras se busca uno, se ocultan.
  const leads = numeroFiltro ? [] : allLeads

  const [selectedOportunidad, setSelectedOportunidad] = useState<any | null>(null)
  const [activeOppDialog, setActiveOppDialog] = useState<'gestion' | null>(null)
  const [openNewOpp, setOpenNewOpp] = useState(false)

  const handleViewOppGestion = (opp: any) => {
    setSelectedOportunidad(opp)
    setActiveOppDialog('gestion')
  }

  const closeOppDialogs = () => {
    setActiveOppDialog(null)
    setTimeout(() => setSelectedOportunidad(null), 300)
  }

  // Helper para calc de prioridad (0: Rojo, 1: Naranjo, 2: Nuevo(Gris), 3: Gestionada(Amarillo))
  const getPriority = useCallback((c: any) => {
    if (!vencimientoDe(c)) return 10
    return { vencida: 0, por_vencer: 1, gestionada: 2, normal: 3 }[estadoVencimiento(c)]
  }, [])

  // Ordenar Cotizaciones Enviadas
  const sortedCotEnviadas = useMemo(() => {
    return [...cotEnviadas].sort((a, b) => {
      const pA = getPriority(a)
      const pB = getPriority(b)
      if (pA !== pB) return pA - pB
      // Si tienen misma prioridad, ordenar por fecha (más antigua primero? o ID?)
      // Por defecto ID desc (más nuevo arriba)
      return b.id - a.id
    })
  }, [cotEnviadas])

  // Cotizaciones en un estado que el tablero no reconoce: no tienen columna en el
  // kanban, pero la grilla las muestra igual para que no desaparezcan.
  const cotOtras = useMemo(() => {
    const conocidos = new Set<unknown>([
      estadoIdsCot.idEnviada,
      estadoIdsCot.idAprobada,
      estadoIdsCot.idRechazada,
      estadoIdsCot.idAnulada,
      estadoIdsCot.idNoVigente,
    ])
    return cotizaciones.filter((c: any) => !conocidos.has(c.estado_id))
  }, [cotizaciones, estadoIdsCot])

  // Todo el pipeline (Leads + Cotizaciones) para la vista de grilla tipo Excel, una fila por registro
  const gridRows = useMemo(() => [
    ...leads
      .filter((o: any) => o && typeof o.id === "number")
      .map((o: any) => ({ tipo: 'lead' as const, oportunidad: o })),
    ...sortedCotEnviadas.map((c: any) => ({ tipo: 'cotizacion' as const, cotizacion: c, grupo: 'enviada' as const })),
    ...cotAprobadas.map((c: any) => ({ tipo: 'cotizacion' as const, cotizacion: c, grupo: 'ventas' as const })),
    ...sortedCotNoVigentes.map((c: any) => ({ tipo: 'cotizacion' as const, cotizacion: c, grupo: 'no_vigente' as const })),
    ...cotOtras.map((c: any) => ({ tipo: 'cotizacion' as const, cotizacion: c, grupo: 'otra' as const })),
  ], [leads, sortedCotEnviadas, cotAprobadas, sortedCotNoVigentes, cotOtras])

  // DnD State
  const [activeId, setActiveId] = useState<string | null>(null)
  const [activeQuote, setActiveQuote] = useState<any | null>(null) // Para el overlay
  const [activeLead, setActiveLead] = useState<any | null>(null)

  // Confirmation Dialog State
  const [confirmState, setConfirmState] = useState<{
    open: boolean
    kind: 'quote' | 'opportunity'
    recordId: number
    targetStateId: number | null
    targetStateName: string
    recordNumber: string
  }>({
    open: false,
    kind: 'quote',
    recordId: 0,
    targetStateId: null,
    targetStateName: "",
    recordNumber: ""
  })
  const moveInFlight = useRef(false)
  const [moving, setMoving] = useState(false)

  // File Dialog State
  const [fileDialogState, setFileDialogState] = useState<{
    open: boolean
    quoteId: number
    quoteNumber: string
    targetStateId: number | null
    archivos?: Array<{
      id: number
      tipo: string
      nombre_archivo: string
      url: string
    }>
  }>({
    open: false,
    quoteId: 0,
    quoteNumber: "",
    targetStateId: null
  })

  // Motivo y observación que se piden al soltar una tarjeta en "No Vigentes"
  const [noVigenteDialogState, setNoVigenteDialogState] = useState<{
    open: boolean
    quoteId: number
    quoteNumber: string
    targetStateId: number | null
    enVenta: boolean
  }>({
    open: false,
    quoteId: 0,
    quoteNumber: "",
    targetStateId: null,
    enVenta: false
  })

  const [selectedQuote, setSelectedQuote] = useState<any | null>(null)
  const [activeDialog, setActiveDialog] = useState<'detail' | 'gestion' | 'archivos' | 'no-vigente-info' | null>(null)



  const handleViewDetail = (quote: any) => {
    setSelectedQuote(quote)
    setActiveDialog('detail')
  }

  const handleViewGestion = (quote: any) => {
    setSelectedQuote(quote)
    setActiveDialog('gestion')
  }

  const handleViewArchivos = (quote: any) => {
    setSelectedQuote(quote)
    setActiveDialog('archivos')
  }

  const handleViewNoVigente = (quote: any) => {
    setSelectedQuote(quote)
    setActiveDialog('no-vigente-info')
  }

  const closeGlobalDialogs = () => {
    setActiveDialog(null)
    setTimeout(() => setSelectedQuote(null), 300) // Clear after animation
  }


  // Filter State
  const [selectedEjecutivo, setSelectedEjecutivo] = useState("all")
  const [selectedCliente, setSelectedCliente] = useState("all")
  // A quién va dirigida: all | bomberos | empresa | persona_natural
  const [selectedDestinatario, setSelectedDestinatario] = useState("all")
  const [selectedDateRange, setSelectedDateRange] = useState("all")

  // Filtros activos como única fuente de verdad: los usa tanto el efecto de
  // recarga como los reload tras acciones (drag & drop, gestiones, archivos),
  // para que ninguna recarga pise el tablero con datos sin filtrar.
  const currentFilters = useMemo(() => {
    const filters: any = {}
    if (selectedEjecutivo !== "all") filters.ejecutivo_id = selectedEjecutivo
    if (selectedDestinatario !== "all") filters.tipo_destinatario = selectedDestinatario
    if (selectedCliente !== "all") {
      const entidad = clientes.find(c => c.id === selectedCliente)
      if (entidad?.kind === "cuerpo") filters.cuerpo_id = selectedCliente
      else if (entidad?.kind === "compania") filters.compania_id = selectedCliente
      else filters.cliente_id = selectedCliente
    }

    // Un N° de cotización se busca en todo el historial, sin el rango de fechas
    if (numeroFiltro) {
      filters.numero = numeroFiltro
    } else if (selectedDateRange !== "all") {
      const days = parseInt(selectedDateRange)
      if (!isNaN(days)) {
        const date = new Date()
        date.setDate(date.getDate() - days)
        filters.from = date.toISOString().split('T')[0]
        filters.to = new Date().toISOString().split('T')[0]
      }
    }
    return filters
  }, [selectedEjecutivo, selectedCliente, selectedDestinatario, selectedDateRange, numeroFiltro, clientes])

  const reloadCotizaciones = useCallback(
    () => loadCotizaciones(currentFilters),
    [currentFilters, loadCotizaciones]
  )
  // La columna de Leads comparte los filtros de persona y cliente (las
  // oportunidades no tienen rango de fechas en la API, así que ese no aplica)
  const reloadLeads = useCallback(
    () => loadLeads({
      ejecutivo_id: currentFilters.ejecutivo_id,
      cliente_id: currentFilters.cliente_id,
      cuerpo_id: currentFilters.cuerpo_id,
      compania_id: currentFilters.compania_id,
      tipo_destinatario: currentFilters.tipo_destinatario,
    }),
    [currentFilters, loadLeads]
  )
  const reloadBoard = useCallback(() => {
    reloadCotizaciones()
    reloadLeads()
  }, [reloadCotizaciones, reloadLeads])

  // Reload when filters change
  useEffect(() => {
    reloadBoard()
  }, [reloadBoard])

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    })
  )

  const handleDragStart = useCallback((event: DragStartEvent) => {
    const { active } = event
    setActiveId(active.id as string)
    setActiveQuote(active.data.current?.quote)
    setActiveLead(active.data.current?.oportunidad ?? null)
  }, [])

  const handleDragEnd = useCallback((event: DragEndEvent) => {
    const { active, over } = event
    setActiveId(null)
    setActiveQuote(null)
    setActiveLead(null)

    if (!over) return

    const opportunity = active.data.current?.oportunidad
    const columnId = over.id as string

    // Leads y cotizaciones son entidades distintas, con estados y endpoints propios.
    if (opportunity) {
      if (columnId === "col-lead") return
      if (columnId !== "col-quote-sent") {
        toast.error("Primero crea la cotización del lead antes de moverla a otra etapa.")
        return
      }
      if (!Number.isSafeInteger(opportunity.id) || opportunity.id <= 0) {
        toast.error("No se pudo identificar el lead. Actualiza el tablero e intenta nuevamente.")
        return
      }
      const conversionState = estados.find(state => [
        'aprobada - cotización', 'cotización enviada', 'enviar cotización',
      ].includes(state.nombre.trim().toLowerCase()))
      if (!conversionState) {
        toast.error("No se encontró el estado para crear la cotización. Actualiza el tablero o contacta a soporte.")
        return
      }
      setConfirmState({
        open: true,
        kind: 'opportunity',
        recordId: opportunity.id,
        targetStateId: conversionState.id,
        targetStateName: "Cotización Enviada",
        recordNumber: `OP-${String(opportunity.id).padStart(4, "0")}`,
      })
      return
    }

    const quoteId = active.data.current?.quote?.id
    if (!Number.isSafeInteger(quoteId) || quoteId <= 0) {
      toast.error("No se pudo identificar la cotización. Actualiza el tablero e intenta nuevamente.")
      return
    }
    const quote = active.data.current?.quote
    // Mismo N° que muestra la tarjeta
    const quoteNumber = quote?.numero || `CT-${String(quoteId).padStart(4, "0")}`
    const estadoActualNombre = nombreEstadoDe(quote)
    const estaNoVigente = esEstadoNoVigente(estadoActualNombre)

    if (columnId === "col-no-vigentes") {
      // Rechazadas o Anuladas anteriores ya están en esta etapa
      if (estaNoVigente) return
      if (!estadoIdsCot.idNoVigente) {
        toast.error('No se encontró el estado "No Vigente". Actualiza el tablero o contacta a soporte.')
        return
      }
      setNoVigenteDialogState({
        open: true,
        quoteId,
        quoteNumber,
        targetStateId: estadoIdsCot.idNoVigente,
        enVenta: quote?.estado_id === estadoIdsCot.idAprobada,
      })
      return
    }

    if (estaNoVigente && !puedeSalirDeNoVigentes(quote, estadoActualNombre, esSuperadmin)) {
      toast.error("Solo un superadmin puede reactivar una cotización con finalización administrativa.")
      return
    }

    let targetStateId: number | null = null
    let targetStateName = ""

    if (columnId === "col-quote-sent" && estadoIdsCot.idEnviada) {
      targetStateId = estadoIdsCot.idEnviada
      targetStateName = "Cotización Enviada"
    } else if (columnId === "col-approved" && estadoIdsCot.idAprobada) {
      targetStateId = estadoIdsCot.idAprobada
      targetStateName = "Ventas"
    }

    // Si no hay estado válido o es el mismo estado actual (simplificación: asume que la columna es el estado)
    if (!targetStateId || targetStateId === quote?.estado_id) return

    // ✅ CHECK ARCHIVOS: Si va a Ventas, verificar si tiene documentos
    if (targetStateName === "Ventas") {
      const archivos = quote?.archivos || []

      // Verificar si tiene al menos uno de los 3 tipos
      const hasDocs = archivos.some((a: any) =>
        ['orden_venta', 'facturacion', 'pago'].includes(a.tipo)
      )

      if (!hasDocs) {
        // Abrir dialog de archivos
        setFileDialogState({
          open: true,
          quoteId,
          quoteNumber,
          targetStateId
        })
        return // Bloquear movimiento
      }
    }

    // Abrir confirmación
    setConfirmState({
      open: true,
      kind: 'quote',
      recordId: quoteId,
      targetStateId,
      targetStateName,
      recordNumber: quoteNumber
    })
  }, [estadoIdsCot, estados, nombreEstadoDe, esSuperadmin])

  const handleConfirmMove = useCallback(async () => {
    if (moveInFlight.current || !confirmState.open) return
    const { kind, recordId, targetStateId, targetStateName } = confirmState
    if (!Number.isSafeInteger(recordId) || recordId <= 0 || !targetStateId) {
      toast.error("Faltan datos para realizar el movimiento. Actualiza el tablero.")
      return
    }
    moveInFlight.current = true
    setMoving(true)
    try {
      const resource = kind === 'opportunity' ? 'oportunidades' : 'cotizaciones'
      const response = await api.patch<{ cotizacion_id?: number }>(`/api/${resource}/${recordId}/estado`, {
        estado_id: targetStateId
      })
      if (kind === 'opportunity') {
        reloadBoard()
        const quoteId = response?.cotizacion_id
        if (!Number.isSafeInteger(quoteId) || !quoteId || quoteId <= 0) {
          toast.error("No se recibió la cotización del lead. Actualiza el tablero antes de continuar.")
          return
        }
        toast.success("Cotización creada. Completa sus productos y condiciones.")
        router.push(`/comercial/cotizaciones/${quoteId}`)
      } else {
        toast.success(`Cotización movida a ${targetStateName}`)
        reloadCotizaciones()
      }
      setConfirmState(prev => ({ ...prev, open: false }))
    } catch (e) {
      console.error(e)
      toast.error(kind === 'opportunity'
        ? "No se pudo crear la cotización. Intenta nuevamente."
        : "No se pudo mover la cotización. Intenta nuevamente.")
    } finally {
      moveInFlight.current = false
      setMoving(false)
    }
  }, [confirmState, reloadBoard, reloadCotizaciones, router])

  // Si falla, el error llega al diálogo, que queda abierto con lo escrito y lo muestra
  async function handleConfirmNoVigente(form: FormNoVigente, boucherDespacho: File | null) {
    const { quoteId, targetStateId } = noVigenteDialogState
    if (!quoteId || !targetStateId) return

    await api.patch(`/api/cotizaciones/${quoteId}/estado`, payloadNoVigente(targetStateId, form))
    toast.success("Cotización movida a No Vigentes")

    // El cambio de etapa ya quedó: si el boucher falla, se sube después desde la tarjeta
    if (boucherDespacho) {
      try {
        const formData = new FormData()
        formData.append("archivo", boucherDespacho)
        formData.append("tipo", "boucher_despacho")
        await api.post(`/api/cotizaciones/${quoteId}/archivos`, formData)
      } catch (e: any) {
        toast.error(`No se pudo adjuntar el Boucher Despacho: ${e?.message || "intenta desde la tarjeta"}`)
      }
    }
    reloadCotizaciones()
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
              <BreadcrumbPage>Comercial</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
      </div>

      <div className="border-b bg-background px-6 py-4">
        <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center">
          <div>
            <h1 className="text-2xl font-bold">Módulo Comercial</h1>
            <p className="text-sm text-muted-foreground">Gestión de oportunidades y cotizaciones</p>
          </div>
          <div className="sm:absolute sm:left-1/2 sm:-translate-x-1/2">
            <div className="flex items-center gap-2">
              <ClientesDialog />
              <Button onClick={() => setOpenNewOpp(true)}>
                <Plus className="h-4 w-4" />
                Nueva Oportunidad
              </Button>
            </div>
          </div>

          <div className="flex items-center gap-1 rounded-md border bg-muted/30 p-1 sm:ml-auto">
            <Button
              type="button"
              variant={viewMode === 'kanban' ? 'default' : 'ghost'}
              size="sm"
              className="h-7 gap-1.5 px-2 text-xs"
              onClick={() => setViewMode('kanban')}
              aria-pressed={viewMode === 'kanban'}
            >
              <Kanban className="h-3.5 w-3.5" />
              Tablero
            </Button>
            <Button
              type="button"
              variant={viewMode === 'grid' ? 'default' : 'ghost'}
              size="sm"
              className="h-7 gap-1.5 px-2 text-xs"
              onClick={() => setViewMode('grid')}
              aria-pressed={viewMode === 'grid'}
            >
              <LayoutGrid className="h-3.5 w-3.5" />
              Grilla
            </Button>
          </div>
        </div>

        <div className="mt-4 flex flex-wrap gap-3">
          {mounted ? (
            <>
              <div className="relative w-[180px]">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  value={numeroInput}
                  onChange={(e) => setNumeroInput(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === "Enter") setNumeroFiltro(numeroInput.trim())
                    if (e.key === "Escape") { setNumeroInput(""); setNumeroFiltro("") }
                  }}
                  placeholder="N° cotización"
                  aria-label="Buscar por N° de cotización"
                  className={cn("h-9 pl-9 text-sm", numeroInput && "pr-8")}
                />
                {numeroInput && (
                  <button
                    type="button"
                    onClick={() => { setNumeroInput(""); setNumeroFiltro("") }}
                    className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    aria-label="Quitar búsqueda por N°"
                  >
                    <XCircle className="h-4 w-4" />
                  </button>
                )}
              </div>

              <Select value={selectedEjecutivo} onValueChange={setSelectedEjecutivo}>
                <SelectTrigger className="w-[180px] h-9 text-sm">
                  <User className="mr-2 h-4 w-4 text-muted-foreground" />
                  <SelectValue placeholder="Todos los ejecutivos" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos los ejecutivos</SelectItem>
                  {users.map((u) => (
                    <SelectItem key={u.id} value={String(u.id)}>
                      {u.nombre}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <Select value={selectedCliente} onValueChange={setSelectedCliente}>
                <SelectTrigger className="w-[180px] h-9 text-sm">
                  <Users className="mr-2 h-4 w-4 text-muted-foreground" />
                  <SelectValue placeholder="Todos los clientes" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos los clientes</SelectItem>
                  {clientes.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {c.nombre_empresa} {c.kind === "cuerpo" ? "(Cuerpo)" : c.kind === "compania" ? "(Compañía)" : c.tipo === "particular" ? "(Persona natural)" : ""}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <Select value={selectedDestinatario} onValueChange={setSelectedDestinatario}>
                <SelectTrigger className="w-[190px] h-9 text-sm" aria-label="Dirigida a">
                  <Users className="mr-2 h-4 w-4 text-muted-foreground" />
                  <SelectValue placeholder="Dirigida a" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Dirigida a: todos</SelectItem>
                  <SelectItem value="bomberos">{DESTINATARIO_LABEL.bomberos}</SelectItem>
                  <SelectItem value="empresa">{DESTINATARIO_LABEL.empresa}</SelectItem>
                  <SelectItem value="persona_natural">{DESTINATARIO_LABEL.persona_natural}</SelectItem>
                </SelectContent>
              </Select>

              <Select value={selectedDateRange} onValueChange={setSelectedDateRange} disabled={!!numeroFiltro}>
                <SelectTrigger
                  className="w-[180px] h-9 text-sm"
                  title={numeroFiltro ? "La búsqueda por N° considera todo el historial" : undefined}
                >
                  <Calendar className="mr-2 h-4 w-4 text-muted-foreground" />
                  <SelectValue placeholder="Todo el historial" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="7">Últimos 7 días</SelectItem>
                  <SelectItem value="30">Últimos 30 días</SelectItem>
                  <SelectItem value="90">Últimos 90 días</SelectItem>
                  <SelectItem value="all">Todo el historial</SelectItem>
                </SelectContent>
              </Select>

              <Button variant="ghost" size="sm" className="h-9 px-2 text-muted-foreground" onClick={() => {
                // Reset filters: el useEffect recarga el tablero al cambiar el estado
                setSelectedEjecutivo("all")
                setSelectedCliente("all")
                setSelectedDestinatario("all")
                setSelectedDateRange("all")
                setNumeroInput("")
                setNumeroFiltro("")
              }}>
                <Filter className="mr-2 h-3.5 w-3.5" />
                Limpiar filtros
              </Button>
            </>
          ) : (
            <>
              <div className="h-9 w-[180px] animate-pulse rounded-md bg-muted" />
              <div className="h-9 w-[180px] animate-pulse rounded-md bg-muted" />
              <div className="h-9 w-[180px] animate-pulse rounded-md bg-muted" />
              <div className="h-9 w-[180px] animate-pulse rounded-md bg-muted" />
              <div className="h-9 w-[120px] animate-pulse rounded-md bg-muted" />
            </>
          )}
        </div>
      </div>

      <main className="flex-1 overflow-auto p-6">
        {viewMode === 'grid' ? (
          <div className="space-y-3">
            {(cotError || estadosCotError) && (
              <div className="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                {cotError || estadosCotError}
              </div>
            )}

            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <span>{gridRows.length} registros</span>
              <span>· {leads.length} leads</span>
              <span>· {gridRows.filter((r) => r.tipo === 'cotizacion').length} cotizaciones</span>
              {(cotLoading || leadsLoading) && <span>· Cargando…</span>}
            </div>

            <div className="rounded-md border bg-background">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>N°</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Cliente</TableHead>
                    <TableHead>Dirigida a</TableHead>
                    <TableHead>Contacto</TableHead>
                    <TableHead>Nombre cotización</TableHead>
                    <TableHead>Responsable</TableHead>
                    <TableHead>Creado por</TableHead>
                    <TableHead>Creada</TableHead>
                    <TableHead>Vence</TableHead>
                    <TableHead className="text-right">Ítems</TableHead>
                    <TableHead className="text-right">Total</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {gridRows.map((row) => {
                    if (row.tipo === 'lead') {
                      const o = row.oportunidad
                      const nombreEmpresa =
                        o.cuerpo?.nombre || o.compania?.nombre || o.cliente?.nombre_empresa || `Oportunidad #${o.id}`
                      const nombreContacto = o.contacto?.nombre || "--"
                      const estadoNombre = o.estado?.nombre || "Nuevo"
                      const estadoColor = o.estado?.color || estados.find((e) => e.id === o.estado?.id)?.color || null
                      const titulo = `OP-${String(o.id).padStart(4, "0")}`

                      return (
                        <TableRow
                          key={`lead-${o.id}`}
                          className="cursor-pointer"
                          onClick={() => handleViewOppGestion(o)}
                        >
                          <TableCell className={cn("font-semibold whitespace-nowrap", DESTINATARIO_NUMERO_CLASS[tipoDestinatarioDe(o)])}>{titulo}</TableCell>
                          <TableCell>
                            <EstadoBadge nombre={estadoNombre} color={estadoColor} />
                          </TableCell>
                          <TableCell className="max-w-[180px] truncate font-medium">{nombreEmpresa}</TableCell>
                          <TableCell><DestinatarioBadge tipo={tipoDestinatarioDe(o)} /></TableCell>
                          <TableCell className="max-w-[160px] truncate text-muted-foreground">{nombreContacto}</TableCell>
                          <TableCell className="max-w-[180px] truncate text-muted-foreground">--</TableCell>
                          <TableCell className="whitespace-nowrap text-muted-foreground">{nombreUsuario(o.ejecutivo, "--")}</TableCell>
                          <TableCell className="whitespace-nowrap text-muted-foreground">{nombreUsuario(o.creador, "--")}</TableCell>
                          <TableCell className="whitespace-nowrap text-muted-foreground">
                            {formatDateDMY(o.created_at || o.fecha_ingreso)}
                          </TableCell>
                          <TableCell className="whitespace-nowrap">
                            <span className={cn("rounded-full px-2 py-0.5 text-xs font-medium", vencimientoLeadClass(getLeadVencimiento(o)))}>
                              {formatDateDMY(getLeadVencimiento(o))}
                            </span>
                          </TableCell>
                          <TableCell className="text-right text-muted-foreground">--</TableCell>
                          <TableCell className="text-right text-muted-foreground">--</TableCell>
                          <TableCell className="text-right">
                            <div className="flex items-center justify-end gap-1">
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7"
                                title="Ver gestión"
                                onClick={(e) => {
                                  e.stopPropagation()
                                  handleViewOppGestion(o)
                                }}
                              >
                                <CalendarClock className="h-3.5 w-3.5" />
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      )
                    }

                    const c = row.cotizacion
                    const grupo = row.grupo
                    const nombreEmpresa =
                      c.cuerpo?.nombre || c.compania?.nombre || c.cliente?.nombre_empresa || `Cotización #${c.id}`
                    const nombreContacto = c.contacto?.nombre || c.oportunidad?.contacto?.nombre || "--"
                    const nombreVendedor = nombreUsuario(c.ejecutivo, "--")
                    const nombreCreador = nombreUsuario(c.creador, "--")
                    const titulo = c.numero ? c.numero : `CT-${String(c.id).padStart(4, "0")}`
                    const itemsCount = c.detalles?.length ?? 0
                    const total =
                      (typeof c.total === "number" && c.total > 0)
                        ? c.total
                        : (typeof c.total_neto === "number" && c.total_neto > 0)
                          ? c.total_neto
                          : 0
                    const vencimientoDateStr = c.ultima_gestion?.fecha_vencimiento_nueva || c.fecha_vencimiento
                    const noVigente = grupo === 'no_vigente' ? datosNoVigente(c, nombreEstadoDe(c)) : null

                    return (
                      <TableRow
                        key={`cot-${c.id}`}
                        className="cursor-pointer"
                        onClick={() => handleViewDetail(c)}
                      >
                        <TableCell className={cn("font-semibold whitespace-nowrap", DESTINATARIO_NUMERO_CLASS[tipoDestinatarioDe(c)])}>{titulo}</TableCell>
                        <TableCell>
                          <Badge className={cn("rounded-full font-medium", noVigente ? MOTIVO_BADGE_CLASS[noVigente.motivo] : GRUPO_BADGE_CLASS[grupo])}>
                            {noVigente ? `${GRUPO_LABEL[grupo]} · ${etiquetaMotivo(noVigente)}` : GRUPO_LABEL[grupo]}
                          </Badge>
                        </TableCell>
                        <TableCell className="max-w-[180px] truncate font-medium">{nombreEmpresa}</TableCell>
                        <TableCell><DestinatarioBadge tipo={tipoDestinatarioDe(c)} /></TableCell>
                        <TableCell className="max-w-[160px] truncate text-muted-foreground">{nombreContacto}</TableCell>
                        <TableCell className="max-w-[180px] truncate text-muted-foreground">{c.nombre || "Sin nombre"}</TableCell>
                        <TableCell className="whitespace-nowrap text-muted-foreground">{nombreVendedor}</TableCell>
                        <TableCell className="whitespace-nowrap text-muted-foreground">{nombreCreador}</TableCell>
                        <TableCell className="whitespace-nowrap text-muted-foreground">
                          {formatDateDMY(c.fecha_creacion || c.created_at)}
                        </TableCell>
                        <TableCell className="whitespace-nowrap text-muted-foreground">
                          {grupo === 'enviada' ? (
                            <span className={cn("rounded-full px-2 py-0.5 text-xs font-medium", vencimientoCotClass(vencimientoDateStr, !!c.ultima_gestion))}>
                              {formatDateDMY(vencimientoDateStr)}
                            </span>
                          ) : "--"}
                        </TableCell>
                        <TableCell className="text-right text-muted-foreground">{itemsCount}</TableCell>
                        <TableCell className="text-right font-semibold">{formatCLP(total)}</TableCell>
                        <TableCell className="text-right">
                          <div className="flex items-center justify-end gap-1">
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              className="h-7 w-7"
                              title="Ver detalle"
                              onClick={(e) => {
                                e.stopPropagation()
                                handleViewDetail(c)
                              }}
                            >
                              <Eye className="h-3.5 w-3.5" />
                            </Button>

                            {grupo === 'enviada' && (
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7"
                                title="Gestionar vencimiento"
                                onClick={(e) => {
                                  e.stopPropagation()
                                  handleViewGestion(c)
                                }}
                              >
                                <CalendarClock className="h-3.5 w-3.5" />
                              </Button>
                            )}

                            {grupo === 'ventas' && (
                              <>
                                <Button
                                  type="button"
                                  variant="ghost"
                                  size="icon"
                                  className="h-7 w-7"
                                  title="Ver archivos"
                                  onClick={(e) => {
                                    e.stopPropagation()
                                    handleViewArchivos(c)
                                  }}
                                >
                                  <FolderOpen className="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                  type="button"
                                  variant="ghost"
                                  size="icon"
                                  className="h-7 w-7"
                                  title="Personalización"
                                  onClick={(e) => {
                                    e.stopPropagation()
                                    router.push(`/comercial/cotizaciones/${c.id}/personalizacion`)
                                  }}
                                >
                                  <Palette className="h-3.5 w-3.5" />
                                </Button>
                              </>
                            )}

                            {grupo === 'no_vigente' && (
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7"
                                title="Ver motivo"
                                onClick={(e) => {
                                  e.stopPropagation()
                                  handleViewNoVigente(c)
                                }}
                              >
                                <Ban className="h-3.5 w-3.5" />
                              </Button>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    )
                  })}

                  {gridRows.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={13} className="h-32 text-center text-sm text-muted-foreground">
                        {(cotLoading || leadsLoading) ? "Cargando..." : numeroFiltro ? `No hay cotizaciones con el N° "${numeroFiltro}"` : "No hay leads ni cotizaciones"}
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </div>
          </div>
        ) : (
        <DndContext
          sensors={sensors}
          onDragStart={handleDragStart}
          onDragEnd={handleDragEnd}
        >
          <ScrollArea className="h-[calc(100vh-280px)] w-full">
            <div className="flex gap-6 pb-4">

              {/* LEADS (backend) */}
              <KanbanColumn title="Leads" count={leads.length} columnId="col-lead">
                <div className="mb-2 flex justify-end">
                  <button
                    onClick={reloadLeads}
                    disabled={leadsLoading}
                    className="text-xs underline text-muted-foreground hover:text-foreground disabled:opacity-50"
                  >
                    {leadsLoading ? "Cargando..." : "Refrescar"}
                  </button>
                </div>

                {leadsError && (
                  <div className="mb-3 rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                    {leadsError}
                  </div>
                )}

                {estadosError && (
                  <div className="mb-3 rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                    {estadosError}
                  </div>
                )}

                {leads
                  .filter((o) => o && typeof o.id === "number")
                  .map((oportunidad) => (
                    <OportunidadKanbanCard
                      key={oportunidad.id}
                      oportunidad={oportunidad}
                      estados={estados}
                      onEstadoUpdated={reloadBoard}
                      onViewGestion={handleViewOppGestion}
                    />
                  ))}

                {!leadsError && leads.length === 0 && !leadsLoading && (
                  <div className="rounded-lg border-2 border-dashed p-8 text-center">
                    <p className="text-sm text-muted-foreground">
                      {numeroFiltro ? "Los leads no se muestran al buscar por N° de cotización" : "No hay leads"}
                    </p>
                  </div>
                )}
              </KanbanColumn>
              <KanbanColumn title="Cotizaciones Enviadas" count={cotEnviadas.length} columnId="col-quote-sent">
                <div className="mb-2 flex items-center justify-between">
                  <div className="text-xs text-muted-foreground">{cotLoading ? "Cargando..." : ""}</div>
                  <button
                    onClick={reloadCotizaciones}
                    disabled={cotLoading}
                    className="text-xs underline text-muted-foreground hover:text-foreground disabled:opacity-50"
                  >
                    Refrescar
                  </button>
                </div>

                {cotError && (
                  <div className="mb-3 rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                    {cotError}
                  </div>
                )}

                {estadosCotError && (
                  <div className="mb-3 rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                    {estadosCotError}
                  </div>
                )}

                {!estadoIdsCot.idEnviada && (
                  <div className="mb-3 rounded-md border bg-muted/30 p-3 text-xs text-muted-foreground">
                    No se encontró el estado "Cotización Enviada" en scope=cotizacion.
                  </div>
                )}

                {sortedCotEnviadas.map((c) => (
                  <DraggableCard key={c.id} id={`quote-${c.id}`} data={{ quote: c }}>
                    <CotizacionKanbanCard
                      cotizacion={c}
                      estadosCotizacion={estadosCot}
                      onEstadoUpdated={reloadCotizaciones}
                      onViewDetail={handleViewDetail}
                      onViewGestion={handleViewGestion}
                    />
                  </DraggableCard>
                ))}

                {estadoIdsCot.idEnviada && cotEnviadas.length === 0 && !cotLoading && (
                  <div className="rounded-lg border-2 border-dashed p-8 text-center">
                    <p className="text-sm text-muted-foreground">No hay cotizaciones enviadas</p>
                  </div>
                )}
              </KanbanColumn>

              {/* APROBADO POR CLIENTE (backend real) */}
              <KanbanColumn title="Ventas" count={cotAprobadas.length} columnId="col-approved">
                {!estadoIdsCot.idAprobada && (
                  <div className="mb-3 rounded-md border bg-muted/30 p-3 text-xs text-muted-foreground">
                    No se encontró el estado "Ventas" en scope=cotizacion.
                  </div>
                )}

                {cotAprobadas.map((c) => (
                  <DraggableCard key={c.id} id={`quote-${c.id}`} data={{ quote: c }}>
                    <CotizacionKanbanCard
                      cotizacion={c}
                      estadosCotizacion={estadosCot}
                      onEstadoUpdated={reloadCotizaciones}
                      onViewDetail={handleViewDetail}
                      onViewArchivos={handleViewArchivos}
                      onViewPersonalizacion={(c) => router.push(`/comercial/cotizaciones/${c.id}/personalizacion`)}
                      onSendPersonalizacion={async (c) => {
                        try {
                          await api.post(`/api/cotizaciones/${c.id}/send-personalizacion`)
                          toast.success("Enviado a módulo de personalización exitosamente.")
                          reloadCotizaciones()
                        } catch (err: any) {
                          toast.error(err.response?.data?.message || "Error al enviar a personalización.")
                        }
                      }}
                    />
                  </DraggableCard>
                ))}

                {estadoIdsCot.idAprobada && cotAprobadas.length === 0 && (
                  <div className="rounded-lg border-2 border-dashed p-8 text-center">
                    <p className="text-sm text-muted-foreground">No hay cotizaciones aprobadas</p>
                  </div>
                )}
              </KanbanColumn>

              {/* NO VIGENTES: todas las que salieron del flujo, con su motivo */}
              <KanbanColumn
                title="No Vigentes"
                count={cotNoVigentesFiltradas.length}
                columnId="col-no-vigentes"
                headerAction={
                  <Select value={filtroMotivo} onValueChange={(v) => setFiltroMotivo(v as "all" | MotivoTarjeta)}>
                    <SelectTrigger className="h-7 w-[140px] text-xs" aria-label="Filtrar por motivo">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">Todos</SelectItem>
                      {(Object.keys(MOTIVO_LABEL) as MotivoTarjeta[]).map((m) => (
                        <SelectItem key={m} value={m}>{MOTIVO_LABEL[m]}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                }
              >
                {!estadoIdsCot.idNoVigente && (
                  <div className="mb-3 rounded-md border bg-muted/30 p-3 text-xs text-muted-foreground">
                    No se encontró el estado "No Vigente" en scope=cotizacion.
                  </div>
                )}

                {cotNoVigentesFiltradas.map((c) => {
                  // Una finalización administrativa solo la reactiva un superadmin
                  const bloqueada = !puedeSalirDeNoVigentes(c, nombreEstadoDe(c), esSuperadmin)
                  return (
                    <DraggableCard key={c.id} id={`quote-${c.id}`} data={{ quote: c }} disabled={bloqueada}>
                      <CotizacionKanbanCard
                        cotizacion={c}
                        estadosCotizacion={estadosCot}
                        onEstadoUpdated={reloadCotizaciones}
                        onViewDetail={handleViewDetail}
                        onViewNoVigente={handleViewNoVigente}
                        reactivacionBloqueada={bloqueada}
                      />
                    </DraggableCard>
                  )
                })}

                {cotNoVigentesFiltradas.length === 0 && !cotLoading && (
                  <div className="rounded-lg border-2 border-dashed p-8 text-center">
                    <p className="text-sm text-muted-foreground">
                      {filtroMotivo === "all" ? "No hay cotizaciones no vigentes" : `No hay cotizaciones con motivo "${MOTIVO_LABEL[filtroMotivo]}"`}
                    </p>
                  </div>
                )}
              </KanbanColumn>
            </div>

            <ScrollBar orientation="horizontal" />
          </ScrollArea>

          <DragOverlay>
            {activeQuote ? (
              <div className="opacity-90 rotate-2 scale-105">
                <CotizacionKanbanCard
                  cotizacion={activeQuote}
                  estadosCotizacion={estadosCot}
                  onEstadoUpdated={() => { }}
                />
              </div>
            ) : activeLead ? (
              <div className="opacity-90 rotate-2 scale-105">
                <OportunidadDragPreview oportunidad={activeLead} />
              </div>
            ) : null}
          </DragOverlay>

        </DndContext>
        )}
      </main>

      <AlertDialog open={confirmState.open} onOpenChange={(open) => {
        if (!open && !moveInFlight.current) setConfirmState(prev => ({ ...prev, open: false }))
      }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{confirmState.kind === 'opportunity' ? '¿Crear cotización para este lead?' : '¿Mover cotización?'}</AlertDialogTitle>
            <AlertDialogDescription>
              {confirmState.kind === 'opportunity' ? (
                <>Se creará la cotización del lead <strong>{confirmState.recordNumber}</strong> y se abrirá para completar sus productos y condiciones.</>
              ) : (
                <>¿Estás seguro que deseas mover la cotización <strong>{confirmState.recordNumber}</strong> al estado <strong>{confirmState.targetStateName}</strong>?</>
              )}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={moving}>Cancelar</AlertDialogCancel>
            <AlertDialogAction disabled={moving} onClick={async (event) => {
              event.preventDefault()
              await handleConfirmMove()
            }}>{moving ? 'Procesando…' : confirmState.kind === 'opportunity' ? 'Crear cotización' : 'Mover'}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* DESACTIVADO (usa ClientesDialog) */}
      <Dialog open={false} onOpenChange={() => { }}>
        <DialogContent className="sm:max-w-[700px]">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Users className="h-4 w-4" />
              Clientes
            </DialogTitle>
            <DialogDescription>
              Administra la base de datos de clientes y contactos comerciales.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-3">
            <div className="flex items-center gap-2">
              <div className="relative flex-1">
                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input value={""} onChange={() => { }} placeholder="Buscar por nombre, RUT o email..." className="pl-8" />
              </div>

              <Button variant="outline" onClick={() => { }}>
                Refrescar
              </Button>
            </div>

            <div className="rounded-md border">
              <ScrollArea className="h-[360px]">
                <div className="divide-y">
                  <div className="p-4 text-sm text-muted-foreground">Desactivado (usa ClientesDialog).</div>
                </div>
              </ScrollArea>
            </div>
          </div>

          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => { }}>
              Cerrar
            </Button>

            <Button onClick={() => router.push("/comercial/clientes/nueva")}>
              <Plus className="h-4 w-4" />
              Agregar cliente
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Dialog para subir archivos antes de aprobar */}
      <CotizacionArchivosDialog
        open={fileDialogState.open}
        onOpenChange={(open) => setFileDialogState(prev => ({ ...prev, open }))}
        cotizacionId={fileDialogState.quoteId}
        archivos={(() => {
          // Buscar la cotización actualizada en las listas para pasar los archivos realtime
          const allCots = [...cotEnviadas, ...cotAprobadas, ...cotNoVigentes]
          const current = allCots.find(c => c.id === fileDialogState.quoteId)
          return current?.archivos || []
        })()}
        onSuccess={() => {
          // Recargar cotizaciones para actualizar lista de archivos y permitir mover
          reloadCotizaciones()
        }}
        onApprove={() => {
          if (fileDialogState.targetStateId && fileDialogState.quoteId) {
            // Ejecutar el movimiento
            api.patch(`/api/cotizaciones/${fileDialogState.quoteId}/estado`, {
              estado_id: fileDialogState.targetStateId
            })
              .then(() => {
                toast.success("Cotización aprobada y movida exitosamente.")
                reloadCotizaciones()
                setFileDialogState(prev => ({ ...prev, open: false }))
              })
              .catch((e) => {
                console.error(e)
                toast.error("Error al mover la cotización.")
              })
          } else {
            setFileDialogState(prev => ({ ...prev, open: false }))
          }
        }}
      />

      <CotizacionNoVigenteDialog
        open={noVigenteDialogState.open}
        onOpenChange={(open) => setNoVigenteDialogState(prev => ({ ...prev, open }))}
        numeroCotizacion={noVigenteDialogState.quoteNumber}
        esSuperadmin={esSuperadmin}
        enVenta={noVigenteDialogState.enVenta}
        onConfirm={handleConfirmNoVigente}
      />

      {/* ✅ GLOBAL DIALOGS */}
      {selectedQuote && (
        <>
          <CotizacionDetailDialog
            open={activeDialog === 'detail'}
            onOpenChange={(open) => !open && closeGlobalDialogs()}
            cotizacion={selectedQuote}
            onDeleted={() => {
              closeGlobalDialogs()
              reloadCotizaciones()
            }}
            onTraspasado={(actualizada) => {
              // El diálogo sigue abierto: refleja de inmediato el nuevo responsable
              setSelectedQuote((prev: any) => (prev ? { ...prev, ...actualizada } : prev))
              reloadCotizaciones()
            }}
          />

          <CotizacionGestionDialog
            open={activeDialog === 'gestion'}
            onOpenChange={(open) => !open && closeGlobalDialogs()}
            cotizacionId={selectedQuote.id}
            currentVencimiento={selectedQuote.ultima_gestion?.fecha_vencimiento_nueva || selectedQuote.fecha_vencimiento}
            onSuccess={() => {
              // closeGlobalDialogs() // Keep open? Or close?
              reloadCotizaciones()
            }}
          />

          {/* Reusa el dialog de archivos pero en modo solo lectura/carga simple */}
          <CotizacionArchivosDialog
            open={activeDialog === 'archivos'}
            onOpenChange={(open) => !open && closeGlobalDialogs()}
            cotizacionId={selectedQuote.id}
            archivos={selectedQuote.archivos || []}
            onSuccess={() => reloadCotizaciones()}
          />

          {/* Motivo, observación y fecha de una cotización No Vigente */}
          <CotizacionNoVigenteInfoDialog
            open={activeDialog === 'no-vigente-info'}
            onOpenChange={(open) => !open && closeGlobalDialogs()}
            numeroCotizacion={selectedQuote.numero || `CT-${String(selectedQuote.id).padStart(4, "0")}`}
            datos={datosNoVigente(selectedQuote, nombreEstadoDe(selectedQuote))}
            cotizacionId={selectedQuote.id}
            archivos={selectedQuote.archivos}
            onArchivoSubido={async () => {
              // El diálogo sigue abierto: trae la cotización con su archivo nuevo
              const res: any = await api.get(`/api/cotizaciones/${selectedQuote.id}`).catch(() => null)
              const fresca = res?.data ?? res
              if (fresca?.id) setSelectedQuote((prev: any) => (prev ? { ...prev, archivos: fresca.archivos } : prev))
              reloadCotizaciones()
            }}
          />
        </>
      )}

      <NuevaOportunidadDialog
        open={openNewOpp}
        onOpenChange={setOpenNewOpp}
        onSuccess={() => { reloadBoard() }}
      />

      {selectedOportunidad && (
        <OportunidadGestionDialog
          open={activeOppDialog === 'gestion'}
          onOpenChange={(open) => !open && closeOppDialogs()}
          oportunidad={selectedOportunidad}
          onGestionAgregada={() => { reloadBoard() }}
        />
      )}
    </div>
  )
}

