// Dashboard "Red de Bomberos": tipos del endpoint y cálculos de participación.
//
// Participación = cobertura: Cuerpos (y Compañías) con al menos una venta en el
// período sobre el total de la red. Venta = cotización con fecha_venta y
// comprobante de pago que no terminó Desistida/Anulada (lo decide el backend).

export type CuerpoRed = {
  id: string
  nombre: string
  region_id: number
  comuna: string | null
  ejecutivo_id: number | null
  companias: number
  companias_con_venta: number
  ventas: number
  monto: number
  ultima_venta: string | null
}

export type EjecutivoRed = { id: number; name: string; codigo_vendedor: string | null; color_cartera?: string | null }

export type ReporteRed = { cuerpos: CuerpoRed[]; ejecutivos: EjecutivoRed[] }

export type Cobertura = {
  cuerpos: number
  cuerposConVenta: number
  companias: number
  companiasConVenta: number
  ventas: number
  monto: number
}

export function cobertura(cuerpos: CuerpoRed[]): Cobertura {
  return cuerpos.reduce<Cobertura>((acc, c) => ({
    cuerpos: acc.cuerpos + 1,
    cuerposConVenta: acc.cuerposConVenta + (c.ventas > 0 ? 1 : 0),
    companias: acc.companias + c.companias,
    companiasConVenta: acc.companiasConVenta + c.companias_con_venta,
    ventas: acc.ventas + c.ventas,
    monto: acc.monto + c.monto,
  }), { cuerpos: 0, cuerposConVenta: 0, companias: 0, companiasConVenta: 0, ventas: 0, monto: 0 })
}

/** Porcentaje entero; 0 si no hay base. */
export function porcentaje(parte: number, total: number): number {
  return total > 0 ? Math.round((parte / total) * 100) : 0
}

/**
 * Paleta que el superadmin elige en Administración, por familias para que un
 * grupo de ejecutivos pueda quedar con colores afines. Sin grises: se confunden
 * con "sin cartera".
 */
export const PALETA_CARTERA = [
  { color: "#1e3a8a", nombre: "Azul marino" },
  { color: "#2563eb", nombre: "Azul" },
  { color: "#0ea5e9", nombre: "Celeste" },
  { color: "#06b6d4", nombre: "Cian" },
  { color: "#4338ca", nombre: "Índigo" },
  { color: "#7c3aed", nombre: "Lila" },
  { color: "#c026d3", nombre: "Fucsia" },
  { color: "#db2777", nombre: "Frambuesa" },
  { color: "#dc2626", nombre: "Rojo" },
  { color: "#ea580c", nombre: "Naranjo" },
  { color: "#d97706", nombre: "Ámbar" },
  { color: "#eab308", nombre: "Amarillo" },
  { color: "#65a30d", nombre: "Lima" },
  { color: "#15803d", nombre: "Verde" },
  { color: "#0d9488", nombre: "Verde azulado" },
  { color: "#78350f", nombre: "Café" },
]
export const COLOR_SIN_CARTERA = "#94a3b8"

/** El color elegido o, si no tiene, el primero de la paleta que nadie más usa. */
export function colorCartera(ejecutivoId: number | null, ejecutivos: EjecutivoRed[]): string {
  const ejecutivo = ejecutivoId ? ejecutivos.find(e => e.id === ejecutivoId) : null
  if (!ejecutivo) return COLOR_SIN_CARTERA
  if (ejecutivo.color_cartera) return ejecutivo.color_cartera

  const elegidos = new Set(ejecutivos.map(e => e.color_cartera))
  const libres = PALETA_CARTERA.filter(p => !elegidos.has(p.color))
  const posicion = ejecutivos.filter(e => !e.color_cartera).indexOf(ejecutivo)
  return libres.length ? libres[posicion % libres.length].color : COLOR_SIN_CARTERA
}

export function etiquetaEjecutivo(e: EjecutivoRed): string {
  return e.codigo_vendedor ? `${e.codigo_vendedor} · ${e.name}` : e.name
}

/** Rango de fechas de cada período (null = sin límite). */
export type Periodo = "todo" | "12m" | "anio" | "mes" | "semana"

export type Rango = { desde: string | null; hasta: string | null }

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`

const diasDelMes = (mes: string) => {
  const [a, m] = mes.split("-").map(Number)
  return new Date(a, m, 0).getDate()
}

/**
 * Semanas del mes por día de calendario: 1–7, 8–14, 15–21, 22–28 y 29–fin.
 * No se usan semanas lunes–domingo para que cada semana caiga entera en su mes.
 */
export function semanasDelMes(mes: string): { semana: number; desde: number; hasta: number; label: string }[] {
  const fin = diasDelMes(mes)
  const semanas = []
  for (let s = 1, dia = 1; dia <= fin; s++, dia += 7) {
    const hasta = s === 5 ? fin : Math.min(dia + 6, fin)
    semanas.push({ semana: s, desde: dia, hasta, label: `Semana ${s} (${dia}–${hasta})` })
    if (s === 5) break
  }
  return semanas
}

/** Últimos `n` meses, del actual hacia atrás, como "AAAA-MM". */
export function mesesRecientes(n: number, hoy = new Date()): { value: string; label: string }[] {
  return Array.from({ length: n }, (_, i) => {
    const d = new Date(hoy.getFullYear(), hoy.getMonth() - i, 1)
    const label = d.toLocaleDateString("es-CL", { month: "long", year: "numeric" })
    return { value: iso(d).slice(0, 7), label: label.charAt(0).toUpperCase() + label.slice(1) }
  })
}

/** `mes` ("AAAA-MM") se usa en "mes" y "semana"; `semana` (1..5) solo en "semana". */
export function rangoPeriodo(periodo: Periodo, mes: string, semana: number, hoy = new Date()): Rango {
  if (periodo === "todo") return { desde: null, hasta: null }
  if (periodo === "anio") return { desde: iso(new Date(hoy.getFullYear(), 0, 1)), hasta: null }
  if (periodo === "12m") return { desde: iso(new Date(hoy.getFullYear() - 1, hoy.getMonth(), hoy.getDate())), hasta: null }
  if (periodo === "mes") return { desde: `${mes}-01`, hasta: `${mes}-${String(diasDelMes(mes)).padStart(2, "0")}` }
  const s = semanasDelMes(mes).find(x => x.semana === semana) ?? semanasDelMes(mes)[0]
  return { desde: `${mes}-${String(s.desde).padStart(2, "0")}`, hasta: `${mes}-${String(s.hasta).padStart(2, "0")}` }
}

export function desdePeriodo(periodo: Periodo, hoy = new Date()): string | null {
  return rangoPeriodo(periodo, iso(hoy).slice(0, 7), 1, hoy).desde
}
