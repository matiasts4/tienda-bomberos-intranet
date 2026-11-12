import type { KitDefinition } from '@/lib/kit-plan'

export type KitSelectionGroup = {
  key: string
  title: string
  rule: KitDefinition['regla']
  colorIndex: number
  definitions: KitDefinition[]
}

// Agrupar para mostrar, sin normalizar nombres ni modificar el snapshot del kit.
export function kitSelectionGroups(catalog: KitDefinition[]): KitSelectionGroup[] {
  const groups = new Map<string, KitSelectionGroup>()
  let alternativeIndex = 0
  for (const definition of catalog) {
    const alternative = definition.regla === 'alternativa'
    const key = JSON.stringify([definition.regla, alternative ? definition.grupo_alternativa : ''])
    if (!groups.has(key)) groups.set(key, {
      key,
      title: alternative ? definition.grupo_alternativa || 'Alternativas' : definition.regla === 'obligatorio' ? 'Prendas obligatorias' : 'Prendas opcionales',
      rule: definition.regla,
      colorIndex: alternative ? alternativeIndex++ : -1,
      definitions: [],
    })
    groups.get(key)!.definitions.push(definition)
  }
  const order = { obligatorio: 0, alternativa: 1, opcional: 2 }
  return Array.from(groups.values()).sort((a, b) => order[a.rule] - order[b.rule])
}
