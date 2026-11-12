"use client"

import { useState, useEffect } from "react"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Loader2, AlertTriangle } from "lucide-react"

interface QualityControlDialogProps {
    open: boolean
    onOpenChange: (open: boolean) => void
    onConfirm: (wasteItems: { id: number; quantity: number }[], notes: string) => Promise<void>
    packageCode: string
    items: any[]
}

export function QualityControlDialog({
    open,
    onOpenChange,
    onConfirm,
    packageCode,
    items
}: QualityControlDialogProps) {
    // Store REPARACIÓN quantities (default 0)
    const [reparacionQuantities, setReparaciónQuantities] = useState<Record<string, number>>({})
    const [notes, setNotes] = useState("")
    const [isSubmitting, setIsSubmitting] = useState(false)

    // Normalizar items para manejar pivot.quantity vs quantity
    const normalizedItems = items.map(item => ({
        ...item,
        // Usar pivot.quantity si existe, sino item.quantity
        quantity: item.pivot?.quantity ?? item.quantity ?? 0
    }))

    // Reset when opening
    useEffect(() => {
        if (open) {
            setReparaciónQuantities({})
            setNotes("")
        }
    }, [open, items])

    const handleReparaciónChange = (itemId: string, value: string) => {
        let qty = parseInt(value)
        if (isNaN(qty)) qty = 0
        // No negative numbers
        if (qty < 0) qty = 0

        setReparaciónQuantities(prev => ({
            ...prev,
            [itemId]: qty
        }))
    }

    const handleSubmit = async () => {
        setIsSubmitting(true)
        try {
            const wasteItems: { id: number; quantity: number }[] = []

            normalizedItems.forEach(item => {
                const wasteQty = reparacionQuantities[item.id] ?? 0

                if (wasteQty > 0) {
                    // Cap waste at item quantity just in case
                    const finalWaste = Math.min(wasteQty, item.quantity)
                    
                    // Asegurar que el id sea un número válido
                    const itemId = typeof item.id === 'number' ? item.id : parseInt(String(item.id), 10)
                    
                    if (!isNaN(itemId) && itemId > 0) {
                        wasteItems.push({
                            id: itemId,
                            quantity: finalWaste
                        })
                    } else {
                        console.warn('QC Dialog - Invalid item id:', item.id, item)
                    }
                }
            })

            console.log('QC Dialog - Submitting wasteItems:', wasteItems)
            await onConfirm(wasteItems, notes)
            onOpenChange(false)
        } catch (error) {
            console.error("Error confirming QC:", error)
        } finally {
            setIsSubmitting(false)
        }
    }

    // Calculate totals
    const totalItems = normalizedItems.reduce((sum, item) => sum + item.quantity, 0)
    const totalWaste = Object.values(reparacionQuantities).reduce((sum, qty) => sum + qty, 0)
    // Ensure we don't display negative OK if user inputs too much waste
    // Actual logic in submit will cap it, but for display:
    let displayedTotalWaste = 0
    normalizedItems.forEach(item => {
        const w = reparacionQuantities[item.id] ?? 0
        displayedTotalWaste += Math.min(w, item.quantity)
    })

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-[95vw] w-fit">
                <DialogHeader>
                    <DialogTitle>Control de Calidad - Paquete {packageCode}</DialogTitle>
                    <DialogDescription>
                        Indica la cantidad de productos con <strong>REPARACIÓN (defectuosos)</strong>. Si todo está bien, deja las cantidades en 0.
                    </DialogDescription>
                </DialogHeader>

                <div className="py-4">
                    <div className="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Producto</TableHead>
                                    <TableHead className="text-right">Total</TableHead>
                                    <TableHead className="text-right w-[120px]">Cant. Reparación</TableHead>
                                    <TableHead className="text-right">Cant. OK</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {normalizedItems.map((item) => {
                                    const wasteQtyInput = reparacionQuantities[item.id] ?? 0
                                    // Clamp for display calculation
                                    const wasteQty = Math.min(wasteQtyInput, item.quantity)
                                    const okQty = item.quantity - wasteQty
                                    const hasWaste = wasteQty > 0

                                    return (
                                        <TableRow key={item.id} className={hasWaste ? "bg-orange-50/50" : ""}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {item.product_type} {item.color}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    Talla {item.size}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right font-medium">{item.quantity}</TableCell>
                                            <TableCell className="text-right">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    max={item.quantity}
                                                    value={wasteQtyInput === 0 ? '' : wasteQtyInput}
                                                    onChange={(e) => handleReparaciónChange(item.id, e.target.value)}
                                                    placeholder="0"
                                                    className={`h-8 text-right font-semibold ${hasWaste ? "border-orange-300 text-orange-700 bg-white" : ""}`}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground">
                                                {okQty}
                                            </TableCell>
                                        </TableRow>
                                    )
                                })}
                            </TableBody>
                        </Table>
                    </div>

                    {displayedTotalWaste > 0 && (
                        <div className="mt-4 p-3 bg-orange-50 border border-orange-200 rounded-md flex gap-2 items-start">
                            <AlertTriangle className="h-5 w-5 text-orange-600 mt-0.5" />
                            <div>
                                <h4 className="font-semibold text-orange-800">Se reportará Reparación</h4>
                                <p className="text-sm text-orange-700">
                                    Se creará automáticamente un nuevo paquete con <strong>{displayedTotalWaste} items</strong> marcados como reparacion.
                                </p>
                            </div>
                        </div>
                    )}

                    <div className="mt-4">
                        <Label htmlFor="notes">Notas de Control de Calidad</Label>
                        <Input
                            id="notes"
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            placeholder="Detalles sobre el control de calidad..."
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
                        Cancelar
                    </Button>
                    <Button onClick={handleSubmit} disabled={isSubmitting} className={displayedTotalWaste > 0 ? "bg-orange-600 hover:bg-orange-700" : ""}>
                        {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        {displayedTotalWaste > 0 ? "Confirmar como reparacion" : "Confirmar Todo OK"}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
