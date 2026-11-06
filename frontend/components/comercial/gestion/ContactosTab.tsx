"use client"

import { useEffect, useState } from "react"
import { api } from "@/lib/api"
import { toast } from "sonner"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/components/ui/command"
import { Plus, Search, Pencil, Trash2, User, X, ShieldCheck, Building2 } from "lucide-react"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { formatPhone, formatRut, validateEmail, validateRut } from "@/lib/utils"
import { CargoSelector } from "@/components/comercial/CargoSelector"
import { BitacoraContacto } from "@/components/comercial/BitacoraContacto"
import { ContactoMeta } from "@/components/comercial/ContactoMeta"
import { NomContacCelu } from "@/components/comercial/NomContacCelu"
import { useAuth } from "@/hooks/useAuth"
import { AsignarContactoDialog } from "@/components/comercial/AsignarContactoDialog"
import { estaSinAsignar } from "@/lib/asignacion-contacto"

type ComunaRef = { id: number; nombre: string } | null
type EntidadRef = { id: string; nombre: string }
type ContactoRow = {
    id: number
    nombre: string
    rut?: string | null
    telefono?: string | null
    email?: string | null
    cargo?: string | null
    cargo_id?: number | null
    cargo_obj?: { id: number; nombre: string } | null
    cuerpos?: (EntidadRef & { comuna?: ComunaRef })[]
    companias?: (EntidadRef & { cuerpo?: (EntidadRef & { comuna?: ComunaRef }) | null })[]
    cliente?: { id: string; nombre_empresa: string } | null
    /** Nombre para el celular del ejecutivo; null si no es de la Red de Bomberos. */
    nom_contac_celu?: string | null
    fecha_nacimiento?: string | null
    created_at?: string | null
    updated_at?: string | null
}

type EntidadOpcion = { kind: "cuerpo" | "compania"; id: string; nombre: string; subtitulo?: string | null }

/** Filtro por asignación (backend: GET /api/contactos?asignacion=...). "todos" no filtra. */
type Asignacion = "todos" | "sin_asignar" | "red_bomberos" | "empresa"
type Conteos = { total: number; sin_asignar: number; red_bomberos: number; empresa: number }

const ASIGNACIONES: { valor: Asignacion; etiqueta: string; conteo: keyof Conteos }[] = [
    { valor: "todos", etiqueta: "Todos", conteo: "total" },
    { valor: "sin_asignar", etiqueta: "Sin asignar", conteo: "sin_asignar" },
    { valor: "red_bomberos", etiqueta: "Red de Bomberos", conteo: "red_bomberos" },
    { valor: "empresa", etiqueta: "Empresa o particular", conteo: "empresa" },
]

function safeList<T>(res: any): T[] {
    return Array.isArray(res) ? res : (res?.data || [])
}

const emptyForm = {
    nombre: "",
    rut: "",
    telefono: "",
    email: "",
    cargo: "",
    cargo_id: null as number | null,
}

