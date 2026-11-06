"use client"

import { FechaInput } from "@/components/ui/fecha-input"
import { useEffect, useMemo, useState } from "react"
import { useParams, useRouter } from "next/navigation"
import { ArrowLeft, RefreshCcw, Save, User, X } from "lucide-react"
import { toast } from "sonner"

import { api } from "@/lib/api"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Label } from "@/components/ui/label"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { GiroSelect } from "@/components/comercial/GiroSelect"
import { ComunaSelect } from "@/components/comercial/ComunaSelect"
import { useGeografia } from "@/hooks/use-geografia"
import { useQuotableSkus } from "@/hooks/useQuotableSkus"
import { ResponsablePanel } from "@/components/comercial/ResponsablePanel"
import type { Traspaso, UsuarioRef } from "@/lib/responsables"
import { DestinatarioBadge } from "@/components/comercial/DestinatarioBadge"
import { tipoDestinatarioDe, type TipoDestinatario } from "@/lib/destinatario"
import { AsignarContactoDialog } from "@/components/comercial/AsignarContactoDialog"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"

type Estado = { id: number; nombre: string; scope?: string | null }
type Detalle = {
  sku: string
  n_item?: number
  cantidad: number
  subtotal?: number | null
  is_personalizable?: boolean

  producto?: string | null
  talla?: string | null
  color?: string | null
  genero?: string | null
  tipo_personalizacion?: string | null
  precio_unitario?: number | null
  total_neto?: number | null
  precio_iva?: number | null
}

type Contacto = {
  id: number;
  nombre: string;
  rut?: string | null;
  email?: string | null;
  telefono?: string | null;
}

// Cliente = solo clientes sueltos (empresa/particular). Ya no tiene `parent`
// desde que Cuerpo/Compañía dejaron de tener cliente espejo.
type Cliente = {
  id: string;
  tipo?: string | null;
  nombre_empresa: string;
  rut?: string;
  rut_empresa?: string;
  correo?: string;
  email?: string;
  telefono?: string;
  direccion?: string;
  giro?: string | null;
  region_id?: number | null;
  comuna_id?: number | null;
  comuna?: ComunaRef | null;
  contactos?: Contacto[];
}

type ComunaRef = { id: number; nombre: string; region_id?: number }

type Cuerpo = {
  id: string
  nombre: string
  rut?: string | null
  direccion?: string | null
  giro?: string | null
  region_id?: number | null
  comuna_id?: number | null
  comuna?: ComunaRef | null
}

type Compania = {
  id: string
  nombre: string
  cuerpo?: Cuerpo | null
}

type Cotizacion = {
  id: number
  nombre?: string | null // Nuevo campo
  estado_id?: number | null
  estado?: Estado | null

  cliente_id?: string | null
  cliente?: Cliente | null

  contacto_id?: number | null
  contacto?: Contacto | null
  cuerpo_id?: string | null
  cuerpo?: Cuerpo | null
  compania_id?: string | null
  compania?: Compania | null

  oportunidad_id?: number | null
  oportunidad?: {
    contacto?: Contacto | null
  } | null

  numero?: string | null
  tipo_destinatario?: TipoDestinatario | null
  /** A quién va (lo calcula el backend): "Cuerpo de Bomberos de …", la empresa o la persona. */
  nombre_cliente?: string | null
  // Responsable actual (user_id), quien la creó y los cambios de responsable
  user_id?: number | null
  creado_por_id?: number | null
  ejecutivo?: UsuarioRef | null
  creador?: UsuarioRef | null
  traspasos?: Traspaso[]

  origen?: string | null
  fecha_creacion?: string | null
  fecha_vencimiento?: string | null
  observaciones?: string | null
  plazo_produccion?: string | null
  condiciones_pago?: string | null
  despacho?: string | null

  detalles?: Detalle[] | null
}

const DEFAULT_PLAZO_PRODUCCION = "20 dias"

function toISODate(input?: string | null) {
  if (!input) return ""
  // si viene "2026-01-25 00:00:00" -> "2026-01-25"
  return input.slice(0, 10)
}

function safeNumber(v: any): number | null {
  if (v === null || v === undefined || v === "") return null
  const n = Number(v)
  return Number.isFinite(n) ? n : null
}


