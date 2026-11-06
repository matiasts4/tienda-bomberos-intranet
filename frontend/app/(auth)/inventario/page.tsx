"use client"

import React, { useState, useEffect } from "react"
import { AppHeader } from "@/components/app-header"
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb"
import { Card, CardContent } from "@/components/ui/card"
import { Loader2, AlertTriangle } from "lucide-react"
import { api } from "@/lib/api"
import { useInventoryPermissions } from "@/hooks/useInventoryPermissions"
import { useToast } from "@/hooks/use-toast"

// Context
import { useCatalogos } from "./CatalogoContext"

// Custom Hooks
import { useInventario } from "./hooks/useInventario"
import { useBulkEdit } from "./hooks/useBulkEdit"
import { useSkuEditor } from "./hooks/useSkuEditor"
import { useBitacora } from "./hooks/useBitacora"
import { useExcelImportExport } from "./hooks/useExcelImportExport"

// Components
import { InventoryStats } from "./components/InventoryStats"
import { InventoryToolbar } from "./components/InventoryToolbar"
import { InventoryTable } from "./components/InventoryTable"
import { BulkEditFloatingPanel } from "./components/BulkEditFloatingPanel"
import { BitacoraModal } from "./components/BitacoraModal"
import { CreateProductModal } from "./components/CreateProductModal"
import { EditProductModal } from "./components/EditProductModal"
import { ImportConfirmationModal } from "./components/ImportConfirmationModal"
import { ManualOrderModal } from "./components/ManualOrderModal"
import { PendingRequestsModal } from "./components/PendingRequestsModal"
import { ManageGenericosModal } from "./components/ManageGenericosModal"

// Constants
const FAMILIAS_VESTUARIO_IDS = [0, 1, 2, 3, 4, 5, 6, 9]
const FAMILIAS_ACCESORIOS_IDS = [0, 7, 8, 9]
const FORMATOS_VESTUARIO_IDS = [0, 21, 22, 23, 24]
const FORMATOS_GENERICO_IDS = [0] // Genérico siempre visible en ambos grupos
const MAPA_FAMILIA_SUBFAMILIA: Record<number, number[]> = {
  0: [0],
  1: [0, 11, 12],
  2: [0, 20],
  3: [0, 30, 31],
  4: [0, 40, 41, 42, 43, 44],
  5: [0, 50],
  6: [0, 60, 61, 62],
  7: [0, 70, 71],
  8: [0, 80],
  9: [0]
}
// Tipos válidos por familia (IDs del seeder)
const MAPA_FAMILIA_TIPOS: Record<number, number[]> = {
  0: [0],                                         // Genérico
  1: [0, 30, 31, 32, 33, 34, 35, 36, 10, 3],      // Camisas
  2: [0, 30, 31, 32, 33, 34, 35, 36, 10, 3],      // Poleras
  3: [0, 21, 22, 23, 50],                          // Polerones
  4: [0, 21, 22, 23, 70, 71, 72, 80, 81, 82, 83, 84, 85], // Chaquetas
  5: [0, 10, 23, 24, 35],                          // Pantalones
  6: [0, 60, 61, 70, 71, 72, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89], // Kit
  7: [0, 40, 41, 42, 45, 46, 47, 50],              // Accesorios
  8: [0, 86, 87, 88, 89],                          // Suvenir
  9: [0],                                          // Otros
}

interface CatalogoItem { id: number; nombre: string }

