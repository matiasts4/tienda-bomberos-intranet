"use client"

import {
  DndContext,
  DragOverlay,
  PointerSensor,
  useSensor,
  useSensors,
  closestCorners,
  type DragEndEvent,
  type DragStartEvent,
} from "@dnd-kit/core"
import { type ReactNode, useState, default as React } from "react"
import { KanbanCard, KanbanCardContent } from "@/components/kanban-card"
import { StatusChangeConfirmDialog } from "@/components/status-change-confirm-dialog"
import { WorkshopAssignmentDialog } from "@/components/workshop-assignment-dialog"
import { QualityControlDialog } from "@/components/quality-control-dialog"
import type { Order } from "@/lib/mock-data"
import { useOrdersStore } from "@/hooks/use-orders-store"
import { getStatusFromId } from "@/lib/dnd-utils"
import { packagesAPI } from "@/lib/api/packages"

interface DndProviderProps {
  children: ReactNode
  allOrders?: Order[] // Órdenes adicionales a considerar (ej: órdenes de corte)
  onOrderUpdate?: (orderId: string, newStatus: string) => Promise<void> // Callback para actualizar órdenes de corte
  onPackageUpdate?: (packageId: string, newStatus: string, extraData?: any) => Promise<void> // Callback para actualizar paquetes
}

export const DndActionsContext = React.createContext<{
  openQcDialog: (packageId: string, packageCode: string, items: any[]) => void
} | null>(null)

export function useDndActions() {
  const context = React.useContext(DndActionsContext)
  if (!context) {
    throw new Error("useDndActions must be used within a DndProvider")
  }
  return context
}

