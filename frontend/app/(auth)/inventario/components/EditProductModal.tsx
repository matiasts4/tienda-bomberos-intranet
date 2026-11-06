"use client"

import { useRef, useState } from "react"
import { Archive, Download, Loader2, Save, X } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Dialog, DialogContent, DialogDescription, DialogTitle } from "@/components/ui/dialog"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from "@/components/ui/alert-dialog"
import type { SkuEditor } from "../hooks/useSkuEditor"
import { actorName, actorRole, availableDelta, historyDate, movementLabel, quoteText, type SkuMovement } from "../lib/sku-history"

export function EditProductModal({ editor }: { editor: SkuEditor }) {
  const [tab, setTab] = useState("editar")
  const [confirmation, setConfirmation] = useState<"archive" | "discard" | null>(null)
  const returnFocus = useRef(typeof document === "undefined" ? null : document.activeElement as HTMLElement | null)
  const { session, formData, updating, dirty } = editor
  if (!session) return null
  const requestClose = () => { if (!updating) { if (dirty) setConfirmation("discard"); else editor.close() } }
  const canSave = dirty && !updating && Object.keys(editor.fieldErrors).length === 0

  return (
    <Dialog open onOpenChange={open => { if (!open) requestClose() }}>
      <DialogContent showCloseButton={false}
        className={`flex max-h-[92dvh] w-[calc(100%-2rem)] flex-col gap-0 overflow-hidden p-0 ${tab === "historial" ? "sm:max-w-[1200px]" : "sm:max-w-2xl"}`}
        onEscapeKeyDown={event => { if (updating || confirmation) event.preventDefault() }}
        onInteractOutside={event => event.preventDefault()}
        onCloseAutoFocus={event => { event.preventDefault(); if (returnFocus.current?.isConnected) returnFocus.current.focus() }}>
        <header className="shrink-0 border-b bg-slate-50 px-5 py-4">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <DialogTitle className="text-lg leading-snug text-slate-900">{session.product.producto}</DialogTitle>
              <DialogDescription className="mt-1 text-sm text-slate-600">SKU {session.product.sku}</DialogDescription>
            </div>
            <Button type="button" size="icon" variant="ghost" aria-label="Cerrar edición de SKU" disabled={updating} onClick={requestClose} className="h-11 w-11 shrink-0"><X className="h-5 w-5" /></Button>
          </div>
        </header>
        <form onSubmit={event => { event.preventDefault(); void editor.save() }} noValidate className="flex min-h-0 flex-1 flex-col">
          <Tabs value={tab} onValueChange={setTab} className="flex min-h-0 flex-1 flex-col gap-0">
            <div className="shrink-0 border-b px-5 py-2"><TabsList className="grid h-11 w-full grid-cols-2"><TabsTrigger value="editar">Datos y stock</TabsTrigger><TabsTrigger value="historial">Historial</TabsTrigger></TabsList></div>
            <div className="min-h-0 flex-1 overflow-y-auto">
              <TabsContent value="editar" className="m-0 space-y-6 p-5">
                <div>
                  <label htmlFor="sku-sale-price" className="mb-2 block text-sm font-semibold text-slate-800">Precio de venta ($)</label>
                  <Input id="sku-sale-price" type="number" min={0} step={1} inputMode="numeric" value={formData.precio_venta} disabled={updating}
                    onChange={event => editor.setFormData({ ...formData, precio_venta: event.target.value })}
                    onFocus={event => event.target.select()} aria-invalid={!!editor.fieldErrors.precio_venta} aria-describedby={editor.fieldErrors.precio_venta ? "sku-price-error" : undefined} className="h-11" />
                  {editor.fieldErrors.precio_venta && <p id="sku-price-error" className="mt-1 text-sm text-red-700">{editor.fieldErrors.precio_venta}</p>}
                </div>
                <section className="border-t pt-5" aria-label="Stock actual">
                  <h3 className="mb-3 text-sm font-semibold text-slate-800">Stock actual</h3>
                  <dl className="grid grid-cols-3 gap-3">
                    {[['Disponible', Number(formData.cantidad)], ['Reservado', Number(session.product.reserved_stock ?? 0)], ['Físico total', Number(formData.cantidad) + Number(session.product.reserved_stock ?? 0)]].map(([label, value]) => <div key={label} className="min-w-0"><dt className="text-sm text-slate-600">{label}</dt><dd className="mt-1 text-xl font-semibold tabular-nums text-slate-900">{value}</dd></div>)}
                  </dl>
                  <p className="mt-3 text-sm text-slate-600">Valores al abrir. Físico total = disponible + reservado. Modifica las existencias desde los ajustes de inventario.</p>
                </section>
                <div>
                  <label htmlFor="sku-critical-stock" className="mb-2 block text-sm font-semibold text-slate-800">Mínimo de stock disponible</label>
                  <Input id="sku-critical-stock" type="number" min={0} step={1} inputMode="numeric" value={formData.stock_critico} disabled={updating}
                    onChange={event => editor.setFormData({ ...formData, stock_critico: event.target.value })}
                    onFocus={event => event.target.select()} aria-invalid={!!editor.fieldErrors.stock_critico} aria-describedby={`sku-critical-help${editor.fieldErrors.stock_critico ? " sku-critical-error" : ""}`} className="h-11" />
                  <p id="sku-critical-help" className="mt-1 text-sm text-slate-600">Activa la alerta cuando el disponible llega a este mínimo.</p>
                  {editor.fieldErrors.stock_critico && <p id="sku-critical-error" className="mt-1 text-sm text-red-700">{editor.fieldErrors.stock_critico}</p>}
                </div>
              </TabsContent>
              <TabsContent value="historial" className="m-0 p-4 sm:p-5">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                  <p className="text-sm text-slate-600">Un registro por movimiento. Los saldos muestran el stock disponible.</p>
                  <Button type="button" variant="outline" disabled={editor.downloading || updating || editor.loadingHistory || !!editor.historyError || editor.history.length === 0} onClick={() => void editor.download()} className="h-11 gap-2">{editor.downloading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}Descargar PDF</Button>
                </div>
                {editor.loadingHistory ? <p role="status" className="flex items-center justify-center gap-2 py-12 text-sm text-slate-600"><Loader2 className="h-5 w-5 animate-spin" />Cargando historial…</p>
                  : editor.historyError ? <div role="alert" className="space-y-3 py-6"><p className="text-sm text-red-700">{editor.historyError}</p><Button type="button" variant="outline" onClick={() => void editor.retryHistory()}>Reintentar</Button></div>
                  : editor.history.length === 0 ? <p className="py-12 text-center text-sm text-slate-600">Este SKU todavía no tiene movimientos registrados.</p>
                  : <div>
                    <div aria-hidden className="hidden grid-cols-[110px_140px_minmax(120px,1.3fr)_minmax(100px,1fr)_75px_75px_75px] gap-3 border-b bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600 lg:grid"><span>Fecha y hora</span><span>Movimiento</span><span>Cotización / Cliente</span><span>Responsable</span><span className="text-right">Saldo original</span><span className="text-right">Cant. ajuste</span><span className="text-right">Saldo final</span></div>
                    {editor.history.map(mov => <MovementRow key={mov.id} mov={mov} />)}
                  </div>}
              </TabsContent>
            </div>
          </Tabs>
          {editor.error && <p role="alert" className="shrink-0 border-t bg-red-50 px-5 py-3 text-sm text-red-800">{editor.error}</p>}
          {tab === "historial" && Object.keys(editor.fieldErrors).length > 0 && <p className="shrink-0 border-t px-5 py-2 text-sm text-red-700">Hay campos por corregir en Datos y stock.</p>}
          <footer className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t bg-white p-4">
            <Button type="button" variant="ghost" disabled={updating} onClick={() => setConfirmation("archive")} className="h-11 gap-2 text-red-700 hover:bg-red-50 hover:text-red-800"><Archive className="h-4 w-4" />Archivar</Button>
            <div className="flex flex-wrap items-center gap-2">
              {!dirty && <span className="hidden text-xs text-slate-600 sm:inline">Sin cambios pendientes</span>}
              <Button type="button" variant="outline" disabled={updating} onClick={requestClose} className="h-11">Cancelar</Button>
              <Button type="submit" disabled={!canSave} className="h-11 gap-2">{updating ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}Guardar cambios</Button>
            </div>
          </footer>
        </form>
        <AlertDialog open={confirmation !== null} onOpenChange={open => { if (!open && !updating) setConfirmation(null) }}>
          <AlertDialogContent onEscapeKeyDown={event => { if (updating) event.preventDefault() }}>
            <AlertDialogHeader>
              <AlertDialogTitle>{confirmation === "archive" ? "¿Archivar esta variante?" : "¿Descartar los cambios?"}</AlertDialogTitle>
              <AlertDialogDescription>{session.product.producto} · SKU {session.product.sku}. {confirmation === "archive" ? "Dejará de aparecer en el inventario activo y en nuevas reservas. Su stock e historial se conservarán. Los cambios pendientes de edición no se guardarán." : "Los cambios que hiciste en el precio o el mínimo de stock no se guardarán."}</AlertDialogDescription>
            </AlertDialogHeader>
            {editor.error && <p role="alert" className="text-sm text-red-700">{editor.error}</p>}
            <AlertDialogFooter>
              <AlertDialogCancel disabled={updating}>Volver</AlertDialogCancel>
              <AlertDialogAction disabled={updating} onClick={event => { event.preventDefault(); if (confirmation === "archive") void editor.archive(); else editor.close() }} className="bg-red-700 text-white hover:bg-red-800">{updating && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}{confirmation === "archive" ? "Archivar variante" : "Descartar cambios"}</AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </DialogContent>
    </Dialog>
  )
}

