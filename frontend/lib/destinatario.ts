// A quién va dirigida una cotización o un lead.
//
// El backend lo calcula (`tipo_destinatario`) a partir de a qué apunta la tarjeta;
// acá hay un respaldo equivalente por si el dato no llega (respuestas antiguas).
//   - bomberos         Cuerpo o Compañía
//   - empresa          cliente suelto de tipo "empresa"
//   - persona_natural  cliente suelto de tipo "particular", o solo un contacto

export type TipoDestinatario = "bomberos" | "empresa" | "persona_natural"

export const DESTINATARIO_LABEL: Record<TipoDestinatario, string> = {
  bomberos: "Bomberos",
  empresa: "Empresa",
  persona_natural: "Persona natural",
}

/** Color del N° (CT-/OP-) según a quién va dirigida: bomberos rojo, persona natural azul, empresa verde. */
export const DESTINATARIO_NUMERO_CLASS: Record<TipoDestinatario, string> = {
  bomberos: "text-red-600",
  persona_natural: "text-blue-600",
  empresa: "text-emerald-600",
}

export type TarjetaConDestinatario = {
  tipo_destinatario?: TipoDestinatario | string | null
  cliente_id?: string | number | null
  cliente?: { tipo?: string | null; [campo: string]: unknown } | null
  cuerpo_id?: string | null
  cuerpo?: unknown
  compania_id?: string | null
  compania?: unknown
  contacto_id?: number | null
  contacto?: unknown
}

export function tipoDestinatarioDe(t: TarjetaConDestinatario): TipoDestinatario {
  const dado = t.tipo_destinatario
  if (dado === "bomberos" || dado === "empresa" || dado === "persona_natural") return dado

  if (t.cuerpo_id || t.cuerpo || t.compania_id || t.compania) return "bomberos"
  if (t.cliente_id || t.cliente) {
    if (t.cliente?.tipo === "bomberos") return "bomberos"
    if (t.cliente?.tipo === "particular") return "persona_natural"
    return "empresa"
  }
  return t.contacto_id || t.contacto ? "persona_natural" : "empresa"
}

/** Nombre que identifica al destinatario según su tipo (persona, empresa o cuerpo). */
export function nombreDestinatario(
  t: TarjetaConDestinatario & {
    cuerpo?: { nombre?: string | null } | null
    compania?: { nombre?: string | null } | null
    cliente?: { nombre_empresa?: string | null; tipo?: string | null } | null
    contacto?: { nombre?: string | null } | null
  },
  fallback = "Sin cliente"
): string {
  return (
    t.cuerpo?.nombre || t.compania?.nombre || t.cliente?.nombre_empresa || t.contacto?.nombre || fallback
  )
}
