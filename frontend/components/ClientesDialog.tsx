"use client"

import { useEffect, useMemo, useState } from "react"
import { useRouter } from "next/navigation"
import { Users, Search, RefreshCcw, Plus, Trash2, Save, FileText, Flame, Building2, User } from "lucide-react"
import { toast } from "sonner"

import { api } from "@/lib/api"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { ScrollArea } from "@/components/ui/scroll-area"
import { ClientForm, ClientData } from "./comercial/ClientForm"
import { EmpresaForm, EmpresaData, emptyEmpresa, emptyEmpresaContacto } from "./comercial/EmpresaForm"
import { useGeografia } from "@/hooks/use-geografia"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { ClientQuotesHistoryDialog } from "./comercial/ClientQuotesHistoryDialog"
import type { Cuerpo, Compania } from "@/lib/red-bomberos"

// Cliente = solo clientes sueltos (empresa/particular) desde el refactor de
// Red de Bomberos. Cuerpos y Compañías ya no tienen fila espejo acá.
export type Cliente = {
  id: string
  tipo?: string | null
  giro?: string | null
  nombre_empresa?: string | null
  rut_empresa?: string | null
  telefono?: string | null
  correo?: string | null
  fecha_ingreso?: string | null
  direccion?: string | null
  region_id?: number | null
  provincia_id?: number | null
  comuna_id?: number | null
  logo_url?: string | null
  region?: { id: number; nombre: string; zona_id: number }
  provincia?: { id: number; nombre: string; region_id: number }
  comuna?: { id: number; nombre: string; region_id: number }
}

// Fila unificada para el listado: cada una sabe a qué endpoint pertenece.
type Item =
  | ({ kind: "cuerpo" } & Cuerpo)
  | ({ kind: "compania" } & Compania)
  | ({ kind: "cliente" } & Cliente)

function nombreDe(item: Item): string {
  if (item.kind === "cliente") return item.nombre_empresa || `Cliente #${item.id}`
  return item.nombre
}

function safeArray<T>(maybe: any): T[] {
  if (Array.isArray(maybe)) return maybe
  if (Array.isArray(maybe?.data)) return maybe.data
  return []
}

const emptyClientForm: Omit<ClientData, "kind"> = {
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
  numero_compania: null,
  logo_url: null,
}

