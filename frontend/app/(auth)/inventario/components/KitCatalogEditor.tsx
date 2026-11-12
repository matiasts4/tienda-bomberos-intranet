"use client"
import { useEffect, useState } from 'react'
import { api } from '@/lib/api'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import type { KitDefinition } from '@/lib/kit-plan'

export function KitCatalogEditor({ parentId, value, names, disabled, onChange }: { parentId: number; value: KitDefinition[]; names: KitDefinition[]; disabled: boolean; onChange: (v: KitDefinition[]) => void }) {
  const [search, setSearch] = useState(''), [products, setProducts] = useState<any[]>([])
  const [loading, setLoading] = useState(false), [error, setError] = useState(''), [retry, setRetry] = useState(0)
  useEffect(() => {
    const controller = new AbortController()
    setLoading(true); setError(''); setProducts([])
    const timer = setTimeout(async () => {
      try {
        const result = await api.get(`/api/sku-genericos/admin?estado=publicado&per_page=100&search=${encodeURIComponent(search.trim())}`, { signal: controller.signal })
        if (!controller.signal.aborted) setProducts((result.data ?? []).filter((p: any) => p.id !== parentId && !p.modo_kit))
      } catch { if (!controller.signal.aborted) setError('No se pudieron cargar los productos. Reintenta la búsqueda.') }
      finally { if (!controller.signal.aborted) setLoading(false) }
    }, 200)
    return () => { clearTimeout(timer); controller.abort() }
  }, [parentId, search, retry])
  return <section className="rounded-xl border bg-white p-5 space-y-4" aria-label="Componentes del Kit/Traje">
    <div><h3 className="text-sm font-bold text-slate-800">Componentes del Kit/Traje</h3><p className="text-xs text-slate-500 mt-1">Define prendas obligatorias, opcionales o alternativas. Las alternativas con el mismo grupo permiten elegir una sola prenda.</p></div>
    <Input aria-label="Buscar productos hijos" placeholder="Buscar nombre o SKU del componente…" value={search} onChange={e => setSearch(e.target.value)} disabled={disabled} />
    {loading ? <p role="status" className="text-xs text-slate-500">Buscando productos…</p> : error ? <div role="alert" className="text-sm text-red-700">{error} <Button type="button" variant="outline" onClick={() => setRetry(v => v + 1)}>Reintentar</Button></div> : <div className="max-h-36 overflow-auto divide-y rounded-md border">
      {products.filter(p => !value.some(d => d.sku_generico_id === p.id)).map(p => <button key={p.id} type="button" disabled={disabled} className="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 disabled:opacity-50" onClick={() => onChange([...value, { sku_generico_id: p.id, cantidad_por_kit: 1, regla: 'opcional', grupo_alternativa: '', nombre: p.nombre, sku_base: p.sku_base }])}>{p.nombre} <span className="text-xs text-slate-500">{p.sku_base}</span></button>)}
      {!products.filter(p => !value.some(d => d.sku_generico_id === p.id)).length && <p className="p-3 text-xs text-slate-500">No hay más productos en esta búsqueda. Busca otro nombre o SKU.</p>}
    </div>}
    {value.map((d, index) => { const name = names.find(n => n.sku_generico_id === d.sku_generico_id) || d; const edit = (changes: Partial<KitDefinition>) => onChange(value.map((r, i) => i === index ? { ...r, ...changes } : r)); return <div key={d.sku_generico_id} className="grid grid-cols-1 md:grid-cols-[1fr_95px_140px_160px_auto] gap-3 items-end rounded-lg bg-slate-50 p-3">
      <div><p className="text-sm font-medium">{name.nombre || `Producto #${d.sku_generico_id}`}</p><p className="text-xs font-mono text-slate-500">{name.sku_base}</p></div>
      <label className="text-xs">Unidades por kit<Input type="number" min={1} max={100} aria-label={`Unidades por kit de ${name.nombre}`} value={d.cantidad_por_kit} disabled={disabled} onChange={e => edit({ cantidad_por_kit: Number(e.target.value) })} /></label>
      <label className="text-xs">Regla<select className="w-full h-9 rounded-md border bg-white" aria-label={`Regla de ${name.nombre}`} disabled={disabled} value={d.regla} onChange={e => edit({ regla: e.target.value as KitDefinition['regla'], grupo_alternativa: '' })}><option value="obligatorio">Obligatorio</option><option value="opcional">Opcional</option><option value="alternativa">Alternativa</option></select></label>
      <label className="text-xs">Grupo de alternativas<Input placeholder="Ej.: Prenda superior" aria-label={`Grupo de ${name.nombre}`} disabled={disabled || d.regla !== 'alternativa'} value={d.grupo_alternativa} onChange={e => edit({ grupo_alternativa: e.target.value })} /></label>
      <Button type="button" variant="outline" disabled={disabled} onClick={() => onChange(value.filter((_, i) => i !== index))}>Quitar</Button>
    </div> })}
    {!value.length && <p className="text-sm text-amber-700">Agrega componentes antes de publicar el kit.</p>}
    <p className="text-xs text-slate-500">Guarda el producto para aplicar estos cambios. Los pedidos con composición guardada conservan su definición anterior.</p>
  </section>
}
