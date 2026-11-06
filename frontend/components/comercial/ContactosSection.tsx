"use client"

import { useState, useEffect } from "react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Trash2, Plus, Pencil, User, Link2 } from "lucide-react"
import { api } from "@/lib/api"
import { toast } from "sonner"
import { formatPhone, formatRut, validateEmail } from "@/lib/utils"
import { CargoSelector } from "./CargoSelector"
import { ContactoMeta } from "@/components/comercial/ContactoMeta"
import { AsignarContactoDialog } from "@/components/comercial/AsignarContactoDialog"
import { VincularContactoDialog } from "@/components/comercial/VincularContactoDialog"
import type { ContactoAsignable, OrganizacionOpcion } from "@/lib/asignacion-contacto"

export type Contacto = {
    id: number
    cliente_id?: number | string | null
    nombre: string
    rut?: string
    telefono?: string
    email?: string
    cargo?: string
    cargo_id?: number | null
    fecha_nacimiento?: string | null
    created_at?: string | null
    updated_at?: string | null
    cuerpos?: { comuna?: { id: number; nombre: string } | null }[]
    companias?: { cuerpo?: { comuna?: { id: number; nombre: string } | null } | null }[]
}

type Props = {
    /** Id de la entidad dueña de los contactos. */
    entityId: number | string
    /** A qué tabla pertenece entityId. Default "cliente" (comportamiento histórico). */
    kind?: "cliente" | "cuerpo" | "compania"
    /** Nombre de la entidad: con él se puede vincular un contacto que ya existe (p. ej. uno sin asignar). */
    entityNombre?: string
}

const LIST_ENDPOINT: Record<NonNullable<Props["kind"]>, (id: string | number) => string> = {
    cliente: (id) => `/api/clientes/${id}/contactos`,
    cuerpo: (id) => `/api/cuerpos/${id}/contactos`,
    compania: (id) => `/api/companias/${id}/contactos`,
}

const TIPO_ORGANIZACION: Record<NonNullable<Props["kind"]>, OrganizacionOpcion["tipo"]> = {
    cliente: "empresa",
    cuerpo: "cuerpo",
    compania: "compania",
}

