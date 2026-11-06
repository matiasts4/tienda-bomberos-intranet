"use client"

import { useMemo } from "react"

import { useComunas, type Region } from "@/hooks/use-geografia"

type Props = {
    value?: number | null
    onChange: (comunaId: number | null) => void
    /** Si viene, la lista se acota a las comunas de esa región. */
    regionId?: number | null
    regiones?: Region[]
    disabled?: boolean
    id?: string
    className?: string
}

const SELECT_CLASS =
    "flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors " +
    "file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground " +
    "focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"

/**
 * Selector de comuna con las 346 comunas de Chile.
 *
 * Si se le pasa `regionId` muestra solo las de esa región; si no, las muestra
 * todas agrupadas por región para que la lista siga siendo navegable.
 */
export function ComunaSelect({ value, onChange, regionId, regiones, disabled, id, className }: Props) {
    const { comunas, loading } = useComunas()

    const nombreRegion = useMemo(() => {
        const mapa = new Map<number, string>()
        for (const r of regiones ?? []) mapa.set(r.id, r.nombre)
        return mapa
    }, [regiones])

    const filtradas = useMemo(
        () => (regionId ? comunas.filter((c) => c.region_id === regionId) : comunas),
        [comunas, regionId]
    )

    // Sin región elegida agrupamos por región; con región, lista plana.
    const grupos = useMemo(() => {
        if (regionId) return null
        const porRegion = new Map<number, typeof filtradas>()
        for (const c of filtradas) {
            if (!porRegion.has(c.region_id)) porRegion.set(c.region_id, [])
            porRegion.get(c.region_id)!.push(c)
        }
        return Array.from(porRegion.entries()).sort(([a], [b]) =>
            (nombreRegion.get(a) || String(a)).localeCompare(nombreRegion.get(b) || String(b))
        )
    }, [filtradas, regionId, nombreRegion])

    // La comuna guardada puede no estar en el filtro actual (p. ej. si cambió la
    // región del cuerpo). La agregamos igual para no perder el dato en silencio.
    const seleccionadaFueraDeFiltro = useMemo(
        () => (value && !filtradas.some((c) => c.id === value) ? comunas.find((c) => c.id === value) : null),
        [value, filtradas, comunas]
    )

    const placeholder = loading && comunas.length === 0 ? "Cargando comunas..." : "Seleccione Comuna"

    return (
        <select
            id={id}
            className={className ?? SELECT_CLASS}
            disabled={disabled}
            value={value ?? ""}
            onChange={(e) => onChange(e.target.value === "" ? null : Number(e.target.value))}
        >
            <option value="">{placeholder}</option>

            {seleccionadaFueraDeFiltro && (
                <option value={seleccionadaFueraDeFiltro.id}>{seleccionadaFueraDeFiltro.nombre}</option>
            )}

            {grupos
                ? grupos.map(([rid, lista]) => (
                      <optgroup key={rid} label={nombreRegion.get(rid) || `Región ${rid}`}>
                          {lista.map((c) => (
                              <option key={c.id} value={c.id}>
                                  {c.nombre}
                              </option>
                          ))}
                      </optgroup>
                  ))
                : filtradas.map((c) => (
                      <option key={c.id} value={c.id}>
                          {c.nombre}
                      </option>
                  ))}
        </select>
    )
}