function MovementRow({ mov }: { mov: SkuMovement }) {
  const delta = availableDelta(mov)
  const badgeColor = delta < 0 ? "bg-red-100 text-red-700" : delta > 0 ? "bg-emerald-100 text-emerald-700" : "bg-slate-100 text-slate-700"
  const adjustmentColor = delta < 0 ? "text-red-700" : delta > 0 ? "text-emerald-700" : "text-slate-600"
  return <article aria-label={`${movementLabel(mov.tipo_movimiento)}, ${quoteText(mov)}`} className="border-b last:border-0">
    <div className="grid grid-cols-2 items-start gap-3 px-3 py-3 text-sm lg:grid-cols-[110px_140px_minmax(120px,1.3fr)_minmax(100px,1fr)_75px_75px_75px]">
      <time className="text-slate-600">{historyDate(mov.fecha_hora)}</time>
      <span className={`w-fit max-w-full break-all rounded px-2 py-1 text-[11px] font-semibold leading-snug ${badgeColor}`}>{mov.tipo_movimiento}</span>
      <div className="col-span-2 min-w-0 break-words lg:col-span-1">
        {mov.cotizacion_id && mov.cotizacion_disponible !== false ? <a href={`/comercial/cotizaciones/${mov.cotizacion_id}`} target="_blank" rel="noopener noreferrer" className="font-medium text-blue-700 underline underline-offset-2">{quoteText(mov)}<span className="sr-only"> (abre en otra pestaña)</span></a> : <span className="font-medium text-slate-800">{quoteText(mov)}</span>}
        {mov.cotizacion_id && mov.cotizacion_disponible === false && <p className="text-xs text-slate-600">Cotización no disponible</p>}
        <p className="mt-1 text-xs text-slate-600">{mov.cliente_mostrado || mov.cliente_nombre || "Cliente no registrado"}</p>
      </div>
      <div className="col-span-2 min-w-0 break-words text-slate-800 lg:col-span-1"><span className="block text-xs text-slate-600">{actorRole(mov)}</span>{actorName(mov)}</div>
      <dl className="col-span-2 grid grid-cols-3 gap-3 tabular-nums lg:contents">
        <div className="text-right text-slate-800"><dt className="text-xs text-slate-600 lg:sr-only">Saldo original</dt><dd>{mov.saldo_anterior}</dd></div>
        <div className={`text-right font-semibold ${adjustmentColor}`}><dt className="text-xs font-normal text-slate-600 lg:sr-only">Cant. ajuste</dt><dd title={delta === 0 ? "Sin cambio en el stock disponible" : undefined}>{delta > 0 ? "+" : ""}{delta}</dd></div>
        <div className="text-right text-slate-800"><dt className="text-xs text-slate-600 lg:sr-only">Saldo final</dt><dd>{mov.saldo_nuevo}</dd></div>
      </dl>
    </div>
    <details className="px-3 pb-3 text-sm text-slate-700">
      <summary className="w-fit cursor-pointer rounded py-1 text-xs font-medium text-blue-700 focus-visible:outline-2 focus-visible:outline-blue-600">Ver detalle del movimiento #{mov.id}</summary>
      <dl className="mt-2 grid gap-x-6 gap-y-2 rounded-md bg-slate-50 p-3 sm:grid-cols-2">
        {[
          ["Cotización", mov.cotizacion_nombre], ["Solicitó", mov.solicitante],
          ["Solicitud", mov.peticion_id ? `ID ${mov.peticion_id}` : null], ["Estado actual de solicitud", mov.estado_solicitud_actual],
          [mov.resolucion_tipo === "ANULACION_RESERVA" ? "Rechazó" : "Aprobó", mov.resuelto_por ? `${mov.resuelto_por}${mov.resuelto_en ? ` · ${historyDate(mov.resuelto_en)}` : ""}` : null],
          ["Observación del movimiento", mov.observacion], ["Observación de solicitud", mov.observacion_solicitud],
          ["Unidades del movimiento", String(mov.cantidad)], ["Lote", mov.id_lote], ["Código de movimiento", mov.tipo_movimiento],
          ["Referencias inconsistentes", mov.referencia_inconsistente ? `Revisar los vínculos guardados. Referencia externa: ${mov.referencia_externa || "—"}` : null],
        ].filter(([, value]) => !!value).map(([label, value]) => <div key={label} className="min-w-0 break-words"><dt className="text-xs font-medium text-slate-600">{label}</dt><dd className="mt-0.5 whitespace-pre-wrap text-slate-900">{value}</dd></div>)}
      </dl>
    </details>
  </article>
}
