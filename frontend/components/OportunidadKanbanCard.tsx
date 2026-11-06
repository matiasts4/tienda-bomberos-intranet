"use client"

import { useEffect, useMemo, useState, memo, useCallback } from "react"
import { useRouter } from "next/navigation"
import { Card, CardContent, CardHeader } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Pencil, Tag, Trash2, Building2, User, Phone, Mail, UserCheck, MessageSquare, Quote, XCircle as XCircleIcon } from "lucide-react"
import { cn } from "@/lib/utils"
import { useDraggable } from "@dnd-kit/core"
import { api } from "@/lib/api"
import { toast } from "sonner"
import { EstadoBadge } from "./comercial/EstadoBadge"
import { Badge } from "@/components/ui/badge"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { HistoryTimeline, HistorialEtapa } from "@/components/HistoryTimeline"
import { OportunidadDeleteDialog } from "./comercial/OportunidadDeleteDialog"
import { OportunidadEditDialog, type ContactoLeadData } from "./comercial/OportunidadEditDialog"
import { ResponsableLinea } from "./comercial/ResponsableLinea"
import { ResponsablePanel } from "./comercial/ResponsablePanel"
import type { Traspaso, UsuarioRef } from "@/lib/responsables"
import { DestinatarioBadge } from "./comercial/DestinatarioBadge"
import { DESTINATARIO_NUMERO_CLASS, tipoDestinatarioDe, type TipoDestinatario } from "@/lib/destinatario"
import { AsignarContactoDialog } from "./comercial/AsignarContactoDialog"

export type Oportunidad = {
  id: number
  referido_por: string | null
  fecha_ingreso?: string | null
  created_at?: string | null

  cliente_id?: string | null
  cliente?: { id: string; nombre_empresa: string; tipo?: string | null } | null
  tipo_destinatario?: TipoDestinatario | null
  cuerpo_id?: string | null
  cuerpo?: { id: string; nombre: string } | null
  compania_id?: string | null
  compania?: { id: string; nombre: string; cuerpo?: { id: string; nombre: string } | null } | null
  contacto_id?: number | null
  contacto?: { id: number; nombre: string; telefono?: string | null; email?: string | null; cargo?: string | null } | null
  // Responsable actual (user_id), quien la creó y los cambios de responsable
  user_id?: number | null
  creado_por_id?: number | null
  ejecutivo?: UsuarioRef | null
  creador?: UsuarioRef | null
  traspasos?: Traspaso[]
  estado?: { id: number; nombre: string; color?: string | null } | null
  canal?: string | null
  mensaje_prospeccion?: string | null
  historial?: HistorialEtapa[]
  ultima_gestion?: {
    id: number
    glosa: string
    fecha_gestion: string
    fecha_vencimiento_nueva?: string | null
  } | null
}

export type Estado = {
  id: number
  nombre: string
  color?: string | null
}

type Props = {
  oportunidad: Oportunidad
  estados?: Estado[]
  onEstadoUpdated?: () => void
  onViewGestion?: (o: Oportunidad) => void
}

