// Etapa "No Vigentes" del kanban de cotizaciones: reúne las que salieron del flujo.
//
// El estado dice dónde está la tarjeta ("No Vigente"); el motivo, por qué salió.
// Las Rechazadas y Anuladas de antes de esta etapa no se migraron: se muestran aquí
// con su motivo de entonces. Mismas claves y reglas que App\Support\NoVigente (backend);
// acá solo orientan al usuario, el backend es quien las hace cumplir.

export type MotivoNoVigente = "desistida" | "anulada" | "finalizada" | "finalizacion_adm" | "otro"

/** Motivo que muestra una tarjeta: los nuevos más el de las Rechazadas antiguas. */
export type MotivoTarjeta = MotivoNoVigente | "rechazada"

export const ESTADO_NO_VIGENTE = "No Vigente"
export const ESTADOS_ANTERIORES = ["Rechazado", "Rechazada", "Anulado"]

export const MAX_DETALLE_OTRO = 120
export const MAX_OBSERVACION = 2000

export type OpcionMotivo = {
  value: MotivoNoVigente
  label: string
  descripcion: string
  soloSuperadmin?: boolean
  soloDesdeVenta?: boolean
}

export const MOTIVOS_NO_VIGENTE: OpcionMotivo[] = [
  { value: "desistida", label: "Desistida", descripcion: "El cliente decidió no seguir con la compra." },
  { value: "anulada", label: "Anulada", descripcion: "Se anuló: error, duplicada o venta cancelada." },
  { value: "finalizada", label: "Finalizada", descripcion: "La venta terminó correctamente.", soloDesdeVenta: true },
  { value: "finalizacion_adm", label: "Finalización Adm.", descripcion: "Cierre administrativo.", soloSuperadmin: true },
  { value: "otro", label: "Otro", descripcion: "Otro motivo: descríbelo brevemente." },
]

export const MOTIVO_LABEL: Record<MotivoTarjeta, string> = {
  desistida: "Desistida",
  anulada: "Anulada",
  finalizada: "Finalizada",
  finalizacion_adm: "Finalización Adm.",
  otro: "Otro",
  rechazada: "Rechazada",
}

export const MOTIVO_BADGE_CLASS: Record<MotivoTarjeta, string> = {
  desistida: "bg-orange-100 text-orange-800",
  anulada: "bg-slate-200 text-slate-700",
  finalizada: "bg-emerald-100 text-emerald-800",
  finalizacion_adm: "bg-violet-100 text-violet-800",
  otro: "bg-amber-100 text-amber-800",
  rechazada: "bg-red-100 text-red-800",
}

export type TarjetaNoVigente = {
  motivo_no_vigente?: string | null
  motivo_no_vigente_otro?: string | null
  observacion_no_vigente?: string | null
  fecha_no_vigente?: string | null
  no_vigente_por?: { name?: string | null } | null
  motivo_rechazo?: string | null
  fecha_rechazo?: string | null
}

function norm(s?: string | null) {
  return (s || "").normalize("NFD").replace(/[̀-ͯ]/g, "").trim().toLowerCase()
}

/** ¿El estado es de salida del flujo (el nuevo o uno de los anteriores)? */
export function esEstadoNoVigente(nombreEstado?: string | null): boolean {
  const n = norm(nombreEstado)
  return n === norm(ESTADO_NO_VIGENTE) || ESTADOS_ANTERIORES.some((e) => norm(e) === n)
}

/**
 * Motivo que muestra la tarjeta, o null si sigue en el flujo.
 *  - "No Vigente": el motivo que se guardó al arrastrarla (uno desconocido se muestra como "otro").
 *  - "Anulado" (anterior): "anulada".  "Rechazado"/"Rechazada" (anterior): "rechazada".
 */
