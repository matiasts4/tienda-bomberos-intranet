// Notas de una gestión de prospección. Una calificación llega del backend como
// un resumen de una línea por dato (ResultadoGestion::resumenCalificacion):
//
//   Necesita: Pantalones
//   Cantidad aprox.: 1
//   Plazo: En 1 a 3 meses
//
// Aquí se separa en líneas para mostrarlo como lista en la bitácora. Archivo sin
// imports a propósito: los tests lo transpilan y lo cargan solo.

export type LineaNota = {
  /** Rótulo antes de los dos puntos ("Necesita"), o null si es texto libre. */
  etiqueta: string | null
  valor: string
}

/**
 * Largo máximo de un rótulo ("Cantidad aprox." tiene 15). Más largo es una
 * frase libre que casualmente tiene dos puntos ("Me dijo que…: llamarlo").
 */
const MAX_LARGO_ETIQUETA = 20

export function lineasDeNotas(notas?: string | null): LineaNota[] {
  if (!notas) return []

  return notas
    .split(/\r?\n/)
    .map(linea => linea.trim())
    .filter(Boolean)
    .map(linea => {
      // Solo el primer ": " separa: el valor puede traer horas o citas con dos puntos.
      const corte = linea.indexOf(": ")
      if (corte <= 0 || corte > MAX_LARGO_ETIQUETA) return { etiqueta: null, valor: linea }
      return { etiqueta: linea.slice(0, corte).trim(), valor: linea.slice(corte + 2).trim() }
    })
}
