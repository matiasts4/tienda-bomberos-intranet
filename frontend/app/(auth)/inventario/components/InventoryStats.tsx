"use client"

import { Package, AlertTriangle, TrendingDown, XCircle } from "lucide-react"

interface InventoryStatsProps {
    totalProducts: number
    outOfStockCount: number
    criticalStockCount: number
    lowStockCount: number
    currentFilter: string | null
    onFilterChange: (filter: string | null) => void
}

export function InventoryStats({
    totalProducts,
    outOfStockCount,
    criticalStockCount,
    lowStockCount,
    currentFilter,
    onFilterChange
}: InventoryStatsProps) {
    const hasCriticalAlert = criticalStockCount > 0
    const hasOutOfStockAlert = outOfStockCount > 0

    const handleToggleFilter = (filter: string) => {
        if (currentFilter === filter) {
            onFilterChange(null) // Quitar filtro
        } else {
            onFilterChange(filter)
        }
    }

    return (
        <div className="grid gap-3 md:grid-cols-4 mb-4">

            {/* Total SKUs — neutro, menos peso visual */}
            <button
                type="button"
                aria-pressed={!currentFilter}
                className={`w-full rounded-xl px-5 py-4 flex items-center gap-4 text-left cursor-pointer transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2
                    ${!currentFilter ? 'bg-white ring-2 ring-blue-500/20 shadow-sm' : 'bg-white opacity-70 hover:opacity-100'}`}
                onClick={() => onFilterChange(null)}
            >
                <div className="flex-shrink-0 w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center">
                    <Package className={`h-4.5 w-4.5 ${!currentFilter ? 'text-blue-600' : 'text-slate-500'}`} />
                </div>
                <div>
                    <p className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total SKUs</p>
                    <p className="text-2xl font-bold text-slate-800 leading-tight">{totalProducts}</p>
                </div>
            </button>

            {/* Agotados — gris medio, peso moderado */}
            <button
                type="button"
                aria-pressed={currentFilter === 'agotados'}
                className={`w-full rounded-xl px-5 py-4 flex items-center gap-4 text-left cursor-pointer transition-all duration-150 group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2
                    ${currentFilter === 'agotados'
                        ? 'bg-slate-200 ring-2 ring-slate-400 shadow-inner'
                        : (hasOutOfStockAlert ? 'bg-slate-50 hover:bg-slate-100 ring-1 ring-slate-200' : 'bg-white hover:bg-slate-50')
                    }`}
                onClick={() => handleToggleFilter('agotados')}
            >
                <div className={`flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center
                    ${hasOutOfStockAlert || currentFilter === 'agotados' ? 'bg-slate-200' : 'bg-slate-100'}`}>
                    <XCircle className={`h-4.5 w-4.5 ${hasOutOfStockAlert || currentFilter === 'agotados' ? 'text-slate-600' : 'text-slate-400'}`} />
                </div>
                <div>
                    <p className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Agotados</p>
                    <p className={`text-2xl font-bold leading-tight ${hasOutOfStockAlert || currentFilter === 'agotados' ? 'text-slate-700' : 'text-slate-300'}`}>
                        {outOfStockCount}
                    </p>
                </div>
            </button>

            {/* Críticos — rojo vibrante, MÁXIMO peso visual cuando hay alertas */}
            <button
                type="button"
                aria-pressed={currentFilter === 'criticos'}
                className={`w-full rounded-xl px-5 py-4 flex items-center gap-4 text-left cursor-pointer transition-all duration-150 group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2
                    ${currentFilter === 'criticos'
                        ? 'bg-red-100 ring-2 ring-red-400 shadow-inner'
                        : (hasCriticalAlert ? 'bg-red-50 ring-1 ring-red-200 hover:bg-red-100 shadow-sm' : 'bg-white hover:bg-red-50')
                    }`}
                onClick={() => handleToggleFilter('criticos')}
            >
                <div className={`flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center
                    ${hasCriticalAlert || currentFilter === 'criticos' ? 'bg-red-100' : 'bg-red-50'}`}>
                    <AlertTriangle className={`h-4.5 w-4.5 ${hasCriticalAlert || currentFilter === 'criticos' ? 'text-red-600' : 'text-red-300'}`} />
                </div>
                <div>
                    <p className={`text-[11px] font-semibold uppercase tracking-wider
                        ${hasCriticalAlert || currentFilter === 'criticos' ? 'text-red-500' : 'text-slate-400'}`}>
                        Críticos
                    </p>
                    <div className="flex items-baseline gap-2">
                        <p className={`text-2xl font-bold leading-tight
                            ${hasCriticalAlert || currentFilter === 'criticos' ? 'text-red-600' : 'text-slate-300'}`}>
                            {criticalStockCount}
                        </p>
                    </div>
                </div>
            </button>

            {/* Stock Bajo — naranja, peso intermedio */}
            <button
                type="button"
                aria-pressed={currentFilter === 'bajo_stock'}
                className={`w-full rounded-xl px-5 py-4 flex items-center gap-4 text-left cursor-pointer transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2
                    ${currentFilter === 'bajo_stock'
                        ? 'bg-orange-100 ring-2 ring-orange-400 shadow-inner'
                        : (lowStockCount > 0 ? 'bg-orange-50 ring-1 ring-orange-100 hover:bg-orange-100' : 'bg-white hover:bg-orange-50')
                    }`}
                onClick={() => handleToggleFilter('bajo_stock')}
            >
                <div className={`flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center
                    ${lowStockCount > 0 || currentFilter === 'bajo_stock' ? 'bg-orange-100' : 'bg-orange-50'}`}>
                    <TrendingDown className={`h-4.5 w-4.5 ${lowStockCount > 0 || currentFilter === 'bajo_stock' ? 'text-orange-500' : 'text-orange-300'}`} />
                </div>
                <div>
                    <p className={`text-[11px] font-semibold uppercase tracking-wider
                        ${lowStockCount > 0 || currentFilter === 'bajo_stock' ? 'text-orange-400' : 'text-slate-400'}`}>
                        Stock Bajo
                    </p>
                    <p className={`text-2xl font-bold leading-tight
                        ${lowStockCount > 0 || currentFilter === 'bajo_stock' ? 'text-orange-500' : 'text-slate-300'}`}>
                        {lowStockCount}
                    </p>
                </div>
            </button>
        </div>
    )
}
