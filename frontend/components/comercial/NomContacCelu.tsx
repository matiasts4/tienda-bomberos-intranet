"use client"

// Nombre con que los ejecutivos guardan un contacto en su celular:
// "CB La Ligua - 5 - Toto Pavo - TEN1 - V2". Lo arma el backend con los datos
// del sistema; aquí solo se muestra y se copia. Un "?" indica que falta el cargo
// o la cartera (código de vendedor) del Cuerpo.

import { useState } from "react"
import { Check, Copy, Smartphone } from "lucide-react"
import { cn } from "@/lib/utils"

export function NomContacCelu({ valor, className }: { valor?: string | null; className?: string }) {
    const [copiado, setCopiado] = useState(false)
    if (!valor) return null
    const incompleto = valor.split(" - ").includes("?")

    async function copiar(e: React.MouseEvent) {
        e.stopPropagation()
        try {
            await navigator.clipboard.writeText(valor!)
            setCopiado(true)
            setTimeout(() => setCopiado(false), 1500)
        } catch {
            // Sin permiso de portapapeles: el texto igual queda visible para copiarlo a mano
        }
    }

    return (
        <button
            type="button"
            onClick={copiar}
            title={incompleto
                ? "Falta el cargo o la cartera del Cuerpo (código V1–V4). Clic para copiar igual."
                : "Nombre para guardar en el celular. Clic para copiar."}
            className={cn(
                "inline-flex max-w-full items-center gap-1 rounded border px-1.5 py-0.5 font-mono text-[10px] leading-tight transition-colors",
                incompleto
                    ? "border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100"
                    : "border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100",
                className,
            )}
        >
            <Smartphone className="h-3 w-3 shrink-0" />
            <span className="truncate">{valor}</span>
            {copiado ? <Check className="h-3 w-3 shrink-0 text-emerald-600" /> : <Copy className="h-3 w-3 shrink-0 opacity-60" />}
        </button>
    )
}
