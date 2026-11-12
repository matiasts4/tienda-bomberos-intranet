"use client"

import { useState } from "react"
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogDescription
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Textarea } from "@/components/ui/textarea"
import { Label } from "@/components/ui/label"
import { Loader2, Ban } from "lucide-react"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"

interface CotizacionAnulacionDialogProps {
    open: boolean
    onOpenChange: (open: boolean) => void
    onConfirm: (motivo: string) => Promise<void>
    numeroCotizacion?: string
}

export function CotizacionAnulacionDialog({
    open,
    onOpenChange,
    onConfirm,
    numeroCotizacion
}: CotizacionAnulacionDialogProps) {
    const [motivoGeneral, setMotivoGeneral] = useState("")
    const [comentario, setComentario] = useState("")
    const [loading, setLoading] = useState(false)

    const motivos = [
        "Error de tipado",
        "Venta cancelada",
        "No recibe pago",
        "Cotización duplicada",
        "Presupuesto vencido / No aceptado",
        "Otro"
    ]

    async function handleConfirm() {
        if (!motivoGeneral) return

        setLoading(true)
        try {
            const finalReason = comentario.trim() ? `${motivoGeneral}: ${comentario}` : motivoGeneral;
            await onConfirm(finalReason)
            setMotivoGeneral("")
            setComentario("")
            onOpenChange(false)
        } catch (error) {
            console.error(error)
        } finally {
            setLoading(false)
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-red-600">
                        <Ban className="h-5 w-5" />
                        Anular Cotización {numeroCotizacion}
                    </DialogTitle>
                    <DialogDescription>
                        La cotización pasará a No Vigentes con motivo "Anulada". No se eliminará del registro histórico.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    <div className="space-y-2">
                        <Label htmlFor="motivo-anulacion">Motivo de anulación <span className="text-red-500">*</span></Label>
                        <Select value={motivoGeneral} onValueChange={setMotivoGeneral}>
                            <SelectTrigger id="motivo-anulacion">
                                <SelectValue placeholder="Seleccione una razón..." />
                            </SelectTrigger>
                            <SelectContent>
                                {motivos.map((m) => (
                                    <SelectItem key={m} value={m}>{m}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="comentario-anulacion">Detalles adicionales (Opcional)</Label>
                        <Textarea
                            id="comentario-anulacion"
                            placeholder="Proporcione más contexto si es necesario..."
                            value={comentario}
                            onChange={(e) => setComentario(e.target.value)}
                            rows={3}
                        />
                    </div>
                </div>

                <DialogFooter className="gap-2 sm:justify-between">
                    <Button variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
                        Cancelar
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={handleConfirm}
                        disabled={!motivoGeneral || loading}
                        className="bg-red-600 hover:bg-red-700"
                    >
                        {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        Confirmar Anulación
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
