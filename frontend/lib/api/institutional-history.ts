// Historial institucional consolidado de compras y órdenes por Cuerpo o Compañía.
import { api } from "@/lib/api"

export type InstitutionKind = "cuerpo" | "compania"

export type HistoryEventType = "approved_quote" | "workshop_order" | "delivered_order"

export interface InstitutionalHistoryEvent {
  type: HistoryEventType
  id: number
  reference: string
  title: string | null
  date: string | null
  timestamp: string | null
  status: string | null
  garments: string[]
  total_quantity: number
  amount: number | null
  cotizacion_id: number | null
}

export interface InstitutionalHistorySummary {
  total_approved_quotes: number
  total_workshop_orders: number
  completed_workshop_orders: number
  delivered_orders: number
  total_amount: number
}

export interface InstitutionalHistoryFilters {
  year?: number
  status?: string
  garment_type?: string
}

export interface InstitutionalHistoryResponse {
  success: boolean
  summary: InstitutionalHistorySummary
  timeline: InstitutionalHistoryEvent[]
  company?: { id: string; nombre: string; numero?: string | number | null; cuerpo?: { id: string; nombre: string } | null }
  fire_department?: { id: string; nombre: string; rut?: string | null; companias_count?: number }
}

export const HISTORY_EVENT_LABELS: Record<HistoryEventType, string> = {
  approved_quote: "Cotización aprobada",
  workshop_order: "Orden de taller",
  delivered_order: "Pedido entregado",
}

/**
 * Obtiene el historial unificado (cotizaciones aprobadas, órdenes de taller y
 * despachos entregados) de un Cuerpo de Bomberos o de una Compañía.
 */
export function fetchInstitutionalHistory(
  kind: InstitutionKind,
  id: string,
  filters: InstitutionalHistoryFilters = {}
): Promise<InstitutionalHistoryResponse> {
  const params = new URLSearchParams()
  if (filters.year) params.set("year", String(filters.year))
  if (filters.status) params.set("status", filters.status)
  if (filters.garment_type) params.set("garment_type", filters.garment_type)
  const query = params.toString()
  const base = kind === "cuerpo" ? `/api/fire-departments/${id}/orders` : `/api/companias/${id}/orders`
  return api.get<InstitutionalHistoryResponse>(`${base}${query ? `?${query}` : ""}`)
}
