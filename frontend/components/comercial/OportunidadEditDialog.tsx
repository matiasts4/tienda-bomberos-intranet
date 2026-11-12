import { useState, useEffect } from "react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Save, Search, User } from "lucide-react"
import { Oportunidad } from "@/components/OportunidadKanbanCard"
import { api } from "@/lib/api"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"
import { Label } from "@/components/ui/label"
import { validateEmail } from "@/lib/utils"
import { ContactoFields } from "@/components/comercial/ContactoFields"

type EntidadKind = "cuerpo" | "compania" | "cliente"

type EntidadOpcion = {
    kind: EntidadKind
    id: string
    nombre: string
    subtitulo?: string | null
}

type Contacto = {
    id: number;
    nombre: string;
    email?: string;
    telefono?: string;
    cargo?: string;
}

export type ContactoLeadData = {
    nombre_contacto?: string | null
    rut_contacto?: string | null
    cargo_id_contacto?: number | null
    cargo_contacto?: string | null
    numero_contacto?: string | null
    email_contacto?: string | null
}

const contactoVacio = { nombre: "", rut: "", cargo: "", cargo_id: null as number | null, telefono: "", email: "" }

type Props = {
    open: boolean
    onOpenChange: (v: boolean) => void
    oportunidad: Oportunidad
    onSave: (data: Partial<Oportunidad> & ContactoLeadData) => Promise<void>
    saving: boolean
    error?: string | null
    setError: (e: string | null) => void
}

const kindLabel: Record<EntidadKind, string> = {
    cuerpo: "Cuerpo",
    compania: "Compañía",
    cliente: "Cliente",
}

function safeList<T>(res: any): T[] {
    return Array.isArray(res) ? res : (res?.data || [])
}

