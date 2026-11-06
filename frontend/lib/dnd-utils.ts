import type { OrderStatus } from "@/lib/mock-data"

// Map drag source/target identifiers to statuses
export function getStatusFromId(id: string): OrderStatus | null {
  const statusMap: Record<string, OrderStatus> = {
    "col-lead": "lead",
    "col-quote-sent": "quote-sent",
    "col-negotiating": "negotiating",
    "col-approved": "approved",
    "col-rejected": "rejected",
    "col-pending-receipt": "pending-receipt",
    "col-in-reception": "in-reception",
    "col-available": "available",
    "col-committed": "committed",
    "col-out-of-stock": "out-of-stock",
    "col-order-received": "order-received",
    "col-design": "design",
    "col-cutting": "cutting",
    "col-cutting-finished": "cutting-finished",
    "col-transit": "transit",
    "col-sewing": "sewing",
    "col-quality-control": "quality-control",
    "col-finished": "finished",
    "col-pending-art": "pending-art",
    "col-art-sent": "art-sent",
    "col-art-approved": "art-approved",
    "col-in-personalization": "in-personalization",
    "col-personalization-finished": "personalization-finished",
  }
  return statusMap[id] || null
}
