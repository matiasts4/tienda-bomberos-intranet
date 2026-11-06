import { useId, type ReactNode } from "react"
import { FechaInput } from "@/components/ui/fecha-input"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogTitle } from "@/components/ui/dialog"
import { ChevronDown, ChevronRight, ExternalLink, FileText, History, Loader2, RefreshCw } from "lucide-react"
import type { LoteMovimiento, useBitacora } from "../hooks/useBitacora"
import { quoteReference } from "../lib/quote-reference"

const movementTypes: Record<string, string> = {
    RESERVA_PRODUCTO: "Solicitud / Reserva de stock",
    SALIDA_VENTA: "Sacada de stock / Despacho",
    ANULACION_RESERVA: "Anulación de solicitud / reserva",
    INGRESO_BODEGA: "Ingreso a bodega",
    ANULACION_INGRESO: "Anulación de ingreso",
    AJUSTE_MANUAL: "Ajuste manual de stock",
    CAMBIOS_EXCEL: "Cambios por Excel",
    INVENTARIO_INICIAL: "Inventario inicial",
}
const typeLabel = (type: string) => movementTypes[type] ?? type.replaceAll("_", " ")
// Qué hizo quien registró el movimiento: un ejecutivo solicita, un superadmin aprueba o rechaza.
const actorVerbs: Record<string, string> = { solicito: "Solicitó", aprobo: "Aprobó", rechazo: "Rechazó", registro: "Registró" }
const actorVerb = (lote: LoteMovimiento) => actorVerbs[lote.rol_actor ?? "registro"] ?? "Registró"
// El solicitante se muestra aparte solo cuando no es quien registró (en una solicitud, lo es).
const separateRequester = (lote: LoteMovimiento) =>
    lote.rol_actor !== "solicito" && lote.solicitante && lote.solicitante !== lote.usuario ? lote.solicitante : null
const requestStatus = (lote: LoteMovimiento) => {
    const when = lote.resuelto_en ? ` · ${lote.resuelto_en}` : ""
    switch (lote.estado_solicitud) {
        case "pendiente": return { text: "Pendiente de aprobación", className: "bg-amber-50 text-amber-900 border-amber-200" }
        case "aprobada": return { text: `Aprobada por ${lote.resuelto_por ?? "—"}${when}`, className: "bg-emerald-50 text-emerald-900 border-emerald-200" }
        case "rechazada": return { text: `Rechazada por ${lote.resuelto_por ?? "—"}${when}`, className: "bg-red-50 text-red-900 border-red-200" }
        case "parcial": return { text: `Resuelta en parte por ${lote.resuelto_por ?? "—"}${when}`, className: "bg-slate-100 text-slate-800 border-slate-300" }
        default: return null
    }
}
// Sin "COT." repetido: la etiqueta ya dice "Cot." o "Cotización:".
const quoteLabel = (num?: string | null, id?: number | null) => quoteReference(num || id)
const controlClass = "h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus-visible:outline-2 focus-visible:outline-blue-600 focus-visible:outline-offset-2"

function DaySection({ date, count, expanded, onToggle, onFilter, children }: { date: string; count: number; expanded: boolean; onToggle: () => void; onFilter?: () => void; children: ReactNode }) {
    const contentId = useId()
    return (
        <section aria-label={`Movimientos del ${date}`} className="mb-4 last:mb-0">
            <div className="mb-2 flex items-center gap-3 border-b pb-1">
                <h4 className="min-w-0 flex-1 text-sm font-semibold text-slate-900">
                    <button type="button" aria-expanded={expanded} aria-controls={contentId} onClick={onToggle} className="flex min-h-10 w-full flex-wrap items-center gap-x-3 gap-y-1 rounded-md px-2 py-2 text-left hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-blue-600">
                        {expanded ? <ChevronDown aria-hidden="true" className="h-4 w-4 shrink-0 text-slate-600" /> : <ChevronRight aria-hidden="true" className="h-4 w-4 shrink-0 text-slate-600" />}
                        <span>{date}</span>
                        <span className="font-normal text-slate-600">{count} {count === 1 ? "operación" : "operaciones"} en esta página</span>
                        <span className="ml-auto text-xs font-normal text-slate-600">{expanded ? "Minimizar" : "Expandir"}</span>
                    </button>
                </h4>
                {onFilter && <button type="button" className="min-h-10 shrink-0 rounded px-2 text-xs font-medium text-blue-700 underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-blue-600" onClick={onFilter}>Ver este día</button>}
            </div>
            <div id={contentId} hidden={!expanded}>{children}</div>
        </section>
    )
}

