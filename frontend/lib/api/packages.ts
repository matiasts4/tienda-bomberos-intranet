// API client for packages management
import { Order } from "@/lib/mock-data"

// Package status types
export type PackageStatus =
  | 'preparing'
  | 'cutting-finished'
  | 'transit'
  | 'delivered'
  | 'returned'
  | 'sewing'
  | 'quality-control'
  | 'finished'

// Package interface
export interface Package {
  id: string
  code: string
  destination: string
  items?: any[]
  transport_type: string
  transport?: string
  cutting_orders?: Order[]
  status: PackageStatus
  created_at?: string
  notes?: string
  // New fields
  workshop?: string
  workshop_notes?: string
  // Campos de Control de Calidad (QC)
  total_unidades?: number
  unidades_en_taller?: number
  unidades_en_reparacion?: number
  unidades_listas?: number
  // Campos de reparación
  parent_package_id?: number
  is_repair_package?: boolean
}

// Create package request
export interface CreatePackageRequest {
  destination: string
  transport: string
  notes?: string
  items: {
    id: string
    quantity: number
  }[]
}

// Update package request
export interface UpdatePackageRequest {
  destination?: string
  transport?: string
  status?: PackageStatus
  notes?: string
  shipped_date?: string
  delivered_date?: string
  tracking_number?: string
  // New fields
  workshop?: string
  workshop_notes?: string
}

// API response types
export interface PackageResponse {
  success: boolean
  data: Package
  message?: string
}

export interface PackagesListResponse {
  success: boolean
  data: Package[]
  message?: string
}

export interface NextCodeResponse {
  success: boolean
  code: string
  message?: string
}

// API base URL
const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8001'

// API client class
class PackagesAPI {
  private baseUrl: string

  constructor(baseUrl: string = `${API_BASE_URL}/api`) {
    this.baseUrl = baseUrl
  }

  // Get authorization headers
  private getHeaders(): HeadersInit {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };

    if (typeof window !== 'undefined') {
      const token = localStorage.getItem('auth_token');
      if (token) {
        headers['Authorization'] = `Bearer ${token}`;
      }
    }

