"use client"

import React, { useState, useEffect, useMemo, useCallback, useRef, useId } from "react"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from "@/components/ui/dialog"
import { AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle, AlertDialogDescription, AlertDialogFooter, AlertDialogCancel, AlertDialogAction } from "@/components/ui/alert-dialog"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Search, Loader2, Package, Check, Trash2 } from "lucide-react"
import { api, downloadPdf } from "@/lib/api"
import { useInventoryPermissions } from "@/hooks/useInventoryPermissions"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { ScrollArea } from "@/components/ui/scroll-area"
import { ReservationProductPicker } from "./ReservationProductPicker"
import { cartQuantityError, mergeReservationSelection, type ReservationItem } from "../lib/reservation-selection"
import { reservationFailure, type ReservationFeedback } from "../lib/reservation-feedback"

interface ManualOrderModalProps {
    isOpen: boolean
    onClose: () => void
    onSuccess: () => void
}

interface Quote {
    id: number
    numero: string
    nombre?: string | null
    nombre_cliente?: string
    cliente?: {
        nombre_empresa?: string
        nombre_contacto?: string
    }
    cuerpo?: {
        id: number
        nombre: string
    }
    compania?: {
        id: number
        nombre: string
        cuerpo?: {
            id: number
            nombre: string
        }
    }
    contacto?: {
        id: number
        nombre: string
    }
}

function getQuoteClientName(q?: Quote | null): string {
    if (!q) return "N/A"
    if (q.nombre_cliente) return q.nombre_cliente
    const cuerpoResuelto = q.cuerpo ?? q.compania?.cuerpo ?? null
    if (cuerpoResuelto?.nombre) {
        return `Cuerpo de Bomberos de ${cuerpoResuelto.nombre}${q.compania ? ` – ${q.compania.nombre}` : ""}`
    }
    return q.cliente?.nombre_empresa || q.cliente?.nombre_contacto || q.contacto?.nombre || "Cliente no especificado"
}

