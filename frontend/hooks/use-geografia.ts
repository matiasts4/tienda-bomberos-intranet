import { useState, useEffect } from "react"
import { api } from "@/lib/api"

export type Zona = { id: number; nombre: string; codigo?: string }
export type Region = { id: number; nombre: string; zona_id: number }
export type Provincia = { id: number; nombre: string; region_id: number }
export type Comuna = { id: number; nombre: string; region_id: number; provincia_id: number | null }

// El catálogo de comunas son 346 filas fijas: lo cacheamos a nivel de módulo
// para no pedirlo de nuevo en cada formulario que lo monte.
let comunasCache: Comuna[] | null = null
let comunasInFlight: Promise<Comuna[]> | null = null

export async function fetchComunas(): Promise<Comuna[]> {
    if (comunasCache) return comunasCache
    if (!comunasInFlight) {
        comunasInFlight = api
            .get<Comuna[]>("/api/geografia/comunas")
            .then((data) => {
                comunasCache = Array.isArray(data) ? data : []
                return comunasCache
            })
            .catch((e) => {
                console.error(e)
                return [] as Comuna[]
            })
            .finally(() => {
                comunasInFlight = null
            })
    }
    return comunasInFlight
}

/** Catálogo completo de comunas de Chile, cacheado. */
export function useComunas(options?: { enabled?: boolean }) {
    const enabled = options?.enabled ?? true
    const [comunas, setComunas] = useState<Comuna[]>(comunasCache ?? [])
    const [loading, setLoading] = useState(false)

    useEffect(() => {
        if (!enabled || comunasCache) return
        let cancelled = false
        setLoading(true)
        fetchComunas()
            .then((data) => {
                if (!cancelled) setComunas(data)
            })
            .finally(() => {
                if (!cancelled) setLoading(false)
            })
        return () => {
            cancelled = true
        }
    }, [enabled])

    return { comunas, loading }
}

export function useGeografia(options?: { enabled?: boolean }) {
    const enabled = options?.enabled ?? true

    const [zonas, setZonas] = useState<Zona[]>([])
    const [regiones, setRegiones] = useState<Region[]>([])
    const [provincias, setProvincias] = useState<Provincia[]>([])
    const [comunas, setComunas] = useState<Comuna[]>(comunasCache ?? [])
    const [loading, setLoading] = useState(false)

    useEffect(() => {
        if (!enabled) return
        loadZonas()
        loadRegiones()
        loadProvincias()
        fetchComunas().then(setComunas)
    }, [enabled])

    async function loadZonas() {
        try {
            const data = await api.get<Zona[]>("/api/geografia/zonas")
            setZonas(data || [])
        } catch (e) {
            console.error(e)
        }
    }

    async function loadRegiones() {
        try {
            const data = await api.get<Region[]>("/api/geografia/regiones")
            setRegiones(data || [])
        } catch (e) {
            console.error(e)
        }
    }

    async function loadProvincias() {
        try {
            const data = await api.get<Provincia[]>("/api/geografia/provincias")
            setProvincias(data || [])
        } catch (e) {
            console.error(e)
        }
    }

    return {
        zonas,
        regiones,
        provincias,
        comunas,
        loading
    }
}
