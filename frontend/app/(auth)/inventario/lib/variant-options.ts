type Option = { id: number; nombre: string; orden?: number | null }
const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' })
const normalize = (value: string) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toUpperCase()

export function searchColors<T extends Option>(colors: T[], search: string): T[] {
  const words = normalize(search).split(/\s+/).filter(Boolean)
  return colors.filter(color => words.every(word => normalize(color.nombre).includes(word)))
    .sort((a, b) => collator.compare(a.nombre, b.nombre) || a.id - b.id)
}

function sizeKey(size: Option): [number, number] {
  const name = normalize(size.nombre).replace(/\s+/g, '')
  const small = name.match(/^(X*)S$/)
  if (small) return [0, -small[1].length]
  const numberedSmall = name.match(/^(\d+)XS$/)
  if (numberedSmall) return [0, -Number(numberedSmall[1])]
  if (name === 'M') return [0, 1]
  const large = name.match(/^(X*)L$/)
  if (large) return [0, 2 + large[1].length]
  const numberedLarge = name.match(/^(\d+)XL$/)
  if (numberedLarge) return [0, 2 + Number(numberedLarge[1])]
  if (/^\d+(?:[.,]\d+)?$/.test(name)) return [1, Number(name.replace(',', '.'))]
  if (['UNICA', 'UNICO', 'U', 'ONESIZE', 'GENERICO'].includes(name)) return [3, 0]
  if (name.startsWith('AJUSTABLE') || name === 'ESPECIAL') return [4, 0]
  return [2, size.orden ?? Number.MAX_SAFE_INTEGER]
}

/** Orden comercial, sin mutar el catálogo compartido ni depender del ID de talla. */
export function sortSizes<T extends Option>(sizes: T[]): T[] {
  return [...sizes].sort((a, b) => {
    const ka = sizeKey(a), kb = sizeKey(b)
    return ka[0] - kb[0] || ka[1] - kb[1] || collator.compare(a.nombre, b.nombre) || a.id - b.id
  })
}
