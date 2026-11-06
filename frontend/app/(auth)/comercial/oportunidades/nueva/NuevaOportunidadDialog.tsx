"use client"

import React, { useEffect, useMemo, useRef, useState } from "react"
import { useRouter } from "next/navigation"
import { Check, ChevronsUpDown, Loader2, Plus, Search, User, UserPlus } from "lucide-react"

import { useAuthContext } from "@/components/auth-provider"
import { api } from "@/lib/api"
import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command"
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover"
import { cn, formatRut, validateEmail, validateRut } from "@/lib/utils"
import { toast } from "sonner"
import { ContactoFields } from "@/components/comercial/ContactoFields"
import { AsignarContactoDialog, type OrganizacionOpcion } from "@/components/comercial/AsignarContactoDialog"

type EjecutivoUser = {
    id: number
    nombre: string
}

type Estado = {
    id: number
    nombre: string
    orden?: number | null
}

type EntidadKind = "cuerpo" | "compania" | "cliente" | "contacto"

type EntidadOpcion = {
    kind: EntidadKind
    id: string
    nombre: string
    subtitulo?: string | null
    /** Solo para clientes sueltos: "empresa" o "particular" (persona natural). */
    tipo?: string | null
    /** Solo kind "contacto" (una persona): su id y la organización a la que pertenece, si tiene. */
    contactoId?: number
    ancla?: EntidadOpcion | null
    rut?: string | null
}

type Contacto = {
    id: number
    nombre: string
    rut?: string | null
    cargo?: string | null
    cargo_id?: number | null
    email?: string | null
    telefono?: string | null
}

type NuevaOportunidadDialogProps = {
    open: boolean
    onOpenChange: (open: boolean) => void
    onSuccess?: () => void
}

function safeList<T>(res: any): T[] {
    return Array.isArray(res) ? res : (res?.data || [])
}

