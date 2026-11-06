"use client"

import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableFooter } from "@/components/ui/table"
import { Badge } from "@/components/ui/badge"
import { Separator } from "@/components/ui/separator"
import { Calendar, User, Building, MapPin } from "lucide-react"

import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { HistoryTimeline, HistorialEtapa } from "@/components/HistoryTimeline"
import { useState } from "react"
import { Ban } from "lucide-react"
import { CotizacionAnulacionDialog } from "./CotizacionAnulacionDialog"
import { ResponsablePanel } from "./ResponsablePanel"
import type { Traspaso, UsuarioRef } from "@/lib/responsables"
import { DestinatarioBadge } from "./DestinatarioBadge"
import { DESTINATARIO_NUMERO_CLASS, tipoDestinatarioDe, type TipoDestinatario } from "@/lib/destinatario"
import { esEstadoNoVigente } from "@/lib/no-vigentes"
import { cn, formatFecha } from "@/lib/utils"

type Cotizacion = {
    id: number
    numero?: string | null
    fecha_creacion?: string | null
    cliente?: {
        id: number;
        nombre_empresa?: string | null;
        nombre_contacto?: string | null;
        direccion?: string | null;
    } | null
    tipo_destinatario?: TipoDestinatario | null
    cuerpo?: { id: string; nombre: string } | null
    compania?: { id: string; nombre: string; cuerpo?: { id: string; nombre: string } | null } | null
    contacto?: { id: number; nombre: string } | null
    oportunidad?: { contacto?: { id: number; nombre: string } | null } | null
    // ... (rest of props implied)
    fecha_vencimiento?: string | null
    total?: number | null
    total_neto?: number | null
    iva?: number | null
    detalles?: Array<{
        id: number;
        sku: string;
        producto?: string;
        cantidad: number;
        precio_unitario?: number;
        total_neto?: number;
    }> | null
    estado?: { nombre: string; color?: string }
    // Responsable actual (user_id), quien la creó y los cambios de responsable
    user_id?: number | null
    creado_por_id?: number | null
    ejecutivo?: UsuarioRef | null
    creador?: UsuarioRef | null
    traspasos?: Traspaso[]
    historial?: HistorialEtapa[]
    gestiones?: Array<{
        id: number
        glosa: string
        fecha_gestion: string
        user?: { id: number; name: string }
    }>
}

import { Button, buttonVariants } from "@/components/ui/button"
import { FileText, Trash2, Edit, Loader2 } from "lucide-react"
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog"

import { api } from "@/lib/api"

type Props = {
    open: boolean
    onOpenChange: (open: boolean) => void
    cotizacion: Cotizacion | null
    onDeleted?: () => void
    /** Se llama con la cotización actualizada tras traspasar el responsable. */
    onTraspasado?: (cotizacion: any) => void
}

function formatCLP(value?: number | null) {
    const n = typeof value === "number" ? value : 0
    return new Intl.NumberFormat("es-CL", { style: "currency", currency: "CLP", maximumFractionDigits: 0 }).format(n)
}

function formatDate(dateStr?: string | null) {
    if (!dateStr) return "Sin fecha"
    const d = new Date(dateStr)
    return isNaN(d.getTime()) ? dateStr : d.toLocaleDateString("es-CL", {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    })
}

// Qué fecha imprime el PDF: la de hoy o la de creación de la cotización
type FechaPdf = "actual" | "creacion"

