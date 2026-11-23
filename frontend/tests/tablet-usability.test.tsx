/**
 * Suite de Pruebas Automatizadas de Usabilidad Táctil y Accesibilidad (RNF-04 y RNF-02)
 * Entorno de Operación: Tablet Industrial 10" en Planta Textil
 * Resoluciones de referencia: 1024x768 px (4:3) y 1280x800 px (16:10)
 */

import React from 'react'
import {
  WORKSHOP_STAGES,
  STAGE_STYLES,
  STAGE_LABELS,
  ADVANCE_LABELS,
  NEXT_STAGE,
  type WorkshopStage,
} from '../lib/workshop-stages'

export interface TabletViewport {
  name: string
  width: number
  height: number
  aspectRatio: string
  orientation: 'landscape' | 'portrait'
}

export const TABLET_STANDARD_VIEWPORTS: TabletViewport[] = [
  {
    name: 'Tablet 10" WXGA Horizontal (Recomendado Planta)',
    width: 1280,
    height: 800,
    aspectRatio: '16:10',
    orientation: 'landscape',
  },
  {
    name: 'Tablet 10" XGA Horizontal (Estándar Base)',
    width: 1024,
    height: 768,
    aspectRatio: '4:3',
    orientation: 'landscape',
  },
  {
    name: 'Tablet 10" WXGA Vertical',
    width: 800,
    height: 1280,
    aspectRatio: '10:16',
    orientation: 'portrait',
  },
  {
    name: 'Tablet 10" XGA Vertical',
    width: 768,
    height: 1024,
    aspectRatio: '3:4',
    orientation: 'portrait',
  },
]

/**
 * Requisito RNF-04: Todo elemento táctil debe tener un área de interacción mínima de 48x48 px
 * (Directriz WCAG 2.5.5 Nivel AAA y Android Material Touch Target).
 */
export const TOUCH_TARGET_STANDARDS = {
  MIN_TOUCH_TARGET_PX: 48,
  RECOMMENDED_TOUCH_TARGET_PX: 64, // min-h-16 en Tailwind CSS
  CONFIRMATION_WINDOW_MS: 5000,     // Ventana para doble toque de confirmación
}

/**
 * Requisito RNF-02: Contraste cromático para iluminación de planta textil.
 * Ratio mínimo: 4.5:1 (WCAG AA para texto estándar) y 7:1 (WCAG AAA para planta).
 */
export const WORKSHOP_CONTRAST_SPEC = {
  corte: {
    bgClass: 'bg-amber-600',
    textClass: 'text-white',
    hexBg: '#d97706',
    hexText: '#ffffff',
    expectedRatioMin: 4.5,
  },
  confeccion: {
    bgClass: 'bg-blue-700',
    textClass: 'text-white',
    hexBg: '#1d4ed8',
    hexText: '#ffffff',
    expectedRatioMin: 7.0,
  },
  empaque: {
    bgClass: 'bg-violet-700',
    textClass: 'text-white',
    hexBg: '#6d28d9',
    hexText: '#ffffff',
    expectedRatioMin: 7.0,
  },
  terminado: {
    bgClass: 'bg-emerald-700',
    textClass: 'text-white',
    hexBg: '#047857',
    hexText: '#ffffff',
    expectedRatioMin: 7.0,
  },
  confirmacionAlerta: {
    bgClass: 'bg-red-600',
    textClass: 'text-white',
    hexBg: '#dc2626',
    hexText: '#ffffff',
    expectedRatioMin: 4.5,
  },
}

/**
 * Funciones de verificación de interfaz y accesibilidad táctil para integración con test runners.
 */
export function validateTabletViewports(viewports: TabletViewport[] = TABLET_STANDARD_VIEWPORTS): boolean {
  for (const vp of viewports) {
    if (vp.orientation === 'landscape' && vp.width < 1024) return false
    if (vp.orientation === 'portrait' && vp.width < 768) return false
    if (vp.height < 768 && vp.width < 1024) return false
  }
  return true
}

export function validateTouchTargetPixels(tailwindMinHeightClass: string): number {
  const match = tailwindMinHeightClass.match(/min-h-(\d+)/)
  if (!match) return 0
  const remUnits = parseInt(match[1], 10)
  // En Tailwind CSS, 1 unidad = 0.25rem = 4px (con raíz 16px)
  return remUnits * 4
}
