import { useDraggable } from "@dnd-kit/core"
import { CSS } from "@dnd-kit/utilities"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardHeader } from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import { CuttingOrderDetails } from "@/components/cutting-order-details"
import { Clock, AlertCircle, Package, Building, Trash2, FileText } from "lucide-react"
import type { Order } from "@/lib/mock-data"
import { cn } from "@/lib/utils"
import { ReactNode, useState, useEffect, memo, useMemo, useCallback } from "react"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog"
import { Button } from "@/components/ui/button"

interface KanbanCardProps {
  id?: string
  order: Order
  children?: ReactNode
  className?: string
  isDragging?: boolean
  onDelete?: (orderId: string) => Promise<void>
}

export const KanbanCard = memo(function KanbanCard({ id, order, children, className, isDragging, onDelete }: KanbanCardProps) {
  const [isMounted, setIsMounted] = useState(false)

  useEffect(() => {
    setIsMounted(true)
  }, [])

  // Si es para DragOverlay, no usar sortable
  if (isDragging) {
    return (
      <div className={cn("w-full shrink-0 opacity-90 rotate-2 scale-105", className)}>
        {children || <KanbanCardContent order={order} onDelete={onDelete} />}
      </div>
    )
  }

  // Para el uso normal en el kanban
  const cardId = id || order.id

  // Hook para acciones del contexto (QC Dialog)
  const dndActions = useDndActions()

  // Determinar si esta tarjeta puede ser arrastrada
  // TEMPORALMENTE: permitir que todas las tarjetas sean draggables para probar
  const isCuttingOrder = order.code?.match(/^\d+$/) // Solo números = orden de corte
  const isPackage = order.code?.startsWith('P-') // P-XXX = paquete
  const isDraggableCard = true // TEMPORAL: Todo draggable para debugging

  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    isDragging: isDraggingState,
  } = useDraggable({
    id: cardId,
    disabled: !isMounted || !isDraggableCard,
  })

  const style = useMemo(() => transform ? {
    transform: CSS.Translate.toString(transform),
  } : undefined, [transform])

  if (isDragging) {
    return (
      <div className={cn("w-64 shrink-0 opacity-90 rotate-2 scale-105", className)}>
        {children || <KanbanCardContent order={order} onDelete={onDelete} />}
      </div>
    )
  }

  if (!isMounted) {
    return (
      <div className={cn("w-full shrink-0", className)}>
        {children || <KanbanCardContent order={order} onDelete={onDelete} />}
      </div>
    )
  }

  const handleCardClick = () => {
    // Ya no abrimos el QC Dialog desde aquí para los paquetes.
    // Eso ahora se hace desde PackageDetailsDialog.
  }

  return (
    <div
      ref={setNodeRef}
      className={cn(
        "w-full shrink-0 transition-transform touch-none select-none",
        isDraggableCard
          ? isDraggingState
            ? "opacity-50 z-50 cursor-grabbing border-2 border-blue-500"
            : "cursor-grab hover:cursor-grab border-2 border-transparent hover:border-blue-300"
          : "cursor-default border-2 border-transparent opacity-80", // Órdenes de corte individuales con opacidad reducida
        // Highlight clickable cards in QC (Removed to match behavior)
        className
      )}
      style={style}
      {...attributes}
      {...(isDraggableCard ? listeners : {})} // Solo aplicar listeners si es draggable
      onClick={handleCardClick}
      title={
        isDraggableCard
          ? "Arrastrar para mover entre columnas"
          : isCuttingOrder
            ? "Orden de corte - Se agrupa en paquetes para envío"
            : "Esta orden se puede arrastrar"
      }
    >
      {children || <KanbanCardContent order={order} onDelete={onDelete} />}
    </div>
  )
})

import { PackageDetailsDialog } from "@/components/package-details-dialog"
import { useDndActions } from "./dnd-provider"

