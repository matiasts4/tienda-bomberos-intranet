"use client"

import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { AlertTriangle, Loader2 } from "lucide-react"

type Props = {
    open: boolean
    onOpenChange: (open: boolean) => void
    onConfirm: () => Promise<void>
    loading?: boolean
    userName?: string
}

export function UserDeleteDialog({ open, onOpenChange, onConfirm, loading, userName }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-red-600">
                        <AlertTriangle className="h-5 w-5" />
                        Eliminar usuario
                    </DialogTitle>
                    <DialogDescription>
                        ¿Estás seguro que quieres eliminar a <strong>{userName}</strong>? Esta acción no se puede deshacer y el
                        usuario perderá acceso al sistema inmediatamente.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
                        Cancelar
                    </Button>
                    <Button variant="destructive" onClick={onConfirm} disabled={loading}>
                        {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        Confirmar eliminación
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
