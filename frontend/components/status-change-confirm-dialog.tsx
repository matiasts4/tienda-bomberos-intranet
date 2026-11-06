"use client"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { ArrowRight, Package, AlertCircle } from "lucide-react"

interface StatusChangeConfirmProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onConfirm: () => void
  orderCode: string
  currentStatus: string
  newStatus: string
}

const statusLabels: Record<string, string> = {
  "order-received": "Orden Recibida",
  "design": "Diseño / Patronaje",
  "cutting": "Corte",
  "sewing": "Confección",
  "quality-control": "Control de Calidad",
  "finished": "Terminado",
  "rejected": "Rechazado",
  "lead": "Prospecto",
  "quote-sent": "Cotización Enviada",
  "negotiating": "Negociando",
  "approved": "Aprobado"
}

export function StatusChangeConfirmDialog({
  open,
  onOpenChange,
  onConfirm,
  orderCode,
  currentStatus,
  newStatus
}: StatusChangeConfirmProps) {
  const currentStatusLabel = statusLabels[currentStatus] || currentStatus
  const newStatusLabel = statusLabels[newStatus] || newStatus

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle className="flex items-center gap-2">
            <AlertCircle className="h-5 w-5 text-orange-500" />
            ¿Confirmar cambio de estado?
          </AlertDialogTitle>
          <AlertDialogDescription asChild>
            <div className="space-y-3">
              <p>¿Estás seguro que quieres cambiar el estado de la orden de producción?</p>

              <div className="bg-muted/50 rounded-lg p-4 space-y-3">
                <div className="flex items-center gap-2 text-sm">
                  <Package className="h-4 w-4" />
                  <span className="font-medium">Orden:</span>
                  <span className="font-mono text-[#E53935]">{orderCode}</span>
                </div>

                <div className="flex items-center gap-3 text-sm">
                  <div className="flex items-center gap-2">
                    <span className="font-medium">Estado actual:</span>
                    <span className="px-2 py-1 rounded bg-orange-100 text-orange-700 text-xs font-medium">
                      {currentStatusLabel}
                    </span>
                  </div>

                  <ArrowRight className="h-4 w-4 text-muted-foreground" />

                  <div className="flex items-center gap-2">
                    <span className="font-medium">Nuevo estado:</span>
                    <span className="px-2 py-1 rounded bg-green-100 text-green-700 text-xs font-medium">
                      {newStatusLabel}
                    </span>
                  </div>
                </div>
              </div>

              <p className="text-xs text-muted-foreground flex items-center gap-1">
                <AlertCircle className="h-3 w-3" />
                Esta acción actualizará el progreso de la orden en el sistema de producción.
              </p>
            </div>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancelar</AlertDialogCancel>
          <AlertDialogAction
            onClick={onConfirm}
            className="bg-[#E53935] hover:bg-[#C62828]"
          >
            Confirmar Cambio
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