const getProductColorClass = (color: string) => {
  const c = color.toLowerCase().trim()
  if (c.includes("azul") || c.includes("blue")) return "bg-blue-500 border-blue-600"
  if (c.includes("rojo") || c.includes("red")) return "bg-red-500 border-red-600"
  if (c.includes("verde") || c.includes("green")) return "bg-green-500 border-green-600"
  if (c.includes("amarillo") || c.includes("yellow")) return "bg-yellow-400 border-yellow-500"
  if (c.includes("naranjo") || c.includes("naranja") || c.includes("orange")) return "bg-orange-500 border-orange-600"
  if (c.includes("negro") || c.includes("black")) return "bg-slate-900 border-slate-700"
  if (c.includes("blanco") || c.includes("white")) return "bg-white border-slate-300"
  if (c.includes("gris") || c.includes("grey")) return "bg-slate-500 border-slate-600"
  if (c.includes("morado") || c.includes("purple")) return "bg-purple-500 border-purple-600"
  if (c.includes("rosa") || c.includes("pink")) return "bg-pink-500 border-pink-600"
  if (c.includes("beige")) return "bg-[#F5F5DC] border-[#E1D4A7]"
  if (c.includes("café") || c.includes("cafe") || c.includes("brown")) return "bg-amber-800 border-amber-900"
  return "bg-slate-200 border-slate-300"
}

