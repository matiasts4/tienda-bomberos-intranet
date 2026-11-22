"use client"

import { memo, useMemo, useState, useCallback } from "react"
import { useDraggable } from "@dnd-kit/core"
import { cn } from "@/lib/utils"
import { Building2, Tag, Calendar, Printer, ChevronDown, ChevronRight, Clock, Package } from "lucide-react"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog"
import { api } from "@/lib/api"

type DespachoItem = {
  id: number
  cotizacion: any
  despacho: any
  estado_despacho: any
  cliente: any
  ejecutivo: any
  bloqueo_despacho?: string | null
  componentes_kit?: { item_id: number; kit: string; composicion: string; sku: string | null; producto: string; talla: string; color: string | null; cantidad: number }[]
}

type Props = {
  item: DespachoItem
}

function formatCLP(value?: number | null) {
  const n = typeof value === "number" ? value : 0
  return new Intl.NumberFormat("es-CL", { style: "currency", currency: "CLP", maximumFractionDigits: 0 }).format(n)
}

function formatDateDMY(dateStr?: string | null) {
  if (!dateStr) return null
  const d = new Date(dateStr)
  if (Number.isNaN(d.getTime())) return null
  return d.toLocaleDateString("es-CL")
}

export const DespachoKanbanCard = memo(function DespachoKanbanCard({ item }: Props) {
  const [expandedItems, setExpandedItems] = useState<Set<number>>(new Set())

  const code = useMemo(() => `CT-${String(item.cotizacion.id).padStart(4, "0")}`, [item.cotizacion.id])
  const titulo = item.cotizacion.numero ? item.cotizacion.numero : code

  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: item.id,
    data: { item },
    disabled: Boolean(item.bloqueo_despacho),
  })

  const style = useMemo(() => transform ? ({ transform: `translate3d(${transform.x}px, ${transform.y}px, 0)` }) : undefined, [transform])

  const nombreEmpresa = item.cotizacion.nombre_cliente || item.cliente?.nombre_empresa || `Cotización #${item.cotizacion.id}`
  const detalles = item.cotizacion.detalles || []
  const plazoProduccion = item.cotizacion.plazo_produccion

  const toggleItem = useCallback((idx: number) => {
    setExpandedItems(prev => {
      const next = new Set(prev)
      if (next.has(idx)) next.delete(idx)
      else next.add(idx)
      return next
    })
  }, [])

  return (
    <Dialog>
      <DialogTrigger asChild>
        <div
          ref={setNodeRef}
          style={style}
          {...listeners}
          {...attributes}
          className={cn(
            "rounded-xl border bg-white p-4 shadow-sm hover:border-primary/50 hover:shadow-md transition-all cursor-pointer relative overflow-hidden flex flex-col justify-between min-h-[120px]",
            isDragging && "opacity-70 shadow-lg ring-2 ring-muted"
          )}
        >
          {/* ── Header ── */}
          <div className="flex flex-col min-w-0 flex-1">
            <div className="flex items-center justify-between gap-2">
              <span className="text-sm font-bold text-red-600 whitespace-nowrap">{titulo}</span>
              {item.cotizacion.fecha_creacion && (
                <span className="text-[11px] text-muted-foreground whitespace-nowrap flex items-center gap-1">
                  <Calendar className="h-3 w-3" />
                  {formatDateDMY(item.cotizacion.fecha_creacion)}
                </span>
              )}
            </div>

          <div className="flex items-center gap-1.5 text-[13px] font-semibold text-slate-800 mt-2">
            <Building2 className="h-3.5 w-3.5 text-slate-400 shrink-0" />
            <span className="truncate">{nombreEmpresa}</span>
          </div>

          {item.cotizacion.nombre && (
            <div className="truncate text-[12px] font-medium text-slate-500 mt-1 pl-5">
              {item.cotizacion.nombre}
            </div>
          )}

          {item.ejecutivo && (
            <div className="truncate text-[11px] text-slate-500 mt-1 pl-5">
              Ejecutivo: {item.ejecutivo.nombre}
            </div>
          )}
        </div>

          {item.bloqueo_despacho && <p className="mt-3 text-sm text-amber-800">{item.bloqueo_despacho}</p>}
          {!!item.componentes_kit?.length && <details className="mt-3 rounded-md border bg-slate-50 p-2 text-xs" onClick={e => e.stopPropagation()} onPointerDown={e => e.stopPropagation()}><summary className="cursor-pointer font-semibold">Prendas físicas de kits ({item.componentes_kit.reduce((n, row) => n + Number(row.cantidad), 0)} unidades)</summary><ul className="mt-2 space-y-2">{item.componentes_kit.map((row, i) => <li key={i}><strong>{row.producto} · {row.cantidad} u.</strong><span className="block text-slate-500">{row.composicion} · {row.color} · {row.talla} · {row.sku || 'SKU pendiente'}</span></li>)}</ul></details>}
          {/* ── Productos desplegables ── */}
          {detalles.length > 0 && (
            <div
              className="mt-3 border-t pt-2 space-y-0.5"
              onClick={(e) => e.stopPropagation()}
              onPointerDown={(e) => e.stopPropagation()}
            >
              <p className="text-[10px] font-semibold text-muted-foreground uppercase tracking-wide mb-1 flex items-center gap-1">
                <Package className="h-3 w-3" />
                Productos ({detalles.length})
              </p>
              {detalles.map((prod: any, idx: number) => {
                const isExpanded = expandedItems.has(idx)
                return (
                  <div key={idx} className="rounded-md overflow-hidden">
                    <button
                      type="button"
                      onClick={(e) => { e.stopPropagation(); e.preventDefault(); toggleItem(idx) }}
                      onPointerDown={(e) => e.stopPropagation()}
                      className={cn(
                        "w-full flex items-center gap-1.5 text-[12px] py-1.5 px-2 rounded-md transition-colors text-left",
                        isExpanded
                          ? "bg-slate-100 text-slate-800"
                          : "hover:bg-slate-50 text-slate-700"
                      )}
                    >
                      {isExpanded
                        ? <ChevronDown className="h-3 w-3 text-slate-500 shrink-0" />
                        : <ChevronRight className="h-3 w-3 text-slate-400 shrink-0" />
                      }
                      <span className="font-medium truncate flex-1">
                        {prod.producto || prod.skuGenerico?.nombre || 'Producto sin nombre'}
                      </span>
                      <span className="font-bold text-emerald-600 shrink-0 ml-1">
                        {prod.cantidad} u.
                      </span>
                    </button>

                    {isExpanded && (
                      <div className="ml-5 pl-2.5 border-l-2 border-slate-200 py-1.5 space-y-1 animate-in slide-in-from-top-1 duration-150">
                        {(prod.talla || prod.color) && (
                          <p className="text-[11px] text-slate-500">
                            {prod.talla && <span>Talla: <strong>{prod.talla}</strong> </span>}
                            {prod.color && <span>Color: <strong>{prod.color}</strong></span>}
                          </p>
                        )}
                        <p className="text-[11px] text-muted-foreground">
                          Subtotal: <strong>{formatCLP(prod.total_neto || (prod.precio_unitario * prod.cantidad))}</strong>
                        </p>
                        {plazoProduccion && (
                          <p className="flex items-center gap-1 text-[11px] text-amber-700 font-semibold bg-amber-50 rounded px-1.5 py-0.5 w-fit">
                            <Clock className="h-3 w-3" />
                            SLA: {plazoProduccion}
                          </p>
                        )}
                      </div>
                    )}
                  </div>
                )
              })}
            </div>
          )}

          {/* ── Total ── */}
          <div className="mt-3 pt-3 border-t flex items-center justify-between">
            <span className="text-xs font-medium text-muted-foreground flex items-center gap-1">
              <Tag className="h-3.5 w-3.5" /> Total
            </span>
            <span className="font-bold text-sm text-foreground">
              {formatCLP(item.cotizacion.total || item.cotizacion.total_neto)}
            </span>
          </div>
        </div>
      </DialogTrigger>
    
      {/* ── Dialog detalle ── */}
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Detalle de Productos - {titulo}</DialogTitle>
        </DialogHeader>
        <div className="mt-4 space-y-4">
          <div className="grid grid-cols-2 gap-4 text-sm bg-slate-50 p-3 rounded border">
            <div>
              <p className="text-muted-foreground">Cliente</p>
              <p className="font-semibold">{nombreEmpresa}</p>
            </div>
            <div>
              <p className="text-muted-foreground">Total</p>
              <p className="font-semibold">{formatCLP(item.cotizacion.total || item.cotizacion.total_neto)}</p>
            </div>
          </div>
          
          {item.estado_despacho?.nombre === 'Listo para despacho' && (
            <div className="flex justify-end">
              <button 
                type="button"
                onClick={() => {
                  const apiUrl = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000"
                  api.downloadPdf(`${apiUrl}/api/despachos/cotizacion/${item.cotizacion.id}/guia`)
                }}
                className="text-sm font-semibold bg-primary text-primary-foreground py-2 px-4 rounded-md hover:bg-primary/90 transition-colors flex items-center gap-2"
              >
                <Printer className="h-4 w-4" />
                Imprimir Guía de Despacho
              </button>
            </div>
          )}
          
          <h4 className="font-bold text-sm mt-4 border-b pb-2">Lista de Productos</h4>
            <div className="max-h-[300px] overflow-y-auto space-y-2 pr-2">
              {detalles.length > 0 ? (
                detalles.map((prod: any, idx: number) => (
                  <div key={idx} className="flex justify-between p-3 border rounded-lg bg-white shadow-sm text-sm">
                    <div>
                      <p className="font-semibold text-slate-800">{prod.producto || prod.skuGenerico?.nombre || 'Producto sin nombre'}</p>
                      {(prod.talla || prod.color) && (
                        <p className="text-xs text-slate-500 mt-1">
                          {prod.talla && <span>Talla: {prod.talla} </span>}
                          {prod.color && <span>Color: {prod.color}</span>}
                        </p>
                      )}
                      {plazoProduccion && (
                        <p className="text-xs text-amber-700 font-medium mt-1 flex items-center gap-1">
                          <Clock className="h-3 w-3" />
                          SLA: {plazoProduccion}
                        </p>
                      )}
                    </div>
                    <div className="text-right">
                      <p className="font-bold text-emerald-600">{prod.cantidad} unid.</p>
                      <p className="text-xs text-muted-foreground mt-1">Subtotal: {formatCLP(prod.total_neto || (prod.precio_unitario * prod.cantidad))}</p>
                    </div>
                  </div>
                ))
              ) : (
                <p className="text-sm text-muted-foreground italic text-center py-4">No hay productos registrados en esta cotización.</p>
              )}
            </div>
            
            {item.despacho?.voucher_url && (
              <div className="mt-4 pt-4 border-t">
                <a 
                  href={`${process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000"}${item.despacho.voucher_url}`} 
                  target="_blank" 
                  rel="noopener noreferrer"
                  className="text-sm text-blue-600 hover:underline flex items-center gap-1 font-medium"
                >
                  <Tag className="h-4 w-4" />
                  Ver Voucher de Entrega adjunto
                </a>
              </div>
            )}
          </div>
        </DialogContent>
    </Dialog>
  )
})