export function ClientesDialog() {
  const router = useRouter()
  const [open, setOpen] = useState(false)
  const [openNuevo, setOpenNuevo] = useState(false)
  const { zonas, regiones, provincias } = useGeografia({ enabled: open || openNuevo })

  // lista
  const [items, setItems] = useState<Item[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // buscar
  const [query, setQuery] = useState("")
  const [selectedZona, setSelectedZona] = useState<string>("all")
  const [selectedRegion, setSelectedRegion] = useState<string>("all")

  // Jerarquía: si currentCuerpo está seteado, mostramos sus Compañías.
  const [currentCuerpo, setCurrentCuerpo] = useState<Cuerpo | null>(null)

  // Tipo de registro a crear: null = mostrar selector
  const [tipoNuevo, setTipoNuevo] = useState<"bomberos" | "empresa" | "particular" | null>(null)
  const [nuevaEmpresa, setNuevaEmpresa] = useState<EmpresaData>({ ...emptyEmpresa, contacto: { ...emptyEmpresaContacto } })
  const [editEmpresa, setEditEmpresa] = useState<EmpresaData>({ ...emptyEmpresa, contacto: { ...emptyEmpresaContacto } })

  // popup nuevo (Cuerpo o Compañía)
  const [nuevo, setNuevo] = useState<Omit<ClientData, "kind">>({ ...emptyClientForm })
  const [savingNuevo, setSavingNuevo] = useState(false)
  const [errorNuevo, setErrorNuevo] = useState<string | null>(null)

  // popup detalle/editar
  const [openDetalle, setOpenDetalle] = useState(false)
  const [seleccionado, setSeleccionado] = useState<Item | null>(null)
  const [edit, setEdit] = useState<Omit<ClientData, "kind">>({ ...emptyClientForm })
  const [savingEdit, setSavingEdit] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [errorDetalle, setErrorDetalle] = useState<string | null>(null)

  const [historyItem, setHistoryItem] = useState<Item | null>(null)
  const [openHistory, setOpenHistory] = useState(false)

  async function loadItems() {
    setLoading(true)
    setError(null)
    try {
      if (currentCuerpo) {
        // Dentro de un Cuerpo: solo sus Compañías.
        const params = new URLSearchParams()
        params.append("cuerpo_id", currentCuerpo.id)
        if (query) params.append("search", query)
        const data = await api.get<any>(`/api/companias?${params.toString()}`)
        const companias = safeArray<Compania>(data).map((c) => ({ kind: "compania" as const, ...c }))
        setItems(companias)
        return
      }

      // Raíz: Cuerpos + Clientes sueltos, mezclados.
      const cuerposParams = new URLSearchParams()
      if (query) cuerposParams.append("search", query)
      if (selectedRegion !== "all") cuerposParams.append("region_id", selectedRegion)

      const clientesParams = new URLSearchParams()
      clientesParams.append("per_page", "500")
      // Las copias "bomberos" de Cuerpos/Compañías no son clientes sueltos: se ven entrando al Cuerpo
      clientesParams.append("tipo", "empresa,particular")
      if (query) clientesParams.append("search", query)

      const [cuerposData, clientesData] = await Promise.all([
        api.get<any>(`/api/cuerpos?${cuerposParams.toString()}`),
        api.get<any>(`/api/clientes?${clientesParams.toString()}`),
      ])

      const cuerpos = safeArray<Cuerpo>(cuerposData).map((c) => ({ kind: "cuerpo" as const, ...c }))
      const clientes = safeArray<Cliente>(clientesData).map((c) => ({ kind: "cliente" as const, ...c }))
      setItems([...cuerpos, ...clientes])
    } catch (e: any) {
      setError(e?.message || "Error cargando clientes")
      setItems([])
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    if (open) loadItems()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, selectedRegion, currentCuerpo])

  useEffect(() => {
    setSelectedRegion("all")
  }, [selectedZona])

  function abrirDetalle(item: Item) {
    setSeleccionado(item)
    if (item.kind === "cliente") {
      setEditEmpresa({
        id: item.id,
        nombre_empresa: item.nombre_empresa || "",
        rut_empresa: item.rut_empresa || "",
        giro: item.giro || "",
        direccion: (item as any).direccion || "",
        telefono: item.telefono || "",
        correo: item.correo || "",
        region_id: item.region_id ?? null,
        provincia_id: item.provincia_id ?? null,
        comuna_id: (item as any).comuna_id ?? (item as any).comuna?.id ?? null,
        logo_url: item.logo_url || null,
        contacto: { ...emptyEmpresaContacto },
      })
      setErrorDetalle(null)
      setOpenDetalle(true)
      return
    }

    if (item.kind === "cuerpo") {
      setEdit({
        nombre_empresa: item.nombre,
        telefono: item.telefono || "",
        correo: "",
        region_id: item.region_id ?? null,
        comuna_id: item.comuna_id ?? item.comuna?.id ?? null,
        rut_empresa: item.rut || "",
        fecha_fundacion: item.fecha_fundacion || "",
        direccion: item.direccion || "",
        giro: item.giro || "",
        numero_companias: item.numero_companias || null,
        logo_url: item.logo_url || null,
      })
    } else {
      setEdit({
        nombre_empresa: item.nombre,
        telefono: "",
        correo: "",
        numero_compania: item.numero,
        fecha_fundacion: item.fecha_fundacion || "",
        cuerpo: item.cuerpo,
      })
    }
    setErrorDetalle(null)
    setOpenDetalle(true)
  }

  async function crearCliente() {
    setSavingNuevo(true)
    setErrorNuevo(null)

    try {
      if (!currentCuerpo && (tipoNuevo === "empresa" || tipoNuevo === "particular")) {
        // Creando una Empresa / Cliente directo (sin estructura de bomberos)
        if (!nuevaEmpresa.nombre_empresa.trim()) {
          setErrorNuevo("El nombre de la empresa o cliente es obligatorio.")
          setSavingNuevo(false)
          return
        }

        const payload: any = {
          tipo: tipoNuevo,
          nombre_empresa: nuevaEmpresa.nombre_empresa.trim(),
          rut_empresa: nuevaEmpresa.rut_empresa?.trim() || null,
          giro: nuevaEmpresa.giro?.trim() || null,
          direccion: nuevaEmpresa.direccion?.trim() || null,
          telefono: nuevaEmpresa.telefono?.trim() || null,
          correo: nuevaEmpresa.correo?.trim() || null,
          region_id: nuevaEmpresa.region_id,
          provincia_id: nuevaEmpresa.provincia_id,
          comuna_id: nuevaEmpresa.comuna_id ?? null,
        }

        if (nuevaEmpresa.contacto.nombre.trim()) {
          payload.contacto = {
            nombre: nuevaEmpresa.contacto.nombre.trim(),
            rut: nuevaEmpresa.contacto.rut?.trim() || null,
            telefono: nuevaEmpresa.contacto.telefono?.trim() || null,
            email: nuevaEmpresa.contacto.email?.trim() || null,
            cargo: nuevaEmpresa.contacto.cargo || null,
            cargo_id: nuevaEmpresa.contacto.cargo_id,
          }
        }

        await api.post("/api/clientes", payload)

        toast.success("Cliente creado correctamente.")
        setOpenNuevo(false)
        setTipoNuevo(null)
        setNuevaEmpresa({ ...emptyEmpresa, contacto: { ...emptyEmpresaContacto } })
        setSavingNuevo(false)
        await loadItems()
        return
      }

      if (currentCuerpo) {
        if (!nuevo.nombre_empresa?.trim()) {
          setErrorNuevo("El nombre de la compañía es obligatorio.")
          setSavingNuevo(false)
          return
        }

        // Creando una Compañía dentro del Cuerpo actual.
        // La fecha de fundación es opcional: si no viene, va null.
        await api.post("/api/companias", {
          cuerpo_id: currentCuerpo.id,
          nombre: nuevo.nombre_empresa.trim(),
          numero: nuevo.numero_compania || 1,
          fecha_fundacion: nuevo.fecha_fundacion?.trim() ? nuevo.fecha_fundacion.trim() : null,
        })
      } else {
        // Creando un Cuerpo de Bomberos
        const nuevoCuerpo = await api.post<Cuerpo>("/api/cuerpos", {
          region_id: nuevo.region_id,
          nombre: nuevo.nombre_empresa?.trim() ? nuevo.nombre_empresa.trim() : "",
          rut: nuevo.rut_empresa?.trim() ? nuevo.rut_empresa.trim() : null,
          fecha_fundacion: nuevo.fecha_fundacion?.trim() ? nuevo.fecha_fundacion.trim() : null,
          direccion: nuevo.direccion?.trim() ? nuevo.direccion.trim() : null,
          comuna_id: nuevo.comuna_id ?? null,
          giro: nuevo.giro?.trim() ? nuevo.giro.trim() : null,
          telefono: nuevo.telefono?.trim() ? nuevo.telefono.trim() : null,
          numero_companias: nuevo.numero_companias,
        })

        toast.success("Cuerpo creado. Ahora ingresa su primera compañía.")
        setCurrentCuerpo(nuevoCuerpo)
        setNuevo({ ...emptyClientForm, numero_compania: null })
        setSavingNuevo(false)
        await loadItems()
        return // Dejamos el popup abierto para cargar la primera compañía
      }

      setOpenNuevo(false)
      setNuevo({ ...emptyClientForm })
      await loadItems()
    } catch (e: any) {
      setErrorNuevo(e?.message || "Error agregando registro. Verifique que los números no se repitan.")
    } finally {
      setSavingNuevo(false)
    }
  }

  async function guardarEdicion() {
    if (!seleccionado?.id) return

    setSavingEdit(true)
    setErrorDetalle(null)

    try {
      if (seleccionado.kind === "cliente") {
        if (!editEmpresa.nombre_empresa.trim()) {
          setErrorDetalle("El nombre de la empresa o cliente es obligatorio.")
          setSavingEdit(false)
          return
        }

        await api.patch(`/api/clientes/${seleccionado.id}`, {
          nombre_empresa: editEmpresa.nombre_empresa.trim(),
          rut_empresa: editEmpresa.rut_empresa?.trim() || null,
          giro: editEmpresa.giro?.trim() || null,
          direccion: editEmpresa.direccion?.trim() || null,
          telefono: editEmpresa.telefono?.trim() || null,
          correo: editEmpresa.correo?.trim() || null,
          region_id: editEmpresa.region_id,
          provincia_id: editEmpresa.provincia_id,
          comuna_id: editEmpresa.comuna_id ?? null,
        })
      } else if (seleccionado.kind === "compania") {
        await api.patch(`/api/companias/${seleccionado.id}`, {
          nombre: edit.nombre_empresa?.trim() ? edit.nombre_empresa.trim() : "",
          numero: edit.numero_compania || 1,
          fecha_fundacion: edit.fecha_fundacion?.trim() ? edit.fecha_fundacion.trim() : null,
        })
      } else {
        await api.patch(`/api/cuerpos/${seleccionado.id}`, {
          nombre: edit.nombre_empresa?.trim() ? edit.nombre_empresa.trim() : "",
          rut: edit.rut_empresa?.trim() ? edit.rut_empresa.trim() : null,
          fecha_fundacion: edit.fecha_fundacion?.trim() ? edit.fecha_fundacion.trim() : null,
          direccion: edit.direccion?.trim() ? edit.direccion.trim() : null,
          comuna_id: edit.comuna_id ?? null,
          giro: edit.giro?.trim() ? edit.giro.trim() : null,
          telefono: edit.telefono?.trim() ? edit.telefono.trim() : null,
          numero_companias: edit.numero_companias,
        })
      }

      toast.success("Cambios guardados")
      setOpenDetalle(false)
      await loadItems()
    } catch (e: any) {
      setErrorDetalle(e?.message || "Error guardando cambios")
    } finally {
      setSavingEdit(false)
    }
  }

  async function eliminarCliente() {
    if (!seleccionado?.id) return

    const ok = window.confirm("¿Eliminar este registro? Esta acción no se puede deshacer.")
    if (!ok) return

    setDeleting(true)
    setErrorDetalle(null)

    try {
      const endpoint =
        seleccionado.kind === "cuerpo" ? `/api/cuerpos/${seleccionado.id}` :
        seleccionado.kind === "compania" ? `/api/companias/${seleccionado.id}` :
        `/api/clientes/${seleccionado.id}`
      await api.del(endpoint)
      setOpenDetalle(false)
      setSeleccionado(null)
      await loadItems()
    } catch (e: any) {
      setErrorDetalle(e?.message || "Error eliminando registro")
    } finally {
      setDeleting(false)
    }
  }

  // Filter lists for dropdowns
  const filteredRegiones = selectedZona !== "all"
    ? regiones.filter(r => r.zona_id === Number(selectedZona))
    : regiones

  return (
    <>
      <Button variant="outline" onClick={() => setOpen(true)}>
        <Users className="h-4 w-4" />
        Clientes
      </Button>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="sm:max-w-[820px]">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Users className="h-4 w-4 shrink-0" />
              {currentCuerpo ? (
                <span className="truncate" title={`${currentCuerpo.nombre} / Compañías`}>
                  {currentCuerpo.nombre} / Compañías
                </span>
              ) : (
                "Clientes"
              )}
            </DialogTitle>
          </DialogHeader>

          <div className="space-y-3">
            <div className="flex items-center gap-2">
              {currentCuerpo && (
                <Button variant="outline" onClick={() => setCurrentCuerpo(null)}>
                  ← Volver
                </Button>
              )}
              <div className="relative flex-1">
                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && loadItems()}
                  placeholder={currentCuerpo ? `Buscar compañía en ${currentCuerpo.nombre}...` : "Buscar cliente o Cuerpo de Bomberos..."}
                  className="pl-8"
                />
              </div>
              <Button variant="outline" onClick={loadItems} disabled={loading}>
                <RefreshCcw className="h-4 w-4" />
                {loading ? "Cargando..." : "Refrescar"}
              </Button>
            </div>

            {!currentCuerpo && (
              <div className="flex gap-2">
                <Select value={selectedZona} onValueChange={setSelectedZona}>
                  <SelectTrigger className="flex-1">
                    <SelectValue placeholder="Zona" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todas las Zonas</SelectItem>
                    {zonas.map(z => (
                      <SelectItem key={z.id} value={String(z.id)}>{z.nombre}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>

                <Select value={selectedRegion} onValueChange={setSelectedRegion} disabled={selectedZona === "all"}>
                  <SelectTrigger className="flex-1">
                    <SelectValue placeholder="Región" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todas las Regiones</SelectItem>
                    {filteredRegiones.map(r => (
                      <SelectItem key={r.id} value={String(r.id)}>{r.nombre}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}

            {error && <div className="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">{error}</div>}

            <div className="rounded-md border">
              <ScrollArea className="h-[380px]">
                <div className="divide-y">
                  {loading && items.length === 0 ? (
                    <div className="p-4 text-sm text-muted-foreground">Cargando...</div>
                  ) : items.length === 0 ? (
                    <div className="p-4 text-sm text-muted-foreground">No hay registros para mostrar.</div>
                  ) : (
                    items.map((item) => (
                      <div key={`${item.kind}-${item.id}`} className="px-4 py-3 hover:bg-muted/50 transition-colors">
                        <div className="flex items-start justify-between gap-3">
                          <button
                            className="min-w-0 flex-1 text-left"
                            onClick={() => {
                              if (item.kind === "cuerpo" && !currentCuerpo) {
                                setCurrentCuerpo(item)
                              } else {
                                abrirDetalle(item)
                              }
                            }}
                          >
                            <div className="flex items-center gap-2">
                              <span className="truncate text-sm font-semibold">{nombreDe(item)}</span>
                              {item.kind === "cliente" && (
                                <span className="shrink-0 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">
                                  {item.tipo === "particular" ? "Persona natural" : "Empresa"}
                                </span>
                              )}
                            </div>
                            <div className="mt-1 space-y-0.5 text-xs text-muted-foreground">
                              {!currentCuerpo && (
                                <div>
                                  {item.kind === "cliente"
                                    ? (item.giro || "Cliente directo")
                                    : "Ver Compañías →"}
                                </div>
                              )}
                              {!currentCuerpo && item.kind !== "compania" && (
                                <div>
                                  {(item as any).region?.nombre || "—"}
                                </div>
                              )}
                            </div>
                          </button>

                          <div className="flex shrink-0 items-center gap-2">
                            <Button variant="ghost" size="sm" onClick={(e) => {
                              e.stopPropagation()
                              setHistoryItem(item)
                              setOpenHistory(true)
                            }}>
                              <FileText className="h-4 w-4 mr-1" />
                              Historial
                            </Button>

                            <Button variant="ghost" size="sm" onClick={(e) => {
                              e.stopPropagation()
                              abrirDetalle(item)
                            }}>
                              Editar
                            </Button>
                          </div>
                        </div>
                      </div>
                    ))
                  )}
                </div>
              </ScrollArea>
            </div>
          </div>

          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setOpen(false)}>
              Cerrar
            </Button>
            <Button onClick={() => {
              setTipoNuevo(currentCuerpo ? "bomberos" : null)
              setNuevo({ ...emptyClientForm })
              setErrorNuevo(null)
              setOpenNuevo(true)
            }}>
              <Plus className="h-4 w-4" />
              Agregar cliente
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>


      <Dialog open={openNuevo} onOpenChange={(v) => {
        setOpenNuevo(v)
        if (!v) setTipoNuevo(null)
      }}>
        <DialogContent className="sm:max-w-[560px] max-h-[85vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              {currentCuerpo
                ? `${currentCuerpo.nombre} / Nueva Compañía`
                : tipoNuevo === "empresa"
                  ? "Nueva Empresa"
                  : tipoNuevo === "particular"
                    ? "Nueva Persona Natural"
                  : tipoNuevo === "bomberos"
                    ? "Nuevo Cuerpo de Bomberos"
                    : "Nuevo Cliente"}
            </DialogTitle>
          </DialogHeader>

          {!currentCuerpo && tipoNuevo === null ? (
            <div className="grid gap-3 py-2">
              <p className="text-sm text-muted-foreground">¿Qué tipo de cliente quieres agregar?</p>
              <button
                className="flex items-center gap-3 rounded-md border p-4 text-left transition-colors hover:border-red-300 hover:bg-red-50"
                onClick={() => setTipoNuevo("bomberos")}
              >
                <Flame className="h-6 w-6 shrink-0 text-red-500" />
                <div>
                  <div className="text-sm font-semibold">Cuerpo de Bomberos</div>
                  <div className="text-xs text-muted-foreground">Estructura de cuerpo con sus compañías asociadas.</div>
                </div>
              </button>
              <button
                className="flex items-center gap-3 rounded-md border p-4 text-left transition-colors hover:border-emerald-300 hover:bg-emerald-50"
                onClick={() => setTipoNuevo("empresa")}
              >
                <Building2 className="h-6 w-6 shrink-0 text-emerald-600" />
                <div>
                  <div className="text-sm font-semibold">Empresa</div>
                  <div className="text-xs text-muted-foreground">Cliente directo sin asociación a cuerpos ni compañías.</div>
                </div>
              </button>
              <button
                className="flex items-center gap-3 rounded-md border p-4 text-left transition-colors hover:border-violet-300 hover:bg-violet-50"
                onClick={() => setTipoNuevo("particular")}
              >
                <User className="h-6 w-6 shrink-0 text-violet-600" />
                <div>
                  <div className="text-sm font-semibold">Persona natural</div>
                  <div className="text-xs text-muted-foreground">Cotizaciones a una persona, sin giro ni empresa detrás.</div>
                </div>
              </button>
            </div>
          ) : !currentCuerpo && (tipoNuevo === "empresa" || tipoNuevo === "particular") ? (
            <EmpresaForm
              tipo={tipoNuevo}
              data={nuevaEmpresa}
              onChange={setNuevaEmpresa}
              error={errorNuevo}
              regiones={regiones}
              provincias={provincias}
            />
          ) : (
            <ClientForm
              data={{ ...nuevo, kind: currentCuerpo ? "compania" : "cuerpo", cuerpo: currentCuerpo }}
              onChange={(d) => setNuevo(d)}
              error={errorNuevo}
              regiones={regiones}
              provincias={provincias}
            />
          )}

          <DialogFooter className="gap-2">
            {!currentCuerpo && tipoNuevo !== null && (
              <Button variant="ghost" onClick={() => setTipoNuevo(null)} disabled={savingNuevo}>
                ← Cambiar tipo
              </Button>
            )}
            <Button variant="outline" onClick={() => setOpenNuevo(false)}>
              Cancelar
            </Button>
            {(currentCuerpo || tipoNuevo !== null) && (
              <Button onClick={crearCliente} disabled={savingNuevo}>
                {savingNuevo ? "Guardando..." : "Crear cliente"}
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={openDetalle} onOpenChange={setOpenDetalle}>
        <DialogContent className="sm:max-w-[620px] max-h-[85vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              {seleccionado ? nombreDe(seleccionado) : "Cliente"}
            </DialogTitle>
          </DialogHeader>
          {seleccionado?.kind === "cliente" ? (
            <EmpresaForm
              tipo={seleccionado.tipo === "particular" ? "particular" : "empresa"}
              data={editEmpresa}
              onChange={setEditEmpresa}
              error={errorDetalle}
              regiones={regiones}
              provincias={provincias}
            />
          ) : (
            <ClientForm
              data={{ ...edit, id: seleccionado?.id, kind: seleccionado?.kind === "compania" ? "compania" : "cuerpo" }}
              onChange={setEdit}
              error={errorDetalle}
              regiones={regiones}
              provincias={provincias}
            />
          )}
          <DialogFooter className="gap-2 sm:justify-between">
            <Button
              variant="destructive"
              onClick={eliminarCliente}
              disabled={deleting || savingEdit || !seleccionado?.id}
            >
              <Trash2 className="h-4 w-4" />
              {deleting ? "Eliminando..." : "Eliminar"}
            </Button>
            <div className="flex gap-2">
              <Button variant="outline" onClick={() => setOpenDetalle(false)}>
                Cerrar
              </Button>
              <Button onClick={guardarEdicion} disabled={savingEdit || deleting || !seleccionado?.id}>
                <Save className="h-4 w-4" />
                {savingEdit ? "Guardando..." : "Guardar cambios"}
              </Button>
            </div>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ClientQuotesHistoryDialog
        open={openHistory}
        onOpenChange={setOpenHistory}
        entity={historyItem ? { kind: historyItem.kind, id: historyItem.id, nombre: nombreDe(historyItem) } : null}
      />
    </>
  )
}
