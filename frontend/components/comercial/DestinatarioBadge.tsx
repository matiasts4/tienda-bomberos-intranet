import { Building2, Flame, User } from "lucide-react"

import { cn } from "@/lib/utils"
import { DESTINATARIO_LABEL, type TipoDestinatario } from "@/lib/destinatario"

const ESTILO: Record<TipoDestinatario, { clase: string; Icono: typeof User }> = {
  bomberos: { clase: "border-red-200 bg-red-50 text-red-700", Icono: Flame },
  empresa: { clase: "border-emerald-200 bg-emerald-50 text-emerald-700", Icono: Building2 },
  persona_natural: { clase: "border-blue-200 bg-blue-50 text-blue-700", Icono: User },
}

/** Insignia que indica a quién va dirigida la tarjeta: Bomberos, Empresa o Persona natural. */
export function DestinatarioBadge({
  tipo,
  className,
  soloIcono = false,
}: {
  tipo: TipoDestinatario
  className?: string
  soloIcono?: boolean
}) {
  const { clase, Icono } = ESTILO[tipo]
  const label = DESTINATARIO_LABEL[tipo]

  return (
    <span
      title={`Dirigida a: ${label}`}
      className={cn(
        "inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full border px-1.5 py-0.5 text-[10px] font-medium leading-none",
        clase,
        className
      )}
    >
      <Icono className="h-3 w-3" aria-hidden />
      {soloIcono ? <span className="sr-only">{label}</span> : label}
    </span>
  )
}
