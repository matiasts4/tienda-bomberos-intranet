"use client"

import { useState } from "react"
import { CircleUser, UserCog, UserPen } from "lucide-react"

import { useAuthContext } from "@/components/auth-provider"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import {
  creadorDistinto,
  nombreUsuario,
  puedeTraspasar,
  type TarjetaConResponsable,
  type TipoTarjeta,
} from "@/lib/responsables"
import { TraspasarResponsableDialog } from "./TraspasarResponsableDialog"

type Props = {
  tarjeta: TarjetaConResponsable
  tipo: TipoTarjeta
  tarjetaId: number
  codigo: string
  onTraspasado?: (tarjeta: any) => void
  className?: string
}

/**
 * Bloque "Creado por / Responsable" con el botón de traspaso.
 * Se usa en los detalles de leads y cotizaciones.
 */
export function ResponsablePanel({ tarjeta, tipo, tarjetaId, codigo, onTraspasado, className }: Props) {
  const { user } = useAuthContext()
  const [open, setOpen] = useState(false)
  const puede = puedeTraspasar(tarjeta, user)
  const traspasada = creadorDistinto(tarjeta)

  return (
    <div className={cn("rounded-lg border bg-slate-50/60 p-4", className)}>
      <div className="flex items-center justify-between gap-2">
        <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Ejecutivos</p>
        {puede && (
          <Button type="button" size="sm" variant="outline" className="h-7 gap-1.5 text-xs" onClick={() => setOpen(true)}>
            <UserCog className="h-3.5 w-3.5" />
            Traspasar
          </Button>
        )}
      </div>

      <dl className="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
        <div>
          <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
            <CircleUser className="h-3.5 w-3.5" /> Creado por
          </dt>
          <dd className="mt-0.5 font-medium text-slate-800">{nombreUsuario(tarjeta.creador, "Sin registro")}</dd>
        </div>
        <div>
          <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
            <UserPen className="h-3.5 w-3.5" /> Responsable / gestiona
          </dt>
          <dd className="mt-0.5 font-medium text-slate-800">{nombreUsuario(tarjeta.ejecutivo)}</dd>
        </div>
      </dl>

      {traspasada && (
        <p className="mt-3 text-[11px] text-muted-foreground">
          La gestiona un ejecutivo distinto a quien la creó. El detalle está en la pestaña Historial.
        </p>
      )}
      {!puede && user && (
        <p className="mt-3 text-[11px] text-muted-foreground">
          Solo el responsable o un administrador puede traspasarla.
        </p>
      )}

      <TraspasarResponsableDialog
        open={open}
        onOpenChange={setOpen}
        tipo={tipo}
        tarjetaId={tarjetaId}
        codigo={codigo}
        responsableActual={tarjeta.ejecutivo}
        onTraspasado={onTraspasado}
      />
    </div>
  )
}
