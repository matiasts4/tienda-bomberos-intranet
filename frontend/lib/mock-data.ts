// Mock data for Tienda Bomberos application

export type OrderStatus =
  | "lead"
  | "quote-sent"
  | "negotiating"
  | "approved"
  | "rejected"
  | "pending-receipt"
  | "in-reception"
  | "available"
  | "committed"
  | "out-of-stock"
  | "order-received"
  | "design"
  | "cutting"
  | "cutting-finished"
  | "transit"
  | "pending-warehouse"
  | "in-warehouse"
  | "sewing"
  | "quality-control"
  | "finished"
  | "pending-art"
  | "art-sent"
  | "art-approved"
  | "in-personalization"
  | "personalization-finished"

export type Priority = "high" | "medium" | "low"

// Detalle de producto individual con especificaciones
export interface ProductDetail {
  id: string
  productType: string // Tipo de producto (Chaqueta, Pantalón, etc.)
  size: string
  color: string
  quantity: number
  notes?: string
}

// Entrada de bitácora para órdenes de corte
export interface CuttingOrderLogEntry {
  id: string
  orderId: string
  orderCode?: string
  timestamp: string
  user: string
  action: string // "created", "status_changed", "updated", "material_added", etc.
  previousStatus?: OrderStatus
  newStatus?: OrderStatus
  details: string
  metadata?: Record<string, any> // Para datos adicionales
}

// Resumen de bitácora por orden
export interface CuttingOrderLogSummary {
  orderId: string
  orderCode: string
  client: string
  totalEntries: number
  dateRange: {
    start: string
    end: string
  }
  statusHistory: {
    status: OrderStatus
    timestamp: string
    duration?: string // Tiempo en este estado
  }[]
  entries: CuttingOrderLogEntry[]
}

export interface Order {
  id: string
  code?: string
  client: string
  products?: string[] // Para compatibilidad con órdenes existentes
  product?: string // Para compatibilidad con órdenes existentes
  fabricType?: string // Para compatibilidad con órdenes existentes
  color?: string // Para compatibilidad con órdenes existentes
  size?: string // Para compatibilidad con órdenes existentes
  quantity?: number // Para compatibilidad con órdenes existentes

  // Nueva estructura detallada
  items?: any[] // Raw items from API
  productDetails?: ProductDetail[] // Detalles específicos de cada producto
  supplies?: any[] // Insumos requeridos (any[] por compatibilidad)

  amount?: number
  status: OrderStatus
  priority?: Priority
  deadline?: string
  assignee?: string
  notes?: string
  createdAt: string
  updatedAt?: string
  estimatedDays?: number
  progress?: number

  // Seguimiento de QC Parcial (CuttingOrders)
  total_unidades?: number
  unidades_en_taller?: number
  unidades_en_reparacion?: number
  unidades_listas?: number

  workshop?: string // Taller asignado para confección
  workshopNotes?: string // Notas del taller

  // Campos relacionados con paquetes
  packageId?: string // ID del paquete al que pertenece
  packageCode?: string // Código del paquete para referencia rápida
  isPackage?: boolean // true si esta orden es en realidad un paquete
  packagedOrderIds?: string[] // IDs de órdenes contenidas si es un paquete
  packagedAt?: string // Fecha cuando fue empaquetada
  originOrderCode?: string // Código de la orden de corte original (para paquetes)
  
  // Campos de reparación
  is_repair_package?: boolean // true si es un paquete de reparación
  parent_package_id?: number // ID del paquete padre (para paquetes de reparación)
}

export interface Product {
  id: string
  code: string
  name: string
  category: string
  size?: string
  color?: string
  stockAvailable: number
  stockCommitted: number
  stockMinimum: number
  warehouse?: string
}

export interface User {
  id: string
  name: string
  email: string
  role: "superadmin" | "comercial" | "bodega" | "fabrica" | "personalizacion"
  status: "active" | "inactive"
}

