"use client"

import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { useRef, useState } from "react"
import { Ban, Calendar, Loader2, Truck, Upload, User } from "lucide-react"
import { toast } from "sonner"
import { api } from "@/lib/api"
import { cn } from "@/lib/utils"
import { MOTIVO_BADGE_CLASS, etiquetaMotivo, type DatosNoVigente } from "@/lib/no-vigentes"

interface CotizacionNoVigenteInfoDialogProps {
    open: boolean
    onOpenChange: (open: boolean) => void
    numeroCotizacion?: string
    datos: DatosNoVigente | null
    cotizacionId?: number
    archivos?: { id: number; tipo: string; nombre_archivo: string; url: string }[]
    /** Tras subir el Boucher Despacho, para refrescar la tarjeta. */
    onArchivoSubido?: () => void
}

function urlArchivo(url: string) {
    return url.startsWith("http") ? url : `${process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000"}${url}`
}

function formatFecha(fecha: string | null) {
    if (!fecha) return "--"
    const d = new Date(fecha)
    return Number.isNaN(d.getTime()) ? "--" : d.toLocaleDateString("es-CL")
}

export function CotizacionNoVigenteInfoDialog({
    open,
    onOpenChange,
    numeroCotizacion,
    datos,
    cotizacionId,
    archivos = [],
    onArchivoSubido
}: CotizacionNoVigenteInfoDialogProps) {
    const inputRef = useRef<HTMLInputElement>(null)
    const [subiendo, setSubiendo] = useState(false)
    // Si se reemplaza, vale el más reciente
    const boucher = archivos
        .filter(a => a.tipo === "boucher_despacho")
        .reduce<typeof archivos[number] | undefined>((ultimo, a) => (!ultimo || a.id > ultimo.id ? a : ultimo), undefined)

    async function subirBoucher(file: File) {
        if (!cotizacionId) return
        setSubiendo(true)
        try {
            const formData = new FormData()
            formData.append("archivo", file)
            formData.append("tipo", "boucher_despacho")
            await api.post(`/api/cotizaciones/${cotizacionId}/archivos`, formData)
            toast.success("Boucher Despacho adjuntado")
            onArchivoSubido?.()
        } catch (e: any) {
            toast.error(e?.message || "No se pudo subir el Boucher Despacho")
        } finally {
            setSubiendo(false)
            if (inputRef.current) inputRef.current.value = ""
        }
    }
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="mx-auto mt-2 mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                        <Ban className="h-6 w-6 text-slate-600" />
                    </div>
                    <DialogTitle className="text-center text-xl">
                        Cotización No Vigente {numeroCotizacion}
                    </DialogTitle>
                    <DialogDescription className="text-center">
                        Por qué salió del flujo comercial
                    </DialogDescription>
                </DialogHeader>

                {datos && (
                    <div className="my-2 space-y-4">
                        <div className="flex justify-center">
                            <Badge className={cn("rounded-full px-3 py-1 text-sm font-medium", MOTIVO_BADGE_CLASS[datos.motivo])}>
                                {etiquetaMotivo(datos)}
                            </Badge>
                        </div>

                        <div className="rounded-lg border bg-muted/40 p-4">
                            <p className="mb-1 text-xs font-semibold uppercase text-muted-foreground">Observación</p>
                            <p className="text-sm text-foreground whitespace-pre-wrap leading-relaxed">
                                {datos.observacion || "No se registró una observación."}
                            </p>
                        </div>

                        {datos.motivo === "finalizada" && cotizacionId && (
                            <div className="flex items-center justify-between gap-3 rounded-lg border p-3">
                                <div className="flex min-w-0 items-center gap-2">
                                    <Truck className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium">Boucher Despacho</p>
                                        {boucher ? (
                                            <a href={urlArchivo(boucher.url)} target="_blank" rel="noreferrer" className="block truncate text-xs text-blue-600 hover:underline" title={boucher.nombre_archivo}>
                                                {boucher.nombre_archivo}
                                            </a>
                                        ) : (
                                            <p className="text-xs text-amber-600">Pendiente</p>
                                        )}
                                    </div>
                                </div>
                                <input
                                    ref={inputRef}
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    className="hidden"
                                    onChange={(e) => { const f = e.target.files?.[0]; if (f) subirBoucher(f) }}
                                />
                                <Button size="sm" variant="outline" disabled={subiendo} onClick={() => inputRef.current?.click()}>
                                    {subiendo ? <Loader2 className="mr-1 h-4 w-4 animate-spin" /> : <Upload className="mr-1 h-4 w-4" />}
                                    {boucher ? "Reemplazar" : "Subir"}
                                </Button>
                            </div>
                        )}

                        <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                            <span className="flex items-center gap-1.5">
                                <Calendar className="h-3.5 w-3.5" />
                                {formatFecha(datos.fecha)}
                            </span>
                            {datos.por && (
                                <span className="flex items-center gap-1.5">
                                    <User className="h-3.5 w-3.5" />
                                    {datos.por}
                                </span>
                            )}
                        </div>
                    </div>
                )}

                <DialogFooter className="sm:justify-center">
                    <Button variant="outline" onClick={() => onOpenChange(false)} className="w-full sm:w-auto px-8">
                        Cerrar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
