import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Pencil, Loader2, ArrowUpDown } from "lucide-react"
import { useInventoryPermissions } from "@/hooks/useInventoryPermissions"

interface ProductoInventario {
    sku: string
    producto: string
    categoria: string
    talla: string
    color: string
    genero: string
    bodega: string
    precio_venta: number | null
    cantidad_fisica: number
    reserved_stock?: number
    stock_critico: number
    estado: string
}

interface InventoryTableProps {
    products: ProductoInventario[]
    loading: boolean
    sortConfig: { key: keyof ProductoInventario; direction: 'asc' | 'desc' } | null
    onSort: (key: keyof ProductoInventario) => void
    editedStocks: Record<string, number>
    onStockChange: (sku: string, value: string) => void
    onEditProduct: (product: ProductoInventario) => void
    pagination: {
        currentPage: number
        lastPage: number
        total: number
    }
    onPageChange: (page: number) => void
}

const getStockLevel = (c: number, cri: number) => {
    if (c <= 0) return "none"
    if (c <= cri) return "critical"
    if (c <= cri * 2) return "low"
    return "normal"
}

const SortableHeader = ({
    label,
    sortKey,
    alignRight = false,
    sortConfig,
    onSort,
    className
}: {
    label: string
    sortKey: keyof ProductoInventario
    alignRight?: boolean
    sortConfig: { key: keyof ProductoInventario; direction: 'asc' | 'desc' } | null
    onSort: (key: keyof ProductoInventario) => void
    className?: string
}) => (
    <TableHead
        aria-sort={sortConfig?.key === sortKey ? (sortConfig.direction === 'asc' ? 'ascending' : 'descending') : 'none'}
        className={`h-10 select-none p-0 text-gray-500 ${className || ''}`}
    >
        <button
            type="button"
            className={`flex h-10 w-full items-center gap-1 px-3 text-[11px] font-semibold uppercase tracking-wider transition-colors hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500 ${alignRight ? 'justify-end text-right' : 'text-left'}`}
            onClick={() => onSort(sortKey)}
            aria-label={`Ordenar por ${label}${sortConfig?.key === sortKey ? `, orden actual ${sortConfig.direction === 'asc' ? 'ascendente' : 'descendente'}` : ''}`}
        >
            {label}
            <ArrowUpDown
                className={`h-3 w-3 flex-shrink-0 ${sortConfig?.key === sortKey ? 'text-blue-500 stroke-[2.5]' : 'text-gray-300'
                    }`}
            />
        </button>
    </TableHead>
)

// Badge de estado como dot + texto — menos ruido visual
const StockBadge = ({ level }: { level: string }) => {
    if (level === 'none') return (
        <span className="inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
            <span className="w-1.5 h-1.5 rounded-full bg-slate-300 flex-shrink-0" />
            Agotado
        </span>
    )
    if (level === 'critical') return (
        <span className="inline-flex items-center gap-1.5 text-[11px] font-semibold text-red-600">
            <span className="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0 animate-pulse motion-reduce:animate-none" />
            Crítico
        </span>
    )
    if (level === 'low') return (
        <span className="inline-flex items-center gap-1.5 text-[11px] font-medium text-orange-500">
            <span className="w-1.5 h-1.5 rounded-full bg-orange-400 flex-shrink-0" />
            Bajo
        </span>
    )
    return (
        <span className="inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-600">
            <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 flex-shrink-0" />
            Normal
        </span>
    )
}

