import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { api, downloadPdf } from "@/lib/api"

export interface MovimientoItem {
    id: number
    sku: string
    producto: string
    talla: string
    color: string
    cantidad: number
    saldo_anterior: number
    saldo_nuevo: number
    cotizacion_id?: number | null
    cotizacion_numero?: string | null
    referencia_externa?: string | null
    cliente_nombre?: string | null
}
export interface LoteMovimiento {
    movimiento_id: number
    id_lote: string | null
    fecha_hora_grupo: string
    usuario: string
    solicitante?: string | null
    /** Qué hizo `usuario`: solicitó (reserva), aprobó (despacho), rechazó (anulación) o registró (resto). */
    rol_actor?: "solicito" | "aprobo" | "rechazo" | "registro"
    /** Solo en solicitudes de reserva con solicitud vinculada. */
    estado_solicitud?: "pendiente" | "aprobada" | "rechazada" | "parcial" | null
    resuelto_por?: string | null
    resuelto_en?: string | null
    tipo_movimiento: string
    total_registros: number
    total_unidades: number
    cotizacion_id: number | null
    cotizacion_numero?: string | null
    referencia_externa?: string | null
    cliente_nombre: string | null
    producto_resumen: string
}
interface Pagination { current_page: number; per_page: number; total: number; last_page: number }
interface Page<T> { data: T[]; pagination: Pagination }
interface Detail { page?: Page<MovimientoItem>; loading: boolean; error?: string; requestedPage: number }
const emptyPagination: Pagination = { current_page: 1, per_page: 25, total: 0, last_page: 1 }
const currentMonth = () => {
    const now = new Date()
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`
}

export function weeksOfMonth(month: string) {
    const [year, monthNumber] = month.split("-").map(Number)
    const offset = (new Date(year, monthNumber - 1, 1).getDay() + 6) % 7
    const days = new Date(year, monthNumber, 0).getDate()
    return Array.from({ length: Math.ceil((days + offset) / 7) }, (_, index) => {
        const start = Math.max(1, index * 7 - offset + 1)
        const end = Math.min(days, (index + 1) * 7 - offset)
        return { number: index + 1, start, end, label: `Semana ${index + 1} · ${String(start).padStart(2, "0")}–${String(end).padStart(2, "0")}/${String(monthNumber).padStart(2, "0")}` }
    })
}

export function useBitacora() {
    const [isOpen, setIsOpen] = useState(false)
    const [collapsedDays, setCollapsedDays] = useState<Set<string>>(new Set())
    const toggleDay = (date: string) => setCollapsedDays(previous => {
        const next = new Set(previous)
        if (next.has(date)) next.delete(date)
        else next.add(date)
        return next
    })
    const [filters, setFilters] = useState(() => ({ mes: currentMonth(), semana: "", tipo: "", fecha: "", usuario_id: "", page: 1 }))
    const [users, setUsers] = useState<{ value: string; label: string }[]>([])
    const [usersLoading, setUsersLoading] = useState(false)
    const [usersError, setUsersError] = useState("")
    const [revision, setRevision] = useState(0)
    const [data, setData] = useState<LoteMovimiento[]>([])
    const [pagination, setPagination] = useState(emptyPagination)
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState("")
    const [details, setDetails] = useState<Record<number, Detail>>({})
    const [expandedId, setExpandedId] = useState<number | null>(null)
    const [pdfError, setPdfError] = useState("")
    const [downloading, setDownloading] = useState<string | null>(null)
    const detailRequests = useRef(new Map<number, AbortController>())
    const pdfRequest = useRef<AbortController | null>(null)

    const abortDetails = useCallback(() => {
        detailRequests.current.forEach(controller => controller.abort())
        detailRequests.current.clear()
    }, [])

    useEffect(() => {
        if (!isOpen) return
        const controller = new AbortController()
        setUsersLoading(true)
        setUsersError("")
        api.get<{ data: { value: string; label: string }[] }>(`/api/movimientos/usuarios?mes=${filters.mes}`, { signal: controller.signal })
            .then(result => { if (!controller.signal.aborted) setUsers(result.data) })
            .catch(() => { if (!controller.signal.aborted) setUsersError("No se pudieron cargar los usuarios del mes.") })
            .finally(() => { if (!controller.signal.aborted) setUsersLoading(false) })
        return () => controller.abort()
    }, [isOpen, filters.mes, revision])

    useEffect(() => {
        abortDetails()
        setDetails({})
        setExpandedId(null)
        setPdfError("")
        if (!isOpen) return
        const controller = new AbortController()
        setLoading(true)
        setError("")
        setData([])
        const params = new URLSearchParams({ mes: filters.mes, page: String(filters.page), per_page: "25" })
        if (filters.tipo) params.set("tipo", filters.tipo)
        if (filters.semana) params.set("semana", filters.semana)
        if (filters.fecha) params.set("fecha", filters.fecha)
        if (filters.usuario_id) params.set("usuario_id", filters.usuario_id)
        api.get<Page<LoteMovimiento>>(`/api/movimientos/general?${params}`, { signal: controller.signal })
            .then(result => {
                if (controller.signal.aborted) return
                setData(result.data)
                setPagination(result.pagination)
            })
            .catch(() => {
                if (!controller.signal.aborted) setError("No se pudo cargar la bitácora. Reintenta sin cambiar tus filtros.")
            })
            .finally(() => { if (!controller.signal.aborted) setLoading(false) })
        return () => { controller.abort(); abortDetails() }
    }, [isOpen, filters, revision, abortDetails])

    useEffect(() => () => { abortDetails(); pdfRequest.current?.abort() }, [abortDetails])

    const open = () => { setLoading(true); setIsOpen(true) }
    const close = () => {
        abortDetails()
        pdfRequest.current?.abort()
        pdfRequest.current = null
        setDownloading(null)
        setIsOpen(false)
    }
    const changeFilter = (name: "mes" | "semana" | "tipo" | "fecha" | "usuario_id", value: string) => {
        if (name === "mes" && !/^\d{4}-\d{2}$/.test(value)) return
        setLoading(true)
        if (name === "mes") setUsers([])
        setFilters(prev => ({ ...prev, [name]: value, ...(name === "mes" ? { fecha: "", semana: "", usuario_id: "" } : name === "semana" ? { fecha: "" } : {}), page: 1 }))
    }
    const goToPage = (page: number) => {
        setLoading(true)
        setFilters(prev => ({ ...prev, page }))
    }
    const fetchDetails = async (id: number, page = 1) => {
        if (detailRequests.current.has(id)) return
        const controller = new AbortController()
        detailRequests.current.set(id, controller)
        setDetails(prev => ({ ...prev, [id]: { ...prev[id], loading: true, error: undefined, requestedPage: page } }))
        try {
            const result = await api.get<Page<MovimientoItem>>(`/api/movimientos/lote-items?movimiento_id=${id}&page=${page}`, { signal: controller.signal })
            if (!controller.signal.aborted) setDetails(prev => ({ ...prev, [id]: { page: result, loading: false, requestedPage: page } }))
        } catch {
            if (!controller.signal.aborted) setDetails(prev => ({ ...prev, [id]: { ...prev[id], loading: false, error: "No se pudieron cargar los productos.", requestedPage: page } }))
        } finally {
            if (detailRequests.current.get(id) === controller) detailRequests.current.delete(id)
        }
    }
    const toggleLote = (id: number) => {
        setExpandedId(prev => prev === id ? null : id)
        if (!details[id]?.page) void fetchDetails(id)
    }
    const download = async (lote?: LoteMovimiento, period: "month" | "week" = "month") => {
        if (pdfRequest.current) return
        if (!lote && period === "week" && !filters.semana) return
        const controller = new AbortController()
        pdfRequest.current = controller
        setDownloading(lote ? String(lote.movimiento_id) : period)
        setPdfError("")
        const params = new URLSearchParams(lote ? { movimiento_id: String(lote.movimiento_id) } : { mes: filters.mes })
        if (!lote && filters.tipo) params.set("tipo", filters.tipo)
        if (!lote && filters.usuario_id) params.set("usuario_id", filters.usuario_id)
        if (!lote && period === "week") params.set("semana", filters.semana)
        try {
            await downloadPdf(`/api/movimientos/pdf/${lote ? "individual" : period === "week" ? "semanal" : "mensual"}?${params}`, {
                filename: lote ? `movimiento-${lote.movimiento_id}.pdf` : `bitacora-${filters.mes}${period === "week" ? `-semana-${filters.semana}` : ""}.pdf`,
                throwOnError: true, signal: controller.signal,
            })
        } catch (err) {
            if (!controller.signal.aborted) setPdfError(err instanceof Error ? err.message : "No se pudo generar el PDF. Reintenta la descarga.")
        } finally {
            if (pdfRequest.current === controller) { pdfRequest.current = null; setDownloading(null) }
        }
    }
    const groups = useMemo(() => data.reduce<Record<string, LoteMovimiento[]>>((result, lote) => {
        const date = lote.fecha_hora_grupo.split(" ")[0]
        ;(result[date] ??= []).push(lote)
        return result
    }, {}), [data])
    const weeks = useMemo(() => weeksOfMonth(filters.mes), [filters.mes])
    const weekGroups = useMemo(() => weeks.slice().reverse().map(week => ({
        ...week,
        days: Object.entries(groups).filter(([date]) => {
            const day = Number(date.split("/")[0])
            return day >= week.start && day <= week.end
        }),
    })).filter(week => week.days.length > 0), [weeks, groups])

    return { isOpen, open, close, collapsedDays, toggleDay, filters, changeFilter, groups, weeks, weekGroups, pagination, loading, error, users, usersLoading, usersError,
        retry: () => { setLoading(true); setRevision(n => n + 1) }, goToPage,
        details, expandedId, toggleLote, fetchDetails, download, downloading, pdfError }
}
