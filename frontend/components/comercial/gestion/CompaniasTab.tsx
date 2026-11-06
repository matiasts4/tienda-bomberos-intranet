"use client"

import { useEffect, useState } from "react"
import { api } from "@/lib/api"
import { toast } from "sonner"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Plus, Search, Pencil, Trash2, Building2 } from "lucide-react"
import { useGeografia } from "@/hooks/use-geografia"
import { ClientForm, ClientData } from "@/components/comercial/ClientForm"
import type { Cuerpo, Compania } from "@/lib/red-bomberos"

const emptyForm: Omit<ClientData, "kind"> = {
    nombre_empresa: "",
    telefono: "",
    correo: "",
    numero_compania: null,
    fecha_fundacion: "",
}

export function CompaniasTab() {
    const { regiones, provincias } = useGeografia()
    const [cuerpos, setCuerpos] = useState<Cuerpo[]>([])
    const [items, setItems] = useState<Compania[]>([])
    const [loading, setLoading] = useState(false)
    const [query, setQuery] = useState("")
    const [filterCuerpo, setFilterCuerpo] = useState<string>("all")

    const [open, setOpen] = useState(false)
    const [editing, setEditing] = useState<Compania | null>(null)
    const [selectedCuerpoId, setSelectedCuerpoId] = useState<string>("")
    const [form, setForm] = useState<Omit<ClientData, "kind">>({ ...emptyForm })
    const [saving, setSaving] = useState(false)
    const [error, setError] = useState<string | null>(null)

    async function loadCuerpos() {
        try {
            const data = await api.get<Cuerpo[]>("/api/cuerpos")
            setCuerpos(Array.isArray(data) ? data : [])
        } catch (e) {
            console.error(e)
        }
    }

    async function load() {
        setLoading(true)
        try {
            const params = new URLSearchParams()
            if (query) params.append("search", query)
            if (filterCuerpo !== "all") params.append("cuerpo_id", filterCuerpo)
            const data = await api.get<Compania[]>(`/api/companias?${params.toString()}`)
            setItems(Array.isArray(data) ? data : [])
        } catch (e: any) {
            toast.error(e?.message || "Error cargando compañías")
        } finally {
            setLoading(false)
        }
    }

    useEffect(() => {
        loadCuerpos()
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [])

    useEffect(() => {
        load()
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filterCuerpo])

    function openNew() {
        setEditing(null)
        setSelectedCuerpoId(filterCuerpo !== "all" ? filterCuerpo : "")
        setForm({ ...emptyForm })
        setError(null)
        setOpen(true)
    }

    function openEdit(c: Compania) {
        setEditing(c)
        setSelectedCuerpoId(c.cuerpo_id)
        setForm({
            nombre_empresa: c.nombre,
            telefono: "",
            correo: "",
            numero_compania: c.numero,
            fecha_fundacion: c.fecha_fundacion || "",
        })
        setError(null)
        setOpen(true)
    }

    async function guardar() {
        if (!form.nombre_empresa.trim()) {
            setError("El nombre es obligatorio.")
            return
        }
        if (!editing && !selectedCuerpoId) {
            setError("Debes seleccionar el Cuerpo al que pertenece.")
            return
        }
        setSaving(true)
        setError(null)
        try {
            if (editing) {
                await api.patch(`/api/companias/${editing.id}`, {
                    nombre: form.nombre_empresa.trim(),
                    numero: form.numero_compania || 1,
                    fecha_fundacion: form.fecha_fundacion?.trim() || null,
                })
                toast.success("Compañía actualizada")
            } else {
                await api.post("/api/companias", {
                    cuerpo_id: selectedCuerpoId,
                    nombre: form.nombre_empresa.trim(),
                    numero: form.numero_compania || 1,
                    // Opcional: se puede crear la compañía sin fecha de fundación.
                    fecha_fundacion: form.fecha_fundacion?.trim() || null,
                })
                toast.success("Compañía creada")
            }
            setOpen(false)
            await load()
        } catch (e: any) {
            setError(e?.data?.message || e?.data?.error || e?.message || "Error guardando la compañía.")
        } finally {
            setSaving(false)
        }
    }

    async function eliminar(c: Compania) {
        if (!window.confirm(`¿Eliminar "${c.nombre}"?`)) return
        try {
            await api.del(`/api/companias/${c.id}`)
            toast.success("Compañía eliminada")
            await load()
        } catch (e: any) {
            toast.error(e?.message || "Error eliminando la compañía")
        }
    }

    const cuerpoSeleccionado = cuerpos.find((c) => c.id === selectedCuerpoId) || editing?.cuerpo || null

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2">
                <div className="relative flex-1">
                    <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={(e) => e.key === "Enter" && load()}
                        placeholder="Buscar compañía..."
                        className="pl-8"
                    />
                </div>
                <Select value={filterCuerpo} onValueChange={setFilterCuerpo}>
                    <SelectTrigger className="w-[220px]">
                        <SelectValue placeholder="Todos los Cuerpos" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos los Cuerpos</SelectItem>
                        {cuerpos.map((c) => (
                            <SelectItem key={c.id} value={c.id}>{c.nombre}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button variant="outline" onClick={load} disabled={loading}>
                    {loading ? "Cargando..." : "Buscar"}
                </Button>
                <Button onClick={openNew}>
                    <Plus className="h-4 w-4" />
                    Nueva Compañía
                </Button>
            </div>

            <div className="rounded-md border">
                <ScrollArea className="h-[520px]">
                    <div className="divide-y">
                        {loading && items.length === 0 ? (
                            <div className="p-4 text-sm text-muted-foreground">Cargando...</div>
                        ) : items.length === 0 ? (
                            <div className="p-4 text-sm text-muted-foreground">No hay registros para mostrar.</div>
                        ) : (
                            items.map((c) => (
                                <div key={c.id} className="px-4 py-3 flex items-center justify-between gap-3 hover:bg-muted/50">
                                    <div className="min-w-0 flex items-center gap-2">
                                        <Building2 className="h-4 w-4 text-slate-400 shrink-0" />
                                        <div className="min-w-0">
                                            <div className="text-sm font-semibold truncate">{c.nombre}</div>
                                            <div className="text-xs text-muted-foreground truncate">{c.cuerpo?.nombre || "—"}</div>
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <Button variant="ghost" size="sm" onClick={() => openEdit(c)}>
                                            <Pencil className="h-4 w-4 mr-1" /> Editar
                                        </Button>
                                        <Button variant="ghost" size="sm" className="text-red-600 hover:text-red-700" onClick={() => eliminar(c)}>
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </ScrollArea>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-[560px] max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{editing ? `Editar ${editing.nombre}` : "Nueva Compañía"}</DialogTitle>
                    </DialogHeader>

                    {!editing && (
                        <div className="grid gap-1">
                            <Label>Cuerpo de Bomberos <span className="text-red-500">*</span></Label>
                            <select
                                value={selectedCuerpoId}
                                onChange={(e) => setSelectedCuerpoId(e.target.value)}
                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option value="">Seleccione un Cuerpo...</option>
                                {cuerpos.map((c) => (
                                    <option key={c.id} value={c.id}>{c.nombre}</option>
                                ))}
                            </select>
                        </div>
                    )}

                    <ClientForm
                        data={{ ...form, id: editing?.id, kind: "compania", cuerpo: cuerpoSeleccionado }}
                        onChange={(d) => setForm(d)}
                        error={error}
                        regiones={regiones}
                        provincias={provincias}
                    />
                    <DialogFooter className="gap-2">
                        <Button variant="outline" onClick={() => setOpen(false)}>Cancelar</Button>
                        <Button onClick={guardar} disabled={saving}>
                            {saving ? "Guardando..." : "Guardar"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    )
}
