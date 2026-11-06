export interface SkuMovement {
  id: number
  sku: string
  fecha_hora: string
  tipo_movimiento: string
  cantidad: number
  saldo_anterior: number
  saldo_nuevo: number
  cotizacion_id?: number | null
  cotizacion_numero?: string | null
  cotizacion_nombre?: string | null
  cotizacion_disponible?: boolean
  referencia_externa?: string | null
  referencia_inconsistente?: boolean
  cliente_mostrado?: string | null
  cliente_nombre?: string | null
  usuario_nombre?: string
  usuario?: { name?: string } | null
  rol_actor?: string
  solicitante?: string | null
  peticion_id?: number | null
  estado_solicitud_actual?: string | null
  resuelto_por?: string | null
  resuelto_en?: string | null
  resolucion_tipo?: string | null
  observacion?: string | null
  observacion_solicitud?: string | null
  id_lote?: string | null
}

const labels: Record<string, string> = {
  RESERVA_PRODUCTO: "Reserva", SALIDA_VENTA: "Venta / despacho", ANULACION_RESERVA: "Reserva rechazada",
  INGRESO_BODEGA: "Ingreso de bodega", ANULACION_INGRESO: "Ingreso anulado",
  AJUSTE_MANUAL: "Ajuste manual", INVENTARIO_INICIAL: "Inventario inicial",
}
export const movementLabel = (type: string) => labels[type] ?? type.replaceAll("_", " ")
export const availableDelta = (mov: SkuMovement) => Number(mov.saldo_nuevo) - Number(mov.saldo_anterior)
export const actorName = (mov: SkuMovement) => mov.usuario_nombre || mov.usuario?.name || "SISTEMA"
export const actorRole = (mov: SkuMovement) => mov.rol_actor || (mov.tipo_movimiento === "RESERVA_PRODUCTO" ? "Solicitó" : mov.tipo_movimiento === "ANULACION_RESERVA" ? "Rechazó" : mov.tipo_movimiento === "SALIDA_VENTA" && mov.peticion_id ? "Aprobó" : "Registró")
export function quoteText(mov: SkuMovement) {
  if (mov.cotizacion_id) return `${mov.cotizacion_numero || "Cotización interna"} · ID ${mov.cotizacion_id}`
  return mov.referencia_externa ? `Externa · N.º ${mov.referencia_externa}` : "Sin cotización"
}
export const historyDate = (value: string) => {
  // Los registros históricos sin zona contienen la hora local del sistema.
  const wallTime = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::\d{2}(?:\.\d+)?)?$/.exec(value)
  if (wallTime) return `${wallTime[3]}-${wallTime[2]}-${wallTime[1].slice(-2)} ${wallTime[4]}:${wallTime[5]}`
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString("es-CL", { timeZone: "America/Santiago", dateStyle: "short", timeStyle: "short", hourCycle: "h23" })
}
