"use client"

import { useEffect, useMemo, useState } from "react"
import dynamic from "next/dynamic"
import { api } from "@/lib/api"
import { AppHeader } from "@/components/app-header"
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Button } from "@/components/ui/button"
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import { ChevronDown, RefreshCw } from "lucide-react"
import { cn, formatCLP } from "@/lib/utils"
import { REGIONES_CHILE, nombreRegion } from "@/lib/geo-chile"
import {
  COLOR_SIN_CARTERA, cobertura, colorCartera, etiquetaEjecutivo, mesesRecientes, porcentaje, rangoPeriodo, semanasDelMes,
  type Periodo, type ReporteRed,
} from "@/lib/reporte-red-bomberos"

const MapaRedBomberos = dynamic(() => import("@/components/reportes/MapaRedBomberos"), { ssr: false })

const PERIODOS: { value: Periodo; label: string }[] = [
  { value: "todo", label: "Todo el historial" },
  { value: "12m", label: "Últimos 12 meses" },
  { value: "anio", label: "Este año" },
  { value: "mes", label: "Un mes" },
  { value: "semana", label: "Una semana del mes" },
]

const MESES = mesesRecientes(24)

export default function RedBomberosPage() {
  const [periodo, setPeriodo] = useState<Periodo>("todo")
  const [mes, setMes] = useState(MESES[0].value)
  const [semana, setSemana] = useState(1)
  // Carteras marcadas: ids de ejecutivo y "sin". Vacío = todas.
  const [carteras, setCarteras] = useState<Set<string>>(new Set())
  const [regionId, setRegionId] = useState<number | null>(null)
  const [datos, setDatos] = useState<ReporteRed | null>(null)
  const [cargando, setCargando] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function cargar() {
    setCargando(true)
    setError(null)
    try {
      const { desde, hasta } = rangoPeriodo(periodo, mes, semana)
      const params = new URLSearchParams()
      if (desde) params.set("desde", desde)
      if (hasta) params.set("hasta", hasta)
      const qs = params.toString()
      const res = await api.get<ReporteRed>(`/api/reportes/red-bomberos${qs ? `?${qs}` : ""}`)
      setDatos(res)
    } catch (e: any) {
      setError(e?.message || "No se pudo cargar el reporte")
    } finally {
      setCargando(false)
    }
  }

  useEffect(() => { cargar() }, [periodo, mes, semana]) // eslint-disable-line react-hooks/exhaustive-deps

  const ejecutivos = datos?.ejecutivos ?? []
  const cuerpos = useMemo(() => {
    const todos = datos?.cuerpos ?? []
    if (carteras.size === 0) return todos
    return todos.filter(c => carteras.has(c.ejecutivo_id ? String(c.ejecutivo_id) : "sin"))
  }, [datos, carteras])

  function alternarCartera(id: string) {
    setCarteras(prev => {
      const sig = new Set(prev)
      if (sig.has(id)) sig.delete(id)
      else sig.add(id)
      return sig
    })
  }

  const opcionesCartera = [...ejecutivos.map(e => ({ id: String(e.id), etiqueta: etiquetaEjecutivo(e) })), { id: "sin", etiqueta: "Sin cartera" }]
  const textoCartera = carteras.size === 0
    ? "Todas las carteras"
    : carteras.size === 1
      ? opcionesCartera.find(o => carteras.has(o.id))?.etiqueta ?? "1 cartera"
      : `${carteras.size} carteras`

  const enFoco = regionId ? cuerpos.filter(c => c.region_id === regionId) : cuerpos
  const total = cobertura(enFoco)

  const porCartera = useMemo(() => {
    const todos = datos?.cuerpos ?? []
    const filas = ejecutivos.map(e => ({ id: String(e.id), etiqueta: etiquetaEjecutivo(e), color: colorCartera(e.id, ejecutivos), cob: cobertura(todos.filter(c => c.ejecutivo_id === e.id)) }))
    filas.push({ id: "sin", etiqueta: "Sin cartera", color: COLOR_SIN_CARTERA, cob: cobertura(todos.filter(c => !c.ejecutivo_id)) })
    return filas.filter(f => f.cob.cuerpos > 0 || f.id !== "sin")
  }, [datos, ejecutivos])

  const porRegion = useMemo(() => REGIONES_CHILE
    .map(r => ({ ...r, cob: cobertura(cuerpos.filter(c => c.region_id === r.id)) }))
    .filter(r => r.cob.cuerpos > 0), [cuerpos])

  return (
    <div className="flex h-full flex-col">
      <AppHeader />
      <div className="border-b bg-muted/50 px-6 py-3">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem><BreadcrumbLink href="/">Módulos</BreadcrumbLink></BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem><BreadcrumbLink href="/comercial/cotizaciones">Comercial</BreadcrumbLink></BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem><BreadcrumbPage>Red de Bomberos</BreadcrumbPage></BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
      </div>

      <div className="flex-1 space-y-4 overflow-auto p-4 sm:p-6">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold">Red de Bomberos</h1>
            <p className="text-sm text-muted-foreground">
              Presencia de Tienda Bomberos como proveedor: cuerpos y compañías con al menos una venta, por región y cartera.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Select value={periodo} onValueChange={v => setPeriodo(v as Periodo)}>
              <SelectTrigger className="w-[170px]"><SelectValue /></SelectTrigger>
              <SelectContent>{PERIODOS.map(p => <SelectItem key={p.value} value={p.value}>{p.label}</SelectItem>)}</SelectContent>
            </Select>
            {(periodo === "mes" || periodo === "semana") && (
              <Select value={mes} onValueChange={v => { setMes(v); setSemana(1) }}>
                <SelectTrigger className="w-[160px]"><SelectValue /></SelectTrigger>
                <SelectContent>{MESES.map(m => <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>)}</SelectContent>
              </Select>
            )}
            {periodo === "semana" && (
              <Select value={String(semana)} onValueChange={v => setSemana(Number(v))}>
                <SelectTrigger className="w-[170px]"><SelectValue /></SelectTrigger>
                <SelectContent>{semanasDelMes(mes).map(s => <SelectItem key={s.semana} value={String(s.semana)}>{s.label}</SelectItem>)}</SelectContent>
              </Select>
            )}
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" className="w-[210px] justify-between font-normal">
                  <span className="truncate">{textoCartera}</span>
                  <ChevronDown className="h-4 w-4 opacity-50" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-[230px]">
                {opcionesCartera.map(o => (
                  <DropdownMenuCheckboxItem
                    key={o.id}
                    checked={carteras.has(o.id)}
                    onCheckedChange={() => alternarCartera(o.id)}
                    onSelect={e => e.preventDefault()} // el menú queda abierto para marcar varias
                  >
                    {o.etiqueta}
                  </DropdownMenuCheckboxItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem disabled={carteras.size === 0} onSelect={() => setCarteras(new Set())}>Todas las carteras</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
            <Button variant="outline" size="icon" onClick={cargar} disabled={cargando} title="Actualizar">
              <RefreshCw className={cn("h-4 w-4", cargando && "animate-spin")} />
            </Button>
          </div>
        </div>

        {error && <div role="alert" className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}

        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <Kpi titulo="Cuerpos con venta" valor={`${porcentaje(total.cuerposConVenta, total.cuerpos)}%`} detalle={`${total.cuerposConVenta} de ${total.cuerpos}`} />
          <Kpi titulo="Compañías con venta" valor={`${porcentaje(total.companiasConVenta, total.companias)}%`} detalle={`${total.companiasConVenta} de ${total.companias}`} />
          <Kpi titulo="Ventas" valor={String(total.ventas)} detalle="cotizaciones vendidas" />
          <Kpi titulo="Monto vendido" valor={formatCLP(total.monto)} detalle={regionId ? nombreRegion(regionId) : "Todo Chile"} />
        </div>

        <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_420px]">
          <div className="h-[640px]">
            <MapaRedBomberos cuerpos={cuerpos} ejecutivos={ejecutivos} regionId={regionId} onRegion={setRegionId} />
          </div>

          <div className="space-y-4">
            <section className="rounded-lg border">
              <h2 className="border-b px-3 py-2 text-sm font-semibold">Por cartera</h2>
              <Table className="text-xs">
                <TableHeader>
                  <TableRow><TableHead>Ejecutivo</TableHead><TableHead className="text-right">Cuerpos</TableHead><TableHead className="text-right">Con venta</TableHead><TableHead className="text-right">Monto</TableHead></TableRow>
                </TableHeader>
                <TableBody>
                  {porCartera.map(f => (
                    <TableRow key={f.id} className={cn("cursor-pointer", carteras.has(f.id) && "bg-muted")} onClick={() => alternarCartera(f.id)}>
                      <TableCell><span className="flex items-center gap-1.5"><span className="inline-block size-2.5 shrink-0 rounded-full" style={{ background: f.color }} />{f.etiqueta}</span></TableCell>
                      <TableCell className="text-right">{f.cob.cuerpos}</TableCell>
                      <TableCell className="text-right">{f.cob.cuerposConVenta} ({porcentaje(f.cob.cuerposConVenta, f.cob.cuerpos)}%)</TableCell>
                      <TableCell className="text-right">{formatCLP(f.cob.monto)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </section>

            <section className="rounded-lg border">
              <div className="flex items-center justify-between border-b px-3 py-2">
                <h2 className="text-sm font-semibold">Por región</h2>
                {regionId && <Button variant="ghost" size="sm" className="h-6 text-xs" onClick={() => setRegionId(null)}>Todo Chile</Button>}
              </div>
              <div className="max-h-[300px] overflow-auto">
                <Table className="text-xs">
                  <TableHeader className="sticky top-0 bg-background">
                    <TableRow><TableHead>Región</TableHead><TableHead className="text-right">Cuerpos c/venta</TableHead><TableHead className="text-right">Cías c/venta</TableHead></TableRow>
                  </TableHeader>
                  <TableBody>
                    {porRegion.map(r => (
                      <TableRow key={r.id} className={cn("cursor-pointer", regionId === r.id && "bg-muted")} onClick={() => setRegionId(regionId === r.id ? null : r.id)}>
                        <TableCell>{r.nombre}</TableCell>
                        <TableCell className="text-right">{r.cob.cuerposConVenta}/{r.cob.cuerpos} ({porcentaje(r.cob.cuerposConVenta, r.cob.cuerpos)}%)</TableCell>
                        <TableCell className="text-right">{r.cob.companiasConVenta}/{r.cob.companias} ({porcentaje(r.cob.companiasConVenta, r.cob.companias)}%)</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </section>

            <p className="text-[11px] leading-relaxed text-muted-foreground">
              Venta = cotización en Ventas con comprobante de pago cargado (PAGO en verde) que no terminó Desistida ni Anulada (las No Vigentes Finalizadas sí cuentan).
              La cartera se asigna en Clientes &amp; Cuerpos → Cuerpos → Editar.
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}

function Kpi({ titulo, valor, detalle }: { titulo: string; valor: string; detalle: string }) {
  return (
    <div className="rounded-lg border bg-white p-3">
      <p className="text-xs text-muted-foreground">{titulo}</p>
      <p className="text-2xl font-semibold tabular-nums">{valor}</p>
      <p className="text-xs text-muted-foreground">{detalle}</p>
    </div>
  )
}
