"use client"

import { FechaInput } from "@/components/ui/fecha-input"
import { useEffect, useState, useCallback, useMemo } from "react"
import { AppHeader } from "@/components/app-header"
import { KanbanColumn } from "@/components/kanban-column"
import { DespachoKanbanCard } from "@/components/DespachoKanbanCard"
import { ScrollArea, ScrollBar } from "@/components/ui/scroll-area"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import {
  DndContext,
  DragOverlay,
  useSensor,
  useSensors,
  PointerSensor,
  DragEndEvent,
  DragStartEvent
} from "@dnd-kit/core"
import { api } from "@/lib/api"
import { toast } from "sonner"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { OrderTrackingPanel } from "@/components/fabrica/OrderTrackingPanel"
import { Activity, Truck } from "lucide-react"

export default function DespachoPage() {
  const [mounted, setMounted] = useState(false)
  const [loading, setLoading] = useState(true)
  const [estados, setEstados] = useState<any[]>([])
  const [items, setItems] = useState<any[]>([])
  
  const [activeId, setActiveId] = useState<string | null>(null)
  const [activeItem, setActiveItem] = useState<any | null>(null)

  // Dialog State
  const [formOpen, setFormOpen] = useState(false)
  const [voucherFormOpen, setVoucherFormOpen] = useState(false)
  const [voucherFile, setVoucherFile] = useState<File | null>(null)
  const [pendingMove, setPendingMove] = useState<{ item: any, targetStateId: number } | null>(null)
  const [tipoAgencia, setTipoAgencia] = useState("Starken")
  const [formData, setFormData] = useState({
    calle: '',
    numero_calle: '',
    comuna: '',
    ciudad: '',
    agencia: '',
    fecha_despacho: '',
    observacion: ''
  })
  const [submitting, setSubmitting] = useState(false)

  const loadData = useCallback(async () => {
    try {
      setLoading(true)
      const res = await api.get<any>('/api/despachos/kanban')
      setEstados(res?.estados || [])
      setItems(res?.items || [])
    } catch (e: any) {
      toast.error(e?.data?.message || "Error al cargar despachos")
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    setMounted(true)
    loadData()
  }, [loadData])

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    })
  )

  const handleDragStart = useCallback((event: DragStartEvent) => {
    const { active } = event
    setActiveId(active.id as string)
    setActiveItem(active.data.current?.item)
  }, [])

  const handleDragEnd = useCallback(async (event: DragEndEvent) => {
    const { active, over } = event
    setActiveId(null)
    setActiveItem(null)

    if (!over) return

    const item = active.data.current?.item
    if (!item) return

    const targetStateIdString = over.id as string
    const targetStateId = Number(targetStateIdString.replace('col-', ''))
    const currentStateId = item.estado_despacho?.id

    if (!targetStateId || targetStateId === currentStateId) return

    const targetState = estados.find(e => e.id === targetStateId)
    const currentState = estados.find(e => e.id === currentStateId)

    // Si pasamos a "Entregado a agencia"
    if (targetState?.nombre === 'Entregado a agencia') {
      setPendingMove({ item, targetStateId })
      setVoucherFile(null)
      setVoucherFormOpen(true)
      return
    }

    // Si pasamos de "Por despachar" a "Preparando despacho"
    if (targetState?.nombre === 'Preparando despacho' && currentState?.nombre === 'Por despachar') {
      const prevAgencia = item.despacho?.agencia || ''
      const isPreset = prevAgencia === 'Starken' || prevAgencia === 'Chilexpress' || prevAgencia === 'Chilexpress'
      
      setTipoAgencia(isPreset ? prevAgencia : (prevAgencia ? 'Otra' : 'Starken'))
      setFormData({
        calle: item.despacho?.calle || item.cliente?.calle || '',
        numero_calle: item.despacho?.numero_calle || item.cliente?.numero || '',
        comuna: item.despacho?.comuna || item.cliente?.comuna || '',
        ciudad: item.despacho?.ciudad || item.cliente?.ciudad || '',
        agencia: prevAgencia,
        fecha_despacho: item.despacho?.fecha_despacho ? item.despacho.fecha_despacho.split('T')[0] : new Date().toISOString().split('T')[0],
        observacion: item.despacho?.observacion || ''
      })
      setPendingMove({ item, targetStateId })
      setFormOpen(true)
      return
    }

    // Normal move
    try {
      await api.patch(`/api/despachos/cotizacion/${item.cotizacion.id}/estado`, {
        estado_id: targetStateId,
      })
      toast.success("Estado actualizado exitosamente")
      loadData()
    } catch (e: any) {
      toast.error(e?.data?.message || "Error al actualizar el estado")
      console.error(e)
    }
  }, [estados, loadData])

  const handleVoucherSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!pendingMove || !voucherFile) {
      toast.error("Debe adjuntar el voucher")
      return
    }

    setSubmitting(true)
    const formDataObj = new FormData()
    formDataObj.append("estado_id", String(pendingMove.targetStateId))
    formDataObj.append("voucher_file", voucherFile)
    
    // In Laravel we use POST to submit FormData containing files, and _method=PATCH
    formDataObj.append("_method", "PATCH")

    try {
      await api.post(`/api/despachos/cotizacion/${pendingMove.item.cotizacion.id}/estado`, formDataObj)
      toast.success("Estado actualizado y voucher adjuntado")
      setVoucherFormOpen(false)
      setPendingMove(null)
      loadData()
    } catch (e: any) {
      toast.error(e?.data?.message || "Error al subir el voucher")
      console.error(e)
    } finally {
      setSubmitting(false)
    }
  }

  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!pendingMove) return

    setSubmitting(true)
    
    // Determine the actual agency string
    let finalAgencia = tipoAgencia
    if (tipoAgencia === 'Otra') {
      finalAgencia = formData.agencia
      if (!finalAgencia || finalAgencia.trim() === '') {
        toast.error("Debe especificar la agencia")
        setSubmitting(false)
        return
      }
    }

    try {
      await api.patch(`/api/despachos/cotizacion/${pendingMove.item.cotizacion.id}/estado`, {
        estado_id: pendingMove.targetStateId,
        ...formData,
        agencia: finalAgencia
      })
      toast.success("Estado actualizado y datos guardados")
      setFormOpen(false)
      setPendingMove(null)
      loadData()
    } catch (e: any) {
      toast.error(e?.data?.message || "Error al guardar los datos de despacho")
      console.error(e)
    } finally {
      setSubmitting(false)
    }
  }

  if (!mounted) return null

  return (
    <div className="flex h-full flex-col">
      <AppHeader />

      <div className="border-b bg-muted/50 px-6 py-3">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href="/">Tienda Bomberos</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Despacho</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
      </div>

      <div className="border-b bg-background px-6 py-4">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold">Seguimiento y Despacho</h1>
            <p className="text-sm text-muted-foreground">Trazabilidad en tiempo real de las órdenes de taller y gestión de envíos</p>
          </div>
          <div>
            <button
              onClick={loadData}
              disabled={loading}
              className="text-sm font-medium underline text-muted-foreground hover:text-foreground disabled:opacity-50"
            >
              {loading ? "Cargando..." : "Refrescar datos"}
            </button>
          </div>
        </div>
      </div>

      <main className="flex-1 overflow-auto p-6 bg-slate-50/30">
        <Tabs defaultValue="seguimiento" className="gap-4">
          <TabsList className="h-12">
            <TabsTrigger value="seguimiento" className="h-10 gap-2 px-4">
              <Activity className="h-4 w-4" aria-hidden="true" />
              Seguimiento de órdenes
            </TabsTrigger>
            <TabsTrigger value="despachos" className="h-10 gap-2 px-4">
              <Truck className="h-4 w-4" aria-hidden="true" />
              Despachos
            </TabsTrigger>
          </TabsList>

          <TabsContent value="seguimiento">
            <OrderTrackingPanel />
          </TabsContent>

          <TabsContent value="despachos">
        <DndContext
          sensors={sensors}
          onDragStart={handleDragStart}
          onDragEnd={handleDragEnd}
        >
          <ScrollArea className="h-[calc(100vh-300px)] w-full">
            <div className="flex gap-6 pb-4 min-w-max h-full">
              
              {estados.map((estado) => {
                const columnItems = items.filter(i => i.estado_despacho?.id === estado.id)
                return (
                  <KanbanColumn 
                    key={estado.id} 
                    title={estado.nombre} 
                    count={columnItems.length} 
                    columnId={`col-${estado.id}`}
                  >
                    {columnItems.map((item) => (
                      <DespachoKanbanCard
                        key={item.id}
                        item={item}
                      />
                    ))}

                    {columnItems.length === 0 && !loading && (
                      <div className="rounded-lg border-2 border-dashed p-8 text-center bg-white/50">
                        <p className="text-sm text-muted-foreground">Vacío</p>
                      </div>
                    )}
                  </KanbanColumn>
                )
              })}
            </div>

            <ScrollBar orientation="horizontal" />
          </ScrollArea>

          <DragOverlay>
            {activeItem ? (
              <div className="opacity-90 rotate-2 scale-105">
                <DespachoKanbanCard item={activeItem} />
              </div>
            ) : null}
          </DragOverlay>
        </DndContext>
          </TabsContent>
        </Tabs>
      </main>

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Datos de Envío</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleFormSubmit} className="space-y-4 mt-2">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Calle</Label>
                <Input value={formData.calle} onChange={e => setFormData({...formData, calle: e.target.value})} />
              </div>
              <div className="space-y-2">
                <Label>Número</Label>
                <Input value={formData.numero_calle} onChange={e => setFormData({...formData, numero_calle: e.target.value})} />
              </div>
              <div className="space-y-2">
                <Label>Comuna</Label>
                <Input value={formData.comuna} onChange={e => setFormData({...formData, comuna: e.target.value})} />
              </div>
              <div className="space-y-2">
                <Label>Ciudad</Label>
                <Input value={formData.ciudad} onChange={e => setFormData({...formData, ciudad: e.target.value})} />
              </div>
              <div className="space-y-2 col-span-2 sm:col-span-1">
                <Label>Agencia de Envío</Label>
                <RadioGroup 
                  value={tipoAgencia} 
                  onValueChange={setTipoAgencia} 
                  className="flex flex-col space-y-2 mt-1"
                >
                  <div className="flex items-center space-x-2">
                    <RadioGroupItem value="Starken" id="r-starken" />
                    <Label htmlFor="r-starken" className="cursor-pointer font-normal">Starken</Label>
                  </div>
                  <div className="flex items-center space-x-2">
                    <RadioGroupItem value="Chilexpress" id="r-chilexpress" />
                    <Label htmlFor="r-chilexpress" className="cursor-pointer font-normal">Chilexpress</Label>
                  </div>
                  <div className="flex items-center space-x-2">
                    <RadioGroupItem value="Otra" id="r-otra" />
                    <Label htmlFor="r-otra" className="cursor-pointer font-normal">Otra</Label>
                  </div>
                </RadioGroup>
                
                {tipoAgencia === 'Otra' && (
                  <Input 
                    value={formData.agencia} 
                    onChange={e => setFormData({...formData, agencia: e.target.value})} 
                    placeholder="Especifique la agencia..." 
                    className="mt-2"
                    required
                  />
                )}
              </div>
              <div className="space-y-2 col-span-2 sm:col-span-1">
                <Label>Fecha de Despacho</Label>
                <FechaInput value={formData.fecha_despacho} onChange={v => setFormData({...formData, fecha_despacho: v})} required />
              </div>
            </div>
            <div className="space-y-2">
              <Label>Observación</Label>
              <Textarea value={formData.observacion} onChange={e => setFormData({...formData, observacion: e.target.value})} placeholder="Instrucciones especiales..." />
            </div>
            <DialogFooter className="pt-4">
              <Button type="button" variant="ghost" onClick={() => setFormOpen(false)} disabled={submitting}>Cancelar</Button>
              <Button type="submit" disabled={submitting}>{submitting ? 'Guardando...' : 'Confirmar Envío'}</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
      <Dialog open={voucherFormOpen} onOpenChange={setVoucherFormOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Adjuntar Voucher</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleVoucherSubmit} className="space-y-4 mt-2">
            <div className="space-y-2">
              <Label>Voucher de Agencia</Label>
              <Input 
                type="file" 
                accept=".pdf,.jpg,.jpeg,.png"
                onChange={e => setVoucherFile(e.target.files?.[0] || null)} 
                required 
              />
              <p className="text-xs text-muted-foreground">Sube el comprobante de entrega en formato PDF o imagen.</p>
            </div>
            <DialogFooter className="pt-4">
              <Button type="button" variant="ghost" onClick={() => setVoucherFormOpen(false)} disabled={submitting}>Cancelar</Button>
              <Button type="submit" disabled={submitting || !voucherFile}>{submitting ? 'Subiendo...' : 'Confirmar Entrega'}</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