export default function InventarioPage() {
  const { canManageStock, canCreateRequest } = useInventoryPermissions()
  const { toast } = useToast()
  const { catalogos, loading: loadingCatalogos, error: catalogosError, initCatalogos } = useCatalogos()

  // Custom hooks
  const {
    products,
    loading,
    error,
    searchTerm,
    setSearchTerm,
    sortConfig,
    handleSort,
    sortedProducts,
    fetchInventario,
    pagination,
    goToPage,
    stats,
    stockFilter,
    setStockFilter,
    tallaFilter,
    setTallaFilter
  } = useInventario()

  const {
    editedStocks,
    handleQuickStockChange,
    saveBulkChanges,
    cancelBulkChanges,
    isBulkSaving
  } = useBulkEdit(products, fetchInventario)

  const bitacora = useBitacora()

  const {
    handleImportExcel,
    handleExportExcel,
    confirmImport,
    pendingImport,
    isConfirmModalOpen,
    setIsConfirmModalOpen,
    loadingImport,
    isExporting
  } = useExcelImportExport(fetchInventario, { searchTerm, stockFilter, tallaFilter, sortConfig })

  // Create/Edit modal states
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
  const [creating, setCreating] = useState(false)

  // Form states
  const [createFormData, setCreateFormData] = useState({
    nombre: '', descripcion: '', precio_venta: '', cantidad: '', stock_critico: 5,
    unidad_negocio_id: 1, origen_id: 1, grp_familia_id: 1, familia_id: -1,
    subfamilia_id: 0, familia_tipo_id: 0, familia_formato_id: 0,
    genero_id: 1, color_id: -1, talla_id: -1
  })
  const skuEditor = useSkuEditor(canManageStock, fetchInventario, toast)

  // Order Reservation States
  const [isManualOrderOpen, setIsManualOrderOpen] = useState(false)
  const [isManageGenericosOpen, setIsManageGenericosOpen] = useState(false)
  const [manageGenericosCreateMode, setManageGenericosCreateMode] = useState(false)
  const [isPendingRequestsOpen, setIsPendingRequestsOpen] = useState(false)
  const [pendingCount, setPendingCount] = useState(0)

  useEffect(() => {
    fetchPendingCount()
  }, [])

  const fetchPendingCount = async () => {
    try {
      const data: any = await api.get('/api/order-requests/count')
      setPendingCount(data.count || 0)
    } catch (e) {
      console.error("Error fetching pending count:", e)
    }
  }

  // Filtered catalogs
  const [familiasFiltradas, setFamiliasFiltradas] = useState<CatalogoItem[]>([])
  const [subfamiliasFiltradas, setSubfamiliasFiltradas] = useState<CatalogoItem[]>([])
  const [formatosFiltrados, setFormatosFiltrados] = useState<CatalogoItem[]>([])
  const [tiposFiltrados, setTiposFiltrados] = useState<CatalogoItem[]>([])

  // Filter catalogs based on selection
  useEffect(() => {
    if (!catalogos.familias?.length) return

    const grpId = Number(createFormData.grp_familia_id)
    const familiaId = Number(createFormData.familia_id)

    if (grpId === 1) {
      setFamiliasFiltradas(catalogos.familias?.filter((f: any) => FAMILIAS_VESTUARIO_IDS.includes(f.id)) || [])
      setFormatosFiltrados(catalogos.formatos?.filter((f: any) => FORMATOS_VESTUARIO_IDS.includes(f.id)) || [])
    } else {
      setFamiliasFiltradas(catalogos.familias?.filter((f: any) => FAMILIAS_ACCESORIOS_IDS.includes(f.id)) || [])
      setFormatosFiltrados(catalogos.formatos?.filter((f: any) => !FORMATOS_VESTUARIO_IDS.includes(f.id) || FORMATOS_GENERICO_IDS.includes(f.id)) || [])
    }

    if (familiaId !== -1 && MAPA_FAMILIA_SUBFAMILIA[familiaId]) {
      setSubfamiliasFiltradas(catalogos.subfamilias?.filter((s: any) => MAPA_FAMILIA_SUBFAMILIA[familiaId].includes(s.id)) || [])
    } else {
      setSubfamiliasFiltradas(catalogos.subfamilias || [])
    }

    // Filter tipos by familia
    const tiposIds = familiaId !== -1 && MAPA_FAMILIA_TIPOS[familiaId]
      ? MAPA_FAMILIA_TIPOS[familiaId]
      : [0]
    setTiposFiltrados(catalogos.tipos?.filter((t: any) => tiposIds.includes(t.id)) || [])

    // Only reset tipo if the current tipo is not valid for this family
    setCreateFormData(prev => {
      if (!tiposIds.includes(Number(prev.familia_tipo_id))) {
        return { ...prev, familia_tipo_id: 0 }
      }
      return prev
    })
  }, [createFormData.grp_familia_id, createFormData.familia_id, catalogos.familias, catalogos.formatos, catalogos.subfamilias, catalogos.tipos])

  // Create product -> Redirige al Catálogo Comercial (centro único de creación)
  const handleOpenCreate = async () => {
    try {
      setManageGenericosCreateMode(true)
      setIsManageGenericosOpen(true)
      void initCatalogos().catch(() => {})
    } catch (e) {
      console.error(e)
    }
  }

  const handleCreateChange = (e: any) => {
    const { name, value } = e.target
    setCreateFormData(prev => {
      const newState = { ...prev, [name]: value }
      if (name === 'grp_familia_id') {
        newState.familia_formato_id = -1
        newState.familia_id = -1
        newState.subfamilia_id = -1
      }
      if (name === 'familia_id') {
        newState.subfamilia_id = -1
      }
      return newState
    })
  }

  const handleCreateBulk = async (baseData: any, colores: number[], tallas: number[], generos: number[]) => {
    if (!canManageStock || creating) return
    setCreating(true)
    try {
      const precioLimpio = String(baseData.precio_venta).replace(/[\.,]/g, '')
      const payload = {
        nombre: baseData.nombre,
        descripcion: baseData.descripcion || '',
        precio_venta: Number(precioLimpio),
        stock_critico: Number(baseData.stock_critico),
        unidad_negocio_id: Number(baseData.unidad_negocio_id),
        origen_id: Number(baseData.origen_id),
        grp_familia_id: Number(baseData.grp_familia_id),
        familia_id: Number(baseData.familia_id),
        subfamilia_id: Number(baseData.subfamilia_id),
        familia_tipo_id: Number(baseData.familia_tipo_id),
        familia_formato_id: Number(baseData.familia_formato_id),
        colores,
        tallas,
        generos,
      }

      const response: any = await api.post('/api/productos/bulk', payload)
      const { creados, duplicados } = response.resumen
      
      await fetchInventario()
      setIsCreateModalOpen(false)
      setCreateFormData(prev => ({
        ...prev, nombre: '', descripcion: '', precio_venta: '', cantidad: '',
        familia_id: -1, subfamilia_id: -1, familia_formato_id: -1, color_id: -1, talla_id: -1
      }))

      let msg = `✨ Productos creados: ${creados}`
      if (duplicados > 0) msg += ` · Duplicados omitidos: ${duplicados}`
      toast({
        title: "Generación masiva completada",
        description: msg,
      })
    } catch (error: any) {
      toast({
        title: "Error en creación masiva",
        description: error.response?.data?.message || error.message,
        variant: "destructive",
      })
    } finally {
      setCreating(false)
    }
  }

  return (
    <div className="flex h-full flex-col bg-gray-50/50 text-sm pb-16 relative">
      <AppHeader />

      <div className="border-b bg-white px-4 py-2">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href="/" className="text-xs">Tienda Bomberos</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator className="[&>svg]:w-3 [&>svg]:h-3" />
            <BreadcrumbItem>
              <BreadcrumbPage className="text-xs">Inventario</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
      </div>

      <InventoryToolbar
        searchTerm={searchTerm}
        onSearchChange={setSearchTerm}
        tallaFilter={tallaFilter}
        onTallaFilterChange={setTallaFilter}
        onOpenBitacora={bitacora.open}
        onExport={handleExportExcel}
        onImport={handleImportExcel}
        onRefresh={() => fetchInventario(1, sortConfig, stockFilter, tallaFilter)}
        onCreateProduct={handleOpenCreate}
        onOpenManualOrder={() => setIsManualOrderOpen(true)}
        onOpenPendingRequests={() => setIsPendingRequestsOpen(true)}
        onManageGenericos={() => {
          setManageGenericosCreateMode(false)
          setIsManageGenericosOpen(true)
          void initCatalogos().catch(() => { /* El modal muestra el error y permite reintentar. */ })
        }}
        loading={loading || isExporting}
        loadingImport={loadingImport}
        loadingCatalogos={loadingCatalogos}
        pendingRequestsCount={pendingCount}
      />

      <main className="flex-1 overflow-auto p-2">
        <InventoryStats
          totalProducts={stats ? stats.total_skus : 0}
          outOfStockCount={stats ? stats.agotados : 0}
          criticalStockCount={stats ? stats.criticos : 0}
          lowStockCount={stats ? stats.bajo_stock : 0}
          currentFilter={stockFilter}
          onFilterChange={setStockFilter}
        />

        {error && (
          <div className="mb-4 p-3 text-sm text-red-700 bg-red-100 rounded-md flex items-center gap-2">
            <AlertTriangle className="h-4 w-4" />{error}
          </div>
        )}

        <Card className="shadow-sm border-gray-200">
          <CardContent className="p-0">
            <InventoryTable
              products={sortedProducts}
              loading={loading}
              sortConfig={sortConfig}
              onSort={handleSort}
              editedStocks={editedStocks}
              onStockChange={handleQuickStockChange}
              onEditProduct={skuEditor.open}
              pagination={pagination}
              onPageChange={goToPage}
            />
          </CardContent>
        </Card>
      </main>

      <BulkEditFloatingPanel
        changesCount={canManageStock ? Object.keys(editedStocks).length : 0}
        onSave={saveBulkChanges}
        onCancel={cancelBulkChanges}
        isSaving={isBulkSaving}
      />

      {canManageStock && isCreateModalOpen && <CreateProductModal
        isOpen={canManageStock && isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
        onBulkSubmit={handleCreateBulk}
        creating={creating}
        formData={createFormData}
        onChange={handleCreateChange}
        catalogos={catalogos}
        familiasFiltradas={familiasFiltradas}
        subfamiliasFiltradas={subfamiliasFiltradas}
        formatosFiltrados={formatosFiltrados}
        tiposFiltrados={tiposFiltrados}
      />}

      <EditProductModal key={skuEditor.session?.id ?? "closed"} editor={skuEditor} />

      <BitacoraModal bitacora={bitacora} />

      <ImportConfirmationModal
        isOpen={canManageStock && isConfirmModalOpen}
        onClose={() => setIsConfirmModalOpen(false)}
        onConfirm={confirmImport}
        loading={loadingImport}
        summary={pendingImport}
      />

      <ManualOrderModal
        isOpen={canCreateRequest && isManualOrderOpen}
        onClose={() => setIsManualOrderOpen(false)}
        onSuccess={() => {
          fetchInventario()
          fetchPendingCount()
        }}
      />

      <PendingRequestsModal
        isOpen={isPendingRequestsOpen}
        onClose={() => setIsPendingRequestsOpen(false)}
        onStatusChange={() => {
          fetchInventario()
          fetchPendingCount()
        }}
      />

      <ManageGenericosModal
        isOpen={isManageGenericosOpen}
        onClose={() => {
          setIsManageGenericosOpen(false)
          setManageGenericosCreateMode(false)
        }}
        catalogos={catalogos}
        loadingCatalogos={loadingCatalogos}
        catalogosError={catalogosError}
        onRetryCatalogos={() => { void initCatalogos().catch(() => {}) }}
        initialCreateMode={manageGenericosCreateMode}
        onCatalogChange={fetchInventario}
      />

      {loadingCatalogos && !isManageGenericosOpen && (
        <div className="fixed inset-0 z-[200] bg-black/50 backdrop-blur-sm flex items-center justify-center">
          <div className="bg-white p-6 rounded-lg shadow-2xl flex flex-col items-center gap-4 animate-in zoom-in-95 duration-200">
            <Loader2 className="h-10 w-10 text-blue-600 animate-spin" />
            <div className="text-center">
              <p className="font-bold text-gray-800 text-lg">Cargando Catálogos</p>
              <p className="text-xs text-gray-500">Preparando formularios...</p>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
