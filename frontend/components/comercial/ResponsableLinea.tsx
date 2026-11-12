import { User } from "lucide-react"

import { cn } from "@/lib/utils"
import { creadorDistinto, nombreCorto, nombreUsuario, type TarjetaConResponsable } from "@/lib/responsables"

/**
 * Línea compacta para tarjetas de kanban: responsable actual y, si la tarjeta fue
 * traspasada (o creada para otro), una pista de quién la creó.
 */
export function ResponsableLinea({ tarjeta, className }: { tarjeta: TarjetaConResponsable; className?: string }) {
  if (!tarjeta.ejecutivo && !tarjeta.creador) return null

  const traspasada = creadorDistinto(tarjeta)
  const titulo = traspasada
    ? `Responsable: ${nombreUsuario(tarjeta.ejecutivo)} · Creada por ${nombreUsuario(tarjeta.creador)}`
    : `Responsable: ${nombreUsuario(tarjeta.ejecutivo)}`

  return (
    <div className={cn("flex min-w-0 items-center gap-1 text-xs text-slate-500", className)} title={titulo}>
      <User className="h-3 w-3 shrink-0" />
      <span className="truncate font-medium text-slate-600">{nombreCorto(tarjeta.ejecutivo)}</span>
      {traspasada && <span className="shrink-0 text-[10px] text-slate-400">· creó {nombreCorto(tarjeta.creador)}</span>}
    </div>
  )
}
