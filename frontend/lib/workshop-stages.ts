// Etapas operativas del taller (máquina de estados Corte → Confección → Empaque → Terminado).
// Los alias reflejan los valores históricos que la API puede devolver en `status`.

export type WorkshopStage = "corte" | "confeccion" | "empaque" | "terminado"

export const WORKSHOP_STAGES: WorkshopStage[] = ["corte", "confeccion", "empaque", "terminado"]

/** Etapas que el operario trabaja activamente en la tablet. */
export const ACTIVE_WORKSHOP_STAGES: WorkshopStage[] = ["corte", "confeccion", "empaque"]

export const STAGE_LABELS: Record<WorkshopStage, string> = {
  corte: "Corte",
  confeccion: "Confección",
  empaque: "Empaque",
  terminado: "Terminado",
}

export const STAGE_DESCRIPTIONS: Record<WorkshopStage, string> = {
  corte: "Tizado y corte de piezas",
  confeccion: "Costura, bordado y armado",
  empaque: "Control de calidad y embalaje",
  terminado: "Lista para despacho",
}

/** Siguiente etapa permitida; null cuando la orden ya está terminada. */
export const NEXT_STAGE: Record<WorkshopStage, WorkshopStage | null> = {
  corte: "confeccion",
  confeccion: "empaque",
  empaque: "terminado",
  terminado: null,
}

/** Texto del botón táctil que avanza la orden desde cada etapa. */
export const ADVANCE_LABELS: Record<WorkshopStage, string> = {
  corte: "Corte listo · Pasar a Confección",
  confeccion: "Confección lista · Pasar a Empaque",
  empaque: "Empaque listo · Marcar Terminado",
  terminado: "Orden terminada",
}

/** Clases de color por etapa (contraste alto para planta). */
export const STAGE_STYLES: Record<WorkshopStage, { accent: string; soft: string; button: string; dot: string }> = {
  corte: {
    accent: "border-amber-500",
    soft: "bg-amber-50 text-amber-900",
    button: "bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white",
    dot: "bg-amber-500",
  },
  confeccion: {
    accent: "border-blue-600",
    soft: "bg-blue-50 text-blue-900",
    button: "bg-blue-700 hover:bg-blue-800 active:bg-blue-900 text-white",
    dot: "bg-blue-600",
  },
  empaque: {
    accent: "border-violet-600",
    soft: "bg-violet-50 text-violet-900",
    button: "bg-violet-700 hover:bg-violet-800 active:bg-violet-900 text-white",
    dot: "bg-violet-600",
  },
  terminado: {
    accent: "border-emerald-600",
    soft: "bg-emerald-50 text-emerald-900",
    button: "bg-emerald-700 hover:bg-emerald-800 active:bg-emerald-900 text-white",
    dot: "bg-emerald-600",
  },
}

const STAGE_ALIASES: Record<WorkshopStage, string[]> = {
  corte: ["corte", "en corte", "en-corte", "cutting", "in-cutting", "order-received"],
  confeccion: ["confeccion", "confección", "en confeccion", "en confección", "en-confeccion", "sewing", "in-assembly", "cutting-finished"],
  empaque: ["empaque", "empaque y control de calidad", "packing", "quality-control", "in-quality-control", "control-calidad", "control de calidad", "pending-warehouse"],
  terminado: ["terminado", "finished", "completed", "delivered", "transit", "despacho"],
}

/** Normaliza cualquier `status` de orden de corte hacia su etapa de taller. */
export function workshopStageOf(status?: string | null): WorkshopStage | null {
  if (!status) return null
  const value = status.trim().toLowerCase().replace(/_/g, "-").replace(/\s+/g, " ")
  for (const stage of WORKSHOP_STAGES) {
    if (STAGE_ALIASES[stage].includes(value)) return stage
  }
  return null
}

/** Indica si la orden aún no inicia formalmente el corte (registro recibido sin transición). */
export function isPendingStart(status?: string | null): boolean {
  return (status ?? "").trim().toLowerCase() === "order-received"
}
