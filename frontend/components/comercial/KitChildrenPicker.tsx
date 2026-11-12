"use client"

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import { AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle, AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel } from '@/components/ui/alert-dialog'
import { kitChildren, newKitComposition, setKitChoice, type KitConfig, type KitDefinition } from '@/lib/kit-plan'
import { kitSelectionGroups } from './kit-selection-groups'

const groupColors = [
  'border-sky-200 bg-sky-50 text-sky-950',
  'border-teal-200 bg-teal-50 text-teal-950',
  'border-amber-200 bg-amber-50 text-amber-950',
  'border-rose-200 bg-rose-50 text-rose-950',
  'border-cyan-200 bg-cyan-50 text-cyan-950',
]

export function KitChildrenPicker({ name, quantity, kit, disabled, onClose, onApply, itemNumber }: {
  name: string; quantity: number; kit: KitConfig; disabled: boolean; itemNumber?: number
  onClose: () => void; onApply: (kit: KitConfig) => void
}) {
  // Se trabaja sobre un borrador aislado: cancelar no modifica los diseños existentes.
  const [draft, setDraft] = useState<KitConfig>(() => kit.composiciones.length ? kit : {
    ...kit, composiciones: [newKitComposition(kit.catalogo, crypto.randomUUID(), quantity, 'Combinación 1')],
  })
  const [advanced, setAdvanced] = useState(kit.composiciones.length > 1 || (kit.composiciones.length === 1 && kit.composiciones[0].cantidad !== quantity))
  const [pending, setPending] = useState<null | (() => void)>(null)
  const assigned = draft.composiciones.reduce((sum, c) => sum + c.cantidad, 0)
  const validQuantities = draft.composiciones.every(c => Number.isInteger(c.cantidad) && c.cantidad > 0) && assigned <= quantity
  const children = kitChildren(draft)
  const groups = kitSelectionGroups(draft.catalogo)
  const update = (id: string, changes: object) => setDraft(prev => ({ ...prev, composiciones: prev.composiciones.map(c => c.id === id ? { ...c, ...changes } : c) }))
  const choose = (id: string, definition: KitDefinition, checked: boolean) => {
    if (disabled) return
    const composition = draft.composiciones.find(c => c.id === id)!
    const next = setKitChoice(composition, definition, checked, draft.catalogo)
    const action = () => update(id, { componentes: next.componentes })
    if (composition.componentes.some(c => c.grupos.length && !next.componentes.some(n => n.sku_generico_id === c.sku_generico_id))) setPending(() => action)
    else action()
  }
  return <Dialog open onOpenChange={open => { if (!open && !pending) onClose() }}>
    <DialogContent className="sm:max-w-3xl max-h-[calc(100dvh-2rem)] sm:max-h-[90vh] flex flex-col" onInteractOutside={e => e.preventDefault()}>
      <DialogHeader className="shrink-0 pr-6">
        <DialogTitle>Elegir SKU hijos</DialogTitle>
        <DialogDescription>{name}{itemNumber != null ? ` · Item #${itemNumber}` : ''} · {quantity} {quantity === 1 ? 'kit' : 'kits'}. Las prendas elegidas aparecerán como subítems de este kit para configurar sus tallas, matrices y nombres.</DialogDescription>
      </DialogHeader>
      <div className="space-y-4 overflow-y-auto min-h-0 pr-1">
        {!draft.catalogo.length && <p role="alert" className="text-sm text-amber-800">Este kit no tiene componentes definidos. Configúralos primero en el catálogo.</p>}
        {draft.composiciones.map(c => <section key={c.id} className="space-y-3" aria-label={c.nombre}>
          {advanced && <div className="flex flex-wrap items-end gap-2 rounded-lg bg-slate-50 p-3">
            <label className="flex-1 text-xs font-medium">Combinación<Input aria-label={`Nombre de ${c.nombre}`} value={c.nombre} disabled={disabled} onChange={e => update(c.id, { nombre: e.target.value })} /></label>
            <label className="text-xs font-medium">Cantidad de kits<Input className="w-24" aria-label={`Kits de ${c.nombre}`} type="number" min={1} max={quantity} value={c.cantidad} disabled={disabled} onChange={e => update(c.id, { cantidad: Number(e.target.value) })} /></label>
            <Button variant="ghost" disabled={disabled} onClick={() => {
              const action = () => setDraft(prev => ({ ...prev, composiciones: prev.composiciones.filter(x => x.id !== c.id) }))
              if (c.componentes.some(child => child.grupos.length)) setPending(() => action)
              else action()
            }}>Quitar combinación</Button>
          </div>}
          <div className="space-y-4">{groups.map(group => {
            const selectedCount = group.definitions.filter(d => c.componentes.some(child => child.sku_generico_id === d.sku_generico_id)).length
            const helpId = `kit-group-${c.id}-${group.definitions[0].sku_generico_id}`
            return <fieldset key={group.key} aria-describedby={helpId} className={`min-w-0 rounded-xl border px-3 pb-3 ${group.colorIndex < 0 ? 'border-slate-200 bg-slate-50 text-slate-900' : groupColors[group.colorIndex % groupColors.length]}`}>
              <legend className="max-w-full px-2 text-sm font-semibold break-words">{group.title}</legend>
              <div className="flex flex-wrap items-center justify-between gap-2 px-1 pb-3 pt-1 text-xs">
                <p id={helpId}>{group.rule === 'alternativa' ? 'Elige una opción de este grupo.' : group.rule === 'obligatorio' ? 'Estas prendas están incluidas en cada kit.' : 'Puedes añadir todas, algunas o ninguna.'}</p>
                <output aria-label={`Selección de ${group.title} en ${c.nombre}`} className="font-semibold tabular-nums">{group.rule === 'alternativa' ? `${selectedCount} de 1 elegida` : `${selectedCount} de ${group.definitions.length} ${group.definitions.length === 1 ? 'elegida' : 'elegidas'}`}</output>
              </div>
              <div className="space-y-2">{group.definitions.map(d => {
                const selected = c.componentes.some(child => child.sku_generico_id === d.sku_generico_id)
                const locked = disabled || d.regla === 'obligatorio' || (d.disponible === false && !selected)
                const count = c.cantidad * d.cantidad_por_kit
                return <label key={d.sku_generico_id} className={`flex flex-wrap sm:flex-nowrap items-start gap-x-3 gap-y-1 rounded-lg border p-3 transition-colors focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-indigo-600 ${locked ? 'cursor-default' : 'cursor-pointer hover:border-indigo-400'} ${selected ? 'border-indigo-500 bg-indigo-50/70' : 'border-white bg-white'}`}>
                  <input className="mt-1 h-4 w-4 shrink-0 accent-indigo-600" type="checkbox" aria-label={`${d.nombre} en ${c.nombre}`} checked={selected} disabled={locked} onChange={e => choose(c.id, d, e.target.checked)} />
                  <span className="min-w-0 flex-1"><span className={`block text-sm font-semibold break-words ${selected ? 'text-indigo-950' : 'text-slate-800'}`}>{d.nombre}</span><span className={`mt-0.5 block text-xs break-words ${selected ? 'text-indigo-800' : 'text-slate-600'}`}>SKU: {d.sku_base} · {d.cantidad_por_kit} por kit{d.disponible === false ? ' · No disponible' : ''}</span></span>
                  <span className={`shrink-0 text-xs font-medium tabular-nums ${selected ? 'text-indigo-800' : 'text-slate-700'}`}>{count} {count === 1 ? 'prenda' : 'prendas'}</span>
                </label>
              })}</div>
            </fieldset>
          })}</div>
        </section>)}
        {!advanced ? <Button variant="ghost" className="text-xs text-slate-600" disabled={disabled || !draft.catalogo.length} onClick={() => setAdvanced(true)}>Necesito distintas combinaciones</Button> : <div className="space-y-2">
          <p role="status" className={`text-xs ${validQuantities && assigned === quantity ? 'text-emerald-700' : 'text-amber-800'}`}>{assigned} de {quantity} kits asignados. Reduce una cantidad para añadir otra combinación.</p>
          <Button variant="outline" disabled={disabled || !draft.catalogo.length || !validQuantities || assigned >= quantity} onClick={() => setDraft(prev => ({ ...prev, composiciones: [...prev.composiciones, newKitComposition(prev.catalogo, crypto.randomUUID(), quantity - assigned, `Combinación ${prev.composiciones.length + 1}`)] }))}>Añadir combinación</Button>
        </div>}
      </div>
      <DialogFooter className="shrink-0"><Button variant="outline" onClick={onClose}>Cancelar</Button><Button className="bg-indigo-600 hover:bg-indigo-700" disabled={disabled || !!pending || !children.length || !validQuantities} onClick={() => { if (!disabled && !pending && children.length && validQuantities) onApply(draft) }}>Añadir a personalización</Button></DialogFooter>
      <AlertDialog open={!!pending} onOpenChange={open => { if (!open) setPending(null) }}><AlertDialogContent><AlertDialogHeader><AlertDialogTitle>¿Quitar esta prenda de la selección?</AlertDialogTitle><AlertDialogDescription>Se quitarán sus tallas, matrices y nombres del borrador al aplicar la selección. Las demás prendas conservarán su configuración.</AlertDialogDescription></AlertDialogHeader><AlertDialogFooter><AlertDialogCancel>Conservar</AlertDialogCancel><AlertDialogAction onClick={() => { if (!disabled) pending?.(); setPending(null) }}>Quitar</AlertDialogAction></AlertDialogFooter></AlertDialogContent></AlertDialog>
    </DialogContent>
  </Dialog>
}
