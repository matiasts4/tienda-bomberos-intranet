// Vencimiento de cotizaciones: color de la tarjeta, orden del kanban y fecha
// propuesta al reagendar.
//
// Las fechas `date` de Laravel llegan como "2026-10-28T00:00:00.000000Z"
// (medianoche UTC). `new Date()` las pasa a hora local y en Chile (UTC-3) eso
// es el día anterior: una cotización reagendada para hoy se veía como "ayer" y
// seguía en rojo. Aquí esas fechas se leen como día de calendario.

const SOLO_FECHA = /^(\d{4})-(\d{2})-(\d{2})(?:T00:00:00(?:\.0+)?Z)?$/

/** Fecha sin hora (o medianoche UTC) → ese día local; con hora real → Date normal. */
export function leerFecha(valor?: string | null): Date | null {
  if (!valor) return null
  const m = SOLO_FECHA.exec(valor)
  const fecha = m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : new Date(valor)
  return Number.isNaN(fecha.getTime()) ? null : fecha
}

/** Días de calendario desde hoy; negativo = ya pasó. */
export function diasHasta(valor?: string | null, hoy = new Date()): number | null {
  const fecha = leerFecha(valor)
  if (!fecha) return null
  const a = Date.UTC(fecha.getFullYear(), fecha.getMonth(), fecha.getDate())
  const b = Date.UTC(hoy.getFullYear(), hoy.getMonth(), hoy.getDate())
  return Math.round((a - b) / 86400000)
}

export function formatoFechaDMY(valor?: string | null): string {
  return leerFecha(valor)?.toLocaleDateString("es-CL") ?? "--"
}

/** AAAA-MM-DD del día local (toISOString daría el de UTC, que de noche ya es mañana). */
export function fechaISO(fecha = new Date()): string {
  return `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, "0")}-${String(fecha.getDate()).padStart(2, "0")}`
}

type ConGestion = {
  fecha_vencimiento?: string | null
  ultima_gestion?: { fecha_vencimiento_nueva?: string | null } | null
}

/** La fecha que manda: la del último reagendamiento, si hay. */
export function vencimientoDe(c: ConGestion): string | null {
  return c.ultima_gestion?.fecha_vencimiento_nueva || c.fecha_vencimiento || null
}

export type EstadoVencimiento = "vencida" | "por_vencer" | "gestionada" | "normal"

/** Rojo vencida, naranjo ≤3 días, amarillo reagendada, gris nueva. */
export function estadoVencimiento(c: ConGestion, hoy = new Date()): EstadoVencimiento {
  const dias = diasHasta(vencimientoDe(c), hoy)
  if (dias === null) return "normal"
  if (dias < 0) return "vencida"
  if (dias <= 3) return "por_vencer"
  if (c.ultima_gestion) return "gestionada"
  return "normal"
}

/** Fecha propuesta al reagendar: la vigente si aún no vence; si ya venció, hoy. */
export function fechaPropuestaReagendar(vencimientoActual?: string | null, hoy = new Date()): string {
  const dias = diasHasta(vencimientoActual, hoy)
  const actual = leerFecha(vencimientoActual)
  return actual && dias !== null && dias >= 0 ? fechaISO(actual) : fechaISO(hoy)
}
