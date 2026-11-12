import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import {
    Search, FileText, Download, Upload, RefreshCw, Plus,
    Loader2, Clock, ChevronDown, Layers, Ruler
} from "lucide-react"
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu"
import { useEffect, useState, useRef } from "react"
import { useInventoryPermissions } from "@/hooks/useInventoryPermissions"

interface InventoryToolbarProps {
    searchTerm: string
    onSearchChange: (term: string) => void
    tallaFilter?: string | null
    onTallaFilterChange?: (talla: string | null) => void
    onOpenBitacora: () => void
    onExport: () => void
    onImport: (e: React.ChangeEvent<HTMLInputElement>) => Promise<void>
    onRefresh: () => void
    onCreateProduct: () => void
    onOpenManualOrder: () => void
    onOpenPendingRequests: () => void
    onManageGenericos: () => void
    loading: boolean
    loadingImport: boolean
    loadingCatalogos: boolean
    pendingRequestsCount?: number
}

export function InventoryToolbar({
    searchTerm,
    onSearchChange,
    tallaFilter = null,
    onTallaFilterChange,
    onOpenBitacora,
    onExport,
    onImport,
    onRefresh,
    onCreateProduct,
    onOpenManualOrder,
    onOpenPendingRequests,
    onManageGenericos,
    loading,
    loadingImport,
    loadingCatalogos,
    pendingRequestsCount = 0
}: InventoryToolbarProps) {
    const fileInputRef = useRef<HTMLInputElement>(null)
    const { canManageStock, canCreateRequest } = useInventoryPermissions()
    const [mounted, setMounted] = useState(false)

    useEffect(() => {
        setMounted(true)
    }, [])

    const handleImportClick = () => {
        fileInputRef.current?.click()
    }

    if (!mounted) return <div className="border-b bg-white px-4 py-3 h-[61px]" />;

    return (
        <div className="border-b bg-white px-4 py-3">
            <div className="flex flex-col md:flex-row gap-3 justify-between items-start md:items-center">

                {/* Título + subtítulo */}
                <div className="flex-shrink-0">
                    <h1 className="text-xl font-bold text-gray-900">Gestión de Inventario</h1>
                    <p className="text-xs text-gray-400 mt-0.5">Bodega PTE · Edición Rápida</p>
                </div>

                {/* Controles — agrupados por jerarquía */}
                <div className="flex flex-wrap items-center gap-2 w-full md:w-auto">

                    {/* Búsqueda */}
                    <div className="relative w-full md:w-[320px] xl:w-[360px] 2xl:w-[420px]">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-500" />
                        <Input
                            type="search"
                            placeholder="Buscar producto, SKU, color o talla…"
                            aria-label="Buscar en el inventario por producto, SKU, color o talla"
                            className="pl-9 h-10 text-sm border-gray-200 bg-gray-50 focus:bg-white focus:border-blue-400 transition-colors placeholder:text-gray-500"
                            value={searchTerm}
                            onChange={(e) => onSearchChange(e.target.value)}
                        />
                    </div>

                    {/* Filtro rápido por Talla */}
                    {onTallaFilterChange && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className={`h-9 px-3 text-xs font-medium border-gray-200 transition-colors gap-1.5 ${
                                        tallaFilter
                                            ? 'bg-blue-50 text-blue-700 border-blue-200 font-semibold shadow-2xs'
                                            : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'
                                    }`}
                                >
                                    <Ruler className="h-3.5 w-3.5 opacity-70" />
                                    <span>{tallaFilter ? `Talla: ${tallaFilter}` : 'Tallas'}</span>
                                    <ChevronDown className="h-3 w-3 opacity-60" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" className="w-36 max-h-64 overflow-y-auto">
                                <DropdownMenuItem
                                    onClick={() => onTallaFilterChange(null)}
                                    className={`text-xs cursor-pointer ${!tallaFilter ? 'font-bold bg-slate-50' : ''}`}
                                >
                                    Todas las tallas
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                {['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', 'ESPECIAL', 'Unica', 'Ajustable 1', 'Ajustable 2'].map(t => (
                                    <DropdownMenuItem
                                        key={t}
                                        onClick={() => onTallaFilterChange(t)}
                                        className={`text-xs cursor-pointer font-mono ${tallaFilter === t ? 'font-bold bg-blue-50 text-blue-700' : ''}`}
                                    >
                                        {t}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}

                    {/* Separador visual */}
                    <div className="hidden md:block h-6 w-px bg-gray-150 mx-1" />

                    {/* Pedidos pendientes — alerta contextual */}
                    <Button
                        variant="ghost"
                        size="sm"
                        className={`relative h-9 px-3 text-xs font-medium transition-colors
                            ${pendingRequestsCount > 0
                                ? 'text-orange-600 bg-orange-50 hover:bg-orange-100 border border-orange-150'
                                : 'text-gray-400 hover:text-gray-600 hover:bg-gray-50'
                            }`}
                        onClick={onOpenPendingRequests}
                        title="Pedidos pendientes de bodega"
                    >
                        <Clock className={`h-3.5 w-3.5 mr-1.5 ${pendingRequestsCount > 0 ? 'text-orange-500' : ''}`} />
                        <span className="hidden sm:inline">Pendientes</span>
                        {pendingRequestsCount > 0 && (
                            <span className="ml-1.5 text-[10px] font-bold bg-orange-500 text-white px-1.5 py-0.5 rounded-full leading-none">
                                {pendingRequestsCount}
                            </span>
                        )}
                    </Button>

                    {/* Bitácora */}
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-9 px-3 text-xs font-medium text-gray-400 hover:text-gray-600 hover:bg-gray-50"
                        onClick={onOpenBitacora}
                        title="Historial de movimientos"
                    >
                        <FileText className="h-3.5 w-3.5 mr-1.5" />
                        <span className="hidden sm:inline">Bitácora</span>
                    </Button>

                    {canCreateRequest && (
                        <Button type="button" variant="outline" size="sm" onClick={onOpenManualOrder} className="h-9 gap-1.5 border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100">
                            <Plus className="h-4 w-4" />
                            Pedido Manual
                        </Button>
                    )}

                    {/* Acciones secundarias — agrupadas en dropdown */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-9 px-3 text-xs font-medium border-gray-200 text-gray-500 hover:text-gray-700 hover:bg-gray-50 gap-1"
                                disabled={loading || loadingImport}
                            >
                                {loading || loadingImport
                                    ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                    : <>Más <ChevronDown className="h-3 w-3 opacity-60" /></>
                                }
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-44">
                            <DropdownMenuItem
                                onClick={onExport}
                                className="text-xs cursor-pointer gap-2"
                            >
                                <Download className="h-3.5 w-3.5 text-green-500" />
                                Exportar Excel
                            </DropdownMenuItem>
                            {canManageStock && <DropdownMenuItem
                                onSelect={(e) => {
                                    e.preventDefault()
                                    handleImportClick()
                                }}
                                className="text-xs cursor-pointer gap-2"
                                disabled={loadingImport}
                            >
                                <Upload className="h-3.5 w-3.5 text-orange-500" />
                                Importar Excel
                            </DropdownMenuItem>}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onClick={onRefresh}
                                className="text-xs cursor-pointer gap-2"
                            >
                                <RefreshCw className={`h-3.5 w-3.5 text-gray-400 ${loading ? 'animate-spin' : ''}`} />
                                Actualizar datos
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button
                        size="sm"
                        variant="outline"
                        className="h-9 px-4 text-xs font-semibold shadow-sm transition-all active:scale-95 border-blue-200 text-blue-700 bg-blue-50 hover:bg-blue-100"
                        onClick={onManageGenericos}
                    >
                        <Layers className="h-4 w-4 mr-1.5" />
                        Catálogo
                    </Button>

                    {/* Crear productos también puede establecer stock inicial. */}
                    {canManageStock && <Button
                        size="sm"
                        className="h-9 px-4 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-sm transition-all active:scale-95"
                        onClick={onCreateProduct}
                        disabled={loadingCatalogos}
                    >
                        {loadingCatalogos ? (
                            <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
                        ) : (
                            <Plus className="h-4 w-4 mr-1.5" />
                        )}
                        Crear Producto
                    </Button>}
                </div>
            </div>

            {/* Input de archivo oculto para Importación Excel */}
            <input
                type="file"
                ref={fileInputRef}
                className="hidden"
                accept=".xlsx, .xls"
                disabled={!canManageStock}
                onChange={canManageStock ? onImport : undefined}
            />
        </div>
    )
}