export function ContactosSection({ entityId, kind = "cliente", entityNombre }: Props) {
    const [contactos, setContactos] = useState<Contacto[]>([])
    const [vincularOpen, setVincularOpen] = useState(false)
    const [porAsignar, setPorAsignar] = useState<ContactoAsignable | null>(null)
    const organizacion: OrganizacionOpcion | null = entityNombre
        ? { tipo: TIPO_ORGANIZACION[kind], id: String(entityId), nombre: entityNombre }
        : null
    const [loadingContactos, setLoadingContactos] = useState(false)
    const [openContactDialog, setOpenContactDialog] = useState(false)
    const [editingContact, setEditingContact] = useState<Contacto | null>(null)

    useEffect(() => {
        if (entityId) {
            loadContactos()
        } else {
            setContactos([])
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [entityId, kind])

    const loadContactos = async () => {
        if (!entityId) return
        setLoadingContactos(true)
        try {
            const res = await api.get<Contacto[]>(LIST_ENDPOINT[kind](entityId))
            if (Array.isArray(res)) setContactos(res)
        } catch (e) {
            console.error("Error loading contacts", e)
        } finally {
            setLoadingContactos(false)
        }
    }

    const [contactForm, setContactForm] = useState({
        nombre: "",
        rut: "",
        telefono: "",
        email: "",
        cargo: "",
        cargo_id: null as number | null
    })

    const handleSaveContact = async () => {
        if (!contactForm.nombre.trim()) {
            toast.error("El nombre es obligatorio")
            return
        }

        if (contactForm.email && !validateEmail(contactForm.email)) {
            toast.error("El correo no es válido")
            return
        }

        try {
            if (editingContact) {
                await api.patch(`/api/contactos/${editingContact.id}`, contactForm)
            } else {
                const vinculo =
                    kind === "cuerpo" ? { cuerpo_ids: [entityId] } :
                    kind === "compania" ? { compania_ids: [entityId] } :
                    { cliente_id: entityId }
                await api.post("/api/contactos", {
                    ...vinculo,
                    ...contactForm
                })
            }
            setOpenContactDialog(false)
            loadContactos()
            toast.success("Contacto guardado")
        } catch (e) {
            toast.error("Error al guardar contacto")
        }
    }

    const handleDeleteContact = async (id: number) => {
        if (!confirm("¿Eliminar contacto?")) return
        try {
            await api.del(`/api/contactos/${id}`)
            loadContactos()
            toast.success("Contacto eliminado")
        } catch (e) {
            toast.error("Error al eliminar contacto")
        }
    }

    const openNewContact = () => {
        setEditingContact(null)
        setContactForm({
            nombre: "",
            rut: "",
            telefono: "",
            email: "",
            cargo: "",
            cargo_id: null
        })
        setOpenContactDialog(true)
    }

    const openEditContact = (c: Contacto) => {
        setEditingContact(c)
        setContactForm({
            nombre: c.nombre,
            rut: c.rut || "",
            telefono: c.telefono || "",
            email: c.email || "",
            cargo: c.cargo || "",
            cargo_id: c.cargo_id || null
        })
        setOpenContactDialog(true)
    }

    return (
        <div className="grid gap-3 border p-3 rounded-md bg-gray-50">
            <div className="flex items-center justify-between">
                <h3 className="font-medium text-sm text-gray-700">Personas de Contacto</h3>
                <div className="flex gap-2">
                    {organizacion && (
                        <Button size="sm" variant="outline" onClick={() => setVincularOpen(true)} title="Vincular a alguien que ya está registrado (p. ej. un contacto sin asignar)">
                            <Link2 className="h-4 w-4 mr-2" /> Vincular existente
                        </Button>
                    )}
                    <Button size="sm" variant="outline" onClick={openNewContact}>
                        <Plus className="h-4 w-4 mr-2" /> Agregar
                    </Button>
                </div>
            </div>

            <div className="max-h-[135px] overflow-y-auto rounded-md border bg-white">
                <div className="space-y-2 p-2">
                    {contactos.length === 0 && <p className="text-xs text-muted-foreground">No hay contactos registrados.</p>}
                    {contactos.map(c => (
                        <div key={c.id} className="flex items-center justify-between p-2 bg-white rounded border text-sm">
                            <div>
                                <div className="font-semibold flex items-center gap-2">
                                    <User className="h-3 w-3" /> {c.nombre}
                                    {c.cargo && <span className="text-xs bg-gray-100 px-1 rounded text-gray-500">{c.cargo}</span>}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {c.email} {c.telefono && `• ${c.telefono}`} {c.rut && `• ${c.rut}`}
                                </div>
                                <ContactoMeta contacto={c} className="mt-0.5" />
                            </div>
                            <div className="flex gap-1 shrink-0">
                                <Button size="icon" variant="ghost" className="h-8 w-8" onClick={() => openEditContact(c)}>
                                    <Pencil className="h-3 w-3" />
                                </Button>
                                <Button size="icon" variant="ghost" className="h-8 w-8 text-red-500 hover:text-red-600" onClick={() => handleDeleteContact(c.id)}>
                                    <Trash2 className="h-3 w-3" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {organizacion && (
                <>
                    {/* Primero se busca a la persona y después se confirma (uno tras otro, no anidados) */}
                    <VincularContactoDialog
                        open={vincularOpen}
                        onOpenChange={setVincularOpen}
                        organizacion={organizacion}
                        onElegir={(c) => {
                            setVincularOpen(false)
                            setPorAsignar(c)
                        }}
                    />
                    <AsignarContactoDialog
                        open={!!porAsignar}
                        onOpenChange={(v) => !v && setPorAsignar(null)}
                        contacto={porAsignar}
                        organizacionInicial={organizacion}
                        onAsignado={() => loadContactos()}
                    />
                </>
            )}

            {/* NESTED DIALOG FOR CONTACT FORM */}
            <Dialog open={openContactDialog} onOpenChange={setOpenContactDialog}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editingContact ? "Editar Contacto" : "Nuevo Contacto"}</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-1.5">
                            <Label>Nombre completo</Label>
                            <Input
                                value={contactForm.nombre}
                                onChange={e => setContactForm({ ...contactForm, nombre: e.target.value })}
                                placeholder="Ej: Juan Pérez"
                            />
                        </div>

                        <CargoSelector
                            value={contactForm.cargo_id}
                            onChange={(cargo) => setContactForm({ ...contactForm, cargo_id: cargo.id, cargo: cargo.nombre })}
                        />

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-1.5">
                                <Label>RUT</Label>
                                <Input
                                    value={contactForm.rut}
                                    onChange={e => setContactForm({ ...contactForm, rut: formatRut(e.target.value) })}
                                    placeholder="12.345.678-9"
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label>Teléfono</Label>
                                <Input
                                    value={contactForm.telefono}
                                    onChange={e => setContactForm({ ...contactForm, telefono: formatPhone(e.target.value) })}
                                    placeholder="+569 xxxxxxxx"
                                />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label>Email</Label>
                            <Input
                                type="email"
                                value={contactForm.email}
                                onChange={e => setContactForm({ ...contactForm, email: e.target.value })}
                                placeholder="ejemplo@correo.cl"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setOpenContactDialog(false)}>Cancelar</Button>
                        <Button onClick={handleSaveContact} className="bg-emerald-600 hover:bg-emerald-700 text-white">
                            {editingContact ? "Actualizar" : "Guardar"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    )
}
