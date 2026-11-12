import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export const formatPhone = (val: string) => {
    let digits = val.replace(/\D/g, '')
    if (digits.startsWith('56')) digits = digits.slice(2)
    if (digits.length > 9) digits = digits.slice(0, 9)
    if (digits.length === 0) return ""
    const prefix = "+569 "
    const body = digits.startsWith('9') ? digits.slice(1) : digits
    return prefix + body.slice(0, 8)
}

export const validateEmail = (email: string) => {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

// Formatea un RUT chileno con puntos y guión a medida que se escribe (ej: 12.345.678-9)
export const formatRut = (val: string) => {
    let clean = val.replace(/[^0-9kK]/g, '').toUpperCase()
    if (clean.length > 9) clean = clean.slice(0, 9)
    if (clean.length <= 1) return clean
    const body = clean.slice(0, -1)
    const dv = clean.slice(-1)
    const bodyFormatted = body.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
    return `${bodyFormatted}-${dv}`
}

// Valida un RUT chileno real según el algoritmo oficial de Módulo 11.
// Acepta el RUT con o sin puntos/guión. Mismo algoritmo que
// App\Rules\RutChileno en el backend — si cambia uno, cambiar el otro.
export const validateRut = (rut: string): boolean => {
    if (!rut || typeof rut !== 'string') return false
    const clean = rut.replace(/[^0-9kK]/g, '').toUpperCase()
    if (clean.length < 7 || clean.length > 9) return false

    const body = clean.slice(0, -1)
    const dv = clean.slice(-1)
    if (!/^\d+$/.test(body)) return false

    const numBody = parseInt(body, 10)
    // Rango de RUTs válidos en Chile (personas naturales y jurídicas)
    if (isNaN(numBody) || numBody < 100000 || numBody > 99999999) return false

    let sum = 0
    let multiplier = 2
    for (let i = body.length - 1; i >= 0; i--) {
        sum += parseInt(body[i], 10) * multiplier
        multiplier = multiplier === 7 ? 2 : multiplier + 1
    }
    const remainder = 11 - (sum % 11)
    const computedDv = remainder === 11 ? '0' : remainder === 10 ? 'K' : String(remainder)
    return computedDv === dv
}

export interface MatchingCuerpoResult<T, C> {
    cuerpo: T
    matchedCompania?: C
}

export function normalizeSearchText(text: string): string {
    return text
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-z0-9\s]/g, " ")
        .replace(/\s+/g, " ")
        .trim()
}

const STOP_WORDS = new Set([
    "cuerpo", "de", "del", "la", "el", "los", "las", "bomberos", "bombero", 
    "cb", "cia", "compania", "brigada", "portal", "distribuidor", "marketplace"
])

export function extractCoreTokens(text: string): string[] {
    const normalized = normalizeSearchText(text)
    return normalized.split(" ").filter(w => w.length > 1 && !STOP_WORDS.has(w))
}

export function findBestMatchingCuerpo<
    T extends { id: string; nombre: string; companias?: C[] },
    C extends { id: string; nombre: string; numero: number }
