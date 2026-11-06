"use client"

import { useEffect, useRef, useState } from "react"
import { Link2, Loader2, Search, User } from "lucide-react"
import { api } from "@/lib/api"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { estaSinAsignar, organizacionesDe, type ContactoAsignable, type OrganizacionOpcion } from "@/lib/asignacion-contacto"

type ContactoFila = ContactoAsignable & { rut?: string | null; telefono?: string | null; email?: string | null }

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  organizacion: OrganizacionOpcion
  /** Contacto elegido: quien abre el diálogo lo asigna (con AsignarContactoDialog). */
  onElegir: (contacto: ContactoFila) => void
}

function lista<T>(res: any): T[] {
  return Array.isArray(res) ? res : (res?.data || [])
}

/**
 * Buscar un contacto que ya existe para dejarlo en esta organización, en vez de
 * crearlo de nuevo. Sin texto muestra los que están sin asignar.
 */
export function VincularContactoDialog({ open, onOpenChange, organizacion, onElegir }: Props) {
  const [busqueda, setBusqueda] = useState("")
  const [filas, setFilas] = useState<ContactoFila[]>([])
  const [cargando, setCargando] = useState(false)
  const secuencia = useRef(0)
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null)

  async function cargar(texto: string) {
    const mia = ++secuencia.current
    setCargando(true)
    try {
      const q = texto.trim()
      const params = q.length >= 2 ? `search=${encodeURIComponent(q)}` : "asignacion=sin_asignar"
      const res = await api.get<any>(`/api/contactos?${params}&per_page=20`)
      if (mia === secuencia.current) setFilas(lista<ContactoFila>(res))
    } catch {
      if (mia === secuencia.current) setFilas([])
    } finally {
      if (mia === secuencia.current) setCargando(false)
    }
  }

  useEffect(() => {
    if (!open) return
    setBusqueda("")
    cargar("")
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  function buscar(texto: string) {
    setBusqueda(texto)
    if (timer.current) clearTimeout(timer.current)
    timer.current = setTimeout(() => cargar(texto), 250)
  }

  const buscandoTexto = busqueda.trim().length >= 2

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[520px]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Link2 className="h-4 w-4 text-emerald-600" /> Vincular contacto a {organizacion.nombre}
          </DialogTitle>
          <DialogDescription>Elige a alguien que ya está registrado para no duplicarlo.</DialogDescription>
        </DialogHeader>

        <div className="relative">
          <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
          <Input
            autoFocus
            value={busqueda}
            onChange={(e) => buscar(e.target.value)}
            placeholder="Buscar por nombre, RUT o email…"
            className="pl-8"
            aria-label="Buscar contacto"
          />
        </div>

        <p className="text-xs font-medium text-muted-foreground">
          {buscandoTexto ? "Resultados" : "Contactos sin asignar"}
        </p>
        <div className="max-h-72 overflow-y-auto rounded-md border">
          {cargando && filas.length === 0 ? (
            <p className="flex items-center gap-2 p-3 text-xs text-muted-foreground"><Loader2 className="h-3.5 w-3.5 animate-spin" /> Cargando…</p>
          ) : filas.length === 0 ? (
            <p className="p-3 text-xs text-muted-foreground">
              {buscandoTexto ? "Sin resultados." : "No hay contactos sin asignar. Busca por nombre para vincular a otro."}
            </p>
          ) : (
            filas.map((c) => (
              <button
                key={c.id}
                type="button"
                onClick={() => onElegir(c)}
                className="flex w-full items-start gap-2 border-b px-3 py-2 text-left text-sm last:border-0 hover:bg-muted"
              >
                <User className="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" />
                <span className="min-w-0 flex-1">
                  <span className="block truncate font-medium">{c.nombre}</span>
                  <span className="block truncate text-xs text-muted-foreground">
                    {[c.rut, c.telefono, c.email].filter(Boolean).join(" · ") || "Sin datos de contacto"}
                  </span>
                </span>
                {estaSinAsignar(c) ? (
                  <Badge variant="outline" className="shrink-0 border-amber-200 bg-amber-50 text-[10px] text-amber-800">Sin asignar</Badge>
                ) : (
                  <span className="max-w-[45%] shrink-0 truncate text-right text-xs text-muted-foreground">{organizacionesDe(c).join(" · ")}</span>
                )}
              </button>
            ))
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
