"use client"

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import { Building2, Scissors, Users } from "lucide-react"
import { useState } from "react"

interface WorkshopAssignmentDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onConfirm: (workshopData: { workshop: string; notes?: string }) => void
  orderCode: string
  client: string
  product: string
  order?: {
    fabricType?: string
    color?: string
    size?: string
    quantity?: number
  }
}

const workshops = [
  {
    id: "AYLI",
    name: "AYLI",
    description: "Taller AYLI",
    capacity: "N/A",
    icon: Users
  },
  {
    id: "MAICOL",
    name: "MAICOL",
    description: "Taller MAICOL",
    capacity: "N/A",
    icon: Users
  },
  {
    id: "ISRAEL",
    name: "ISRAEL",
    description: "Taller ISRAEL",
    capacity: "N/A",
    icon: Users
  },
  {
    id: "WALTER",
    name: "WALTER",
    description: "Taller WALTER",
    capacity: "N/A",
    icon: Users
  }
]

export function WorkshopAssignmentDialog({
  open,
  onOpenChange,
  onConfirm,
  orderCode,
  client,
  product,
  order
}: WorkshopAssignmentDialogProps) {
  const [selectedWorkshop, setSelectedWorkshop] = useState<string>("")
  const [notes, setNotes] = useState<string>("")

  const handleConfirm = () => {
    if (!selectedWorkshop) return

    onConfirm({
      workshop: selectedWorkshop,
      notes: notes.trim() || undefined
    })

    // Reset form
    setSelectedWorkshop("")
    setNotes("")
  }

  const handleCancel = () => {
    setSelectedWorkshop("")
    setNotes("")
    onOpenChange(false)
  }

  const selectedWorkshopData = workshops.find(w => w.id === selectedWorkshop)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Scissors className="h-5 w-5 text-[#E53935]" />
            Asignar Taller de Confección
          </DialogTitle>
          <DialogDescription>
            Selecciona el taller donde se realizará la confección de esta orden.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {/* Información de la orden */}
          <div className="bg-muted/50 rounded-lg p-3 space-y-2">
            <div className="text-sm">
              <span className="font-medium">Orden:</span>
              <span className="font-mono text-[#E53935] ml-2">{orderCode}</span>
            </div>
            <div className="text-sm">
              <span className="font-medium">Cliente:</span>
              <span className="ml-2">{client}</span>
            </div>
            <div className="text-sm">
              <span className="font-medium">Producto:</span>
              <span className="ml-2">{product}</span>
            </div>

            {/* Especificaciones técnicas */}
            {order && (order.fabricType || order.color || order.size || order.quantity) && (
              <div className="border-t pt-2 mt-2 space-y-1">
                {order.quantity && (
                  <div className="text-xs text-muted-foreground">
                    <span className="font-medium">Cantidad:</span> {order.quantity} unidades
                  </div>
                )}
                {order.fabricType && (
                  <div className="text-xs text-muted-foreground flex items-center gap-1">
                    <div className="w-1.5 h-1.5 bg-blue-500 rounded-full"></div>
                    <span className="font-medium">Tela:</span> {order.fabricType}
                  </div>
                )}
                {order.color && (
                  <div className="text-xs text-muted-foreground flex items-center gap-1">
                    <div className="w-1.5 h-1.5 bg-green-500 rounded-full"></div>
                    <span className="font-medium">Color:</span> {order.color}
                  </div>
                )}
                {order.size && (
                  <div className="text-xs text-muted-foreground flex items-center gap-1">
                    <div className="w-1.5 h-1.5 bg-purple-500 rounded-full"></div>
                    <span className="font-medium">Talla:</span> {order.size}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Selección de taller */}
          <div className="space-y-2">
            <Label htmlFor="workshop">Taller de Confección</Label>
            <Select value={selectedWorkshop} onValueChange={setSelectedWorkshop}>
              <SelectTrigger>
                <SelectValue placeholder="Selecciona un taller" />
              </SelectTrigger>
              <SelectContent>
                {workshops.map((workshop) => {
                  const Icon = workshop.icon
                  return (
                    <SelectItem key={workshop.id} value={workshop.id}>
                      <div className="flex items-center gap-3">
                        <Icon className="h-4 w-4" />
                        <div>
                          <div className="font-medium">{workshop.name}</div>
                          <div className="text-xs text-muted-foreground">
                            {workshop.description}
                          </div>
                        </div>
                      </div>
                    </SelectItem>
                  )
                })}
              </SelectContent>
            </Select>
          </div>

          {/* Información del taller seleccionado */}
          {selectedWorkshopData && (
            <div className="bg-green-50 border border-green-200 rounded-lg p-3">
              <div className="flex items-center gap-2 mb-2">
                <selectedWorkshopData.icon className="h-4 w-4 text-green-600" />
                <span className="font-medium text-green-800">
                  {selectedWorkshopData.name}
                </span>
              </div>
              <p className="text-sm text-green-700 mb-1">
                {selectedWorkshopData.description}
              </p>
              <p className="text-xs text-green-600">
                Capacidad: {selectedWorkshopData.capacity}
              </p>
            </div>
          )}

          {/* Notas adicionales */}
          <div className="space-y-2">
            <Label htmlFor="notes">Notas Adicionales (Opcional)</Label>
            <Textarea
              id="notes"
              placeholder="Instrucciones especiales, prioridades, observaciones..."
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={3}
            />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={handleCancel}>
            Cancelar
          </Button>
          <Button
            onClick={handleConfirm}
            disabled={!selectedWorkshop}
            className="bg-[#E53935] hover:bg-[#C62828]"
          >
            Asignar Taller
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
