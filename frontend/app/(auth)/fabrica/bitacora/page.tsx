"use client"

import Link from "next/link"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { FileText, Clock, User, Activity, ArrowLeft, ChevronLeft, ChevronRight } from "lucide-react"
import { format } from "date-fns"
import { es } from "date-fns/locale"
import { useFactoryLogs } from "@/hooks/use-factory-logs"
import { useState } from "react"

// Mapa de traducción de estados
const statusTranslations: Record<string, string> = {
  'order-received': 'Corte',
  'cutting': 'Corte',
  'cutting-finished': 'Corte Terminado',
  'preparing': 'Preparación',
  'transit': 'Tránsito',
  'sewing': 'Confección',
  'quality-control': 'Control de Calidad',
  'finished': 'Terminado',
  'delivered': 'Entregado',
  'returned': 'Devuelto',
  'corte': 'Corte',
  'confeccion': 'Confección',
  'empaque': 'Empaque',
  'terminado': 'Terminado'
}

const translateText = (text: string) => {
  let translated = text
  Object.entries(statusTranslations).forEach(([key, value]) => {
    // Reemplaza la palabra exacta o la palabra al final de la frase
    translated = translated.replace(new RegExp(`\\b${key}\\b`, 'g'), value)
  })
  return translated
}

export default function BitacoraPage() {
  const [page, setPage] = useState(1)
  const { logs, meta, isLoading } = useFactoryLogs(page)

  // Calcular estadísticas con datos reales
  const totalLogs = logs.length

  // Estimación de "órdenes activas" basada en logs únicos de entidades recientemente tocadas (últimos 7 días)
  const recentLogs = logs.filter(log => {
    const logDate = new Date(log.created_at)
    const weekAgo = new Date()
    weekAgo.setDate(weekAgo.getDate() - 7)
    return logDate > weekAgo
  })
  const activeEntities = new Set(recentLogs.map(log => `${log.entity_type}-${log.entity_id}`)).size

  const todayLogsCount = logs.filter(log => {
    const logDate = new Date(log.created_at).toDateString()
    const today = new Date().toDateString()
    return logDate === today
  }).length

  const statusChangesCount = logs.filter(log => /estado|status|etapa/i.test(log.action)).length

  return (
    <div className="container mx-auto py-6 space-y-6">
      <div className="flex items-center gap-4">
        <Link href="/fabrica">
          <Button variant="outline" className="h-12 gap-2 px-4">
            <ArrowLeft className="h-4 w-4" />
            Volver a Fábrica
          </Button>
        </Link>
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Bitácora de Fábrica</h1>
          <p className="text-muted-foreground">
            Historial de movimientos, cambios de etapa, órdenes y paquetes con su responsable.
          </p>
        </div>
      </div>

      {/* Estadísticas Rápidas */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Total Movimientos</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{totalLogs}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Actividad Reciente (7d)</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {activeEntities} <span className="text-xs font-normal text-muted-foreground">entidades</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Movimientos Hoy</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {todayLogsCount}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Cambios de Estado</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {statusChangesCount}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Tabla de Actividades */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Activity className="h-5 w-5" />
            Registro de Actividades
          </CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Acción</TableHead>
                <TableHead>Descripción</TableHead>
                <TableHead>Entidad</TableHead>
                <TableHead>Responsable</TableHead>
                <TableHead>Fecha</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={5} className="text-center py-4">Cargando bitácora...</TableCell>
                </TableRow>
              ) : logs.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="text-center py-4">No hay registros aún.</TableCell>
                </TableRow>
              ) : (
                logs.map((log) => (
                  <TableRow key={log.id}>
                    <TableCell>
                      <Badge variant="outline" className={
                        log.action.includes('Cread') ? 'bg-green-50 text-green-700 border-green-200' :
                          /estado|etapa/i.test(log.action) ? 'bg-blue-50 text-blue-700 border-blue-200' :
                            'bg-gray-50'
                      }>
                        {log.action}
                      </Badge>
                    </TableCell>
                    <TableCell className="max-w-md">
                      {translateText(log.description)}
                    </TableCell>
                    <TableCell>
                      <Badge variant="secondary" className="text-xs">
                        {(() => {
                          let details: any = {};
                          try {
                            details = typeof log.details === 'string' ? JSON.parse(log.details) : log.details;
                          } catch (e) {
                            console.error("Error parsing log details", e);
                          }

                          const code = details?.code || details?.id || log.entity_id;

                          if (log.entity_type === 'CuttingOrder' || log.entity_type === 'App\\Models\\CuttingOrder') {
                            return `Orden de corte ${code}`;
                          } else if (log.entity_type === 'Package' || log.entity_type === 'App\\Models\\Package') {
                            return `Paquete ${code}`;
                          }

                          return `${log.entity_type} #${log.entity_id}`;
                        })()}
                      </Badge>
                    </TableCell>
                    <TableCell className="whitespace-nowrap">
                      <div className="flex items-center gap-2 text-sm">
                        <User className="h-3 w-3 text-muted-foreground" />
                        {log.user?.name ?? 'Sistema'}
                      </div>
                    </TableCell>
                    <TableCell className="text-muted-foreground whitespace-nowrap">
                      <div className="flex items-center gap-2 text-sm">
                        <Clock className="h-3 w-3" />
                        {/* Safe date parsing */}
                        {(() => {
                          try {
                            return format(new Date(log.created_at), "dd/MM/yy HH:mm", { locale: es })
                          } catch (e) {
                            return log.created_at
                          }
                        })()}
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {/* Paginación */}
      <div className="flex items-center justify-between">
        <div className="text-sm text-muted-foreground">
          Mostrando página {meta?.currentPage || 1} de {meta?.lastPage || 1} ({meta?.total || 0} registros)
        </div>
        <div className="flex gap-2">
          <Button
            variant="outline"
            className="h-12 px-4"
            onClick={() => setPage(p => Math.max(1, p - 1))}
            disabled={page === 1 || isLoading}
          >
            <ChevronLeft className="h-4 w-4 mr-2" />
            Anterior
          </Button>

          {/* Indicador visual de página actual */}
          <div className="flex items-center justify-center px-3 py-1 text-sm font-medium border rounded-md min-w-[3rem]">
            {page}
          </div>

          <Button
            variant="outline"
            className="h-12 px-4"
            onClick={() => setPage(p => Math.min(meta?.lastPage || 1, p + 1))}
            disabled={page === (meta?.lastPage || 1) || isLoading}
          >
            Siguiente
            <ChevronRight className="h-4 w-4 ml-2" />
          </Button>
        </div>
      </div>
    </div>
  )
}
