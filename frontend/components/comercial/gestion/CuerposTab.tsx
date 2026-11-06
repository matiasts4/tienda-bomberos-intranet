"use client"

import { useEffect, useState } from "react"
import { api } from "@/lib/api"
import { toast } from "sonner"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Plus, Search, Pencil, Trash2, Flame } from "lucide-react"
import { useGeografia } from "@/hooks/use-geografia"
import { ClientForm, ClientData } from "@/components/comercial/ClientForm"
import type { Cuerpo } from "@/lib/red-bomberos"

const emptyForm: Omit<ClientData, "kind"> = {
    nombre_empresa: "",
    telefono: "",
    correo: "",
    region_id: null,
    provincia_id: null,
    comuna_id: null,
    rut_empresa: "",
    fecha_fundacion: "",
    direccion: "",
    giro: "",
    numero_companias: null,
    logo_url: null,
}

export function CuerposTab() {
    const { regiones, provincias } = useGeografia()
    const [items, setItems] = useState<Cuerpo[]>([])
    const [loading, setLoading] = useState(false)
    const [query, setQuery] = useState("")

    const [open, setOpen] = useState(false)
    const [editing, setEditing] = useState<Cuerpo | null>(null)
    const [form, setForm] = useState<Omit<ClientData, "kind">>({ ...emptyForm })
    const [saving, setSaving] = useState(false)
    const [error, setError] = useState<string | null>(null)
    // Cartera: ejecutivo a cargo ("" = sin asignar)
    const [ejecutivos, setEjecutivos] = useState<{ id: number; nombre: string; codigo_vendedor?: string | null }[]>([])
    const [carteraId, setCarteraId] = useState<string>("")

    useEffect(() => {
        api.get<any[]>("/api/ejecutivos").then(d => setEjecutivos(Array.isArray(d) ? d : [])).catch(() => setEjecutivos([]))
    }, [])

    async function load() {
        setLoading(true)
        try {
            const params = new URLSearchParams()
            if (query) params.append("search", query)
            const data = await api.get<Cuerpo[]>(`/api/cuerpos?${params.toString()}`)
            setItems(Array.isArray(data) ? data : [])
        } catch (e: any) {
            toast.error(e?.message || "Error cargando cuerpos")
        } finally {
            setLoading(false)
        }
    }

    useEffect(() => {
        load()
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [])

    function openNew() {
        setEditing(null)
        setForm({ ...emptyForm })
        setCarteraId("")
        setError(null)
        setOpen(true)
    }

    function openEdit(c: Cuerpo) {
        setEditing(c)
        setForm({
            nombre_empresa: c.nombre,
            telefono: c.telefono || "",
            correo: "",
            region_id: c.region_id ?? null,
            comuna_id: c.comuna_id ?? c.comuna?.id ?? null,
            rut_empresa: c.rut || "",
            fecha_fundacion: c.fecha_fundacion || "",
            direccion: c.direccion || "",
            giro: c.giro || "",
            numero_companias: c.numero_companias || null,
            logo_url: c.logo_url || null,
        })
        setCarteraId(c.ejecutivo_id ? String(c.ejecutivo_id) : "")
        setError(null)
        setOpen(true)
    }

    async function guardar() {
        if (!form.nombre_empresa.trim()) {
            setError("El nombre es obligatorio.")
            return
        }
        setSaving(true)
        setError(null)
        try {
            if (editing) {
                await api.patch(`/api/cuerpos/${editing.id}`, {
                    nombre: form.nombre_empresa.trim(),
                    rut: form.rut_empresa?.trim() || null,
                    fecha_fundacion: form.fecha_fundacion?.trim() || null,
                    direccion: form.direccion?.trim() || null,
                    comuna_id: form.comuna_id ?? null,
                    giro: form.giro?.trim() || null,
                    telefono: form.telefono?.trim() || null,
                    numero_companias: form.numero_companias,
                    ejecutivo_id: carteraId ? Number(carteraId) : null,
                })
                toast.success("Cuerpo actualizado")
            } else {
                if (!form.region_id) {
                    setError("La región es obligatoria.")
                    setSaving(false)
                    return
                }
                await api.post("/api/cuerpos", {
                    region_id: form.region_id,
                    nombre: form.nombre_empresa.trim(),
                    rut: form.rut_empresa?.trim() || null,
                    fecha_fundacion: form.fecha_fundacion?.trim() || null,
                    direccion: form.direccion?.trim() || null,
                    comuna_id: form.comuna_id ?? null,
                    giro: form.giro?.trim() || null,
                    telefono: form.telefono?.trim() || null,
                    numero_companias: form.numero_companias,
                    ejecutivo_id: carteraId ? Number(carteraId) : null,
                })
                toast.success("Cuerpo creado")
            }
            setOpen(false)
            await load()
        } catch (e: any) {
            setError(e?.data?.message || e?.message || "Error guardando el cuerpo. Verifica que el RUT sea válido.")
        } finally {
            setSaving(false)
        }
    }

    async function eliminar(c: Cuerpo) {
        if (!window.confirm(`¿Eliminar "${c.nombre}"? Esto elimina también sus compañías y vínculos de contacto.`)) return
        try {
            await api.del(`/api/cuerpos/${c.id}`)
            toast.success("Cuerpo eliminado")
            await load()
        } catch (e: any) {
            toast.error(e?.message || "Error eliminando el cuerpo")
        }
    }

    const filtered = items

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2">
                <div className="relative flex-1">
                    <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={(e) => e.key === "Enter" && load()}
                        placeholder="Buscar Cuerpo de Bomberos..."
                        className="pl-8"
                    />
                </div>
                <Button variant="outline" onClick={load} disabled={loading}>
                    {loading ? "Cargando..." : "Buscar"}
                </Button>
                <Button onClick={openNew}>
                    <Plus className="h-4 w-4" />
                    Nuevo Cuerpo
                </Button>
            </div>

            <div className="rounded-md border">
                <ScrollArea className="h-[520px]">
                    <div className="divide-y">
                        {loading && filtered.length === 0 ? (
                            <div className="p-4 text-sm text-muted-foreground">Cargando...</div>
                        ) : filtered.length === 0 ? (
                            <div className="p-4 text-sm text-muted-foreground">No hay registros para mostrar.</div>
                        ) : (
                            filtered.map((c) => (
                                <div key={c.id} className="px-4 py-3 flex items-center justify-between gap-3 hover:bg-muted/50">
                                    <div className="min-w-0 flex items-center gap-2">
                                        <Flame className="h-4 w-4 text-red-500 shrink-0" />
                                        <div className="min-w-0">
                                            <div className="text-sm font-semibold truncate">{c.nombre}</div>
                                            <div className="text-xs text-muted-foreground truncate">
                                                {c.region?.nombre || "Sin región"} {c.rut ? `• ${c.rut}` : ""} {c.numero_companias ? `• ${c.numero_companias} compañías` : ""}
                                                {" • "}
                                                {c.ejecutivo
                                                    ? `Cartera ${c.ejecutivo.codigo_vendedor ? `${c.ejecutivo.codigo_vendedor} ` : ""}${c.ejecutivo.name}`
                                                    : <span className="text-amber-700">Sin cartera</span>}
                                            </div>
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
                        <DialogTitle>{editing ? `Editar ${editing.nombre}` : "Nuevo Cuerpo de Bomberos"}</DialogTitle>
                    </DialogHeader>
                    <ClientForm
                        data={{ ...form, id: editing?.id, kind: "cuerpo" }}
                        onChange={(d) => setForm(d)}
                        error={error}
                        regiones={regiones}
                        provincias={provincias}
                    />
                    <div className="space-y-1.5">
                        <Label>Cartera (ejecutivo a cargo)</Label>
                        <Select value={carteraId || "none"} onValueChange={v => setCarteraId(v === "none" ? "" : v)}>
                            <SelectTrigger><SelectValue placeholder="Sin cartera" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Sin cartera</SelectItem>
                                {ejecutivos.map(e => (
                                    <SelectItem key={e.id} value={String(e.id)}>
                                        {e.codigo_vendedor ? `${e.codigo_vendedor} · ` : ""}{e.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            Define el código V1–V4 en el nombre de sus contactos para el celular y su color en el mapa de la red.
                        </p>
                    </div>
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
