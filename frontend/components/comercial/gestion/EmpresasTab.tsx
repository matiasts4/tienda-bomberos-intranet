"use client"

import { useEffect, useState } from "react"
import { api } from "@/lib/api"
import { toast } from "sonner"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Plus, Search, Pencil, Trash2, Briefcase } from "lucide-react"
import { useGeografia } from "@/hooks/use-geografia"
import { EmpresaForm, EmpresaData, emptyEmpresa, emptyEmpresaContacto } from "@/components/comercial/EmpresaForm"

type Cliente = {
    id: string
    tipo?: string | null
    nombre_empresa: string
    rut_empresa?: string | null
    giro?: string | null
    direccion?: string | null
    telefono?: string | null
    correo?: string | null
    region_id?: number | null
    provincia_id?: number | null
    comuna_id?: number | null
    comuna?: { id: number } | null
    logo_url?: string | null
}

function safeArray<T>(maybe: any): T[] {
    if (Array.isArray(maybe)) return maybe
    if (Array.isArray(maybe?.data)) return maybe.data
    return []
}

export function EmpresasTab() {
    const { regiones, provincias } = useGeografia()
    const [items, setItems] = useState<Cliente[]>([])
    const [loading, setLoading] = useState(false)
    const [query, setQuery] = useState("")
    const [filterTipo, setFilterTipo] = useState<string>("all")

    const [open, setOpen] = useState(false)
    const [editing, setEditing] = useState<Cliente | null>(null)
    const [tipoNuevo, setTipoNuevo] = useState<"empresa" | "particular">("empresa")
    const [form, setForm] = useState<EmpresaData>({ ...emptyEmpresa, contacto: { ...emptyEmpresaContacto } })
    const [saving, setSaving] = useState(false)
    const [error, setError] = useState<string | null>(null)

    async function load() {
        setLoading(true)
        try {
            const params = new URLSearchParams()
            params.append("per_page", "200")
            params.append("tipo", "empresa,particular")
            if (query) params.append("search", query)
            const data = await api.get<any>(`/api/clientes?${params.toString()}`)
            const list = safeArray<Cliente>(data)
            setItems(list)
        } catch (e: any) {
            toast.error(e?.message || "Error cargando clientes")
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
        setTipoNuevo("empresa")
        setForm({ ...emptyEmpresa, contacto: { ...emptyEmpresaContacto } })
        setError(null)
        setOpen(true)
    }

    function openEdit(c: Cliente) {
        setEditing(c)
        setForm({
            id: c.id,
            nombre_empresa: c.nombre_empresa || "",
            rut_empresa: c.rut_empresa || "",
            giro: c.giro || "",
            direccion: c.direccion || "",
            telefono: c.telefono || "",
            correo: c.correo || "",
            region_id: c.region_id ?? null,
            provincia_id: c.provincia_id ?? null,
            comuna_id: c.comuna_id ?? c.comuna?.id ?? null,
            logo_url: c.logo_url || null,
            contacto: { ...emptyEmpresaContacto },
        })
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
                await api.patch(`/api/clientes/${editing.id}`, {
                    nombre_empresa: form.nombre_empresa.trim(),
                    rut_empresa: form.rut_empresa?.trim() || null,
                    giro: form.giro?.trim() || null,
                    direccion: form.direccion?.trim() || null,
                    telefono: form.telefono?.trim() || null,
                    correo: form.correo?.trim() || null,
                    region_id: form.region_id,
                    provincia_id: form.provincia_id,
                    comuna_id: form.comuna_id ?? null,
                })
                toast.success("Cliente actualizado")
            } else {
                const payload: any = {
                    tipo: tipoNuevo,
                    nombre_empresa: form.nombre_empresa.trim(),
                    rut_empresa: form.rut_empresa?.trim() || null,
                    giro: form.giro?.trim() || null,
                    direccion: form.direccion?.trim() || null,
                    telefono: form.telefono?.trim() || null,
                    correo: form.correo?.trim() || null,
                    region_id: form.region_id,
                    provincia_id: form.provincia_id,
                    comuna_id: form.comuna_id ?? null,
                }
                if (form.contacto.nombre.trim()) {
                    payload.contacto = {
                        nombre: form.contacto.nombre.trim(),
                        rut: form.contacto.rut?.trim() || null,
                        telefono: form.contacto.telefono?.trim() || null,
                        email: form.contacto.email?.trim() || null,
                        cargo: form.contacto.cargo || null,
                        cargo_id: form.contacto.cargo_id,
                    }
                }
                await api.post("/api/clientes", payload)
                toast.success("Cliente creado")
            }
            setOpen(false)
            await load()
        } catch (e: any) {
            setError(e?.data?.message || e?.message || "Error guardando el cliente. Verifica que el RUT sea válido.")
        } finally {
            setSaving(false)
        }
    }

    async function eliminar(c: Cliente) {
        if (!window.confirm(`¿Eliminar "${c.nombre_empresa}"?`)) return
        try {
            await api.del(`/api/clientes/${c.id}`)
            toast.success("Cliente eliminado")
            await load()
        } catch (e: any) {
            toast.error(e?.message || "Error eliminando el cliente")
        }
    }

    const filtered = filterTipo === "all" ? items : items.filter((c) => (c.tipo || "empresa") === filterTipo)

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2">
                <div className="relative flex-1">
                    <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={(e) => e.key === "Enter" && load()}
                        placeholder="Buscar empresa o persona natural..."
                        className="pl-8"
                    />
                </div>
                <Select value={filterTipo} onValueChange={setFilterTipo}>
                    <SelectTrigger className="w-[180px]">
                        <SelectValue placeholder="Todos" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos</SelectItem>
                        <SelectItem value="empresa">Empresas</SelectItem>
                        <SelectItem value="particular">Personas naturales</SelectItem>
                    </SelectContent>
                </Select>
                <Button variant="outline" onClick={load} disabled={loading}>
                    {loading ? "Cargando..." : "Buscar"}
                </Button>
                <Button onClick={openNew}>
                    <Plus className="h-4 w-4" />
                    Nuevo Cliente
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
                                        <Briefcase className="h-4 w-4 text-emerald-600 shrink-0" />
                                        <div className="min-w-0">
                                            <div className="text-sm font-semibold truncate">{c.nombre_empresa}</div>
                                            <div className="text-xs text-muted-foreground truncate">
                                                {c.tipo === "particular" ? "Persona natural" : "Empresa"} {c.giro && c.tipo !== "particular" ? `• ${c.giro}` : ""}
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
                        <DialogTitle>{editing ? `Editar ${editing.nombre_empresa}` : "Nuevo Cliente"}</DialogTitle>
                    </DialogHeader>

                    {!editing && (
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant={tipoNuevo === "empresa" ? "default" : "outline"}
                                size="sm"
                                onClick={() => setTipoNuevo("empresa")}
                            >
                                Empresa
                            </Button>
                            <Button
                                type="button"
                                variant={tipoNuevo === "particular" ? "default" : "outline"}
                                size="sm"
                                onClick={() => setTipoNuevo("particular")}
                            >
                                Persona natural
                            </Button>
                        </div>
                    )}

                    <EmpresaForm
                        tipo={(editing ? editing.tipo : tipoNuevo) === "particular" ? "particular" : "empresa"}
                        data={form}
                        onChange={setForm}
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