export function InventoryTable(props: InventoryTableProps) {
    const { canManageStock } = useInventoryPermissions()
    const {
        products,
        loading,
        sortConfig,
        onSort,
        editedStocks,
        onStockChange,
        onEditProduct
    } = props

    const renderPagination = (borderClass: string) => (
        <div className={`flex items-center justify-between px-4 py-3 bg-gray-50/50 ${borderClass}`}>
            <p className="text-[11px] text-gray-400">
                Página <span className="font-semibold text-gray-600">{props.pagination?.currentPage}</span>{" "}
                de <span className="font-semibold text-gray-600">{props.pagination?.lastPage}</span>
                <span className="ml-2 text-gray-300">· {props.pagination?.total} SKUs</span>
            </p>
            <div className="flex gap-1.5">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={props.pagination?.currentPage === 1 || loading}
                    onClick={() => props.onPageChange && props.onPageChange(props.pagination.currentPage - 1)}
                    className="h-7 text-xs px-3 border-gray-200 text-gray-500 hover:text-gray-700 hover:bg-gray-100"
                >
                    ← Anterior
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={props.pagination?.currentPage === props.pagination?.lastPage || loading}
                    onClick={() => props.onPageChange && props.onPageChange(props.pagination.currentPage + 1)}
                    className="h-7 text-xs px-3 border-gray-200 text-gray-500 hover:text-gray-700 hover:bg-gray-100"
                >
                    Siguiente →
                </Button>
            </div>
        </div>
    )

    return (
        <div className="rounded-xl border-0 bg-white shadow-sm overflow-hidden ring-1 ring-gray-100">
            {/* PAGINACIÓN HEADER (ARRIBA) */}
            {renderPagination("border-b border-gray-100")}

            <div className="overflow-x-auto">
                <Table className="w-full min-w-[1280px] table-fixed">
                    <colgroup>
                        <col className="w-[150px]" />
                        <col className="w-[430px]" />
                        <col className="w-[90px]" />
                        <col className="w-[110px]" />
                        <col className="w-[170px]" />
                        <col className="w-[105px]" />
                        <col className="w-[150px]" />
                        <col className="w-[75px]" />
                    </colgroup>
                    <TableHeader className="bg-gray-50/80 border-b border-gray-100">
                        <TableRow className="hover:bg-transparent">
                            <TableHead className="text-gray-500 font-semibold text-[11px] uppercase tracking-wider h-10 select-none px-3">
                                SKU
                            </TableHead>
                            <SortableHeader
                                label="Producto"
                                sortKey="producto"
                                sortConfig={sortConfig}
                                onSort={onSort}
                            />
                            <SortableHeader
                                label="Talla"
                                sortKey="talla"
                                sortConfig={sortConfig}
                                onSort={onSort}
                            />
                            <SortableHeader
                                label="Precio"
                                sortKey="precio_venta"
                                alignRight
                                sortConfig={sortConfig}
                                onSort={onSort}
                            />
                            <SortableHeader
                                label="Stock Disponible"
                                sortKey="cantidad_fisica"
                                alignRight
                                sortConfig={sortConfig}
                                onSort={onSort}
                            />
                            <SortableHeader
                                label="Reservado"
                                sortKey="reserved_stock"
                                alignRight
                                sortConfig={sortConfig}
                                onSort={onSort}
                            />
                            <TableHead className="text-gray-500 font-semibold text-[11px] uppercase tracking-wider h-10 select-none px-3">
                                Estado
                            </TableHead>
                            <TableHead>
                                <span className="sr-only">Acciones</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {loading ? (
                            <TableRow>
                                <TableCell colSpan={8} className="text-center py-10">
                                    <Loader2 className="animate-spin inline mr-2 h-5 w-5 text-blue-500" />
                                    <span className="text-sm text-gray-400">Cargando datos...</span>
                                </TableCell>
                            </TableRow>
                        ) : products.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={8} className="text-center py-10 text-gray-400 text-sm">
                                    No se encontraron productos.
                                </TableCell>
                            </TableRow>
                        ) : (
                            products.map(p => {
                                const inputValue = editedStocks[p.sku] !== undefined ? editedStocks[p.sku] : p.cantidad_fisica
                                const isEdited = editedStocks[p.sku] !== undefined && editedStocks[p.sku] !== p.cantidad_fisica
                                const originalQty = p.cantidad_fisica
                                const level = getStockLevel(originalQty, p.stock_critico)
                                const isAlert = level === 'critical' || level === 'none'

                                return (
                                    <TableRow
                                        key={p.sku}
                                        className={`transition-colors border-b border-gray-50 last:border-0 h-11 group
                                            ${isEdited
                                                ? 'bg-amber-50/60'
                                                : isAlert
                                                    ? 'bg-red-50/20 hover:bg-red-50/40'
                                                    : 'hover:bg-slate-50/70'
                                            }`}
                                    >
                                        {/* SKU — desenfatizado (datos secundarios) */}
                                        <TableCell className="font-mono text-[11px] text-gray-500 py-2 px-3 whitespace-nowrap">
                                            {p.sku}
                                        </TableCell>

                                        {/* Producto — primer escaneo visual */}
                                        <TableCell
                                            className="overflow-hidden text-ellipsis whitespace-nowrap px-3 py-2"
                                            title={p.producto}
                                        >
                                            <span className="block truncate text-sm font-semibold text-gray-800">{p.producto}</span>
                                        </TableCell>

                                        {/* Talla */}
                                        <TableCell className="py-2 px-3 whitespace-nowrap">
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700 font-mono border border-slate-200/60">
                                                {p.talla || '-'}
                                            </span>
                                        </TableCell>

                                        {/* Precio — $ izquierda fija, número derecha fija */}
                                        <TableCell className="py-2 px-3 whitespace-nowrap">
                                            <div className="flex items-baseline justify-between gap-1">
                                                {p.precio_venta == null ? (
                                                    // Una plantilla creada sin precio no debe tumbar la tabla.
                                                    <span className="text-xs italic text-gray-400">Sin precio</span>
                                                ) : (
                                                    <>
                                                        <span className="text-[11px] text-gray-400 font-normal">$</span>
                                                        <span className="text-sm font-semibold text-gray-700 font-mono tabular-nums">
                                                            {Number(p.precio_venta).toLocaleString('es-CL')}
                                                        </span>
                                                    </>
                                                )}
                                            </div>
                                        </TableCell>

                                        {/* Stock editable */}
                                        <TableCell className="py-1.5 px-3 text-right">
                                            <div className="flex items-center justify-end gap-2.5 h-full">
                                                {canManageStock && <span className="text-[11px] text-gray-300 font-mono tabular-nums">
                                                    {originalQty}
                                                </span>}
                                                {canManageStock ? <Input
                                                    type="number"
                                                    className={`h-8 w-20 px-2.5 text-right font-bold text-sm border bg-white shadow-none focus:border-blue-500 focus:ring-1 focus:ring-blue-100 transition-colors [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none ${isEdited
                                                        ? 'border-amber-400 text-amber-700 bg-amber-50 ring-1 ring-amber-200'
                                                        : 'border-gray-200 text-gray-700 hover:border-gray-300'
                                                        }`}
                                                    value={inputValue}
                                                    aria-label={`Stock disponible de ${p.producto}, SKU ${p.sku}`}
                                                    onChange={(e) => onStockChange(p.sku, e.target.value)}
                                                    onFocus={(e) => e.target.select()}
                                                    min={0}
                                                /> : <span className="inline-block w-20 px-2.5 text-right text-sm font-bold tabular-nums text-gray-700">{originalQty}</span>}
                                            </div>
                                        </TableCell>

                                        {/* Stock Reservado */}
                                        <TableCell className="text-right py-2 px-3 whitespace-nowrap">
                                            {(p.reserved_stock && p.reserved_stock > 0) ? (
                                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold font-mono tabular-nums bg-amber-50 text-amber-700 border border-amber-200/80 shadow-2xs">
                                                    <span className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse" />
                                                    {p.reserved_stock}
                                                </span>
                                            ) : (
                                                <span className="text-[11px] font-mono text-gray-300 tabular-nums">
                                                    0
                                                </span>
                                            )}
                                        </TableCell>

                                        {/* Estado — dot + texto, sin Badge card */}
                                        <TableCell className="py-2 px-3 whitespace-nowrap">
                                            <StockBadge level={level} />
                                        </TableCell>

                                        {/* Acción directa — lápiz, visible en hover */}
                                        <TableCell className="py-2 px-2 text-right">
                                            {canManageStock && <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => onEditProduct(p)}
                                                className="h-8 w-8 opacity-40 transition-opacity hover:bg-blue-50 hover:text-blue-600 group-hover:opacity-100 focus-visible:opacity-100"
                                                title={`Editar ${p.producto}`}
                                                aria-label={`Editar ${p.producto}, SKU ${p.sku}`}
                                            >
                                                <Pencil className="h-3.5 w-3.5 text-blue-500" />
                                            </Button>}
                                        </TableCell>
                                    </TableRow>
                                )
                            })
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* PAGINACIÓN FOOTER */}
            {renderPagination("border-t border-gray-50")}
        </div>
    )
}
