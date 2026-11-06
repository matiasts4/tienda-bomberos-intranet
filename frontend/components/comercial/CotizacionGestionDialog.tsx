"use client"

import { FechaInput } from "@/components/ui/fecha-input"
import { useEffect, useState } from "react"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { api } from "@/lib/api"
import { Loader2 } from "lucide-react"
import { fechaISO, fechaPropuestaReagendar } from "@/lib/vencimiento-cotizacion"

interface CotizacionGestionDialogProps {
    open: boolean
    onOpenChange: (open: boolean) => void
    cotizacionId: number
    currentVencimiento?: string | null
    onSuccess?: () => void
}

export function CotizacionGestionDialog({
    open,
    onOpenChange,
    cotizacionId,
    currentVencimiento,
    onSuccess,
}: CotizacionGestionDialogProps) {
    const [glosa, setGlosa] = useState("")
    const [fechaNueva, setFechaNueva] = useState(() => fechaPropuestaReagendar(currentVencimiento))
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState<string | null>(null)

    // El diálogo queda montado entre aperturas: cada vez que se abre parte de la
    // fecha vigente de esa cotización (o de hoy, si ya venció).
    useEffect(() => {
        if (open) {
            setFechaNueva(fechaPropuestaReagendar(currentVencimiento))
            setError(null)
        }
    }, [open, currentVencimiento])

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault()
        setLoading(true)
        setError(null)
        try {
            await api.post(`/api/cotizaciones/${cotizacionId}/gestion`, {
                glosa,
                fecha_vencimiento_nueva: fechaNueva,
            })
            onOpenChange(false)
            onSuccess?.()
            setGlosa("")
        } catch (e: any) {
            setError(e?.message || "No se pudo reagendar la cotización. Intenta nuevamente.")
        } finally {
            setLoading(false)
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Gestionar Cotización</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-4">
                    <div className="space-y-2">
                        <Label>Nueva Fecha de Vencimiento</Label>
                        <FechaInput
                            value={fechaNueva}
                            min={fechaISO()}
                            onChange={v => setFechaNueva(v)}
                            required
                        />
                    </div>

                    <div className="space-y-2">
                        <Label>Detalle de la gestión (Glosa) (Opcional)</Label>
                        <Input
                            placeholder="Ej: Cliente solicitó extensión por a la espera de presupuesto..."
                            value={glosa}
                            onChange={(e) => setGlosa(e.target.value)}
                        />
                    </div>

                    {error && (
                        <div role="alert" className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            {error}
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={loading}>
                            {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Guardar Gestión
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    )
}