export const KanbanCardContent = memo(function KanbanCardContent({
  order,
  className,
  onDelete
}: {
  order: Order,
  className?: string,
  onDelete?: (orderId: string) => Promise<void>
}) {
  const [isDeleting, setIsDeleting] = useState(false)

  const priorityColors = useMemo(() => ({
    high: "bg-red-500/10 text-red-600 border-red-500/20",
    medium: "bg-orange-500/10 text-orange-600 border-orange-500/20",
    low: "bg-gray-500/10 text-gray-600 border-gray-500/20",
  }), [])

  const { isDelayed, completedToday, isCuttingOrder, isPackage } = useMemo(() => {
    const delayed = !!(order.deadline && new Date(order.deadline) < new Date() && order.status !== "finished")
    const completed = !!(order.status === "finished" && order.updatedAt &&
      new Date(order.updatedAt).toDateString() === new Date().toDateString())
    const cutting = !!order.code?.match(/^\d+$/)
    const pkg = !!(order.isPackage || order.code?.startsWith('P-'))

    return { isDelayed: delayed, completedToday: completed, isCuttingOrder: cutting, isPackage: pkg }
  }, [order.deadline, order.status, order.updatedAt, order.code, order.isPackage])

  const handleDelete = useCallback(async () => {
    if (!onDelete || !order.id) return

    setIsDeleting(true)
    try {
      await onDelete(order.id)
    } catch (error) {
      console.error('Error deleting order:', error)
      alert('Error al eliminar la orden. Por favor intente nuevamente.')
    } finally {
      setIsDeleting(false)
    }
  }, [onDelete, order.id])

  const CardHeaderContent = useMemo(() => (
    <div className="flex items-start justify-between gap-2">
      <div className="flex-1">
        {order.originOrderCode && (
          <div className="inline-flex items-center rounded-md bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700 mb-1">
            Orden de Corte #{order.originOrderCode}
          </div>
        )}
        {(order.client?.toLowerCase().includes('reparación') || order.client?.toLowerCase().includes('reparacion')) && (
          <div className="inline-flex items-center rounded-md bg-red-200 px-2 py-1 text-xs font-bold text-red-800 mb-1 ml-2 border border-red-300">
            🔧 REPARACIÓN
          </div>
        )}

        <div className="flex items-center gap-2">
          <p className="text-sm font-semibold text-[#E53935]">
            {order.code || order.id}
          </p>
          {isPackage && (
            <Badge variant="secondary" className="text-xs bg-blue-100 text-blue-800 border-blue-300">
              📦 Paquete
            </Badge>
          )}
          {isCuttingOrder && (
            <Badge variant="outline" className="text-xs bg-amber-50 text-amber-700 border-amber-300">
              ✂️ Corte
            </Badge>
          )}
        </div>
        <p className="text-sm font-medium">
          {order.client === "Orden de Producción Interna" ? "Orden de Corte" : order.client}
        </p>
      </div>
      <div className="flex items-start gap-1">
        {order.priority && (
          <Badge variant="outline" className={cn("text-xs", priorityColors[order.priority as keyof typeof priorityColors])}>
            {order.priority === "high" && "Alta"}
            {order.priority === "medium" && "Media"}
            {order.priority === "low" && "Baja"}
          </Badge>
        )}
        {completedToday && (
          <Badge variant="default" className="text-xs bg-green-600 hover:bg-green-700">
            Hoy ✓
          </Badge>
        )}
        {isCuttingOrder && onDelete && (
          <AlertDialog>
            <AlertDialogTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                className="h-6 w-6 hover:bg-red-100 hover:text-red-600"
                disabled={isDeleting}
                onClick={(e) => e.stopPropagation()}
              >
                <Trash2 className="h-3.5 w-3.5" />
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent onClick={(e) => e.stopPropagation()}>
              <AlertDialogHeader>
                <AlertDialogTitle>¿Eliminar orden de corte?</AlertDialogTitle>
                <AlertDialogDescription>
                  Esta acción no se puede deshacer. Se eliminará permanentemente la orden de corte <strong>{order.code}</strong> del cliente <strong>{order.client}</strong>.
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel onClick={(e) => e.stopPropagation()}>
                  Cancelar
                </AlertDialogCancel>
                <AlertDialogAction
                  onClick={(e) => {
                    e.stopPropagation()
                    handleDelete()
                  }}
                  className="bg-red-600 hover:bg-red-700"
                >
                  {isDeleting ? "Eliminando..." : "Eliminar"}
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        )}
      </div>
    </div>
  ), [order.code, order.id, order.client, order.originOrderCode, order.priority, isPackage, isCuttingOrder, completedToday, onDelete, isDeleting, handleDelete, priorityColors])

  const CardBodyContent = useMemo(() => (
    <div className="space-y-2">
      <div className="flex items-center gap-1.5 text-xs">
        <Package className="size-3 shrink-0" />
        <span className="break-words line-clamp-2">
          {order.productDetails && order.productDetails.length > 0
            ? `${order.productDetails.length} producto${order.productDetails.length > 1 ? 's' : ''}`
            : order.product || (order.products && order.products[0]) || "Producto no especificado"
          }
        </span>
      </div>

      {order.productDetails && order.productDetails.length > 0 && (
        <div className="space-y-1 pl-4 border-l-2 border-blue-200">
          {order.productDetails.slice(0, 2).map((detail) => (
            <div key={detail.id} className="text-xs text-muted-foreground">
              <span className="font-medium">{detail.productType}</span>
              <span className="ml-2">
                {detail.size} • {detail.color} • {detail.quantity} uds
              </span>
            </div>
          ))}
          {order.productDetails.length > 2 && (
            <div className="text-xs text-muted-foreground">
              +{order.productDetails.length - 2} más...
            </div>
          )}
        </div>
      )}

      {(order.fabricType || order.color || order.size) && !isPackage && (
        <div className="space-y-1">
          {order.fabricType && (
            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
              <span className="w-1 h-1 bg-blue-500 rounded-full"></span>
              <span>Tela: {order.fabricType}</span>
            </div>
          )}

          {order.color && (
            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
              <span className={cn("w-2 h-2 rounded-full border", getProductColorClass(order.color))}></span>
              <span>Color: {order.color}</span>
            </div>
          )}
          {order.size && (
            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
              <span className="w-1 h-1 bg-purple-500 rounded-full"></span>
              <span>Talla: {order.size}</span>
            </div>
          )}
        </div>
      )}

      {order.productDetails ? (
        <p className="text-xs text-muted-foreground">
          Total: <span className="font-medium">
            {order.productDetails.reduce((sum, detail) => sum + detail.quantity, 0)} unidades
          </span>
        </p>
      ) : order.quantity ? (
        <p className="text-xs text-muted-foreground">
          Cantidad: <span className="font-medium">{order.quantity} unidades</span>
        </p>
      ) : order.products && order.products.length > 1 ? (
        <p className="text-xs text-muted-foreground">
          {order.products.length} productos
        </p>
      ) : null}

      {order.amount && (
        <p className="text-sm font-semibold text-foreground">
          ${order.amount.toLocaleString("es-CL")}
        </p>
      )}

      {order.workshop && order.status === "sewing" && (
        <div className="flex items-center gap-1.5 text-xs text-blue-600">
          <Building className="size-3" />
          <span>Taller: {order.workshop}</span>
        </div>
      )}

      {order.deadline && (
        <div className={cn(
          "flex items-center gap-1.5 text-xs",
          isDelayed ? "text-red-600" : "text-muted-foreground"
        )}>
          <Clock className="size-3" />
          <span>Vence: {new Date(order.deadline).toLocaleDateString("es-CL")}</span>
          {isDelayed && <AlertCircle className="size-3 text-red-600" />}
        </div>
      )}

      {order.notes && order.notes.length < 50 && (
        <div className="flex items-start gap-1.5 text-xs text-muted-foreground">
          <AlertCircle className="mt-0.5 size-3 flex-shrink-0" />
          <span className="line-clamp-2">{order.notes}</span>
        </div>
      )}

      {/* QC Progress tracking for Packages AND Cutting Orders in quality-control */}
      {order.status === 'quality-control' && (
        <div className="mt-3 pt-3 border-t border-dashed border-gray-200">
          <div className="flex items-center justify-between mb-1 text-xs">
            <span className="font-semibold text-gray-700">Progreso QC</span>
            <span className={cn(
              "font-medium",
              (order.unidades_listas || 0) < (order.total_unidades || 1) ? "text-amber-600" : "text-emerald-600"
            )}>
              {order.unidades_listas || 0} / {order.total_unidades || order.quantity || 0}
            </span>
          </div>
          <Progress
            value={((order.unidades_listas || 0) / (order.total_unidades || order.quantity || 1)) * 100}
            className="h-1.5 mb-2"
          />

          {/* Indicador de unidades en reparación */}
          {(order.unidades_en_reparacion || 0) > 0 && (
            <div className="flex items-center gap-1.5 px-2 py-1.5 rounded-md bg-red-100 border border-red-200">
              <AlertCircle className="size-3.5 text-red-600 flex-shrink-0" />
              <span className="text-xs font-medium text-red-700">
                {order.unidades_en_reparacion} unidad{order.unidades_en_reparacion !== 1 ? 'es' : ''} en reparación
              </span>
            </div>
          )}
        </div>
      )}
    </div>
  ), [order, isPackage, isDelayed])

  // Usar el campo is_repair_package del backend, o fallback al check de texto
  const isReparacion = order.is_repair_package || 
                       order.client?.toLowerCase().includes('reparación') || 
                       order.client?.toLowerCase().includes('reparacion') || 
                       order.notes?.toLowerCase().includes('reparación') ||
                       order.notes?.toLowerCase().includes('reparacion')

  // Verificar si tiene unidades en reparación (tarjetas con prendas enviadas a reparación)
  const hasUnidadesEnReparacion = (order.unidades_en_reparacion || 0) > 0

  // UI constraint logic for highlighting incomplete QC cards (both packages and cutting orders)
  const isIncompleteQc = order.status === 'quality-control' && (order.unidades_listas || 0) < (order.total_unidades || order.quantity || 1);

  const CardComponent = useMemo(() => (
    <Card
      className={cn(
        "cursor-pointer transition-all hover:shadow-md hover:ring-1 hover:ring-primary/20",
        // Prioridad 1: Paquetes de REPARACIÓN (fondo rojo claro para distinguirlos)
        isReparacion && "border-red-300 bg-red-50/80 shadow-sm hover:ring-red-300 ring-1 ring-red-200",
        // Prioridad 2: Tarjetas con unidades pendientes de reparación (más intenso)
        hasUnidadesEnReparacion && !isReparacion && "border-orange-300 bg-orange-50 shadow-sm hover:ring-orange-300",
        // Prioridad 3: QC incompleto sin reparaciones
        isIncompleteQc && !hasUnidadesEnReparacion && !isReparacion && "border-amber-200 bg-amber-50/50 hover:ring-amber-300 shadow-sm",
        className,
      )}
    >
      <CardHeader className="p-4 pb-3">
        {CardHeaderContent}
      </CardHeader>
      <CardContent className="p-4 pt-0">
        {CardBodyContent}
      </CardContent>
    </Card>
  ), [className, isReparacion, CardHeaderContent, CardBodyContent])

  if (isPackage) {
    return (
      <PackageDetailsDialog
        packageData={order}
        trigger={CardComponent}
      />
    )
  }

  return (
    <CuttingOrderDetails
      order={order}
      trigger={CardComponent}
      onDelete={onDelete}
    />
  )
})
