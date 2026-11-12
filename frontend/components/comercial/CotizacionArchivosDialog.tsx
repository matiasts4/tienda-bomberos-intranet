"use client"

import { useState } from "react"
import { Upload, X, FileText, CheckCircle } from "lucide-react"
import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { api } from "@/lib/api"
import { toast } from "sonner"
import { cn } from "@/lib/utils"

export type CotizacionArchivo = {
    id: number
    tipo: string
    nombre_archivo: string
    url: string
    created_at?: string
}

interface CotizacionArchivosDialogProps {
    open: boolean
    onOpenChange: (open: boolean) => void
    cotizacionId: number
    archivos: CotizacionArchivo[]
    onSuccess: () => void
    onApprove?: () => void
}

export function CotizacionArchivosDialog({
    open,
    onOpenChange,
    cotizacionId,
    archivos,
    onSuccess,
    onApprove,
}: CotizacionArchivosDialogProps) {
    const [uploading, setUploading] = useState<string | null>(null)

    // Tipos requeridos
    const requiredTypes = [
        { key: "orden_venta", label: "Orden de Venta" },
        { key: "facturacion", label: "Facturación" },
        { key: "pago", label: "Comprobante de Pago" },
    ]

    async function handleFileUpload(file: File, tipo: string, reemplazo = false) {
        setUploading(tipo)
        try {
            const formData = new FormData()
            formData.append("archivo", file)
            formData.append("tipo", tipo)

            await api.post(`/api/cotizaciones/${cotizacionId}/archivos`, formData)
            toast.success(reemplazo ? "Documento reemplazado correctamente" : "Archivo subido correctamente")
            onSuccess() // Refresh parent
        } catch (e: any) {
            console.error(e)
            toast.error(e.message || "Error al subir archivo")
        } finally {
            setUploading(null)
        }
    }

    const hasAtLeastOne = requiredTypes.some(t => archivos.some(a => a.tipo === t.key))

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Documentos Requeridos</DialogTitle>
                    <DialogDescription>
                        Para aprobar esta cotización, debes adjuntar al menos uno de los siguientes documentos.
                        Posteriormente podrás adjuntar los restantes.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {requiredTypes.map((type) => {
                        // Al reemplazar se agrega un archivo nuevo: se muestra el más reciente de cada tipo
                        const uploaded = archivos
                            .filter(a => a.tipo === type.key)
                            .reduce<typeof archivos[number] | undefined>((ultimo, a) => (!ultimo || a.id > ultimo.id ? a : ultimo), undefined)

                        return (
                            <div key={type.key} className="flex items-center justify-between rounded-lg border p-3">
                                <div className="flex items-center gap-3">
                                    <div className={cn(
                                        "flex h-10 w-10 items-center justify-center rounded-full",
                                        uploaded ? "bg-green-100 text-green-600" : "bg-muted text-muted-foreground"
                                    )}>
                                        {uploaded ? <CheckCircle className="h-5 w-5" /> : <FileText className="h-5 w-5" />}
                                    </div>
                                    <div>
                                        <Label className="font-medium">{type.label}</Label>
                                        {uploaded ? (
                                            <p className="text-xs text-muted-foreground max-w-[200px] truncate" title={uploaded.nombre_archivo}>
                                                {uploaded.nombre_archivo}
                                            </p>
                                        ) : (
                                            <p className="text-xs text-red-500">Pendiente</p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex items-center gap-1">
                                    {uploaded && (
                                        <Button variant="ghost" size="sm" asChild>
                                            <a
                                                href={uploaded.url.startsWith("http") ? uploaded.url : `${process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000"}${uploaded.url}`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-blue-600 hover:text-blue-800"
                                            >
                                                Ver
                                            </a>
                                        </Button>
                                    )}
                                    {/* Si ya hay documento, volver a subir lo reemplaza (p.ej. archivo equivocado). */}
                                    <div className="relative">
                                        <input
                                            type="file"
                                            className="absolute inset-0 cursor-pointer opacity-0"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            title={uploaded ? "Reemplazar documento" : "Subir documento"}
                                            onChange={(e) => {
                                                const f = e.target.files?.[0]
                                                if (f) {
                                                    if (f.size > 10 * 1024 * 1024) { // 10MB
                                                        toast.error("El archivo pesa más de 10MB. Por favor sube un archivo más liviano.")
                                                        e.target.value = ""
                                                        return
                                                    }
                                                    handleFileUpload(f, type.key, !!uploaded)
                                                }
                                                e.target.value = ""
                                            }}
                                            disabled={!!uploading}
                                        />
                                        <Button variant={uploaded ? "ghost" : "outline"} size="sm" disabled={!!uploading}>
                                            {uploading === type.key ? "Subiendo..." : uploaded ? "Reemplazar" : "Subir"}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )
                    })}
                </div>

                <DialogFooter>
                    {hasAtLeastOne ? (
                        <div className="flex w-full items-center justify-between">
                            <span className="text-sm text-green-600 font-medium flex items-center gap-1">
                                <CheckCircle className="h-4 w-4" /> Listo para aprobar
                            </span>
                            {onApprove ? (
                                <Button onClick={onApprove} className="bg-green-600 hover:bg-green-700">Aprobar y Mover</Button>
                            ) : (
                                <Button onClick={() => onOpenChange(false)}>Cerrar</Button>
                            )}
                        </div>
                    ) : (
                        <Button variant="secondary" onClick={() => onOpenChange(false)}>Cancelar</Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
