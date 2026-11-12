import { useState } from "react"
import { api } from "@/lib/api"
import { bulkSaveMessage, summarizeBulkResults } from "../lib/stock-edit"
import { pteNotice } from "../lib/pte-notice"

interface ProductoInventario {
    sku: string
    producto: string
    precio_venta: number
    cantidad_fisica: number
    stock_critico: number
}

const generateClientUUID = () => {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID()
    }
    return Date.now().toString(36) + Math.random().toString(36).substr(2)
}

export function useBulkEdit(products: ProductoInventario[], onSuccess: () => void) {
    const [editedStocks, setEditedStocks] = useState<Record<string, number>>({})
    // Stock que se veía al empezar a editar cada SKU: el backend lo compara para no pisar reservas hechas entremedio.
    const [baseStocks, setBaseStocks] = useState<Record<string, number>>({})
    const [isBulkSaving, setIsBulkSaving] = useState(false)

    const rememberBaseStock = (sku: string) => {
        setBaseStocks(prev => {
            if (prev[sku] !== undefined) return prev
            const original = products.find(p => p.sku === sku)
            return original ? { ...prev, [sku]: original.cantidad_fisica } : prev
        })
    }

    const handleQuickStockChange = (sku: string, value: string) => {
        rememberBaseStock(sku)

        if (value === "") {
            setEditedStocks(prev => ({ ...prev, [sku]: 0 }))
            return
        }

        const numValue = parseInt(value)
        if (isNaN(numValue)) return

        setEditedStocks(prev => ({ ...prev, [sku]: numValue }))
    }

    const cancelBulkChanges = () => {
        if (window.confirm("¿Descartar los cambios no guardados?")) {
            setEditedStocks({})
            setBaseStocks({})
        }
    }

    const saveBulkChanges = async () => {
        setIsBulkSaving(true)
        try {
            const loteIdUnico = generateClientUUID()
            const entries = Object.entries(editedStocks)

            // Solo la cantidad: nombre, precio y stock crítico no se tocan desde aquí.
            const results = await Promise.allSettled(entries.map(([sku, newQty]) =>
                api.put(`/api/productos/${sku}`, {
                    cantidad: newQty,
                    cantidad_anterior: baseStocks[sku] ?? products.find(p => p.sku === sku)?.cantidad_fisica,
                    id_lote: loteIdUnico
                })
            ))

            const outcome = summarizeBulkResults(entries.map(([sku]) => sku), results)
            const omitSaved = (prev: Record<string, number>) => {
                const next = { ...prev }
                outcome.saved.forEach(sku => { delete next[sku] })
                return next
            }

            // Lo guardado sale de la lista; lo que chocó queda marcado con el stock actual como nueva base.
            setEditedStocks(omitSaved)
            setBaseStocks(prev => ({ ...omitSaved(prev), ...outcome.conflicts }))
            await onSuccess()
            const hasIssues = Object.keys(outcome.conflicts).length > 0 || Object.keys(outcome.failed).length > 0
            const level = !hasIssues ? 'success' : outcome.saved.length > 0 ? 'warning' : 'error'
            pteNotice(level, !hasIssues ? 'Inventario actualizado' : outcome.saved.length > 0 ? 'Guardado parcial' : 'No se guardaron los cambios', bulkSaveMessage(outcome))
        } catch (error: any) {
            pteNotice('error', 'Error al guardar', error.message)
        } finally {
            setIsBulkSaving(false)
        }
    }

    return {
        editedStocks,
        handleQuickStockChange,
        saveBulkChanges,
        cancelBulkChanges,
        isBulkSaving
    }
}