export const OportunidadKanbanCard = memo(function OportunidadKanbanCard({ oportunidad, estados = [], onEstadoUpdated, onViewGestion }: Props) {
  const router = useRouter()
  const code = useMemo(() => `OP-${String(oportunidad.id).padStart(4, "0")}`, [oportunidad.id])

  const [open, setOpen] = useState(false)
  const [editOpen, setEditOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [asignarOpen, setAsignarOpen] = useState(false)

  const [estadoId, setEstadoId] = useState<string>(oportunidad.estado?.id ? String(oportunidad.estado.id) : "")
  const [estadoActual, setEstadoActual] = useState<Oportunidad["estado"]>(oportunidad.estado ?? null)

  const [savingEstado, setSavingEstado] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [savingEdit, setSavingEdit] = useState(false)

  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    setEstadoId(oportunidad.estado?.id ? String(oportunidad.estado.id) : "")
    setEstadoActual(oportunidad.estado ?? null)
  }, [oportunidad])

  const resolvedEstadoColor = useMemo(() => {
    if (estadoActual?.color) return estadoActual.color
    const id = estadoActual?.id
    if (!id) return null
    return estados.find((e) => e.id === id)?.color ?? null
  }, [estadoActual?.id, estadoActual?.color, estados])

  const resolvedOportunidadEstadoColor = useMemo(() => {
    if (oportunidad.estado?.color) return oportunidad.estado.color
    const id = oportunidad.estado?.id
    if (!id) return null
    return estados.find((e) => e.id === id)?.color ?? null
  }, [oportunidad.estado?.id, oportunidad.estado?.color, estados])

  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: oportunidad.id,
    data: { oportunidad },
  })

  // Mientras se arrastra, la copia que se mueve es la del DragOverlay del tablero
  // (flota sobre todas las columnas); esta se queda en su lugar atenuada. Si se
  // moviera esta, la columna siguiente la tapa y el scroll la recorta.
  const style = useMemo(() => transform && !isDragging ? ({ transform: `translate3d(${transform.x}px, ${transform.y}px, 0)` }) : undefined, [transform, isDragging])

  function extractMessage(e: any) {
    let errorsMsg: string | null = null
    if (e?.data?.errors && typeof e.data.errors === "object") {
      const errorValues = Object.values(e.data.errors) as unknown[]
      const firstError = errorValues[0]
      if (Array.isArray(firstError) && firstError.length > 0) {
        errorsMsg = String(firstError[0])
      } else if (typeof firstError === "string") {
        errorsMsg = firstError
      }
    }

    const msg = e?.data?.message || e?.data?.error || errorsMsg || e?.message
    return msg || "Ocurrió un error"
  }

  const guardarEstado = useCallback(async (overrideId?: string) => {
    const finalId = overrideId || estadoId
    if (!finalId) return

    setSavingEstado(true)
    setError(null)

    try {
      const json = await api.patch<any>(`/api/oportunidades/${oportunidad.id}/estado`, {
        estado_id: Number(finalId),
      })

      const cotId = json?.cotizacion_id
      if (cotId) {
        setOpen(false)
        onEstadoUpdated?.()
        router.push(`/comercial/cotizaciones/${cotId}`)
        router.refresh()
        return
      }

      const nuevoId = json?.estado?.id ?? Number(estadoId)
      const nuevoLocal = estados.find((e) => e.id === nuevoId)

      setEstadoActual({
        id: nuevoId,
        nombre: json?.estado?.nombre ?? nuevoLocal?.nombre ?? "Estado",
        color: json?.estado?.color ?? nuevoLocal?.color ?? null,
      })
      setEstadoId(String(nuevoId))

      onEstadoUpdated?.()
    } catch (e: any) {
      setError(extractMessage(e))
    } finally {
      setSavingEstado(false)
    }
  }, [estadoId, oportunidad.id, estados, onEstadoUpdated, router])

  const eliminarOportunidad = useCallback(async () => {
    setDeleting(true)
    setError(null)

    try {
      await api.del(`/api/oportunidades/${oportunidad.id}`)
      setEditOpen(false)
      setOpen(false)
      setDeleteOpen(false)
      onEstadoUpdated?.()
    } catch (e: any) {
      setError(extractMessage(e))
    } finally {
      setDeleting(false)
    }
  }, [oportunidad.id, onEstadoUpdated])

  const abrirEditar = useCallback(() => {
    setError(null)
    setEditOpen(true)
  }, [])

  const guardarEdicion = useCallback(async (data: Partial<Oportunidad> & ContactoLeadData) => {
    setSavingEdit(true)
    setError(null)

    try {
      await api.patch(`/api/oportunidades/${oportunidad.id}`, {
        nombre_contacto: data.nombre_contacto || null,
        rut_contacto: data.rut_contacto || null,
        cargo_id_contacto: data.cargo_id_contacto || null,
        cargo_contacto: data.cargo_contacto || null,
        numero_contacto: data.numero_contacto || null,
        email_contacto: data.email_contacto || null,
        referido_por: data.referido_por || null,
        cliente_id: data.cliente_id || null,
        cuerpo_id: data.cuerpo_id || null,
        compania_id: data.compania_id || null,
        contacto_id: data.contacto_id || null,
      })
      setEditOpen(false)
      onEstadoUpdated?.()
    } catch (e: any) {
      setError(extractMessage(e))
    } finally {
      setSavingEdit(false)
    }
  }, [oportunidad.id, onEstadoUpdated])

  const estadoNombre = estadoActual?.nombre ?? "Sin estado"
  const organizacion = oportunidad.cuerpo?.nombre || oportunidad.compania?.nombre || oportunidad.cliente?.nombre_empresa
  // Un lead sin organización va a nombre de la persona (persona natural): es ella la que se muestra
  const previewEmpresa = organizacion || oportunidad.contacto?.nombre || `Oportunidad #${oportunidad.id}`
  const aNombreDeLaPersona = !organizacion && !!oportunidad.contacto

  return (
    <>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogTrigger asChild>
          <div
            ref={setNodeRef}
            style={style}
            {...listeners}
            {...attributes}
            className={cn(
              "rounded-xl border bg-white shadow-sm hover:border-primary/50 hover:shadow-md transition-all cursor-pointer relative overflow-hidden flex flex-col justify-between min-h-[110px]",
              isDragging && "opacity-40 ring-2 ring-muted"
            )}
          >
            <div className="p-4 flex flex-col gap-2 flex-1">
              <div className="flex flex-row items-center justify-between">
                <div className={cn("font-bold text-sm", DESTINATARIO_NUMERO_CLASS[tipoDestinatarioDe(oportunidad)])}>{code}</div>
                <EstadoBadge nombre={estadoNombre} color={resolvedEstadoColor} />
              </div>
              <div className="space-y-1">
                <div className="flex items-center gap-2 text-[13px] font-semibold text-slate-800">
                  <Building2 className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                  <span className="truncate">{previewEmpresa}</span>
                  <DestinatarioBadge tipo={tipoDestinatarioDe(oportunidad)} className="ml-auto" />
                </div>
                {oportunidad.contacto && !aNombreDeLaPersona && (
                  <div className="flex flex-col pl-5.5 space-y-0.5">
                    <div className="flex items-center gap-1.5 text-[11px] text-slate-600">
                      <User className="h-3 w-3 text-slate-400" />
                      <span className="truncate">{oportunidad.contacto.nombre}</span>
                    </div>
                    {oportunidad.contacto.cargo && (
                      <p className="text-[9px] text-slate-400 uppercase font-medium pl-4.5">
                        {oportunidad.contacto.cargo}
                      </p>
                    )}
                  </div>
                )}
                <ResponsableLinea tarjeta={oportunidad} className="pt-0.5" />
              </div>
            </div>

            {/* BARRA DE GESTIÓN (VENCIMIENTO A 5 DÍAS PREDETERMINADO) */}
            <div
              className={cn(
                "mt-auto px-4 py-2 text-xs font-medium text-center transition-colors flex items-center justify-center gap-1",
                (() => {
                  let target: Date;
                  if (oportunidad.ultima_gestion?.fecha_vencimiento_nueva) {
                    target = new Date(oportunidad.ultima_gestion.fecha_vencimiento_nueva)
                  } else {
                    target = new Date(oportunidad.created_at || new Date())
                    target.setDate(target.getDate() + 5)
                  }

                  const now = new Date()
                  now.setHours(0, 0, 0, 0)
                  target.setHours(0, 0, 0, 0)
                  const diffDays = Math.ceil((target.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))

                  if (diffDays < 0) return "bg-red-100 text-red-800 hover:bg-red-200" // Atrasada
                  if (diffDays <= 1) return "bg-orange-100 text-orange-800 hover:bg-orange-200" // Hoy o Mañana
                  return "bg-green-100 text-green-800 hover:bg-green-200" // A futuro
                })()
              )}
              onClick={(e) => {
                e.stopPropagation()
                onViewGestion?.(oportunidad)
              }}
            >
              {(() => {
                let target: Date;
                if (oportunidad.ultima_gestion?.fecha_vencimiento_nueva) {
                  target = new Date(oportunidad.ultima_gestion.fecha_vencimiento_nueva)
                } else {
                  target = new Date(oportunidad.created_at || new Date())
                  target.setDate(target.getDate() + 5)
                }

                const now = new Date()
                now.setHours(0, 0, 0, 0)
                target.setHours(0, 0, 0, 0)
                const diffDays = Math.ceil((target.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
                const dateStr = target.toLocaleDateString("es-CL")

                if (diffDays < 0) return `Atrasado - Debió contactarse el ${dateStr}`
                if (diffDays === 0) return `Contactar HOY`
                if (diffDays === 1) return `Contactar MAÑANA`
                if (oportunidad.ultima_gestion?.fecha_vencimiento_nueva) {
                  return `Próximo contacto: ${dateStr}`
                }
                return `Atender antes del: ${dateStr}`
              })()}
            </div>
          </div>
        </DialogTrigger>

        <DialogContent className="sm:max-w-[620px] max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="flex items-center justify-between gap-3">
              <span>Detalle oportunidad</span>
              <EstadoBadge nombre={estadoNombre} color={resolvedEstadoColor} />
            </DialogTitle>
          </DialogHeader>

          <Tabs defaultValue="detalle" className="mt-2">
            <TabsList className="grid w-full grid-cols-2">
              <TabsTrigger value="detalle">Detalle</TabsTrigger>
              <TabsTrigger value="historial">Historial</TabsTrigger>
            </TabsList>

            <TabsContent value="detalle">
                <div className="space-y-4 py-4">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {/* Sección: Información Principal */}
                    <div className="space-y-3 rounded-lg border p-4 bg-slate-50/50">
                      <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Detalles de Oportunidad</p>
                      <div className="space-y-2 text-sm">
                        <div className="flex items-center justify-between border-b border-slate-200/60 pb-1.5">
                          <span className="text-muted-foreground flex items-center gap-1.5"><Tag className="h-3.5 w-3.5" /> ID</span>
                          <span className="font-bold text-primary">{code}</span>
                        </div>
                        <div className="flex items-center justify-between border-b border-slate-200/60 pb-1.5">
                          <span className="text-muted-foreground flex items-center gap-1.5"><Quote className="h-3.5 w-3.5" /> Canal</span>
                          <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-100 font-semibold px-2 py-0">
                            {oportunidad.canal || "Teléfono"}
                          </Badge>
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="text-muted-foreground flex items-center gap-1.5"><UserCheck className="h-3.5 w-3.5" /> Asignado por</span>
                          <span className="font-medium text-slate-700">{oportunidad.referido_por?.replace('Prospección - ', '') || "Sistema"}</span>
                        </div>
                      </div>
                    </div>

                    {/* Sección: Contacto y Organización */}
                    <div className="space-y-3 rounded-lg border p-4 bg-emerald-50/30">
                      <div className="flex items-center justify-between gap-2">
                        <p className="text-[10px] font-bold text-emerald-600/70 uppercase tracking-wider">Contacto y Cliente</p>
                        <DestinatarioBadge tipo={tipoDestinatarioDe(oportunidad)} />
                      </div>
                      <div className="space-y-2 text-sm">
                        {(oportunidad.cuerpo || oportunidad.compania || oportunidad.cliente) && (
                          <div className="space-y-0.5">
                            <div className="flex items-center gap-1.5 text-slate-900 font-bold">
                              <Building2 className="h-3.5 w-3.5 text-emerald-600" />
                              {oportunidad.compania?.nombre || oportunidad.cuerpo?.nombre || oportunidad.cliente?.nombre_empresa}
                            </div>
                            {oportunidad.compania && (
                              <p className="text-[10px] text-slate-500 pl-5">
                                Miembro de: {oportunidad.compania.cuerpo?.nombre || oportunidad.cuerpo?.nombre}
                              </p>
                            )}
                          </div>
                        )}
                        {aNombreDeLaPersona && (
                          <div className="flex flex-col items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2">
                            <p className="text-[11px] text-amber-900">
                              Sin Cuerpo, Compañía ni Empresa: el lead y su cotización van a nombre de la persona.
                            </p>
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              className="h-7 border-amber-300 bg-white text-[11px]"
                              onClick={() => setAsignarOpen(true)}
                            >
                              Asignar organización
                            </Button>
                          </div>
                        )}
                        {oportunidad.contacto && (
                          <div className="pt-2 space-y-1.5 border-t border-emerald-100 mt-2">
                            <div className="flex items-center gap-1.5 text-slate-800 font-semibold">
                              <User className="h-3.5 w-3.5 text-emerald-600" />
                              {oportunidad.contacto.nombre}
                            </div>
                            {oportunidad.contacto.cargo && (
                              <p className="text-[10px] text-emerald-700 uppercase font-bold pl-5">{oportunidad.contacto.cargo}</p>
                            )}
                            <div className="flex flex-wrap gap-2 pl-5 pt-1">
                              {oportunidad.contacto.telefono ? (
                                <a href={`tel:${oportunidad.contacto.telefono}`} className="flex items-center gap-1 text-[11px] text-blue-600 hover:underline">
                                  <Phone className="h-3 w-3" /> {oportunidad.contacto.telefono}
                                </a>
                              ) : (
                                <span className="flex items-center gap-1 text-[11px] text-slate-400">
                                  <Phone className="h-3 w-3" /> Sin teléfono
                                </span>
                              )}
                              {oportunidad.contacto.email ? (
                                <a href={`mailto:${oportunidad.contacto.email}`} className="flex items-center gap-1 text-[11px] text-blue-600 hover:underline">
                                  <Mail className="h-3 w-3" /> {oportunidad.contacto.email}
                                </a>
                              ) : (
                                <span className="flex items-center gap-1 text-[11px] text-slate-400">
                                  <Mail className="h-3 w-3" /> Sin correo
                                </span>
                              )}
                            </div>
                          </div>
                        )}
                      </div>
                    </div>
                  </div>

                  {/* Creador y responsable (con traspaso) */}
                  <ResponsablePanel
                    tarjeta={oportunidad}
                    tipo="oportunidades"
                    tarjetaId={oportunidad.id}
                    codigo={code}
                    onTraspasado={() => onEstadoUpdated?.()}
                  />

                  {/* Mensaje de Prospección */}
                  {oportunidad.mensaje_prospeccion && (
                    <div className="rounded-lg border bg-amber-50/30 p-4 space-y-2">
                       <p className="text-[10px] font-bold text-amber-600 uppercase tracking-wider flex items-center gap-1.5">
                        <MessageSquare className="h-3.5 w-3.5" /> Mensaje de Prospección
                       </p>
                       <div className="text-sm text-slate-700 italic border-l-2 border-amber-200 pl-3 py-1 bg-white/50 rounded-r">
                         "{oportunidad.mensaje_prospeccion}"
                       </div>
                    </div>
                  )}

                  {/* Acciones de Gestión */}
                  <div className="pt-4 border-t space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      {/* Descartar */}
                      <Button
                        variant="outline"
                        className="h-16 border-red-200 hover:bg-red-50 hover:text-red-700 text-red-600 flex flex-col items-center justify-center gap-1 group"
                        onClick={() => {
                          setError(null)
                          setDeleteOpen(true)
                        }}
                        disabled={deleting}
                      >
                        <XCircleIcon className="h-5 w-5 group-hover:scale-110 transition-transform" />
                        <span className="font-bold text-xs">DESCARTAR OPORTUNIDAD</span>
                      </Button>

                      {/* Convertir a Cotización */}
                      <Button
                        className="h-16 bg-emerald-600 hover:bg-emerald-700 text-white flex flex-col items-center justify-center gap-1 shadow-md group"
                        onClick={() => {
                           // Aquí iría la lógica para convertir a cotización
                           // Usaremos el estado_id que corresponda a "Cotización" (normalmente gatilla la creación)
                           // Por ahora, buscaremos si hay un estado "Cotizar" o similar
                           const cotizarEstado = estados.find(e => e.nombre.toLowerCase().includes('cotiz') || e.nombre.toLowerCase().includes('enviad'))
                           if (cotizarEstado) {
                             const newId = String(cotizarEstado.id)
                             setEstadoId(newId)
                             guardarEstado(newId)
                           } else {
                             toast.error("No se encontró el estado para cotizar. Contacte a soporte.")
                           }
                        }}
                        disabled={savingEstado}
                      >
                        <Quote className="h-5 w-5 group-hover:scale-110 transition-transform" />
                        <span className="font-bold text-xs uppercase">Crear Cotización</span>
                      </Button>
                    </div>
                  </div>

                  {error && <div className="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">{error}</div>}

                  <DialogFooter className="pt-2 gap-2">
                    <Button variant="outline" onClick={abrirEditar} className="w-full sm:w-auto">
                      <Pencil className="h-4 w-4" /> Editar
                    </Button>
                    <Button variant="ghost" onClick={() => setOpen(false)} className="w-full sm:w-auto">
                      Cerrar Panel
                    </Button>
                  </DialogFooter>
                </div>
            </TabsContent>

            <TabsContent value="historial" className="pt-4">
              <HistoryTimeline
                historial={oportunidad.historial || []}
                creationDate={oportunidad.created_at || oportunidad.fecha_ingreso}
                createdByName={oportunidad.creador?.name || "Sistema"}
                traspasos={oportunidad.traspasos}
              />
            </TabsContent>
          </Tabs>
        </DialogContent>
      </Dialog>

      <OportunidadDeleteDialog
        open={deleteOpen}
        onOpenChange={(v) => {
          setDeleteOpen(v)
          if (!v) setError(null)
        }}
        oportunidad={oportunidad}
        code={code}
        error={error}
        deleting={deleting}
        onConfirm={eliminarOportunidad}
        resolvedEstadoColor={resolvedOportunidadEstadoColor}
      />

      <AsignarContactoDialog
        open={asignarOpen}
        onOpenChange={setAsignarOpen}
        contacto={oportunidad.contacto ?? null}
        onAsignado={() => {
          setOpen(false)
          onEstadoUpdated?.()
        }}
      />

      <OportunidadEditDialog
        open={editOpen}
        onOpenChange={(v) => {
          setEditOpen(v)
          if (!v) setError(null)
        }}
        oportunidad={oportunidad}
        onSave={guardarEdicion}
        saving={savingEdit}
        error={error}
        setError={setError}
      />
    </>
  )

  // Helper inside loop or just use it from above?
  // resolvedOportunidadEstadoColor needs to be memoized or just calc on the fly


})

export default OportunidadKanbanCard

/** Copia liviana del lead para el DragOverlay: sin useDraggable ni diálogos. */
export function OportunidadDragPreview({ oportunidad }: { oportunidad: Oportunidad }) {
  const organizacion = oportunidad.cuerpo?.nombre || oportunidad.compania?.nombre || oportunidad.cliente?.nombre_empresa
  const nombre = organizacion || oportunidad.contacto?.nombre || `Oportunidad #${oportunidad.id}`
  return (
    <div className="rounded-xl border bg-white p-4 shadow-lg min-h-[110px] flex flex-col gap-2">
      <div className={cn("font-bold text-sm", DESTINATARIO_NUMERO_CLASS[tipoDestinatarioDe(oportunidad)])}>
        OP-{String(oportunidad.id).padStart(4, "0")}
      </div>
      <div className="flex items-center gap-2 text-[13px] font-semibold text-slate-800">
        <Building2 className="h-3.5 w-3.5 text-slate-400 shrink-0" />
        <span className="truncate">{nombre}</span>
      </div>
    </div>
  )
}
