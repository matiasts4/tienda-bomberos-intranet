"use client"

import { createElement } from 'react'
import { gooeyToast } from 'goey-toast'

type NoticeLevel = 'success' | 'warning' | 'error'
const palette = {
  success: { fillColor: '#f0fdf4', borderColor: '#15803d', color: '#14532d' },
  warning: { fillColor: '#fff7ed', borderColor: '#c2410c', color: '#7c2d12' },
  error: { fillColor: '#fef2f2', borderColor: '#b91c1c', color: '#7f1d1d' },
}

/** Aviso informativo de PTE: no confirma operaciones ni altera cambios pendientes. */
export function pteNotice(level: NoticeLevel, title: string, description?: string) {
  const colors = palette[level]
  const duration = level === 'success' ? 4000 : 10000
  return gooeyToast[level](title, {
    id: 'pte-feedback',
    description: description ? createElement('div', {
      style: { color: colors.color, whiteSpace: 'pre-line', overflowWrap: 'anywhere', maxHeight: 'min(40vh, 280px)', overflowY: 'auto', fontSize: 14, lineHeight: 1.5 },
    }, description) : undefined,
    duration, timing: { displayDuration: duration },
    fillColor: colors.fillColor, borderColor: colors.borderColor,
    preset: 'subtle', spring: typeof window === 'undefined' || !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    showTimestamp: false, showProgress: true,
  })
}