export function ManualOrderModal({ isOpen, onClose, onSuccess }: ManualOrderModalProps) {
    const { canCreateRequest } = useInventoryPermissions()
    const [container, setContainer] = useState<HTMLDivElement | null>(null)
    const [receiptIds, setReceiptIds] = useState<number[]>([])
    const [downloadingReceipt, setDownloadingReceipt] = useState(false)
    const [receiptError, setReceiptError] = useState<string | null>(null)
    const [discardOpen, setDiscardOpen] = useState(false)
    const [submitError, setSubmitError] = useState<ReservationFeedback | null>(null)
    const [submissionUncertain, setSubmissionUncertain] = useState(false)
    const feedbackFocusRef = useRef<HTMLElement | null>(null)
    const submitInFlightRef = useRef(false)
    const quoteRequestRef = useRef<{ controller: AbortController; id: number } | null>(null)
    const quoteRequestIdRef = useRef(0)
    const [pickerDirty, setPickerDirty] = useState(false)
    const [pendingVariants, setPendingVariants] = useState(0)
    const cartHeadingRef = useRef<HTMLHeadingElement | null>(null)
    const cartHeadingId = useId()

    const viewCart = () => {
        cartHeadingRef.current?.focus({ preventScroll: true })
        cartHeadingRef.current?.scrollIntoView({ block: "start", inline: "nearest", behavior: "instant" })
    }

    const [quotes, setQuotes] = useState<Quote[]>([])
    const [loadingQuotes, setLoadingQuotes] = useState(false)
    const [quoteSearchError, setQuoteSearchError] = useState<string | null>(null)
    const [selectedQuote, setSelectedQuote] = useState<Quote | null>(null)
    const [quoteSearch, setQuoteSearch] = useState("")
    const [quotePopoverOpen, setQuotePopoverOpen] = useState(false)
    const [quoteMode, setQuoteMode] = useState<"system" | "external">("system")
    const [externalQuoteNumber, setExternalQuoteNumber] = useState("")
    const [externalQuoteName, setExternalQuoteName] = useState("")
    const [externalClientName, setExternalClientName] = useState("")

    const [observation, setObservation] = useState("")
    const [submitting, setSubmitting] = useState(false)
    const [cartItems, setCartItems] = useState<ReservationItem[]>([])

    const fetchQuotes = useCallback(async (search = "") => {
        quoteRequestRef.current?.controller.abort()
        const controller = new AbortController()
        const requestId = ++quoteRequestIdRef.current
        quoteRequestRef.current = { controller, id: requestId }

        setLoadingQuotes(true)
        setQuoteSearchError(null)
        try {
            const url = search
                ? `/api/order-requests/quotes?search=${encodeURIComponent(search)}`
                : `/api/order-requests/quotes`
            const data: any = await api.get(url, { signal: controller.signal })
            if (requestId !== quoteRequestIdRef.current) return
            setQuotes(data || [])
        } catch (error) {
            if (controller.signal.aborted || (error instanceof DOMException && error.name === 'AbortError')) return
            if (requestId !== quoteRequestIdRef.current) return
            console.error("Error fetching quotes:", error)
            setQuoteSearchError("No pudimos buscar cotizaciones. Revisa tu conexión e intenta nuevamente.")
        } finally {
            if (requestId === quoteRequestIdRef.current) {
                setLoadingQuotes(false)
            }
        }
    }, [])

    useEffect(() => {
        quoteRequestIdRef.current += 1
        if (!isOpen || quoteMode !== "system") {
            quoteRequestRef.current?.controller.abort()
            return
        }

        quoteRequestRef.current?.controller.abort()
        const timer = setTimeout(() => fetchQuotes(quoteSearch), quoteSearch ? 400 : 0)

        return () => {
            clearTimeout(timer)
            quoteRequestRef.current?.controller.abort()
        }
    }, [quoteSearch, isOpen, quoteMode, fetchQuotes])

    const filteredQuotes = quotes;

    const addToCart = (items: ReservationItem[]): string | null => {
        if (submitInFlightRef.current) return 'Espera a que termine el envío de la solicitud.'
        const result = mergeReservationSelection(cartItems, items)
        if (result.error || !result.items) return result.error
        setCartItems(result.items)
        return null
    }

    const removeFromCart = (sku: string) => {
        if (submitInFlightRef.current) return
        setCartItems(items => items.filter(item => item.sku !== sku))
    }

    const invalidCart = cartItems.some(item => cartQuantityError(item) !== null)
    const isQuoteValid = quoteMode === "system"
        ? !!selectedQuote
        : externalQuoteNumber.trim().length > 0 && externalQuoteName.trim().length > 0

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        if (!canCreateRequest || submitInFlightRef.current || pendingVariants > 0 || invalidCart || cartItems.length === 0 || !isQuoteValid) return

        submitInFlightRef.current = true
        setSubmitting(true)
        setSubmitError(null)
        let created = false
        try {
            const result = await api.post<{ data: { id: number }[] }>('/api/order-requests', {
                items: cartItems.map(item => ({
                    sku: item.sku,
                    quantity: item.requestedQuantity
                })),
                is_external: quoteMode === "external",
                ...(quoteMode === "system"
                    ? { quote_identifier: selectedQuote!.id }
                    : { external_quote_number: externalQuoteNumber.trim(), external_quote_name: externalQuoteName.trim(), external_client_name: externalClientName.trim() || undefined }),
                observation: observation
            })
            setReceiptIds(result.data.map(item => item.id))
            setQuotePopoverOpen(false)
            created = true
        } catch (error: unknown) {
            const feedback = reservationFailure(error, cartItems)
            setSubmitError(feedback)
            if (feedback.uncertain) setSubmissionUncertain(true)
        } finally {
            submitInFlightRef.current = false
            setSubmitting(false)
        }
        // A refresh failure must not be presented as a failed reservation.
        if (created) onSuccess()
    }

    const isDirty = useMemo(() => {
        return cartItems.length > 0 ||
            pickerDirty ||
            selectedQuote !== null ||
            externalQuoteNumber.trim() !== "" ||
            externalQuoteName.trim() !== "" ||
            externalClientName.trim() !== "" ||
            observation.trim() !== "" ||
            quoteSearch.trim() !== ""
    }, [cartItems, pickerDirty, selectedQuote, externalQuoteNumber, externalQuoteName, externalClientName, observation, quoteSearch])

    const attemptClose = (open: boolean) => {
        if (submitInFlightRef.current || discardOpen || submitError) return
        // If 'open' is false, it means someone is trying to close the modal
        if (!open) {
            if (isDirty) {
                setDiscardOpen(true)
                return
            }
            handleClose()
        }
    }

    const handleClose = () => {
        setDiscardOpen(false)
        setSubmitError(null)
        setSubmissionUncertain(false)
        setReceiptIds([])
        setReceiptError(null)
        quoteRequestIdRef.current += 1
        quoteRequestRef.current?.controller.abort()
        setQuotePopoverOpen(false)
        setQuoteSearchError(null)
        setSelectedQuote(null)
        setQuoteMode("system")
        setExternalQuoteNumber("")
        setExternalQuoteName("")
        setExternalClientName("")
        setQuoteSearch("")
        setObservation("")
        setCartItems([])
        setPickerDirty(false)
        setPendingVariants(0)
        onClose()
    }

    const downloadReceipt = async () => {
        setDownloadingReceipt(true)
        setReceiptError(null)
        try {
            const params = new URLSearchParams()
            receiptIds.forEach(id => params.append('ids[]', String(id)))
            await downloadPdf(`/api/order-requests/receipt?${params}`, {
                filename: `solicitud-${receiptIds[0]}.pdf`,
                throwOnError: true,
            })
        } catch {
            setReceiptError("La solicitud está guardada, pero no pudimos descargar el PDF. Puedes reintentar o descargarlo desde Pendientes.")
        } finally {
            setDownloadingReceipt(false)
        }
    }

    if (receiptIds.length > 0) return (
        <Dialog open={isOpen} onOpenChange={open => { if (!open && !downloadingReceipt) handleClose() }}>
            <DialogContent showCloseButton={!downloadingReceipt}>
                <DialogHeader>
                    <DialogTitle>Solicitud registrada</DialogTitle>
                    <DialogDescription>Tu solicitud quedó guardada en Pendientes y el stock está reservado, a la espera de aprobación del superadmin. Aunque no descargues el PDF ahora, podrás consultarla y obtener su comprobante desde Pendientes.</DialogDescription>
                </DialogHeader>
                <p className="text-sm text-gray-700">¿Quieres descargar el comprobante de este pedido?</p>
                {receiptError && <p role="alert" className="text-sm text-red-700">{receiptError}</p>}
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={handleClose} disabled={downloadingReceipt}>Cerrar</Button>
                    <Button type="button" onClick={downloadReceipt} disabled={downloadingReceipt}>
                        {downloadingReceipt && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        {receiptError ? "Reintentar descarga" : "Descargar PDF"}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )

    return (
        <Dialog open={isOpen} onOpenChange={attemptClose}>
            <DialogContent ref={setContainer} showCloseButton={!submitting} className="w-[calc(100vw-2rem)] sm:max-w-[960px] max-h-[92vh] flex flex-col p-4 sm:p-6">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Package className="h-5 w-5 text-blue-600" />
                        Reserva Manual de Stock
                    </DialogTitle>
                    <DialogDescription className="sr-only">Completa los datos de la cotización, agrega productos y revisa sus cantidades para enviar tu solicitud.</DialogDescription>
                </DialogHeader>

                <div className="min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto pr-2 py-4 space-y-6">
                    {/* Datos de la Orden */}
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h4 className="text-sm font-bold text-gray-700 uppercase">1. Datos de la Orden</h4>
                            <div className="flex flex-wrap gap-1 rounded-lg border border-gray-200 bg-gray-100 p-1" role="group" aria-label="Tipo de cotización">
                                <Button type="button" size="sm" variant={quoteMode === "system" ? "outline" : "ghost"} aria-pressed={quoteMode === "system"} onClick={() => setQuoteMode("system")}>
                                    Cotización en Sistema
                                </Button>
                                <Button type="button" size="sm" variant={quoteMode === "external" ? "outline" : "ghost"} aria-pressed={quoteMode === "external"} className={quoteMode === "external" ? "border-amber-300 text-amber-800 bg-white" : ""} onClick={() => { setQuotePopoverOpen(false); setQuoteMode("external") }}>
                                    Cotización Externa / Manual
                                </Button>
                            </div>
                        </div>
                        {quoteMode === "system" ? <div className="space-y-2">
                            <Label>Seleccionar Cotización</Label>
                            <Popover open={quotePopoverOpen} onOpenChange={setQuotePopoverOpen}>
                                <PopoverTrigger asChild>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        aria-expanded={quotePopoverOpen}
                                        aria-label="Buscar cotización para la reserva"
                                        className="w-full justify-between font-normal"
                                    >
                                        {selectedQuote ? (
                                            <span className="truncate">
                                                <span className="font-bold mr-2">#{selectedQuote.id}</span>
                                                {selectedQuote.numero && <span className="mr-2">({selectedQuote.numero})</span>}
                                                {selectedQuote.nombre && <span className="mr-2 font-medium">{selectedQuote.nombre} ·</span>}
                                                <span className="text-muted-foreground">{getQuoteClientName(selectedQuote)}</span>
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground">Buscar cotización enviada/aprobada...</span>
                                        )}
                                        <Search className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent container={container} className="w-[var(--radix-popover-trigger-width)] max-w-[calc(100vw-2rem)] p-0" align="start">
                                    <div className="flex items-center border-b px-3">
                                        <Search className="mr-2 h-4 w-4 shrink-0 opacity-50" />
                                        <input
                                            className="flex h-10 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50"
                                            placeholder="Escribe ID, número o cliente..."
                                            aria-label="Buscar cotización por ID, número o cliente"
                                            value={quoteSearch}
                                            onChange={(e) => setQuoteSearch(e.target.value)}
                                        />
                                    </div>
                                    <ScrollArea className="h-[250px]" onWheel={(e) => e.stopPropagation()}>
                                        {loadingQuotes ? (
                                            <div className="p-4 text-center text-sm text-muted-foreground">
                                                <Loader2 className="h-4 w-4 animate-spin inline mr-2" />
                                                Cargando cotizaciones...
                                            </div>
                                        ) : quoteSearchError ? (
                                            <div className="p-6 text-center" role="alert">
                                                <p className="text-sm font-medium text-red-700">{quoteSearchError}</p>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="mt-3"
                                                    onClick={() => fetchQuotes(quoteSearch)}
                                                >
                                                    Reintentar búsqueda
                                                </Button>
                                            </div>
                                        ) : filteredQuotes.length === 0 ? (
                                            <div className="p-4 text-center text-sm text-muted-foreground">
                                                No se encontraron cotizaciones.
                                            </div>
                                        ) : (
                                            <div className="p-1">
                                                {filteredQuotes.map((q) => (
                                                    <button
                                                        key={q.id}
                                                        type="button"
                                                        className="relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground text-left"
                                                        onClick={() => {
                                                            setSelectedQuote(q)
                                                            setQuotePopoverOpen(false)
                                                        }}
                                                    >
                                                        <div className="flex flex-col items-start w-full">
                                                            <div className="flex justify-between w-full">
                                                                <span className="font-bold text-blue-700">ID #{q.id}</span>
                                                                {q.numero && <span className="text-xs font-mono">{q.numero}</span>}
                                                            </div>
                                                            {q.nombre && (
                                                                <span className="text-sm font-medium text-gray-900 truncate max-w-[400px]" title={q.nombre}>{q.nombre}</span>
                                                            )}
                                                            <span className="text-xs text-gray-600 truncate max-w-[400px]">
                                                                Cliente: {getQuoteClientName(q)}
                                                            </span>
                                                        </div>
                                                        {selectedQuote?.id === q.id && (
                                                            <Check className="ml-auto h-4 w-4 shrink-0 ml-2" />
                                                        )}
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </ScrollArea>
                                </PopoverContent>
                            </Popover>
                            <p className="text-xs text-gray-600">
                                Solo aparecen cotizaciones en estado "Enviada" o "Aprobada".
                            </p>
                        </div> : (
                            <div className="grid grid-cols-1 gap-4 rounded-xl border border-amber-200 bg-amber-50/60 p-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="external-quote-number">N° Cotización Externa *</Label>
                                    <Input id="external-quote-number" required maxLength={100} value={externalQuoteNumber} onChange={e => setExternalQuoteNumber(e.target.value)} placeholder="Ej: 30219 o COT-2024-55" className="bg-white border-amber-300" aria-describedby="external-quote-help" />
                                    <p id="external-quote-help" className="text-xs text-amber-800">Identificador de la cotización creada fuera del sistema.</p>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="external-quote-name">Nombre de la cotización *</Label>
                                    <Input id="external-quote-name" required maxLength={255} value={externalQuoteName} onChange={e => setExternalQuoteName(e.target.value)} placeholder="Ej: Cotización Camisetas Aniversario" className="bg-white border-amber-300" aria-describedby="external-quote-name-help" />
                                    <p id="external-quote-name-help" className="text-xs text-amber-800">Describe de qué trata la cotización, como en Comercial.</p>
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="external-client-name">Cliente / Entidad (opcional)</Label>
                                    <Input id="external-client-name" maxLength={255} value={externalClientName} onChange={e => setExternalClientName(e.target.value)} placeholder="Ej: Cuerpo de Bomberos de Curicó" className="bg-white border-amber-300" />
                                    <p className="text-xs text-amber-800">Nombre para identificar la orden en bodega y despacho.</p>
                                </div>
                            </div>
                        )}
                        <div className="space-y-2">
                            <Label htmlFor="observation">Observación General (opcional)</Label>
                            <Input
                                id="observation"
                                placeholder="Razón de la reserva..."
                                value={observation}
                                onChange={(e) => setObservation(e.target.value)}
                            />
                        </div>
                    </div>

                    {/* Búsqueda y Adición */}
                    <div className="space-y-4 p-3 sm:p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <h4 className="text-sm font-bold text-gray-700 uppercase">2. Agregar Producto</h4>
                        <ReservationProductPicker cart={cartItems} disabled={submitting || invalidCart} onAdd={addToCart} onDirtyChange={setPickerDirty} onPendingChange={setPendingVariants} />
                    </div>

                    {/* Lista de Carrito */}
                    <div className="space-y-2">
                        <h4 ref={cartHeadingRef} id={cartHeadingId} tabIndex={-1} className="scroll-mt-4 rounded-sm text-sm font-bold text-gray-700 uppercase focus:outline-none focus:ring-2 focus:ring-blue-500">3. Productos para reservar ({cartItems.length})</h4>
                        <p className="text-xs text-slate-600">Puedes ajustar las cantidades aquí. El stock se reserva al finalizar la solicitud.</p>
                        {cartItems.length > 0 && <p role="status" className="text-sm font-medium text-slate-800">{cartItems.length} variantes · {cartItems.reduce((sum, item) => sum + (Number.isSafeInteger(item.requestedQuantity) && item.requestedQuantity > 0 ? item.requestedQuantity : 0), 0)} unidades en el pedido</p>}
                        <div className="border rounded-lg overflow-x-auto">
                            <table className="w-full min-w-[560px] table-fixed text-sm">
                                <colgroup>
                                    <col />
                                    <col className="w-24" />
                                    <col className="w-24" />
                                    <col className="w-24" />
                                    <col className="w-12" />
                                </colgroup>
                                <thead className="bg-gray-50 border-b">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-medium text-gray-500">Producto</th>
                                        <th className="px-3 py-2 text-left font-medium text-gray-500">Talla</th>
                                        <th className="px-3 py-2 text-right font-medium text-gray-500 whitespace-nowrap">Stock disp.</th>
                                        <th className="px-3 py-2 text-right font-medium text-gray-500">Cant.</th>
                                        <th className="px-3 py-2 text-right"><span className="sr-only">Quitar</span></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {cartItems.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground italic">
                                                No hay ítems en la lista.
                                            </td>
                                        </tr>
                                    ) : (
                                        cartItems.map((item) => (
                                            <React.Fragment key={item.sku}>
                                            <tr className="hover:bg-gray-50">
                                                <td className="px-3 py-1.5 font-medium" title={item.producto}>
                                                    <span className="block truncate">{item.producto}</span>
                                                </td>
                                                <td className="px-3 py-1.5 whitespace-nowrap text-gray-600">{item.talla || '—'}</td>
                                                <td className="px-3 py-1.5 text-right tabular-nums text-gray-600">{item.cantidad_fisica}</td>
                                                <td className="px-3 py-1.5 text-right font-bold text-blue-600 tabular-nums">
                                                    <Input
                                                        type="number" min={1} step={1} max={Number(item.cantidad_fisica)}
                                                        value={item.requestedQuantity || ''}
                                                        disabled={submitting}
                                                        aria-label={`Cantidad para reservar de ${item.producto}, talla ${item.talla || 'sin talla'}`}
                                                        aria-invalid={!!cartQuantityError(item)}
                                                        aria-describedby={cartQuantityError(item) ? `cart-error-${item.sku}` : undefined}
                                                        className="h-9 px-2 text-right tabular-nums"
                                                        onChange={event => {
                                                            const quantity = Number(event.target.value)
                                                            setCartItems(items => items.map(row => row.sku === item.sku ? { ...row, requestedQuantity: quantity } : row))
                                                        }}
                                                    />
                                                </td>
                                                <td className="px-2 py-1 text-right">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-7 w-7 text-red-500 hover:text-red-700 hover:bg-red-50"
                                                        onClick={() => removeFromCart(item.sku)}
                                                        disabled={submitting}
                                                        aria-label={`Quitar ${item.producto} de la reserva`}
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                </td>
                                            </tr>
                                            {cartQuantityError(item) && <tr><td colSpan={5} className="px-3 pb-2 text-sm text-red-700" id={`cart-error-${item.sku}`} role="alert">{item.producto}: {cartQuantityError(item)}</td></tr>}
                                            </React.Fragment>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <DialogFooter className="shrink-0 pt-4 border-t flex-col sm:flex-row sm:flex-wrap">
                    {invalidCart && <p role="status" className="mr-auto text-sm text-red-700">Corrige las cantidades indicadas para continuar.</p>}
                    {pendingVariants > 0 && <p role="status" className="mr-auto text-sm text-slate-600">Agrega al pedido o limpia las {pendingVariants} cantidades pendientes en «Agregar Producto».</p>}
                    {cartItems.length > 0 && <Button type="button" variant="outline" onClick={viewCart} aria-controls={cartHeadingId} disabled={submitting} className="min-h-11 sm:mr-auto">
                        Ver pedido · {cartItems.length} {cartItems.length === 1 ? 'producto' : 'productos'}
                    </Button>}
                    <Button type="button" variant="outline" disabled={submitting} onClick={() => attemptClose(false)}>
                        Cancelar
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={!canCreateRequest || submitting || pendingVariants > 0 || invalidCart || cartItems.length === 0 || !isQuoteValid}
                        className="bg-blue-600 hover:bg-blue-700 min-w-[140px]"
                    >
                        {submitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        {submitting ? 'Enviando solicitud…' : 'Finalizar Reserva'}
                    </Button>
                </DialogFooter>
                <AlertDialog open={isOpen && (discardOpen || !!submitError)} onOpenChange={open => { if (!open) { setDiscardOpen(false); setSubmitError(null) } }}>
                    <AlertDialogContent
                        onOpenAutoFocus={() => { feedbackFocusRef.current = document.activeElement as HTMLElement | null }}
                        onCloseAutoFocus={event => {
                            if (feedbackFocusRef.current?.isConnected) { event.preventDefault(); feedbackFocusRef.current.focus() }
                        }}
                    >
                        <AlertDialogHeader>
                            <AlertDialogTitle>{submitError?.title ?? '¿Descartar este pedido?'}</AlertDialogTitle>
                            <AlertDialogDescription>
                                {submitError?.description ?? (submissionUncertain
                                    ? 'Se perderán los datos de este formulario. Como no pudimos confirmar el envío anterior, revisa Pendientes antes de crear otro pedido.'
                                    : 'Se perderán los productos seleccionados, las cantidades y los datos de la orden. Todavía no se ha reservado stock.')}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>{submitError ? 'Volver al pedido' : 'Seguir editando'}</AlertDialogCancel>
                            {!submitError && <AlertDialogAction className="bg-red-600 text-white hover:bg-red-700" onClick={handleClose}>Descartar y salir</AlertDialogAction>}
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </DialogContent>
        </Dialog>
    )
}
