"use client"

import useSWR from "swr"
import { type Order, type OrderStatus } from "@/lib/mock-data"

const ORDERS_KEY = "orders"

export function useOrdersStore() {
  const { data: orders = [], mutate } = useSWR<Order[]>(ORDERS_KEY, null, {
    fallbackData: [],
    revalidateOnFocus: false,
    revalidateOnReconnect: false,
  })

  const updateOrderStatus = (orderId: string, newStatus: OrderStatus) => {
    mutate(
      (currentOrders) => {
        if (!currentOrders) return []
        return currentOrders.map((order) => {
          if (order.id === orderId) {
            const updatedOrder = { ...order, status: newStatus }
            
            // Si se mueve a "finished", agregar timestamp de completado
            if (newStatus === "finished") {
              updatedOrder.updatedAt = new Date().toISOString()
            }
            // Si se saca de "finished" y tenía timestamp del día actual, removerlo
            else if (order.status === "finished" && order.updatedAt) {
              const orderDate = new Date(order.updatedAt).toDateString()
              const today = new Date().toDateString()
              if (orderDate === today) {
                updatedOrder.updatedAt = undefined
              }
            }
            
            return updatedOrder
          }
          return order
        })
      },
      { revalidate: false },
    )
  }

  const updateOrderWithWorkshop = (orderId: string, workshopData: { workshop: string; notes?: string }) => {
    mutate(
      (currentOrders) => {
        if (!currentOrders) return []
        return currentOrders.map((order) =>
          order.id === orderId
            ? {
                ...order,
                workshop: workshopData.workshop,
                workshopNotes: workshopData.notes,
                status: "sewing" as OrderStatus,
                updatedAt: new Date().toISOString()
              }
            : order
        )
      },
      { revalidate: false },
    )
  }

  const addOrder = (newOrder: Order) => {
    mutate(
      (currentOrders) => {
        if (!currentOrders) return [newOrder]
        return [...currentOrders, newOrder]
      },
      { revalidate: false },
    )
  }

  const getOrdersByStatus = (status: OrderStatus) => {
    return orders.filter((order) => order.status === status)
  }

  return {
    orders,
    updateOrderStatus,
    updateOrderWithWorkshop,
    addOrder,
    getOrdersByStatus,
  }
}
