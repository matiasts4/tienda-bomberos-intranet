"use client"

import { Cake, MapPin, Clock } from "lucide-react"
import { calcularEdad, formatFecha, formatFechaHora } from "@/lib/utils"

type ComunaRef = { id?: number; nombre?: string | null } | null

/**
 * Forma mínima que necesita este componente. Se declara laxa a propósito para
 * que sirva en cualquier listado de contactos sin obligar a unificar los tipos
 * que hoy tiene cada pantalla.
 */
export type ContactoConMeta = {
    fecha_nacimiento?: string | null
    created_at?: string | null
    updated_at?: string | null
    cuerpos?: { comuna?: ComunaRef }[] | null
    companias?: { cuerpo?: { comuna?: ComunaRef } | null }[] | null
}

/** Un contacto no tiene comuna propia: la hereda del cuerpo al que pertenece. */
function comunasDe(contacto: ContactoConMeta): string[] {
    const nombres = [
        ...(contacto.cuerpos || []).map((c) => c.comuna?.nombre),
        ...(contacto.companias || []).map((c) => c.cuerpo?.comuna?.nombre),
    ].filter((n): n is string => !!n)

    return Array.from(new Set(nombres))
}

export function ContactoMeta({ contacto, className = "" }: { contacto: ContactoConMeta; className?: string }) {
    const edad = calcularEdad(contacto.fecha_nacimiento)
    const nacimiento = formatFecha(contacto.fecha_nacimiento)
    const comunas = comunasDe(contacto)
    const creado = formatFechaHora(contacto.created_at)
    const actualizado = formatFechaHora(contacto.updated_at)

    if (!nacimiento && comunas.length === 0 && !creado) return null

    return (
        <div className={`flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground ${className}`}>
            {nacimiento && (
                <span className="inline-flex items-center gap-1" title="Fecha de nacimiento">
                    <Cake className="h-3 w-3 shrink-0" />
                    {nacimiento}
                    {edad !== null && <span className="text-slate-500">({edad} años)</span>}
                </span>
            )}

            {comunas.length > 0 && (
                <span className="inline-flex items-center gap-1" title="Comuna del cuerpo de bomberos">
                    <MapPin className="h-3 w-3 shrink-0" />
                    {comunas.join(", ")}
                </span>
            )}

            {creado && (
                <span className="inline-flex items-center gap-1" title={`Creado el ${creado}${actualizado ? ` · actualizado el ${actualizado}` : ""}`}>
                    <Clock className="h-3 w-3 shrink-0" />
                    Creado {creado}
                    {actualizado && actualizado !== creado && <span>· act. {actualizado}</span>}
                </span>
            )}
        </div>
    )
}
