import { useState, useCallback, useEffect, useMemo } from "react"
import { api } from "@/lib/api"
import type { Traspaso, UsuarioRef } from "@/lib/responsables"
import type { TipoDestinatario } from "@/lib/destinatario"
import type { TarjetaNoVigente } from "@/lib/no-vigentes"

export type Opportunity = {
    id: number
    empresa: string | null
    nombre_contacto: string | null
    numero_contacto: string | null
    referido_por: string | null
    cliente_id: string | null
    cliente?: { id: string; nombre_empresa: string; tipo?: string | null } | null
    tipo_destinatario?: TipoDestinatario | null
    cuerpo_id?: string | null
    cuerpo?: { id: string; nombre: string } | null
    compania_id?: string | null
    compania?: { id: string; nombre: string; cuerpo?: { id: string; nombre: string } | null } | null
    user_id?: number | null
    creado_por_id?: number | null
    ejecutivo?: UsuarioRef | null
    creador?: UsuarioRef | null
    traspasos?: Traspaso[]
    estado?: { id: number; nombre: string; color?: string } | null
    created_at?: string | null
}

export type Estado = {
    id: number
    nombre: string
    color?: string | null
    scope?: string
    orden?: number
}

export type Cotizacion = TarjetaNoVigente & {
    id: number
    numero?: string | null
    oportunidad_id?: number | null
    cliente_id?: string | null
    ejecutivo_id?: number | null
    user_id?: number | null
    creado_por_id?: number | null
    ejecutivo?: UsuarioRef | null
    creador?: UsuarioRef | null
    traspasos?: Traspaso[]
    estado_id: number
    fecha_vencimiento?: string | null
    total?: number | null
    total_neto?: number | null
    iva?: number | null
    origen?: string | null
    cliente?: { id: string; nombre_empresa?: string | null; tipo?: string | null } | null
    tipo_destinatario?: TipoDestinatario | null
    contacto?: { id: number; nombre: string } | null
    cuerpo?: { id: string; nombre: string } | null
    compania?: { id: string; nombre: string; cuerpo?: { id: string; nombre: string } | null } | null
    detalles?: Array<{ id: number; sku: string; cantidad: number }> | null
    archivos?: Array<{ id: number; tipo: string; nombre_archivo: string; url: string }>
    created_at?: string | null
    ultima_gestion?: {
        glosa: string
        fecha_gestion: string
        fecha_vencimiento_nueva: string
    } | null
}

function safeArray<T>(maybe: any): T[] {
    if (Array.isArray(maybe)) return maybe
    if (Array.isArray(maybe?.data)) return maybe.data
    return []
}

// Los endpoints paginan con tope de 100 por página (el backend ignora per_page mayores),
// así que se recorren todas las páginas en vez de confiar en un per_page grande.
const MAX_PAGES = 50

async function fetchAllPages<T>(path: string, params: URLSearchParams): Promise<T[]> {
    params.set("per_page", "100")
    const all: T[] = []
    for (let page = 1; page <= MAX_PAGES; page++) {
        params.set("page", String(page))
        const json: any = await api.get(`${path}?${params.toString()}`)
        all.push(...safeArray<T>(json))
        const last = Number(json?.last_page)
        if (!Number.isFinite(last) || page >= last) break
    }
    return all
}

function norm(s: string) {
    return (s || "").trim().toLowerCase()
}

type Paginated<T> = {
    data: T[]
}

