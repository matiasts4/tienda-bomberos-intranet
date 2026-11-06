"use client"

import { useState } from "react"
import { List } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"

// Lista única de giros comunes para todos los formularios de cliente.
export const GIROS_COMUNES = [
    "Cuerpo de Bomberos",
    "Institución pública / Municipalidad",
    "Comercio minorista",
    "Comercio mayorista",
    "Construcción",
    "Transporte y logística",
    "Agricultura y forestal",
    "Minería",
    "Industria manufacturera",
    "Alimentos y gastronomía",
    "Hotelería y turismo",
    "Educación",
    "Salud",
    "Servicios financieros y seguros",
    "Servicios profesionales / Consultoría",
    "Informática y tecnología",
    "Inmobiliaria",
    "Seguridad y prevención de riesgos",
    "Organización sin fines de lucro",
]

const SIN_GIRO = "__none__"
const OTRO = "__otro__"

/**
 * Selector de giro con lista de giros comunes. Si el valor guardado no está en
 * la lista (datos antiguos escritos a mano) o se elige "Otro", cambia a un
 * campo de texto libre para no perder información.
 */
export function GiroSelect({
    value,
    onChange,
    placeholder = "Selecciona un giro",
}: {
    value: string
    onChange: (v: string) => void
    placeholder?: string
}) {
    const isCustomValue = !!value && !GIROS_COMUNES.includes(value)
    const [customMode, setCustomMode] = useState(isCustomValue)

    if (customMode || isCustomValue) {
        return (
            <div className="flex gap-1">
                <Input
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="Escribe el giro"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    title="Elegir de la lista"
                    onClick={() => {
                        onChange("")
                        setCustomMode(false)
                    }}
                >
                    <List className="h-4 w-4" />
                </Button>
            </div>
        )
    }

    return (
        <Select
            value={value || undefined}
            onValueChange={(v) => {
                if (v === OTRO) {
                    onChange("")
                    setCustomMode(true)
                } else if (v === SIN_GIRO) {
                    onChange("")
                } else {
                    onChange(v)
                }
            }}
        >
            <SelectTrigger>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent className="max-h-[280px]">
                {GIROS_COMUNES.map((g) => (
                    <SelectItem key={g} value={g}>
                        {g}
                    </SelectItem>
                ))}
                <SelectItem value={OTRO}>Otro (escribir)…</SelectItem>
                <SelectItem value={SIN_GIRO}>— Sin giro —</SelectItem>
            </SelectContent>
        </Select>
    )
}
