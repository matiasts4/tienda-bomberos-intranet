"use client"

import { useState } from "react"
import { es } from "date-fns/locale"
import { CalendarIcon } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { cn } from "@/lib/utils"
import { fechaISO, formatoFechaDMY, leerFecha } from "@/lib/vencimiento-cotizacion"

// Reemplazo de <input type="date">: el nativo muestra la fecha según el idioma
// del navegador (MM/DD/YYYY en un Chrome en inglés). Este siempre la muestra
// como el resto del sistema (DD-MM-AAAA) y entrega el mismo valor AAAA-MM-DD.
type Props = {
  value: string
  onChange: (valor: string) => void
  min?: string
  max?: string
  required?: boolean
  disabled?: boolean
  id?: string
  className?: string
  placeholder?: string
  /** Selector de mes y año, para fechas lejanas (p. ej. fundación). */
  conAnios?: boolean
}

export function FechaInput({ value, onChange, min, max, required, disabled, id, className, placeholder = "dd-mm-aaaa", conAnios }: Props) {
  const [open, setOpen] = useState(false)
  const seleccion = leerFecha(value) ?? undefined
  const desde = leerFecha(min) ?? undefined
  const hasta = leerFecha(max) ?? undefined
  const deshabilitados = [
    ...(desde ? [{ before: desde }] : []),
    ...(hasta ? [{ after: hasta }] : []),
  ]

  return (
    <div className={cn("relative", className)}>
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            id={id}
            type="button"
            variant="outline"
            disabled={disabled}
            className={cn("h-9 w-full justify-between px-3 font-normal", !value && "text-muted-foreground")}
          >
            {value ? formatoFechaDMY(value) : placeholder}
            <CalendarIcon className="h-4 w-4 opacity-60" />
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-auto p-0" align="start">
          <Calendar
            mode="single"
            locale={es}
            selected={seleccion}
            defaultMonth={seleccion ?? desde}
            disabled={deshabilitados}
            captionLayout={conAnios ? "dropdown" : "label"}
            startMonth={conAnios ? new Date(1850, 0) : undefined}
            endMonth={conAnios ? new Date(new Date().getFullYear() + 10, 11) : undefined}
            onSelect={(fecha) => {
              onChange(fecha ? fechaISO(fecha) : "")
              setOpen(false)
            }}
          />
        </PopoverContent>
      </Popover>
      {required && (
        // Mantiene la validación nativa del formulario ("Completa este campo")
        <input
          tabIndex={-1}
          aria-hidden
          required
          value={value}
          onChange={() => { }}
          className="pointer-events-none absolute inset-x-0 bottom-0 h-px opacity-0"
        />
      )}
    </div>
  )
}
