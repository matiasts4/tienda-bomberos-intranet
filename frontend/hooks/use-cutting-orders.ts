"use client"

import useSWR from "swr"
import { cuttingOrdersAPI, type CuttingOrder } from "@/lib/api/cutting-orders"
import type { WorkshopStage } from "@/lib/workshop-stages"
import { useState } from "react"

// Función fetcher para SWR
const fetcher = async () => {
  console.log('🔄 [useCuttingOrders] Fetching orders...')
  try {
    const orders = await cuttingOrdersAPI.getAllOrders()
    console.log('✅ [useCuttingOrders] Orders received:', orders.length)
    return orders
  } catch (error) {
    console.error('❌ [useCuttingOrders] Error fetching orders:', error)
    throw error
  }
}

interface UseCuttingOrdersOptions {
  /** Intervalo de refresco automático en ms (tablet de taller y seguimiento en vivo). */
  refreshInterval?: number
}

export function useCuttingOrders(options: UseCuttingOrdersOptions = {}) {
  const [isCreating, setIsCreating] = useState(false)
  const [isUpdating, setIsUpdating] = useState(false)
  const [advancingId, setAdvancingId] = useState<string | null>(null)

  const {
    data: orders = [],
    error,
    isLoading,
    mutate
  } = useSWR<CuttingOrder[]>('cutting-orders', fetcher, {
    revalidateOnFocus: Boolean(options.refreshInterval),
    revalidateOnReconnect: true,
    refreshInterval: options.refreshInterval ?? 0,
    errorRetryCount: 3,
    dedupingInterval: 1000,
  })

  const createOrder = async (orderData: any) => {
    setIsCreating(true)
    try {
      const newOrder = await cuttingOrdersAPI.createOrder(orderData)

      // A successful POST remains successful even if the following GET fails.
      await mutate(current => [newOrder, ...(current ?? []).filter(order => String(order.id) !== String(newOrder.id))], false)
      void mutate().catch(error => console.error('No se pudo actualizar la lista tras crear la orden:', error))

      return newOrder
    } catch (error) {
      console.error('Error creating cutting order:', error)
      throw error
    } finally {
      setIsCreating(false)
    }
  }

  const updateOrder = async (id: string, updates: any) => {
    setIsUpdating(true)
    try {
      const updatedOrder = await cuttingOrdersAPI.updateOrder(id, updates)

      // Actualizar la caché local optimísticamente
      mutate(
        orders.map(order => order.id === id ? updatedOrder : order),
        false
      )

      // Revalidar desde el servidor
      await mutate()

      return updatedOrder
    } catch (error) {
      console.error('Error updating cutting order:', error)
      throw error
    } finally {
      setIsUpdating(false)
    }
  }

  /**
   * Avanza una orden a la etapa indicada mediante la máquina de estados del taller.
   * La lista se actualiza con la respuesta del servidor y luego se revalida.
   */
  const advanceStage = async (id: string, stage: WorkshopStage, observaciones?: string) => {
    setAdvancingId(String(id))
    try {
      const updatedOrder = await cuttingOrdersAPI.updateStage(id, stage, observaciones)
      await mutate(
        current => (current ?? []).map(order => String(order.id) === String(id) ? { ...order, ...updatedOrder } : order),
        false
      )
      void mutate()
      return updatedOrder
    } finally {
      setAdvancingId(null)
    }
  }

  const deleteOrder = async (id: string) => {
    try {
      await cuttingOrdersAPI.deleteOrder(id)

      // Actualizar la caché local optimísticamente
      mutate(orders.filter(order => order.id !== id), false)

      // Revalidar desde el servidor
      await mutate()
    } catch (error) {
      console.error('Error deleting cutting order:', error)
      throw error
    }
  }

  const getOrder = async (id: string) => {
    try {
      return await cuttingOrdersAPI.getOrder(id)
    } catch (error) {
      console.error('Error getting cutting order:', error)
      throw error
    }
  }

  const getNextCode = async () => {
    try {
      return await cuttingOrdersAPI.getNextCode()
    } catch (error) {
      console.error('Error getting next order code:', error)
      throw error
    }
  }

  return {
    orders,
    error,
    isLoading,
    isCreating,
    isUpdating,
    advancingId,
    createOrder,
    updateOrder,
    advanceStage,
    deleteOrder,
    getOrder,
    getNextCode,
    refresh: mutate,
  }
}
