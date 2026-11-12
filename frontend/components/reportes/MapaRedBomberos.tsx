"use client"

// Mapa de la red de bomberos para el dashboard: regiones coloreadas por
// cobertura (% de Cuerpos con venta) y un punto por Cuerpo, del color de su
// cartera. Relleno = TB le ha vendido; solo borde = sin venta en el período.
// El tamaño crece con sus compañías. Cada región lleva su nombre, para ubicarse
// al navegar o hacer zoom.
//
// Reutiliza la geografía de Prospección (lib/geo-chile). Leaflet necesita
// `window`: la página lo importa con next/dynamic y ssr: false.

import { useEffect, useRef, useState } from "react"
import L from "leaflet"
import "leaflet/dist/leaflet.css"
import { RefreshCw } from "lucide-react"
import { ATRIBUCION_LIMITES, LIMITES_CHILE, cargarGeoChile, centroRegion, nombreRegion, ubicarCuerpo, type GeoChile } from "@/lib/geo-chile"
import { formatCLP } from "@/lib/utils"
import {
  cobertura, colorCartera, etiquetaEjecutivo, porcentaje,
  type CuerpoRed, type EjecutivoRed,
} from "@/lib/reporte-red-bomberos"

// Escala secuencial verde (claro = poca cobertura, oscuro = mucha).
const ESCALA = ["#e5f5e0", "#c7e9c0", "#a1d99b", "#74c476", "#41ab5d", "#238b45", "#005a32"]
const SIN_CUERPOS = "#e4e4e7"

function colorCobertura(pct: number | null): string {
  if (pct === null) return SIN_CUERPOS
  return ESCALA[Math.min(ESCALA.length - 1, Math.floor((pct / 100) * ESCALA.length))]
}

// Halo blanco: el rótulo se lee sobre cualquier verde de la escala.
const ESTILO_ETIQUETA = "position:absolute;transform:translate(-50%,-50%);white-space:nowrap;font-size:10px;font-weight:600;color:#1e293b;"
  + "text-shadow:0 0 2px #fff,0 0 2px #fff,0 0 3px #fff,0 0 3px #fff"

function escaparHtml(texto: string): string {
  return texto.replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]!))
}

type Props = {
  cuerpos: CuerpoRed[]
  ejecutivos: EjecutivoRed[]
  regionId: number | null
  onRegion: (regionId: number | null) => void
}

