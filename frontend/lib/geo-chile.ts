// Geografía de Chile para el mapa de prospección.
//
// public/geo/chile-regiones.json: límites de las 16 regiones (sin Rapa Nui ni
// Juan Fernández, para que el zoom a Valparaíso no se vaya al Pacífico),
// simplificados con mapshaper. Cada feature trae `id` = regiones.id del sistema.
// public/geo/chile-comunas.json: { "<comuna normalizada>": [lat, lng] }. Es la
// cabecera comunal en las comunas extensas y un punto interior del polígono en
// el resto: sirve para ubicar "más o menos" dónde está cada Cuerpo.
//
// Fuente: geoBoundaries (wmgeolab) sobre datos de la Biblioteca del Congreso
// Nacional de Chile, licencia CC BY 3.0 IGO.

import type { FeatureCollection, MultiPolygon, Polygon } from "geojson"

export const ATRIBUCION_LIMITES =
  'Límites: <a href="https://www.geoboundaries.org" target="_blank" rel="noreferrer">geoBoundaries</a> / BCN (CC BY 3.0 IGO)'

export type RegionChile = { id: number; nombre: string }

/**
 * Regiones de norte a sur. `id` es el regiones.id del sistema, que coincide con
 * el código CUT de la región. Los nombres van aquí y no desde la BD porque en
 * algunas copias de la BD la tabla `regiones` tiene los nombres desfasados.
 */
export const REGIONES_CHILE: RegionChile[] = [
  { id: 15, nombre: "Arica y Parinacota" },
  { id: 1, nombre: "Tarapacá" },
  { id: 2, nombre: "Antofagasta" },
  { id: 3, nombre: "Atacama" },
  { id: 4, nombre: "Coquimbo" },
  { id: 5, nombre: "Valparaíso" },
  { id: 13, nombre: "Metropolitana" },
  { id: 6, nombre: "O'Higgins" },
  { id: 7, nombre: "Maule" },
  { id: 16, nombre: "Ñuble" },
  { id: 8, nombre: "Biobío" },
  { id: 9, nombre: "La Araucanía" },
  { id: 14, nombre: "Los Ríos" },
  { id: 10, nombre: "Los Lagos" },
  { id: 11, nombre: "Aysén" },
  { id: 12, nombre: "Magallanes" },
]

const POSICION_REGION = new Map(REGIONES_CHILE.map((r, i) => [r.id, i]))
const NOMBRE_REGION = new Map(REGIONES_CHILE.map(r => [r.id, r.nombre]))

export function nombreRegion(id: number, respaldo?: string): string {
  return NOMBRE_REGION.get(id) ?? respaldo ?? `Región ${id}`
}

/** Para ordenar regiones de norte a sur; las desconocidas van al final. */
export function posicionRegion(id: number): number {
  return POSICION_REGION.get(id) ?? Number.MAX_SAFE_INTEGER
}

/** Chile continental, [[sur, oeste], [norte, este]]. */
export const LIMITES_CHILE: [[number, number], [number, number]] = [[-55.9, -75.8], [-17.4, -66.4]]

/** Misma normalización con que se generó chile-comunas.json. */
export function normalizarNombre(nombre: string): string {
  return nombre
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, " ")
    .trim()
}

export type GeoRegiones = FeatureCollection<Polygon | MultiPolygon, { id: number; nombre: string }>
export type PuntosComunas = Record<string, [number, number]>

/**
 * Centro del polígono más grande de una región, como [lat, lng], para rotularla.
 * Se ignoran las islas: en Aysén o Magallanes correrían el rótulo hacia el mar.
 */
export function centroRegion(geometria: Polygon | MultiPolygon): [number, number] {
  const anillos = geometria.type === "Polygon" ? [geometria.coordinates[0]] : geometria.coordinates.map(p => p[0])
  let mayor = { area: -1, lat: 0, lng: 0 }
  for (const anillo of anillos) {
    let area = 0, lng = 0, lat = 0
    for (let i = 0; i < anillo.length - 1; i++) {
      const [x0, y0] = anillo[i]
      const [x1, y1] = anillo[i + 1]
      const cruz = x0 * y1 - x1 * y0
      area += cruz
      lng += (x0 + x1) * cruz
      lat += (y0 + y1) * cruz
    }
    if (Math.abs(area) > mayor.area) mayor = { area: Math.abs(area), lat: lat / (3 * area), lng: lng / (3 * area) }
  }
  return [mayor.lat, mayor.lng]
}
export type GeoChile = { regiones: GeoRegiones; comunas: PuntosComunas }

let cargaGeo: Promise<GeoChile> | null = null

/** Descarga (una sola vez por sesión) los límites y puntos de comunas. */
export function cargarGeoChile(): Promise<GeoChile> {
  if (!cargaGeo) {
    cargaGeo = Promise.all([
      fetch("/geo/chile-regiones.json").then(r => r.json() as Promise<GeoRegiones>),
      fetch("/geo/chile-comunas.json").then(r => r.json() as Promise<PuntosComunas>),
    ])
      .then(([regiones, comunas]) => ({ regiones, comunas }))
      .catch(e => {
        cargaGeo = null // permite reintentar
        throw e
      })
  }
  return cargaGeo
}

/**
 * Dónde está "más o menos" un Cuerpo: el punto de su comuna. Si el cuerpo no
 * tiene comuna se prueba con su nombre, porque la mayoría se llama como su
 * ciudad ("Cuerpo de Bomberos de Limache"). Sin coincidencia devuelve null.
 */
export function ubicarCuerpo(
  cuerpo: { nombre: string; comuna?: string | null },
  comunas: PuntosComunas,
): [number, number] | null {
  for (const candidato of [cuerpo.comuna, cuerpo.nombre]) {
    if (!candidato) continue
    const punto = comunas[normalizarNombre(candidato)]
    if (punto) return punto
  }
  return null
}
