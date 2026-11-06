"use client"

import { Loader2, Pencil } from 'lucide-react'
import { useEffect, useRef } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useVariantName } from '../hooks/useVariantName'

export function VariantNameCell({ genericId, sku, name, canEdit, onSaved }: {
  genericId: number; sku: string; name: string; canEdit: boolean; onSaved: (name: string) => void
}) {
  const editor = useVariantName(genericId, sku, name, canEdit, onSaved)
  const editButton = useRef<HTMLButtonElement>(null)
  const wasEditing = useRef(false)
  useEffect(() => {
    if (wasEditing.current && !editor.editing) editButton.current?.focus()
    wasEditing.current = editor.editing
  }, [editor.editing])
  return <div className="min-w-64 max-w-md">
    {editor.editing ? <div className="space-y-2">
      <Input autoFocus aria-label={`Nombre de variante ${sku}`} aria-invalid={!!editor.error} aria-describedby={editor.error ? `name-error-${sku}` : undefined}
        value={editor.draft} disabled={editor.saving} maxLength={150} className="h-8 text-xs"
        onChange={event => editor.setDraft(event.target.value)}
        onKeyDown={event => {
          if (event.key === 'Enter' && !event.nativeEvent.isComposing) { event.preventDefault(); void editor.save() }
          if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); editor.cancel() }
        }} />
      <div className="flex gap-2">
        <Button type="button" size="sm" className="h-7 text-xs" disabled={editor.saving} onClick={() => void editor.save()}>
          {editor.saving && <Loader2 className="h-3 w-3 animate-spin" />} Guardar nombre
        </Button>
        <Button type="button" size="sm" variant="ghost" className="h-7 text-xs" disabled={editor.saving} onClick={editor.cancel}>Cancelar</Button>
      </div>
      {editor.error && <p role="alert" id={`name-error-${sku}`} className="text-xs text-red-700">{editor.error}</p>}
    </div> : <div className="flex items-start gap-2">
      <span className="flex-1 break-words font-medium text-slate-800">{name || 'Nombre no disponible'}</span>
      {canEdit && name && <Button ref={editButton} type="button" size="icon" variant="ghost" className="h-7 w-7 shrink-0 text-slate-500"
        aria-label={`Editar nombre de ${sku}`} title="Editar nombre de esta variante" onClick={editor.start}><Pencil className="h-3.5 w-3.5" /></Button>}
    </div>}
  </div>
}
