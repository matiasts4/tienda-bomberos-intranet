"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { CuttingOrderLog } from "@/components/cutting-order-log"
import {
  User,
  Package,
  FileText,
  AlertCircle,
  Wrench,
  Edit,
  Eye,
  Settings,
  CheckCircle2,
  XCircle,
  Truck,
  Scissors,
  Palette,
  Target,
  Clock,
  Trash2
} from "lucide-react"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog"
import { DialogDescription } from "@/components/ui/dialog"
import type { Order } from "@/lib/mock-data"

interface CuttingOrderDetailsProps {
  order: Order
  trigger?: React.ReactNode
  onDelete?: (id: string) => Promise<void>
}

const statusLabels: Record<string, string> = {
  "order-received": "Orden Recibida",
  "design": "Diseño / Patronaje",
  "cutting": "Corte",
  "transit": "Tránsito",
  "sewing": "Confección",
  "quality-control": "Control de Calidad",
  "finished": "Terminado",
  "rejected": "Rechazado",
  "lead": "Prospecto",
  "quote-sent": "Cotización Enviada",
  "negotiating": "Negociando",
  "approved": "Aprobado"
}

const statusProgress: Record<string, number> = {
  "order-received": 10,
  "design": 25,
  "cutting": 45,
  "transit": 55,
  "sewing": 65,
  "quality-control": 85,
  "finished": 100,
  "rejected": 0,
  "lead": 5,
  "quote-sent": 15,
  "negotiating": 20,
  "approved": 30
}

const statusIcons: Record<string, React.ComponentType<any>> = {
  "order-received": Package,
  "design": Palette,
  "cutting": Scissors,
  "transit": Truck,
  "sewing": Target,
  "quality-control": CheckCircle2,
  "finished": CheckCircle2,
  "rejected": XCircle,
  "lead": User,
  "quote-sent": FileText,
  "negotiating": Clock,
  "approved": CheckCircle2
}