>(
    cuerpos: T[],
    marketplaceName?: string,
    slug?: string,
    userCompanyUnit?: string | null
): MatchingCuerpoResult<T, C> | null {
    if (!cuerpos || cuerpos.length === 0) return null

    // 1. Si la unidad del usuario coincide directamente con una compañía específica
    if (userCompanyUnit && !userCompanyUnit.toLowerCase().includes("administraci")) {
        const userNorm = normalizeSearchText(userCompanyUnit)
        for (const c of cuerpos) {
            if (c.companias) {
                for (const comp of c.companias) {
                    const compNorm = normalizeSearchText(comp.nombre)
                    if (userNorm === compNorm || (userNorm.length > 5 && (userNorm.includes(compNorm) || compNorm.includes(userNorm)))) {
                        return { cuerpo: c, matchedCompania: comp }
                    }
                }
            }
        }
    }

    // 2. Buscar por nombre del Marketplace, slug y unidad del usuario
    const searchSources = [marketplaceName, slug, userCompanyUnit].filter(Boolean) as string[]

    for (const src of searchSources) {
        const coreTokens = extractCoreTokens(src)
        if (coreTokens.length === 0) continue
        const corePhrase = coreTokens.join(" ")

        // Coincidencia exacta de frase clave en cuerpo.nombre
        for (const c of cuerpos) {
            const cCoreTokens = extractCoreTokens(c.nombre)
            const cCorePhrase = cCoreTokens.join(" ")

            if (cCorePhrase === corePhrase || cCorePhrase.includes(corePhrase) || corePhrase.includes(cCorePhrase)) {
                let matchedComp: C | undefined
                if (userCompanyUnit && c.companias && !userCompanyUnit.toLowerCase().includes("administraci")) {
                    const userNorm = normalizeSearchText(userCompanyUnit)
                    matchedComp = c.companias.find(comp => {
                        const compNorm = normalizeSearchText(comp.nombre)
                        return userNorm.includes(compNorm) || compNorm.includes(userNorm) ||
                               (userNorm.includes(String(comp.numero)) && userNorm.includes(comp.nombre.slice(0, 5)))
                    })
                }
                return { cuerpo: c, matchedCompania: matchedComp }
            }
        }

        // Coincidencia por intersección de tokens significativos
        let bestMatch: T | null = null
        let maxIntersection = 0

        for (const c of cuerpos) {
            const cCoreTokens = new Set(extractCoreTokens(c.nombre))
            const intersection = coreTokens.filter(t => cCoreTokens.has(t)).length
            if (intersection > maxIntersection && intersection >= Math.min(coreTokens.length, cCoreTokens.size)) {
                maxIntersection = intersection
                bestMatch = c
            }
        }

        if (bestMatch) {
            let matchedComp: C | undefined
            if (userCompanyUnit && bestMatch.companias && !userCompanyUnit.toLowerCase().includes("administraci")) {
                const userNorm = normalizeSearchText(userCompanyUnit)
                matchedComp = bestMatch.companias.find(comp => {
                    const compNorm = normalizeSearchText(comp.nombre)
                    return userNorm.includes(compNorm) || compNorm.includes(userNorm)
                })
            }
            return { cuerpo: bestMatch, matchedCompania: matchedComp }
        }
    }

    return null
}

// Formatea números en pesos chilenos ($ 12.345)
export const formatCLP = (amount: number | string | null | undefined): string => {
    const num = typeof amount === 'number' ? amount : parseFloat(String(amount || 0))
    if (isNaN(num)) return '$0'
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(num)
}

export function getMarketplaceImageUrl(url: string | null | undefined): string | null {
  if (!url) return null
  const apiBase = (process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000').replace(/\/$/, '')
  
  if (url.includes('marketplace/')) {
    const relativePath = 'marketplace/' + url.split('marketplace/')[1]
    return `${apiBase}/api/storage-proxy/${relativePath}`
  }

  if (url.includes('logos/')) {
    const relativePath = 'logos/' + url.split('logos/')[1]
    return `${apiBase}/api/storage-proxy/${relativePath}`
  }
  
  if (url.startsWith('http')) return url
  return `${apiBase}/storage/${url.replace(/^\/?(storage\/)?/, '')}`
}

/**
 * Edad cumplida a partir de una fecha "YYYY-MM-DD".
 *
 * Se parsea a mano en vez de usar new Date(iso): una fecha sin hora se
 * interpreta como UTC y en Chile (UTC-3/-4) retrocede un día, lo que en un
 * cumpleaños desplaza la edad en un año.
 */
export const calcularEdad = (fecha?: string | null): number | null => {
  const partes = String(fecha ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (!partes) return null

  const [, anio, mes, dia] = partes
  const nacimiento = new Date(Number(anio), Number(mes) - 1, Number(dia))
  if (Number.isNaN(nacimiento.getTime())) return null

  const hoy = new Date()
  const cumpleDeEsteAnio = new Date(hoy.getFullYear(), nacimiento.getMonth(), nacimiento.getDate())
  let edad = hoy.getFullYear() - nacimiento.getFullYear()
  if (hoy < cumpleDeEsteAnio) edad--

  return edad >= 0 && edad < 130 ? edad : null
}

/** "1994-11-22" -> "22-11-1994". Sin zona horaria de por medio. */
export const formatFecha = (fecha?: string | null): string => {
  const partes = String(fecha ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/)
  return partes ? `${partes[3]}-${partes[2]}-${partes[1]}` : ''
}

/** created_at / updated_at vienen en UTC: se muestran en hora local. */
export const formatFechaHora = (iso?: string | null): string => {
  if (!iso) return ''
  const fecha = new Date(iso)
  if (Number.isNaN(fecha.getTime())) return ''

  return fecha.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
