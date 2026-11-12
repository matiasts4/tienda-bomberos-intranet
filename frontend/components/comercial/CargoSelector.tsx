import { useState, useEffect, useCallback } from "react"
import { Label } from "@/components/ui/label"
import { CheckCircle2, Search, X } from "lucide-react"
import { api } from "@/lib/api"
import { cn } from "@/lib/utils"
import { Input } from "@/components/ui/input"

type Cargo = {
    id: number
    nombre: string
}

type Props = {
    value?: number | null
    onChange: (cargo: { id: number; nombre: string }) => void
    label?: string
}

export function CargoSelector({ value, onChange, label = "Cargo" }: Props) {
    const [availableCargos, setAvailableCargos] = useState<Cargo[]>([])
    const [loading, setLoading] = useState(false)
    const [searchTerm, setSearchTerm] = useState("")

    const loadCargos = useCallback(async () => {
        setLoading(true)
        try {
            const res = await api.get<Cargo[]>("/api/catalogos/cargos")
            if (Array.isArray(res)) setAvailableCargos(res)
        } catch (e) {
            console.error("Error loading cargos", e)
        } finally {
            setLoading(false)
        }
    }, [])

    useEffect(() => {
        loadCargos()
    }, [loadCargos])

    const filteredCargos = availableCargos.filter(c => 
        c.nombre.toLowerCase().includes(searchTerm.toLowerCase())
    )

    return (
        <div className="grid gap-1.5 focus-within:ring-0">
            <div className="flex items-center justify-between">
                <Label>{label} <span className="text-xs text-muted-foreground font-normal">(Filtrar y seleccionar)</span></Label>
            </div>
            
            <div className="relative">
                <Search className="absolute left-2.5 top-2.5 size-3.5 text-muted-foreground" />
                <Input
                    placeholder="Escribe el cargo para buscar..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="pl-8 h-9 text-xs mb-2 border-dashed focus-visible:ring-emerald-500"
                />
                {searchTerm && (
                    <button 
                        onClick={() => setSearchTerm("")}
                        className="absolute right-2.5 top-2.5 text-muted-foreground hover:text-foreground"
                    >
                        <X className="size-3.5" />
                    </button>
                )}
            </div>

            <div className="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto p-2 border rounded-md bg-slate-50/30">
                {loading && availableCargos.length === 0 && (
                    <p className="text-xs text-muted-foreground p-2 col-span-2 text-center italic">Cargando cargos...</p>
                )}
                {!loading && filteredCargos.length === 0 && (
                    <p className="text-xs text-muted-foreground p-4 col-span-2 text-center italic">No se encontraron cargos con "{searchTerm}"</p>
                )}
                {filteredCargos.map(cargo => (
                    <button
                        key={cargo.id}
                        type="button"
                        onClick={() => onChange(cargo)}
                        className={cn(
                            "flex items-center gap-2 px-2 py-1.5 text-[11px] rounded-md border transition-all text-left group",
                            value === cargo.id 
                                ? "bg-emerald-50 border-emerald-500 text-emerald-800 font-bold shadow-sm" 
                                : "bg-white hover:bg-emerald-50/50 hover:border-emerald-200 text-gray-600"
                        )}
                    >
                        <div className={cn(
                            "size-3 rounded border flex items-center justify-center shrink-0 transition-colors",
                            value === cargo.id 
                                ? "bg-emerald-500 border-emerald-500" 
                                : "bg-white border-gray-300 group-hover:border-emerald-400"
                        )}>
                            {value === cargo.id && <CheckCircle2 className="size-2 text-white" />}
                        </div>
                        <span className="truncate">{cargo.nombre}</span>
                    </button>
                ))}
            </div>
        </div>
    )
}
