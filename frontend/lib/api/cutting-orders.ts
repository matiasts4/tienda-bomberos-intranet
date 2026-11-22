// API service for cutting orders
import { factoryBoardStatus } from '@/lib/factory-board-status';
import type { WorkshopStage } from '@/lib/workshop-stages';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

export interface CuttingOrderItem {
  sku?: string;
  id?: string;
  product_type: string;
  size: string;
  color: string;
  quantity: number;
  fabric_type: string;
  notes?: string;
}

export interface CuttingOrderSupply {
  id?: string;
  name: string;
  type: string;
  quantity: number;
  unit: string;
  notes?: string;
}

export interface CuttingOrder {
  id?: string;
  code: string;
  client?: string;
  selected_product: string;
  status: 'order-received' | 'cutting' | 'cutting-finished' | 'transit' | 'sewing' | 'quality-control' | 'finished' | WorkshopStage;
  notes?: string;
  estimated_days: number;
  progress: number;
  created_at?: string;
  updated_at?: string;
  items: CuttingOrderItem[];
  supplies: CuttingOrderSupply[];
  total_quantity?: number;
  cotizacion_id?: number | null;
  compania_id?: string | null;
  cuerpo_id?: string | null;
  responsable_id?: number | null;
  fecha_compromiso?: string | null;
  total_unidades?: number | null;
  unidades_listas?: number | null;
  cotizacion?: { id: number; numero?: string | null; nombre?: string | null; estado?: { nombre?: string } | null } | null;
  compania?: { id: string; nombre: string; numero?: string | number | null } | null;
  cuerpo?: { id: string; nombre: string } | null;
  responsable?: AuditUser | null;
  historial_etapas?: StageHistoryEntry[];
}

/** Usuario auditor que ejecutó una acción en taller. */
export interface AuditUser {
  id: number;
  name: string;
  email?: string;
}

/** Registro de cambio de etapa (fecha, hora y responsable). */
export interface StageHistoryEntry {
  id: number;
  etapa_anterior?: string | null;
  etapa_nueva?: string | null;
  observaciones?: string | null;
  user?: AuditUser | null;
  user_id?: number | null;
  created_at: string;
}

export interface FactoryLogEntry {
  id: number;
  action: string;
  description: string;
  details?: Record<string, unknown> | null;
  entity_type: string;
  entity_id: number;
  user?: AuditUser | null;
  created_at: string;
}

/** Trazabilidad completa de una orden: etapas y bitácora de fábrica. */
export interface CuttingOrderLogs {
  cutting_order_id: number;
  code: string;
  current_stage: string;
  status: string;
  stage_history: StageHistoryEntry[];
  factory_logs: FactoryLogEntry[];
}

export interface CuttingOrderFilters {
  stage?: WorkshopStage;
  search?: string;
  cotizacion_id?: number;
  compania_id?: string;
  cuerpo_id?: string;
}

export interface CreateCuttingOrderRequest {
  catalog_product_id?: number;
  client?: string;
  selected_product: string;
  notes?: string;
  estimated_days?: number;
  items: Omit<CuttingOrderItem, 'id'>[];
  supplies?: Omit<CuttingOrderSupply, 'id'>[];
}

export interface CuttingCatalogProduct {
  id: number; sku: string; name: string; gender: string | null;
  variants: { sku: string; size: string; color: string }[];
}

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data?: T;
  error?: string;
  errors?: Record<string, string[]>;
}