    return headers;
  }

  // Handle API responses
  private async handleResponse<T>(response: Response): Promise<T> {
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.message || `HTTP error! status: ${response.status}`)
    }
    return response.json()
  }

  // Get all packages
  async getPackages(): Promise<Package[]> {
    const response = await fetch(`${this.baseUrl}/packages`, {
      method: 'GET',
      headers: this.getHeaders(),
      credentials: 'include', // Include cookies for CORS
    })

    // Backend returns array directly, not wrapped in response object
    return this.handleResponse<Package[]>(response)
  }

  // Get a single package by ID
  async getPackage(id: string): Promise<Package> {
    const response = await fetch(`${this.baseUrl}/packages/${id}`, {
      method: 'GET',
      headers: this.getHeaders(),
      credentials: 'include',
    })

    return this.handleResponse<Package>(response)
  }

  // Create a new package
  async createPackage(packageData: CreatePackageRequest): Promise<Package> {
    // Map frontend fields to backend fields
    const backendData = {
      destination: packageData.destination,
      transport_type: packageData.transport,
      notes: packageData.notes || '',
      items: packageData.items,
      priority: 'medium', // Default priority
      estimated_delivery_days: 3 // Default delivery days
    }

    const response = await fetch(`${this.baseUrl}/packages`, {
      method: 'POST',
      headers: this.getHeaders(),
      credentials: 'include',
      body: JSON.stringify(backendData),
    })

    const data = await this.handleResponse<{ message: string; package: Package }>(response)
    return data.package
  }

  // Update a package
  async updatePackage(id: string, updates: UpdatePackageRequest): Promise<Package> {
    const response = await fetch(`${this.baseUrl}/packages/${id}`, {
      method: 'PUT',
      headers: this.getHeaders(),
      credentials: 'include',
      body: JSON.stringify(updates),
    })

    const data = await this.handleResponse<PackageResponse>(response)
    return data.data
  }

  // Update package status
  async updatePackageStatus(id: string, status: PackageStatus): Promise<Package> {
    return this.updatePackage(id, { status })
  }

  // Register Quality Control (with optional waste)
  async registerQualityControl(id: string, wasteItems: { id: number; quantity: number }[], notes?: string): Promise<{ package: Package; waste_package?: Package }> {
    const response = await fetch(`${this.baseUrl}/packages/${id}/quality-control`, {
      method: 'POST',
      headers: this.getHeaders(),
      credentials: 'include',
      body: JSON.stringify({
        waste_items: wasteItems,
        notes
      }),
    })

    const data = await this.handleResponse<{ message: string; package: Package; waste_package?: Package }>(response)
    return {
      package: data.package,
      waste_package: data.waste_package
    }
  }

  // Delete a package
  async deletePackage(id: string): Promise<void> {
    const response = await fetch(`${this.baseUrl}/packages/${id}`, {
      method: 'DELETE',
      headers: this.getHeaders(),
      credentials: 'include',
    })

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.message || `HTTP error! status: ${response.status}`)
    }
  }

  // Get next package code
  async getNextCode(): Promise<NextCodeResponse> {
    try {
      console.log('🔄 Fetching next package code from:', `${this.baseUrl}/packages/next-code`)

      const response = await fetch(`${this.baseUrl}/packages/next-code`, {
        method: 'GET',
        headers: this.getHeaders(),
        credentials: 'include', // Include cookies for CORS
      })

      console.log('📡 Response status:', response.status, response.statusText)

      if (!response.ok) {
        console.error('❌ Response not OK:', response.status, response.statusText)
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      // Backend returns { code: "P-001" } directly
      const data = await this.handleResponse<{ code: string }>(response)
      console.log('✅ Package code received:', data.code)

      return { success: true, code: data.code }
    } catch (error) {
      console.error('❌ Error fetching next package code:', error)
      console.error('Error details:', {
        message: error instanceof Error ? error.message : 'Unknown error',
        stack: error instanceof Error ? error.stack : undefined
      })
      throw error
    }
  }

  // Get packages by status
  async getPackagesByStatus(status: PackageStatus): Promise<Package[]> {
    const packages = await this.getPackages()
    return packages.filter(pkg => pkg.status === status)
  }

  // Search packages by destination or transport
  async searchPackages(query: string): Promise<Package[]> {
    const packages = await this.getPackages()
    const lowerQuery = query.toLowerCase()

    return packages.filter(pkg =>
      pkg.destination.toLowerCase().includes(lowerQuery) ||
      pkg.transport_type.toLowerCase().includes(lowerQuery) ||
      pkg.code.toLowerCase().includes(lowerQuery)
    )
  }

  // Get packages for specific cutting orders
  async getPackagesForOrders(orderIds: string[]): Promise<Package[]> {
    const packages = await this.getPackages()
    return packages.filter(pkg =>
      pkg.cutting_orders?.some(order => orderIds.includes(order.id))
    )
  }

  // Add tracking number to package
  async addTrackingNumber(id: string, trackingNumber: string): Promise<Package> {
    return this.updatePackage(id, { tracking_number: trackingNumber })
  }

  // Mark package as shipped
  async markAsShipped(id: string, shippedDate: string = new Date().toISOString()): Promise<Package> {
    return this.updatePackage(id, {
      status: 'transit',
      shipped_date: shippedDate
    })
  }

  // Mark package as delivered
  async markAsDelivered(id: string, deliveredDate: string = new Date().toISOString()): Promise<Package> {
    return this.updatePackage(id, {
      status: 'delivered',
      delivered_date: deliveredDate
    })
  }
}

// Export singleton instance
export const packagesAPI = new PackagesAPI()

// Export utility functions
export const packageStatusLabels: Record<PackageStatus, string> = {
  preparing: 'Preparando',
  'cutting-finished': 'Corte Terminado',
  transit: 'En Tránsito',
  sewing: 'En Confección',
  'quality-control': 'En Control de Calidad',
  finished: 'Terminado',
  delivered: 'Entregado',
  returned: 'Devuelto',
}

export const getPackageStatusColor = (status: PackageStatus): string => {
  const colors: Record<PackageStatus, string> = {
    preparing: 'bg-yellow-100 text-yellow-800',
    'cutting-finished': 'bg-amber-100 text-amber-800',
    transit: 'bg-blue-100 text-blue-800',
    sewing: 'bg-purple-100 text-purple-800',
    'quality-control': 'bg-indigo-100 text-indigo-800',
    finished: 'bg-green-100 text-green-800',
    delivered: 'bg-emerald-100 text-emerald-800',
    returned: 'bg-red-100 text-red-800'
  }
  return colors[status] || 'bg-gray-100 text-gray-800'
}

export default packagesAPI