export function ContactosTab() {
    const [items, setItems] = useState<ContactoRow[]>([])
    const [total, setTotal] = useState<number | null>(null)
    const [loading, setLoading] = useState(false)
    const [query, setQuery] = useState("")
    const [asignacion, setAsignacion] = useState<Asignacion>("todos")
    const [conteos, setConteos] = useState<Conteos | null>(null)
    const [asignarA, setAsignarA] = useState<ContactoRow | null>(null)

    const [open, setOpen] = useState(false)
    const [editing, setEditing] = useState<ContactoRow | null>(null)
    const [form, setForm] = useState({ ...emptyForm })
    const [rutTouched, setRutTouched] = useState(false)
    const [cuerpoIds, setCuerpoIds] = useState<EntidadRef[]>([])
    const [companiaIds, setCompaniaIds] = useState<(EntidadRef & { subtitulo?: string | null })[]>([])
    const [clienteSuelto, setClienteSuelto] = useState<EntidadRef | null>(null)
    const [saving, setSaving] = useState(false)
    const [error, setError] = useState<string | null>(null)

    // La bitácora es información de auditoría: solo la ve la administración.
    const { user } = useAuth()
    const isAdmin = user?.role === "superadmin" || user?.role === "admin"
    const [bitacoraDe, setBitacoraDe] = useState<ContactoRow | null>(null)

    const [entidadOpciones, setEntidadOpciones] = useState<EntidadOpcion[]>([])
    const [clienteOpciones, setClienteOpciones] = useState<EntidadRef[]>([])
    const [openEntidadCombo, setOpenEntidadCombo] = useState(false)
    const [openClienteCombo, setOpenClienteCombo] = useState(false)

    async function load(filtro: Asignacion = asignacion) {
        setLoading(true)
        try {
            const params = new URLSearchParams()
            params.append("per_page", "100")
            if (query) params.append("search", query)
            if (filtro !== "todos") params.append("asignacion", filtro)
            const data = await api.get<any>(`/api/contactos?${params.toString()}`)
            setItems(safeList<ContactoRow>(data))
            setTotal(typeof data?.total === "number" ? data.total : null)
        } catch (e: any) {
            toast.error(e?.message || "Error cargando contactos")
        } finally {
            setLoading(false)
        }
    }

    async function loadConteos() {
        try {
            setConteos(await api.get<Conteos>("/api/contactos/conteos"))
        } catch {
            setConteos(null)
        }
    }

    /** Tras crear, editar, eliminar o asignar cambian la lista y cuántos quedan sin asignar. */
    async function recargar() {
        await Promise.all([load(), loadConteos()])
    }

    useEffect(() => {
        load()
        loadConteos()
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [])

    function cambiarAsignacion(valor: Asignacion) {
        setAsignacion(valor)
        load(valor)
    }

    async function searchEntidades(q: string) {
        try {
            const [cuerposRes, companiasRes] = await Promise.all([
                api.get<any>(`/api/cuerpos?search=${q}`),
                api.get<any>(`/api/companias?search=${q}`),
            ])
            const cuerpos: EntidadOpcion[] = safeList<any>(cuerposRes).map((c) => ({ kind: "cuerpo", id: String(c.id), nombre: c.nombre }))
            const companias: EntidadOpcion[] = safeList<any>(companiasRes).map((c) => ({ kind: "compania", id: String(c.id), nombre: c.nombre, subtitulo: c.cuerpo?.nombre }))
            setEntidadOpciones([...cuerpos, ...companias])
        } catch (e) {
            console.error(e)
        }
    }

    async function searchClientes(q: string) {
        try {
            const res = await api.get<any>(`/api/clientes?search=${q}&per_page=20&tipo=empresa,particular`)
            setClienteOpciones(safeList<any>(res).map((c) => ({ id: String(c.id), nombre: c.nombre_empresa })))
        } catch (e) {
            console.error(e)
        }
    }

    function openNew() {
        setEditing(null)
        setForm({ ...emptyForm })
        setCuerpoIds([])
        setCompaniaIds([])
        setClienteSuelto(null)
        setRutTouched(false)
        setError(null)
        setOpen(true)
    }

    function openEdit(c: ContactoRow) {
        setEditing(c)
        setForm({
            nombre: c.nombre,
            rut: c.rut || "",
            telefono: c.telefono || "",
            email: c.email || "",
            cargo: c.cargo || "",
            cargo_id: c.cargo_id ?? null,
        })
        setCuerpoIds((c.cuerpos || []).map((e) => ({ id: e.id, nombre: e.nombre })))
        setCompaniaIds((c.companias || []).map((e) => ({ id: e.id, nombre: e.nombre, subtitulo: e.cuerpo?.nombre })))
        setClienteSuelto(c.cliente ? { id: c.cliente.id, nombre: c.cliente.nombre_empresa } : null)
        setRutTouched(false)
        setError(null)
        setOpen(true)
    }

    const rutValido = !form.rut.trim() || validateRut(form.rut)

    async function guardar() {
        if (!form.nombre.trim()) {
            setError("El nombre es obligatorio.")
            return
        }
        if (form.email && !validateEmail(form.email)) {
            setError("El correo no es válido.")
            return
        }
        if (form.rut.trim() && !validateRut(form.rut)) {
            setRutTouched(true)
            setError("El RUT no es válido.")
            return
        }

        setSaving(true)
        setError(null)
        try {
            const payload: any = {
                nombre: form.nombre.trim(),
                rut: form.rut.trim() || null,
                telefono: form.telefono.trim() || null,
                email: form.email.trim() || null,
                cargo: form.cargo || null,
                cargo_id: form.cargo_id,
                cuerpo_ids: cuerpoIds.map((e) => e.id),
                compania_ids: companiaIds.map((e) => e.id),
                cliente_id: clienteSuelto?.id ?? null,
            }

            if (editing) {
                await api.patch(`/api/contactos/${editing.id}`, payload)
                toast.success("Contacto actualizado")
            } else {
                await api.post("/api/contactos", payload)
                toast.success("Contacto creado")
            }
            setOpen(false)
            await recargar()
        } catch (e: any) {
            setError(e?.data?.message || e?.message || "Error guardando el contacto.")
        } finally {
            setSaving(false)
        }
    }

    async function eliminar(c: ContactoRow) {
        if (!window.confirm(`¿Eliminar a "${c.nombre}"?`)) return
        try {
            await api.del(`/api/contactos/${c.id}`)
            toast.success("Contacto eliminado")
            await recargar()
        } catch (e: any) {
            toast.error(e?.message || "Error eliminando el contacto")
        }
    }

    function addEntidad(op: EntidadOpcion) {
        if (op.kind === "cuerpo") {
            if (!cuerpoIds.some((e) => e.id === op.id)) setCuerpoIds([...cuerpoIds, { id: op.id, nombre: op.nombre }])
        } else {
            if (!companiaIds.some((e) => e.id === op.id)) setCompaniaIds([...companiaIds, { id: op.id, nombre: op.nombre, subtitulo: op.subtitulo }])
        }
        setOpenEntidadCombo(false)
    }

    function orgResumen(c: ContactoRow): string {
        const partes = [
            ...(c.cuerpos || []).map((e) => e.nombre),
            ...(c.companias || []).map((e) => e.nombre),
        ]
        if (c.cliente) partes.push(c.cliente.nombre_empresa)
        return partes.join(", ")
    }

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2">
                <div className="relative flex-1">
                    <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={(e) => e.key === "Enter" && load()}
                        placeholder="Buscar contacto por nombre, RUT, email, Cuerpo o Compañía..."
                        className="pl-8"
                    />
                </div>
                <Select value={asignacion} onValueChange={(v) => cambiarAsignacion(v as Asignacion)}>
                    <SelectTrigger className="w-[230px]" aria-label="Filtrar por asignación">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {ASIGNACIONES.map((a) => (
                            <SelectItem key={a.valor} value={a.valor}>
                                {a.etiqueta}
                                {conteos ? ` (${conteos[a.conteo].toLocaleString("es-CL")})` : ""}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button variant="outline" onClick={() => load()} disabled={loading}>
                    {loading ? "Cargando..." : "Buscar"}
                </Button>
                <Button onClick={openNew}>
                    <Plus className="h-4 w-4" />
                    Nuevo Contacto
                </Button>
            </div>

            {asignacion === "sin_asignar" && (
                <p className="text-xs text-muted-foreground">
                    Contactos sin Cuerpo, Compañía ni Empresa: se cotizan como persona natural. Usa <span className="font-medium">Asignar</span> cuando sepas de dónde son.
                </p>
            )}
            {total !== null && total > items.length && (
                <p className="text-xs text-muted-foreground">
                    Mostrando {items.length.toLocaleString("es-CL")} de {total.toLocaleString("es-CL")}. Busca o filtra para acotar.
                </p>
            )}

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
                                        <User className="h-4 w-4 text-slate-400 shrink-0" />
                                        <div className="min-w-0">
                                            <div className="text-sm font-semibold truncate flex items-center gap-2">
                                                {c.nombre}
                                                {c.cargo_obj?.nombre && (
                                                    <span className="text-[10px] bg-gray-100 px-1.5 py-0.5 rounded text-gray-500 shrink-0">{c.cargo_obj.nombre}</span>
                                                )}
                                            </div>
                                            {estaSinAsignar(c) ? (
                                                <Badge variant="outline" className="mt-0.5 border-amber-200 bg-amber-50 text-[10px] font-medium text-amber-800">
                                                    Sin asignar
                                                </Badge>
                                            ) : (
                                                <div className="text-xs text-muted-foreground truncate">{orgResumen(c)}</div>
                                            )}
                                            <ContactoMeta contacto={c} className="mt-0.5" />
                                            <NomContacCelu valor={c.nom_contac_celu} className="mt-1" />
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        {estaSinAsignar(c) && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="border-emerald-200 text-emerald-700 hover:bg-emerald-50"
                                                onClick={() => setAsignarA(c)}
                                                title="Asignar a un Cuerpo, Compañía o Empresa"
                                            >
                                                <Building2 className="h-4 w-4 mr-1" /> Asignar
                                            </Button>
                                        )}
                                        {isAdmin && (
                                            <Button variant="ghost" size="sm" onClick={() => setBitacoraDe(c)} title="Ver quién gestionó este contacto">
                                                <ShieldCheck className="h-4 w-4 mr-1" /> Historial
                                            </Button>
                                        )}
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

            <AsignarContactoDialog
                open={!!asignarA}
                onOpenChange={(v) => !v && setAsignarA(null)}
                contacto={asignarA}
                onAsignado={() => recargar()}
            />

            <Dialog open={!!bitacoraDe} onOpenChange={(v) => !v && setBitacoraDe(null)}>
                <DialogContent className="sm:max-w-[640px] max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <ShieldCheck className="h-4 w-4 text-slate-500" />
                            Bitácora de {bitacoraDe?.nombre}
                        </DialogTitle>
                    </DialogHeader>
                    {bitacoraDe && <BitacoraContacto contactoId={bitacoraDe.id} enabled={isAdmin} />}
                </DialogContent>
            </Dialog>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-[560px] max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{editing ? `Editar ${editing.nombre}` : "Nuevo Contacto"}</DialogTitle>
                    </DialogHeader>

                    <div className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label>Nombre completo</Label>
                            <Input
                                value={form.nombre}
                                onChange={(e) => setForm({ ...form, nombre: e.target.value })}
                                placeholder="Ej: Juan Pérez"
                            />
                        </div>

                        <CargoSelector
                            value={form.cargo_id}
                            onChange={(cargo) => setForm({ ...form, cargo_id: cargo.id, cargo: cargo.nombre })}
                        />

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-1.5">
                                <Label>RUT</Label>
                                <Input
                                    value={form.rut}
                                    onChange={(e) => setForm({ ...form, rut: formatRut(e.target.value) })}
                                    onBlur={() => setRutTouched(true)}
                                    placeholder="12.345.678-9"
                                    className={rutTouched && !rutValido ? "border-red-500 focus-visible:ring-red-500" : ""}
                                />
                                {rutTouched && !rutValido && (
                                    <p className="text-xs text-red-600">RUT inválido, revisa el dígito verificador.</p>
                                )}
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Teléfono</Label>
                                <Input
                                    value={form.telefono}
                                    onChange={(e) => setForm({ ...form, telefono: formatPhone(e.target.value) })}
                                    placeholder="+569 xxxxxxxx"
                                />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label>Email</Label>
                            <Input
                                type="email"
                                value={form.email}
                                onChange={(e) => setForm({ ...form, email: e.target.value })}
                                placeholder="ejemplo@correo.cl"
                            />
                        </div>

                        <div className="grid gap-2 border-t pt-3">
                            <Label>Cuerpos y Compañías</Label>
                            <div className="flex flex-wrap gap-2">
                                {cuerpoIds.map((e) => (
                                    <Badge key={`cuerpo-${e.id}`} variant="outline" className="gap-1 bg-red-50 border-red-200 text-red-700">
                                        {e.nombre}
                                        <button onClick={() => setCuerpoIds(cuerpoIds.filter((x) => x.id !== e.id))}>
                                            <X className="h-3 w-3" />
                                        </button>
                                    </Badge>
                                ))}
                                {companiaIds.map((e) => (
                                    <Badge key={`compania-${e.id}`} variant="outline" className="gap-1 bg-blue-50 border-blue-200 text-blue-700">
                                        {e.nombre}
                                        <button onClick={() => setCompaniaIds(companiaIds.filter((x) => x.id !== e.id))}>
                                            <X className="h-3 w-3" />
                                        </button>
                                    </Badge>
                                ))}
                            </div>
                            <Popover modal open={openEntidadCombo} onOpenChange={setOpenEntidadCombo}>
                                <PopoverTrigger asChild>
                                    <Button variant="outline" size="sm" className="w-full justify-start text-muted-foreground font-normal">
                                        <Search className="mr-2 h-3.5 w-3.5" />
                                        Agregar Cuerpo o Compañía...
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="p-0 w-[400px]" align="start">
                                    <Command shouldFilter={false}>
                                        <CommandInput placeholder="Buscar por nombre..." onValueChange={searchEntidades} />
                                        <CommandList>
                                            <CommandEmpty>Sin resultados.</CommandEmpty>
                                            <CommandGroup>
                                                {entidadOpciones.map((op) => (
                                                    <CommandItem key={`${op.kind}-${op.id}`} value={`${op.kind}-${op.id}`} onSelect={() => addEntidad(op)}>
                                                        <div className="flex flex-col">
                                                            <span>{op.nombre} <span className="text-xs text-muted-foreground">({op.kind === "cuerpo" ? "Cuerpo" : "Compañía"})</span></span>
                                                            {op.subtitulo && <span className="text-xs text-muted-foreground">Pertenece a: {op.subtitulo}</span>}
                                                        </div>
                                                    </CommandItem>
                                                ))}
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <div className="grid gap-2 border-t pt-3">
                            <Label>Cliente suelto (opcional, solo si no pertenece a la Red de Bomberos)</Label>
                            {clienteSuelto ? (
                                <Badge variant="outline" className="gap-1 w-fit bg-emerald-50 border-emerald-200 text-emerald-700">
                                    {clienteSuelto.nombre}
                                    <button onClick={() => setClienteSuelto(null)}>
                                        <X className="h-3 w-3" />
                                    </button>
                                </Badge>
                            ) : (
                                <Popover modal open={openClienteCombo} onOpenChange={setOpenClienteCombo}>
                                    <PopoverTrigger asChild>
                                        <Button variant="outline" size="sm" className="w-full justify-start text-muted-foreground font-normal">
                                            <Search className="mr-2 h-3.5 w-3.5" />
                                            Buscar cliente suelto...
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="p-0 w-[400px]" align="start">
                                        <Command shouldFilter={false}>
                                            <CommandInput placeholder="Buscar por nombre..." onValueChange={searchClientes} />
                                            <CommandList>
                                                <CommandEmpty>Sin resultados.</CommandEmpty>
                                                <CommandGroup>
                                                    {clienteOpciones.map((op) => (
                                                        <CommandItem key={op.id} value={op.id} onSelect={() => { setClienteSuelto(op); setOpenClienteCombo(false) }}>
                                                            {op.nombre}
                                                        </CommandItem>
                                                    ))}
                                                </CommandGroup>
                                            </CommandList>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                            )}
                        </div>

                        {error && <div className="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">{error}</div>}
                    </div>

                    <DialogFooter className="gap-2">
                        <Button variant="outline" onClick={() => setOpen(false)}>Cancelar</Button>
                        <Button onClick={guardar} disabled={saving || (rutTouched && !rutValido)}>
                            {saving ? "Guardando..." : "Guardar"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    )
}
