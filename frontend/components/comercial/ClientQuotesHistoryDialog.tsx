"use client"

import { useEffect, useState } from "react"
import { api } from "@/lib/api"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { format } from "date-fns"
import { es } from "date-fns/locale"
import { Badge } from "@/components/ui/badge"
import { FileText, Eye, ExternalLink } from "lucide-react"
import { CotizacionDetailDialog } from "./CotizacionDetailDialog"

export type HistoryEntity = { kind: "cuerpo" | "compania" | "cliente"; id: string; nombre: string }

const QUERY_PARAM: Record<HistoryEntity["kind"], string> = {
  cuerpo: "cuerpo_id",
  compania: "compania_id",
  cliente: "cliente_id",
}

function getStatusColor(statusName: string) {
    if (!statusName) return "bg-gray-100 text-gray-800 border-gray-200"
    const lower = statusName.toLowerCase()
    if (lower.includes("aprobada") || lower.includes("aprobado") || lower.includes("ganada")) {
        return "bg-green-100 text-green-800 border-green-200"
    }
    if (lower.includes("rechazada") || lower.includes("rechazado") || lower.includes("perdida")) {
        return "bg-red-100 text-red-800 border-red-200"
    }
    if (lower.includes("enviada") || lower.includes("enviado")) {
        return "bg-blue-100 text-blue-800 border-blue-200"
    }
    return "bg-gray-100 text-gray-800 border-gray-200"
}

export function ClientQuotesHistoryDialog({
    open,
    onOpenChange,
    entity
}: {
    open: boolean
    onOpenChange: (open: boolean) => void
    entity: HistoryEntity | null
}) {
    const [quotes, setQuotes] = useState<any[]>([])
    const [loading, setLoading] = useState(false)
    const [selectedQuote, setSelectedQuote] = useState<any | null>(null)

    useEffect(() => {
        if (open && entity) {
            loadQuotes()
        } else {
            setQuotes([])
        }
    }, [open, entity])

    async function loadQuotes() {
        if (!entity) return
        setLoading(true)
        try {
            const param = QUERY_PARAM[entity.kind]
            const data = await api.get<any>(`/api/cotizaciones?${param}=${entity.id}&per_page=100`)
            // The backend returns a paginated response so data.data will hold the items
            const items = (data && data.data) ? data.data : (Array.isArray(data) ? data : [])
            setQuotes(items)
        } catch (error) {
            console.error("Error loading quotes history:", error)
        } finally {
            setLoading(false)
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[900px]">
                <DialogHeader>
                    <DialogTitle>Historial de Cotizaciones - {entity?.nombre}</DialogTitle>
                </DialogHeader>
                <ScrollArea className="h-[450px] mt-2 rounded-md border">
                    <Table>
                        <TableHeader className="bg-muted/50">
                            <TableRow>
                                <TableHead className="w-[120px]">Número</TableHead>
                                <TableHead className="w-[140px]">Fecha</TableHead>
                                <TableHead>Cliente / Compañía</TableHead>
                                <TableHead className="w-[180px]">Estado</TableHead>
                                <TableHead className="text-right w-[140px]">Total Neto</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {loading ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-center h-24 text-muted-foreground">
                                        Cargando cotizaciones...
                                    </TableCell>
                                </TableRow>
                            ) : quotes.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-center h-24 text-muted-foreground">
                                        No hay cotizaciones para mostrar.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                quotes.map((q) => (
                                    <TableRow
                                        key={q.id}
                                        className="hover:bg-muted/50 transition-colors cursor-pointer group"
                                        onClick={() => setSelectedQuote(q)}
                                    >
                                        <TableCell className="font-medium text-foreground relative">
                                            <div className="flex items-center gap-2">
                                                <div className="h-6 w-6 rounded-md bg-muted flex items-center justify-center shrink-0 group-hover:bg-primary/10 group-hover:text-primary transition-colors">
                                                    <FileText className="h-3.5 w-3.5" />
                                                </div>
                                                {q.numero || `CT-${String(q.id).padStart(4, "0")}`}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {q.fecha_creacion ? format(new Date(q.fecha_creacion), "dd MMM yyyy", { locale: es }) : "—"}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {q.cuerpo?.nombre || q.compania?.nombre || q.cliente?.nombre_empresa || "—"}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline" className={getStatusColor(q.estado?.nombre)}>
                                                {q.estado?.nombre || "—"}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right font-semibold">
                                            <div className="flex items-center justify-end gap-3">
                                                <span>
                                                    {q.total_neto ? (
                                                        <span className="text-muted-foreground text-sm font-normal mr-1">$</span>
                                                    ) : null}
                                                    {q.total_neto ? q.total_neto.toLocaleString("es-CL") : "0"}
                                                </span>
                                                <ExternalLink className="h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </ScrollArea>
            </DialogContent>

            {selectedQuote && (
                <CotizacionDetailDialog
                    open={!!selectedQuote}
                    onOpenChange={(open) => !open && setSelectedQuote(null)}
                    cotizacion={selectedQuote}
                    onDeleted={() => {
                        setSelectedQuote(null)
                        loadQuotes()
                    }}
                />
            )}
        </Dialog>
    )
}
