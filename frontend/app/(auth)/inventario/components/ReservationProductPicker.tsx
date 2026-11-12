"use client"

import { useCallback, useEffect, useId, useRef, useState } from "react"
import { Loader2, Search } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { api } from "@/lib/api"
import { remainingQuantity, selectionError, type ReservationItem, type ReservationProduct } from "../lib/reservation-selection"

interface ProductPage {
    pagination: { data: ReservationProduct[]; current_page: number; last_page: number; total: number }
}
interface Props {
    cart: ReservationItem[]
    disabled: boolean
    onAdd: (items: ReservationItem[]) => string | null
    onDirtyChange: (dirty: boolean) => void
    onPendingChange: (count: number) => void
}
export function ReservationProductPicker({ cart, disabled, onAdd, onDirtyChange, onPendingChange }: Props) {
    const uid = useId()
    const [query, setQuery] = useState("")
    const [products, setProducts] = useState<ReservationProduct[]>([])
    const [selection, setSelection] = useState<Record<string, ReservationItem>>({})
    const [page, setPage] = useState({ current: 0, last: 1, total: 0 })
    const [loading, setLoading] = useState(false)
    const [loadingMore, setLoadingMore] = useState(false)
    const [error, setError] = useState("")
    const [failedPage, setFailedPage] = useState(1)
    const [addError, setAddError] = useState("")
    const [notice, setNotice] = useState("")
    const request = useRef<AbortController | null>(null)
    const sequence = useRef(0)
    const adding = useRef(false)
    const selected = Object.values(selection)
    const invalid = selected.some(item => selectionError(item, cart) !== null)
    const hasSearch = query.trim().length > 0

    const fetchProducts = useCallback(async (search: string, nextPage = 1) => {
        if (!search.trim()) return
        request.current?.abort()
        const controller = new AbortController()
        request.current = controller
        const id = ++sequence.current
        if (nextPage === 1) { setLoading(true); setLoadingMore(false) }
        else setLoadingMore(true)
        setError("")
        setFailedPage(nextPage)
        try {
            const params = new URLSearchParams({ search: search.trim(), page: String(nextPage), per_page: "20" })
            const result = await api.get<ProductPage>(`/api/inventario?${params}`, { signal: controller.signal })
            if (controller.signal.aborted || id !== sequence.current) return
            const incoming = result.pagination.data
            setProducts(previous => [...new Map([...(nextPage === 1 ? [] : previous), ...incoming].map(row => [row.sku, row])).values()])
            setSelection(previous => {
                const updated = { ...previous }
                for (const row of incoming) if (updated[row.sku]) updated[row.sku] = { ...row, requestedQuantity: updated[row.sku].requestedQuantity }
                return updated
            })
            setPage({ current: result.pagination.current_page, last: result.pagination.last_page, total: result.pagination.total })
        } catch {
            if (!controller.signal.aborted && id === sequence.current) setError(nextPage === 1 ? "No pudimos cargar los productos. Tus cantidades se conservaron." : "No pudimos cargar más resultados. Tus cantidades se conservaron.")
        } finally {
            if (id === sequence.current && !controller.signal.aborted) { setLoading(false); setLoadingMore(false) }
        }
    }, [])
    useEffect(() => {
        if (!query.trim()) return
        const timer = setTimeout(() => void fetchProducts(query), 400)
        return () => { clearTimeout(timer); sequence.current++; request.current?.abort() }
    }, [query, fetchProducts])
    useEffect(() => { onDirtyChange(selected.length > 0) }, [selected.length, onDirtyChange])
    useEffect(() => { onPendingChange(selected.length) }, [selected.length, onPendingChange])

    const resetResults = (search: string) => {
        sequence.current++; request.current?.abort()
        setProducts([]); setPage({ current: 0, last: 1, total: 0 }); setLoading(search.trim().length > 0); setLoadingMore(false); setError(""); setNotice("")
    }
    const changeQuery = (value: string) => { resetResults(value); setQuery(value) }
    const changeQuantity = (product: ReservationProduct, value: string) => {
        if (disabled) return
        setAddError(""); setNotice("")
        setSelection(previous => {
            const next = { ...previous }, quantity = Number(value)
            if (value === "" || quantity === 0) delete next[product.sku]
            else next[product.sku] = { ...product, requestedQuantity: quantity }
            return next
        })
    }
    const addSelected = () => {
        if (adding.current || disabled || invalid || selected.length === 0) return
        adding.current = true
        try {
            const message = onAdd(selected)
            setAddError(message ?? "")
            if (message) return
            setNotice(`${selected.reduce((sum, item) => sum + item.requestedQuantity, 0)} unidades agregadas al pedido. Puedes buscar otro SKU o revisar la lista de abajo.`)
            setSelection({})
        } finally { adding.current = false }
    }

    return <div className="space-y-4">
        <div className="space-y-2">
            <label htmlFor={`${uid}-search`} className="text-sm font-medium">Buscar producto</label>
            <div className="relative"><Search aria-hidden="true" className="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-slate-500" /><Input id={`${uid}-search`} value={query} disabled={disabled} onChange={event => changeQuery(event.target.value)} placeholder="Nombre, SKU, color o género. Ej.: camisa Oxford" className="h-11 bg-white pl-10 text-base" /></div>
            <p className="text-sm text-slate-600">{hasSearch ? "Escribe la cantidad junto a cada SKU. Vacío o 0 no agrega ese SKU." : "Escribe un nombre, SKU, color o género para buscar productos."}</p>
        </div>
        {selected.length > 0 && <div className="sticky top-0 z-10 space-y-2 border-b border-slate-200 bg-gray-50 py-3">
            <p role="status" className="text-sm font-semibold text-slate-900">Por agregar: {selected.length} variantes · {selected.reduce((sum, item) => sum + (Number.isSafeInteger(item.requestedQuantity) && item.requestedQuantity > 0 ? item.requestedQuantity : 0), 0)} unidades</p>
            <div className="flex flex-wrap gap-2"><Button type="button" disabled={invalid || disabled} onClick={addSelected} className="min-h-11">Agregar al pedido</Button><Button type="button" variant="outline" disabled={disabled} onClick={() => { setSelection({}); setAddError("") }} className="min-h-11">Limpiar cantidades</Button></div>
            {invalid && <p role="alert" className="text-sm text-red-700">Corrige o quita las cantidades indicadas antes de agregar.</p>}
            {addError && <p role="alert" className="text-sm text-red-700">{addError}</p>}
        </div>}
        {hasSearch && <div role="region" aria-label="Resultados de productos" tabIndex={0} className="max-h-80 overflow-y-auto rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:max-h-96" aria-busy={loading || loadingMore}>
            {loading ? <p role="status" className="flex items-center gap-2 py-6 text-sm text-slate-600"><Loader2 className="h-4 w-4 animate-spin" />Buscando productos…</p> : products.length > 0 && <div className="rounded-lg border border-slate-200 bg-white">
                <div className="sticky top-0 z-[1] grid grid-cols-[minmax(0,1fr)_80px_80px] gap-2 border-b bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 sm:grid-cols-[minmax(0,1fr)_120px_112px]"><span>Producto / SKU</span><span className="text-right">Disponible</span><span className="text-right">Cantidad</span></div>
                {products.map(product => {
                    const item = selection[product.sku], available = remainingQuantity(product, cart)
                    const inCart = cart.find(row => row.sku === product.sku)?.requestedQuantity ?? 0
                    const issue = item ? selectionError(item, cart) : null, fieldId = `${uid}-${product.sku}`
                    return <div key={product.sku} className="grid grid-cols-[minmax(0,1fr)_80px_80px] items-center gap-2 border-b border-slate-100 px-3 py-2 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_120px_112px]">
                        <label htmlFor={fieldId} className="min-w-0 break-words text-sm text-slate-900">
                            <span className="block font-medium">{product.producto}</span>
                            <span className="block break-all font-mono text-xs text-slate-500">SKU: {product.sku}</span>
                            <span className="block text-xs text-slate-600">{product.color || "Sin color"} · Talla {product.talla || "Sin talla"}</span>
                        </label>
                        <div className="text-right text-sm tabular-nums text-slate-600"><span className="block">{available === 0 ? "Sin stock" : available}</span>{inCart > 0 && <span className="block text-xs">En pedido: {inCart}</span>}</div>
                        <Input id={fieldId} aria-label={`Cantidad de ${product.producto}, ${product.color}, talla ${product.talla}`} type="number" inputMode="numeric" min={0} max={available} step={1} placeholder="0" value={item?.requestedQuantity ?? ""} disabled={disabled || (available === 0 && !item)} aria-invalid={!!issue} aria-describedby={issue ? `${fieldId}-error` : undefined} onChange={event => changeQuantity(product, event.target.value)} className="h-11 bg-white text-right text-base tabular-nums" />
                        {issue && <p id={`${fieldId}-error`} role="alert" className="col-span-full text-sm text-red-700">{issue}</p>}
                    </div>
                })}
            </div>}
            {!loading && products.length === 0 && !error && <p className="py-6 text-sm text-slate-600">No encontramos productos. Prueba con menos palabras.</p>}
            {error && <div role="alert" className="space-y-2 py-3 text-sm text-red-700"><p>{error}</p><Button type="button" variant="outline" disabled={disabled} onClick={() => void fetchProducts(query, failedPage)}>Reintentar</Button></div>}
            {!loading && !error && page.current < page.last && <div className="pt-3"><Button type="button" variant="outline" disabled={loadingMore || disabled} onClick={() => void fetchProducts(query, page.current + 1)}>{loadingMore ? "Cargando…" : `Cargar más resultados (${products.length} de ${page.total} SKU)`}</Button></div>}
        </div>}
        {selected.length > 0 && <div className="space-y-3 border-t border-slate-200 pt-4">
            <p className="text-sm font-semibold text-slate-900">Detalle de cantidades por agregar</p>
            <ul className="max-h-40 space-y-2 overflow-y-auto rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500" tabIndex={0} aria-label="Cantidades pendientes de agregar">{selected.map(item => <li key={item.sku} className="flex flex-wrap items-start justify-between gap-2 text-sm">
                <div className="min-w-0 flex-1"><p className="break-words text-slate-800">{item.producto} · {item.color} · Talla {item.talla} · <strong>{item.requestedQuantity} u.</strong></p>{selectionError(item, cart) && <p role="alert" className="text-red-700">{selectionError(item, cart)}</p>}</div>
                <Button type="button" variant="ghost" disabled={disabled} className="min-h-11" aria-label={`Quitar cantidad pendiente de ${item.producto}`} onClick={() => changeQuantity(item, "")}>Quitar</Button>
            </li>)}</ul>
        </div>}
        {notice && <p role="status" className="text-sm font-medium text-green-800">{notice}</p>}
        <p className="text-xs text-slate-600">Agregar al pedido todavía no reserva stock. La reserva se realiza al finalizar la solicitud.</p>
    </div>
}
