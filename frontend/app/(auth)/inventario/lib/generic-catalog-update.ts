import type { KitDefinition } from '@/lib/kit-plan'
type EditableGeneric = {
  nombre: string
  precio_venta: number | null
  permite_personalizar?: boolean
  permite_cotizar?: boolean
  modo_kit?: boolean
  componentes_kit?: KitDefinition[]
}

/** Evita reenviar precios al cambiar solo un permiso del producto base. */
export function genericCatalogChanges(original: EditableGeneric, edited: EditableGeneric): Partial<EditableGeneric> {
  const changes: Partial<EditableGeneric> = {}
  if (original.nombre !== edited.nombre) changes.nombre = edited.nombre
  if ((original.precio_venta === null ? null : Number(original.precio_venta)) !== (edited.precio_venta === null ? null : Number(edited.precio_venta))) changes.precio_venta = edited.precio_venta
  if ((original.permite_personalizar ?? true) !== (edited.permite_personalizar ?? true)) changes.permite_personalizar = edited.permite_personalizar ?? true
  if ((original.permite_cotizar ?? true) !== (edited.permite_cotizar ?? true)) changes.permite_cotizar = edited.permite_cotizar ?? true
  if ((original.modo_kit ?? false) !== (edited.modo_kit ?? false)) changes.modo_kit = edited.modo_kit ?? false
  if (JSON.stringify(original.componentes_kit ?? []) !== JSON.stringify(edited.componentes_kit ?? [])) changes.componentes_kit = edited.componentes_kit ?? []
  return changes
}