// Helper sencillo para construir URL
function getPdfUrl(id: number, fecha?: FechaPdf) {
    const base = (process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000").replace(/\/$/, "")
    const query = fecha ? `?fecha=${fecha}` : ""
    return `${base}/api/cotizaciones/${id}/pdf${query}`
}

async function handleDownloadPdf(id: number, fecha?: FechaPdf) {
    try {
        const token = typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null;
        const response = await fetch(getPdfUrl(id, fecha), {
            headers: token ? { 'Authorization': `Bearer ${token}` } : {}
        });

        if (!response.ok) throw new Error("Error al descargar PDF");

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        window.open(url, '_blank');

        setTimeout(() => window.URL.revokeObjectURL(url), 1000);
    } catch (error) {
        console.error("Error PDF:", error);
        alert("No se pudo cargar el PDF de la cotización");
    }
}

export function CotizacionDetailDialog({ open, onOpenChange, cotizacion, onDeleted, onTraspasado }: Props) {
    const [isAnularOpen, setIsAnularOpen] = useState(false)
    const [isFechaPdfOpen, setIsFechaPdfOpen] = useState(false)

    if (!cotizacion) return null

    const cuerpoResuelto = cotizacion.cuerpo ?? cotizacion.compania?.cuerpo ?? null
    const empresa = cuerpoResuelto?.nombre
        ? `Cuerpo de Bomberos de ${cuerpoResuelto.nombre}${cotizacion.compania ? ` – ${cotizacion.compania.nombre}` : ""}`
        : cotizacion.cliente?.nombre_empresa || cotizacion.cliente?.nombre_contacto
            // Sin organización: va a nombre de la persona (persona natural), como en el PDF
            || cotizacion.contacto?.nombre || cotizacion.oportunidad?.contacto?.nombre
            || "Cliente desconocido"
    const titulo = cotizacion.numero ? cotizacion.numero : `CT-${String(cotizacion.id).padStart(4, "0")}`
    // Solo en "Cotización Enviada" se pregunta qué fecha lleva el PDF
    const preguntarFechaPdf = cotizacion.estado?.nombre === 'Cotización Enviada'

    // Calcular totales si no vienen (fallback)
    const items = cotizacion.detalles || []
    const totalCalculado = items.reduce((acc, item) => acc + (item.total_neto || 0), 0)
    const totalDisplay = cotizacion.total || totalCalculado

    // Combinar historial y gestiones
    const historyItems: HistorialEtapa[] = [...(cotizacion.historial || [])]

    if (cotizacion.gestiones) {
        cotizacion.gestiones.forEach(g => {
            historyItems.push({
                id: -g.id, // ID negativo para evitar colisión
                model_type: 'CotizacionGestion',
                model_id: cotizacion.id,
                user_id: g.user?.id || 0,
                created_at: g.fecha_gestion,
                estado_anterior_id: null,
                estado_nuevo_id: 0,
                user: { id: g.user?.id || 0, name: g.user?.name || 'Usuario' },
                estado_nuevo: { id: 0, nombre: 'Gestión', color: '#EAB308' }, // Amarillo
                glosa: g.glosa,
                tipo: 'gestion'
            })
        })
    }

    // Ordenar por fecha descendente
    historyItems.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[780px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <div className="flex items-center justify-between gap-4 pr-12">
                        <div className="shrink-0">
                            <DialogTitle className={cn("text-xl font-bold flex items-center gap-2", DESTINATARIO_NUMERO_CLASS[tipoDestinatarioDe(cotizacion)])}>
                                {titulo}
                            </DialogTitle>
                            <DialogDescription>Detalle de la cotización</DialogDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            {cotizacion.estado && (
                                <Badge variant="outline" className="capitalize">
                                    {cotizacion.estado.nombre}
                                </Badge>
                            )}
                            {cotizacion.estado?.nombre !== 'Ventas' && (
                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <Button variant="destructive" size="sm" className="h-8 gap-1">
                                            <Trash2 className="h-3.5 w-3.5" />
                                            Eliminar
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                Esta acción no se puede deshacer. Esto eliminará permanentemente la cotización.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                            <AlertDialogAction onClick={async () => {
                                                try {
                                                    await api.del(`/api/cotizaciones/${cotizacion.id}`)
                                                    onOpenChange(false)
                                                    if (onDeleted) onDeleted()
                                                } catch (e) {
                                                    console.error("Error eliminando", e)
                                                    alert("Error al eliminar la cotización")
                                                }
                                            }}>
                                                Eliminar
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            )}


                            {cotizacion.estado?.nombre !== 'Ventas' && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1"
                                    onClick={() => {
                                        onOpenChange(false)
                                        // router.push is not available here unless we pass it or use the hook
                                        // We will use window.location.href or try to use useRouter if we add the hook
                                        window.location.href = `/comercial/cotizaciones/${cotizacion.id}`
                                    }}
                                >
                                    <Edit className="h-3.5 w-3.5" />
                                    Editar
                                </Button>
                            )}

                            {!esEstadoNoVigente(cotizacion.estado?.nombre) && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1 text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200"
                                    onClick={() => setIsAnularOpen(true)}
                                >
                                    <Ban className="h-3.5 w-3.5" />
                                    Anular
                                </Button>
                            )}


                            <Button
                                variant="outline"
                                size="sm"
                                className="h-8 gap-1"
                                onClick={() => preguntarFechaPdf
                                    ? setIsFechaPdfOpen(true)
                                    : handleDownloadPdf(cotizacion.id)}
                            >
                                <FileText className="h-3.5 w-3.5" />
                                PDF
                            </Button>

                            <AlertDialog open={isFechaPdfOpen} onOpenChange={setIsFechaPdfOpen}>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>¿Qué fecha debe llevar el PDF?</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            Elige si el encabezado de la cotización muestra la fecha en que se creó o la de hoy.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                        {/* AlertDialogAction trae el estilo primario; text-foreground evita texto blanco sobre fondo blanco */}
                                        <AlertDialogAction
                                            className={cn(buttonVariants({ variant: "outline" }), "text-foreground")}
                                            onClick={() => handleDownloadPdf(cotizacion.id, "creacion")}
                                        >
                                            Creación · {formatFecha(cotizacion.fecha_creacion) || "sin fecha"}
                                        </AlertDialogAction>
                                        <AlertDialogAction onClick={() => handleDownloadPdf(cotizacion.id, "actual")}>
                                            Hoy · {formatFecha(new Date().toLocaleDateString("sv-SE"))}
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </div>
                    </div>
                </DialogHeader>

                <Tabs defaultValue="detalle" className="mt-2">
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="detalle">Detalle</TabsTrigger>
                        <TabsTrigger value="historial">Historial</TabsTrigger>
                    </TabsList>

                    <TabsContent value="detalle">
                        <div className="grid gap-6 py-4">
                            {/* Header Info */}
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div className="space-y-3">
                                    <div className="flex items-start gap-2 text-muted-foreground">
                                        <Building className="h-4 w-4 mt-0.5 text-primary" />
                                        <div>
                                            <span className="font-medium text-foreground flex items-center gap-2">
                                                Cliente
                                                <DestinatarioBadge tipo={tipoDestinatarioDe(cotizacion)} />
                                            </span>
                                            {empresa}
                                        </div>
                                    </div>
                                </div>
                                <div className="space-y-3">
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <Calendar className="h-4 w-4 text-primary" />
                                        <span>Vence: <strong className="text-foreground">{formatDate(cotizacion.fecha_vencimiento)}</strong></span>
                                    </div>
                                </div>
                            </div>

                            <ResponsablePanel
                                tarjeta={cotizacion}
                                tipo="cotizaciones"
                                tarjetaId={cotizacion.id}
                                codigo={titulo}
                                onTraspasado={onTraspasado}
                            />

                            <Separator />

                            {/* Product Table */}
                            <div>
                                <h3 className="font-semibold mb-3">Productos</h3>
                                <div className="rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="w-[100px]">SKU</TableHead>
                                                <TableHead>Producto</TableHead>
                                                <TableHead className="text-right">Cant.</TableHead>
                                                <TableHead className="text-right">Precio</TableHead>
                                                <TableHead className="text-right">Total</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {items.length === 0 ? (
                                                <TableRow>
                                                    <TableCell colSpan={5} className="text-center py-4 text-muted-foreground">
                                                        Sin productos
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                items.map((item, idx) => (
                                                    <TableRow key={item.id || idx}>
                                                        <TableCell className="font-mono text-xs text-muted-foreground">
                                                            {item.sku}
                                                        </TableCell>
                                                        <TableCell>
                                                            <span className="font-medium">
                                                                {item.producto || "Producto sin nombre"}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell className="text-right">{item.cantidad}</TableCell>
                                                        <TableCell className="text-right">
                                                            {(item.precio_unitario !== null && item.precio_unitario !== undefined) ? formatCLP(item.precio_unitario) : "-"}
                                                        </TableCell>
                                                        <TableCell className="text-right font-medium">
                                                            {(item.total_neto !== null && item.total_neto !== undefined) ? formatCLP(item.total_neto) : "-"}
                                                        </TableCell>
                                                    </TableRow>
                                                ))
                                            )}
                                        </TableBody>
                                        <TableFooter className="bg-transparent border-t-2">
                                            <TableRow className="hover:bg-transparent">
                                                <TableCell colSpan={4} className="text-right py-1 text-muted-foreground font-normal">Subtotal Neto</TableCell>
                                                <TableCell className="text-right py-1">{formatCLP(cotizacion.total_neto)}</TableCell>
                                            </TableRow>
                                            <TableRow className="hover:bg-transparent border-b-0">
                                                <TableCell colSpan={4} className="text-right py-1 text-muted-foreground font-normal">IVA (19%)</TableCell>
                                                <TableCell className="text-right py-1">{formatCLP(cotizacion.iva)}</TableCell>
                                            </TableRow>
                                            <TableRow className="hover:bg-transparent bg-muted/30">
                                                <TableCell colSpan={4} className="text-right font-bold text-lg">Total</TableCell>
                                                <TableCell className="text-right font-bold text-lg">{formatCLP(totalDisplay)}</TableCell>
                                            </TableRow>
                                        </TableFooter>
                                    </Table>
                                </div>
                            </div>

                            {/* Totals Div Removed */}
                        </div>
                    </TabsContent>

                    <TabsContent value="historial" className="pt-4">
                        <HistoryTimeline
                            historial={historyItems}
                            creationDate={cotizacion.fecha_creacion || undefined}
                            createdByName={cotizacion.creador?.name || "Sistema"}
                            traspasos={cotizacion.traspasos}
                        />
                    </TabsContent>
                </Tabs>

            </DialogContent>

            <CotizacionAnulacionDialog
                open={isAnularOpen}
                onOpenChange={setIsAnularOpen}
                numeroCotizacion={titulo}
                onConfirm={async (motivo) => {
                    try {
                        await api.post(`/api/cotizaciones/${cotizacion.id}/anular`, { motivo })
                        onOpenChange(false)
                        if (onDeleted) onDeleted()
                    } catch (e) {
                        console.error("Error anulando", e)
                        alert("Error al anular la cotización")
                    }
                }}
            />
        </Dialog>
    )
}
