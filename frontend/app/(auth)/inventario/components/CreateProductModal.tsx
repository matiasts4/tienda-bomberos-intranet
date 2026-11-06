import React, { useState, useMemo } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Plus, X, Loader2, Zap, Search, Check, ChevronDown, ChevronUp } from "lucide-react"
import { pteNotice } from "../lib/pte-notice"

interface CreateProductModalProps {
  isOpen: boolean;
  onClose: () => void;
  onBulkSubmit: (baseData: any, colores: number[], tallas: number[], generos: number[]) => void;
  creating: boolean;
  formData: any;
  onChange: (e: any) => void;
  catalogos: any;
  familiasFiltradas: any[];
  subfamiliasFiltradas: any[];
  formatosFiltrados: any[];
  tiposFiltrados: any[];
}

export function CreateProductModal({
  isOpen, onClose, onBulkSubmit, creating, formData, onChange,
  catalogos, familiasFiltradas, subfamiliasFiltradas, formatosFiltrados, tiposFiltrados
}: CreateProductModalProps) {
  
  const [selectedColors, setSelectedColors] = useState<number[]>([])
  const [selectedTallas, setSelectedTallas] = useState<number[]>([])
  const [selectedGeneros, setSelectedGeneros] = useState<number[]>([0])
  const [colorSearch, setColorSearch] = useState("")
  const [showAllColors, setShowAllColors] = useState(false)

  const filteredColors = useMemo(() => {
    if (!catalogos.colores) return [];
    if (!colorSearch) return catalogos.colores;
    return catalogos.colores.filter((c: any) => 
      c.nombre.toLowerCase().includes(colorSearch.toLowerCase())
    );
  }, [catalogos.colores, colorSearch]);

  const sortedTallas = useMemo(() => {
    if (!catalogos.tallas) return [];
    const order = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', '4XL', 'GENERICO', 'Unica'];
    return [...catalogos.tallas].sort((a: any, b: any) => {
      const indexA = order.indexOf(a.nombre);
      const indexB = order.indexOf(b.nombre);
      if (indexA !== -1 && indexB !== -1) return indexA - indexB;
      if (indexA !== -1) return -1;
      if (indexB !== -1) return 1;
      return a.nombre.localeCompare(b.nombre);
    });
  }, [catalogos.tallas]);

  if (!isOpen) return null;

  const variantCount = selectedColors.length * selectedTallas.length * selectedGeneros.length;
  const displayedColors = showAllColors ? filteredColors : filteredColors.slice(0, 18);

  // Helpers
  const toggleColor = (id: number) => setSelectedColors(p => p.includes(id) ? p.filter(c => c !== id) : [...p, id]);
  const toggleTalla = (id: number) => setSelectedTallas(p => p.includes(id) ? p.filter(t => t !== id) : [...p, id]);
  const toggleGenero = (id: number) => setSelectedGeneros(p => p.includes(id) ? p.filter(g => g !== id) : [...p, id]);
  const selectAllColors = () => {
    const ids = filteredColors.map((c: any) => c.id);
    const all = ids.every((id: number) => selectedColors.includes(id));
    setSelectedColors(all ? selectedColors.filter((id: number) => !ids.includes(id)) : [...new Set([...selectedColors, ...ids])]);
  };
  const selectAllTallas = () => {
    const ids = sortedTallas.map((t: any) => t.id) || [];
    setSelectedTallas(ids.every((id: number) => selectedTallas.includes(id)) ? [] : ids);
  };

  const handleBulkSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (creating) return;
    if (selectedColors.length === 0) { pteNotice('warning', 'Selecciona al menos un color'); return; }
    if (selectedTallas.length === 0) { pteNotice('warning', 'Selecciona al menos una talla'); return; }
    if (selectedGeneros.length === 0) { pteNotice('warning', 'Selecciona al menos un género'); return; }
    for (const [val, name] of [[formData.familia_id,'Familia'],[formData.subfamilia_id,'Subfamilia'],[formData.familia_formato_id,'Formato']]) {
      if (Number(val) === -1) { pteNotice('warning', `Selecciona: ${name}`); return; }
    }
    onBulkSubmit(formData, selectedColors, selectedTallas, selectedGeneros);
  };

  // Shared select component
  const Sel = ({ label, name, value, options, span }: { label: string; name: string; value: any; options: any[]; span?: number }) => (
    <div className={span ? `col-span-${span}` : ''}>
      <label htmlFor={`create-${name}`} className="text-[10px] font-bold text-gray-500 block mb-1">{label}</label>
      <select id={`create-${name}`} name={name} value={value} onChange={onChange} disabled={creating}
        className="w-full border border-gray-200 px-2 py-1.5 rounded-md text-xs bg-white focus:ring-1 focus:ring-blue-300 focus:border-blue-300 transition-all">
        {options[0]?.id !== undefined && !options.some((o: any) => o.id === -1) && <option value="-1">Seleccione</option>}
        {options.map((o: any) => <option key={o.id} value={o.id}>{o.nombre}</option>)}
      </select>
    </div>
  );

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 overflow-y-auto">
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-4xl border border-gray-100 relative overflow-hidden">
        
        {/* LOADING OVERLAY */}
        {creating && (
          <div className="absolute inset-0 z-[60] bg-white/90 backdrop-blur-sm flex flex-col items-center justify-center gap-4">
            <div className="relative">
              <div className="h-14 w-14 border-[3px] border-gray-200 border-t-blue-600 rounded-full animate-spin" />
              <Zap className="absolute inset-0 m-auto h-5 w-5 text-amber-500" />
            </div>
            <div className="text-center">
              <p className="text-sm font-bold text-gray-800">{`Creando producto base y ${variantCount} variantes...`}</p>
              <p className="text-xs text-gray-400 mt-1">Sincronizando con base de datos</p>
            </div>
          </div>
        )}

        {/* HEADER */}
        <div className="flex justify-between items-center px-5 py-3 border-b border-gray-100 bg-gray-50/50">
          <h2 className="text-sm font-bold text-gray-800 flex items-center gap-2">
            <Plus className="h-4 w-4 text-blue-600" /> Crear producto base
          </h2>
          <div className="flex items-center gap-4">
            <button type="button" aria-label="Cerrar creación de producto base" onClick={onClose} disabled={creating} className="text-gray-400 hover:text-red-500 transition-colors p-1"><X className="h-6 w-6" /></button>
          </div>
        </div>

        <form onSubmit={handleBulkSubmit} className="p-5 space-y-4 max-h-[82vh] overflow-y-auto">
           
          {/* BANNER MASIVO */}
          <div className="bg-gradient-to-r from-amber-50 via-orange-50 to-yellow-50 p-3 rounded-lg border border-amber-200/80">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <div className="h-7 w-7 rounded-full bg-amber-500 flex items-center justify-center shadow-sm">
                  <Zap className="h-3.5 w-3.5 text-white" />
                </div>
                <div>
                  <p className="text-xs font-bold text-amber-800">Producto base para Catálogo</p>
                  <p className="text-xs text-amber-800">
                    {selectedGeneros.length} gén. × {selectedColors.length} col. × {selectedTallas.length} tall.
                  </p>
                </div>
              </div>
              <div className={`px-4 py-1.5 rounded-full text-sm font-bold transition-all ${
                variantCount > 0 ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-600'
              }`}>
                {variantCount}
              </div>
            </div>
          </div>

          <p className="text-sm text-slate-600">Se creará una base por género con las variantes iniciales seleccionadas, sin stock. Para añadir colores o tallas a un producto existente, usa Catálogo.</p>

          {/* NOMBRE Y PRECIO */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div className="md:col-span-2">
              <label htmlFor="create-nombre" className="text-[10px] font-bold text-gray-500 block mb-1">
                Nombre del producto base <span className="font-normal text-gray-500">— Referencia en Catálogo</span>
              </label>
              <Input id="create-nombre" required name="nombre" value={formData.nombre} onChange={onChange} disabled={creating}
                className="h-9 text-sm" placeholder="Ej: Chaqueta USAR, Polera Polo MC"/>
            </div>
            <div>
              <label htmlFor="create-precio" className="text-[10px] font-bold text-gray-500 block mb-1">Precio Venta</label>
              <Input id="create-precio" required name="precio_venta" type="text" value={formData.precio_venta} onChange={onChange} disabled={creating}
                className="h-9 text-sm" placeholder="0"/>
            </div>
          </div>

          {/* STOCK CRITICO */}
          <div className="bg-blue-50/50 p-3 rounded-lg border border-blue-100/60 flex items-center gap-3">
            <label htmlFor="create-stock-critico" className="text-[10px] font-bold text-blue-700 whitespace-nowrap">Stock Crítico por variante</label>
            <Input id="create-stock-critico" type="number" required min="0" step="1" name="stock_critico" value={formData.stock_critico} onChange={onChange} disabled={creating} className="bg-white h-8 text-sm w-24"/>
          </div>

          {/* ATRIBUTOS BASE */}
          <div className="space-y-2">
            <h3 className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Atributos del Producto</h3>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-2 bg-gray-50 p-3 rounded-lg border border-gray-100">
              <Sel label="Unidad" name="unidad_negocio_id" value={formData.unidad_negocio_id} options={catalogos.unidades || []} />
              {Number(formData.grp_familia_id) === 1 && (
                <Sel label="Origen" name="origen_id" value={formData.origen_id} options={catalogos.origenes || []} />
              )}
              <Sel label="Grupo" name="grp_familia_id" value={formData.grp_familia_id} options={catalogos.grupos || []} />
              <Sel label="Familia" name="familia_id" value={formData.familia_id} options={familiasFiltradas} />
              <Sel label="Subfamilia" name="subfamilia_id" value={formData.subfamilia_id} options={subfamiliasFiltradas} />
              <Sel label="Tipo" name="familia_tipo_id" value={formData.familia_tipo_id} options={tiposFiltrados} />
              <Sel label="Formato" name="familia_formato_id" value={formData.familia_formato_id} options={formatosFiltrados} />
            </div>
          </div>

          {/* === MODO MASIVO: Multi-selects === */}
          <div className="space-y-3">
            {/* GÉNERO MULTI */}
            <div className="bg-purple-50/60 p-3 rounded-lg border border-purple-100/60">
              <label className="text-[10px] font-bold text-purple-600 uppercase tracking-wider block mb-2">Géneros</label>
              <div className="flex flex-wrap gap-1.5">
                {catalogos.generos?.map((g: any) => (
                  <button key={g.id} type="button" disabled={creating} aria-pressed={selectedGeneros.includes(g.id)} onClick={() => toggleGenero(g.id)}
                    className={`px-3 py-1.5 rounded-md text-[11px] font-semibold transition-all border ${
                      selectedGeneros.includes(g.id)
                        ? 'bg-purple-600 text-white border-purple-600 shadow-sm shadow-purple-600/20'
                        : 'bg-white text-gray-500 border-gray-200 hover:border-purple-300 hover:text-purple-600'
                    }`}>
                    {selectedGeneros.includes(g.id) && <Check className="h-3 w-3 inline mr-1 -mt-0.5" />}
                    {g.nombre}
                  </button>
                ))}
              </div>
            </div>

            {/* COLOR MULTI */}
            <div className="bg-emerald-50/60 p-3 rounded-lg border border-emerald-100/60">
              <div className="flex items-center justify-between mb-2">
                <label className="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">
                  Colores <span className="text-emerald-400 font-normal lowercase">({selectedColors.length} seleccionados)</span>
                </label>
                <button type="button" onClick={selectAllColors}
                  className="text-[10px] font-bold text-emerald-600 hover:text-emerald-800 transition-colors">
                  {filteredColors.length > 0 && filteredColors.every((c: any) => selectedColors.includes(c.id)) ? '✕ Quitar todos' : '✓ Todos'}
                </button>
              </div>
              <div className="relative mb-2">
                <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3 w-3 text-gray-400" />
                <input type="text" placeholder="Buscar color..." value={colorSearch} onChange={e => setColorSearch(e.target.value)}
                  className="w-full pl-7 pr-3 py-1.5 text-xs border border-emerald-200/80 rounded-md bg-white focus:outline-none focus:ring-1 focus:ring-emerald-300" />
              </div>
              <div className="grid grid-cols-3 sm:grid-cols-4 gap-1 max-h-[200px] overflow-y-auto pr-1 scrollbar-thin">
                {displayedColors.map((c: any) => (
                  <button key={c.id} type="button" disabled={creating} aria-pressed={selectedColors.includes(c.id)} onClick={() => toggleColor(c.id)}
                    className={`flex items-center gap-1 px-2 py-1.5 rounded text-[10px] font-medium transition-all text-left truncate ${
                      selectedColors.includes(c.id)
                        ? 'bg-emerald-600 text-white'
                        : 'bg-white text-gray-600 border border-gray-150 hover:border-emerald-300'
                    }`}>
                    {selectedColors.includes(c.id) && <Check className="h-2.5 w-2.5 shrink-0" />}
                    <span className="truncate">{c.nombre}</span>
                  </button>
                ))}
              </div>
              {filteredColors.length > 18 && (
                <button type="button" onClick={() => setShowAllColors(!showAllColors)}
                  className="flex items-center gap-1 mt-2 text-[10px] font-semibold text-emerald-600 hover:text-emerald-800 transition-colors">
                  {showAllColors ? <><ChevronUp className="h-3 w-3" /> Mostrar menos</> : <><ChevronDown className="h-3 w-3" /> Ver {filteredColors.length - 18} más</>}
                </button>
              )}
            </div>

            {/* TALLA MULTI */}
            <div className="bg-sky-50/60 p-3 rounded-lg border border-sky-100/60">
              <div className="flex items-center justify-between mb-2">
                <label className="text-[10px] font-bold text-sky-600 uppercase tracking-wider">
                  Tallas <span className="text-sky-400 font-normal lowercase">({selectedTallas.length} seleccionadas)</span>
                </label>
                <button type="button" onClick={selectAllTallas}
                  className="text-[10px] font-bold text-sky-600 hover:text-sky-800 transition-colors">
                  {sortedTallas.length > 0 && sortedTallas.every((t: any) => selectedTallas.includes(t.id)) ? '✕ Quitar todas' : '✓ Todas'}
                </button>
              </div>
              <div className="flex flex-wrap gap-1.5">
                {sortedTallas.map((t: any) => (
                  <button key={t.id} type="button" disabled={creating} aria-pressed={selectedTallas.includes(t.id)} onClick={() => toggleTalla(t.id)}
                    className={`px-3 py-1.5 rounded-md text-[11px] font-bold transition-all border min-w-[48px] text-center ${
                      selectedTallas.includes(t.id)
                        ? 'bg-sky-600 text-white border-sky-600 shadow-sm shadow-sky-600/20'
                        : 'bg-white text-gray-500 border-gray-200 hover:border-sky-300 hover:text-sky-600'
                    }`}>
                    {t.nombre}
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* FOOTER */}
          <div className="flex flex-wrap items-center justify-end gap-2 pt-3 border-t border-gray-100">
            <span className="mr-auto text-xs text-slate-600">{selectedGeneros.length} bases · {variantCount} variantes iniciales</span>
            <Button type="button" variant="outline" onClick={onClose} disabled={creating}>Cancelar</Button>
            <Button type="submit" disabled={creating || variantCount === 0} className="bg-blue-600 hover:bg-blue-700">
              {creating ? <Loader2 className="animate-spin h-4 w-4 mr-2"/> : <Plus className="h-4 w-4 mr-2"/>}
              Crear producto base
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}
