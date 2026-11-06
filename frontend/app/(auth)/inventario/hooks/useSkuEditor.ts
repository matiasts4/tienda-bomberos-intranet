"use client"

import { useEffect, useRef, useState } from "react"
import { api, downloadPdf } from "@/lib/api"
import { buildProductUpdatePayload, validateProductEdit, type ProductEditValues } from "../lib/stock-edit"
import type { SkuMovement } from "../lib/sku-history"

export interface EditableSku {
  sku: string; producto: string; precio_venta: number | null; cantidad_fisica: number; stock_critico: number; reserved_stock?: number
}
type Notice = { title: string; description: string; variant?: "destructive" }
type EditSession = { id: number; product: EditableSku; original: ProductEditValues }

export function useSkuEditor(canManage: boolean, refresh: () => Promise<unknown>, notify: (notice: Notice) => void) {
  const sequence = useRef(0)
  const current = useRef<EditSession | null>(null)
  const historyRequest = useRef<AbortController | null>(null)
  const historySequence = useRef(0)
  const mutation = useRef(false)
  const pdfRequest = useRef<AbortController | null>(null)
  const [session, setSession] = useState<EditSession | null>(null)
  const [formData, setFormData] = useState<ProductEditValues>({ nombre: "", precio_venta: 0, cantidad: 0, stock_critico: 0 })
  const [history, setHistory] = useState<SkuMovement[]>([])
  const [loadingHistory, setLoadingHistory] = useState(false)
  const [historyError, setHistoryError] = useState("")
  const [error, setError] = useState("")
  const [updating, setUpdating] = useState(false)
  const [downloading, setDownloading] = useState(false)
  const fieldErrors = validateProductEdit(formData)
  const dirty = !!session && (Object.keys(buildProductUpdatePayload(session.original, formData)).length > 0 || Object.keys(fieldErrors).length > 0)

  useEffect(() => () => { current.current = null; historyRequest.current?.abort(); pdfRequest.current?.abort() }, [])

  const loadHistory = async (target = current.current) => {
    if (!target) return
    historyRequest.current?.abort()
    const request = new AbortController()
    historyRequest.current = request
    const loadId = ++historySequence.current
    setLoadingHistory(true); setHistoryError("")
    try {
      const rows = await api.get<SkuMovement[]>(`/api/productos/${encodeURIComponent(target.product.sku)}/movimientos`, { signal: request.signal })
      if (current.current?.id === target.id && loadId === historySequence.current) setHistory(rows)
    } catch {
      if (!request.signal.aborted && current.current?.id === target.id && loadId === historySequence.current) setHistoryError("No se pudo cargar el historial. Reintenta la consulta.")
    } finally {
      if (current.current?.id === target.id && loadId === historySequence.current) setLoadingHistory(false)
    }
  }
  const open = (product: EditableSku) => {
    if (!canManage || mutation.current) return
    pdfRequest.current?.abort(); setDownloading(false)
    const original = { nombre: product.producto, precio_venta: product.precio_venta ?? 0, cantidad: product.cantidad_fisica, stock_critico: product.stock_critico }
    const target = { id: ++sequence.current, product, original }
    current.current = target; setSession(target); setFormData(original); setHistory([]); setError("")
    void loadHistory(target)
  }
  const close = () => {
    if (mutation.current) return
    current.current = null; historyRequest.current?.abort(); pdfRequest.current?.abort(); setSession(null); setDownloading(false)
  }
  const write = async (archive: boolean) => {
    const target = current.current
    if (!canManage || !target || mutation.current) return
    if (!archive && Object.keys(validateProductEdit(formData)).length) { setError("Revisa los campos indicados antes de guardar."); return }
    const payload = buildProductUpdatePayload(target.original, formData)
    if (!archive && Object.keys(payload).length === 0) return
    mutation.current = true; setUpdating(true); setError("")
    let written = false
    try {
      const url = `/api/productos/${encodeURIComponent(target.product.sku)}`
      if (archive) await api.del(url); else await api.put(url, payload)
      written = true
      // La escritura y la recarga tienen resultados distintos: nunca sugerir repetir una escritura exitosa.
      if (current.current?.id === target.id) { current.current = null; historyRequest.current?.abort(); pdfRequest.current?.abort(); setSession(null) }
      try {
        const refreshed = await refresh()
        if (refreshed === false) throw new Error("No se pudo recargar la tabla")
        notify({ title: archive ? "Variante archivada" : "Producto actualizado", description: archive ? "Se conservaron su stock e historial." : "Los cambios se guardaron correctamente." })
      } catch {
        notify({ title: "Cambios guardados", description: "No se pudo actualizar la tabla. Recarga la página para ver los datos actuales; no repitas el guardado." })
      }
    } catch (failure) {
      if (!written && current.current?.id === target.id) setError(failure instanceof Error ? failure.message : "No se pudo completar la operación. Inténtalo nuevamente.")
    } finally {
      mutation.current = false; setUpdating(false)
    }
  }
  const download = async () => {
    const target = current.current
    if (!target || pdfRequest.current) return
    const request = new AbortController(); pdfRequest.current = request; setDownloading(true); setError("")
    try {
      await downloadPdf(`/api/productos/${encodeURIComponent(target.product.sku)}/movimientos/pdf`, { signal: request.signal, throwOnError: true, filename: `historial_${target.product.sku}.pdf` })
    } catch (failure) {
      if (!request.signal.aborted && current.current?.id === target.id) setError(failure instanceof Error ? failure.message : "No se pudo descargar el PDF.")
    } finally {
      if (pdfRequest.current === request) { pdfRequest.current = null; setDownloading(false) }
    }
  }
  return { session, formData, setFormData, history, loadingHistory, historyError, error, fieldErrors, dirty, updating, downloading, open, close, retryHistory: () => loadHistory(), save: () => write(false), archive: () => write(true), download }
}

export type SkuEditor = ReturnType<typeof useSkuEditor>
