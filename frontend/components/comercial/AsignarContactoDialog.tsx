"use client"

import { useEffect, useRef, useState } from "react"
import { Building2, Briefcase, Check, Flame, Loader2, Search, X } from "lucide-react"
import { toast } from "sonner"
import { api } from "@/lib/api"
import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Input } from "@/components/ui/input"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import {
  organizacionesDe, resumenAsignacion,
  type ContactoAsignable, type OrganizacionOpcion, type RegistrosSinEntidad, type ResultadoAsignacion,
} from "@/lib/asignacion-contacto"

export type { ContactoAsignable, OrganizacionOpcion, ResultadoAsignacion } from "@/lib/asignacion-contacto"

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  contacto: ContactoAsignable | null
  /** Organización ya elegida (p. ej. al vincular desde la ficha de una Compañía): solo se confirma. */
  organizacionInicial?: OrganizacionOpcion | null
  onAsignado?: (resultado: ResultadoAsignacion) => void
}

const ICONO: Record<OrganizacionOpcion["tipo"], React.ElementType> = { cuerpo: Flame, compania: Building2, empresa: Briefcase }
const ETIQUETA: Record<OrganizacionOpcion["tipo"], string> = { cuerpo: "Cuerpo", compania: "Compañía", empresa: "Empresa" }
const MAX_RESULTADOS = 30

function lista<T>(res: any): T[] {
  return Array.isArray(res) ? res : (res?.data || [])
}

/**
 * Asigna un contacto a un Cuerpo, una Compañía o una Empresa después de creado.
 * Si tiene leads o cotizaciones a su nombre (persona natural), deja elegir cuáles
 * pasan a la organización; las ventas y los cierres quedan como se emitieron.
 */
