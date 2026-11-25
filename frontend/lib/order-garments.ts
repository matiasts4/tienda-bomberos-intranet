/**
 * Resumen de las prendas de una orden de taller.
 *
 * Una orden puede traer varias prendas distintas (poleras, polares, camisas).
 * El campo `selected_product` de la orden solo guarda la primera, así que la
 * tablet del taller NO debe mostrarlo como si fuera toda la orden: hay que
 * agrupar por prenda y, dentro de cada una, por talla.
 */

export interface GarmentItem {
  product_type?: string | null
  size?: string | null
  quantity?: number | string | null
}

export interface GarmentGroup {
  /** Nombre de la prenda ("Prenda sin especificar" si viene vacío). */
  product: string
  /** Unidades totales de esa prenda. */
  total: number
  /** Unidades por talla de ESA prenda: [talla, cantidad] ("Única" si viene vacía). */
  sizes: Array<[string, number]>
}

export const UNSPECIFIED_GARMENT = "Prenda sin especificar"
export const SINGLE_SIZE = "Única"

/** Orden en que se leen las tallas de ropa; las que no están aquí van al final. */
const SIZE_ORDER = ["XXS", "XS", "S", "M", "L", "XL", "XXL", "XXXL", "3XL", "4XL", "5XL"]

function sizeRank(size: string): number {
  const rank = SIZE_ORDER.indexOf(size.toUpperCase())
  return rank === -1 ? SIZE_ORDER.length : rank
}

/**
 * Agrupa por prenda (en el orden en que se ingresaron en la cotización) y, dentro
 * de cada prenda, suma por talla, con las tallas en su orden natural (S, M, L…).
 */
export function groupItemsByGarment(items: GarmentItem[] | null | undefined): GarmentGroup[] {
  const byGarment = new Map<string, Map<string, number>>()

  for (const item of items ?? []) {
    const product = item.product_type?.trim() || UNSPECIFIED_GARMENT
    const size = item.size?.trim() || SINGLE_SIZE
    const quantity = Number(item.quantity) || 0
    const sizes = byGarment.get(product) ?? new Map<string, number>()
    sizes.set(size, (sizes.get(size) ?? 0) + quantity)
    byGarment.set(product, sizes)
  }

  return Array.from(byGarment, ([product, sizes]) => {
    // sort es estable: las tallas fuera de SIZE_ORDER conservan su orden de aparición.
    const sorted = Array.from(sizes).sort(([a], [b]) => sizeRank(a) - sizeRank(b))
    return { product, total: sorted.reduce((sum, [, qty]) => sum + qty, 0), sizes: sorted }
  })
}

/** Texto corto para listados: el nombre si es una sola prenda, "N prendas" si son varias. */
export function garmentLabel(groups: GarmentGroup[]): string {
  if (groups.length === 0) return UNSPECIFIED_GARMENT
  if (groups.length === 1) return groups[0].product
  return `${groups.length} prendas`
}