export default function MapaRedBomberos({ cuerpos, ejecutivos, regionId, onRegion }: Props) {
  const contenedorRef = useRef<HTMLDivElement>(null)
  const mapaRef = useRef<L.Map | null>(null)
  const capaRegionesRef = useRef<L.GeoJSON | null>(null)
  const capaCuerposRef = useRef<L.LayerGroup | null>(null)
  const [geo, setGeo] = useState<GeoChile | null>(null)
  const [errorGeo, setErrorGeo] = useState(false)
  const [sinUbicar, setSinUbicar] = useState(0)

  const actualRef = useRef({ cuerpos, ejecutivos, regionId, onRegion })
  actualRef.current = { cuerpos, ejecutivos, regionId, onRegion }

  // ── Montaje ───────────────────────────────────────────────────────────────
  useEffect(() => {
    if (!contenedorRef.current) return
    const mapa = L.map(contenedorRef.current, { zoomSnap: 0.25, scrollWheelZoom: false, minZoom: 3 })
    mapa.attributionControl.setPrefix(false)
    mapa.attributionControl.addAttribution(ATRIBUCION_LIMITES)
    mapa.fitBounds(LIMITES_CHILE)
    capaCuerposRef.current = L.layerGroup().addTo(mapa)
    // Sobre las regiones y los puntos, pero sin capturar el mouse
    const panelEtiquetas = mapa.createPane("etiquetasRegion")
    panelEtiquetas.style.zIndex = "450"
    panelEtiquetas.style.pointerEvents = "none"
    mapaRef.current = mapa

    const observador = new ResizeObserver(() => mapa.invalidateSize())
    observador.observe(contenedorRef.current)

    let vigente = true
    cargarGeoChile()
      .then(datos => { if (vigente) setGeo(datos) })
      .catch(() => { if (vigente) setErrorGeo(true) })

    return () => {
      vigente = false
      observador.disconnect()
      mapa.remove()
      mapaRef.current = null
      capaRegionesRef.current = null
      capaCuerposRef.current = null
    }
  }, [])

  // ── Regiones ─────────────────────────────────────────────────────────────
  useEffect(() => {
    const mapa = mapaRef.current
    if (!mapa || !geo) return
    const capa = L.geoJSON(geo.regiones, {
      onEachFeature: (feature, layer) => {
        const id = Number(feature.properties.id)
        layer.bindTooltip("", { sticky: true, direction: "top", opacity: 0.95 })
        layer.on({
          click: () => actualRef.current.onRegion(actualRef.current.regionId === id ? null : id),
          mouseover: () => (layer as L.Path).setStyle({ weight: 2.5, color: "#0f172a" }),
          mouseout: () => dibujar(),
        })
      },
    }).addTo(mapa)
    capaRegionesRef.current = capa
    const etiquetas = L.layerGroup(geo.regiones.features.map(feature => L.marker(centroRegion(feature.geometry), {
      pane: "etiquetasRegion",
      interactive: false,
      keyboard: false,
      icon: L.divIcon({
        className: "",
        iconSize: [0, 0],
        html: `<span style="${ESTILO_ETIQUETA}">${escaparHtml(nombreRegion(Number(feature.properties.id)))}</span>`,
      }),
    }))).addTo(mapa)
    dibujar()
    return () => { capa.remove(); etiquetas.remove(); capaRegionesRef.current = null }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [geo])

  useEffect(() => {
    dibujar()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cuerpos, ejecutivos, regionId, geo])

  function dibujar() {
    const { cuerpos, ejecutivos, regionId } = actualRef.current
    const porRegion = new Map<number, CuerpoRed[]>()
    for (const c of cuerpos) porRegion.set(c.region_id, [...(porRegion.get(c.region_id) ?? []), c])

    capaRegionesRef.current?.eachLayer(layer => {
      const feature = (layer as L.GeoJSON & { feature: GeoJSON.Feature<GeoJSON.Geometry, { id: number }> }).feature
      const id = Number(feature.properties.id)
      const lista = porRegion.get(id) ?? []
      const cob = cobertura(lista)
      const pct = lista.length ? porcentaje(cob.cuerposConVenta, cob.cuerpos) : null
      const enfocada = regionId === id
      ;(layer as L.Path).setStyle({
        fillColor: colorCobertura(pct),
        fillOpacity: regionId && !enfocada ? 0.35 : 0.85,
        color: enfocada ? "#0f172a" : "#ffffff",
        weight: enfocada ? 2.5 : 1,
      })
      layer.setTooltipContent(
        `<strong>${nombreRegion(id)}</strong><br>`
        + (lista.length
          ? `${cob.cuerposConVenta}/${cob.cuerpos} cuerpos con venta (${pct}%)<br>`
            + `${cob.companiasConVenta}/${cob.companias} compañías con venta<br>${formatCLP(cob.monto)}`
          : "Sin cuerpos registrados"),
      )
    })

    const capa = capaCuerposRef.current
    if (!capa) return
    capa.clearLayers()
    if (!geo) return

    const nombres = new Map(ejecutivos.map(e => [e.id, etiquetaEjecutivo(e)]))
    const ocupados = new Map<string, number>()
    let perdidos = 0
    // Sin venta primero: los que tienen venta quedan encima.
    const ordenados = [...cuerpos].sort((a, b) => Number(a.ventas > 0) - Number(b.ventas > 0))
    for (const c of ordenados) {
      const punto = ubicarCuerpo(c, geo.comunas)
      if (!punto) { perdidos++; continue }
      const clave = punto.join(",")
      const n = ocupados.get(clave) ?? 0
      ocupados.set(clave, n + 1)
      const angulo = n * 2.4
      const p: [number, number] = n === 0 ? punto : [punto[0] + 0.02 * Math.sin(angulo), punto[1] + 0.025 * Math.cos(angulo)]

      const color = colorCartera(c.ejecutivo_id, ejecutivos)
      const conVenta = c.ventas > 0
      const atenuado = regionId !== null && c.region_id !== regionId
      L.circleMarker(p, {
        radius: 3.5 + Math.min(6, Math.sqrt(c.companias) * 1.4),
        color,
        weight: conVenta ? 1 : 1.75,
        fillColor: conVenta ? color : "#ffffff",
        fillOpacity: atenuado ? 0.25 : conVenta ? 0.9 : 0.6,
        opacity: atenuado ? 0.3 : 1,
      })
        .bindTooltip(
          `<strong>CB ${escaparHtml(c.nombre)}</strong>`
            + (c.comuna ? ` · ${escaparHtml(c.comuna)}` : "")
            + `<br>Cartera: ${escaparHtml(c.ejecutivo_id ? nombres.get(c.ejecutivo_id) ?? "—" : "Sin cartera")}`
            + `<br>Compañías: ${c.companias_con_venta}/${c.companias} con venta`
            + (conVenta ? `<br>${c.ventas} venta${c.ventas === 1 ? "" : "s"} · ${formatCLP(c.monto)}` : "<br>Sin ventas en el período"),
          { direction: "top", offset: [0, -4] },
        )
        .addTo(capa)
    }
    setSinUbicar(perdidos)
  }

  // Cámara: región enfocada o todo Chile
  useEffect(() => {
    const mapa = mapaRef.current
    if (!mapa || !geo) return
    let limites: L.LatLngBounds | null = null
    capaRegionesRef.current?.eachLayer(layer => {
      const feature = (layer as L.GeoJSON & { feature: GeoJSON.Feature<GeoJSON.Geometry, { id: number }> }).feature
      if (Number(feature.properties.id) === regionId) limites = (layer as L.Polygon).getBounds()
    })
    if (limites) mapa.flyToBounds(limites, { duration: 0.6, padding: [16, 16] })
    else mapa.flyToBounds(LIMITES_CHILE, { duration: 0.6, padding: [8, 8] })
  }, [regionId, geo])

  return (
    <div className="relative isolate h-full min-h-[560px] w-full overflow-hidden rounded-lg border bg-slate-50">
      <div ref={contenedorRef} className="absolute inset-0" style={{ background: "transparent" }} />

      {!geo && (
        <div className="pointer-events-none absolute inset-0 z-[1000] flex items-center justify-center bg-white/60 text-xs text-muted-foreground">
          {errorGeo ? "No se pudo cargar el mapa de Chile" : <><RefreshCw className="mr-2 size-3.5 animate-spin" /> Cargando mapa…</>}
        </div>
      )}

      <div className="pointer-events-none absolute right-2 top-2 z-[1000] space-y-1.5 rounded-md border bg-white/95 px-2.5 py-2 text-[10px] text-slate-600 shadow-sm">
        <p className="font-medium text-slate-700">Cobertura regional</p>
        <div className="flex items-center gap-1.5">
          <span>0%</span>
          <span className="flex h-2 w-24 overflow-hidden rounded-full">
            {ESCALA.map(c => <span key={c} className="flex-1" style={{ background: c }} />)}
          </span>
          <span>100%</span>
        </div>
        <div className="flex items-center gap-3 pt-0.5">
          <span className="flex items-center gap-1"><span className="inline-block size-2.5 rounded-full bg-slate-500" /> Con venta</span>
          <span className="flex items-center gap-1"><span className="inline-block size-2.5 rounded-full border-2 border-slate-500 bg-white" /> Sin venta</span>
        </div>
        <p>Tamaño = n° de compañías · Color = cartera</p>
        {sinUbicar > 0 && <p className="text-amber-700">{sinUbicar} cuerpo{sinUbicar === 1 ? "" : "s"} sin comuna ubicable</p>}
      </div>
    </div>
  )
}
