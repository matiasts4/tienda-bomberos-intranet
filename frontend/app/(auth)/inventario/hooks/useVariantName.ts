"use client"

import { useEffect, useRef, useState } from 'react'
import { api } from '@/lib/api'
import { pteNotice } from '../lib/pte-notice'

export function useVariantName(genericId: number, sku: string, name: string, canEdit: boolean, onSaved: (name: string) => void) {
  const [editing, setEditing] = useState(false)
  const [draft, setDraft] = useState(name)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const session = useRef(0)
  const busy = useRef(false)
  useEffect(() => {
    session.current++
    busy.current = false
    setDraft(name); setEditing(false); setSaving(false); setError('')
    return () => { session.current++ }
  }, [genericId, sku, name])

  const start = () => { if (canEdit && !busy.current) { setDraft(name); setError(''); setEditing(true) } }
  const cancel = () => { if (!busy.current) { setDraft(name); setError(''); setEditing(false) } }
  const save = async () => {
    if (!canEdit || busy.current) return
    const trimmed = draft.trim()
    if (!trimmed || Array.from(trimmed).length > 150) { setError('Ingresa un nombre de entre 1 y 150 caracteres.'); return }
    if (trimmed === name) { cancel(); return }
    const currentSession = session.current
    busy.current = true; setSaving(true); setError('')
    let result: { sku: string; nombre: string }
    try {
      result = await api.patch(`/api/sku-genericos/${genericId}/variants/${encodeURIComponent(sku)}/name`, { nombre: trimmed, nombre_anterior: name })
    } catch (err: unknown) {
      if (currentSession === session.current) {
        const failure = err as { data?: { message?: string }; message?: string }
        setError(failure.data?.message || failure.message || 'No se pudo guardar el nombre. Inténtalo nuevamente.')
      }
      return
    } finally {
      if (currentSession === session.current) { busy.current = false; setSaving(false) }
    }
    if (currentSession !== session.current) return
    setEditing(false)
    try { onSaved(result.nombre) } catch (err) { console.error('No se pudo actualizar la vista de inventario:', err) }
    pteNotice('success', 'Nombre de variante actualizado', `SKU ${sku}`)
  }
  return { editing, draft, setDraft, saving, error, start, cancel, save }
}