// Mock products/inventory data
export const mockProducts: Product[] = [
  {
    id: "1",
    code: "CHQ-EST-001",
    name: "Chaqueta Estructural Pro",
    category: "Chaquetas",
    size: "L",
    color: "Negro/Amarillo",
    stockAvailable: 45,
    stockCommitted: 32,
    stockMinimum: 20,
    warehouse: "Bodega Central",
  },
  {
    id: "2",
    code: "PNT-EST-002",
    name: "Pantalón Estructural Pro",
    category: "Pantalones",
    size: "L",
    color: "Negro",
    stockAvailable: 38,
    stockCommitted: 40,
    stockMinimum: 20,
    warehouse: "Bodega Central",
  },
  {
    id: "3",
    code: "CSC-F1-003",
    name: "Casco F1 Certificado",
    category: "Cascos",
    color: "Amarillo",
    stockAvailable: 12,
    stockCommitted: 15,
    stockMinimum: 25,
    warehouse: "Bodega Norte",
  },
  {
    id: "4",
    code: "GNT-PRO-004",
    name: "Guantes Estructurales",
    category: "Guantes",
    size: "M",
    color: "Negro",
    stockAvailable: 85,
    stockCommitted: 30,
    stockMinimum: 40,
    warehouse: "Bodega Central",
  },
  {
    id: "5",
    code: "BTS-SEG-005",
    name: "Botas de Seguridad",
    category: "Calzado",
    size: "42",
    color: "Negro",
    stockAvailable: 5,
    stockCommitted: 40,
    stockMinimum: 30,
    warehouse: "Bodega Sur",
  },
  {
    id: "6",
    code: "UNF-OPR-006",
    name: "Uniforme Operativo",
    category: "Uniformes",
    size: "L",
    color: "Azul Marino",
    stockAvailable: 62,
    stockCommitted: 25,
    stockMinimum: 30,
    warehouse: "Bodega Central",
  },
  {
    id: "7",
    code: "SCBA-PRO-007",
    name: "Equipo SCBA Completo",
    category: "Equipos",
    stockAvailable: 8,
    stockCommitted: 8,
    stockMinimum: 10,
    warehouse: "Bodega Especial",
  },
  {
    id: "8",
    code: "CHQ-CRP-008",
    name: "Chaqueta Corporativa",
    category: "Ropa Corporativa",
    size: "M",
    color: "Rojo",
    stockAvailable: 95,
    stockCommitted: 30,
    stockMinimum: 40,
    warehouse: "Bodega Central",
  },
  {
    id: "9",
    code: "LNT-LED-009",
    name: "Linterna LED Profesional",
    category: "Accesorios",
    stockAvailable: 150,
    stockCommitted: 25,
    stockMinimum: 50,
    warehouse: "Bodega Norte",
  },
  {
    id: "10",
    code: "MSC-FLT-010",
    name: "Máscara Filtro HEPA",
    category: "Protección",
    stockAvailable: 3,
    stockCommitted: 0,
    stockMinimum: 50,
    warehouse: "Bodega Central",
  },
]

// Mock users data
export const mockUsers: User[] = [
  {
    id: "1",
    name: "Juan Pérez",
    email: "juan.perez@tiendabomberos.cl",
    role: "superadmin",
    status: "active",
  },
  {
    id: "2",
    name: "María González",
    email: "maria.gonzalez@tiendabomberos.cl",
    role: "comercial",
    status: "active",
  },
  {
    id: "3",
    name: "Carlos Ruiz",
    email: "carlos.ruiz@tiendabomberos.cl",
    role: "bodega",
    status: "active",
  },
  {
    id: "4",
    name: "Pedro Soto",
    email: "pedro.soto@tiendabomberos.cl",
    role: "fabrica",
    status: "active",
  },
  {
    id: "5",
    name: "Sofía Torres",
    email: "sofia.torres@tiendabomberos.cl",
    role: "personalizacion",
    status: "active",
  },
  {
    id: "6",
    name: "Ana Morales",
    email: "ana.morales@tiendabomberos.cl",
    role: "comercial",
    status: "inactive",
  },
]

export function getStockStatus(product: Product): "normal" | "low" | "critical" {
  const available = product.stockAvailable
  const minimum = product.stockMinimum

  if (available <= minimum * 0.3) return "critical"
  if (available <= minimum) return "low"
  return "normal"
}

