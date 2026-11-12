import { useState, useEffect, useRef } from "react"
import { api } from "@/lib/api"

interface ProductoInventario {
    sku: string
    producto: string
    categoria: string
    talla: string
    color: string
    genero: string
    bodega: string
    cantidad_fisica: number
    reserved_stock?: number
    stock_critico: number
    precio_venta: number
    estado: string
}

export function useInventario() {
    const requestControllerRef = useRef<AbortController | null>(null)
    const requestIdRef = useRef(0)
    const [products, setProducts] = useState<ProductoInventario[]>([])
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState<string | null>(null)
    const [searchTerm, setSearchTerm] = useState("")
    const [stockFilter, setStockFilter] = useState<string | null>(null)
    const [tallaFilter, setTallaFilter] = useState<string | null>(null)
    const [sortConfig, setSortConfig] = useState<{ key: keyof ProductoInventario; direction: 'asc' | 'desc' } | null>({ key: 'producto', direction: 'asc' })

    // ESTADO DE PAGINACIÓN
    const [pagination, setPagination] = useState({
        currentPage: 1,
        lastPage: 1,
        total: 0,
        perPage: 20
    })

    // ESTADO DE STATS
    const [stats, setStats] = useState({
        total_skus: 0,
        agotados: 0,
        criticos: 0,
        bajo_stock: 0
    })

    const fetchInventario = async (
        page = 1,
        currentSort: typeof sortConfig = sortConfig,
        currentStockFilter: string | null = stockFilter,
        currentTallaFilter: string | null = tallaFilter
    ) => {
        requestControllerRef.current?.abort()
        const controller = new AbortController()
        const requestId = ++requestIdRef.current
        requestControllerRef.current = controller

        setLoading(true)
        try {
            let url = `/api/inventario?page=${page}&per_page=${pagination.perPage}`
            if (searchTerm) {
                url += `&search=${encodeURIComponent(searchTerm)}`
            }

            if (currentStockFilter) {
                url += `&stock_filter=${currentStockFilter}`
            }

            if (currentTallaFilter) {
                url += `&talla=${encodeURIComponent(currentTallaFilter)}`
            }

            if (currentSort) {
                url += `&sort_by=${currentSort.key}&order=${currentSort.direction}`
            }

            const res: any = await api.get(url, { signal: controller.signal })

            if (requestId !== requestIdRef.current) return false

            const pagData = res.pagination || {}
            const statsData = res.stats || {
                total_skus: 0,
                agotados: 0,
                criticos: 0,
                bajo_stock: 0
            }

            const data = pagData.data || []
            const meta = {
                currentPage: pagData.current_page,
                lastPage: pagData.last_page,
                total: pagData.total,
                perPage: pagData.per_page
            }

            setProducts(data)
            setPagination(meta)
            setStats(statsData)
            setError(null)
            return true
        } catch (err) {
            if (controller.signal.aborted || (err instanceof DOMException && err.name === 'AbortError')) return false
            if (requestId !== requestIdRef.current) return false
            console.error(err)
            setError("No pudimos cargar el inventario. Revisa tu conexión e intenta actualizar nuevamente.")
            return false
        } finally {
            if (requestId === requestIdRef.current) {
                setLoading(false)
            }
        }
    }

    // Efecto debounce para búsqueda
    useEffect(() => {
        // Invalida inmediatamente cualquier respuesta de una consulta anterior.
        requestIdRef.current += 1
        requestControllerRef.current?.abort()

        const timer = setTimeout(() => {
            fetchInventario(1, sortConfig, stockFilter, tallaFilter)
        }, 500)

        return () => clearTimeout(timer)
    }, [searchTerm])

    useEffect(() => {
        return () => requestControllerRef.current?.abort()
    }, [])

    const handleSort = (key: keyof ProductoInventario) => {
        setSortConfig(prev => {
            const newDirection = prev?.key === key && prev.direction === 'asc' ? 'desc' : 'asc'
            const newConfig = { key, direction: newDirection as 'asc' | 'desc' }
            fetchInventario(1, newConfig, stockFilter, tallaFilter)
            return newConfig
        })
    }

    const setAndFetchStockFilter = (filter: string | null) => {
        setStockFilter(filter)
        fetchInventario(1, sortConfig, filter, tallaFilter)
    }

    const setAndFetchTallaFilter = (filter: string | null) => {
        setTallaFilter(filter)
        fetchInventario(1, sortConfig, stockFilter, filter)
    }

    const sortedProducts = products

    const goToPage = (page: number) => {
        if (page >= 1 && page <= pagination.lastPage) {
            fetchInventario(page, sortConfig, stockFilter, tallaFilter)
        }
    }

    return {
        products,
        loading,
        error,
        searchTerm,
        setSearchTerm,
        stockFilter,
        setStockFilter: setAndFetchStockFilter,
        tallaFilter,
        setTallaFilter: setAndFetchTallaFilter,
        sortConfig,
        handleSort,
        sortedProducts,
        fetchInventario,
        pagination,
        goToPage,
        stats
    }
}