export function useComercialData() {
    const [leads, setLeads] = useState<Opportunity[]>([])
    const [leadsLoading, setLeadsLoading] = useState(false)
    const [leadsError, setLeadsError] = useState<string | null>(null)

    const [estados, setEstados] = useState<Estado[]>([])
    const [estadosError, setEstadosError] = useState<string | null>(null)

    const [estadosCot, setEstadosCot] = useState<Estado[]>([])
    const [estadosCotError, setEstadosCotError] = useState<string | null>(null)

    const [cotizaciones, setCotizaciones] = useState<Cotizacion[]>([])
    const [cotLoading, setCotLoading] = useState(false)
    const [cotError, setCotError] = useState<string | null>(null)

    const [users, setUsers] = useState<{ id: number; nombre: string }[]>([])
    // "clientes" acá es en realidad el universo completo para el filtro "Todos
    // los clientes": Cuerpos + Compañías + clientes sueltos, cada uno tageado
    // con su "kind" para saber contra qué campo comparar al filtrar.
    const [clientes, setClientes] = useState<{ id: string; nombre_empresa: string; kind: "cuerpo" | "compania" | "cliente"; tipo?: string | null }[]>([])

    const loadUsers = useCallback(async () => {
        try {
            const json = await api.get("/api/ejecutivos")
            setUsers(safeArray(json))
        } catch (e) {
            console.error(e)
        }
    }, [])

    const loadClientes = useCallback(async () => {
        try {
            const [cuerposRes, companiasRes, clientesRes] = await Promise.all([
                api.get<any>("/api/cuerpos"),
                api.get<any>("/api/companias"),
                // tipo en el servidor: hay ~2.000 copias "bomberos" que, sin él, llenan
                // los 100 resultados y dejan fuera a las empresas y particulares reales.
                api.get<any>("/api/clientes?per_page=100&tipo=empresa,particular"),
            ])
            const cuerpos = safeArray<any>(cuerposRes).map((c) => ({ id: String(c.id), nombre_empresa: c.nombre, kind: "cuerpo" as const }))
            const companias = safeArray<any>(companiasRes).map((c) => ({ id: String(c.id), nombre_empresa: c.nombre, kind: "compania" as const }))
            // Filtramos tipo="bomberos": mientras no corra la limpieza de clientes
            // espejo en el backend, /api/clientes todavía puede traerlos junto a
            // los sueltos reales, y ya vienen listados arriba vía /api/cuerpos y
            // /api/companias.
            const sueltos = safeArray<any>(clientesRes)
                .filter((c) => c.tipo !== "bomberos")
                .map((c) => ({ id: String(c.id), nombre_empresa: c.nombre_empresa, kind: "cliente" as const, tipo: c.tipo ?? null }))
            setClientes([...cuerpos, ...companias, ...sueltos])
        } catch (e) {
            console.error(e)
        }
    }, [])

    const loadLeads = useCallback(async (filters?: {
        ejecutivo_id?: string | number,
        cliente_id?: string | number,
        cuerpo_id?: string | number,
        compania_id?: string | number,
        tipo_destinatario?: string,
    }) => {
        setLeadsLoading(true)
        setLeadsError(null)
        try {
            const params = new URLSearchParams()
            params.set("exclude_has_quotation", "1")

            // El endpoint de oportunidades llama user_id a lo que el tablero llama ejecutivo
            if (filters?.ejecutivo_id && filters.ejecutivo_id !== "all") {
                params.set("user_id", String(filters.ejecutivo_id))
            }
            if (filters?.cliente_id && filters.cliente_id !== "all") {
                params.set("cliente_id", String(filters.cliente_id))
            }
            if (filters?.cuerpo_id) {
                params.set("cuerpo_id", String(filters.cuerpo_id))
            }
            if (filters?.compania_id) {
                params.set("compania_id", String(filters.compania_id))
            }
            if (filters?.tipo_destinatario) {
                params.set("tipo_destinatario", filters.tipo_destinatario)
            }

            const list = await fetchAllPages<Opportunity>("/api/oportunidades", params)
            // Sort oldest first (ascendente)
            list.sort((a, b) => a.id - b.id)
            setLeads(list)
        } catch (e: any) {
            setLeadsError(e?.message || "Error cargando leads")
            setLeads([])
        } finally {
            setLeadsLoading(false)
        }
    }, [])

    const loadEstadosOportunidad = useCallback(async () => {
        setEstadosError(null)
        try {
            const json = await api.get<Estado[] | Paginated<Estado>>("/api/estados?scope=oportunidad")
            setEstados(safeArray<Estado>(json))
        } catch (e: any) {
            setEstadosError(e?.message || "Error cargando estados")
            setEstados([])
        }
    }, [])

    const loadEstadosCotizacion = useCallback(async () => {
        setEstadosCotError(null)
        try {
            const json = await api.get<Estado[] | Paginated<Estado>>("/api/estados?scope=cotizacion")
            const list = safeArray<Estado>(json)
            list.sort((a, b) => (a.orden ?? 999) - (b.orden ?? 999) || a.id - b.id)
            setEstadosCot(list)
        } catch (e: any) {
            setEstadosCotError(e?.message || "Error cargando estados de cotización")
            setEstadosCot([])
        }
    }, [])

    const loadCotizaciones = useCallback(async (filters?: {
        ejecutivo_id?: string | number,
        cliente_id?: string | number,
        cuerpo_id?: string | number,
        compania_id?: string | number,
        from?: string,
        to?: string,
        numero?: string,
        tipo_destinatario?: string
    }) => {
        setCotLoading(true)
        setCotError(null)
        try {
            const params = new URLSearchParams()

            if (filters?.ejecutivo_id && filters.ejecutivo_id !== "all") {
                params.set("ejecutivo_id", String(filters.ejecutivo_id))
            }
            if (filters?.cliente_id && filters.cliente_id !== "all") {
                params.set("cliente_id", String(filters.cliente_id))
            }
            if (filters?.cuerpo_id) {
                params.set("cuerpo_id", String(filters.cuerpo_id))
            }
            if (filters?.compania_id) {
                params.set("compania_id", String(filters.compania_id))
            }
            if (filters?.from) params.set("from", filters.from)
            if (filters?.to) params.set("to", filters.to)
            if (filters?.numero) params.set("numero", filters.numero)
            if (filters?.tipo_destinatario) params.set("tipo_destinatario", filters.tipo_destinatario)

            const list = await fetchAllPages<Cotizacion>("/api/cotizaciones", params)
            list.sort((a, b) => (b.id ?? 0) - (a.id ?? 0))
            setCotizaciones(list)
        } catch (e: any) {
            setCotError(e?.message || "Error cargando cotizaciones")
            setCotizaciones([])
        } finally {
            setCotLoading(false)
        }
    }, [])

    const refreshAll = useCallback(() => {
        loadLeads()
        loadCotizaciones() // Reload without filters defaults to all? We might need to persist filters in page
    }, [loadLeads, loadCotizaciones])

    useEffect(() => {
        loadEstadosOportunidad()
        loadEstadosCotizacion()
        loadUsers()
        loadClientes()
        // loadCotizaciones() y loadLeads() los llama la página con los filtros activos
    }, [loadEstadosOportunidad, loadEstadosCotizacion, loadUsers, loadClientes])


    // Computed columns
    const estadoIdsCot = useMemo(() => {
        const byName = new Map<string, number>()
        for (const e of estadosCot) byName.set(norm(e.nombre), e.id)

        const idEnviada =
            byName.get("cotización enviada") ||
            byName.get("cotizacion enviada") ||
            byName.get("enviada") ||
            byName.get("cotizacion enviadas") ||
            null

        const idAprobada =
            byName.get("ventas") ||
            byName.get("aprobada por cliente") ||
            byName.get("aprobada") ||
            byName.get("aprobado por cliente") ||
            null

        const idRechazada = byName.get("rechazada") || byName.get("rechazado") || null
        const idAnulada = byName.get("anulada") || byName.get("anulado") || null
        const idNoVigente = byName.get("no vigente") || null

        return { idEnviada, idAprobada, idRechazada, idAnulada, idNoVigente }
    }, [estadosCot])

    const cotEnviadas = useMemo(() => {
        if (!estadoIdsCot.idEnviada) return []
        return cotizaciones.filter((c) => c.estado_id === estadoIdsCot.idEnviada)
    }, [cotizaciones, estadoIdsCot.idEnviada])

    const cotAprobadas = useMemo(() => {
        if (!estadoIdsCot.idAprobada) return []
        return cotizaciones.filter((c) => c.estado_id === estadoIdsCot.idAprobada)
    }, [cotizaciones, estadoIdsCot.idAprobada])

    // Etapa "No Vigentes": el estado nuevo más Rechazadas y Anuladas anteriores,
    // que no se migraron y conservan su estado.
    const cotNoVigentes = useMemo(() => {
        const ids = new Set([estadoIdsCot.idNoVigente, estadoIdsCot.idRechazada, estadoIdsCot.idAnulada].filter(Boolean))
        return cotizaciones.filter((c) => ids.has(c.estado_id))
    }, [cotizaciones, estadoIdsCot.idNoVigente, estadoIdsCot.idRechazada, estadoIdsCot.idAnulada])

    return {
        leads,
        leadsLoading,
        leadsError,
        estados,
        estadosError,
        estadosCot,
        estadosCotError,
        cotizaciones,
        cotLoading,
        cotError,
        cotEnviadas,
        cotAprobadas,
        cotNoVigentes,
        estadoIdsCot,
        loadLeads,
        loadCotizaciones,
        refreshAll,
        users, // Exported
        clientes, // Exported
    }
}
