import { useState } from "react"
import * as XLSX from 'xlsx'
import { api } from "@/lib/api"
import { useCatalogos } from "../CatalogoContext"
import { pteNotice } from "../lib/pte-notice"

interface ProductoInventario {
    sku: string
    producto: string
    categoria: string
    talla: string
    color: string
    precio_venta: number
    cantidad_fisica: number
    stock_critico: number
}

const getStockLevel = (c: number, cri: number) => {
    if (c <= 0) return "none"
    if (c <= cri) return "critical"
    if (c <= cri * 2) return "low"
    return "normal"
}

export interface ImportSummary {
    total: number
    nuevos: any[]
    actualizados: any[]
    sinCambios: number
    duplicadosEliminados: number
}

const normalizeText = (text: string) => {
    return text.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim()
}

// Filtros y orden visibles en pantalla: el Excel exporta exactamente lo mismo.
export interface InventoryExportFilters {
    searchTerm?: string
    stockFilter?: string | null
    tallaFilter?: string | null
    sortConfig?: { key: string; direction: 'asc' | 'desc' } | null
}

export function buildInventoryReportUrl({ searchTerm, stockFilter, tallaFilter, sortConfig }: InventoryExportFilters = {}) {
    const params = new URLSearchParams()
    if (searchTerm) params.set('search', searchTerm)
    if (stockFilter) params.set('stock_filter', stockFilter)
    if (tallaFilter) params.set('talla', tallaFilter)
    if (sortConfig) {
        params.set('sort_by', String(sortConfig.key))
        params.set('order', sortConfig.direction)
    }
    const query = params.toString()
    return `/api/inventario/reporte${query ? `?${query}` : ''}`
}

const stockLabel = (cantidad: number, critico: number) => {
    const level = getStockLevel(cantidad, critico)
    if (level === 'none') return 'Agotado'
    if (level === 'critical') return 'Crítico'
    if (level === 'low') return 'Bajo'
    return 'Normal'
}

// Columnas del Excel. Prd y Num_Talla sirven para tablas dinámicas (producto × talla ordenada);
// "Cantidad" es el stock disponible y es la única columna de stock que lee la importación.
export function toExportRow(p: any) {
    const cantidad = Number(p.cantidad_fisica) || 0
    const reservado = Number(p.reserved_stock) || 0
    return {
        SKU: p.sku,
        Producto: p.producto,
        Prd: p.prd ?? p.producto,
        Categoría: p.categoria,
        Género: p.genero,
        Plantilla: p.plantilla ?? '',
        Talla: p.talla,
        Num_Talla: p.num_talla ?? p.talla,
        Color: p.color,
        Precio: p.precio_venta,
        Cantidad: cantidad,
        Reservado: reservado,
        Fisico: p.fisico ?? cantidad + reservado,
        Stock_Critico: p.stock_critico,
        Estado: stockLabel(cantidad, Number(p.stock_critico) || 0),
    }
}

export function exportFileName(now: Date = new Date()) {
    const pad = (n: number) => String(n).padStart(2, '0')
    return `Inventario_Bomberos_${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_${pad(now.getHours())}${pad(now.getMinutes())}.xlsx`
}