export function OportunidadEditDialog({ open, onOpenChange, oportunidad, onSave, saving, error, setError }: Props) {
    const [referidoPor, setReferidoPor] = useState(oportunidad.referido_por ?? "")
    const [contactoId, setContactoId] = useState<number | null>(oportunidad.contacto?.id ?? null)
    const [entidad, setEntidad] = useState<EntidadOpcion | null>(null)
    const [opciones, setOpciones] = useState<EntidadOpcion[]>([])
    const [contactsList, setContactsList] = useState<Contacto[]>([])
    const [contactoManual, setContactoManual] = useState(contactoVacio)

    // Initial Load: reconstruye la entidad seleccionada a partir de lo que ya
    // trae la oportunidad (cuerpo, compania o cliente suelto).
    useEffect(() => {
        if (open) {
            setReferidoPor(oportunidad.referido_por ?? "")
            setContactoId(oportunidad.contacto?.id ?? null)
            setContactoManual(contactoVacio)

            if (oportunidad.cuerpo) {
                setEntidad({ kind: "cuerpo", id: oportunidad.cuerpo.id, nombre: oportunidad.cuerpo.nombre })
            } else if (oportunidad.compania) {
                setEntidad({ kind: "compania", id: oportunidad.compania.id, nombre: oportunidad.compania.nombre, subtitulo: oportunidad.compania.cuerpo?.nombre })
            } else if (oportunidad.cliente) {
                setEntidad({ kind: "cliente", id: String(oportunidad.cliente.id), nombre: oportunidad.cliente.nombre_empresa })
            } else {
                setEntidad(null)
            }
        }
    }, [open, oportunidad, setError])

    // Fetch contacts when la entidad cambia
    useEffect(() => {
        if (!entidad) {
            setContactsList([])
            return
        }
        const endpoint =
            entidad.kind === "cuerpo" ? `/api/cuerpos/${entidad.id}/contactos` :
            entidad.kind === "compania" ? `/api/companias/${entidad.id}/contactos` :
            `/api/clientes/${entidad.id}/contactos`
        api.get<Contacto[]>(endpoint)
            .then(data => setContactsList(data || []))
            .catch(console.error)
    }, [entidad])

    // El contacto que ya tiene el lead. Si es una persona sin organización no está en la lista de
    // la entidad elegida, pero sigue siendo su contacto: al guardar el backend la vincula a esa entidad.
    const contactoActual = oportunidad.contacto ?? null
    const esDePersona = !oportunidad.cuerpo && !oportunidad.compania && !oportunidad.cliente && !!contactoActual
    const opcionesContacto: Contacto[] = esDePersona && contactoActual && !contactsList.some((c) => c.id === contactoActual.id)
        ? [{ id: contactoActual.id, nombre: contactoActual.nombre, cargo: contactoActual.cargo ?? undefined }, ...contactsList]
        : contactsList

    const handleSave = () => {
        if (!entidad && !contactoId) {
            setError("Elige un Cuerpo, Compañía o Cliente, o deja un contacto: el lead tiene que ir dirigido a alguien.")
            return
        }
        const manual = entidad && !contactoId
        const hayDatos = Object.entries(contactoManual).some(([k, v]) => k !== "cargo" && v)
        if (manual && hayDatos && !contactoManual.nombre.trim()) {
            setError("Ingrese el nombre del contacto para guardarlo")
            return
        }
        if (manual && contactoManual.email && !validateEmail(contactoManual.email)) {
            setError("El correo del contacto no es válido")
            return
        }
        setError(null)

        onSave({
            // Contacto nuevo: el backend lo guarda en el Cuerpo/Compañía del lead.
            nombre_contacto: manual ? contactoManual.nombre.trim() || null : null,
            rut_contacto: manual ? contactoManual.rut || null : null,
            cargo_id_contacto: manual ? contactoManual.cargo_id : null,
            cargo_contacto: manual ? contactoManual.cargo || null : null,
            numero_contacto: manual ? contactoManual.telefono || null : null,
            email_contacto: manual ? contactoManual.email || null : null,
            referido_por: referidoPor,
            cliente_id: entidad?.kind === "cliente" ? entidad.id : null,
            cuerpo_id: entidad?.kind === "cuerpo" ? entidad.id : null,
            compania_id: entidad?.kind === "compania" ? entidad.id : null,
            contacto_id: contactoId,
        })
    }

    // Busca Cuerpos + Compañías + Clientes en paralelo
    const handleSearchEntidades = async (q: string) => {
        if (q.length < 2) return
        try {
            const [cuerposRes, companiasRes, clientesRes] = await Promise.all([
                api.get<any>(`/api/cuerpos?search=${q}`),
                api.get<any>(`/api/companias?search=${q}`),
                api.get<any>(`/api/clientes?search=${q}&per_page=5&tipo=empresa,particular`),
            ])
            const cuerpos: EntidadOpcion[] = safeList<any>(cuerposRes).map((c) => ({ kind: "cuerpo", id: String(c.id), nombre: c.nombre }))
            const companias: EntidadOpcion[] = safeList<any>(companiasRes).map((c) => ({ kind: "compania", id: String(c.id), nombre: c.nombre, subtitulo: c.cuerpo?.nombre }))
            const clientes: EntidadOpcion[] = safeList<any>(clientesRes).map((c) => ({ kind: "cliente", id: String(c.id), nombre: c.nombre_empresa }))
            setOpciones([...cuerpos, ...companias, ...clientes])
        } catch (e) {
            console.error(e)
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[560px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Editar oportunidad</DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    {/* Entidad Selection */}
                    <div className="space-y-2">
                        <Label>Cuerpo, Compañía o Cliente</Label>
                        <div className="flex gap-2 items-center">
                            {entidad ? (
                                <div className="flex-1 p-2 border rounded-md bg-muted/20 flex justify-between items-center">
                                    <div>
                                        <div className="font-medium text-sm">{entidad.nombre} <span className="text-xs text-muted-foreground">({kindLabel[entidad.kind]})</span></div>
                                        {entidad.subtitulo && <div className="text-xs text-muted-foreground">{entidad.subtitulo}</div>}
                                    </div>
                                    <Button variant="ghost" size="sm" title="Quitar la organización (queda a nombre del contacto)" onClick={() => {
                                        setEntidad(null)
                                        setContactoId(contactoActual?.id ?? null)
                                    }}>
                                        <XIcon className="h-4 w-4" />
                                    </Button>
                                </div>
                            ) : (
                                <div className="flex-1 relative">
                                    <Popover>
                                        <PopoverTrigger asChild>
                                            <Button variant="outline" className="w-full justify-start text-muted-foreground">
                                                <Search className="mr-2 h-4 w-4" />
                                                Buscar...
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="p-0 w-[400px]" align="start">
                                            <div className="p-2 border-b">
                                                <Input
                                                    placeholder="Escribe para buscar..."
                                                    onChange={(e) => handleSearchEntidades(e.target.value)}
                                                    autoFocus
                                                />
                                            </div>
                                            <div className="max-h-[200px] overflow-y-auto">
                                                {opciones.map(op => (
                                                    <button
                                                        key={`${op.kind}-${op.id}`}
                                                        className="w-full text-left px-3 py-2 text-sm hover:bg-muted transition-colors border-b last:border-0"
                                                        onClick={() => {
                                                            setEntidad(op)
                                                            // Un lead de persona natural pasa a la organización con su persona;
                                                            // si ya tenía organización, el contacto se vuelve a elegir de la nueva.
                                                            if (!esDePersona) setContactoId(null)
                                                        }}
                                                    >
                                                        <div className="font-medium">{op.nombre} <span className="text-xs text-muted-foreground">({kindLabel[op.kind]})</span></div>
                                                        {op.subtitulo && <div className="text-xs text-muted-foreground">{op.subtitulo}</div>}
                                                    </button>
                                                ))}
                                                {opciones.length === 0 && <div className="p-4 text-center text-xs text-muted-foreground">No se encontraron resultados</div>}
                                            </div>
                                        </PopoverContent>
                                    </Popover>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Contact Selection */}
                    <div className="space-y-2">
                        <Label>Contacto</Label>
                        {entidad ? (
                            <Select
                                value={contactoId ? String(contactoId) : "new"}
                                onValueChange={(v) => {
                                    if (v === "new") setContactoId(null)
                                    else setContactoId(Number(v))
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar contacto..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="new">-- Nuevo / Manual --</SelectItem>
                                    {opcionesContacto.map(c => (
                                        <SelectItem key={c.id} value={String(c.id)}>
                                            {c.nombre} {c.cargo ? `(${c.cargo})` : ""}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : contactoId && contactoActual ? (
                            <div className="space-y-1 rounded border border-amber-200 bg-amber-50 p-2 text-sm">
                                <div className="flex items-center gap-1.5 font-medium text-amber-900">
                                    <User className="h-3.5 w-3.5" /> {contactoActual.nombre}
                                    <span className="text-xs font-normal text-amber-800">(persona natural)</span>
                                </div>
                                <p className="text-xs text-amber-800">
                                    Elige arriba un Cuerpo, Compañía o Cliente para pasar el lead a esa organización: {contactoActual.nombre} quedará como su contacto.
                                </p>
                            </div>
                        ) : (
                            <div className="text-sm text-yellow-600 bg-yellow-50 p-2 rounded">
                                Selecciona un Cuerpo, Compañía o Cliente para ver sus contactos.
                            </div>
                        )}

                        {entidad && !contactoId && (
                            <div className="border-l-2 pl-4 border-muted pt-2">
                                <ContactoFields form={contactoManual} onChange={setContactoManual} />
                            </div>
                        )}
                    </div>


                    <div className="space-y-1">
                        <Label>Referido por</Label>
                        <Input value={referidoPor} onChange={(e) => setReferidoPor(e.target.value)} placeholder="Referido por" />
                    </div>

                    {error && <div className="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">{error}</div>}
                </div>

                <DialogFooter className="gap-2">
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button onClick={handleSave} disabled={saving}>
                        <Save className="h-4 w-4" />
                        {saving ? "Guardando..." : "Guardar cambios"}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}

function XIcon(props: any) {
    return (
        <svg
            {...props}
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M18 6 6 18" />
            <path d="m6 6 12 12" />
        </svg>
    )
}