export function BitacoraModal({ bitacora: b }: { bitacora: ReturnType<typeof useBitacora> }) {
    const [year, month] = b.filters.mes.split("-").map(Number)
    const lastDay = new Date(year, month, 0).getDate()
    const selectedWeek = b.weeks.find(week => String(week.number) === b.filters.semana)
    return (
        <Dialog open={b.isOpen} onOpenChange={open => { if (!open) b.close() }}>
            <DialogContent className="flex h-[90dvh] max-h-[960px] flex-col gap-0 overflow-hidden p-0 w-[96vw] max-w-[96vw] sm:max-w-[96vw] 2xl:max-w-[1600px]">
                <div className="max-h-[45dvh] shrink-0 overflow-y-auto border-b px-5 py-4 sm:max-h-none sm:overflow-visible sm:px-6">
                    <DialogTitle className="flex items-center gap-2 pr-8 text-xl"><FileText className="h-5 w-5 text-blue-600" />Bitácora de inventario</DialogTitle>
                    <DialogDescription className="mt-1">Revisa las operaciones por día. Despliega una fila para ver productos, responsables y saldos completos.</DialogDescription>
                    <div className="mt-4 grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-[150px_1fr_1fr_1fr_170px]">
                        <label className="space-y-1 text-sm font-medium text-slate-700">Mes
                            <input type="month" value={b.filters.mes} onChange={e => b.changeFilter("mes", e.target.value)} className={controlClass} />
                        </label>
                        <label className="space-y-1 text-sm font-medium text-slate-700">Semana del mes
                            <select value={b.filters.semana} onChange={e => b.changeFilter("semana", e.target.value)} className={controlClass}>
                                <option value="">Todas las semanas</option>
                                {b.weeks.map(week => <option key={week.number} value={week.number}>{week.label}</option>)}
                            </select>
                        </label>
                        <label className="space-y-1 text-sm font-medium text-slate-700">Tipo de movimiento
                            <select value={b.filters.tipo} onChange={e => b.changeFilter("tipo", e.target.value)} className={controlClass}>
                                <option value="">Todos los tipos</option>
                                <optgroup label="Solicitudes y Despachos">
                                    <option value="RESERVA_PRODUCTO">Solicitud / Reserva de stock</option>
                                    <option value="SALIDA_VENTA">Sacada de stock / Despacho</option>
                                    <option value="ANULACION_RESERVA">Anulación de solicitud / reserva</option>
                                </optgroup>
                                <optgroup label="Ingresos y Ajustes">
                                    <option value="INGRESO_BODEGA">Ingreso a bodega</option>
                                    <option value="ANULACION_INGRESO">Anulación de ingreso</option>
                                    <option value="AJUSTE_MANUAL">Ajuste manual de stock</option>
                                    <option value="CAMBIOS_EXCEL">Cambios por Excel</option>
                                    <option value="INVENTARIO_INICIAL">Inventario inicial</option>
                                </optgroup>
                            </select>
                        </label>
                        <label className="min-w-0 space-y-1 text-sm font-medium text-slate-700">Usuario
                            <select value={b.filters.usuario_id} onChange={e => b.changeFilter("usuario_id", e.target.value)} disabled={b.usersLoading || !!b.usersError} aria-describedby="bitacora-users-help" className={controlClass}>
                                <option value="">{b.usersLoading ? "Cargando usuarios…" : b.usersError ? "Usuarios no disponibles" : "Todos los usuarios (solicitó, aprobó o registró)"}</option>
                                {b.users.map(user => <option key={user.value} value={user.value}>{user.label}</option>)}
                            </select>
                        </label>
                        <label className="space-y-1 text-sm font-medium text-slate-700">Día (opcional)
                            <FechaInput min={`${b.filters.mes}-${String(selectedWeek?.start ?? 1).padStart(2, "0")}`} max={`${b.filters.mes}-${String(selectedWeek?.end ?? lastDay).padStart(2, "0")}`} value={b.filters.fecha} onChange={v => b.changeFilter("fecha", v)} className="w-full" />
                        </label>
                    </div>
                    <p id="bitacora-users-help" className="sr-only">Incluye a quien solicitó, aprobó, rechazó o registró el movimiento. Se muestran los usuarios con actividad en el mes.</p>
                    {b.usersError && <div role="alert" className="mt-2 flex flex-wrap items-center gap-2 text-sm text-red-700">{b.usersError}<Button variant="outline" size="sm" onClick={b.retry}>Reintentar</Button>{b.filters.usuario_id && <Button variant="outline" size="sm" onClick={() => b.changeFilter("usuario_id", "")}>Quitar filtro de usuario</Button>}</div>}
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <p className="text-xs text-slate-600">Semanas de lunes a domingo, dentro del mes. Se cargan 25 operaciones por página.</p>
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" className="h-9 gap-2" disabled={b.downloading !== null || !b.filters.semana} title={!b.filters.semana ? "Selecciona una semana para descargar su PDF" : selectedWeek?.label} onClick={() => void b.download(undefined, "week")}>
                                {b.downloading === "week" ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileText className="h-4 w-4" />}
                                {b.downloading === "week" ? "Generando…" : "PDF de la semana"}
                            </Button>
                            <Button variant="outline" className="h-10 gap-2" disabled={b.downloading !== null} onClick={() => void b.download()}>
                                {b.downloading === "month" ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileText className="h-4 w-4" />}
                                {b.downloading === "month" ? "Generando…" : "PDF del mes"}
                            </Button>
                            <Button variant="outline" size="icon" className="h-10 w-10" disabled={b.loading} onClick={b.retry} aria-label="Actualizar bitácora"><RefreshCw className="h-4 w-4" /></Button>
                        </div>
                    </div>
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
                        <p>Los PDF respetan el tipo y usuario seleccionados, pero no el filtro de día. El PDF mensual incluye todas las semanas.</p>
                        {b.filters.fecha && <button type="button" className="rounded px-2 py-1 text-sm text-blue-700 underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-blue-600" onClick={() => b.changeFilter("fecha", "")}>Quitar filtro de día</button>}
                    </div>
                    {b.pdfError && <p role="alert" className="mt-3 text-sm text-red-700">{b.pdfError}</p>}
                </div>
                <div key={`${b.filters.mes}-${b.filters.semana}-${b.filters.fecha}-${b.filters.tipo}-${b.filters.usuario_id}-${b.filters.page}`} className="min-h-0 flex-1 overflow-y-auto px-5 py-4 sm:px-6" aria-busy={b.loading}>
                    {b.loading ? (
                        <div role="status" className="flex h-full min-h-40 items-center justify-center gap-3 text-slate-600"><Loader2 className="h-5 w-5 animate-spin" />Cargando operaciones…</div>
                    ) : b.error ? (
                        <div role="alert" className="py-12 text-center"><p className="mb-4 text-slate-700">{b.error}</p><Button variant="outline" onClick={b.retry}>Reintentar</Button></div>
                    ) : Object.keys(b.groups).length === 0 ? (
                        <div className="flex h-full min-h-40 flex-col items-center justify-center gap-3 text-center text-slate-600"><History className="h-8 w-8" /><p className="font-medium text-slate-900">No hay movimientos con estos filtros</p><p className="text-sm">Prueba otro mes, usuario, tipo de movimiento o día.</p></div>
                    ) : b.weekGroups.map(week => (
                        <section key={week.number} className="mb-6 last:mb-0" aria-label={week.label}>
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-md bg-slate-100 px-3 py-2">
                                <h3 className="text-sm font-semibold text-slate-900">{week.label}</h3>
                                {!b.filters.semana && <Button variant="ghost" size="sm" onClick={() => b.changeFilter("semana", String(week.number))}>Ver solo esta semana</Button>}
                            </div>
                            {week.days.map(([date, lotes]) => (
                        <DaySection key={date} date={date} count={lotes.length} expanded={!b.collapsedDays.has(date)} onToggle={() => b.toggleDay(date)} onFilter={!b.filters.fecha ? () => b.changeFilter("fecha", date.split("/").reverse().join("-")) : undefined}>
                            <div className="divide-y divide-slate-200">
                                {lotes.map(lote => {
                                    const id = lote.movimiento_id
                                    const expanded = b.expandedId === id
                                    const detail = b.details[id]
                                    return (
                                        <div key={id}>
                                            <div className="flex items-center gap-2 py-1">
                                                 <div
                                                    role="button"
                                                    tabIndex={0}
                                                    aria-expanded={expanded}
                                                    aria-controls={`bitacora-detail-${id}`}
                                                    onClick={() => b.toggleLote(id)}
                                                    onKeyDown={e => { if (e.target === e.currentTarget && (e.key === "Enter" || e.key === " ")) { e.preventDefault(); b.toggleLote(id) } }}
                                                    className="grid min-w-0 flex-1 cursor-pointer grid-cols-[20px_1fr] items-center gap-3 rounded-md px-2 py-2 text-left hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-blue-600 sm:grid-cols-[20px_52px_180px_1fr_80px] xl:flex"
                                                >
                                                    {expanded ? <ChevronDown className="h-4 w-4 text-slate-600" /> : <ChevronRight className="h-4 w-4 text-slate-600" />}
                                                    <span className="hidden shrink-0 text-sm tabular-nums text-slate-600 sm:block">{lote.fecha_hora_grupo.split(" ")[1]}</span>
                                                    <span className="min-w-0 xl:hidden">
                                                        <span className="block text-sm font-semibold text-slate-900">{typeLabel(lote.tipo_movimiento)}</span>
                                                        <span className="block truncate text-xs text-slate-600" title={`${actorVerb(lote)}: ${lote.usuario}`}>
                                                            {actorVerb(lote)}: <span className="font-medium text-slate-700">{lote.usuario}</span>
                                                        </span>
                                                        {separateRequester(lote) ? (
                                                            <span className="block truncate text-[11px] text-amber-800" title={`Solicitó: ${separateRequester(lote)}`}>
                                                                Solicitó: <span className="font-medium">{separateRequester(lote)}</span>
                                                            </span>
                                                        ) : null}
                                                        {requestStatus(lote) ? (
                                                            <span className={`mt-0.5 inline-block max-w-full truncate rounded border px-1.5 py-px text-[11px] font-medium ${requestStatus(lote)!.className}`} title={requestStatus(lote)!.text}>
                                                                {requestStatus(lote)!.text}
                                                            </span>
                                                        ) : null}
                                                    </span>
                                                    <span className="col-start-2 min-w-0 sm:col-auto xl:hidden">
                                                        <span className="block truncate text-sm text-slate-900" title={lote.producto_resumen}>{lote.producto_resumen}</span>
                                                        <span className="flex flex-wrap items-center gap-1.5 text-xs text-slate-600 mt-0.5">
                                                            {lote.cotizacion_id ? (
                                                                <a
                                                                    href={`/comercial/cotizaciones/${lote.cotizacion_id}`}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    onClick={e => e.stopPropagation()}
                                                                    className="inline-flex items-center gap-1 font-bold text-amber-900 bg-amber-100 hover:bg-amber-200 hover:text-amber-950 px-1.5 py-0.5 rounded border border-amber-300 text-[11px] leading-none shrink-0 transition-colors shadow-2xs"
                                                                    title={`Abrir Cotización ${quoteLabel(lote.cotizacion_numero, lote.cotizacion_id)} en nueva pestaña`}
                                                                >
                                                                    <span>Cot. {quoteLabel(lote.cotizacion_numero, lote.cotizacion_id)}</span>
                                                                    <ExternalLink className="h-2.5 w-2.5 opacity-70" />
                                                                </a>
                                                            ) : lote.cotizacion_numero ? (
                                                                <span
                                                                    className="inline-flex items-center gap-1 font-bold text-amber-900 bg-amber-100 px-1.5 py-0.5 rounded border border-amber-300 text-[11px] leading-none shrink-0 shadow-2xs"
                                                                    title={`Cotización externa / manual: ${lote.cotizacion_numero}`}
                                                                >
                                                                    <span>Cot. {quoteLabel(lote.cotizacion_numero, null)}</span>
                                                                </span>
                                                            ) : null}
                                                            {lote.cliente_nombre ? (
                                                                <span className="inline-flex items-center font-semibold text-blue-900 bg-blue-50/90 px-1.5 py-0.5 rounded border border-blue-200 text-[11px] leading-none shrink-0 max-w-[260px] truncate" title={lote.cliente_nombre}>
                                                                    Cliente: {lote.cliente_nombre}
                                                                </span>
                                                            ) : null}
                                                            <span className="text-slate-500 text-xs">
                                                                {lote.total_registros > 1 ? `${lote.total_registros} registros` : "1 registro"}
                                                            </span>
                                                        </span>
                                                    </span>
                                                    <span className="col-start-2 text-sm tabular-nums text-slate-700 sm:col-auto sm:text-right xl:hidden">{lote.total_unidades} u.</span>
                                                    <span className="hidden min-w-0 flex-1 grid-cols-[165px_180px_120px_minmax(0,1fr)_64px] items-center gap-3 xl:grid">
                                                        <span className="flex min-w-0 items-center gap-2 text-sm font-semibold text-slate-900" title={requestStatus(lote)?.text || typeLabel(lote.tipo_movimiento)}>
                                                            <span className="truncate">{typeLabel(lote.tipo_movimiento).replace("Solicitud / Reserva de stock", "Reserva").replace("Sacada de stock / Despacho", "Despacho de stock").replace("Anulación de solicitud / reserva", "Anulación de reserva")}</span>
                                                            {requestStatus(lote) && <span className={`shrink-0 rounded border px-1.5 py-0.5 text-xs font-medium ${requestStatus(lote)!.className}`}>{({ pendiente: "Pendiente", aprobada: "Aprobada", rechazada: "Rechazada", parcial: "Parcial" } as Record<string, string>)[lote.estado_solicitud!]}</span>}
                                                        </span>
                                                        <span className="truncate text-sm text-slate-700" title={`${actorVerb(lote)}: ${lote.usuario}${separateRequester(lote) ? ` · Solicitó: ${separateRequester(lote)}` : ""}${requestStatus(lote) ? ` · ${requestStatus(lote)!.text}` : ""}`}>{actorVerb(lote)}: {lote.usuario}</span>
                                                        <span className="truncate text-sm text-amber-900" title={lote.cotizacion_numero || "Sin cotización"}>
                                                            {lote.cotizacion_id ? <a href={`/comercial/cotizaciones/${lote.cotizacion_id}`} target="_blank" rel="noopener noreferrer" onClick={event => event.stopPropagation()} className="rounded font-semibold underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-blue-600">Cot. {quoteLabel(lote.cotizacion_numero, lote.cotizacion_id)}</a> : lote.cotizacion_numero ? `Cot. ${quoteLabel(lote.cotizacion_numero, null)}` : "—"}
                                                        </span>
                                                        <span className="flex min-w-0 items-center gap-2 text-sm" title={`${lote.producto_resumen} · ${lote.total_registros} registros${lote.cliente_nombre ? ` · Cliente: ${lote.cliente_nombre}` : ""}`}>
                                                            <span className="min-w-0 flex-1 truncate text-slate-900">{lote.producto_resumen}</span>
                                                            {lote.total_registros > 1 && <span className="shrink-0 text-xs text-slate-600">+{lote.total_registros - 1} registros</span>}
                                                            {lote.cliente_nombre && <span className="min-w-0 max-w-60 flex-1 truncate text-xs text-slate-600" title={`Cliente: ${lote.cliente_nombre}`}>{lote.cliente_nombre}</span>}
                                                        </span>
                                                        <span className="text-right text-sm tabular-nums text-slate-700">{lote.total_unidades} u.</span>
                                                    </span>
                                                </div>
                                                <Button variant="ghost" size="icon" disabled={b.downloading !== null} onClick={() => void b.download(lote)} aria-label={`Descargar PDF de ${typeLabel(lote.tipo_movimiento)}, ${lote.fecha_hora_grupo}`} title="PDF de esta operación">
                                                    {b.downloading === String(id) ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileText className="h-4 w-4" />}
                                                </Button>
                                            </div>
                                            {expanded && (
                                                <div id={`bitacora-detail-${id}`} className="mb-4 rounded-md bg-slate-50 p-3 sm:ml-9" aria-busy={detail?.loading}>
                                                    {(lote.cliente_nombre || lote.cotizacion_id || lote.cotizacion_numero || lote.solicitante || lote.estado_solicitud) ? (
                                                        <div className="mb-2.5 flex flex-wrap items-center justify-between gap-2 text-xs bg-white p-2.5 rounded border border-slate-200 shadow-2xs">
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                {lote.cotizacion_id ? (
                                                                    <a
                                                                        href={`/comercial/cotizaciones/${lote.cotizacion_id}`}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="inline-flex items-center gap-1.5 font-bold text-amber-900 bg-amber-100 hover:bg-amber-200 px-2 py-0.5 rounded border border-amber-300 text-xs transition-colors"
                                                                        title={`Abrir Cotización ${quoteLabel(lote.cotizacion_numero, lote.cotizacion_id)} en nueva pestaña`}
                                                                    >
                                                                        <span>Cotización: {quoteLabel(lote.cotizacion_numero, lote.cotizacion_id)}</span>
                                                                        <ExternalLink className="h-3 w-3 opacity-70" />
                                                                    </a>
                                                                ) : lote.cotizacion_numero ? (
                                                                    <span
                                                                        className="inline-flex items-center gap-1.5 font-bold text-amber-900 bg-amber-100 px-2 py-0.5 rounded border border-amber-300 text-xs shadow-2xs"
                                                                        title={`Cotización externa / manual: ${lote.cotizacion_numero}`}
                                                                    >
                                                                        <span>Cotización: {quoteLabel(lote.cotizacion_numero, null)}</span>
                                                                    </span>
                                                                ) : null}
                                                                {lote.cliente_nombre ? (
                                                                    <span className="inline-flex items-center gap-1 text-slate-700">
                                                                        <span className="font-bold text-blue-950">Cliente:</span>
                                                                        <span className="font-semibold text-blue-900">{lote.cliente_nombre}</span>
                                                                    </span>
                                                                ) : null}
                                                                {separateRequester(lote) ? (
                                                                    <span className="inline-flex items-center gap-1 text-slate-600 bg-amber-50/80 px-2 py-0.5 rounded border border-amber-200 text-xs">
                                                                        <span className="font-semibold text-amber-900">Solicitó:</span>
                                                                        <span className="font-medium text-amber-950">{separateRequester(lote)}</span>
                                                                    </span>
                                                                ) : null}
                                                                <span className="inline-flex items-center gap-1 text-slate-600 bg-slate-100 px-2 py-0.5 rounded border border-slate-200 text-xs">
                                                                    <span className="font-semibold text-slate-700">{actorVerb(lote)}:</span>
                                                                    <span className="font-medium text-slate-900">{lote.usuario}</span>
                                                                </span>
                                                                {requestStatus(lote) ? (
                                                                    <span className={`inline-flex items-center px-2 py-0.5 rounded border text-xs font-medium ${requestStatus(lote)!.className}`}>
                                                                        {requestStatus(lote)!.text}
                                                                    </span>
                                                                ) : null}
                                                            </div>
                                                            <span className="text-slate-400 text-[11px] tabular-nums">
                                                                Lote Movimiento #{id}
                                                            </span>
                                                        </div>
                                                    ) : null}
                                                    {detail?.loading ? <p role="status" className="flex items-center gap-2 py-4 text-sm text-slate-600"><Loader2 className="h-4 w-4 animate-spin" />Cargando productos…</p> : detail?.error ? (
                                                        <div role="alert" className="flex items-center gap-3 text-sm text-red-700">{detail.error}<Button variant="outline" size="sm" onClick={() => void b.fetchDetails(id, detail.requestedPage)}>Reintentar</Button></div>
                                                    ) : detail?.page ? <>
                                                        <div className="overflow-x-auto">
                                                            <table className="w-full min-w-[640px] text-sm">
                                                                <caption className="sr-only">Productos y cambio de stock de la operación</caption>
                                                                <thead>
                                                                    <tr className="border-b text-left text-xs text-slate-600">
                                                                        <th scope="col" className="py-2 pr-3">Producto</th>
                                                                        <th scope="col" className="px-2">Cotización</th>
                                                                        <th scope="col" className="px-2">SKU</th>
                                                                        <th scope="col" className="px-2">Color</th>
                                                                        <th scope="col" className="px-2">Talla</th>
                                                                        <th scope="col" className="px-2 text-right">Unidades</th>
                                                                        <th scope="col" className="px-2 text-right">Anterior</th>
                                                                        <th scope="col" className="px-2 text-right">Nuevo</th>
                                                                        <th scope="col" className="pl-2 text-right">Cambio disponible</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>{detail.page.data.map(item => {
                                                                    const delta = item.saldo_nuevo - item.saldo_anterior
                                                                    return (
                                                                        <tr key={item.id} className="border-b border-slate-200 last:border-0">
                                                                            <td className="max-w-96 py-2 pr-3 text-slate-900">{item.producto}</td>
                                                                            <td className="px-2">
                                                                                {item.cotizacion_id ? (
                                                                                    <a
                                                                                        href={`/comercial/cotizaciones/${item.cotizacion_id}`}
                                                                                        target="_blank"
                                                                                        rel="noopener noreferrer"
                                                                                        className="inline-flex items-center gap-1 font-bold text-amber-900 bg-amber-100 hover:bg-amber-200 hover:text-amber-950 px-1.5 py-0.5 rounded border border-amber-300 text-[11px] leading-none shrink-0 transition-colors"
                                                                                        title={`Abrir Cotización ${quoteLabel(item.cotizacion_numero, item.cotizacion_id)} en nueva pestaña`}
                                                                                    >
                                                                                        <span>Cot. {quoteLabel(item.cotizacion_numero, item.cotizacion_id)}</span>
                                                                                        <ExternalLink className="h-2.5 w-2.5 opacity-70" />
                                                                                    </a>
                                                                                ) : item.cotizacion_numero ? (
                                                                                    <span
                                                                                        className="inline-flex items-center gap-1 font-bold text-amber-900 bg-amber-100 px-1.5 py-0.5 rounded border border-amber-300 text-[11px] leading-none shrink-0 shadow-2xs"
                                                                                        title={`Cotización externa / manual: ${item.cotizacion_numero}`}
                                                                                    >
                                                                                        <span>Cot. {quoteLabel(item.cotizacion_numero, null)}</span>
                                                                                    </span>
                                                                                ) : (
                                                                                    <span className="text-slate-400 text-xs">-</span>
                                                                                )}
                                                                            </td>
                                                                            <td className="whitespace-nowrap px-2 text-xs tabular-nums text-slate-600">{item.sku}</td>
                                                                            <td className="px-2 text-slate-700">{item.color}</td>
                                                                            <td className="px-2 text-slate-700">{item.talla}</td>
                                                                            <td className="px-2 text-right tabular-nums">{item.cantidad}</td>
                                                                            <td className="px-2 text-right tabular-nums">{item.saldo_anterior}</td>
                                                                            <td className="px-2 text-right tabular-nums">{item.saldo_nuevo}</td>
                                                                            <td className={`pl-2 text-right font-medium tabular-nums ${delta < 0 ? "text-red-700" : delta > 0 ? "text-emerald-700" : "text-slate-600"}`}>{delta > 0 ? "+" : ""}{delta}{delta === 0 ? " (sin cambio)" : ""}</td>
                                                                        </tr>
                                                                    )
                                                                })}</tbody>
                                                            </table>
                                                        </div>
                                                        <p className="mt-2 text-xs text-slate-600">El despacho de productos ya reservados no vuelve a descontar stock disponible.</p>
                                                        {detail.page.pagination.last_page > 1 && <div className="mt-3 flex items-center justify-between gap-2 text-xs text-slate-600"><span>Productos: página {detail.page.pagination.current_page} de {detail.page.pagination.last_page}</span><div className="flex gap-2"><Button variant="outline" size="sm" disabled={detail.page.pagination.current_page <= 1} onClick={() => void b.fetchDetails(id, detail.page!.pagination.current_page - 1)}>Anteriores</Button><Button variant="outline" size="sm" disabled={detail.page.pagination.current_page >= detail.page.pagination.last_page} onClick={() => void b.fetchDetails(id, detail.page!.pagination.current_page + 1)}>Siguientes</Button></div></div>}
                                                    </> : null}
                                                </div>
                                            )}
                                        </div>
                                    )
                                })}
                            </div>
                        </DaySection>
                            ))}
                        </section>
                    ))}
                </div>
                <div className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t px-5 py-3 text-sm text-slate-600 sm:px-6">
                    <p aria-live="polite">{b.loading ? "Consultando…" : b.error ? "Consulta no disponible" : `${b.pagination.total} operaciones · Página ${b.pagination.current_page} de ${b.pagination.last_page}`}</p>
                    <div className="flex gap-2"><Button variant="outline" size="sm" disabled={b.loading || !!b.error || b.pagination.current_page <= 1} onClick={() => b.goToPage(b.pagination.current_page - 1)}>Anterior</Button><Button variant="outline" size="sm" disabled={b.loading || !!b.error || b.pagination.current_page >= b.pagination.last_page} onClick={() => b.goToPage(b.pagination.current_page + 1)}>Siguiente</Button></div>
                </div>
            </DialogContent>
        </Dialog>
    )
}
