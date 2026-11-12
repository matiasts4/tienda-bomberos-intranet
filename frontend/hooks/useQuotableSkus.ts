"use client"

import { useEffect, useRef, useState } from 'react'
import { api } from '@/lib/api'

export type QuotableSku = {
  id: number; sku: string; nombre: string; precio_venta: number | null; permite_cotizar?: boolean; permite_personalizar?: boolean
}

/** Refresca al abrir; ninguna búsqueda antigua reemplaza los resultados del ítem actual. */
export function useQuotableSkus(open: boolean, search: string, itemKey?: number | null) {
  const [products, setProducts] = useState<QuotableSku[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [retry, setRetry] = useState(0)
  const requestId = useRef(0)
  const query = search.trim()
  useEffect(() => {
    const id = ++requestId.current
    const controller = new AbortController()
    setProducts([]); setError(''); setLoading(open)
    if (!open) return
    const timer = setTimeout(async () => {
      try {
        const params = new URLSearchParams()
        if (query) params.set('search', query)
        const result = await api.get<QuotableSku[]>(`/api/comercial-skus${params.size ? `?${params}` : ''}`, { signal: controller.signal })
        if (!controller.signal.aborted && id === requestId.current) {
          setProducts(result.filter(product => product.permite_cotizar !== false))
        }
      } catch {
        if (!controller.signal.aborted && id === requestId.current) setError('No se pudo cargar el catálogo. Reintenta la búsqueda.')
      } finally {
        if (!controller.signal.aborted && id === requestId.current) setLoading(false)
      }
    }, 200)
    return () => { clearTimeout(timer); controller.abort() }
  }, [open, query, retry, itemKey])
  return { products, loading, error, reload: () => setRetry(value => value + 1) }
}
