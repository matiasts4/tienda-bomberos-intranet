// Asignar un contacto a un Cuerpo, Compañía o Empresa después de creado.
//
// Un contacto sin organización se cotiza como persona natural; al asignarlo, sus
// leads y cotizaciones abiertos pueden pasar a la organización (backend:
// App\Services\AsignacionContacto). Sin imports a propósito: los tests lo
// transpilan y lo cargan solo.

export type ContactoAsignable = {
  id: number
  nombre: string
  cuerpos?: { id: string; nombre: string }[] | null
  companias?: { id: string; nombre: string; cuerpo?: { id?: string; nombre: string } | null }[] | null
  cliente_id?: string | number | null
  cliente?: { id: string | number; nombre_empresa: string } | null
}

export type OrganizacionOpcion = {
  tipo: "cuerpo" | "compania" | "empresa"
  id: string
  nombre: string
  /** Compañía: el Cuerpo al que pertenece. */
  subtitulo?: string | null
}

export type RegistroSinEntidad = {
  id: number
  codigo: string
  estado: string | null
  abierta: boolean
  total?: number
}

export type RegistrosSinEntidad = {
  oportunidades: RegistroSinEntidad[]
  cotizaciones: RegistroSinEntidad[]
}

export type ResultadoAsignacion = {
  contacto: ContactoAsignable & Record<string, unknown>
  organizacion: OrganizacionOpcion
  oportunidades_actualizadas: number[]
  cotizaciones_actualizadas: number[]
}

/** Sin Cuerpo, Compañía ni Empresa: se cotiza como persona natural. */
export function estaSinAsignar(contacto: ContactoAsignable): boolean {
  return !contacto.cuerpos?.length && !contacto.companias?.length && !contacto.cliente && !contacto.cliente_id
}

/**
 * Nombres de las organizaciones del contacto, de la más específica a la más
 * general. Un Cuerpo que ya aparece por una de sus Compañías no se repite.
 */
export function organizacionesDe(contacto: ContactoAsignable): string[] {
  const companias = contacto.companias ?? []
  const cuerposPorCompania = new Set(companias.map((c) => c.cuerpo?.nombre).filter(Boolean))
  return [
    ...companias.map((c) => (c.cuerpo?.nombre ? `${c.nombre} (${c.cuerpo.nombre})` : c.nombre)),
    ...(contacto.cuerpos ?? []).filter((c) => !cuerposPorCompania.has(c.nombre)).map((c) => `Cuerpo ${c.nombre}`),
    ...(contacto.cliente?.nombre_empresa ? [contacto.cliente.nombre_empresa] : []),
  ]
}

function plural(n: number, singular: string, varios: string): string {
  return `${n} ${n === 1 ? singular : varios}`
}

/** "Juan Pérez quedó en 1ª Compañía · pasaron 1 lead y 2 cotizaciones" */
export function resumenAsignacion(nombre: string, r: Pick<ResultadoAsignacion, "organizacion" | "oportunidades_actualizadas" | "cotizaciones_actualizadas">): string {
  const pasaron = [
    r.oportunidades_actualizadas.length ? plural(r.oportunidades_actualizadas.length, "lead", "leads") : null,
    r.cotizaciones_actualizadas.length ? plural(r.cotizaciones_actualizadas.length, "cotización", "cotizaciones") : null,
  ].filter(Boolean)
  return `${nombre} quedó en ${r.organizacion.nombre}` + (pasaron.length ? ` · pasaron ${pasaron.join(" y ")}` : "")
}
