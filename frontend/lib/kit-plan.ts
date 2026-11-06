export type KitDefinition = {
  sku_generico_id: number; cantidad_por_kit: number; regla: 'obligatorio' | 'opcional' | 'alternativa'; grupo_alternativa: string
  nombre?: string; sku_base?: string; permite_personalizar?: boolean; disponible?: boolean
  variantes?: { sku: string; talla: string; color: string; genero: string }[]
}
export type KitChild = { sku_generico_id: number; grupos: any[] }
export type KitComposition = { id: string; nombre: string; cantidad: number; componentes: KitChild[] }
export type KitConfig = { kit_version: 1; catalogo: KitDefinition[]; composiciones: KitComposition[] }
export function initialChildGroups(permits = true) {
  return [{ nombre_grupo: 'Grupo A', tallas: [], variables: [], matrices: permits
    ? [{ posicion: 'Frente', tecnica: 'Bordado', tipo_contenido: 'logo', valor_fijo: '', comentarios: '' }]
    : [] }]
}
export function initializeKitChildren(kit: KitConfig): KitConfig {
  return { ...kit, composiciones: kit.composiciones.map(c => ({ ...c, componentes: c.componentes.map(child => ({
    ...child, grupos: child.grupos.length ? child.grupos : initialChildGroups(kit.catalogo.find(d => d.sku_generico_id === child.sku_generico_id)?.permite_personalizar !== false),
  })) })) }
}
export function kitChildren(kit: KitConfig) {
  return kit.composiciones.flatMap(composition => composition.componentes.map(child => {
    const definition = kit.catalogo.find(d => d.sku_generico_id === child.sku_generico_id)!
    return { ...definition, ...child, key: `${composition.id}:${child.sku_generico_id}`, composicion_id: composition.id, composicion_nombre: composition.nombre, cantidad: composition.cantidad * definition.cantidad_por_kit }
  }))
}
export function newKitComposition(catalog: KitDefinition[], id: string, quantity: number, name: string): KitComposition {
  return { id, nombre: name, cantidad: quantity, componentes: catalog.filter(d => d.regla === 'obligatorio').map(d => ({ sku_generico_id: d.sku_generico_id, grupos: [] })) }
}
export function setKitChoice(composition: KitComposition, definition: KitDefinition, enabled: boolean, catalog: KitDefinition[]): KitComposition {
  const componentes = composition.componentes.filter(c => c.sku_generico_id !== definition.sku_generico_id && !(enabled && definition.regla === 'alternativa' && catalog.some(d => d.sku_generico_id === c.sku_generico_id && d.regla === 'alternativa' && d.grupo_alternativa === definition.grupo_alternativa)))
  if (enabled) componentes.push(composition.componentes.find(c => c.sku_generico_id === definition.sku_generico_id) || { sku_generico_id: definition.sku_generico_id, grupos: [] })
  return { ...composition, componentes }
}