function addWeekdays(dateStr: string, days: number): string {
  if (!dateStr) return ""
  // Asegurar que se interprete como local o UTC consistente.
  // "2026-02-19" -> UTC. "2026-02-19T12:00:00" -> Local/UTC.
  // Para evitar problemas de zona horaria con fechas simples, usamos:
  const parts = dateStr.split(' ')[0].split('-')
  if (parts.length !== 3) return ""

  const d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]))

  let count = 0
  while (count < days) {
    d.setDate(d.getDate() + 1)
    const day = d.getDay()
    if (day !== 0 && day !== 6) { // 0=Sun, 6=Sat
      count++
    }
  }

  // Retornar YYYY-MM-DD manualmente para evitar desfases de toISOString
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const da = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${da}`
}


export default function CotizacionPage() {
  const params = useParams<{ id: string }>()
  const id = Number(params?.id)
  const router = useRouter()
  // Solo para etiquetar las comunas por región en el selector.
  const { regiones } = useGeografia()

  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [cotizacion, setCotizacion] = useState<Cotizacion | null>(null)

  // Editores de datos del cliente y del contacto (reemplazan al antiguo "Cambiar Cliente")
  const [clientOpen, setClientOpen] = useState(false)
  const [clientSaving, setClientSaving] = useState(false)
  const [clientDraft, setClientDraft] = useState({
    nombre_empresa: "",
    rut_empresa: "",
    direccion: "",
    comuna_id: null as number | null,
    giro: "",
    compania: "",
  })

  const [contactOpen, setContactOpen] = useState(false)
  const [contactSaving, setContactSaving] = useState(false)
  const [contactDraft, setContactDraft] = useState({ nombre: "", rut: "", email: "", telefono: "" })
  const [asignarOpen, setAsignarOpen] = useState(false)

  // draft editable
  const [nombre, setNombre] = useState("")  // Nuevo campo nombre
  const [observaciones, setObservaciones] = useState("")
  const [fechaVenc, setFechaVenc] = useState("")
  const [plazoProduccion, setPlazoProduccion] = useState("")
  const [condicionesPago, setCondicionesPago] = useState("")
  const [despacho, setDespacho] = useState("")

  const [detalles, setDetalles] = useState<Detalle[]>([])

  // Generic Comercial SKUs Dropdown State
  const [openSkuPopover, setOpenSkuPopover] = useState<number | null>(null)
  const [skusFilters, setSkusFilters] = useState<{ [key: number]: string }>({})
  const { products: comercialSkusList, loading: loadingSkus, error: skusError, reload: reloadSkus } = useQuotableSkus(
    openSkuPopover !== null, openSkuPopover === null ? "" : skusFilters[openSkuPopover] || "", openSkuPopover
  )

  async function load() {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const data = await api.get<Cotizacion>(`/api/cotizaciones/${id}`)

      setCotizacion(data)

      setNombre(data?.nombre ?? "") // Cargar nombre
      setObservaciones(data?.observaciones ?? "")

      // Lógica de Vencimiento:
      // 1. Si viene del backend, usarlo.
      // 2. Si no viene, calcular 15 días hábiles desde fecha_creacion.
      let venc = toISODate(data?.fecha_vencimiento ?? null)
      if (!venc && data?.fecha_creacion) {
        const creacion = toISODate(data.fecha_creacion)
        venc = addWeekdays(creacion, 15)
      }
      setFechaVenc(venc)

      setPlazoProduccion(data?.plazo_produccion?.trim() || DEFAULT_PLAZO_PRODUCCION)
      setCondicionesPago(data?.condiciones_pago ?? "")
      setDespacho(data?.despacho ?? "")

      setDetalles((data?.detalles ?? []).map((d: any) => ({
        ...d,
        precio_iva: (d.precio_unitario !== null && d.precio_unitario !== undefined) ? Math.round(d.precio_unitario * 1.19) : null
      })))
    } catch (e: any) {
      setError(e?.message || "Error cargando cotización")
      setCotizacion(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])


  const [options, setOptions] = useState<{
    colores: string[]
    tallas: string[]
    generos: string[]
    productos: string[]
    personalizaciones: string[]
    plazos_produccion: string[]
    condiciones_pago: string[]
    despacho: string[]
  }>({
    colores: [],
    tallas: [],
    generos: [],
    productos: [],
    personalizaciones: [],
    plazos_produccion: [],
    condiciones_pago: [],
    despacho: []
  })

  async function loadOptions() {
    try {
      const res = await api.get('/api/cotizaciones/options')
      if (res) {
        setOptions(res as any)
      }
    } catch (e) {
      console.error("Error loading options", e)
    }
  }

  useEffect(() => {
    load()
    loadOptions()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  const estadoLabel = useMemo(() => {
    if (!cotizacion) return "—"
    if (cotizacion.estado?.nombre) return cotizacion.estado.nombre
    if (cotizacion.estado_id) return `Estado #${cotizacion.estado_id}`
    return "—"
  }, [cotizacion])

  async function guardar() {
    if (!id) return
    setSaving(true)
    setError(null)
    try {
      // tu CotizacionUpdateRequest debe permitir esto:
      await api.patch(`/api/cotizaciones/${id}`, {
        cliente_id: cotizacion?.cliente_id,
        nombre: nombre?.trim() ? nombre.trim() : null, // Guardar nombre
        observaciones: observaciones?.trim() ? observaciones.trim() : null,
        fecha_vencimiento: fechaVenc ? fechaVenc : null,

        plazo_produccion: plazoProduccion?.trim() ? plazoProduccion.trim() : null,
        condiciones_pago: condicionesPago?.trim() ? condicionesPago.trim() : null,
        despacho: despacho?.trim() ? despacho.trim() : null,

        detalles: detalles.map((d, idx) => ({
          sku: d.sku || `TMP-${(d.n_item ?? idx + 1).toString().padStart(3, "0")}`,
          n_item: d.n_item ?? idx + 1,
          cantidad: Number(d.cantidad) || 0,
          subtotal: d.subtotal ?? null,
          is_personalizable: !!d.is_personalizable,

          producto: d.producto ?? null,
          talla: d.talla ?? null,
          color: d.color ?? null,
          genero: d.genero ?? null,
          tipo_personalizacion: d.tipo_personalizacion ?? null,
          precio_unitario: d.precio_unitario ?? null,
          total_neto: d.total_neto ?? null,
        })),
      })
      router.push("/comercial/cotizaciones") // o la ruta real de tu tablero
      await load()
    } catch (e: any) {
      setError(e?.message || "Error guardando cotización")
    } finally {
      setSaving(false)
    }
  }

  function updateDetalle(i: number, patch: Partial<Detalle>) {
    setDetalles((prev) => prev.map((d, idx) => (idx === i ? { ...d, ...patch } : d)))
  }

  function removeDetalle(i: number) {
    setDetalles((prev) => prev.filter((_, idx) => idx !== i))
  }

  // Logic for display: la Cotización ancla directo a Cuerpo/Compañía desde el
  // refactor de Red de Bomberos. `cliente` solo se usa como fallback legacy
  // (cotizaciones antiguas sin cuerpo_id/compania_id, o clientes sueltos).
  const cuerpo = cotizacion?.cuerpo ?? cotizacion?.compania?.cuerpo ?? null
  const compania = cotizacion?.compania ?? null
  const realClient = cotizacion?.cliente
  const tipoDestinatario = cotizacion ? tipoDestinatarioDe(cotizacion) : "empresa"
  const esPersonaNatural = tipoDestinatario === "persona_natural"

  // Contact priority: contacto directo de la cotización -> contacto de la oportunidad -> primer contacto del cliente
  const currentContacto = cotizacion?.contacto ?? cotizacion?.oportunidad?.contacto ?? realClient?.contactos?.[0] ?? null
  // A nombre de una persona sin Cuerpo, Compañía ni Empresa: sus datos son los del contacto (igual que en el PDF)
  const soloContacto = !cuerpo && !realClient && !!currentContacto

  const bodyData = cuerpo
    ? {
        nombre_empresa: cuerpo.nombre,
        rut_empresa: cuerpo.rut,
        direccion: cuerpo.direccion,
        // El giro del cuerpo ya se guarda en la BD: antes salía siempre vacío.
        giro: cuerpo.giro ?? null,
        comuna: cuerpo.comuna?.nombre ?? null,
        comuna_id: cuerpo.comuna_id ?? cuerpo.comuna?.id ?? null,
        region_id: cuerpo.region_id ?? cuerpo.comuna?.region_id ?? null,
      }
    : realClient
      ? {
          nombre_empresa: realClient.nombre_empresa,
          rut_empresa: realClient.rut_empresa ?? realClient.rut,
          direccion: realClient.direccion,
          giro: realClient.giro ?? null,
          comuna: realClient.comuna?.nombre ?? null,
          comuna_id: realClient.comuna_id ?? realClient.comuna?.id ?? null,
          // Sin comuna aún, usa la región del cliente para acotar la lista de comunas
          region_id: realClient.comuna?.region_id ?? realClient.region_id ?? null,
        }
      : soloContacto && currentContacto
        ? {
            nombre_empresa: currentContacto.nombre,
            rut_empresa: currentContacto.rut ?? null,
            direccion: null,
            giro: null,
            comuna: null,
            comuna_id: null,
            region_id: null,
          }
        : null

  const companiaLabel = compania ? compania.nombre : (cuerpo ? "Cuerpo de Bomberos (directo)" : "—")

  const isReadOnly = cotizacion?.estado?.nombre === 'Ventas' || cotizacion?.estado?.nombre === 'Aprobada por Cliente'

  function agregarProducto() {
    setDetalles([...detalles, {
      sku: "",
      cantidad: 1,
      producto: "",
      precio_unitario: 0,
      precio_iva: 0,
      total_neto: 0,
      is_personalizable: false
    }])
    setTimeout(() => {
      window.scrollTo({ top: document.body.scrollHeight, behavior: "smooth" })
    }, 100)
  }

  const contactName = currentContacto?.nombre || "—"
  const contactEmail = currentContacto?.email || realClient?.correo || realClient?.email || "—"
  const contactPhone = currentContacto?.telefono || realClient?.telefono || "—"

  function openClientEditor(open: boolean) {
    setClientOpen(open)
    if (open) {
      setClientDraft({
        nombre_empresa: bodyData?.nombre_empresa ?? "",
        rut_empresa: bodyData?.rut_empresa ?? "",
        direccion: bodyData?.direccion ?? "",
        comuna_id: bodyData?.comuna_id ?? null,
        giro: bodyData?.giro ?? "",
        compania: compania?.nombre ?? "",
      })
    }
  }

  async function guardarCliente() {
    if (!clientDraft.nombre_empresa.trim()) return
    setClientSaving(true)
    try {
      if (cuerpo?.id) {
        await api.patch(`/api/cuerpos/${cuerpo.id}`, {
          nombre: clientDraft.nombre_empresa.trim(),
          rut: clientDraft.rut_empresa.trim() || null,
          direccion: clientDraft.direccion.trim() || null,
          comuna_id: clientDraft.comuna_id ?? null,
          giro: clientDraft.giro.trim() || null,
        })

        if (compania?.id && clientDraft.compania.trim()) {
          await api.patch(`/api/companias/${compania.id}`, {
            nombre: clientDraft.compania.trim(),
          })
        }
      } else if (realClient?.id) {
        await api.patch(`/api/clientes/${realClient.id}`, {
          nombre_empresa: clientDraft.nombre_empresa.trim(),
          rut_empresa: clientDraft.rut_empresa.trim() || null,
          direccion: clientDraft.direccion.trim() || null,
          comuna_id: clientDraft.comuna_id ?? null,
          giro: clientDraft.giro.trim() || null,
        })
      } else {
        setClientSaving(false)
        return
      }

      toast.success("Datos del cliente actualizados")
      setClientOpen(false)
      await load()
    } catch (e: any) {
      toast.error(e?.message || "Error actualizando el cliente")
    } finally {
      setClientSaving(false)
    }
  }

  function openContactEditor(open: boolean) {
    setContactOpen(open)
    if (open) {
      setContactDraft({
        nombre: currentContacto?.nombre ?? "",
        rut: currentContacto?.rut ?? "",
        email: currentContacto?.email ?? realClient?.correo ?? "",
        telefono: currentContacto?.telefono ?? realClient?.telefono ?? "",
      })
    }
  }

  async function guardarContacto() {
    if (!contactDraft.nombre.trim()) return
    if (!currentContacto?.id && !cotizacion?.cliente_id && !cuerpo?.id && !compania?.id) return
    setContactSaving(true)
    try {
      const payload = {
        nombre: contactDraft.nombre.trim(),
        rut: contactDraft.rut.trim() || null,
        email: contactDraft.email.trim() || null,
        telefono: contactDraft.telefono.trim() || null,
      }

      if (currentContacto?.id) {
        // El contacto ya existe: se actualiza donde vive (tabla contactos)
        await api.patch(`/api/contactos/${currentContacto.id}`, payload)
      } else {
        // No hay contacto aún: se crea vinculado a la compañía/cuerpo/cliente
        // de esta cotización, y se enlaza en la cotización (y la oportunidad,
        // si viene de una) para que el PDF lo use.
        const vinculo = compania?.id
          ? { compania_ids: [compania.id] }
          : cuerpo?.id
            ? { cuerpo_ids: [cuerpo.id] }
            : { cliente_id: cotizacion?.cliente_id }

        const nuevo = await api.post<Contacto>(`/api/contactos`, { ...vinculo, ...payload })

        if (cotizacion?.oportunidad_id && nuevo?.id) {
          await api.patch(`/api/oportunidades/${cotizacion.oportunidad_id}`, { contacto_id: nuevo.id })
        }
        if (nuevo?.id && cotizacion?.id && !cotizacion.contacto_id) {
          await api.patch(`/api/cotizaciones/${cotizacion.id}`, { contacto_id: nuevo.id })
        }
      }

      toast.success("Datos del contacto actualizados")
      setContactOpen(false)
      await load()
    } catch (e: any) {
      toast.error(e?.message || "Error actualizando el contacto")
    } finally {
      setContactSaving(false)
    }
  }

  return (
    <div className="space-y-4 p-6">
      {/* Datalists for autocomplete */}
      <datalist id="list-productos">
        {options.productos.map((op, i) => <option key={i} value={op} />)}
      </datalist>
      <datalist id="list-tallas">
        {options.tallas.map((op, i) => <option key={i} value={op} />)}
      </datalist>
      <datalist id="list-colores">
        {options.colores.map((op, i) => <option key={i} value={op} />)}
      </datalist>
      <datalist id="list-generos">
        {options.generos.map((op, i) => <option key={i} value={op} />)}
      </datalist>
      <datalist id="list-personalizaciones">
        {options.personalizaciones.map((op, i) => <option key={i} value={op} />)}
      </datalist>
      <datalist id="list-plazos">
        {options.plazos_produccion.map((op, i) => <option key={i} value={op} />)}
      </datalist>
      <datalist id="list-condiciones">
        {options.condiciones_pago.map((op, i) => <option key={i} value={op} />)}
      </datalist>
      <datalist id="list-despacho">
        {options.despacho.map((op, i) => <option key={i} value={op} />)}
      </datalist>


      <div className="flex items-center justify-between gap-4 border-b pb-6">
        <div>
          <div className="flex items-baseline gap-3">
            <h1 className="text-3xl font-bold tracking-tight">Cotización #{id}</h1>
            {nombre && (
              <div className="text-xl text-muted-foreground font-light px-2 border-l border-border h-6 flex items-center">
                {nombre}
              </div>
            )}
          </div>
          <div className="text-sm text-muted-foreground mt-1 flex items-center gap-2">
            <span className="font-medium text-foreground">{cotizacion?.nombre_cliente || cotizacion?.cliente?.nombre_empresa || "Cliente no asignado"}</span>
            <span>•</span>
            <span>{toISODate(cotizacion?.fecha_creacion)}</span>
            {cotizacion && <DestinatarioBadge tipo={tipoDestinatario} className="text-xs px-2 py-1" />}
          </div>
          {cotizacion && (
            <ResponsablePanel
              className="mt-3 max-w-xl"
              tarjeta={cotizacion}
              tipo="cotizaciones"
              tarjetaId={cotizacion.id}
              codigo={cotizacion.numero || `CT-${String(cotizacion.id).padStart(4, "0")}`}
              onTraspasado={() => load()}
            />
          )}
        </div>

        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={() => router.push("/comercial/cotizaciones")}>
            <ArrowLeft className="mr-2 h-4 w-4" />
            Volver
          </Button>
          <Button variant="outline" size="sm" onClick={load} disabled={loading}>
            <RefreshCcw className="mr-2 h-4 w-4" />
            {loading ? "Cargando..." : "Refrescar"}
          </Button>
        </div>
      </div>

      {error && <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}

      <AsignarContactoDialog
        open={asignarOpen}
        onOpenChange={setAsignarOpen}
        contacto={soloContacto && currentContacto ? { id: currentContacto.id, nombre: currentContacto.nombre } : null}
        onAsignado={() => load()}
      />

      <Card>
        <CardHeader>
          <CardTitle>Resumen</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-6">
          {/* Cliente Section */}
          <div className="rounded-md border p-4 bg-muted/20">
            <div className="flex items-center justify-between mb-4">
              <h3 className="font-semibold flex items-center gap-2">
                <User className="h-4 w-4" />
                {esPersonaNatural ? "Datos de la Persona Natural" : "Datos del Cliente"}
                {cotizacion && <DestinatarioBadge tipo={tipoDestinatario} />}
              </h3>
              <div className="flex gap-2">
              {soloContacto && (
                <Button
                  variant="outline"
                  size="sm"
                  className="border-emerald-200 text-emerald-700 hover:bg-emerald-50"
                  onClick={() => setAsignarOpen(true)}
                  title="Asignar la persona a un Cuerpo, Compañía o Empresa"
                >
                  Asignar organización
                </Button>
              )}
              {/* A nombre de una persona sin organización no hay cliente que editar: sus datos son los del contacto */}
              {!soloContacto && (
              <Popover open={clientOpen} onOpenChange={openClientEditor}>
                <PopoverTrigger asChild>
                  <Button variant="outline" size="sm">Editar Cliente</Button>
                </PopoverTrigger>
                <PopoverContent className="w-[320px]" align="end">
                  <div className="space-y-3">
                    <div className="space-y-1">
                      <Label htmlFor="cliente-razon">{esPersonaNatural ? "Nombre completo" : "Razón Social"}</Label>
                      <Input
                        id="cliente-razon"
                        value={clientDraft.nombre_empresa}
                        onChange={(e) => setClientDraft(prev => ({ ...prev, nombre_empresa: e.target.value }))}
                        placeholder={esPersonaNatural ? "Nombre completo" : "Razón social"}
                      />
                    </div>
                    <div className="space-y-1">
                      <Label htmlFor="cliente-rut">RUT</Label>
                      <Input
                        id="cliente-rut"
                        value={clientDraft.rut_empresa}
                        onChange={(e) => setClientDraft(prev => ({ ...prev, rut_empresa: e.target.value }))}
                        placeholder="76.123.456-7"
                      />
                    </div>
                    <div className="space-y-1">
                      <Label htmlFor="cliente-direccion">Dirección</Label>
                      <Input
                        id="cliente-direccion"
                        value={clientDraft.direccion}
                        onChange={(e) => setClientDraft(prev => ({ ...prev, direccion: e.target.value }))}
                        placeholder="Calle 123"
                      />
                    </div>
                    <div className="space-y-1">
                      <Label htmlFor="cliente-comuna">Comuna</Label>
                      <ComunaSelect
                        id="cliente-comuna"
                        value={clientDraft.comuna_id}
                        onChange={(comunaId) => setClientDraft(prev => ({ ...prev, comuna_id: comunaId }))}
                        regionId={bodyData?.region_id ?? null}
                        regiones={regiones}
                      />
                    </div>
                    {!esPersonaNatural && (
                      <div className="space-y-1">
                        <Label>Giro</Label>
                        <GiroSelect
                          value={clientDraft.giro}
                          onChange={(v) => setClientDraft(prev => ({ ...prev, giro: v }))}
                        />
                      </div>
                    )}
                    {compania && (
                      <div className="space-y-1">
                        <Label htmlFor="cliente-compania">Compañía</Label>
                        <Input
                          id="cliente-compania"
                          value={clientDraft.compania}
                          onChange={(e) => setClientDraft(prev => ({ ...prev, compania: e.target.value }))}
                          placeholder="Nombre de la compañía"
                        />
                      </div>
                    )}
                    <div className="flex justify-end gap-2 pt-1">
                      <Button variant="ghost" size="sm" onClick={() => setClientOpen(false)}>
                        Cancelar
                      </Button>
                      <Button
                        size="sm"
                        onClick={guardarCliente}
                        disabled={clientSaving || !clientDraft.nombre_empresa.trim()}
                      >
                        {clientSaving ? "Guardando..." : "Guardar"}
                      </Button>
                    </div>
                  </div>
                </PopoverContent>
              </Popover>
              )}

              <Popover open={contactOpen} onOpenChange={openContactEditor}>
                <PopoverTrigger asChild>
                  <Button variant="outline" size="sm">Editar Contacto</Button>
                </PopoverTrigger>
                <PopoverContent className="w-[320px]" align="end">
                  <div className="space-y-3">
                    <div className="space-y-1">
                      <Label htmlFor="contacto-nombre">Nombre</Label>
                      <Input
                        id="contacto-nombre"
                        value={contactDraft.nombre}
                        onChange={(e) => setContactDraft(prev => ({ ...prev, nombre: e.target.value }))}
                        placeholder="Nombre del contacto"
                      />
                    </div>
                    <div className="space-y-1">
                      <Label htmlFor="contacto-rut">RUT</Label>
                      <Input
                        id="contacto-rut"
                        value={contactDraft.rut}
                        onChange={(e) => setContactDraft(prev => ({ ...prev, rut: e.target.value }))}
                        placeholder="12.345.678-9"
                      />
                    </div>
                    <div className="space-y-1">
                      <Label htmlFor="contacto-email">Email</Label>
                      <Input
                        id="contacto-email"
                        type="email"
                        value={contactDraft.email}
                        onChange={(e) => setContactDraft(prev => ({ ...prev, email: e.target.value }))}
                        placeholder="correo@ejemplo.cl"
                      />
                    </div>
                    <div className="space-y-1">
                      <Label htmlFor="contacto-telefono">Teléfono</Label>
                      <Input
                        id="contacto-telefono"
                        value={contactDraft.telefono}
                        onChange={(e) => setContactDraft(prev => ({ ...prev, telefono: e.target.value }))}
                        placeholder="+56 9 ..."
                      />
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                      <Button variant="ghost" size="sm" onClick={() => setContactOpen(false)}>
                        Cancelar
                      </Button>
                      <Button
                        size="sm"
                        onClick={guardarContacto}
                        disabled={contactSaving || !contactDraft.nombre.trim()}
                      >
                        {contactSaving ? "Guardando..." : "Guardar"}
                      </Button>
                    </div>
                  </div>
                </PopoverContent>
              </Popover>
              </div>
            </div>

            {soloContacto && (
              <p className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                Va a nombre de {currentContacto?.nombre}, que no tiene Cuerpo, Compañía ni Empresa: el PDF sale a su nombre. Si sabes de dónde es, usa <span className="font-medium">Asignar organización</span>.
              </p>
            )}

            <div className="grid gap-4 md:grid-cols-2 text-sm">
              {/* Left Side: Body Data */}
              <div className="space-y-3">
                <div>
                  <Label className="text-xs text-muted-foreground">{esPersonaNatural ? "Nombre completo" : "Razón Social"}</Label>
                  <div className="font-medium">{bodyData?.nombre_empresa || "—"}</div>
                </div>
                <div>
                  <Label className="text-xs text-muted-foreground">RUT</Label>
                  <div>{bodyData?.rut_empresa || "—"}</div>
                </div>
                <div>
                  <Label className="text-xs text-muted-foreground">Dirección</Label>
                  <div>{bodyData?.direccion || "—"}</div>
                </div>
                <div>
                  <Label className="text-xs text-muted-foreground">Comuna</Label>
                  <div>{bodyData?.comuna || "—"}</div>
                </div>
                {!esPersonaNatural && (
                  <div>
                    <Label className="text-xs text-muted-foreground">Giro</Label>
                    <div>{bodyData?.giro || "—"}</div>
                  </div>
                )}
              </div>

              {/* Right Side: Contact Data */}
              <div className="space-y-3">
                {!esPersonaNatural && (
                  <div>
                    <Label className="text-xs text-muted-foreground">Compañía</Label>
                    <div className="font-medium">{companiaLabel || "—"}</div>
                  </div>
                )}
                <div>
                  <Label className="text-xs text-muted-foreground">Contacto</Label>
                  <div className="font-medium">{contactName}</div>
                </div>
                <div>
                  <Label className="text-xs text-muted-foreground">Email</Label>
                  <div>{contactEmail}</div>
                </div>
                <div>
                  <Label className="text-xs text-muted-foreground">Teléfono</Label>
                  <div>{contactPhone}</div>
                </div>
              </div>
            </div>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-1">
              <div className="text-muted-foreground text-sm">Estado</div>
              <div className="font-semibold">{estadoLabel}</div>
            </div>

            <div className="space-y-1">
              <div className="text-muted-foreground">Origen</div>
              <div className="font-semibold">{cotizacion?.origen ?? "—"}</div>
            </div>

            <div className="md:col-span-2 space-y-1">
              <Label className="text-muted-foreground">Nombre de la Cotización</Label>
              <Input
                value={nombre}
                onChange={(e) => setNombre(e.target.value)}
                placeholder="Ej: Cotización Camisetas Aniversario"
              />
            </div>

            <div className="space-y-1">
              <div className="text-muted-foreground">Fecha creación</div>
              <div className="font-semibold">{toISODate(cotizacion?.fecha_creacion ?? null) || "—"}</div>
            </div>

            <div className="space-y-1">
              <Label className="text-muted-foreground">Fecha vencimiento</Label>
              <FechaInput value={fechaVenc} onChange={v => setFechaVenc(v)} />
            </div>

            <div className="md:col-span-2 space-y-1">
              <Label className="text-muted-foreground">Observaciones</Label>
              <Textarea value={observaciones} onChange={(e) => setObservaciones(e.target.value)} placeholder="—" />
            </div>

            {/* Condiciones Comerciales - Nueva Zona */}
            <div className="md:col-span-2 grid gap-4 grid-cols-1 md:grid-cols-3 pt-2 border-t mt-2">
              <div>
                <Label className="text-xs text-muted-foreground">{isReadOnly ? "Plazo de entrega" : "Plazo Producción"}</Label>
                <Select value={plazoProduccion} onValueChange={(v) => setPlazoProduccion(v)}>
                  <SelectTrigger className="h-9">
                    <SelectValue placeholder="Seleccionar..." />
                  </SelectTrigger>
                  <SelectContent>
                    {options.plazos_produccion.map((op, i) => (
                      <SelectItem key={i} value={op}>{op}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label className="text-xs text-muted-foreground">Condiciones de pago</Label>
                <Select value={condicionesPago} onValueChange={(v) => setCondicionesPago(v)}>
                  <SelectTrigger className="h-9">
                    <SelectValue placeholder="Seleccionar..." />
                  </SelectTrigger>
                  <SelectContent>
                    {options.condiciones_pago.map((op, i) => (
                      <SelectItem key={i} value={op}>{op}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label className="text-xs text-muted-foreground">Despacho</Label>
                <Select value={despacho} onValueChange={(v) => setDespacho(v)}>
                  <SelectTrigger className="h-9">
                    <SelectValue placeholder="Seleccionar..." />
                  </SelectTrigger>
                  <SelectContent>
                    {options.despacho.map((op, i) => (
                      <SelectItem key={i} value={op}>{op}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Detalles</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          {detalles.length === 0 ? (
            <div className="rounded-md border p-6 text-center text-muted-foreground">
              <p>No hay productos en esta cotización.</p>
              <p className="text-sm mt-1">Presiona "Agregar Producto" para comenzar.</p>
            </div>
          ) : (
            <div className="space-y-4">
              {detalles.map((d, i) => (
                <div key={i} className="rounded-md border p-4 bg-card">
                  <div className="grid gap-4">
                    {/* Fila 1: Datos principales */}
                    <div className="grid gap-4 md:grid-cols-12">
                      <div className="md:col-span-3">
                        <Label className="text-xs text-muted-foreground">Producto (SKU Genérico)</Label>
                        <Popover
                          open={openSkuPopover === i}
                          onOpenChange={(open) => {
                            setOpenSkuPopover(open ? i : null)
                          }}
                        >
                          <PopoverTrigger asChild>
                            <Button
                              variant="outline"
                              role="combobox"
                              className="w-full justify-between font-normal text-left h-9 px-3"
                            >
                              {d.producto ? (
                                <span className="truncate">{d.producto}</span>
                              ) : (
                                <span className="text-muted-foreground">Seleccionar producto...</span>
                              )}
                            </Button>
                          </PopoverTrigger>
                          <PopoverContent className="w-[300px] p-0" align="start">
                            <div className="p-2 border-b">
                              <Input
                                placeholder="Buscar producto..."
                                className="h-8"
                                value={skusFilters[i] || ""}
                                onChange={(e) => {
                                  const val = e.target.value
                                  setSkusFilters(prev => ({ ...prev, [i]: val }))
                                }}
                              />
                            </div>
                            <div className="max-h-[200px] overflow-y-auto">
                              {loadingSkus ? <p role="status" className="p-3 text-xs text-center text-muted-foreground">Cargando productos cotizables…</p>
                              : skusError ? <div role="alert" className="p-3 space-y-2 text-xs text-red-700"><p>{skusError}</p><Button type="button" size="sm" variant="outline" onClick={reloadSkus}>Reintentar</Button></div>
                              : comercialSkusList.length === 0 ? (
                                <div className="p-3 text-xs text-center text-muted-foreground">No se encontraron productos</div>
                              ) : (
                                comercialSkusList.map((sku: any) => (
                                  <button
                                    key={sku.id}
                                    className="w-full text-left px-3 py-2 text-sm hover:bg-muted transition-colors border-b last:border-0"
                                    onClick={() => {
                                      const cant = d.cantidad || 1;
                                      const precioBruto = sku.precio_venta || 0;
                                      const precioNeto = Math.round(precioBruto / 1.19);
                                      updateDetalle(i, {
                                        producto: sku.nombre,
                                        sku: sku.sku,
                                        precio_unitario: precioNeto,
                                        precio_iva: precioBruto,
                                        total_neto: precioNeto * cant,
                                        cantidad: cant
                                      })
                                      setOpenSkuPopover(null)
                                    }}
                                  >
                                    <div className="min-w-0">
                                      <div className="font-medium text-xs truncate">{sku.nombre}</div>
                                      <div className="text-[10px] text-muted-foreground font-mono">{sku.sku}</div>
                                    </div>
                                  </button>
                                ))
                              )}
                            </div>
                          </PopoverContent>
                        </Popover>
                        {d.sku && <p className="text-[10px] text-muted-foreground mt-1 font-mono">SKU: {d.sku}</p>}
                      </div>

                      <div className="md:col-span-2">
                        <Label className="text-xs text-muted-foreground">Cantidad</Label>
                        <Input
                          type="number"
                          min={1}
                          value={d.cantidad}
                          onChange={(e) => {
                            const cant = Number(e.target.value) || 0;
                            const patch: Partial<Detalle> = { 
                              cantidad: cant,
                              total_neto: cant * (d.precio_unitario ?? 0)
                            };
                            updateDetalle(i, patch);
                          }}
                        />
                      </div>

                      <div className="md:col-span-2">
                        <Label className="text-xs text-muted-foreground">Precio Unit. (Neto)</Label>
                        <Input
                          type="number"
                          value={d.precio_unitario ?? ""}
                          onChange={(e) => {
                            const price = safeNumber(e.target.value) ?? 0;
                            const ivaPrice = Math.round(price * 1.19);
                            updateDetalle(i, {
                              precio_unitario: price,
                              precio_iva: ivaPrice,
                              total_neto: price * (d.cantidad || 0)
                            });
                          }}
                        />
                      </div>

                      <div className="md:col-span-2">
                        <Label className="text-xs text-muted-foreground">Precio Unit. (+IVA)</Label>
                        <Input
                          type="number"
                          value={d.precio_iva ?? ""}
                          onChange={(e) => {
                            const priceIva = safeNumber(e.target.value) ?? 0;
                            const priceNeto = Math.round(priceIva / 1.19);
                            updateDetalle(i, {
                              precio_iva: priceIva,
                              precio_unitario: priceNeto,
                              total_neto: priceNeto * (d.cantidad || 0)
                            });
                          }}
                        />
                      </div>

                      <div className="md:col-span-3">
                        <Label className="text-xs text-muted-foreground">Total Neto</Label>
                        <Input
                          type="number"
                          value={d.total_neto ?? ""}
                          readOnly
                          className="bg-muted font-medium"
                        />
                      </div>
                    </div>

                    {/* Fila 2: Atributos extra */}
                    <div className="grid gap-4 md:grid-cols-4 bg-muted/30 p-2 rounded text-sm">
                      <div>
                        <Label className="text-xs">Talla</Label>
                        <Select value={d.talla || ""} onValueChange={(v) => updateDetalle(i, { talla: v })}>
                          <SelectTrigger className="h-8">
                            <SelectValue placeholder="-" />
                          </SelectTrigger>
                          <SelectContent>
                            {options.tallas.map((op, k) => (
                              <SelectItem key={k} value={op}>{op}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div>
                        <Label className="text-xs">Color</Label>
                        <Select value={d.color || ""} onValueChange={(v) => updateDetalle(i, { color: v })}>
                          <SelectTrigger className="h-8">
                            <SelectValue placeholder="-" />
                          </SelectTrigger>
                          <SelectContent>
                            {options.colores.map((op, k) => (
                              <SelectItem key={k} value={op}>{op}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div>
                        <Label className="text-xs">Género</Label>
                        <Select value={d.genero || ""} onValueChange={(v) => updateDetalle(i, { genero: v })}>
                          <SelectTrigger className="h-8">
                            <SelectValue placeholder="-" />
                          </SelectTrigger>
                          <SelectContent>
                            {options.generos.map((op, k) => (
                              <SelectItem key={k} value={op}>{op}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div>
                        <Label className="text-xs">Personalización</Label>
                        <Select
                          value={d.tipo_personalizacion || ""}
                          onValueChange={(v) => updateDetalle(i, { tipo_personalizacion: v, is_personalizable: !!v })}
                        >
                          <SelectTrigger className="h-8">
                            <SelectValue placeholder="-" />
                          </SelectTrigger>
                          <SelectContent>
                            {options.personalizaciones.map((op, k) => (
                              <SelectItem key={k} value={op}>{op}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                    </div>

                    {!isReadOnly && (
                      <div className="flex justify-end">
                        <Button
                          variant="ghost"
                          size="sm"
                          className="text-red-500 hover:text-red-700 hover:bg-red-50 h-8"
                          onClick={() => removeDetalle(i)}
                        >
                          Eliminar ítem
                        </Button>
                      </div>
                    )}
                  </div>
                </div>
              ))}

              {/* Summary Totals */}
              <div className="flex justify-end p-4 border-t mt-4">
                <div className="w-64 space-y-2 text-sm">
                  <div className="flex justify-between items-center text-muted-foreground">
                    <span>Total Neto</span>
                    <span>${detalles.reduce((acc, d) => acc + (d.total_neto || 0), 0).toLocaleString('es-CL')}</span>
                  </div>
                  <div className="flex justify-between items-center text-muted-foreground">
                    <span>IVA (19%)</span>
                    <span>${Math.round(detalles.reduce((acc, d) => acc + (d.total_neto || 0), 0) * 0.19).toLocaleString('es-CL')}</span>
                  </div>
                  <div className="flex justify-between items-center font-bold text-base border-t pt-2">
                    <span>Total Bruto</span>
                    <span>${Math.round(detalles.reduce((acc, d) => acc + (d.total_neto || 0), 0) * 1.19).toLocaleString('es-CL')}</span>
                  </div>
                </div>
              </div>

            </div>
          )}
        </CardContent>
      </Card>

      {/* Floating Save Button */}
      {!isReadOnly && (
        <div className="sticky bottom-6 flex justify-start gap-3 z-50 mt-4">
          <Button onClick={guardar} disabled={saving} className="shadow-lg rounded-full px-6 flex items-center gap-2">
            <Save className="h-4 w-4" />
            {saving ? "Guardando..." : "Guardar Cambios"}
          </Button>
          <Button onClick={agregarProducto} variant="secondary" className="shadow-lg rounded-full px-6 border">
            + Agregar Producto
          </Button>
        </div>
      )}

    </div>
  )
}
