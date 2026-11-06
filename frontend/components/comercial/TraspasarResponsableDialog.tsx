"use client"

import { useEffect, useMemo, useState } from "react"
import { ArrowRight, Loader2, UserCog } from "lucide-react"
import { toast } from "sonner"

import { api } from "@/lib/api"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import { nombreUsuario, type TipoTarjeta, type UsuarioRef } from "@/lib/responsables"

type Ejecutivo = { id: number; nombre: string; email?: string | null; role?: string | null }

const MOTIVO_MAX = 255

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  tipo: TipoTarjeta
  tarjetaId: number
  /** Código visible de la tarjeta, ej. "OP-0012" o "CT-0042". */
  codigo: string
  responsableActual?: UsuarioRef | null
  /** Recibe la tarjeta actualizada que devuelve el backend. */
  onTraspasado?: (tarjeta: any) => void
}

export function TraspasarResponsableDialog({
  open,
  onOpenChange,
  tipo,
  tarjetaId,
  codigo,
  responsableActual,
  onTraspasado,
}: Props) {
  const [ejecutivos, setEjecutivos] = useState<Ejecutivo[]>([])
  const [loadingEjecutivos, setLoadingEjecutivos] = useState(false)
  const [destinoId, setDestinoId] = useState("")
  const [motivo, setMotivo] = useState("")
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!open) return
    setDestinoId("")
    setMotivo("")
    setError(null)

    let cancelled = false
    setLoadingEjecutivos(true)
    api
      .get<Ejecutivo[]>("/api/ejecutivos")
      .then((list) => {
        if (!cancelled) setEjecutivos(Array.isArray(list) ? list : [])
      })
      .catch(() => {
        if (!cancelled) setError("No se pudo cargar la lista de ejecutivos.")
      })
      .finally(() => {
        if (!cancelled) setLoadingEjecutivos(false)
      })
    return () => {
      cancelled = true
    }
  }, [open])

  // El responsable actual no es un destino válido.
  const destinos = useMemo(
    () => ejecutivos.filter((e) => e.id !== responsableActual?.id),
    [ejecutivos, responsableActual?.id]
  )
  const destino = destinos.find((e) => String(e.id) === destinoId)

  async function confirmar() {
    if (!destino) return
    setSaving(true)
    setError(null)
    try {
      const actualizada = await api.post<any>(`/api/${tipo}/${tarjetaId}/traspasar`, {
        user_id: destino.id,
        motivo: motivo.trim() || null,
      })
      toast.success(`${codigo} ahora la gestiona ${destino.nombre}`)
      onTraspasado?.(actualizada)
      onOpenChange(false)
    } catch (e: any) {
      const errores = e?.data?.errors ? (Object.values(e.data.errors).flat() as string[]) : []
      setError(errores[0] || e?.data?.message || e?.message || "No se pudo traspasar.")
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={(v) => !saving && onOpenChange(v)}>
      <DialogContent className="sm:max-w-[460px]" onClick={(e) => e.stopPropagation()}>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <UserCog className="h-5 w-5 text-primary" />
            Traspasar {tipo === "oportunidades" ? "lead" : "cotización"} {codigo}
          </DialogTitle>
          <DialogDescription>
            El nuevo ejecutivo pasa a ser el responsable. Quien la creó se mantiene y el cambio queda en el historial.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-1">
          <div className="space-y-1.5">
            <Label className="text-xs text-muted-foreground">Responsable actual</Label>
            <div className="rounded-md border bg-muted/40 px-3 py-2 text-sm font-medium">
              {nombreUsuario(responsableActual)}
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="traspaso-destino">Nuevo responsable</Label>
            <Select value={destinoId} onValueChange={setDestinoId} disabled={loadingEjecutivos || saving}>
              <SelectTrigger id="traspaso-destino">
                <SelectValue placeholder={loadingEjecutivos ? "Cargando ejecutivos..." : "Selecciona un ejecutivo"} />
              </SelectTrigger>
              <SelectContent>
                {destinos.map((e) => (
                  <SelectItem key={e.id} value={String(e.id)}>
                    {e.nombre}
                  </SelectItem>
                ))}
                {!loadingEjecutivos && destinos.length === 0 && (
                  <div className="px-3 py-2 text-sm text-muted-foreground">No hay otros ejecutivos disponibles.</div>
                )}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1.5">
            <div className="flex items-center justify-between">
              <Label htmlFor="traspaso-motivo">
                Motivo <span className="font-normal text-muted-foreground">(opcional)</span>
              </Label>
              <span className="text-[11px] text-muted-foreground">
                {motivo.length}/{MOTIVO_MAX}
              </span>
            </div>
            <Textarea
              id="traspaso-motivo"
              rows={3}
              maxLength={MOTIVO_MAX}
              placeholder="Ej: vacaciones, cambio de cartera, cliente de otra zona..."
              value={motivo}
              onChange={(e) => setMotivo(e.target.value)}
              disabled={saving}
            />
          </div>

          {destino && (
            <div className="flex items-center gap-2 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-sm text-blue-900">
              <span className="truncate">{nombreUsuario(responsableActual)}</span>
              <ArrowRight className="h-4 w-4 shrink-0" />
              <span className="truncate font-semibold">{destino.nombre}</span>
            </div>
          )}

          {error && (
            <div role="alert" className="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">
              {error}
            </div>
          )}
        </div>

        <DialogFooter className="gap-2 sm:gap-0">
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)} disabled={saving}>
            Cancelar
          </Button>
          <Button type="button" onClick={confirmar} disabled={!destino || saving}>
            {saving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            Traspasar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
