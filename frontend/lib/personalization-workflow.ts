import { api } from "@/lib/api"

export function workflowErrorMessage(error: unknown, fallback: string): string {
  const failure = error as { status?: number; data?: { message?: string }; message?: string } | null
  if ((failure?.status ?? 0) >= 500) return `${fallback} No se guardó la operación. Intenta nuevamente.`
  return failure?.data?.message || failure?.message || fallback
}

export async function loadPersonalizationItem(item: { id: number; [key: string]: any }) {
  const full = await api.get<any>(`/api/personalizaciones/${item.id}`)
  if (!full || full.id !== item.id || !("configuracion" in full)) {
    throw new Error("No se pudo cargar el detalle completo. Recarga el trabajo antes de editarlo.")
  }
  // Use fresh quantities/state too, never the stale slim card for mutations or PDFs.
  return { ...item, ...full }
}
