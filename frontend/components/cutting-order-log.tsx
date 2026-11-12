"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet"
import { Badge } from "@/components/ui/badge"
import { Separator } from "@/components/ui/separator"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { 
  FileText, 
  Clock, 
  User, 
  Activity, 
  Download, 
  Calendar,
  TrendingUp,
  Package,
  Scissors,
  AlertCircle
} from "lucide-react"
import { format } from "date-fns"
import { es } from "date-fns/locale"
import { getCuttingOrderLogSummary } from "@/lib/mock-data"
import { useCuttingOrderLog } from "@/hooks/use-cutting-order-log"
import type { CuttingOrderLogEntry } from "@/lib/mock-data"

interface CuttingOrderLogProps {
  orderId: string
  trigger?: React.ReactNode
}

const actionLabels: Record<string, { label: string; icon: any; color: string }> = {
  created: { 
    label: "Orden Creada", 
    icon: Package, 
    color: "bg-blue-500" 
  },
  status_changed: { 
    label: "Cambio de Estado", 
    icon: TrendingUp, 
    color: "bg-green-500" 
  },
  updated: { 
    label: "Orden Actualizada", 
    icon: FileText, 
    color: "bg-orange-500" 
  },
  material_added: { 
    label: "Material Agregado", 
    icon: Scissors, 
    color: "bg-purple-500" 
  },
  material_consumed: { 
    label: "Material Consumido", 
    icon: Scissors, 
    color: "bg-red-500" 
  },
  priority_changed: { 
    label: "Prioridad Cambiada", 
    icon: AlertCircle, 
    color: "bg-yellow-500" 
  },
}

const statusLabels: Record<string, string> = {
  "order-received": "Orden Recibida",
  "design": "Diseño / Patronaje", 
  "cutting": "Corte",
  "transit": "Tránsito",
  "sewing": "Confección",
  "quality-control": "Control de Calidad",
  "finished": "Terminado",
}

