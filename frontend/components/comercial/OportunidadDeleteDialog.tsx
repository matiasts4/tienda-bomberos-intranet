import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { AlertTriangle, Trash2 } from "lucide-react"
import { EstadoBadge } from "./EstadoBadge"
import { Oportunidad } from "@/components/OportunidadKanbanCard"

type Props = {
    open: boolean
    onOpenChange: (v: boolean) => void
    oportunidad: Oportunidad
    code: string
    error?: string | null
    deleting: boolean
    onConfirm: () => void
    resolvedEstadoColor: string | null
}

export function OportunidadDeleteDialog({
    open,
    onOpenChange,
    oportunidad,
    code,
    error,
    deleting,
    onConfirm,
    resolvedEstadoColor,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[560px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <AlertTriangle className="h-5 w-5 text-orange-500" />
                        ¿Confirmar eliminación?
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4 text-sm text-muted-foreground">
                    <p>¿Estás seguro que quieres eliminar esta oportunidad? Esta acción no se puede deshacer.</p>

                    <div className="rounded-lg border bg-muted/30 p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div className="space-y-1">
                                <div className="text-xs text-muted-foreground">Oportunidad</div>
                                <div className="font-semibold text-foreground">{code}</div>
                                {(oportunidad as any).empresa && (
                                    <div className="text-xs">
                                        Empresa: <span className="text-foreground">{(oportunidad as any).empresa}</span>
                                    </div>
                                )}
                                {(oportunidad as any).nombre_contacto && (
                                    <div className="text-xs">
                                        Contacto: <span className="text-foreground">{(oportunidad as any).nombre_contacto}</span>
                                    </div>
                                )}
                            </div>

                            <div className="space-y-1 text-right">
                                <div className="text-xs text-muted-foreground">Estado actual</div>
                                <EstadoBadge
                                    nombre={oportunidad.estado?.nombre ?? "Sin estado"}
                                    color={resolvedEstadoColor}
                                />
                            </div>
                        </div>
                    </div>

                    <div className="text-xs text-muted-foreground">
                        Esta acción actualizará el tablero eliminando la oportunidad.
                    </div>

                    {error && <div className="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">{error}</div>}
                </div>

                <DialogFooter className="gap-2">
                    <Button variant="outline" onClick={() => onOpenChange(false)} disabled={deleting}>
                        Cancelar
                    </Button>
                    <Button variant="destructive" onClick={onConfirm} disabled={deleting}>
                        <Trash2 className="h-4 w-4" />
                        {deleting ? "Eliminando..." : "Confirmar eliminación"}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
