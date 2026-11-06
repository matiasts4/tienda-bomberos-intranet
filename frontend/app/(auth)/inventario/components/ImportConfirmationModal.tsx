import React, { useState, useMemo, useRef } from 'react'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogDescription,
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { AlertCircle, CheckCircle2, PackagePlus, RefreshCcw, ChevronLeft, ChevronRight } from "lucide-react"

interface ImportConfirmationModalProps {
    isOpen: boolean
    onClose: () => void
    onConfirm: () => void
    loading: boolean
    summary: {
        total: number
        nuevos: any[]
        actualizados: any[]
        sinCambios: number
        duplicadosEliminados: number
    } | null
}

const ITEMS_PER_PAGE = 20

export function ImportConfirmationModal({
    isOpen,
    onClose,
    onConfirm,
    loading,
    summary
}: ImportConfirmationModalProps) {
    const [currentPage, setCurrentPage] = useState(1)
    const topScrollRef = useRef<HTMLDivElement>(null)
    const tableScrollRef = useRef<HTMLDivElement>(null)

    // Sincronizar scroll superior -> tabla
    const handleTopScroll = (e: React.UIEvent<HTMLDivElement>) => {
        if (tableScrollRef.current) {
            tableScrollRef.current.scrollLeft = e.currentTarget.scrollLeft
        }
    }

    // Sincronizar tabla -> scroll superior
    const handleTableScroll = (e: React.UIEvent<HTMLDivElement>) => {
        if (topScrollRef.current) {
            topScrollRef.current.scrollLeft = e.currentTarget.scrollLeft
        }
    }

    React.useEffect(() => {
        if (isOpen) {
            setCurrentPage(1)
        }
    }, [isOpen, summary])

    const allItems = useMemo(() => {
        if (!summary) return []
        return [...summary.nuevos, ...summary.actualizados]
    }, [summary])

    const totalPages = Math.ceil(allItems.length / ITEMS_PER_PAGE)

    const paginatedItems = useMemo(() => {
        const start = (currentPage - 1) * ITEMS_PER_PAGE
        return allItems.slice(start, start + ITEMS_PER_PAGE)
    }, [allItems, currentPage])

    if (!summary) return null

    return (
        <>
            <style dangerouslySetInnerHTML={{
                __html: `
                .scrollbar-hover-effect::-webkit-scrollbar-thumb {
                    background-color: rgb(229 231 235 / 0.15) !important;
                    transition: all 0.2s ease-in-out;
                }
                .scrollbar-hover-effect:hover::-webkit-scrollbar-thumb {
                    background-color: rgb(209 213 219 / 1) !important;
                }
            `}} />
            <Dialog open={isOpen} onOpenChange={onClose}>
                <DialogContent className="max-w-[95vw]! max-h-[95vh] flex flex-col p-0 overflow-hidden border-none shadow-2xl">
                    <DialogHeader className="p-8 pb-6 bg-white shrink-0">
                        <div className="flex items-center gap-3 mb-1">
                            <div className="bg-orange-100 p-2 rounded-lg">
                                <AlertCircle className="h-6 w-6 text-orange-600" />
                            </div>
                            <DialogTitle className="text-2xl font-bold tracking-tight text-gray-900">
                                Confirmar Importación Masiva
                            </DialogTitle>
                        </div>
                        <DialogDescription className="text-gray-500 text-sm">
                            Revisa el resumen de cambios antes de procesar el archivo. Los SKUs duplicados han sido consolidados automáticamente.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex-1 overflow-y-auto px-8 pb-8 space-y-8 scrollbar-thin scrollbar-thumb-gray-200">
                        {/* Resumen de impacto - Estilo Tarjetas Premium */}
                        <div className="grid grid-cols-3 gap-4">
                            <div className="bg-blue-50/50 border border-blue-100 p-4 rounded-xl flex items-center gap-4 transition-all hover:bg-blue-50">
                                <div className="bg-blue-500 p-3 rounded-lg shadow-sm">
                                    <PackagePlus className="h-6 w-6 text-white" />
                                </div>
                                <div>
                                    <p className="text-2xl font-black text-blue-700 leading-none mb-1">{summary.nuevos.length}</p>
                                    <p className="text-[11px] text-blue-500 font-bold uppercase tracking-widest">Nuevos</p>
                                </div>
                            </div>

                            <div className="bg-emerald-50/50 border border-emerald-100 p-4 rounded-xl flex items-center gap-4 transition-all hover:bg-emerald-50">
                                <div className="bg-emerald-500 p-3 rounded-lg shadow-sm">
                                    <RefreshCcw className="h-6 w-6 text-white" />
                                </div>
                                <div>
                                    <p className="text-2xl font-black text-emerald-700 leading-none mb-1">{summary.actualizados.length}</p>
                                    <p className="text-[11px] text-emerald-500 font-bold uppercase tracking-widest">Cambios</p>
                                </div>
                            </div>

                            <div className="bg-slate-50/50 border border-slate-200 p-4 rounded-xl flex items-center gap-4 transition-all hover:bg-slate-100">
                                <div className="bg-slate-400 p-3 rounded-lg shadow-sm">
                                    <AlertCircle className="h-6 w-6 text-white" />
                                </div>
                                <div>
                                    <p className="text-2xl font-black text-slate-700 leading-none mb-1">{summary.sinCambios}</p>
                                    <p className="text-[11px] text-slate-500 font-bold uppercase tracking-widest">Al día</p>
                                </div>
                            </div>
                        </div>

                        {summary.duplicadosEliminados > 0 && (
                            <div className="bg-amber-50 border border-amber-200 p-4 rounded-lg flex gap-3 items-start text-amber-900 text-xs animate-in fade-in duration-500">
                                <AlertCircle className="h-5 w-5 text-amber-500 shrink-0 mt-0.5" />
                                <p className="leading-relaxed">
                                    <span className="font-bold underline">Nota de SKUs Duplicados:</span> Se encontraron <strong>{summary.duplicadosEliminados}</strong> SKUs repetidos en el archivo. Solo se mantendrá la última aparición de cada uno para evitar conflictos de stock.
                                </p>
                            </div>
                        )}

                        <div className="space-y-4">
                            <div className="flex items-center justify-between border-b pb-2">
                                <h3 className="text-sm font-bold text-gray-800 uppercase tracking-tight">Vista previa del archivo</h3>
                                <div className="flex items-center gap-3">
                                    <span className="text-[11px] font-medium text-gray-400">
                                        Página <span className="text-gray-700">{currentPage}</span> de {totalPages || 1}
                                    </span>
                                    <div className="flex border rounded-lg overflow-hidden shadow-sm">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8 rounded-none border-r"
                                            onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                                            disabled={currentPage <= 1}
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8 rounded-none"
                                            onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                                            disabled={currentPage >= totalPages}
                                        >
                                            <ChevronRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div className="rounded-xl border border-gray-100 overflow-hidden shadow-sm bg-white">
                                {/* Barra de scroll superior (Dummy) */}
                                <div
                                    ref={topScrollRef}
                                    onScroll={handleTopScroll}
                                    className="overflow-x-auto scrollbar-thin scrollbar-hover-effect bg-gray-50/30 border-b transition-opacity duration-300"
                                >
                                    <div className="h-[2px]" style={{ width: '1350px' }} />
                                </div>

                                <div
                                    ref={tableScrollRef}
                                    onScroll={handleTableScroll}
                                    className="overflow-x-auto scrollbar-thin scrollbar-hover-effect transition-opacity duration-300"
                                >
                                    <table className="w-full text-[12px] border-collapse min-w-[1350px]">
                                        <thead>
                                            <tr className="bg-gray-50/50 border-b border-gray-100">
                                                <th className="px-4 py-3 font-semibold text-gray-600 text-left w-[130px]">SKU</th>
                                                <th className="px-4 py-3 font-semibold text-gray-600 text-left">Producto</th>
                                                <th className="px-2 py-3 font-semibold text-gray-600 text-center w-[150px]">Color</th>
                                                <th className="px-2 py-3 font-semibold text-gray-600 text-center w-[80px]">Talla</th>
                                                <th className="px-3 py-3 font-semibold text-gray-600 text-center w-[110px]">P. Anterior</th>
                                                <th className="px-3 py-3 font-semibold text-gray-600 text-center w-[110px]">P. Nuevo</th>
                                                <th className="px-3 py-3 font-semibold text-gray-600 text-center w-[120px]">Stock Anterior</th>
                                                <th className="px-3 py-3 font-semibold text-gray-600 text-center w-[100px]">Nuevo</th>
                                                <th className="px-3 py-3 font-semibold text-gray-600 text-center w-[100px]">Diferencia</th>
                                                <th className="px-3 py-3 font-semibold text-gray-600 text-center w-[100px]">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {paginatedItems.map((item) => {
                                                const esNuevo = summary.nuevos.some(n => n.sku === item.sku)
                                                return (
                                                    <tr key={item.sku} className={`hover:bg-gray-50/80 transition-colors border-b border-gray-50 last:border-0 group`}>
                                                        <td className="px-4 py-3 font-mono text-gray-500 align-middle">
                                                            <span className="bg-gray-100 px-1.5 py-0.5 rounded text-[11px] font-bold text-gray-700">{item.sku}</span>
                                                        </td>
                                                        <td className="px-4 py-3 text-gray-700 font-medium leading-tight align-middle text-pretty">
                                                            {item.nombre || '---'}
                                                        </td>
                                                        <td className="px-2 py-3 text-center align-middle">
                                                            <span className="text-[10px] text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full border border-gray-100 whitespace-nowrap block truncate max-w-[110px]" title={item.color}>
                                                                {item.color}
                                                            </span>
                                                        </td>
                                                        <td className="px-2 py-3 text-center align-middle">
                                                            <Badge variant="outline" className="text-[10px] font-bold border-gray-200 uppercase whitespace-nowrap">{item.talla}</Badge>
                                                        </td>
                                                        <td className="px-3 py-3 text-center align-middle">
                                                            <span className="text-gray-400 font-medium">${(item.precio_anterior ?? 0).toLocaleString('es-CL')}</span>
                                                        </td>
                                                        <td className="px-3 py-3 text-center align-middle">
                                                            <span className={`font-bold ${(item.precio_venta && item.precio_venta !== item.precio_anterior) ? 'text-blue-600' : 'text-gray-700'}`}>
                                                                ${(item.precio_venta ?? 0).toLocaleString('es-CL')}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-3 text-center align-middle bg-slate-50/30">
                                                            <span className="text-gray-500 font-medium">{item.stock_anterior ?? 0}</span>
                                                        </td>
                                                        <td className="px-3 py-3 text-center align-middle bg-blue-50/20">
                                                            <span className="text-blue-700 font-bold text-sm tracking-tight">{item.cantidad}</span>
                                                        </td>
                                                        <td className="px-3 py-3 text-center align-middle">
                                                            {(() => {
                                                                const diff = item.cantidad - (item.stock_anterior ?? 0);
                                                                if (diff === 0) return <span className="text-gray-300">-</span>;
                                                                return (
                                                                    <span className={`font-bold text-xs ${diff > 0 ? 'text-emerald-600' : 'text-rose-500'}`}>
                                                                        {diff > 0 ? '+' : ''}{diff}
                                                                    </span>
                                                                );
                                                            })()}
                                                        </td>
                                                        <td className="px-3 py-3 text-center align-middle">
                                                            {esNuevo ? (
                                                                <Badge className="bg-blue-500 hover:bg-blue-600 text-white text-[9px] font-bold border-none h-5 px-2">NUEVO</Badge>
                                                            ) : (
                                                                <Badge className="bg-emerald-500 hover:bg-emerald-600 text-white text-[9px] font-bold border-none h-5 px-2 uppercase tracking-tight">Cambio</Badge>
                                                            )}
                                                        </td>
                                                    </tr>
                                                )
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                                {allItems.length === 0 && (
                                    <div className="p-12 text-center text-gray-400 flex flex-col items-center gap-2">
                                        <AlertCircle className="h-8 w-8 opacity-20" />
                                        <p className="italic">No hay datos procesables en la vista previa</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="bg-orange-50/30 border border-orange-100 p-5 rounded-xl flex gap-4 text-orange-900/80 text-xs leading-relaxed">
                            <AlertCircle className="h-6 w-6 text-orange-400 shrink-0 mt-0.5" />
                            <div>
                                <p className="font-bold text-orange-700 mb-2 uppercase tracking-wide">Consideraciones de seguridad</p>
                                <ul className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1.5">
                                    <li className="flex gap-2">
                                        <span className="text-orange-400 font-bold">•</span>
                                        Atributos automáticos heredados del SKU.
                                    </li>
                                    <li className="flex gap-2">
                                        <span className="text-orange-400 font-bold">•</span>
                                        Registro masivo con ID de lote único.
                                    </li>
                                    <li className="flex gap-2">
                                        <span className="text-orange-400 font-bold">•</span>
                                        Verificar stock final antes de confirmar.
                                    </li>
                                    <li className="flex gap-2 font-semibold text-orange-800">
                                        <span className="text-orange-600 font-black">•</span>
                                        Operación sensible e irreversible.
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <DialogFooter className="px-8 py-6 border-t bg-gray-50 shrink-0 gap-3">
                        <Button
                            variant="link"
                            onClick={onClose}
                            disabled={loading}
                            className="text-gray-400 hover:text-gray-600 text-xs decoration-dashed font-medium"
                        >
                            Cancelar operación
                        </Button>
                        <div className="flex-1" />
                        <Button
                            onClick={onConfirm}
                            disabled={loading}
                            className="bg-orange-600 hover:bg-orange-700 text-white font-bold h-11 px-8 rounded-xl shadow-lg shadow-orange-200 transition-all hover:scale-[1.02] active:scale-[0.98]"
                        >
                            {loading ? 'Procesando cambios...' : 'Confirmar Importación'}
                            {!loading && <CheckCircle2 className="ml-2 h-5 w-5 opacity-80" />}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    )
}
