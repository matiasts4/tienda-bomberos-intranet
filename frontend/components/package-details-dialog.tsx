"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import {
    Package,
    Truck,
    MapPin,
    Calendar,
    FileText,
    CheckCircle2,
    AlertCircle,
    Eye
} from "lucide-react"

interface PackageDetailsDialogProps {
    packageData: any // Using any for flexibility as it comes mapped from page.tsx
    trigger?: React.ReactNode
}

import { useDndActions } from "@/components/dnd-provider"

export function PackageDetailsDialog({ packageData, trigger }: PackageDetailsDialogProps) {
    const [open, setOpen] = useState(false)
    const dndActions = useDndActions()

    // Determine status color/label
    const statusConfig: Record<string, { label: string, color: string, icon: any }> = {
        'preparing': { label: 'Preparando', color: 'bg-yellow-100 text-yellow-800', icon: Package },
        'transit': { label: 'En Tránsito', color: 'bg-blue-100 text-blue-800', icon: Truck },
        'delivered': { label: 'Entregado', color: 'bg-green-100 text-green-800', icon: CheckCircle2 },
        'returned': { label: 'Devuelto', color: 'bg-red-100 text-red-800', icon: AlertCircle },
        'cutting-finished': { label: 'Preparando', color: 'bg-yellow-100 text-yellow-800', icon: Package }, // Mapped from page.tsx
    }

    const statusInfo = statusConfig[packageData.status] || statusConfig['preparing']
    const getPdfUrl = (packageId: string) => {
        // Asumiendo que packageId puede ser algo como "package-4" o solo "4"
        const id = packageId.replace('package-', '').replace('pkg-', '');
        const base = process.env.NEXT_PUBLIC_API_URL?.replace('/api', '') || 'http://localhost:8000';
        return `${base}/api/packages/${id}/pdf`;
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger || (
                    <Button variant="outline" size="sm" className="h-8 px-3 text-xs">
                        <Eye className="h-4 w-4 mr-2" />
                        Ver Paquete
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-w-[95vw] w-fit max-h-[90vh] overflow-y-auto">
                <DialogHeader className="pb-4 border-b">
                    <div className="flex justify-between items-start">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-blue-50 rounded-lg border border-blue-100">
                                <Package className="h-8 w-8 text-blue-600" />
                            </div>
                            <div>
                                <DialogTitle className="text-xl font-bold flex items-center gap-2">
                                    Paquete {packageData.code}
                                </DialogTitle>
                                <DialogDescription className="text-sm mt-1">
                                    Creado el {new Date(packageData.createdAt).toLocaleDateString()}
                                </DialogDescription>
                            </div>
                        </div>
                    </div>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    {/* Info Cards */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="p-4 bg-gray-50 rounded-lg border border-gray-100">
                            <div className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Destino</div>
                            <div className="flex items-center gap-2 font-medium text-gray-900">
                                <MapPin className="h-4 w-4 text-gray-400" />
                                {packageData.client.replace('📦 Paquete: ', '')}
                            </div>
                        </div>
                        <div className="p-4 bg-gray-50 rounded-lg border border-gray-100">
                            <div className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Transporte</div>
                            <div className="flex items-center gap-2 font-medium text-gray-900">
                                <Truck className="h-4 w-4 text-gray-400" />
                                {packageData.product} {/* Mapped transport here */}
                            </div>
                        </div>
                    </div>

                    {/* Items Table */}
                    <div className="border rounded-lg overflow-hidden">
                        <div className="bg-gray-50 px-4 py-2 border-b flex justify-between items-center">
                            <h3 className="font-semibold text-sm text-gray-700">Contenido del Paquete</h3>
                            <Badge variant="outline" className="text-xs bg-white">
                                {packageData.items?.length || 0} items
                            </Badge>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="w-[40%]">Producto</TableHead>
                                    <TableHead>Talla</TableHead>
                                    <TableHead>Color</TableHead>
                                    <TableHead className="text-right">Cantidad</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {packageData.items && packageData.items.length > 0 ? (
                                    packageData.items.map((item: any, idx: number) => (
                                        <TableRow key={idx}>
                                            <TableCell className="font-medium">
                                                <div className="flex flex-col">
                                                    <span>{item.product_type}</span>
                                                    <span className="text-xs text-muted-foreground">{item.cutting_order?.code ? `Orden #${item.cutting_order.code}` : ''}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell>{item.size}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <div
                                                        className="w-3 h-3 rounded-full border border-gray-200"
                                                        style={{ backgroundColor: (item.color || 'gray').toLowerCase() === 'sin color' ? '#eee' : (item.color || 'gray').toLowerCase() }}
                                                    />
                                                    {item.color}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right font-bold">{item.pivot?.quantity || item.quantity}</TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={4} className="text-center py-8 text-gray-500">
                                            No hay items en este paquete
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                        {packageData.items && packageData.items.length > 0 && (
                            <div className="bg-gray-50 px-4 py-3 border-t text-right">
                                <span className="text-sm text-gray-600 mr-2">Total Unidades:</span>
                                <span className="text-sm font-bold text-gray-900">
                                    {packageData.items.reduce((acc: number, item: any) => acc + (parseInt(item.pivot?.quantity || item.quantity) || 0), 0)}
                                </span>
                            </div>
                        )}
                    </div>

                    {/* Notes */}
                    {packageData.notes && (
                        <div className="p-4 bg-yellow-50 rounded-lg border border-yellow-100 text-sm text-yellow-800">
                            <div className="flex items-center gap-2 font-semibold mb-1">
                                <FileText className="h-4 w-4" />
                                Notas
                            </div>
                            {packageData.notes}
                        </div>
                    )}
                </div>

                <div className="flex justify-end gap-2 pt-4 border-t mt-2">
                    {/* Botón para Control de Calidad */}
                    {packageData.status === 'quality-control' && (
                        <Button
                            variant="default"
                            className="bg-indigo-600 hover:bg-indigo-700"
                            onClick={() => {
                                setOpen(false) // Close details dialog first
                                dndActions.openQcDialog(
                                    packageData.id,
                                    packageData.code || 'N/A',
                                    packageData.items || []
                                )
                            }}
                        >
                            Control Calidad / Reparación
                        </Button>
                    )}

                    {/* Botón para Imprimir PDF en Tránsito */}
                    {packageData.status === 'transit' && (
                        <Button
                            variant="outline"
                            className="bg-white text-gray-700 border-gray-300 hover:bg-gray-100"
                            onClick={() => window.open(getPdfUrl(packageData.id), '_blank')}
                        >
                            <FileText className="h-4 w-4 mr-2" />
                            Imprimir PDF
                        </Button>
                    )}

                    <Button variant="outline" onClick={() => setOpen(false)}>Cerrar</Button>
                </div>
            </DialogContent>
        </Dialog>
    )
}