export function DndProvider({ children, allOrders = [], onOrderUpdate, onPackageUpdate }: DndProviderProps) {
  const [activeCard, setActiveCard] = useState<Order | null>(null)
  const [qcDialog, setQcDialog] = useState<{
    open: boolean
    packageId: string
    packageCode: string
    items: any[]
  }>({
    open: false,
    packageId: "",
    packageCode: "",
    items: []
  })
  const [confirmDialog, setConfirmDialog] = useState<{
    open: boolean
    orderId: string
    currentStatus: string
    newStatus: string
    orderCode: string
  }>({
    open: false,
    orderId: "",
    currentStatus: "",
    newStatus: "",
    orderCode: ""
  })

  const [workshopDialog, setWorkshopDialog] = useState<{
    open: boolean
    orderId: string
    orderCode: string
    client: string
    product: string
    order?: {
      fabricType?: string
      color?: string
      size?: string
      quantity?: number
    }
  }>({
    open: false,
    orderId: "",
    orderCode: "",
    client: "",
    product: "",
    order: undefined
  })

  const { orders: storeOrders, updateOrderStatus, updateOrderWithWorkshop } = useOrdersStore()

  // Combinar órdenes del store con órdenes adicionales (ej: órdenes de corte)
  const orders = [...storeOrders, ...allOrders]

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    }),
  )

  const handleDragStart = (event: DragStartEvent) => {
    const { active } = event
    console.log('Drag started:', active.id)
    const order = orders.find((o) => o.id === active.id)
    console.log('Found order:', order)
    if (order) {
      setActiveCard(order)
    }
  }

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event
    console.log('Drag ended:', { activeId: active.id, overId: over?.id })
    setActiveCard(null)

    if (!over) {
      console.log('No drop target')
      return
    }

    const orderId = active.id as string
    const targetColumnId = over.id as string

    console.log('Mapping column ID to status:', targetColumnId)
    const newStatus = getStatusFromId(targetColumnId)
    console.log('New status:', newStatus)
    if (!newStatus) return

    // Encontrar la orden actual
    const currentOrder = orders.find(order => order.id === orderId)
    console.log('Current order:', currentOrder)
    if (!currentOrder) return

    // Si el estado no cambia, no hacer nada
    if (currentOrder.status === newStatus) {
      console.log('Status unchanged, skipping')
      return
    }

    console.log(`Changing order ${currentOrder.code} from ${currentOrder.status} to ${newStatus}`)

    // Si se mueve a "sewing" (confección), mostrar diálogo de taller
    if (newStatus === "sewing") {
      setWorkshopDialog({
        open: true,
        orderId,
        orderCode: currentOrder.code || currentOrder.id,
        client: currentOrder.client,
        product: currentOrder.product || (currentOrder.products && currentOrder.products[0]) || "Producto no especificado",
        order: {
          fabricType: currentOrder.fabricType,
          color: currentOrder.color,
          size: currentOrder.size,
          quantity: currentOrder.quantity
        }
      })
      return
    }

    // CAMBIO: Si se mueve a "quality-control", YA NO ABRIR DIÁLOGO AUTOMÁTICAMENTE
    // Solo permitir el movimiento. El diálogo se abre con click.
    /*
    if (newStatus === "quality-control" && (orderId.startsWith('package-') || orderId.startsWith('pkg-'))) {
      setQcDialog({
        open: true,
        packageId: orderId,
        packageCode: currentOrder.code || 'N/A',
        items: currentOrder.items || []
      })
      return
    }
    */

    // Para otros estados, mostrar diálogo de confirmación normal
    setConfirmDialog({
      open: true,
      orderId,
      currentStatus: currentOrder.status,
      newStatus,
      orderCode: currentOrder.code || currentOrder.id
    })
  }

  const handleConfirmStatusChange = async () => {
    const orderId = confirmDialog.orderId
    const newStatus = confirmDialog.newStatus

    // Verificar tipo de item
    const isPackage = orderId.startsWith('package-') || orderId.startsWith('pkg-')
    const order = orders.find(o => o.id === orderId)
    const isCuttingOrder = order?.code?.match(/^\d+$/)

    if (isPackage && onPackageUpdate) {
      // Es un paquete, usar callback de paquetes
      try {
        await onPackageUpdate(orderId, newStatus)
      } catch (error) {
        console.error('Error updating package status:', error)
      }
    } else if (isCuttingOrder && onOrderUpdate) {
      // Es una orden de corte, usar el callback de ordenes
      try {
        await onOrderUpdate(orderId, newStatus)
      } catch (error) {
        console.error('Error updating cutting order:', error)
      }
    } else {
      // Es una orden local, usar el store
      updateOrderStatus(orderId, newStatus as any)
    }

    setConfirmDialog(prev => ({ ...prev, open: false }))
  }

  const handleCancelStatusChange = () => {
    setConfirmDialog(prev => ({ ...prev, open: false }))
  }

  const handleConfirmWorkshopAssignment = async (workshopData: { workshop: string; notes?: string }) => {
    const orderId = workshopDialog.orderId
    const isPackage = orderId.startsWith('package-') || orderId.startsWith('pkg-')

    if (isPackage && onPackageUpdate) {
      try {
        await onPackageUpdate(orderId, 'sewing', {
          workshop: workshopData.workshop,
          workshopNotes: workshopData.notes
        })
      } catch (error) {
        console.error('Error updating package workshop:', error)
      }
    } else {
      updateOrderWithWorkshop(orderId, workshopData)
    }

    setWorkshopDialog(prev => ({ ...prev, open: false }))
  }

  const handleCancelWorkshopAssignment = () => {
    setWorkshopDialog(prev => ({ ...prev, open: false }))
  }

  const handleConfirmQualityControl = async (wasteItems: { id: number; quantity: number }[], notes: string) => {
    const packageId = qcDialog.packageId
    const realId = packageId.replace('package-', '').replace('pkg-', '')

    try {
      // Llamar a la API directamente o a través del callback
      // El callback onPackageUpdate es generico, pero aquí necesitamos pasar wasteItems
      // Opción 1: Modificar onPackageUpdate para aceptar data extra flexible
      // Opción 2: Llamar la API directo aquí (importando packagesAPI)

      console.log('Registrando QC:', { realId, wasteItems, notes })
      await packagesAPI.registerQualityControl(realId, wasteItems, notes)

      // Disparar update general para refrescar la UI
      if (onPackageUpdate) {
        // NOTA: El backend ahora mueve a 'finished', así que aquí deberíamos refrescar o simular el cambio
        await onPackageUpdate(packageId, 'finished', { refreshOnly: true })
      }
    } catch (error) {
      console.error('Error in QC registration:', error)
    }

    setQcDialog(prev => ({ ...prev, open: false }))
  }

  const openQcDialog = (packageId: string, packageCode: string, items: any[]) => {
    setQcDialog({
      open: true,
      packageId,
      packageCode,
      items
    })
  }

  return (
    <DndActionsContext.Provider value={{ openQcDialog }}>
      <DndContext
        sensors={sensors}
        collisionDetection={closestCorners}
        onDragStart={handleDragStart}
        onDragEnd={handleDragEnd}
      >
        {children}
        <DragOverlay dropAnimation={null}>
          {activeCard ? (
            <KanbanCard order={activeCard} isDragging>
              <KanbanCardContent order={activeCard} />
            </KanbanCard>
          ) : null}
        </DragOverlay>
      </DndContext>

      <StatusChangeConfirmDialog
        open={confirmDialog.open}
        onOpenChange={handleCancelStatusChange}
        onConfirm={handleConfirmStatusChange}
        orderCode={confirmDialog.orderCode}
        currentStatus={confirmDialog.currentStatus}
        newStatus={confirmDialog.newStatus}
      />

      <WorkshopAssignmentDialog
        open={workshopDialog.open}
        onOpenChange={handleCancelWorkshopAssignment}
        onConfirm={handleConfirmWorkshopAssignment}
        orderCode={workshopDialog.orderCode}
        client={workshopDialog.client}
        product={workshopDialog.product}
        order={workshopDialog.order}
      />

      <QualityControlDialog
        open={qcDialog.open}
        onOpenChange={(open) => setQcDialog(prev => ({ ...prev, open }))}
        onConfirm={handleConfirmQualityControl}
        packageCode={qcDialog.packageCode}
        items={qcDialog.items}
      />
    </DndActionsContext.Provider>
  )
}