export function AsignarContactoDialog({ open, onOpenChange, contacto, organizacionInicial = null, onAsignado }: Props) {
  const [busqueda, setBusqueda] = useState("")
  const [opciones, setOpciones] = useState<OrganizacionOpcion[]>([])
  const [buscando, setBuscando] = useState(false)
  const [fallidas, setFallidas] = useState<string[]>([])
  const [elegida, setElegida] = useState<OrganizacionOpcion | null>(organizacionInicial)
  const [registros, setRegistros] = useState<RegistrosSinEntidad | null>(null)
  const [marcados, setMarcados] = useState<Set<string>>(new Set())
  const [guardando, setGuardando] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // La última búsqueda escrita manda: las respuestas viejas se descartan
  const secuencia = useRef(0)
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null)

  // Se reinicia al abrir o al cambiar de persona/organización, no cada vez que el padre
  // vuelve a armar el objeto (p. ej. un contacto escrito en línea en el JSX)
  const contactoId = contacto?.id
  const inicialClave = organizacionInicial ? `${organizacionInicial.tipo}-${organizacionInicial.id}` : null
  const inicial = useRef(organizacionInicial)
  inicial.current = organizacionInicial

  useEffect(() => {
    if (!open || !contactoId) return
    setBusqueda("")
    setOpciones([])
    setFallidas([])
    setElegida(inicial.current)
    setError(null)
    setRegistros(null)
    api.get<RegistrosSinEntidad>(`/api/contactos/${contactoId}/registros-sin-entidad`)
      .then((r) => {
        setRegistros(r)
        // Por defecto pasan todos los abiertos: es lo que se espera al saber de quién es la persona
        setMarcados(new Set([
          ...r.oportunidades.filter((o) => o.abierta).map((o) => `op-${o.id}`),
          ...r.cotizaciones.filter((c) => c.abierta).map((c) => `ct-${c.id}`),
        ]))
      })
      .catch(() => setRegistros({ oportunidades: [], cotizaciones: [] }))
  }, [open, contactoId, inicialClave])

  function buscar(texto: string) {
    setBusqueda(texto)
    if (timer.current) clearTimeout(timer.current)
    const q = texto.trim()
    if (q.length < 2) {
      setOpciones([])
      setFallidas([])
      return
    }
    timer.current = setTimeout(async () => {
      const mia = ++secuencia.current
      setBuscando(true)
      const qs = encodeURIComponent(q)
      // allSettled: si una fuente falla se muestran las otras y se avisa cuál no respondió
      const [cuerpos, companias, empresas] = await Promise.allSettled([
        api.get<any>(`/api/cuerpos?search=${qs}`),
        api.get<any>(`/api/companias?search=${qs}`),
        api.get<any>(`/api/clientes?search=${qs}&per_page=10&tipo=empresa`),
      ])
      if (mia !== secuencia.current) return
      const ok = (r: PromiseSettledResult<any>) => (r.status === "fulfilled" ? lista<any>(r.value) : [])
      setOpciones([
        ...ok(cuerpos).map((c): OrganizacionOpcion => ({ tipo: "cuerpo", id: String(c.id), nombre: c.nombre })),
        ...ok(companias).map((c): OrganizacionOpcion => ({ tipo: "compania", id: String(c.id), nombre: c.nombre, subtitulo: c.cuerpo?.nombre ?? null })),
        ...ok(empresas).map((c): OrganizacionOpcion => ({ tipo: "empresa", id: String(c.id), nombre: c.nombre_empresa })),
      ].slice(0, MAX_RESULTADOS))
      setFallidas([
        cuerpos.status === "rejected" ? "Cuerpos" : null,
        companias.status === "rejected" ? "Compañías" : null,
        empresas.status === "rejected" ? "Empresas" : null,
      ].filter((f): f is string => !!f))
      setBuscando(false)
    }, 250)
  }

  function alternar(clave: string) {
    setMarcados((prev) => {
      const siguiente = new Set(prev)
      if (siguiente.has(clave)) siguiente.delete(clave)
      else siguiente.add(clave)
      return siguiente
    })
  }

  async function asignar() {
    if (!contacto || !elegida) return
    setGuardando(true)
    setError(null)
    try {
      const idsMarcados = (prefijo: string) => [...marcados].filter((k) => k.startsWith(prefijo)).map((k) => Number(k.slice(prefijo.length)))
      const res = await api.post<Omit<ResultadoAsignacion, "organizacion">>(`/api/contactos/${contacto.id}/asignar`, {
        tipo: elegida.tipo,
        id: elegida.id,
        oportunidad_ids: idsMarcados("op-"),
        cotizacion_ids: idsMarcados("ct-"),
      })
      const resultado: ResultadoAsignacion = { ...res, organizacion: elegida }
      toast.success(resumenAsignacion(contacto.nombre, resultado))
      onAsignado?.(resultado)
      onOpenChange(false)
    } catch (e: any) {
      const errores = e?.data?.errors ? (Object.values(e.data.errors).flat() as string[]) : []
      setError(errores[0] || e?.data?.message || e?.message || "No se pudo asignar el contacto.")
    } finally {
      setGuardando(false)
    }
  }

  const actuales = contacto ? organizacionesDe(contacto) : []
  // Desde un lead llega el contacto sin sus organizaciones: ahí no se sabe cuáles tiene hoy
  const conoceActuales = !!contacto && (contacto.cuerpos !== undefined || contacto.companias !== undefined || contacto.cliente !== undefined)
  const pendientes = registros ? [...registros.oportunidades, ...registros.cotizaciones] : []
  const IconoElegida = elegida ? ICONO[elegida.tipo] : Building2

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[560px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Building2 className="h-5 w-5 text-emerald-600" /> Asignar a {contacto?.nombre}
          </DialogTitle>
          <DialogDescription>
            Elige el Cuerpo, la Compañía o la Empresa a la que pertenece. Queda como contacto de esa organización.
          </DialogDescription>
        </DialogHeader>

        <div className="grid gap-4">
          {conoceActuales && (
            <p className="text-xs text-muted-foreground">
              Hoy: {actuales.length ? actuales.join(" · ") : <span className="font-medium text-amber-700">sin organización</span>}
            </p>
          )}

          {elegida ? (
            <div className="flex items-center justify-between gap-3 rounded-md border border-emerald-200 bg-emerald-50/60 p-3">
              <div className="flex min-w-0 items-center gap-2">
                <IconoElegida className="h-4 w-4 shrink-0 text-emerald-700" />
                <div className="min-w-0">
                  <p className="truncate text-sm font-semibold text-emerald-900">{elegida.nombre}</p>
                  <p className="text-xs text-emerald-800/80">
                    {ETIQUETA[elegida.tipo]}{elegida.subtitulo ? ` · ${elegida.subtitulo}` : ""}
                  </p>
                </div>
              </div>
              <Button type="button" variant="ghost" size="sm" onClick={() => setElegida(null)} disabled={guardando}>
                <X className="mr-1 h-3.5 w-3.5" /> Cambiar
              </Button>
            </div>
          ) : (
            <div className="grid gap-2">
              <div className="relative">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  autoFocus
                  value={busqueda}
                  onChange={(e) => buscar(e.target.value)}
                  placeholder="Buscar Cuerpo, Compañía o Empresa…"
                  className="pl-8"
                  aria-label="Buscar organización"
                />
              </div>
              <div className="max-h-56 overflow-y-auto rounded-md border" role="listbox" aria-label="Organizaciones">
                {busqueda.trim().length < 2 ? (
                  <p className="p-3 text-xs text-muted-foreground">Escribe al menos 2 letras del nombre.</p>
                ) : buscando && opciones.length === 0 ? (
                  <p className="flex items-center gap-2 p-3 text-xs text-muted-foreground"><Loader2 className="h-3.5 w-3.5 animate-spin" /> Buscando…</p>
                ) : opciones.length === 0 ? (
                  <p className="p-3 text-xs text-muted-foreground">{fallidas.length ? "No se pudo buscar." : "Sin resultados."}</p>
                ) : (
                  opciones.map((op) => {
                    const Icono = ICONO[op.tipo]
                    return (
                      <button
                        key={`${op.tipo}-${op.id}`}
                        type="button"
                        role="option"
                        aria-selected={false}
                        onClick={() => setElegida(op)}
                        className="flex w-full items-start gap-2 border-b px-3 py-2 text-left text-sm last:border-0 hover:bg-muted"
                      >
                        <Icono className="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" />
                        <span className="min-w-0">
                          <span className="block truncate">
                            {op.nombre} <span className="text-xs text-muted-foreground">({ETIQUETA[op.tipo]})</span>
                          </span>
                          {op.subtitulo && <span className="block text-xs text-muted-foreground">Pertenece a: {op.subtitulo}</span>}
                        </span>
                      </button>
                    )
                  })
                )}
              </div>
              {fallidas.length > 0 && busqueda.trim().length >= 2 && (
                <p role="alert" className="text-xs text-red-600">
                  No respondió la búsqueda de {fallidas.join(" y ")}. Intenta de nuevo o revisa el servidor.
                </p>
              )}
            </div>
          )}

          {pendientes.length > 0 && (
            <div className="grid gap-2 rounded-md border bg-muted/20 p-3">
              <p className="text-sm font-medium">Leads y cotizaciones a nombre de {contacto?.nombre}</p>
              <p className="text-xs text-muted-foreground">
                Los marcados quedan a nombre de {elegida ? elegida.nombre : "la organización"}. Las cerradas se mantienen a nombre de la persona.
              </p>
              <div className="grid gap-1.5">
                {[
                  ...(registros?.oportunidades ?? []).map((o) => ({ ...o, clave: `op-${o.id}` })),
                  ...(registros?.cotizaciones ?? []).map((c) => ({ ...c, clave: `ct-${c.id}` })),
                ].map((r) => (
                  <label
                    key={r.clave}
                    className={cn("flex items-center gap-2 text-sm", r.abierta ? "cursor-pointer" : "cursor-not-allowed text-muted-foreground")}
                  >
                    <Checkbox
                      checked={r.abierta && marcados.has(r.clave)}
                      disabled={!r.abierta || guardando}
                      onCheckedChange={() => alternar(r.clave)}
                    />
                    <span className="font-medium">{r.codigo}</span>
                    <span className="text-xs text-muted-foreground">{r.estado ?? "Sin estado"}{r.abierta ? "" : " · cerrada"}</span>
                  </label>
                ))}
              </div>
            </div>
          )}

          {error && <div role="alert" className="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">{error}</div>}
        </div>

        <DialogFooter className="gap-2">
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={guardando}>Cancelar</Button>
          <Button onClick={asignar} disabled={!elegida || guardando}>
            {guardando ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
            {guardando ? "Asignando…" : "Asignar"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