class CuttingOrdersAPI {
  private async request<T>(
    endpoint: string, 
    options: RequestInit = {}
  ): Promise<ApiResponse<T>> {
    try {
      // Función auxiliar para leer cookies (Nativa de JS)
      const getCookie = (name: string) => {
        if (typeof document === 'undefined') return '';
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return decodeURIComponent(parts.pop()?.split(';').shift() || '');
        return '';
      };

      // Obtenemos el token CSRF de las cookies
      const csrfToken = getCookie('XSRF-TOKEN');

      const headers: HeadersInit = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...options.headers,
      };

      // Inyectar token Bearer de localStorage para autenticación cross-domain
      if (typeof window !== 'undefined') {
        const token = localStorage.getItem('auth_token');
        if (token) {
          (headers as any)['Authorization'] = `Bearer ${token}`;
        }
      }

      // Si existe el token, lo inyectamos en la cabecera
      if (csrfToken) {
        (headers as any)['X-XSRF-TOKEN'] = csrfToken;
      }

      const response = await fetch(`${API_BASE_URL}/${endpoint}`, {
        headers,
        credentials: "include", // VITAL: Envía las cookies de sesión
        ...options,
      });

      const data = await response.json();
      
      if (!response.ok) {
        const details = data.errors ? Object.values(data.errors).flat().join(' ') : '';
        throw Object.assign(new Error(details || data.message || `HTTP error! status: ${response.status}`), { status: response.status });
      }

      return data;
    } catch (error) {
      console.error(`API Error (${endpoint}):`, error);
      throw error;
    }
  }

  async getCatalog(): Promise<CuttingCatalogProduct[]> {
    const response = await this.request<CuttingCatalogProduct[]>('api/cutting-orders/catalog');
    if (!response.success || !Array.isArray(response.data)) throw new Error('No se pudo cargar el catálogo de fabricación.');
    return response.data;
  }

  async getAllOrders(filters: CuttingOrderFilters = {}): Promise<CuttingOrder[]> {
    console.log('🔄 Fetching cutting orders from API...');
    try {
      const params = new URLSearchParams();
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && String(value).trim() !== '') params.set(key, String(value));
      });
      const query = params.toString();
      const data = await this.request<CuttingOrder[]>(`api/cutting-orders${query ? `?${query}` : ''}`, {
        method: 'GET',
      });
      console.log('📋 API Response:', data);
      
      if (data.success && data.data) {
        console.log('✅ Orders found:', data.data.length);
        return data.data.map(order => ({ ...order, status: factoryBoardStatus(order.status) as CuttingOrder['status'] }));
      } else {
        console.log('❌ No orders in response');
        throw new Error(data.message || 'No se pudo comprobar la lista de órdenes de corte.');
      }
    } catch (error) {
      console.error('❌ Error fetching orders:', error);
      throw error;
    }
  }

  async getOrder(id: string): Promise<CuttingOrder> {
    const response = await this.request<CuttingOrder>(`api/cutting-orders/${id}`);
    if (!response.data) {
      throw new Error('Order not found');
    }
    return { ...response.data, status: factoryBoardStatus(response.data.status) as CuttingOrder['status'] };
  }

  async createOrder(orderData: CreateCuttingOrderRequest): Promise<CuttingOrder> {
    console.log('🔄 Creating cutting order...');
    console.log('📋 Order data:', orderData);
    
    try {
      const data = await this.request<CuttingOrder>('api/cutting-orders', {
        method: 'POST',
        body: JSON.stringify(orderData),
      });

      console.log('📋 Create response data:', data);
      
      if (data.success && data.data) {
        console.log('✅ Order created successfully');
        return { ...data.data, status: factoryBoardStatus(data.data.status) as CuttingOrder['status'] };
      } else {
        throw new Error(data.message || 'Failed to create order');
      }
    } catch (error) {
      console.error('❌ Error creating order:', error);
      throw error;
    }
  }

  async updateOrder(
    id: string, 
    updates: Partial<Pick<CuttingOrder, 'status' | 'progress' | 'notes' | 'estimated_days'>>
  ): Promise<CuttingOrder> {
    console.log('🔄 Updating cutting order:', id);
    console.log('📋 Updates:', updates);
    
    try {
      const response = await this.request<CuttingOrder>(`api/cutting-orders/${id}`, {
        method: 'PUT',
        body: JSON.stringify(updates),
      });
      
      console.log('📋 Update response:', response);
      
      if (!response.data) {
        throw new Error(response.message || 'Failed to update order');
      }
      
      console.log('✅ Order updated successfully');
      return { ...response.data, status: factoryBoardStatus(response.data.status) as CuttingOrder['status'] };
    } catch (error) {
      console.error('❌ Error updating order:', error);
      throw error;
    }
  }

  /**
   * Avanza la orden a la siguiente etapa del taller. El backend valida la
   * transición y registra fecha, hora y usuario en el historial de etapas.
   */
  async updateStage(id: string | number, stage: WorkshopStage, observaciones?: string): Promise<CuttingOrder> {
    const response = await this.request<CuttingOrder>(`api/cutting-orders/${id}/stage`, {
      method: 'PATCH',
      body: JSON.stringify({ stage, ...(observaciones?.trim() ? { observaciones: observaciones.trim() } : {}) }),
    });
    if (!response.success || !response.data) {
      throw new Error(response.message || 'No se pudo actualizar la etapa de la orden.');
    }
    return response.data;
  }

  /** Historial cronológico de etapas y bitácora de fábrica de una orden. */
  async getLogs(id: string | number): Promise<CuttingOrderLogs> {
    const response = await this.request<CuttingOrderLogs>(`api/cutting-orders/${id}/logs`);
    if (!response.success || !response.data) {
      throw new Error(response.message || 'No se pudo cargar la trazabilidad de la orden.');
    }
    return response.data;
  }

  async deleteOrder(id: string): Promise<void> {
    await this.request(`api/cutting-orders/${id}`, {
      method: 'DELETE',
    });
  }

  async getNextCode(): Promise<string> {
    const response = await this.request<{ next_code: string }>('api/cutting-orders/next-code/generate');
    return response.data?.next_code || '1000';
  }
}

export const cuttingOrdersAPI = new CuttingOrdersAPI();
