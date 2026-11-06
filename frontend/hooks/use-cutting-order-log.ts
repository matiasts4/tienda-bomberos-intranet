import { useState, useCallback } from "react"
import { getCuttingOrderLogs, getCuttingOrderLogSummary } from "@/lib/mock-data"
import type { CuttingOrderLogEntry, CuttingOrderLogSummary, OrderStatus } from "@/lib/mock-data"

interface LogEntry {
  action: string
  details: string
  metadata?: Record<string, any>
}

export function useCuttingOrderLog() {
  const [isLoading, setIsLoading] = useState(false)

  // Obtener logs de una orden específica
  const getOrderLogs = useCallback((orderId: string): CuttingOrderLogEntry[] => {
    return getCuttingOrderLogs(orderId)
  }, [])

  // Obtener resumen de bitácora
  const getLogSummary = useCallback((orderId: string): CuttingOrderLogSummary | null => {
    return getCuttingOrderLogSummary(orderId)
  }, [])

  // Agregar nueva entrada a la bitácora (simulado)
  const addLogEntry = useCallback(async (
    orderId: string,
    orderCode: string,
    entry: LogEntry
  ): Promise<void> => {
    setIsLoading(true)
    
    try {
      // En un entorno real, esto sería una llamada al API
      const newEntry: CuttingOrderLogEntry = {
        id: `log-${Date.now()}`,
        orderId,
        orderCode,
        timestamp: new Date().toISOString(),
        user: "Usuario Actual", // En un entorno real vendría del contexto de auth
        action: entry.action,
        details: entry.details,
        metadata: entry.metadata
      }

      // Simular delay del API
      await new Promise(resolve => setTimeout(resolve, 500))
      
      // TODO: Enviar al backend
      console.log("Nueva entrada de bitácora:", newEntry)
      
    } catch (error) {
      console.error("Error al agregar entrada de bitácora:", error)
      throw error
    } finally {
      setIsLoading(false)
    }
  }, [])

  // Registrar cambio de estado
  const logStatusChange = useCallback(async (
    orderId: string,
    orderCode: string,
    previousStatus: OrderStatus,
    newStatus: OrderStatus,
    details?: string
  ): Promise<void> => {
    await addLogEntry(orderId, orderCode, {
      action: "status_changed",
      details: details || `Estado cambiado de ${previousStatus} a ${newStatus}`,
      metadata: {
        previousStatus,
        newStatus
      }
    })
  }, [addLogEntry])

  // Registrar consumo de material
  const logMaterialConsumption = useCallback(async (
    orderId: string,
    orderCode: string,
    materialId: string,
    materialName: string,
    quantityUsed: number,
    remainingQuantity: number
  ): Promise<void> => {
    await addLogEntry(orderId, orderCode, {
      action: "material_consumed",
      details: `Consumo de material: ${materialName}`,
      metadata: {
        materialId,
        quantityUsed,
        remainingQuantity
      }
    })
  }, [addLogEntry])

  // Registrar actualización de orden
  const logOrderUpdate = useCallback(async (
    orderId: string,
    orderCode: string,
    changes: Record<string, any>,
    details?: string
  ): Promise<void> => {
    await addLogEntry(orderId, orderCode, {
      action: "updated",
      details: details || "Orden actualizada",
      metadata: changes
    })
  }, [addLogEntry])

  // Generar PDF de bitácora (simulado)
  const generateLogPDF = useCallback(async (orderId: string): Promise<void> => {
    setIsLoading(true)
    
    try {
      // En un entorno real, esto sería una llamada al API del backend
      console.log("Generando PDF de bitácora para orden:", orderId)
      
      // Simular delay de generación
      await new Promise(resolve => setTimeout(resolve, 2000))
      
      // TODO: Implementar llamada real al backend
      // const response = await fetch(`/api/cutting-orders/${orderId}/log/pdf`)
      // const blob = await response.blob()
      // const url = window.URL.createObjectURL(blob)
      // const a = document.createElement('a')
      // a.href = url
      // a.download = `bitacora-${orderId}.pdf`
      // a.click()
      // window.URL.revokeObjectURL(url)
      
      console.log("PDF generado exitosamente")
      
    } catch (error) {
      console.error("Error al generar PDF:", error)
      throw error
    } finally {
      setIsLoading(false)
    }
  }, [])

  return {
    isLoading,
    getOrderLogs,
    getLogSummary,
    addLogEntry,
    logStatusChange,
    logMaterialConsumption,
    logOrderUpdate,
    generateLogPDF
  }
}
