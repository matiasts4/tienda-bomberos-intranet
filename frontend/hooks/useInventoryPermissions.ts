"use client"

import { useAuthContext } from "@/components/auth-provider"

export function useInventoryPermissions() {
    const { user, loading } = useAuthContext()
    const canManageStock = !loading && user?.role === "superadmin"
    return {
        canManageStock,
        canApproveRequests: canManageStock,
        canCreateRequest: !loading && !!user && ["superadmin", "ejecutivo", "bodega"].includes(user.role),
    }
}
