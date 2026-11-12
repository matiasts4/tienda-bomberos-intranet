"use client"

import { useEffect, useMemo, useState } from "react"
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Label } from "@/components/ui/label"
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group"
import { Loader2, Lock, Truck } from "lucide-react"
import { cn } from "@/lib/utils"
import {
    MAX_DETALLE_OTRO,
    MAX_OBSERVACION,
    motivosDisponibles,
    validarNoVigente,
    type FormNoVigente,
    type MotivoNoVigente,
} from "@/lib/no-vigentes"

interface CotizacionNoVigenteDialogProps {
    open: boolean
    onOpenChange: (open: boolean) => void
    numeroCotizacion?: string
    esSuperadmin: boolean
    /** La cotización está en Ventas (habilita "Finalizada"). */
    enVenta: boolean
    /** Si lanza un error, el diálogo queda abierto con lo escrito. */
    onConfirm: (form: FormNoVigente, boucherDespacho: File | null) => Promise<void>
}

const FORM_VACIO: FormNoVigente = { motivo: "", detalleOtro: "", observacion: "" }

export function CotizacionNoVigenteDialog({
    open,
    onOpenChange,
    numeroCotizacion,
    esSuperadmin,
    enVenta,
    onConfirm
}: CotizacionNoVigenteDialogProps) {
    const [form, setForm] = useState<FormNoVigente>(FORM_VACIO)
    const [loading, setLoading] = useState(false)
    // Opcional: solo se ofrece con motivo "Finalizada" (venta despachada al cliente)
    const [boucher, setBoucher] = useState<File | null>(null)
    // Error del backend: se muestra aquí, junto al formulario que hay que corregir
    const [errorEnvio, setErrorEnvio] = useState<string | null>(null)

    // Cada tarjeta arrastrada parte con el formulario limpio
    useEffect(() => {
        if (open) {
            setForm(FORM_VACIO)
            setBoucher(null)
            setErrorEnvio(null)
        }
    }, [open])

    const motivos = useMemo(() => motivosDisponibles({ esSuperadmin, enVenta }), [esSuperadmin, enVenta])
    const error = validarNoVigente(form)

    async function handleConfirm() {
        if (error || loading) return
        setLoading(true)
        setErrorEnvio(null)
        try {
            await onConfirm(form, form.motivo === "finalizada" ? boucher : null)
            onOpenChange(false)
        } catch (e: any) {
            setErrorEnvio(e?.message || "No se pudo mover la cotización. Intenta nuevamente.")
        } finally {
            setLoading(false)
        }
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !loading && onOpenChange(next)}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Mover a No Vigentes {numeroCotizacion}</DialogTitle>
                    <DialogDescription>
                        La cotización sale del flujo comercial. Indica por qué; quedará registrado en su historial.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    <div className="space-y-2">
                        <Label>Motivo <span className="text-red-500">*</span></Label>
                        <RadioGroup
                            value={form.motivo}
                            onValueChange={(value) => setForm(prev => ({ ...prev, motivo: value as MotivoNoVigente }))}
                            className="gap-2"
                        >
                            {motivos.map((m) => (
                                <label
                                    key={m.value}
                                    htmlFor={`motivo-${m.value}`}
                                    className={cn(
                                        "flex items-start gap-3 rounded-lg border p-3 transition-colors",
                                        m.deshabilitado ? "cursor-not-allowed bg-muted/40 opacity-60" : "cursor-pointer hover:bg-muted/50",
                                        form.motivo === m.value && "border-primary bg-primary/5"
                                    )}
                                >
                                    <RadioGroupItem id={`motivo-${m.value}`} value={m.value} disabled={m.deshabilitado} className="mt-0.5" />
                                    <div className="flex-1">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-sm font-medium">{m.label}</span>
                                            {m.razon && (
                                                <span className="flex items-center gap-1 text-[11px] text-muted-foreground">
                                                    <Lock className="h-3 w-3" />
                                                    {m.razon}
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground">{m.descripcion}</p>
                                    </div>
                                </label>
                            ))}
                        </RadioGroup>
                    </div>

                    {form.motivo === "otro" && (
                        <div className="space-y-2">
                            <Label htmlFor="detalle-otro">Descripción breve <span className="text-red-500">*</span></Label>
                            <Input
                                id="detalle-otro"
                                placeholder="Ej: Cambio de proveedor"
                                value={form.detalleOtro}
                                maxLength={MAX_DETALLE_OTRO}
                                onChange={(e) => setForm(prev => ({ ...prev, detalleOtro: e.target.value }))}
                            />
                        </div>
                    )}

                    {form.motivo === "finalizada" && (
                        <div className="space-y-2 rounded-lg border border-dashed p-3">
                            <Label htmlFor="boucher-despacho" className="flex items-center gap-2">
                                <Truck className="h-4 w-4" />
                                Boucher Despacho <span className="text-xs font-normal text-muted-foreground">(opcional)</span>
                            </Label>
                            <Input
                                id="boucher-despacho"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(e) => setBoucher(e.target.files?.[0] ?? null)}
                            />
                            <p className="text-xs text-muted-foreground">
                                Comprobante del envío al cliente. Si no lo tienes ahora, puedes subirlo después desde la tarjeta.
                            </p>
                        </div>
                    )}

                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="observacion-no-vigente">Observación <span className="text-red-500">*</span></Label>
                            <span className="text-[11px] text-muted-foreground">
                                {form.observacion.length}/{MAX_OBSERVACION}
                            </span>
                        </div>
                        <Textarea
                            id="observacion-no-vigente"
                            placeholder="¿Qué pasó? Este texto queda en el historial de la cotización."
                            value={form.observacion}
                            maxLength={MAX_OBSERVACION}
                            onChange={(e) => setForm(prev => ({ ...prev, observacion: e.target.value }))}
                            rows={4}
                        />
                    </div>

                    {errorEnvio && (
                        <div role="alert" className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            {errorEnvio}
                        </div>
                    )}
                </div>

                <DialogFooter className="gap-2 sm:justify-between">
                    <Button variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
                        Cancelar
                    </Button>
                    <Button onClick={handleConfirm} disabled={!!error || loading} title={error ?? undefined}>
                        {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        Mover a No Vigentes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