export function CuttingOrderLog({ orderId, trigger }: CuttingOrderLogProps) {
  const [open, setOpen] = useState(false)
  const { generateLogPDF, isLoading } = useCuttingOrderLog()
  const logSummary = getCuttingOrderLogSummary(orderId)
  
  if (!logSummary) {
    return null // No hay bitácora disponible
  }

  const handleDownloadPDF = async () => {
    try {
      await generateLogPDF(orderId)
      // En desarrollo, mostrar mensaje simulado
      alert("PDF de bitácora generado exitosamente (funcionalidad simulada)")
    } catch (error) {
      console.error("Error al generar PDF:", error)
      alert("Error al generar el PDF")
    }
  }

  return (
    <Sheet open={open} onOpenChange={setOpen}>
      <SheetTrigger asChild>
        {trigger || (
          <Button variant="outline" size="sm" className="gap-2">
            <FileText className="h-4 w-4" />
            Ver Bitácora
          </Button>
        )}
      </SheetTrigger>
      <SheetContent className="w-[600px] sm:w-[700px]">
        <SheetHeader>
          <SheetTitle className="flex items-center gap-2">
            <Activity className="h-5 w-5" />
            Bitácora de Orden {logSummary.orderCode}
          </SheetTitle>
          <SheetDescription>
            Historial completo de cambios y actividades de la orden de producción
          </SheetDescription>
        </SheetHeader>

        <div className="mt-6 space-y-6">
          {/* Resumen de la Orden */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span className="text-muted-foreground">Cliente:</span>
                <p className="font-medium">{logSummary.client}</p>
              </div>
              <div>
                <span className="text-muted-foreground">Total Entradas:</span>
                <p className="font-medium">{logSummary.totalEntries} registros</p>
              </div>
              <div>
                <span className="text-muted-foreground">Periodo:</span>
                <p className="font-medium text-xs">
                  {format(new Date(logSummary.dateRange.start), "dd/MM/yy", { locale: es })} - {" "}
                  {format(new Date(logSummary.dateRange.end), "dd/MM/yy", { locale: es })}
                </p>
              </div>
              <div className="flex justify-end">
                <Button 
                  size="sm" 
                  variant="outline"
                  className="gap-2 text-xs"
                  onClick={handleDownloadPDF}
                  disabled={isLoading}
                >
                  <Download className="h-3 w-3" />
                  {isLoading ? "Generando..." : "Descargar PDF"}
                </Button>
              </div>
            </div>
          </div>

          {/* Historial de Estados */}
          <div className="space-y-3">
            <h4 className="text-sm font-medium flex items-center gap-2">
              <TrendingUp className="h-4 w-4" />
              Historial de Estados
            </h4>
            <div className="bg-gray-50 rounded-lg p-3">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="h-8 text-xs">Estado</TableHead>
                    <TableHead className="h-8 text-xs">Fecha</TableHead>
                    <TableHead className="h-8 text-xs">Duración</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {logSummary.statusHistory.map((status, index) => (
                    <TableRow key={index}>
                      <TableCell className="py-2 text-xs">
                        <Badge variant="outline">
                          {statusLabels[status.status] || status.status}
                        </Badge>
                      </TableCell>
                      <TableCell className="py-2 text-xs">
                        {format(new Date(status.timestamp), "dd/MM/yy HH:mm", { locale: es })}
                      </TableCell>
                      <TableCell className="py-2 text-xs text-muted-foreground">
                        {status.duration || "En curso"}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </div>

          <Separator />

          {/* Log de Actividades */}
          <div className="space-y-3">
            <h4 className="text-sm font-medium flex items-center gap-2">
              <Clock className="h-4 w-4" />
              Registro de Actividades
            </h4>
            
            <ScrollArea className="h-[400px]">
              <div className="space-y-3">
                {logSummary.entries.map((entry) => {
                  const actionConfig = actionLabels[entry.action] || {
                    label: entry.action,
                    icon: Activity,
                    color: "bg-gray-500"
                  }
                  const IconComponent = actionConfig.icon

                  return (
                    <div key={entry.id} className="border rounded-lg p-3 bg-white">
                      <div className="flex items-start gap-3">
                        <div className={`rounded-full p-2 ${actionConfig.color} text-white flex-shrink-0`}>
                          <IconComponent className="h-3 w-3" />
                        </div>
                        
                        <div className="flex-1 space-y-1">
                          <div className="flex items-center justify-between">
                            <span className="text-sm font-medium">{actionConfig.label}</span>
                            <span className="text-xs text-muted-foreground">
                              {format(new Date(entry.timestamp), "dd/MM/yy HH:mm", { locale: es })}
                            </span>
                          </div>
                          
                          <p className="text-sm text-muted-foreground">{entry.details}</p>
                          
                          <div className="flex items-center gap-2 text-xs">
                            <User className="h-3 w-3" />
                            <span className="font-medium">{entry.user}</span>
                          </div>

                          {/* Información adicional específica del tipo de acción */}
                          {entry.action === "status_changed" && entry.previousStatus && entry.newStatus && (
                            <div className="flex items-center gap-2 text-xs">
                              <span className="text-muted-foreground">Cambio:</span>
                              <Badge variant="outline" className="text-xs">
                                {statusLabels[entry.previousStatus]}
                              </Badge>
                              <span>→</span>
                              <Badge variant="outline" className="text-xs">
                                {statusLabels[entry.newStatus]}
                              </Badge>
                            </div>
                          )}

                          {/* Metadata adicional */}
                          {entry.metadata && (
                            <div className="mt-2 p-2 bg-gray-50 rounded text-xs">
                              <div className="grid grid-cols-2 gap-2">
                                {Object.entries(entry.metadata).map(([key, value]) => (
                                  <div key={key}>
                                    <span className="text-muted-foreground">{key}:</span>
                                    <span className="ml-1 font-medium">
                                      {Array.isArray(value) ? value.join(", ") : String(value)}
                                    </span>
                                  </div>
                                ))}
                              </div>
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                  )
                })}
              </div>
            </ScrollArea>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  )
}
