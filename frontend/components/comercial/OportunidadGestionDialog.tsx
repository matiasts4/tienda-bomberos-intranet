"use client"

import { FechaInput } from "@/components/ui/fecha-input"
import { useState, useEffect } from "react"
import { api } from "@/lib/api"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"
import { Textarea } from "@/components/ui/textarea"
import { format } from "date-fns"
import { es } from "date-fns/locale"
import { Oportunidad } from "../OportunidadKanbanCard"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"

type Gestion = {
    id: number
    tipo_contacto: string
    glosa: string
    fecha_gestion: string
    fecha_vencimiento_nueva?: string | null
    user?: { name: string; last_name?: string }
}

type Props = {
    open: boolean
    onOpenChange: (open: boolean) => void
    oportunidad: Oportunidad
    onGestionAgregada?: () => void
}

export function OportunidadGestionDialog({ open, onOpenChange, oportunidad, onGestionAgregada }: Props) {
    const [gestiones, setGestiones] = useState<Gestion[]>([])
    const [loading, setLoading] = useState(false)
    const [saving, setSaving] = useState(false)

    // Form
    const [tipoContacto, setTipoContacto] = useState("Primera Llamada")
    const [glosa, setGlosa] = useState("")
    const [fechaGestion, setFechaGestion] = useState(() => new Date().toISOString().slice(0, 16))
    const [proxContacto, setProxContacto] = useState("")
    const [error, setError] = useState<string | null>(null)

    useEffect(() => {
        if (open && oportunidad) {
            cargarGestiones()
            setTipoContacto("Primera Llamada")
            setGlosa("")
            setFechaGestion(new Date().toISOString().slice(0, 16))
            setProxContacto("")
        }
    }, [open, oportunidad])

    async function cargarGestiones() {
        setLoading(true)
        try {
            const data = await api.get<Gestion[]>(`/api/oportunidades/${oportunidad.id}/gestiones`)
            setGestiones(data || [])
        } catch (e: any) {
            console.error(e)
        } finally {
            setLoading(false)
        }
    }

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault()
        if (!glosa.trim() || !fechaGestion || !tipoContacto) {
            setError("Tipo de contacto, descripción y fecha son obligatorias.")
            return
        }

        setSaving(true)
        setError(null)
        try {
            await api.post(`/api/oportunidades/${oportunidad.id}/gestiones`, {
                tipo_contacto: tipoContacto,
                glosa,
                fecha_gestion: new Date(fechaGestion).toISOString(),
                fecha_vencimiento_nueva: proxContacto ? new Date(proxContacto).toISOString() : null,
            })
            onGestionAgregada?.()
            onOpenChange(false)
        } catch (err: any) {
            setError(err?.data?.message || err.message || "Error al guardar gestión")
        } finally {
            setSaving(false)
        }
    }

    const [descartarDialogOpen, setDescartarDialogOpen] = useState(false)
    const [motivoDescarte, setMotivoDescarte] = useState("")

    async function handleDescartarOportunidad() {
        if (!motivoDescarte.trim()) {
            setError("Debe detallar el motivo del descarte.")
            return
        }
        setSaving(true)
        try {
            // Asumimos que el backend acepta un estado de "Cerrada Perdida" o similar
            // Para Tienda Bomberos, el estado 8 es Cerrada Perdida (Rechazada)
            // Agregamos una gestión final con el motivo
            await api.post(`/api/oportunidades/${oportunidad.id}/gestiones`, {
                tipo_contacto: "Oportunidad Descartada",
                glosa: `MOTIVO DESCARTE: ${motivoDescarte.trim()}`,
                fecha_gestion: new Date().toISOString(),
                fecha_vencimiento_nueva: null,
            })
            // Actualizamos el estado a Rechazada
            await api.patch(`/api/oportunidades/${oportunidad.id}/estado`, {
                estado_id: 8 // Cerrada Perdida
            })
            onGestionAgregada?.()
            setDescartarDialogOpen(false)
            onOpenChange(false)
        } catch (err: any) {
            setError(err?.data?.message || err.message || "Error al descartar")
        } finally {
            setSaving(false)
        }
    }

    return (
        <>
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center justify-between">
                        <span>Gestiones - OP-{String(oportunidad.id).padStart(4, "0")}</span>
                        <div className="flex gap-2 mr-6">
                            <Button 
                                variant="outline" 
                                size="sm" 
                                className="text-red-600 border-red-200 hover:bg-red-50 text-[10px] h-7"
                                onClick={() => {
                                    setMotivoDescarte("")
                                    setDescartarDialogOpen(true)
                                }}
                            >
                                Descartar Oportunidad
                            </Button>
                        </div>
                    </DialogTitle>
                </DialogHeader>

                <div className="max-h-[60vh] overflow-y-auto space-y-4 pr-1">
                    <form onSubmit={handleSubmit} className="space-y-4 border rounded-md p-4 bg-slate-50">
                        <h4 className="font-semibold text-sm">Registrar nueva gestión</h4>

                        <div className="grid gap-2">
                            <Label className="text-xs">Tipo de Contacto</Label>
                            <Select value={tipoContacto} onValueChange={setTipoContacto}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccione tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Primera Llamada">Primera Llamada</SelectItem>
                                    <SelectItem value="Segunda Llamada">Segunda Llamada</SelectItem>
                                    <SelectItem value="Llamada de Seguimiento">Llamada de Seguimiento</SelectItem>
                                    <SelectItem value="Correo Electrónico">Correo Electrónico</SelectItem>
                                    <SelectItem value="Reunión Presencial">Reunión Presencial</SelectItem>
                                    <SelectItem value="Reunión Virtual">Reunión Virtual</SelectItem>
                                    <SelectItem value="WhatsApp / Redes">WhatsApp / Redes</SelectItem>
                                    <SelectItem value="Otro">Otro</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label className="text-xs">Fecha y hora de esta gestión</Label>
                            <Input
                                type="datetime-local"
                                value={fechaGestion}
                                onChange={(e) => setFechaGestion(e.target.value)}
                                required
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label className="text-xs">Descripción de la llamada/contacto (Glosa)</Label>
                            <Textarea
                                rows={3}
                                placeholder="Ej: El cliente mostró interés de compra inmediata..."
                                value={glosa}
                                onChange={(e) => setGlosa(e.target.value)}
                                required
                            />
                        </div>

                        <div className="grid gap-2 border-t pt-3 mt-2">
                            <Label className="text-xs font-semibold text-orange-700">Agendar Próximo Contacto (Reagendamiento)</Label>
                            <p className="text-[10px] text-muted-foreground mt-0 mb-1">
                                Esto actualizará la tarjeta del Kanban para recordarte cuándo debes volver a contactar a este prospecto.
                            </p>
                            <FechaInput
                                value={proxContacto}
                                onChange={v => setProxContacto(v)}
                            />
                        </div>

                        {error && <p className="text-red-600 text-xs">{error}</p>}

                        <div className="flex justify-end gap-2">
                            <Button type="submit" disabled={saving}>
                                {saving ? "Guardando..." : "Guardar Gestión"}
                            </Button>
                        </div>
                    </form>

                    <div className="space-y-3">
                        <h4 className="font-semibold text-sm">Historial</h4>
                        {loading ? (
                            <p className="text-xs text-muted-foreground">Cargando...</p>
                        ) : gestiones.length === 0 ? (
                            <p className="text-xs text-muted-foreground">No hay gestiones registradas.</p>
                        ) : (
                            gestiones.map((g) => (
                                <div key={g.id} className="text-sm border-b pb-2 mb-2 last:border-0">
                                    <div className="flex justify-between items-center text-xs text-muted-foreground mb-1">
                                        <span className="font-medium text-foreground">
                                            {g.user?.name} {g.user?.last_name || ""}
                                        </span>
                                        <span>
                                            {format(new Date(g.fecha_gestion), "dd/MM/yyyy HH:mm", { locale: es })}
                                        </span>
                                    </div>
                                    <div className="mb-1">
                                        <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                            {g.tipo_contacto}
                                        </span>
                                    </div>
                                    <p className="text-slate-700 mt-1">{g.glosa}</p>
                                    {g.fecha_vencimiento_nueva && (
                                        <p className="text-xs text-orange-600 mt-1 flex items-center gap-1">
                                            🗓️ Agendado próximo contacto para el {format(new Date(g.fecha_vencimiento_nueva), "dd/MM/yyyy")}
                                        </p>
                                    )}
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog open={descartarDialogOpen} onOpenChange={setDescartarDialogOpen}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="text-red-600">Descartar Oportunidad</DialogTitle>
                </DialogHeader>
                <div className="py-2 space-y-3">
                    <Label className="text-sm">Motivo del descarte (Obligatorio)</Label>
                    <Textarea 
                        placeholder="Ej: El cliente no cuenta con presupuesto, compró a la competencia, etc..."
                        value={motivoDescarte}
                        onChange={e => setMotivoDescarte(e.target.value)}
                        rows={4}
                        className="resize-none"
                    />
                    <p className="text-xs text-muted-foreground italic">
                        Al descartar, esta oportunidad se moverá al estado 'Cerrada Perdida' y se registrará este motivo en el historial.
                    </p>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setDescartarDialogOpen(false)}>Cancelar</Button>
                    <Button 
                        variant="destructive" 
                        disabled={saving || !motivoDescarte.trim()} 
                        onClick={handleDescartarOportunidad}
                    >
                        Confirmar Descarte
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        </>
    )
}