export function NuevaOportunidadDialog({
    open,
    onOpenChange,
    onSuccess,
}: NuevaOportunidadDialogProps) {
    const router = useRouter()
    const { user } = useAuthContext()

    // Entidad (Cuerpo / Compañía / Cliente suelto) seleccionada
    const [entidad, setEntidad] = useState<EntidadOpcion | null>(null)
    const [contactoId, setContactoId] = useState<number | null>(null)

    // Contacto manual (si no se elige uno existente)
    const contactoVacio = {
        nombre: "",
        rut: "",
        cargo: "",
        cargo_id: null as number | null,
        telefono: "",
        email: "",
    }
    const [contactoManual, setContactoManual] = useState(contactoVacio)
    const [editingContactForm, setEditingContactForm] = useState(contactoVacio)

    const [referidoPor, setReferidoPor] = useState("")
    const [canal, setCanal] = useState("")
    const [userId, setUserId] = useState<string>("")
    const [estadoId, setEstadoId] = useState<string>("")

    // Data
    const [ejecutivos, setEjecutivos] = useState<EjecutivoUser[]>([])
    const [estados, setEstados] = useState<Estado[]>([])
    const [opciones, setOpciones] = useState<EntidadOpcion[]>([])
    const [contactos, setContactos] = useState<Contacto[]>([])

    // UI State
    const [loading, setLoading] = useState(false)
    const [loadingCombos, setLoadingCombos] = useState(false)
    const [searchingEntidades, setSearchingEntidades] = useState(false)
    const [loadingContactos, setLoadingContactos] = useState(false)
    const [error, setError] = useState<string | null>(null)

    // Combobox Open States
    const [openEntidadCombo, setOpenEntidadCombo] = useState(false)

    // Búsquedas: la última escrita manda (las respuestas viejas se descartan) y se espera un instante entre teclas
    const busquedaSeq = useRef(0)
    const busquedaTimer = useRef<ReturnType<typeof setTimeout> | null>(null)

    // Persona elegida en el buscador que pertenece a una organización: la cotización puede ir
    // dirigida a la organización o a la persona misma (como persona natural)
    const [personaElegida, setPersonaElegida] = useState<EntidadOpcion | null>(null)

    // Texto buscado y alta rápida de una persona natural cuando no existe
    const [busqueda, setBusqueda] = useState("")
    const [creandoPersona, setCreandoPersona] = useState(false)
    const [personaForm, setPersonaForm] = useState({ nombre: "", rut: "", telefono: "", correo: "" })
    const [guardandoPersona, setGuardandoPersona] = useState(false)
    const [errorPersona, setErrorPersona] = useState<string | null>(null)
    // Persona sin organización que se asigna aquí mismo, antes de crear el lead
    const [asignarOpen, setAsignarOpen] = useState(false)

    useEffect(() => {
        if (open) {
            loadCombos()
            searchEntidades("") // Pre-load opciones
            setEntidad(null)
            setContactoId(null)
            setContactoManual(contactoVacio)
            setReferidoPor("")
            setCanal("")
            setError(null)
            setBusqueda("")
            setPersonaElegida(null)
            setCreandoPersona(false)
            setPersonaForm({ nombre: "", rut: "", telefono: "", correo: "" })
            setErrorPersona(null)
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open])

    // Cargar contactos cuando cambia la entidad seleccionada
    useEffect(() => {
        if (entidad) {
            loadContactos(entidad)
        } else {
            setContactos([])
            setContactoId(null)
        }
    }, [entidad])

    async function loadCombos() {
        setLoadingCombos(true)
        try {
            const [ejRaw, esRaw] = await Promise.all([
                api.get<any[]>("/api/usuarios/ejecutivos"),
                api.get<any[]>("/api/estados?scope=oportunidad"),
            ])

            const ej = Array.isArray(ejRaw) ? ejRaw : (ejRaw as any).data || []
            const es = Array.isArray(esRaw) ? esRaw : (esRaw as any).data || []

            es.sort((a: Estado, b: Estado) => (a.orden ?? 999) - (b.orden ?? 999) || a.id - b.id)

            setEjecutivos(ej)
            setEstados(es)

            const estadoNuevo = es.find((x: Estado) => (x.nombre || "").toLowerCase() === "nuevo")
            if (estadoNuevo && !estadoId) setEstadoId(String(estadoNuevo.id))

            if (!userId) {
                if (user?.id && ej.find((u: EjecutivoUser) => u.id === user.id)) {
                    setUserId(String(user.id))
                } else if (ej.length === 1) {
                    setUserId(String(ej[0].id))
                }
            }

        } catch (e) {
            console.error(e)
            setError("Error cargando datos iniciales")
        } finally {
            setLoadingCombos(false)
        }
    }

    // Busca Cuerpos + Compañías + Clientes sueltos en paralelo y arma una sola
    // lista con "kind" para saber a qué endpoint pertenece cada opción.
    // Los clientes tipo "bomberos" son espejos heredados de Cuerpos/Compañías;
    // se excluyen para no duplicarlos.
    async function searchEntidades(query: string) {
        const secuencia = ++busquedaSeq.current
        setSearchingEntidades(true)
        try {
            const q = encodeURIComponent(query)
            // Las personas (contactos) son decenas de miles: solo se buscan con 2+ letras
            const buscarPersonas = query.trim().length >= 2
            // allSettled: si una fuente falla (p. ej. Cuerpos) igual se muestran las demás
            const resultados = await Promise.allSettled([
                api.get<any>(`/api/cuerpos?search=${q}`),
                api.get<any>(`/api/companias?search=${q}`),
                api.get<any>(`/api/clientes?search=${q}&per_page=50&tipo=empresa,particular`),
                buscarPersonas
                    ? api.get<any>(`/api/contactos?search=${q}&per_page=20`)
                    : Promise.resolve(null),
            ])
            const [cuerposRes, companiasRes, clientesRes, personasRes] = resultados.map((r) => (r.status === "fulfilled" ? r.value : null))
            resultados.forEach((r) => r.status === "rejected" && console.error(r.reason))

            const cuerpos: EntidadOpcion[] = safeList<any>(cuerposRes).map((c) => ({
                kind: "cuerpo", id: String(c.id), nombre: c.nombre,
            }))
            const companias: EntidadOpcion[] = safeList<any>(companiasRes).map((c) => ({
                kind: "compania", id: String(c.id), nombre: c.nombre, subtitulo: c.cuerpo?.nombre,
            }))
            const clientes: EntidadOpcion[] = safeList<any>(clientesRes).map((c) => ({
                kind: "cliente", id: String(c.id), nombre: c.nombre_empresa, tipo: c.tipo ?? null,
            }))
            const personas: EntidadOpcion[] = safeList<any>(personasRes).map((c) => {
                // Con cuerpo/compañía el lead queda anclado a esa organización (como en Prospección);
                // sin organización ni cliente, la persona es el destinatario.
                const compania = c.companias?.[0]
                const cuerpo = c.cuerpos?.[0]
                const ancla: EntidadOpcion | null = compania
                    ? { kind: "compania", id: String(compania.id), nombre: compania.nombre }
                    : cuerpo
                        ? { kind: "cuerpo", id: String(cuerpo.id), nombre: cuerpo.nombre }
                        : c.cliente
                            ? { kind: "cliente", id: String(c.cliente.id), nombre: c.cliente.nombre_empresa, tipo: c.cliente.tipo ?? null }
                            : null
                const orgs = [cuerpo?.nombre && `Cuerpo ${cuerpo.nombre}`, compania?.nombre].filter(Boolean).join(" · ")
                return {
                    kind: "contacto",
                    id: String(c.id),
                    nombre: c.nombre,
                    contactoId: c.id,
                    ancla,
                    rut: c.rut ?? null,
                    tipo: ancla ? null : "particular",
                    subtitulo: orgs || (c.cliente?.nombre_empresa ?? null),
                }
            })

            if (secuencia !== busquedaSeq.current) return
            setOpciones([...cuerpos, ...companias, ...clientes, ...personas])
        } catch (e) {
            console.error(e)
            if (secuencia === busquedaSeq.current) setOpciones([])
        } finally {
            if (secuencia === busquedaSeq.current) setSearchingEntidades(false)
        }
    }

    async function loadContactos(ent: EntidadOpcion) {
        if (ent.kind === "contacto") {
            setContactos([])
            return
        }
        setLoadingContactos(true)
        try {
            const endpoint =
                ent.kind === "cuerpo" ? `/api/cuerpos/${ent.id}/contactos` :
                ent.kind === "compania" ? `/api/companias/${ent.id}/contactos` :
                `/api/clientes/${ent.id}/contactos`
            const res = await api.get<any>(endpoint)
            setContactos(safeList<Contacto>(res))
        } catch (e) {
            console.error(e)
        } finally {
            setLoadingContactos(false)
        }
    }

    async function onSubmit() {
        if (!userId || !estadoId) {
            setError("Debe seleccionar ejecutivo y estado")
            return
        }
        // Un lead tiene que ir dirigido a alguien: una organización, una persona o un contacto ingresado
        if (!entidad && !contactoId && !contactoManual.nombre.trim()) {
            setError("Busca un Cuerpo, Compañía, Empresa o persona, o ingresa el nombre del contacto.")
            return
        }

        // Solo se valida el contacto que realmente se envía (una persona natural es su propio contacto)
        const editaContacto = !!contactoId && !!selectedContactoDetails
        const contactoForm = editaContacto ? editingContactForm : contactoManual
        const hayDatosContacto = !contactoId && !esPersona && Object.entries(contactoManual).some(([k, v]) => k !== "cargo" && v)
        if (editaContacto && !editingContactForm.nombre.trim()) {
            setError("El nombre del contacto no puede quedar vacío")
            return
        }
        if (hayDatosContacto && !contactoManual.nombre.trim()) {
            setError("Ingrese el nombre del contacto para guardarlo")
            return
        }
        if ((editaContacto || hayDatosContacto) && contactoForm.email && !validateEmail(contactoForm.email)) {
            setError("El correo del contacto no es válido")
            return
        }

        setLoading(true)
        setError(null)

        const payload = {
            user_id: Number(userId),
            estado_id: Number(estadoId),
            referido_por: referidoPor || null,
            canal: canal || null,

            cliente_id: entidad?.kind === "cliente" ? entidad.id : null,
            cuerpo_id: entidad?.kind === "cuerpo" ? entidad.id : null,
            compania_id: entidad?.kind === "compania" ? entidad.id : null,
            contacto_id: contactoId || null,

            // Fallback: contacto nuevo si no se eligió uno existente
            // El backend lo guarda como contacto del Cuerpo/Compañía elegido.
            nombre_contacto: !contactoId && !esPersona ? contactoManual.nombre.trim() || null : null,
            rut_contacto: !contactoId && !esPersona ? contactoManual.rut || null : null,
            cargo_id_contacto: !contactoId && !esPersona ? contactoManual.cargo_id : null,
            cargo_contacto: !contactoId && !esPersona ? contactoManual.cargo || null : null,
            numero_contacto: !contactoId && !esPersona ? contactoManual.telefono || null : null,
            email_contacto: !contactoId && !esPersona ? contactoManual.email || null : null,

            fecha_ingreso: new Date().toISOString().split('T')[0],
        }

        try {
            // Solo se edita un contacto de la lista de la entidad (sus datos se cargaron en el formulario)
            if (editaContacto) {
                await api.patch(`/api/contactos/${contactoId}`, {
                    ...editingContactForm,
                    rut: editingContactForm.rut || null,
                    telefono: editingContactForm.telefono || null,
                    email: editingContactForm.email || null,
                })
            }

            const res = await api.post<any>("/api/oportunidades", payload)

            onOpenChange(false)
            if (onSuccess) onSuccess()

            if (res && res.cotizacion_id) {
                router.push(`/comercial/cotizaciones/${res.cotizacion_id}`)
            } else {
                router.refresh()
            }
        } catch (e: any) {
            setError(e.message || "Error creando oportunidad")
        } finally {
            setLoading(false)
        }
    }

    const selectedContactoDetails = useMemo(() => {
        if (!contactoId) return null
        return contactos.find(c => c.id === contactoId) || null
    }, [contactos, contactoId])

    useEffect(() => {
        if (selectedContactoDetails) {
            setEditingContactForm({
                nombre: selectedContactoDetails.nombre || "",
                rut: selectedContactoDetails.rut || "",
                cargo: selectedContactoDetails.cargo || "",
                cargo_id: selectedContactoDetails.cargo_id ?? null,
                telefono: selectedContactoDetails.telefono || "",
                email: selectedContactoDetails.email || ""
            })
        }
    }, [selectedContactoDetails])

    // Elegir una persona (contacto) deja elegida su organización y a ella como contacto
    function elegirOpcion(op: EntidadOpcion) {
        setError(null)
        if (op.kind === "contacto") {
            setEntidad(op.ancla ?? op)
            setContactoId(op.contactoId ?? null)
            setPersonaElegida(op.ancla ? op : null)
        } else {
            setEntidad(op)
            setContactoId(null)
            setPersonaElegida(null)
        }
        setOpenEntidadCombo(false)
    }

    // A quién va dirigida la cotización de una persona que pertenece a una organización
    const dirigidaALaPersona = !!personaElegida && entidad?.kind === "contacto"

    function dirigirA(laPersona: boolean) {
        if (!personaElegida) return
        setContactoId(personaElegida.contactoId ?? null)
        setEntidad(laPersona ? { ...personaElegida, ancla: null, tipo: "particular" } : personaElegida.ancla ?? null)
    }

    // Una persona natural es su propio contacto: no se pide contacto aparte
    const esPersona = entidad?.kind === "contacto" || (entidad?.kind === "cliente" && entidad.tipo === "particular")

    // Persona registrada sin Cuerpo, Compañía ni Empresa: se puede asignar antes de crear el lead
    const personaSinOrganizacion = entidad?.kind === "contacto" && !entidad.ancla && !!entidad.contactoId

    /** Tras asignarla, el lead va a la organización con la persona como contacto. */
    function usarOrganizacionAsignada(org: OrganizacionOpcion) {
        const kind: EntidadKind = org.tipo === "empresa" ? "cliente" : org.tipo
        setEntidad({ kind, id: org.id, nombre: org.nombre, subtitulo: org.subtitulo ?? null, tipo: org.tipo === "empresa" ? "empresa" : null })
        setPersonaElegida(null)
    }

    // El texto buscado se aprovecha para precargar el alta: un RUT va al RUT, lo demás al nombre
    function abrirAltaPersona(texto: string) {
        const t = texto.trim()
        const pareceRut = /^[\d.\-kK]+$/.test(t) && t.replace(/\D/g, "").length >= 7
        setPersonaForm({ nombre: pareceRut ? "" : t, rut: pareceRut ? formatRut(t) : "", telefono: "", correo: "" })
        setErrorPersona(null)
        setEntidad(null)
        setCreandoPersona(true)
        setOpenEntidadCombo(false)
    }

    async function guardarPersona() {
        const nombre = personaForm.nombre.trim()
        if (!nombre) {
            setErrorPersona("El nombre es obligatorio.")
            return
        }
        if (personaForm.rut.trim() && !validateRut(personaForm.rut)) {
            setErrorPersona("RUT inválido: revisa el dígito verificador.")
            return
        }

        setGuardandoPersona(true)
        setErrorPersona(null)
        try {
            const cliente = await api.post<any>("/api/clientes", {
                tipo: "particular",
                nombre_empresa: nombre,
                rut_empresa: personaForm.rut.trim() || null,
                telefono: personaForm.telefono.trim() || null,
                correo: personaForm.correo.trim() || null,
            })
            setEntidad({ kind: "cliente", id: String(cliente.id), nombre: cliente.nombre_empresa ?? nombre, tipo: "particular" })
            setContactoId(null)
            setCreandoPersona(false)
            toast.success(`${nombre} agregado como persona natural`)
        } catch (e: any) {
            const errores = e?.data?.errors ? (Object.values(e.data.errors).flat() as string[]) : []
            setErrorPersona(errores[0] || e?.data?.message || e?.message || "No se pudo crear la persona.")
        } finally {
            setGuardandoPersona(false)
        }
    }

    // Los clientes sueltos se distinguen entre Empresa y Persona natural
    const etiquetaEntidad = (e: EntidadOpcion) =>
        e.kind === "cuerpo" ? "Cuerpo"
            : e.kind === "compania" ? "Compañía"
            : e.kind === "contacto" ? (e.ancla ? "Contacto" : "Persona natural")
            : e.tipo === "particular" ? "Persona natural" : "Empresa"

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Nueva Oportunidad</DialogTitle>
                    <DialogDescription>
                        Busque un Cuerpo, Compañía, Empresa o una persona (por nombre o RUT). Si la persona no existe, puede crearla desde el buscador.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-4">

                    {/* Entidad Search */}
                    <div className="flex flex-col gap-2">
                        <Label>Cuerpo, Compañía, Empresa o Persona natural</Label>
                        <Popover modal open={openEntidadCombo} onOpenChange={setOpenEntidadCombo}>
                            <PopoverTrigger asChild>
                                <Button variant="outline" role="combobox" aria-expanded={openEntidadCombo} className="justify-between w-full">
                                    {entidad
                                        ? `${entidad.nombre} (${etiquetaEntidad(entidad)})`
                                        : "Buscar..."}
                                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="p-0 w-[550px]" align="start">
                                <Command shouldFilter={false}>
                                    <CommandInput
                                        placeholder="Buscar por nombre, RUT o persona..."
                                        onValueChange={(val) => {
                                            setBusqueda(val)
                                            if (busquedaTimer.current) clearTimeout(busquedaTimer.current)
                                            busquedaTimer.current = setTimeout(() => searchEntidades(val), 250)
                                        }}
                                    />
                                    <CommandList>
                                        {searchingEntidades && <div className="p-2 text-sm text-muted-foreground">Buscando...</div>}
                                        <CommandEmpty>No se encontraron resultados.</CommandEmpty>
                                        <CommandGroup>
                                            {opciones.map((op) => (
                                                <CommandItem
                                                    key={`${op.kind}-${op.id}`}
                                                    value={`${op.kind}-${op.id}`}
                                                    onSelect={() => elegirOpcion(op)}
                                                >
                                                    <Check
                                                        className={cn(
                                                            "mr-2 h-4 w-4",
                                                            entidad?.kind === op.kind && entidad?.id === op.id ? "opacity-100" : "opacity-0"
                                                        )}
                                                    />
                                                    <div className="flex flex-col">
                                                        <span>
                                                            {op.kind === "contacto" && <User className="mr-1.5 inline h-3.5 w-3.5 text-violet-600" />}
                                                            {op.nombre} <span className="text-xs text-muted-foreground">({etiquetaEntidad(op)})</span>
                                                        </span>
                                                        {op.subtitulo && (
                                                            <span className="text-xs text-muted-foreground">
                                                                {op.kind === "contacto" ? op.subtitulo : `Pertenece a: ${op.subtitulo}`}
                                                                {op.kind === "contacto" && op.rut ? ` · ${op.rut}` : ""}
                                                            </span>
                                                        )}
                                                    </div>
                                                </CommandItem>
                                            ))}
                                        </CommandGroup>
                                        {busqueda.trim().length >= 2 && (
                                            <CommandGroup heading="¿No está en la lista?">
                                                <CommandItem value="crear-persona-natural" onSelect={() => abrirAltaPersona(busqueda)}>
                                                    <UserPlus className="mr-2 h-4 w-4 text-violet-600" />
                                                    <span>Crear persona natural «{busqueda.trim()}»</span>
                                                </CommandItem>
                                            </CommandGroup>
                                        )}
                                    </CommandList>
                                </Command>
                            </PopoverContent>
                        </Popover>
                    </div>

                    {/* Persona de una organización: ¿cotizar a la organización o a ella como persona natural? */}
                    {personaElegida && (
                        <div className="grid gap-2 rounded-md border border-violet-200 bg-violet-50/40 p-3">
                            <p className="text-sm font-medium text-violet-900">
                                {personaElegida.nombre} pertenece a {personaElegida.ancla?.nombre}. ¿A quién va dirigida la cotización?
                            </p>
                            <div className="grid gap-2 sm:grid-cols-2">
                                <Button
                                    type="button"
                                    variant={dirigidaALaPersona ? "outline" : "default"}
                                    className="h-auto justify-start whitespace-normal py-2 text-left"
                                    aria-pressed={!dirigidaALaPersona}
                                    onClick={() => dirigirA(false)}
                                >
                                    <span>
                                        <span className="block text-sm">A la organización</span>
                                        <span className="block text-xs font-normal opacity-80">{personaElegida.ancla?.nombre} · queda como contacto</span>
                                    </span>
                                </Button>
                                <Button
                                    type="button"
                                    variant={dirigidaALaPersona ? "default" : "outline"}
                                    className="h-auto justify-start whitespace-normal py-2 text-left"
                                    aria-pressed={dirigidaALaPersona}
                                    onClick={() => dirigirA(true)}
                                >
                                    <span>
                                        <span className="block text-sm">A la persona (persona natural)</span>
                                        <span className="block text-xs font-normal opacity-80">Sin vincular a la organización</span>
                                    </span>
                                </Button>
                            </div>
                        </div>
                    )}

                    {personaSinOrganizacion && (
                        <div className="flex items-start justify-between gap-3 rounded-md border border-amber-200 bg-amber-50 p-3">
                            <p className="text-sm text-amber-900">
                                {entidad?.nombre} no tiene Cuerpo, Compañía ni Empresa: el lead irá a su nombre. Si sabes de dónde es, asígnalo y el lead irá a esa organización.
                            </p>
                            <Button type="button" size="sm" variant="outline" className="shrink-0 border-amber-300 bg-white" onClick={() => setAsignarOpen(true)}>
                                Asignar
                            </Button>
                        </div>
                    )}

                    {/* Alta rápida de persona natural */}
                    {creandoPersona && (
                        <div className="grid gap-3 rounded-md border border-violet-200 bg-violet-50/40 p-4">
                            <div className="flex items-center justify-between">
                                <h4 className="flex items-center gap-2 text-sm font-semibold text-violet-800">
                                    <UserPlus className="h-4 w-4" /> Nueva persona natural
                                </h4>
                                <Button type="button" variant="ghost" size="sm" onClick={() => setCreandoPersona(false)} disabled={guardandoPersona}>
                                    Cancelar
                                </Button>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="col-span-2 flex flex-col gap-1.5">
                                    <Label>Nombre completo <span className="text-red-500">*</span></Label>
                                    <Input
                                        value={personaForm.nombre}
                                        onChange={(e) => setPersonaForm({ ...personaForm, nombre: e.target.value })}
                                        placeholder="Ej: Juan Pérez Soto"
                                    />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label>RUT <span className="text-xs text-muted-foreground">(opcional)</span></Label>
                                    <Input
                                        value={personaForm.rut}
                                        onChange={(e) => setPersonaForm({ ...personaForm, rut: formatRut(e.target.value) })}
                                        placeholder="12.345.678-9"
                                    />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label>Teléfono <span className="text-xs text-muted-foreground">(opcional)</span></Label>
                                    <Input
                                        value={personaForm.telefono}
                                        onChange={(e) => {
                                            let val = e.target.value
                                            if (val && !val.startsWith('+')) {
                                                val = val.startsWith('56') ? '+' + val : '+56' + val
                                            }
                                            setPersonaForm({ ...personaForm, telefono: val.replace(/\s/g, '') })
                                        }}
                                        placeholder="+569..."
                                    />
                                </div>
                                <div className="col-span-2 flex flex-col gap-1.5">
                                    <Label>Correo <span className="text-xs text-muted-foreground">(opcional)</span></Label>
                                    <Input
                                        type="email"
                                        value={personaForm.correo}
                                        onChange={(e) => setPersonaForm({ ...personaForm, correo: e.target.value.toLowerCase().trim().replace(/\s/g, '') })}
                                        placeholder="persona@correo.cl"
                                    />
                                </div>
                            </div>
                            {errorPersona && (
                                <div role="alert" className="rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700">{errorPersona}</div>
                            )}
                            <div className="flex justify-end">
                                <Button type="button" size="sm" onClick={guardarPersona} disabled={guardandoPersona}>
                                    {guardandoPersona && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                    Guardar y seleccionar
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Contacto Selection */}
                    {entidad && !esPersona && (
                        <div className="flex flex-col gap-2">
                            <Label>Contacto</Label>
                            <Select
                                value={contactoId ? String(contactoId) : "manual"}
                                onValueChange={(v) => setContactoId(v === "manual" ? null : Number(v))}
                                disabled={loadingContactos}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccione contacto" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="manual">+ Nuevo / Manual</SelectItem>
                                    {contactos.map(c => (
                                        <SelectItem key={c.id} value={String(c.id)}>
                                            {c.nombre} {c.cargo ? `(${c.cargo})` : ""}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {/* Contacto nuevo: se guarda como contacto del Cuerpo/Compañía */}
                    {!contactoId && !esPersona && !creandoPersona && (
                        <div className="border-l-2 pl-4 border-muted space-y-2">
                            {!entidad && (
                                <p className="text-xs text-muted-foreground">
                                    Sin organización elegida, el contacto queda sin asignar y el lead va a su nombre (persona natural). Se puede asignar después desde Clientes y Cuerpos → Contactos.
                                </p>
                            )}
                            <ContactoFields form={contactoManual} onChange={setContactoManual} />
                        </div>
                    )}

                    {/* Contacto existente (editable) */}
                    {contactoId && selectedContactoDetails && (
                        <div className="border border-muted rounded-md p-3 space-y-3 bg-muted/10 text-sm">
                            <h4 className="font-medium text-muted-foreground text-xs">Información del Contacto (Editable)</h4>
                            <ContactoFields form={editingContactForm} onChange={setEditingContactForm} />
                        </div>
                    )}

                    {/* Referido & Canal */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="flex flex-col gap-2">
                            <Label>Referido Por</Label>
                            <Input
                                placeholder="Instagram, Web, Otro..."
                                value={referidoPor}
                                onChange={e => setReferidoPor(e.target.value)}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Canal</Label>
                            <Select value={canal} onValueChange={setCanal}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Teléfono">Teléfono</SelectItem>
                                    <SelectItem value="WhatsApp">WhatsApp</SelectItem>
                                    <SelectItem value="Correo">Correo</SelectItem>
                                    <SelectItem value="Reunión Presencial">Reunión Presencial</SelectItem>
                                    <SelectItem value="Reunión Virtual">Reunión Virtual</SelectItem>
                                    <SelectItem value="Redes Sociales">Redes Sociales</SelectItem>
                                    <SelectItem value="Web">Web</SelectItem>
                                    <SelectItem value="Otro">Otro</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Responsable: arranca siendo quien crea el lead y se puede traspasar después */}
                    <div className="flex flex-col gap-2">
                        <Label>Ejecutivo responsable</Label>
                        <Select value={userId} onValueChange={setUserId} disabled={loadingCombos}>
                            <SelectTrigger>
                                <SelectValue placeholder={loadingCombos ? "Cargando..." : "Seleccione ejecutivo"} />
                            </SelectTrigger>
                            <SelectContent>
                                {ejecutivos.map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {u.nombre}
                                        {u.id === user?.id ? " (tú)" : ""}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            Quedas registrado como creador. El responsable se puede traspasar a otro ejecutivo más adelante.
                        </p>
                    </div>

                    {error && (
                        <div className="text-sm text-red-600 bg-red-50 p-2 rounded">
                            {error}
                        </div>
                    )}

                </div>

                <AsignarContactoDialog
                    open={asignarOpen}
                    onOpenChange={setAsignarOpen}
                    contacto={personaSinOrganizacion && entidad?.contactoId
                        ? { id: entidad.contactoId, nombre: entidad.nombre, cuerpos: [], companias: [], cliente: null }
                        : null}
                    onAsignado={({ organizacion }) => usarOrganizacionAsignada(organizacion)}
                />

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>Cancelar</Button>
                    <Button onClick={onSubmit} disabled={loading}>
                        {loading ? "Creando..." : "Crear Oportunidad"}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