export function CuttingOrderDetails({ order, trigger, onDelete }: CuttingOrderDetailsProps) {
  const [open, setOpen] = useState(false)

  const isDelayed = order.deadline && new Date(order.deadline) < new Date() && order.status !== "finished"
  const StatusIcon = statusIcons[order.status] || Package

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        {trigger || (
          <Button variant="outline" size="sm" className="h-8 px-3 text-xs">
            Ver Detalles
          </Button>
        )}
      </DialogTrigger>
      <DialogContent className="w-fit max-w-[95vw] max-h-[90vh] overflow-y-auto flex flex-col p-6">
        {/* Header Premium */}
        <DialogHeader className="pb-6 border-b">
          <DialogTitle className="flex items-center gap-4">
            <div className="p-3 bg-primary/10 rounded-lg">
              <StatusIcon className="h-8 w-8 text-primary" />
            </div>
            <div className="flex-1 text-left">
              <div className="text-2xl font-bold text-foreground">
                Orden #{order.code || order.id}
              </div>
              <div className="flex items-center gap-2 text-base text-muted-foreground mt-1">
                <User className="h-4 w-4" />
                <span>{order.client}</span>
              </div>
            </div>
            <div className="flex flex-col items-end gap-2">
              {isDelayed && (
                <Badge variant="destructive" className="text-xs px-2 py-1">
                  <AlertCircle className="h-3 w-3 mr-1" />
                  Atrasado
                </Badge>
              )}
            </div>
          </DialogTitle>
        </DialogHeader>

        {/* Contenido principal con scroll optimizado */}
        <div className="flex-1 overflow-y-auto py-4 space-y-6 custom-scroll">

          {/* Información de Fabricación Simplificada */}
          <div className="grid grid-cols-2 gap-4">
            <div className="border rounded-xl p-4 bg-card">
              <div className="text-sm text-muted-foreground font-medium mb-1.5">Cliente</div>
              <div className="text-lg font-semibold text-foreground flex items-center gap-2">
                <User className="h-4 w-4 text-primary" />
                {order.client}
              </div>
            </div>
            <div className="border rounded-xl p-4 bg-card">
              <div className="text-sm text-muted-foreground font-medium mb-1.5">Estado Actual</div>
              <div className="flex items-center gap-2">
                <Badge variant="outline" className="text-sm px-3 py-1 bg-muted/50">
                  <StatusIcon className="h-3.5 w-3.5 mr-2" />
                  {statusLabels[order.status]}
                </Badge>
              </div>
            </div>
          </div>

          {/* Detalles del Producto */}
          <div className="border rounded-xl bg-card shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b bg-muted/30">
              <div className="flex items-center gap-3">
                <div className="p-2 bg-primary/10 rounded-lg">
                  <Package className="h-5 w-5 text-primary" />
                </div>
                <div>
                  <h4 className="font-bold text-lg text-foreground">Detalles de Confección</h4>
                  <p className="text-sm text-muted-foreground">Especificaciones técnicas para el taller</p>
                </div>
              </div>
            </div>

            {/* Productos Detallados */}
            {order.productDetails && order.productDetails.length > 0 ? (
              <div className="p-0 overflow-x-auto">
                <Table>
                  <TableHeader className="bg-muted/50">
                    <TableRow>
                      <TableHead className="h-10 text-sm font-semibold pl-6 whitespace-nowrap">Producto</TableHead>
                      <TableHead className="h-10 text-sm font-semibold whitespace-nowrap">Tela</TableHead>
                      <TableHead className="h-10 text-sm font-semibold whitespace-nowrap">Talla</TableHead>
                      <TableHead className="h-10 text-sm font-semibold whitespace-nowrap">Color</TableHead>
                      <TableHead className="h-10 text-sm font-semibold text-right pr-6 whitespace-nowrap">Cant.</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {order.productDetails.map((detail) => (
                      <TableRow key={detail.id} className="hover:bg-muted/50">
                        <TableCell className="py-4 text-sm font-medium pl-6 whitespace-nowrap">{detail.productType}</TableCell>
                        <TableCell className="py-4 text-sm text-muted-foreground whitespace-nowrap">{(detail as any).fabricType || (order as any).fabricType || "—"}</TableCell>
                        <TableCell className="py-4 text-sm whitespace-nowrap">
                          <Badge variant="outline" className="text-sm font-normal">{detail.size}</Badge>
                        </TableCell>
                        <TableCell className="py-4 text-sm whitespace-nowrap">
                          <div className="flex items-center gap-2">
                            <div
                              className={`w-3 h-3 rounded-full border ${detail.color.toLowerCase() === 'red' ? 'bg-red-500' :
                                detail.color.toLowerCase() === 'blue' ? 'bg-blue-500' :
                                  detail.color.toLowerCase() === 'green' ? 'bg-green-500' :
                                    detail.color.toLowerCase() === 'yellow' ? 'bg-yellow-500' :
                                      detail.color.toLowerCase() === 'black' ? 'bg-black' :
                                        detail.color.toLowerCase() === 'white' ? 'bg-white border-gray-300' :
                                          'bg-gray-400'
                                }`}
                            />
                            <span className="capitalize">{detail.color}</span>
                          </div>
                        </TableCell>
                        <TableCell className="py-4 text-sm font-bold text-right pr-6 whitespace-nowrap">{detail.quantity}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
                <div className="bg-muted/30 p-4 border-t flex justify-end">
                  <div className="text-sm font-medium text-muted-foreground">
                    Total: <span className="text-foreground font-bold ml-1">{order.productDetails.reduce((sum, detail) => sum + detail.quantity, 0)} unidades</span>
                  </div>
                </div>
              </div>
            ) : (
              <div className="space-y-3 text-sm p-6">
                <div>
                  <span className="text-muted-foreground font-medium">Producto:</span>
                  <p className="font-semibold text-gray-800 mt-1">
                    {order.product || (order.products && order.products.join(", ")) || "No especificado"}
                  </p>
                </div>

                {/* Especificaciones técnicas */}
                {(order.fabricType || order.color || order.size) && (
                  <div className="bg-white/60 rounded-lg p-3 space-y-2 border border-violet-200/30">
                    <div className="text-sm font-medium text-violet-800">✨ Especificaciones</div>

                    {order.fabricType && (
                      <div className="flex items-center gap-2 text-sm">
                        <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                        <span className="text-muted-foreground">Tela:</span>
                        <span className="font-medium text-blue-700">{order.fabricType}</span>
                      </div>
                    )}

                    {order.color && (
                      <div className="flex items-center gap-2 text-sm">
                        <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                        <span className="text-muted-foreground">Color:</span>
                        <span className="font-medium text-green-700">{order.color}</span>
                      </div>
                    )}

                    {order.size && (
                      <div className="flex items-center gap-2 text-sm">
                        <div className="w-2 h-2 bg-purple-500 rounded-full"></div>
                        <span className="text-muted-foreground">Talla:</span>
                        <span className="font-medium text-purple-700">{order.size}</span>
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Insumos Requeridos */}
          {order.supplies && order.supplies.length > 0 && (
            <div className="border rounded-xl bg-card shadow-sm overflow-hidden mt-6">
              <div className="px-6 py-4 border-b bg-muted/30">
                <div className="flex items-center gap-3">
                  <div className="p-2 bg-primary/10 rounded-lg">
                    <Wrench className="h-5 w-5 text-primary" />
                  </div>
                  <div>
                    <h4 className="font-bold text-lg text-foreground">Insumos Requeridos</h4>
                    <p className="text-sm text-muted-foreground">Materiales necesarios</p>
                  </div>
                </div>
              </div>

              <div className="p-0 overflow-x-auto">
                <Table>
                  <TableHeader className="bg-muted/50">
                    <TableRow>
                      <TableHead className="h-10 text-sm font-semibold pl-6 whitespace-nowrap">Insumo</TableHead>
                      <TableHead className="h-10 text-sm font-semibold whitespace-nowrap">Tipo</TableHead>
                      <TableHead className="h-10 text-sm font-semibold whitespace-nowrap">Cantidad</TableHead>
                      <TableHead className="h-10 text-sm font-semibold text-right pr-6 whitespace-nowrap">Detalles</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {order.supplies.map((supply) => (
                      <TableRow key={supply.id} className="hover:bg-muted/50">
                        <TableCell className="py-4 text-sm font-medium pl-6 whitespace-nowrap">{supply.name}</TableCell>
                        <TableCell className="py-4 text-sm whitespace-nowrap">
                          <Badge variant="secondary" className="text-xs font-normal">{supply.type}</Badge>
                        </TableCell>
                        <TableCell className="py-4 text-sm font-bold whitespace-nowrap">
                          {supply.quantity} {supply.unit}
                        </TableCell>
                        <TableCell className="py-4 text-sm text-right pr-6 text-muted-foreground whitespace-nowrap">
                          {[supply.size, supply.color].filter(Boolean).join(" • ") || "—"}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </div>
          )}

          {/* Notas */}
          {order.notes && (
            <div className="border rounded-xl bg-card shadow-sm mt-6">
              <div className="px-6 py-4 border-b bg-muted/30">
                <div className="flex items-center gap-3">
                  <div className="p-2 bg-primary/10 rounded-lg">
                    <FileText className="h-5 w-5 text-primary" />
                  </div>
                  <div>
                    <h4 className="font-bold text-lg text-foreground">Notas Adicionales</h4>
                    <p className="text-sm text-muted-foreground">Observaciones especiales</p>
                  </div>
                </div>
              </div>
              <div className="p-6 text-sm leading-relaxed text-muted-foreground">
                {order.notes}
              </div>
            </div>
          )}
        </div>

        {/* Footer con Acciones */}
        <div className="flex-shrink-0 border-t bg-gray-50/50 -mx-6 -mb-6 px-6 py-4 rounded-b-lg mt-4">
          <div className="flex gap-2 justify-end">
            <CuttingOrderLog orderId={order.id} trigger={
              <Button size="sm" variant="outline" className="h-9">
                <Eye className="h-4 w-4 mr-2" />
                Bitácora
              </Button>
            } />

            {onDelete && (
              <AlertDialog>
                <AlertDialogTrigger asChild>
                  <Button variant="destructive" size="sm" className="h-9 bg-[#E53935] hover:bg-[#C62828]">
                    <Trash2 className="h-4 w-4 mr-2" />
                    Eliminar
                  </Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                  <DialogHeader>
                    <DialogTitle>¿Eliminar orden de corte?</DialogTitle>
                    <DialogDescription>
                      Esta acción no se puede deshacer. Se eliminará permanentemente la orden de corte <strong>{order.code}</strong>.
                    </DialogDescription>
                  </DialogHeader>
                  <div className="flex justify-end gap-2">
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction onClick={() => {
                      onDelete(order.id)
                      setOpen(false)
                    }} className="bg-red-600 hover:bg-red-700">
                      Eliminar
                    </AlertDialogAction>
                  </div>
                </AlertDialogContent>
              </AlertDialog>
            )}

            <Button variant="outline" size="sm" onClick={() => setOpen(false)} className="h-9">
              Cerrar
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
