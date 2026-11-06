// Edición de stock en Inventario PTE ("Editar" y edición masiva).
// Se envía a PUT /api/productos/{sku} solo lo que el usuario cambió y, junto con
// la cantidad, el stock que veía en pantalla (cantidad_anterior). Si alguien
// reservó o movió ese SKU entremedio, el backend responde 409 en vez de pisarlo.

export interface ProductEditValues {
  nombre: string
  precio_venta: number | string
  cantidad: number | string
  stock_critico: number | string
}

export type ProductUpdatePayload = Partial<Record<'nombre', string>> &
  Partial<Record<'precio_venta' | 'cantidad' | 'cantidad_anterior' | 'stock_critico', number>>

export function validateProductEdit(values: ProductEditValues): Partial<Record<'precio_venta' | 'stock_critico', string>> {
  const errors: Partial<Record<'precio_venta' | 'stock_critico', string>> = {}
  for (const key of ['precio_venta', 'stock_critico'] as const) {
    const value = String(values[key]).trim()
    if (!value) errors[key] = key === 'precio_venta' ? 'Ingresa el precio de venta. Puedes escribir 0.' : 'Ingresa el mínimo de stock. Puedes escribir 0.'
    else if (!Number.isSafeInteger(Number(value)) || Number(value) < 0) errors[key] = 'Ingresa un número entero igual o mayor que 0.'
  }
  return errors
}

export function buildProductUpdatePayload(original: ProductEditValues, edited: ProductEditValues): ProductUpdatePayload {
  const payload: ProductUpdatePayload = {}

  if (edited.nombre.trim() !== original.nombre.trim()) payload.nombre = edited.nombre.trim()
  // Un SKU sin precio llega como 0: si no se tocó, no se envía y no queda en $0.
  if (Number(edited.precio_venta) !== Number(original.precio_venta)) payload.precio_venta = Number(edited.precio_venta)
  if (Number(edited.stock_critico) !== Number(original.stock_critico)) payload.stock_critico = Number(edited.stock_critico)
  if (Number(edited.cantidad) !== Number(original.cantidad)) {
    payload.cantidad = Number(edited.cantidad)
    payload.cantidad_anterior = Number(original.cantidad)
  }

  return payload
}

export interface BulkSaveOutcome {
  saved: string[]
  // SKU -> stock disponible actual informado por el backend
  conflicts: Record<string, number>
  failed: Record<string, string>
}

type FailedRequest = { status?: number; message?: string; data?: { cantidad_actual?: number } }

export function summarizeBulkResults(skus: string[], results: PromiseSettledResult<unknown>[]): BulkSaveOutcome {
  const outcome: BulkSaveOutcome = { saved: [], conflicts: {}, failed: {} }

  results.forEach((result, index) => {
    const sku = skus[index]
    if (result.status === 'fulfilled') {
      outcome.saved.push(sku)
      return
    }

    const error = (result.reason ?? {}) as FailedRequest
    if (error.status === 409 && typeof error.data?.cantidad_actual === 'number') {
      outcome.conflicts[sku] = error.data.cantidad_actual
    } else {
      outcome.failed[sku] = error.message || 'Error desconocido'
    }
  })

  return outcome
}

export function bulkSaveMessage(outcome: BulkSaveOutcome): string {
  const conflictSkus = Object.keys(outcome.conflicts)
  const failedSkus = Object.keys(outcome.failed)
  const total = outcome.saved.length + conflictSkus.length + failedSkus.length

  if (conflictSkus.length === 0 && failedSkus.length === 0) return 'Inventario actualizado correctamente.'

  const lines = [`Se guardaron ${outcome.saved.length} de ${total} productos.`]
  if (conflictSkus.length > 0) {
    lines.push(
      '',
      'El stock cambió mientras editabas (alguien reservó o movió stock) en:',
      ...conflictSkus.map(sku => `• ${sku}: ahora hay ${outcome.conflicts[sku]} disponibles`),
      'Se cargó el valor actual. Revisa esos productos y vuelve a guardar.'
    )
  }
  if (failedSkus.length > 0) {
    lines.push('', 'No se pudieron guardar:', ...failedSkus.map(sku => `• ${sku}: ${outcome.failed[sku]}`))
  }

  return lines.join('\n')
}