export function motivoDeTarjeta(c: TarjetaNoVigente, nombreEstado?: string | null): MotivoTarjeta | null {
  const estado = norm(nombreEstado)
  if (estado === norm(ESTADO_NO_VIGENTE)) {
    const conocido = MOTIVOS_NO_VIGENTE.find((m) => m.value === c.motivo_no_vigente)
    return conocido ? conocido.value : "otro"
  }
  if (estado === "anulado" || estado === "anulada") return "anulada"
  if (estado === "rechazado" || estado === "rechazada") return "rechazada"
  return null
}

export type DatosNoVigente = {
  motivo: MotivoTarjeta
  label: string
  detalle: string | null
  observacion: string | null
  fecha: string | null
  por: string | null
}

/** Todo lo que la UI muestra de una tarjeta No Vigente (con respaldo para las anteriores). */
export function datosNoVigente(c: TarjetaNoVigente, nombreEstado?: string | null): DatosNoVigente | null {
  const motivo = motivoDeTarjeta(c, nombreEstado)
  if (!motivo) return null
  // Las anteriores guardaban su texto y fecha en motivo_rechazo / fecha_rechazo.
  const anterior = norm(nombreEstado) !== norm(ESTADO_NO_VIGENTE)
  return {
    motivo,
    label: MOTIVO_LABEL[motivo],
    detalle: motivo === "otro" ? c.motivo_no_vigente_otro?.trim() || null : null,
    observacion: (anterior ? c.motivo_rechazo : c.observacion_no_vigente)?.trim() || null,
    fecha: (anterior ? c.fecha_rechazo : c.fecha_no_vigente) || null,
    por: anterior ? null : c.no_vigente_por?.name || null,
  }
}

/** "Otro · Cambio de proveedor" o solo la etiqueta. */
export function etiquetaMotivo(datos: Pick<DatosNoVigente, "label" | "detalle">): string {
  return datos.detalle ? `${datos.label} · ${datos.detalle}` : datos.label
}

export type MotivoDisponible = OpcionMotivo & { deshabilitado: boolean; razon: string | null }

/** Opciones del diálogo: todas visibles, con las que no aplican deshabilitadas y su razón. */
export function motivosDisponibles(ctx: { esSuperadmin: boolean; enVenta: boolean }): MotivoDisponible[] {
  return MOTIVOS_NO_VIGENTE.map((m) => {
    const razon = m.soloSuperadmin && !ctx.esSuperadmin
      ? "Solo superadmin"
      : m.soloDesdeVenta && !ctx.enVenta
        ? "Solo desde Ventas"
        : null
    return { ...m, deshabilitado: razon !== null, razon }
  })
}

export type FormNoVigente = { motivo: MotivoNoVigente | ""; detalleOtro: string; observacion: string }

/** Primer error del formulario, o null si se puede enviar. */
export function validarNoVigente(form: FormNoVigente): string | null {
  if (!form.motivo) return "Elige un motivo."
  if (form.motivo === "otro") {
    const detalle = form.detalleOtro.trim()
    if (!detalle) return "Describe brevemente el motivo."
    if (detalle.length > MAX_DETALLE_OTRO) return `La descripción admite hasta ${MAX_DETALLE_OTRO} caracteres.`
  }
  const observacion = form.observacion.trim()
  if (!observacion) return "Escribe una observación."
  if (observacion.length > MAX_OBSERVACION) return `La observación admite hasta ${MAX_OBSERVACION} caracteres.`
  return null
}

/** Cuerpo del PATCH /api/cotizaciones/{id}/estado al mover a No Vigente. */
export function payloadNoVigente(estadoId: number, form: FormNoVigente) {
  return {
    estado_id: estadoId,
    motivo_no_vigente: form.motivo,
    motivo_no_vigente_otro: form.motivo === "otro" ? form.detalleOtro.trim() : null,
    observacion_no_vigente: form.observacion.trim(),
  }
}

/** Una finalización administrativa solo la saca de No Vigentes un superadmin. */
export function puedeSalirDeNoVigentes(c: TarjetaNoVigente, nombreEstado: string | null | undefined, esSuperadmin: boolean): boolean {
  return esSuperadmin || motivoDeTarjeta(c, nombreEstado) !== "finalizacion_adm"
}
