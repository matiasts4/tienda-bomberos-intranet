
import useSWR from "swr"
import { api } from "@/lib/api"
import type { AuditUser } from "@/lib/api/cutting-orders"

export interface FactoryLog {
    id: number
    action: string
    description: string
    details: any
    entity_type: string
    entity_id: number
    user_id: number
    user?: AuditUser | null
    created_at: string
    updated_at: string
    exit_date?: string | null
}

export interface PaginatedResponse<T> {
    data: T[]
    current_page: number
    last_page: number
    total: number
    per_page: number
}

interface FactoryLogFilters {
    entityType?: string
    entityId?: number
    userId?: number
    perPage?: number
}

function buildKey(page: number, filters: FactoryLogFilters) {
    const params = new URLSearchParams({ page: String(page) })
    if (filters.entityType) params.set("entity_type", filters.entityType)
    if (filters.entityId) params.set("entity_id", String(filters.entityId))
    if (filters.userId) params.set("user_id", String(filters.userId))
    if (filters.perPage) params.set("per_page", String(filters.perPage))
    return `/api/factory-logs?${params.toString()}`
}

export function useFactoryLogs(page: number = 1, filters: FactoryLogFilters = {}) {
    const { data, error, isLoading, mutate } = useSWR<PaginatedResponse<FactoryLog>>(
        buildKey(page, filters),
        (url: string) => api.get<PaginatedResponse<FactoryLog>>(url),
        {
            refreshInterval: 5000,
            keepPreviousData: true // Mantener datos mientras carga la siguiente página
        }
    )

    return {
        logs: data?.data || [],
        meta: {
            currentPage: data?.current_page || 1,
            lastPage: data?.last_page || 1,
            total: data?.total || 0
        },
        isLoading,
        isError: error,
        refresh: mutate
    }
}
