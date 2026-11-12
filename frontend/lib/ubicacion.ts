// Región, provincia y comuna se eligen en selects distintos y el orden en que el
// usuario los toca no está garantizado. Estas reglas evitan que un cambio borre
// en silencio lo que ya se había elegido (p. ej. elegir la comuna Yungay y luego
// la región Ñuble dejaba la comuna vacía y el cliente se guardaba sin comuna).

export type ComunaRef = { id: number; region_id: number; provincia_id: number | null }

export type Ubicacion = {
  region_id?: number | null
  provincia_id?: number | null
  comuna_id?: number | null
}

/**
 * Cambio de región: la comuna (y su provincia) se conservan si pertenecen a la
 * región nueva; si no, se limpian porque quedarían incoherentes.
 */
export function cambiarRegion(actual: Ubicacion, regionId: number | null, comunas: ComunaRef[]): Ubicacion {
  const comuna = actual.comuna_id ? comunas.find((c) => c.id === actual.comuna_id) : undefined
  const comunaSigueValida = !!comuna && (regionId == null || comuna.region_id === regionId)

  if (comunaSigueValida) {
    return { region_id: regionId ?? comuna!.region_id, provincia_id: comuna!.provincia_id ?? actual.provincia_id ?? null, comuna_id: comuna!.id }
  }
  return { region_id: regionId, provincia_id: null, comuna_id: null }
}

/** Cambio de provincia: se limpia la comuna solo si pertenece a otra provincia. */
export function cambiarProvincia(actual: Ubicacion, provinciaId: number | null, comunas: ComunaRef[]): Ubicacion {
  const comuna = actual.comuna_id ? comunas.find((c) => c.id === actual.comuna_id) : undefined
  const choca = !!comuna && provinciaId != null && comuna.provincia_id != null && comuna.provincia_id !== provinciaId
  return { region_id: actual.region_id ?? null, provincia_id: provinciaId, comuna_id: choca ? null : actual.comuna_id ?? null }
}

/** Elegir una comuna completa su región y provincia. */
export function cambiarComuna(actual: Ubicacion, comunaId: number | null, comunas: ComunaRef[]): Ubicacion {
  if (comunaId == null) return { ...actual, comuna_id: null }
  const comuna = comunas.find((c) => c.id === comunaId)
  if (!comuna) return { ...actual, comuna_id: comunaId }
  return { region_id: comuna.region_id, provincia_id: comuna.provincia_id ?? null, comuna_id: comuna.id }
}
