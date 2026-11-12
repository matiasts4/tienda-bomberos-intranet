export interface ReservationProduct {
    sku: string
    producto: string
    cantidad_fisica: number
    talla: string
    color: string
    sku_generico_id?: number | null
    nombre_generico?: string | null
}

export interface ReservationItem extends ReservationProduct {
    requestedQuantity: number
}

// Editing a cart row replaces its quantity; do not subtract that same row again.
export function cartQuantityError(item: ReservationItem): string | null {
    if (!Number.isSafeInteger(item.requestedQuantity) || item.requestedQuantity < 1) return 'Ingresa una cantidad entera mayor que 0.'
    const available = Number(item.cantidad_fisica)
    if (!Number.isFinite(available) || available < 1) return 'Este producto no tiene stock disponible. Quítalo de la lista para continuar.'
    if (item.requestedQuantity > available) return `Puedes reservar hasta ${available} ${available === 1 ? 'unidad' : 'unidades'}.`
    return null
}

export function remainingQuantity(product: ReservationProduct, cart: ReservationItem[]) {
    return Math.max(0, Number(product.cantidad_fisica) - (cart.find(item => item.sku === product.sku)?.requestedQuantity ?? 0))
}

export function selectionError(item: ReservationItem, cart: ReservationItem[]): string | null {
    if (!Number.isSafeInteger(item.requestedQuantity) || item.requestedQuantity < 1) return 'Ingresa una cantidad entera mayor que 0.'
    const available = remainingQuantity(item, cart)
    if (item.requestedQuantity > available) return `Puedes agregar hasta ${available} unidades más.`
    return null
}

// Validate the entire selection first: no partial additions or duplicate SKU rows.
export function mergeReservationSelection(cart: ReservationItem[], selected: ReservationItem[]) {
    const unique = new Map(selected.map(item => [item.sku, item]))
    for (const item of unique.values()) {
        const error = selectionError(item, cart)
        if (error) return { items: null, error: `${item.producto}: ${error}` }
    }
    const merged = new Map(cart.map(item => [item.sku, item]))
    for (const item of unique.values()) {
        merged.set(item.sku, { ...item, requestedQuantity: (merged.get(item.sku)?.requestedQuantity ?? 0) + item.requestedQuantity })
    }
    return { items: [...merged.values()], error: null }
}
