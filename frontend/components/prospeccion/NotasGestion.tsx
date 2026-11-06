import { lineasDeNotas } from "@/lib/notas-gestion"
import { cn } from "@/lib/utils"

/**
 * Notas de una gestión en la bitácora. Una calificación (una línea por dato) se
 * muestra como lista para que quien cotiza la lea de un vistazo; una nota libre
 * de una sola línea se sigue mostrando como cita.
 */
export function NotasGestion({ notas, className }: { notas?: string | null; className?: string }) {
  const lineas = lineasDeNotas(notas)
  if (lineas.length === 0) return null

  if (lineas.length === 1 && !lineas[0].etiqueta) {
    return <p className={cn("text-xs text-slate-500 italic line-clamp-2", className)}>"{lineas[0].valor}"</p>
  }

  return (
    <ul className={cn("list-disc pl-4 space-y-0.5 text-xs text-slate-700 marker:text-slate-300", className)}>
      {lineas.map((linea, i) => (
        <li key={i} className="break-words">
          {linea.etiqueta && <span className="font-medium text-slate-500">{linea.etiqueta}: </span>}
          {linea.valor}
        </li>
      ))}
    </ul>
  )
}