export function getOrdersByStatus(status: OrderStatus): Order[] {
  return [] // mockOrders has been removed
}

export function getCriticalProducts(): Product[] {
  return mockProducts.filter((product) => getStockStatus(product) === "critical")
}

export function getLowStockProducts(): Product[] {
  return mockProducts.filter((product) => {
    const status = getStockStatus(product)
    return status === "low" || status === "critical"
  })
}

// Mock data para bitácora de órdenes de corte
export const mockCuttingOrderLogs: CuttingOrderLogEntry[] = [
  {
    id: "log-001",
    orderId: "prod-004",
    orderCode: "OP-2026-0004",
    timestamp: "2026-01-10T09:15:00Z",
    user: "Pedro Soto",
    action: "created",
    details: "Orden de producción creada con especificaciones completas",
    metadata: {
      productsCount: 4,
      materialsCount: 3,
      suppliesCount: 3
    }
  },
  {
    id: "log-002",
    orderId: "prod-004",
    orderCode: "OP-2026-0004",
    timestamp: "2026-01-10T14:30:00Z",
    user: "María González",
    action: "status_changed",
    previousStatus: "order-received",
    newStatus: "design",
    details: "Orden enviada a diseño para crear patrones",
    metadata: {
      estimatedDuration: "2 días"
    }
  },
  {
    id: "log-003",
    orderId: "prod-004",
    orderCode: "OP-2026-0004",
    timestamp: "2026-01-12T11:00:00Z",
    user: "Carlos Design",
    action: "status_changed",
    previousStatus: "design",
    newStatus: "cutting",
    details: "Patrones completados, enviado a corte",
    metadata: {
      patternsCreated: ["Chaqueta M", "Chaqueta L", "Pantalón M", "Pantalón L"]
    }
  },
  {
    id: "log-004",
    orderId: "prod-004",
    orderCode: "OP-2026-0004",
    timestamp: "2026-01-15T16:45:00Z",
    user: "Ana Cortadora",
    action: "material_consumed",
    details: "Consumo de material: Tela exterior Nomex IIIA",
    metadata: {
      materialId: "mat-001",
      quantityUsed: 15,
      remainingQuantity: 10
    }
  },
  {
    id: "log-005",
    orderId: "prod-002",
    orderCode: "OP-2026-0002",
    timestamp: "2026-01-05T10:00:00Z",
    user: "Pedro Soto",
    action: "created",
    details: "Orden urgente de chaqueta contraincendios creada",
  },
]

// Función para obtener la bitácora de una orden específica
export function getCuttingOrderLogs(orderId: string): CuttingOrderLogEntry[] {
  return mockCuttingOrderLogs.filter(log => log.orderId === orderId)
}

// Función para obtener resumen de bitácora
export function getCuttingOrderLogSummary(orderId: string): CuttingOrderLogSummary | null {
  const order = null // mockOrders has been removed
  if (!order) return null

  const logs = getCuttingOrderLogs(orderId)
  if (logs.length === 0) return null

  // Crear historial de estados
  const statusChanges = logs
    .filter(log => log.action === "status_changed" || log.action === "created")
    .sort((a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime())

  const statusHistory = statusChanges.map((log, index) => {
    const nextLog = statusChanges[index + 1]
    const currentTime = new Date(log.timestamp)
    const nextTime = nextLog ? new Date(nextLog.timestamp) : new Date()

    const durationMs = nextTime.getTime() - currentTime.getTime()
    const durationHours = Math.floor(durationMs / (1000 * 60 * 60))
    const durationDays = Math.floor(durationHours / 24)

    let duration = ""
    if (durationDays > 0) {
      duration = `${durationDays}d ${durationHours % 24}h`
    } else {
      duration = `${durationHours}h`
    }

    return {
      status: (log.newStatus || "unknown") as OrderStatus,
      timestamp: log.timestamp,
      duration: nextLog ? duration : undefined
    }
  })

  return {
    orderId,
    orderCode: "unknown",
    client: "unknown",
    totalEntries: logs.length,
    dateRange: {
      start: logs[logs.length - 1].timestamp,
      end: logs[0].timestamp
    },
    statusHistory,
    entries: logs.sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime())
  }
}