export function useExcelImportExport(fetchSuccess: () => void, filters: InventoryExportFilters = {}) {
    const { catalogos, initCatalogos } = useCatalogos()
    const [loadingImport, setLoadingImport] = useState(false)
    const [isExporting, setIsExporting] = useState(false)
    const [pendingImport, setPendingImport] = useState<ImportSummary | null>(null)
    const [isConfirmModalOpen, setIsConfirmModalOpen] = useState(false)

    const handleImportExcel = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0]
        if (!file) return

        setLoadingImport(true)
        try {
            // Aseguramos que los catálogos estén cargados para el mapeo de nombres
            const freshCatalogos = await initCatalogos()

            const data = await file.arrayBuffer()
            const workbook = XLSX.read(data)
            const worksheet = workbook.Sheets[workbook.SheetNames[0]]
            const jsonData = XLSX.utils.sheet_to_json(worksheet)

            // Obtenemos los productos actuales para saber qué es nuevo
            const currentInventory: any[] = await api.get('/api/inventario/reporte')
            const existingSkus = new Set(currentInventory.map(p => p.sku))

            const mapProductos = new Map<string, any>()
            let duplicadosCount = 0

            jsonData.forEach((row: any) => {
                const keys = Object.keys(row)

                // Normalizamos todos los nombres de columnas presentes
                const normalizedKeys = keys.map(k => ({ original: k, normalized: normalizeText(k) }))

                // Búsqueda de SKU: buscamos coincidencia exacta o que contenga 'sku'
                const keySku = normalizedKeys.find(nk => nk.normalized === 'sku' || nk.normalized.includes('sku'))?.original

                // Búsqueda de Cantidad (Prioridad): buscamos 'cantidad' o 'fisica' primero para evitar confundir con 'stock critico'
                let keyCant = normalizedKeys.find(nk => nk.normalized === 'cantidad' || nk.normalized.includes('cantidad') || nk.normalized.includes('fisica'))?.original

                // Si no hay 'cantidad', buscamos 'stock' que no sea 'critico'
                if (!keyCant) {
                    keyCant = normalizedKeys.find(nk => nk.normalized.includes('stock') && !nk.normalized.includes('critico'))?.original
                }

                const keyNombre = normalizedKeys.find(nk =>
                    nk.normalized.includes('producto') ||
                    nk.normalized.includes('nombre') ||
                    nk.normalized.includes('articulo') ||
                    nk.normalized.includes('descripcion') ||
                    nk.normalized === 'desc'
                )?.original
                const keyPrecio = normalizedKeys.find(nk => nk.normalized.includes('precio'))?.original
                const keyCritico = normalizedKeys.find(nk => nk.normalized.includes('critico'))?.original

                if (!keySku || !keyCant) return

                let sku = String(row[keySku]).trim()
                // Limpieza de SKU (quitar .0 si viene de Excel como número)
                if (sku.endsWith('.0')) sku = sku.substring(0, sku.length - 2)

                // Extraer Color y Talla si el SKU es de 15 dígitos
                let colorName = '---'
                let tallaName = '---'

                if (sku.length >= 15) {
                    const cId = parseInt(sku.substring(11, 13))
                    const taId = parseInt(sku.substring(13, 15))

                    colorName = freshCatalogos.colores.find((c: any) => c.id === cId)?.nombre || `ID: ${cId}`
                    tallaName = freshCatalogos.tallas.find((t: any) => t.id === taId)?.nombre || `ID: ${taId}`
                }
                else if (sku.length > 0) {
                    // Si ya existe en el sistema, podríamos intentar buscar sus datos reales
                    // pero para la previsualización de nuevos, esto es lo más útil.
                }

                const item = {
                    sku,
                    cantidad: Number(row[keyCant]),
                    nombre: keyNombre ? String(row[keyNombre]) : null,
                    precio_venta: keyPrecio ? Number(row[keyPrecio]) : null,
                    stock_critico: keyCritico ? Number(row[keyCritico]) : null,
                    color: colorName,
                    talla: tallaName
                }

                if (!sku || sku === "undefined" || sku === "null") return
                if (isNaN(item.cantidad)) return

                if (mapProductos.has(sku)) {
                    duplicadosCount++
                }

                // Sobreescribir si el SKU ya existe (el último gana)
                mapProductos.set(sku, item)
            })

            if (mapProductos.size === 0) {
                pteNotice('warning', 'Archivo sin datos válidos', 'Se requiere al menos SKU y Cantidad (o Stock). Revisa las columnas y los valores del archivo.')
                return
            }

            const nuevos: any[] = []
            const actualizados: any[] = []
            const sinCambios: any[] = []

            // Crear mapa para búsqueda rápida del estado actual
            const inventoryMap = new Map<string, any>(currentInventory.map(p => [p.sku, p]))

            mapProductos.forEach((item, sku) => {
                const current = inventoryMap.get(sku)
                if (!current) {
                    nuevos.push({ ...item, stock_anterior: 0, precio_anterior: 0 })
                } else {
                    const itemWithContext = { ...item }
                    // Comparar si hay cambios reales
                    const hasStockChange = Number(item.cantidad) !== Number(current.cantidad_fisica)
                    const hasPriceChange = item.precio_venta !== null && Number(item.precio_venta) !== Number(current.precio_venta)
                    const hasStockCriticoChange = item.stock_critico !== null && Number(item.stock_critico) !== Number(current.stock_critico)

                    if (hasStockChange || hasPriceChange || hasStockCriticoChange) {
                        actualizados.push({ ...itemWithContext, stock_anterior: current.cantidad_fisica, precio_anterior: current.precio_venta })
                    } else {
                        sinCambios.push({ ...itemWithContext, stock_anterior: current.cantidad_fisica, precio_anterior: current.precio_venta })
                    }
                }
            })

            setPendingImport({
                total: mapProductos.size,
                nuevos,
                actualizados,
                sinCambios: sinCambios.length,
                duplicadosEliminados: duplicadosCount
            })
            setIsConfirmModalOpen(true)
        } catch (error: any) {
            console.error(error)
            pteNotice('error', 'Error al procesar archivo', error.message)
        } finally {
            setLoadingImport(false)
            e.target.value = ""
        }
    }

    const confirmImport = async () => {
        if (!pendingImport) return

        setLoadingImport(true)
        try {
            const allItems = [...pendingImport.nuevos, ...pendingImport.actualizados]
            const CHUNK_SIZE = 500;
            const totalChunks = Math.ceil(allItems.length / CHUNK_SIZE);
            
            let total_procesados = 0;
            let actualizados = 0;
            let creados = 0;
            let sin_cambios = 0;
            let skus_no_encontrados = 0;
            let detalles_errores: string[] = [];

            for (let i = 0; i < totalChunks; i++) {
                const chunk = allItems.slice(i * CHUNK_SIZE, (i + 1) * CHUNK_SIZE);
                const response: any = await api.post('/api/movimientos/importar', { productos: chunk });
                const res = response.resumen;
                
                total_procesados += res.total_procesados || 0;
                actualizados += res.actualizados || 0;
                creados += res.creados || 0;
                sin_cambios += res.sin_cambios || 0;
                skus_no_encontrados += res.skus_no_encontrados || 0;
                if (res.detalles_errores) {
                    detalles_errores = [...detalles_errores, ...res.detalles_errores];
                }
            }

            let msg = `Procesados: ${total_procesados}\nNuevos: ${creados}\nActualizados: ${actualizados}\nSin cambios: ${sin_cambios}\nErrores: ${skus_no_encontrados}`

            if (detalles_errores && detalles_errores.length > 0) {
                msg += `\n\nAlgunos errores detectados:\n- ` + detalles_errores.slice(0, 10).join('\n- ')
                if (skus_no_encontrados > 10) {
                    msg += `\n... y ${skus_no_encontrados - 10} errores más.`
                }
            }

            const hasIssues = skus_no_encontrados > 0 || detalles_errores.length > 0
            pteNotice(hasIssues ? 'warning' : 'success', hasIssues ? 'Importación completada con observaciones' : 'Importación completada', msg)

            setIsConfirmModalOpen(false)
            setPendingImport(null)
            fetchSuccess()
        } catch (error: any) {
            console.error(error)
            pteNotice('error', 'Error al importar', error.response?.data?.message || error.message)
        } finally {
            setLoadingImport(false)
        }
    }

    const handleExportExcel = async () => {
        setIsExporting(true)
        try {
            // Misma búsqueda, filtros y orden que la tabla en pantalla.
            const fullItems: any[] = await api.get(buildInventoryReportUrl(filters))

            const ws = XLSX.utils.json_to_sheet(fullItems.map(toExportRow))
            const wb = XLSX.utils.book_new()
            XLSX.utils.book_append_sheet(wb, ws, "Inventario")

            ws['!cols'] = [
                { wch: 18 }, { wch: 45 }, { wch: 38 }, { wch: 15 }, { wch: 10 }, { wch: 30 },
                { wch: 10 }, { wch: 14 }, { wch: 15 }, { wch: 10 }, { wch: 10 }, { wch: 10 },
                { wch: 10 }, { wch: 13 }, { wch: 10 }
            ]

            XLSX.writeFile(wb, exportFileName())
        } catch (error: any) {
            pteNotice('error', 'Error al exportar', error.message)
        } finally {
            setIsExporting(false)
        }
    }

    return {
        handleImportExcel,
        handleExportExcel,
        confirmImport,
        pendingImport,
        isConfirmModalOpen,
        setIsConfirmModalOpen,
        loadingImport,
        isExporting
    }
}
