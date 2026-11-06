"use client"

import React, { useState, useEffect, useMemo, useRef, useCallback } from "react"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Loader2, ClipboardList, CheckCircle2, XCircle, FileDown, Calendar, User, ChevronRight, Package, Box } from "lucide-react"
import { api, downloadPdf } from "@/lib/api"
import { quoteReference } from "../lib/quote-reference"
import { pteNotice } from "../lib/pte-notice"
import { useAuthContext } from "@/components/auth-provider"
import { useInventoryPermissions } from "@/hooks/useInventoryPermissions"

interface OrderRequest {
    id: number
    sku: string
    cantidad: number
    cotizacion_id?: number | null
    referencia_externa?: string | null
    nombre_cotizacion?: string | null
    cliente_externo?: string | null
    user_id?: number | null
    observacion: string
    fecha_creacion: string
    sku_producto: {
        nombre: string
        talla?: { nombre: string }
    }
    cotizacion?: {
        id?: number
        numero?: string
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
    estado: {
        nombre: string
        color: string
    }
    user?: {
        id: number
        name: string
    }
}

function resolvePendingClientName(cot?: OrderRequest['cotizacion']): string {
    if (!cot) return 'Cliente no especificado'
    if (cot.nombre_cliente) return cot.nombre_cliente
    const cuerpoResuelto = cot.cuerpo ?? cot.compania?.cuerpo ?? null
    if (cuerpoResuelto?.nombre) {
        return `Cuerpo de Bomberos de ${cuerpoResuelto.nombre}${cot.compania ? ` – ${cot.compania.nombre}` : ''}`
    }
    return cot.cliente?.nombre_empresa || cot.cliente?.nombre_contacto || cot.contacto?.nombre || 'Cliente no especificado'
}

function formatReservationDate(dateStr?: string | null): string {
    if (!dateStr) return 'Fecha no disp.'
    const raw = dateStr.split('T')[0].split(' ')[0]
    const parts = raw.split('-').map(Number)
    if (parts.length === 3 && !parts.some(isNaN)) {
        const [year, month, day] = parts
        const date = new Date(year, month - 1, day)
        return date.toLocaleDateString('es-CL', { day: '2-digit', month: 'short', year: 'numeric' })
    }
    return dateStr
}

function reservationDateOrder(value: string): number {
    const timestamp = Date.parse(value.replace(' ', 'T'))
    return Number.isFinite(timestamp) ? timestamp : 0
}

interface PendingRequestsModalProps {
    isOpen: boolean
    onClose: () => void
    onStatusChange: () => void
}

export function PendingRequestsModal({ isOpen, onClose, onStatusChange }: PendingRequestsModalProps) {
    const { canApproveRequests } = useInventoryPermissions()
    const { user } = useAuthContext()
    const storageKey = user?.id != null ? `inventario:pending-collapsed:v1:${user.id}` : null
    const [requests, setRequests] = useState<OrderRequest[]>([])
    const [loading, setLoading] = useState(false)
    const [processingId, setProcessingId] = useState<number | null>(null)
    const [rejectingId, setRejectingId] = useState<number | null>(null)
    const [isBulkProcessing, setIsBulkProcessing] = useState(false)
    const [isBulkRejecting, setIsBulkRejecting] = useState(false)
    const [collapsedGroups, setCollapsedGroups] = useState<Set<string>>(new Set())
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
    const [loadError, setLoadError] = useState<string | null>(null)
    const [feedback, setFeedback] = useState<{ text: string; error: boolean } | null>(null)
    const loadController = useRef<AbortController | null>(null)
    const loadVersion = useRef(0)
    const operationLock = useRef(false)
    const mounted = useRef(true)
    const isProcessing = processingId !== null || rejectingId !== null || isBulkProcessing || isBulkRejecting
    const canAct = !loading && !loadError && !isProcessing

    useEffect(() => {
        mounted.current = true
        return () => {
            mounted.current = false
            loadController.current?.abort()
            loadVersion.current += 1
        }
    }, [])

    useEffect(() => {
        let saved: string[] = []
        try {
            const parsed: unknown = storageKey ? JSON.parse(localStorage.getItem(storageKey) || '[]') : []
            if (Array.isArray(parsed)) saved = parsed.filter((id): id is string => typeof id === 'string')
        } catch {
            // Storage can be unavailable or contain an older/corrupt preference.
        }
        setCollapsedGroups(new Set(saved))
    }, [storageKey])

    const fetchPendingRequests = useCallback(async () => {
        loadController.current?.abort()
        const controller = new AbortController()
        loadController.current = controller
        const version = ++loadVersion.current
        setLoading(true)
        setLoadError(null)
        try {
            const data = await api.get<OrderRequest[]>('/api/order-requests', { signal: controller.signal })
            if (controller.signal.aborted || version !== loadVersion.current || !mounted.current) return
            if (!Array.isArray(data)) throw new Error('Respuesta inválida de solicitudes')
            setRequests(data)
            const pendingIds = new Set(data.map(req => req.id))
            setSelectedIds(previous => new Set([...previous].filter(id => pendingIds.has(id))))
        } catch {
            if (controller.signal.aborted || version !== loadVersion.current || !mounted.current) return
            setLoadError('No se pudieron actualizar las solicitudes. Reintenta antes de aprobar o rechazar.')
        } finally {
            if (!controller.signal.aborted && version === loadVersion.current && mounted.current) setLoading(false)
        }
    }, [])

    useEffect(() => {
        if (isOpen) {
            void fetchPendingRequests()
            setSelectedIds(new Set())
            setFeedback(null)
        }
        return () => {
            loadController.current?.abort()
            loadVersion.current += 1
        }
    }, [isOpen, fetchPendingRequests])

    const groupedRequests = useMemo(() => {
        const groups: Record<string, {
            id: string
            pedidoKey: string
            userId: number | null
            cotizacion_id?: number | null
            is_external: boolean
            numero: string
            nombre: string | null
            cliente: string
            solicitante: string
            fecha: string
            items: OrderRequest[]
        }> = {}

        requests.forEach(req => {
            // Un pedido = cotización o referencia + solicitante: dos ejecutivos con la
            // misma referencia no se mezclan (misma clave que /order-requests/count).
            const pedidoKey = req.cotizacion_id
                ? String(req.cotizacion_id)
                : `ext_${req.referencia_externa || 'sin_ref'}`
            const groupKey = `${pedidoKey}|${req.user_id ?? 'sin_usuario'}`

            if (!groups[groupKey]) {
                const isExternal = !req.cotizacion_id
                groups[groupKey] = {
                    id: groupKey,
                    pedidoKey,
                    userId: req.user_id ?? null,
                    cotizacion_id: req.cotizacion_id,
                    is_external: isExternal,
                    numero: isExternal
                        ? (req.referencia_externa ? quoteReference(req.referencia_externa) : 'Sin Ref.')
                        : quoteReference(req.cotizacion?.numero || req.cotizacion_id),
                    // Describe de qué trata la cotización (como "Nombre cotización" en Comercial).
                    nombre: (isExternal ? req.nombre_cotizacion : req.cotizacion?.nombre)?.trim() || null,
                    cliente: isExternal
                        ? (req.cliente_externo || 'Cliente no especificado (Cotización Externa)')
                        : resolvePendingClientName(req.cotizacion),
                    solicitante: req.user?.name || 'Usuario desconocido',
                    fecha: req.fecha_creacion,
                    items: []
                }
            }
            if (reservationDateOrder(req.fecha_creacion) > reservationDateOrder(groups[groupKey].fecha)) {
                groups[groupKey].fecha = req.fecha_creacion
            }
            groups[groupKey].items.push(req)
        })

        return Object.values(groups).sort((a, b) =>
            reservationDateOrder(b.fecha) - reservationDateOrder(a.fecha)
            || (b.cotizacion_id ?? 0) - (a.cotizacion_id ?? 0)
            || b.id.localeCompare(a.id, undefined, { numeric: true })
        )
    }, [requests])

    const toggleGroup = (id: string) => {
        const next = new Set(collapsedGroups)
        if (next.has(id)) next.delete(id)
        else next.add(id)
        setCollapsedGroups(next)
        if (storageKey) {
            try { localStorage.setItem(storageKey, JSON.stringify([...next])) } catch {
                // Keep the preference in memory when browser storage is unavailable.
            }
        }
    }

    const toggleSelection = (id: number) => {
        if (!canAct || operationLock.current) return
        const newSelected = new Set(selectedIds)
        if (newSelected.has(id)) {
            newSelected.delete(id)
        } else {
            newSelected.add(id)
        }
        setSelectedIds(newSelected)
    }

    const toggleGroupSelection = (groupId: string, items: OrderRequest[]) => {
        if (!canAct || operationLock.current) return
        const newSelected = new Set(selectedIds)
        const itemIds = items.map(i => i.id)
        const allSelected = itemIds.every(id => newSelected.has(id))

        if (allSelected) {
            itemIds.forEach(id => newSelected.delete(id))
        } else {
            itemIds.forEach(id => newSelected.add(id))
        }
        setSelectedIds(newSelected)
    }

    const processRequests = async (action: 'confirm' | 'reject', id?: number) => {
        if (!canApproveRequests || !canAct || operationLock.current) return
        const ids = id === undefined ? Array.from(selectedIds) : [id]
        if (ids.length === 0) return
        const confirming = action === 'confirm'
        const question = confirming
            ? `¿Confirmar ${ids.length} ${ids.length === 1 ? 'ítem' : 'ítems'}? Las unidades ya están reservadas; no se descontarán nuevamente.`
            : `¿Rechazar ${ids.length} ${ids.length === 1 ? 'ítem' : 'ítems'}? Las unidades reservadas volverán al stock disponible.`
        if (!window.confirm(question)) return
        operationLock.current = true
        setFeedback(null)
        if (id === undefined) {
            if (confirming) setIsBulkProcessing(true)
            else setIsBulkRejecting(true)
        } else if (confirming) setProcessingId(id)
        else setRejectingId(id)

        try {
            let processedIds: number[]
            if (id === undefined) {
                const result = await api.post<{ confirmed_ids?: number[]; rejected_ids?: number[] }>(`/api/order-requests/bulk-${action}`, { ids })
                const returned = confirming ? result.confirmed_ids : result.rejected_ids
                if (!Array.isArray(returned)) throw new Error('El servidor no informó el resultado. Actualiza las solicitudes antes de reintentar.')
                processedIds = [...new Set(returned)].filter(value => ids.includes(value))
            } else {
                await api.post(`/api/order-requests/${id}/${action}`)
                processedIds = [id]
            }
            if (!mounted.current) return
            const processed = new Set(processedIds)
            setRequests(previous => previous.filter(req => !processed.has(req.id)))
            setSelectedIds(previous => new Set([...previous].filter(value => !ids.includes(value))))
            const skipped = ids.length - processedIds.length
            const count = processedIds.length
            const resultText = count === 1
                ? (confirming ? '1 ítem confirmado.' : '1 ítem rechazado.')
                : `${count} ítems ${confirming ? 'confirmados' : 'rechazados'}.`
            setFeedback({ error: false, text: resultText + (skipped ? ` ${skipped} ya no estaban pendientes y no se modificaron.` : '') })
            await fetchPendingRequests()
            try { onStatusChange() } catch (error) { console.error('No se pudo actualizar el resumen de inventario:', error) }
            if (id !== undefined && confirming) void downloadPdf(`/api/order-requests/${id}/dispatch-pdf`, { throwOnError: true }).catch(error => {
                if (mounted.current) pteNotice('error', 'No se pudo descargar el PDF', 'La aprobación ya se completó. ' + (error instanceof Error ? error.message : 'Reintenta la descarga desde Pendientes.'))
            })
        } catch (error: unknown) {
            if (!mounted.current) return
            const message = error instanceof Error ? error.message : 'No se pudo completar la operación.'
            setFeedback({ error: true, text: message })
            // A lost response may follow a successful commit; refresh before allowing another action.
            await fetchPendingRequests()
            if (mounted.current) {
                try { onStatusChange() } catch (refreshError) { console.error('No se pudo actualizar el resumen de inventario:', refreshError) }
            }
        } finally {
            operationLock.current = false
            if (mounted.current) {
                setProcessingId(null)
                setRejectingId(null)
                setIsBulkProcessing(false)
                setIsBulkRejecting(false)
            }
        }
    }

    const handleAccept = (id: number) => processRequests('confirm', id)
    const handleReject = (id: number) => processRequests('reject', id)
    const handleBulkAccept = () => processRequests('confirm')
    const handleBulkReject = () => processRequests('reject')
    const closeModal = () => { if (!operationLock.current) onClose() }

    const downloadQuotePdf = (cotizacionId: string, userId: number | null) => {
        const baseUrl = (process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000').replace(/\/$/, "");
        const byUser = userId ? `?user_id=${userId}` : ""
        downloadPdf(`${baseUrl}/api/order-requests/quote/${encodeURIComponent(cotizacionId)}/pdf${byUser}`)
    }

    return (
        <Dialog open={isOpen} onOpenChange={open => { if (!open) closeModal() }}>
            <DialogContent
                showCloseButton={!isProcessing}
                onEscapeKeyDown={event => { if (operationLock.current) event.preventDefault() }}
                onInteractOutside={event => { if (operationLock.current) event.preventDefault() }}
                className="sm:max-w-[950px] lg:max-w-[1050px] w-[95vw] max-h-[92vh] flex flex-col p-0 overflow-hidden"
            >
                <DialogHeader className="px-6 py-3.5 bg-gray-50/80 border-b">
                    <div className="flex justify-between items-center w-full pr-8">
                        <div>
                            <DialogTitle className="flex items-center gap-2 text-lg font-bold text-gray-900">
                                <ClipboardList className="h-5 w-5 text-orange-500" />
                                {canApproveRequests ? "Aprobaciones Pendientes de Bodega" : "Solicitudes Pendientes de Bodega"}
                            </DialogTitle>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                {canApproveRequests ? "Gestiona los despachos de productos reservados agrupados por cotización" : "El stock está reservado. Solo el superadmin puede aprobar o rechazar las solicitudes."}
                            </p>
                        </div>
                    </div>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto bg-slate-50/40 custom-scrollbar">
                    <div className="p-4 sm:p-5">
                        {feedback && (
                            <div role={feedback.error ? 'alert' : 'status'} className={`mb-3 rounded-lg border p-3 text-sm ${feedback.error ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800'}`}>
                                {feedback.text}
                            </div>
                        )}
                        {loading ? (
                            <div className="flex flex-col items-center justify-center py-20 gap-3">
                                <Loader2 className="h-9 w-9 text-blue-600 animate-spin" />
                                <p className="text-sm font-medium text-gray-500">Cargando solicitudes...</p>
                            </div>
                        ) : loadError ? (
                            <div role="alert" className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                                <p>{loadError}</p>
                                <Button variant="outline" className="mt-3" disabled={isProcessing} onClick={() => void fetchPendingRequests()}>Reintentar</Button>
                            </div>
                        ) : groupedRequests.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-16 text-muted-foreground border-2 border-dashed rounded-xl bg-white">
                                <Box className="h-10 w-10 opacity-20 mb-2" />
                                <p className="text-sm font-medium">No hay despachos pendientes por procesar.</p>
                            </div>
                        ) : (
                            <div className="space-y-3.5">
                                {groupedRequests.map((group) => {
                                    const isCollapsed = collapsedGroups.has(group.id)
                                    const allItemsSelected = group.items.every(i => selectedIds.has(i.id))
                                    const someItemsSelected = group.items.some(i => selectedIds.has(i.id))

                                    return (
                                        <div key={group.id} className="bg-white border border-gray-200/90 rounded-xl shadow-2xs overflow-hidden transition-all">
                                            {/* Header del Grupo (Cotización + Cliente + Solicitante) */}
                                            <div
                                                className={`px-3.5 py-2.5 flex flex-wrap items-center justify-between gap-2.5 bg-gray-50/90 border-b ${isCollapsed ? 'border-transparent' : 'border-gray-200/80'}`}
                                            >
                                                <div className="flex items-center gap-2.5 flex-wrap min-w-0">
                                                    {canApproveRequests && <input
                                                        type="checkbox"
                                                        disabled={!canAct}
                                                        className="w-4 h-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500 cursor-pointer"
                                                        checked={allItemsSelected}
                                                        ref={el => {
                                                            if (el) el.indeterminate = someItemsSelected && !allItemsSelected
                                                        }}
                                                        onChange={() => toggleGroupSelection(group.id, group.items)}
                                                    />}

                                                    <button
                                                        type="button"
                                                        className="flex items-center gap-2 cursor-pointer select-none text-left focus:outline-none"
                                                        aria-expanded={!isCollapsed}
                                                        onClick={() => toggleGroup(group.id)}
                                                    >
                                                        <div className={`transition-transform duration-200 p-0.5 rounded-full bg-slate-200 text-slate-600 hover:bg-slate-300 ${isCollapsed ? "" : "rotate-90"}`}>
                                                            <ChevronRight className="h-3.5 w-3.5" />
                                                        </div>
                                                        <span className={`inline-flex items-center gap-1.5 text-white text-[11px] font-bold px-2.5 py-1 rounded-md shadow-2xs ${
                                                            group.is_external ? 'bg-amber-700' : 'bg-slate-800'
                                                        }`}>
                                                            <Package className="h-3 w-3" />
                                                            {group.is_external ? `Ext. ${group.numero}` : `Sist. ${group.numero}`}
                                                        </span>
                                                    </button>
                                                    {group.nombre && (
                                                        <span className="text-xs font-semibold text-gray-900 truncate max-w-[220px] md:max-w-[300px]" title={group.nombre}>
                                                            {group.nombre}
                                                        </span>
                                                    )}

                                                    {/* Cliente en la misma línea */}
                                                    <span className="text-gray-300 hidden sm:inline">|</span>
                                                    <div className="flex items-center gap-1.5 text-xs text-gray-800 font-medium truncate max-w-[260px] md:max-w-[340px]" title={group.cliente}>
                                                        <User aria-hidden="true" className="h-3.5 w-3.5 text-blue-500 flex-shrink-0" />
                                                        <span className="sr-only">Cliente:</span>
                                                        <span className="font-semibold text-gray-900 truncate">{group.cliente}</span>
                                                    </div>

                                                    {/* Solicitante y Fecha */}
                                                    <span className="text-gray-300 hidden md:inline">·</span>
                                                    <span className="text-[11px] text-gray-500 hidden md:inline truncate">
                                                        Por: <span className="font-semibold text-gray-700">{group.solicitante}</span>
                                                    </span>

                                                    <span className="text-[11px] text-gray-400 hidden lg:inline-flex items-center gap-1">
                                                        <Calendar className="h-3 w-3 text-gray-400" />
                                                        {formatReservationDate(group.fecha)}
                                                    </span>
                                                </div>

                                                <div className="flex items-center gap-2 ml-auto">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={(e) => {
                                                            e.stopPropagation()
                                                            downloadQuotePdf(group.pedidoKey, group.userId)
                                                        }}
                                                        className="h-6 px-2 text-[10px] text-blue-600 hover:text-blue-700 hover:bg-blue-50 flex items-center gap-1 font-semibold border border-blue-200/80 rounded"
                                                    >
                                                        <FileDown className="h-3 w-3" />
                                                        PDF
                                                    </Button>
                                                    <Badge variant="secondary" className="h-6 px-2 text-[10px] font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-150">
                                                        {group.items.length} {group.items.length === 1 ? 'ítem' : 'ítems'}
                                                    </Badge>
                                                </div>
                                            </div>

                                            {/* Contenido del Grupo (Lista comprimida de SKUs) */}
                                            {!isCollapsed && (
                                                <div className="divide-y divide-gray-100 bg-white">
                                                    {group.items.map((req) => (
                                                        <div
                                                            key={req.id}
                                                            className={`px-3 py-2 transition-colors flex items-center gap-3 text-xs hover:bg-slate-50/70 ${selectedIds.has(req.id) ? 'bg-orange-50/40' : ''}`}
                                                        >
                                                            {canApproveRequests && <input
                                                                type="checkbox"
                                                                disabled={!canAct}
                                                                className="w-3.5 h-3.5 rounded border-gray-300 text-orange-600 focus:ring-orange-500 cursor-pointer flex-shrink-0"
                                                                checked={selectedIds.has(req.id)}
                                                                onChange={() => toggleSelection(req.id)}
                                                            />}

                                                            {/* Información de Producto comprimida en fila */}
                                                            <div className="flex-1 min-w-0 flex items-center gap-3 flex-wrap sm:flex-nowrap">
                                                                <div className="min-w-0 flex-1">
                                                                    <div className="flex items-center gap-2 flex-wrap">
                                                                        <span className="font-semibold text-gray-900 truncate" title={req.sku_producto?.nombre}>
                                                                            {req.sku_producto?.nombre}
                                                                        </span>
                                                                        <code className="text-[10px] px-1.5 py-0.2 rounded font-mono text-gray-500 bg-gray-100 border border-gray-200/60 flex-shrink-0">
                                                                            {req.sku}
                                                                        </code>
                                                                    </div>
                                                                    {req.observacion && (
                                                                        <p className="text-[10px] text-gray-500 italic truncate max-w-xl mt-0.5">
                                                                            "{req.observacion}"
                                                                        </p>
                                                                    )}
                                                                </div>

                                                                {/* Talla y Cantidad comprimidas */}
                                                                <div className="flex items-center gap-3 flex-shrink-0">
                                                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold font-mono bg-slate-100 text-slate-700 border border-slate-200/60">
                                                                        {req.sku_producto?.talla?.nombre || 'T: -'}
                                                                    </span>
                                                                    <span className="inline-flex items-center gap-1 text-[11px] font-medium text-gray-600">
                                                                        Cant: <strong className="font-bold text-blue-700 font-mono text-xs">{req.cantidad}</strong>
                                                                    </span>
                                                                </div>
                                                            </div>

                                                            {/* Botones de acción ultra-compactos */}
                                                            <div className="flex items-center gap-1 flex-shrink-0 pl-1">
                                                                {canApproveRequests && <Button
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    className="h-7 px-2 text-emerald-700 hover:text-emerald-800 hover:bg-emerald-50 text-[11px] font-semibold"
                                                                    disabled={!canAct}
                                                                    onClick={() => handleAccept(req.id)}
                                                                >
                                                                    {processingId === req.id ? (
                                                                        <Loader2 className="h-3 w-3 animate-spin" />
                                                                    ) : (
                                                                        <>
                                                                            <CheckCircle2 className="h-3.5 w-3.5 mr-1 text-emerald-600" />
                                                                            Confirmar
                                                                        </>
                                                                    )}
                                                                </Button>}
                                                                {canApproveRequests && <Button
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    className="h-7 px-2 text-rose-600 hover:text-rose-700 hover:bg-rose-50 text-[11px] font-semibold"
                                                                    disabled={!canAct}
                                                                    onClick={() => handleReject(req.id)}
                                                                >
                                                                    {rejectingId === req.id ? (
                                                                        <Loader2 className="h-3 w-3 animate-spin" />
                                                                    ) : (
                                                                        <>
                                                                            <XCircle className="h-3.5 w-3.5 mr-1 text-rose-500" />
                                                                            Rechazar
                                                                        </>
                                                                    )}
                                                                </Button>}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    )
                                })}
                            </div>
                        )}
                    </div>
                </div>
                <div className="p-4 bg-gray-50 border-t flex justify-between items-center gap-3">
                    <div className="flex gap-3">
                        {canApproveRequests && selectedIds.size > 0 && (
                            <>
                                <Button
                                    onClick={handleBulkReject}
                                    disabled={!canAct}
                                    variant="outline"
                                    className="text-red-600 border-red-600 hover:bg-red-50 font-bold animate-in fade-in zoom-in duration-200"
                                >
                                    {isBulkRejecting ? (
                                        <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                    ) : (
                                        <XCircle className="h-4 w-4 mr-2" />
                                    )}
                                    Rechazar Selección ({selectedIds.size})
                                </Button>
                                <Button
                                    onClick={handleBulkAccept}
                                    disabled={!canAct}
                                    variant="outline"
                                    className="text-green-600 border-green-600 hover:bg-green-50 font-bold animate-in fade-in zoom-in duration-200"
                                >
                                    {isBulkProcessing ? (
                                        <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                    ) : (
                                        <CheckCircle2 className="h-4 w-4 mr-2" />
                                    )}
                                    Confirmar Selección ({selectedIds.size})
                                </Button>
                            </>
                        )}
                    </div>
                    <Button variant="outline" onClick={closeModal} disabled={isProcessing} className="text-gray-600">
                        Cerrar Ventana
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    )
}
