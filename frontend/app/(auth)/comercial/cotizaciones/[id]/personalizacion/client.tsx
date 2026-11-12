"use client"

import { useState, useEffect, useRef } from "react"
import { AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle, AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel } from "@/components/ui/alert-dialog"
import { api } from "@/lib/api"
import { toast } from "sonner"
import { useRouter, useParams } from "next/navigation"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Save, Loader2, Plus, Trash2, ArrowLeft, CheckCircle, CheckCircle2, XCircle, UploadCloud, Info, Package } from "lucide-react"
import { KitChildrenPicker } from '@/components/comercial/KitChildrenPicker'
import { kitChildren, initializeKitChildren, type KitConfig } from '@/lib/kit-plan'

export function PersonalizacionClient() {
    const router = useRouter()
    const params = useParams() as { id: string }
    const cotizacionId = params?.id

    const [cotizacion, setCotizacion] = useState<any>(null)
    const [selectedItemId, setSelectedItemId] = useState<number | null>(null)
    const [grupoActivoIndex, setGrupoActivoIndex] = useState<number>(0)
    const [activeStep, setActiveStep] = useState('tallas')
    const [loadingConfig, setLoadingConfig] = useState(false)
    const [saving, setSaving] = useState(false)
    const [uploading, setUploading] = useState(false)
    const uploadingRef = useRef(false)
    const [tallasOptions, setTallasOptions] = useState<string[]>([])
    const [coloresOptions, setColoresOptions] = useState<string[]>([])
    const [generosOptions, setGenerosOptions] = useState<string[]>([])

    // El payload estructurado según API del ítem actual
    const [configuracion, setConfiguracion] = useState<any[]>([])
    // Almacén de configuraciones cargadas/modificadas en cascada para guardarse juntas
    const [configsLocales, setConfigsLocales] = useState<Record<number, any[]>>({})
    const [kitsLocales, setKitsLocales] = useState<Record<number, KitConfig>>({})
    const [selectedChild, setSelectedChild] = useState<{ compositionId: string; childId: number } | null>(null)
    const [kitPickerId, setKitPickerId] = useState<number | null>(null)
    const [loadError, setLoadError] = useState<string | null>(null)
    const [feedback, setFeedback] = useState<string | null>(null)
    const [dirty, setDirty] = useState(false)
    const [leaveOpen, setLeaveOpen] = useState(false)
    const [removePersonalizationOpen, setRemovePersonalizationOpen] = useState(false)
    const [optionsByItem, setOptionsByItem] = useState<Record<number, { generos: string[]; colores: string[]; tallas: string[] }>>({})
    const savingRef = useRef(false)
    const loadSequence = useRef(0)
    const dirtyRef = useRef(false)
    dirtyRef.current = dirty

    useEffect(() => {
        if (cotizacionId) void cargarCotizacion()
        return () => { loadSequence.current++ }
    }, [cotizacionId])

    useEffect(() => {
        const warn = (event: BeforeUnloadEvent) => {
            if (dirtyRef.current) { event.preventDefault(); event.returnValue = "" }
        }
        window.addEventListener("beforeunload", warn)
        return () => window.removeEventListener("beforeunload", warn)
    }, [])

    const applyOptions = (options?: { generos: string[]; colores: string[]; tallas: string[] }) => {
        setGenerosOptions(options?.generos || [])
        setColoresOptions(options?.colores || [])
        setTallasOptions(options?.tallas || [])
    }

    const cargarCotizacion = async () => {
        const sequence = ++loadSequence.current
        setLoadingConfig(true)
        setLoadError(null)
        try {
            const data: any = await api.get(`/api/cotizaciones/${cotizacionId}`)
            // Si falla un ítem, no sustituir su configuración por datos vacíos.
            const loaded = await Promise.all((data.detalles || []).map(async (item: any) => {
                const config: any = await api.get(`/api/cotizacion-items/${item.id}/personalizacion`)
                if (typeof config.es_informativo_cotizacion !== "boolean" || !Array.isArray(config.grupos) || (config.modo_kit && !Array.isArray(config.kit_catalogo))) {
                    throw new Error("Respuesta incompleta de personalización")
                }
                const permits = config.permite_personalizar !== false
                const kit = config.modo_kit ? (config.kit || { kit_version: 1, catalogo: config.kit_catalogo, composiciones: [] }) : null
                return {
                    item: { ...item, es_informativo_cotizacion: config.es_informativo_cotizacion, permite_personalizar: permits, modo_kit: config.modo_kit },
                    kit: kit && !['sent_to_personalization', 'direct_delivery'].includes(data.estado_personalizacion) ? initializeKitChildren(kit) : kit,
                    options: config.options,
                    groups: config.grupos.length ? config.grupos : [{
                        nombre_grupo: "Grupo A", tallas: [], variables: [],
                        matrices: permits && config.tipo_personalizacion !== "none"
                            ? [{ posicion: "Frente", tecnica: "Bordado", tipo_contenido: "logo", valor_fijo: "", comentarios: "" }]
                            : [],
                    }],
                }
            }))
            if (sequence !== loadSequence.current) return
            const operational = loaded.filter(row => !row.item.es_informativo_cotizacion)
            const informational = loaded.filter(row => row.item.es_informativo_cotizacion)
            const configs: Record<number, any[]> = {}
            const options: Record<number, { generos: string[]; colores: string[]; tallas: string[] }> = {}
            operational.forEach(row => { configs[row.item.id] = row.groups; options[row.item.id] = row.options })
            setCotizacion({
                ...data,
                detalles: operational.map(row => row.item),
                informationalItems: informational.map(row => row.item),
            })
            setOptionsByItem(options)
            setConfigsLocales(configs)
            setKitsLocales(Object.fromEntries(operational.filter(row => row.kit).map(row => [row.item.id, row.kit])))
            const first = operational[0]
            setSelectedItemId(first?.item.id ?? null)
            const child = first?.kit ? kitChildren(first.kit)[0] : null
            setSelectedChild(child ? { compositionId: child.composicion_id, childId: child.sku_generico_id } : null)
            setConfiguracion(child ? child.grupos : first?.groups || [])
            setGrupoActivoIndex(0)
            setActiveStep('tallas')
            if (child) applyChildOptions(child)
            else applyOptions(first?.options)
            setKitPickerId(null)
            setDirty(false)
        } catch (error) {
            if (sequence !== loadSequence.current) return
            setLoadError("No pudimos cargar todos los productos. No se ha reemplazado ni guardado ninguna configuración. Reintenta la carga.")
        } finally {
            if (sequence === loadSequence.current) setLoadingConfig(false)
        }
    }

    const selectItem = (id: number) => {
        if (uploadingRef.current || savingRef.current) return
        setSelectedItemId(id)
        setSelectedChild(null)
        setConfiguracion(configsLocales[id] || [])
        setGrupoActivoIndex(0)
        setActiveStep('tallas')
        applyOptions(optionsByItem[id])
    }

    const guardarConfiguracion = async (enviar = true) => {
        if (savingRef.current || uploadingRef.current || loadingConfig || loadError || !cotizacion || ['sent_to_personalization', 'direct_delivery'].includes(cotizacion.estado_personalizacion)) return
        savingRef.current = true
        setSaving(true)
        try {
            const result: any = await api.put(`/api/cotizaciones/${cotizacionId}/personalizacion-configuracion`, {
                items: Object.entries(configsLocales).map(([id, grupos]) => ({ id: Number(id), grupos, ...(kitsLocales[Number(id)] ? { kit: kitsLocales[Number(id)] } : {}) })),
                enviar,
            })
            setDirty(false)
            dirtyRef.current = false
            if (enviar) {
                toast.success(result.message || "Configuración guardada y enviada al taller.")
                router.push("/comercial/cotizaciones")
            } else {
                toast.success("Borrador guardado. No se ha enviado al taller.")
            }
        } catch (error: any) {
            const validation = error.status === 422 ? Object.values(error.data?.errors || {}).flat().join("\n") : ""
            setFeedback(validation || (error.status && error.status < 500 ? error.data?.message : null)
                || "No pudimos confirmar el guardado. Tus cambios siguen aquí. Si estabas enviando al taller, revisa primero el estado de la cotización antes de reintentar.")
        } finally {
            savingRef.current = false
            setSaving(false)
        }
    }

    const itemsDelCot = cotizacion?.detalles || []
    const informationalItems = cotizacion?.informationalItems || []
    const parentActual = itemsDelCot.find((i: any) => i.id === selectedItemId)
    const activeKit = selectedItemId ? kitsLocales[selectedItemId] : null
    const activeChild = activeKit && selectedChild ? kitChildren(activeKit).find(c => c.composicion_id === selectedChild.compositionId && c.sku_generico_id === selectedChild.childId) : null
    const itemActual = activeChild ? { ...parentActual, producto: activeChild.nombre, sku: activeChild.sku_base, cantidad: activeChild.cantidad, permite_personalizar: activeChild.permite_personalizar } : parentActual
    const activeItemNumber = parentActual?.n_item || itemsDelCot.findIndex((item: any) => item.id === selectedItemId) + 1
    const writeCurrentGroups = (groups: any[]) => {
        if (!selectedItemId) return
        if (selectedChild && activeKit) setKitsLocales(prev => ({ ...prev, [selectedItemId]: { ...activeKit, composiciones: activeKit.composiciones.map(c => c.id === selectedChild.compositionId ? { ...c, componentes: c.componentes.map(child => child.sku_generico_id === selectedChild.childId ? { ...child, grupos: groups } : child) } : c) } }))
        else setConfigsLocales(prev => ({ ...prev, [selectedItemId]: groups }))
    }
    const applyChildOptions = (child: ReturnType<typeof kitChildren>[number]) => {
        const variants = child.variantes || []
        applyOptions({ tallas: [...new Set(variants.map(v => v.talla))], colores: [...new Set(variants.map(v => v.color))], generos: [...new Set(variants.map(v => v.genero))] })
    }
    const configureChild = (parentId: number, child: ReturnType<typeof kitChildren>[number]) => {
        if (uploadingRef.current || savingRef.current) return
        setSelectedItemId(parentId)
        setSelectedChild({ compositionId: child.composicion_id, childId: child.sku_generico_id })
        setConfiguracion(child.grupos.length ? child.grupos : [{ nombre_grupo: 'Grupo A', tallas: [], matrices: [], variables: [] }])
        setGrupoActivoIndex(0)
        setActiveStep('tallas')
        applyChildOptions(child)
    }
    const openKitPicker = (parentId: number) => {
        if (savingRef.current || uploadingRef.current || loadingConfig || loadError || ['sent_to_personalization', 'direct_delivery'].includes(cotizacion?.estado_personalizacion)) return
        setKitPickerId(parentId)
    }
    const applyKitSelection = (kit: KitConfig) => {
        if (!kitPickerId || savingRef.current || uploadingRef.current || loadingConfig || loadError || ['sent_to_personalization', 'direct_delivery'].includes(cotizacion?.estado_personalizacion)) return
        const parentId = kitPickerId
        const prepared = initializeKitChildren(kit)
        setKitsLocales(prev => ({ ...prev, [parentId]: prepared }))
        const children = kitChildren(prepared)
        const keep = selectedItemId === parentId && selectedChild
            ? children.find(c => c.composicion_id === selectedChild.compositionId && c.sku_generico_id === selectedChild.childId) : null
        if (keep) {
            // Conservar la pestaña/grupo actual si esa prenda sigue seleccionada.
            setConfiguracion(keep.grupos)
            applyChildOptions(keep)
        } else if (children[0]) configureChild(parentId, children[0])
        else selectItem(parentId)
        setKitPickerId(null)
        setDirty(true)
    }

    // Helpers para modificar el state local (Asumimos 1 grupo en la V1 para simplificar)
    const updateGrupo = (index: number, changes: any) => {
        if (savingRef.current || ['sent_to_personalization', 'direct_delivery'].includes(cotizacion?.estado_personalizacion)) return
        setDirty(true)
        const newConfig = [...configuracion]
        newConfig[index] = { ...newConfig[index], ...changes }
        setConfiguracion(newConfig)
        if (selectedItemId) {
            writeCurrentGroups(newConfig)
        }
    }

    // Helper total de tallas en el grupo actual
    const calcularTotalTallasGrupo = (grupoIndex: number) => {
        if (!configuracion || !configuracion[grupoIndex]) return 0
        const tallas = configuracion[grupoIndex].tallas || []
        return tallas.reduce((sum: number, t: any) => sum + (Number(t.cantidad) || 0), 0)
    }



    // Helper total de tallas sumado entre TODOS los grupos
    const calcularTotalTallasGlobal = () => {
        if (!configuracion || configuracion.length === 0) return 0
        return configuracion.reduce((totalGlobal: number, grupo: any) => {
            const sumGrupo = (grupo.tallas || []).reduce((sum: number, t: any) => sum + (Number(t.cantidad) || 0), 0)
            return totalGlobal + sumGrupo
        }, 0)
    }

    const getInvalidItemNames = () => {
        const invalid: string[] = []
        for (const [idStr, conf] of Object.entries(configsLocales)) {
            if (kitsLocales[Number(idStr)]) continue
            const item = itemsDelCot.find((i: any) => i.id === Number(idStr))
            if (!item) continue;

            const totalEsperado = item.cantidad || 0
            if (totalEsperado === 0) continue;

            const totalIngresado = conf.reduce((acc: number, grupo: any) => {
                const sumGrupo = (grupo.tallas || []).reduce((sum: number, t: any) => sum + (Number(t.cantidad) || 0), 0)
                return acc + sumGrupo
            }, 0)

            const hasTallas = conf.some((g: any) => g.tallas && g.tallas.length > 0)

            // Requerimos que cuadre solo si han agregado alguna talla explícitamente y no coinciden con la sumatoria global.
            if (hasTallas && totalIngresado !== totalEsperado) {
                invalid.push(item.producto || `Item #${item.n_item || item.id}`)
            }
        }
        return invalid
    }

    const renderTallasStep = (grupoIndex: number) => {
        const grupo = configuracion[grupoIndex]
        if (!grupo) return null;
        const tallas = grupo?.tallas || []

        const totalIngresadoGlobal = calcularTotalTallasGlobal()
        const totalIngresadoGrupo = calcularTotalTallasGrupo(grupoIndex)
        const totalEsperado = itemActual?.cantidad || 0
        const diferenciaGlobal = totalEsperado - totalIngresadoGlobal

        let mensajeDiferencia = ""
        let colorDiferencia = "text-slate-500"
        let bgIndicador = "bg-slate-50 border-slate-200"

        if (diferenciaGlobal === 0) {
            mensajeDiferencia = "(Cantidades cuadran ✓)"
            colorDiferencia = "text-green-600 font-bold"
            bgIndicador = "bg-green-50/50 border-green-200"
        } else if (diferenciaGlobal > 0) {
            mensajeDiferencia = `(Faltan asignar ${diferenciaGlobal} de ${totalEsperado})`
            colorDiferencia = "text-amber-600 font-bold"
            bgIndicador = "bg-amber-50/50 border-amber-200"
        } else {
            mensajeDiferencia = `(Sobran ${Math.abs(diferenciaGlobal)} de ${totalEsperado})`
            colorDiferencia = "text-red-500 font-bold"
            bgIndicador = "bg-red-50/50 border-red-200"
        }

        return (
            <div className="space-y-4 pt-4">
                <div className={`flex justify-between items-center p-3 rounded-xl border ${bgIndicador} transition-colors`}>
                    <div>
                        <h3 className="text-sm font-semibold text-slate-700">Distribución de Tallas ({grupo.nombre_grupo})</h3>
                        <p className={`text-xs mt-1 transition-colors ${colorDiferencia}`}>
                            Este Grupo: <span className="font-bold">{totalIngresadoGrupo}</span> <span className="text-slate-400 mx-1">|</span> Total Items: {totalIngresadoGlobal} / {totalEsperado} <span className="ml-1">{mensajeDiferencia}</span>
                        </p>
                    </div>
                    <Button variant="outline" size="sm" onClick={() => {
                        updateGrupo(grupoIndex, {
                            tallas: [...tallas, {
                                talla: tallasOptions[0] || "M",
                                genero: generosOptions[0] || "Unisex",
                                color: coloresOptions[0] || "Generico",
                                cantidad: 1
                            }]
                        })
                    }}>
                        <Plus className="h-4 w-4 mr-2" /> Agregar Talla
                    </Button>
                </div>

                {tallas.map((t: any, i: number) => (
                    <div key={i} className="flex flex-wrap sm:flex-nowrap gap-4 items-end bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <div className="min-w-[100px] flex-[2]">
                            <label className="text-xs font-semibold text-slate-500 mb-1.5 block">Género</label>
                            <Select
                                value={t.genero || ""}
                                onValueChange={v => {
                                    const nt = [...tallas]
                                    nt[i].genero = v
                                    updateGrupo(grupoIndex, { tallas: nt })
                                }}
                            >
                                <SelectTrigger className="bg-white">
                                    <SelectValue placeholder="Género..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {generosOptions.map(option => (
                                        <SelectItem key={option} value={option}>{option}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="min-w-[100px] flex-[2]">
                            <label className="text-xs font-semibold text-slate-500 mb-1.5 block">Color</label>
                            <Select
                                value={t.color || ""}
                                onValueChange={v => {
                                    const nt = [...tallas]
                                    nt[i].color = v
                                    updateGrupo(grupoIndex, { tallas: nt })
                                }}
                            >
                                <SelectTrigger className="bg-white">
                                    <SelectValue placeholder="Color..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {coloresOptions.map(option => (
                                        <SelectItem key={option} value={option}>{option}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="min-w-[72px] flex-1">
                            <label className="text-xs font-semibold text-slate-500 mb-1.5 block">Talla</label>
                            <Select
                                value={t.talla || ""}
                                onValueChange={v => {
                                    const nt = [...tallas]
                                    nt[i].talla = v
                                    updateGrupo(grupoIndex, { tallas: nt })
                                }}
                            >
                                <SelectTrigger className="bg-white">
                                    <SelectValue placeholder="Selecciona..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {tallasOptions.map(option => (
                                        <SelectItem key={option} value={option}>{option}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="min-w-[72px] flex-1">
                            <label className="text-xs font-semibold text-slate-500 mb-1.5 block">Cantidad</label>
                            <Input
                                type="number"
                                value={t.cantidad}
                                onChange={e => {
                                    const nt = [...tallas]
                                    nt[i].cantidad = parseInt(e.target.value) || 0
                                    updateGrupo(grupoIndex, { tallas: nt })
                                }}
                            />
                        </div>
                        <Button variant="ghost" size="icon" onClick={() => {
                            const nt = tallas.filter((_: any, idx: number) => idx !== i)
                            updateGrupo(grupoIndex, { tallas: nt })
                        }}>
                            <Trash2 className="h-5 w-5 text-red-500" />
                        </Button>
                    </div>
                ))}

                {tallas.length === 0 && (
                    <div className="p-8 text-center text-muted-foreground border-2 border-dashed rounded-xl bg-slate-50/50">
                        No hay tallas configuradas todavía. Haz clic en "Agregar Talla" para empezar.
                    </div>
                )}
            </div>
        )
    }

    const renderMatrizStep = (grupoIndex: number) => {
        const grupo = configuracion[grupoIndex]
        if (!grupo) return null;
        const matrices = grupo?.matrices || []
        const hasMixed = new Set(matrices.map((m: any) => m.tecnica).filter(Boolean)).size > 1

        return (
            <div className="space-y-4 pt-4">
                <div className="flex justify-between items-center">
                    <div className="flex items-center gap-2">
                        <h3 className="text-sm font-semibold text-slate-700">Matriz de Personalización ({grupo.nombre_grupo})</h3>
                        {hasMixed && (
                            <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 text-[10px] font-bold px-2 h-5 animate-pulse">
                                PROCESO MIXTO
                            </Badge>
                        )}
                    </div>
                    <Button variant="outline" size="sm" onClick={() => {
                        const lastTecnica = matrices.length > 0 ? matrices[matrices.length - 1].tecnica : "Bordado";
                        updateGrupo(grupoIndex, { matrices: [...matrices, { posicion: "Frente", tecnica: lastTecnica, tipo_contenido: "logo" }] })
                    }}>
                        <Plus className="h-4 w-4 mr-2" /> Agregar Posición
                    </Button>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    {matrices.map((m: any, i: number) => (
                        <div key={i} className="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-4 relative w-full min-w-0 shadow-sm">
                            <Button variant="ghost" size="icon" className="absolute top-2 right-2 h-8 w-8 hover:bg-slate-200" onClick={() => {
                                const nm = matrices.filter((_: any, idx: number) => idx !== i)
                                updateGrupo(grupoIndex, { matrices: nm })
                            }}>
                                <Trash2 className="h-4 w-4 text-red-500" />
                            </Button>

                            <div>
                                <label className="text-xs font-bold uppercase tracking-wider text-slate-700">Posición</label>
                                <Select value={m.posicion || ""} onValueChange={v => {
                                    const nm = [...matrices]; nm[i].posicion = v; updateGrupo(grupoIndex, { matrices: nm })
                                }}>
                                    <SelectTrigger className="h-9 mt-1.5"><SelectValue placeholder="Selecciona..." /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Frente">Frente</SelectItem>
                                        <SelectItem value="Espalda">Espalda</SelectItem>
                                        <SelectItem value="Manga Izquierda">Manga Izquierda</SelectItem>
                                        <SelectItem value="Manga Derecha">Manga Derecha</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <label className="text-xs font-bold uppercase tracking-wider text-slate-700">Técnica y Tipo de Contenido</label>
                                <div className="flex flex-col gap-2 mt-1.5">
                                    <Select 
                                        value={m.tecnica || ""} 
                                        onValueChange={v => {
                                            const nm = [...matrices]; nm[i].tecnica = v; updateGrupo(grupoIndex, { matrices: nm })
                                        }}
                                    >
                                        <SelectTrigger className="h-9 w-full"><SelectValue placeholder="Técnica" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Bordado">Bordado</SelectItem>
                                            <SelectItem value="Estampado">Estampado</SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <Select value={m.tipo_contenido || "logo"} onValueChange={v => {
                                        if (v === (m.tipo_contenido || "logo")) return
                                        // La imagen y el texto comparten valor_fijo, pero no su contenido ni colores.
                                        const nm = matrices.map((matrix: any, index: number) => index === i ? {
                                            ...matrix,
                                            tipo_contenido: v,
                                            valor_fijo: "",
                                            color: v === "logo" ? "Full Color" : "",
                                        } : matrix)
                                        updateGrupo(grupoIndex, { matrices: nm })
                                    }}>
                                        <SelectTrigger className="h-9 w-full"><SelectValue placeholder="Tipo" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="logo">Fijo (Imagen/Logo)</SelectItem>
                                            <SelectItem value="texto_fijo">Fijo (Texto)</SelectItem>
                                            <SelectItem value="texto_variable">Variable (Lista)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            {m.tipo_contenido === 'logo' && (
                                <div className="space-y-4">
                                    <div>
                                        <label className="text-xs font-bold uppercase tracking-wider text-slate-700">Color del Logo</label>
                                        <Select value={m.color || ""} onValueChange={v => {
                                            const nm = [...matrices]; nm[i].color = v; updateGrupo(grupoIndex, { matrices: nm })
                                        }}>
                                            <SelectTrigger className="h-9 mt-1.5"><SelectValue placeholder="Selecciona..." /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Full Color">Full Color</SelectItem>
                                                <SelectItem value="Blanco y Negro">Blanco y Negro</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <label className="text-xs font-bold uppercase tracking-wider text-slate-700">Logo a estampar/bordar</label>

                                        {!m.valor_fijo ? (
                                            <div className="mt-1.5 flex flex-col gap-2">
                                                {/* Botones rápidos predeterminados */}
                                                <div className="flex flex-col gap-2">
                                                    {/* Botón para Logo Predeterminado de Bomberos si existe */}
                                                    {(cotizacion?.cuerpo?.logo_url || cotizacion?.cliente?.logo_url) && (
                                                        <Button
                                                            variant="secondary"
                                                            size="sm"
                                                            type="button"
                                                            className="w-full bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200"
                                                            onClick={() => {
                                                                const logo_url = cotizacion.cuerpo?.logo_url || cotizacion.cliente.logo_url;
                                                                const nm = [...matrices];
                                                                nm[i].valor_fijo = logo_url;
                                                                updateGrupo(grupoIndex, { matrices: nm });
                                                                toast.success("Logo de la institución asignado correctamente");
                                                            }}
                                                        >
                                                            <img src={(cotizacion.cuerpo?.logo_url || cotizacion.cliente.logo_url).startsWith('http') ? (cotizacion.cuerpo?.logo_url || cotizacion.cliente.logo_url) : `${process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000"}${cotizacion.cuerpo?.logo_url || cotizacion.cliente.logo_url}`} alt="Logo FD" className="h-4 w-4 mr-2 object-contain" />
                                                            Usar Logo de Institución
                                                        </Button>
                                                    )}

                                                    {/* Botón para Bandera de Chile */}
                                                    <Button
                                                        variant="secondary"
                                                        size="sm"
                                                        type="button"
                                                        className="w-full bg-red-50 text-red-700 hover:bg-red-100 border border-red-200"
                                                        onClick={() => {
                                                            const nm = [...matrices];
                                                            nm[i].valor_fijo = "https://upload.wikimedia.org/wikipedia/commons/thumb/7/78/Flag_of_Chile.svg/3840px-Flag_of_Chile.svg.png";
                                                            updateGrupo(grupoIndex, { matrices: nm });
                                                            toast.success("Bandera de Chile asignada correctamente");
                                                        }}
                                                    >
                                                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/7/78/Flag_of_Chile.svg/3840px-Flag_of_Chile.svg.png" alt="Bandera Chile" className="h-4 w-4 mr-2 object-contain" />
                                                        Usar Bandera de Chile
                                                    </Button>
                                                </div>

                                                {/* Input de Subida de Archivo Custom */}
                                                <div className="relative flex-1">
                                                    <input
                                                        type="file"
                                                        className="absolute inset-0 cursor-pointer opacity-0"
                                                        accept=".jpg,.jpeg,.png,.webp"
                                                        onChange={async (e) => {
                                                            const f = e.target.files?.[0]
                                                            if (f) {
                                                                if (f.size > 5 * 1024 * 1024) {
                                                                    toast.error("La imagen debe pesar menos de 5MB.")
                                                                    e.target.value = ""
                                                                    return
                                                                }
                                                                try {
                                                                    if (uploadingRef.current) return
                                                                    uploadingRef.current = true
                                                                    setUploading(true)
                                                                    toast.info("Subiendo imagen...")
                                                                    const formData = new FormData()
                                                                    formData.append("image", f)
                                                                    const res = await api.post(`/api/cotizacion-items/${selectedItemId}/personalizacion/upload`, formData)
                                                                    const nm = [...matrices]; nm[i].valor_fijo = res.url; updateGrupo(grupoIndex, { matrices: nm })
                                                                    toast.success("Logo subido correctamente")
                                                                } catch (err) {
                                                                    toast.error("Error al subir el logo")
                                                                } finally {
                                                                    uploadingRef.current = false
                                                                    setUploading(false)
                                                                }
                                                            }
                                                            e.target.value = ""
                                                        }}
                                                    />
                                                    <Button variant="outline" size="sm" type="button" className="w-full bg-white border-slate-300 text-slate-700 hover:bg-slate-100">
                                                        <Plus className="h-4 w-4 mr-2" />
                                                        Cargar Imagen Personalizada
                                                    </Button>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="mt-1.5 p-2 bg-white rounded-md border border-slate-200 flex flex-col items-center gap-2">
                                                <img
                                                    src={m.valor_fijo.startsWith("http") ? m.valor_fijo : `${process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000"}${m.valor_fijo}`}
                                                    alt="Logo vista previa"
                                                    className="w-full h-auto max-h-[120px] object-contain rounded-md"
                                                />
                                                <Button variant="ghost" size="sm" type="button" className="text-red-500 h-7 text-xs w-full" onClick={() => {
                                                    const nm = [...matrices]; nm[i].valor_fijo = null; updateGrupo(grupoIndex, { matrices: nm })
                                                }}>
                                                    Quitar Logo
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                            {m.tipo_contenido === 'texto_fijo' && (
                                <div className="space-y-4">
                                    <div>
                                        <label className="text-xs font-bold uppercase tracking-wider text-slate-700">Color del Texto</label>
                                        <Select value={m.color || ""} onValueChange={v => {
                                            const nm = [...matrices]; nm[i].color = v; updateGrupo(grupoIndex, { matrices: nm })
                                        }}>
                                            <SelectTrigger className="h-9 mt-1.5"><SelectValue placeholder="Selecciona..." /></SelectTrigger>
                                            <SelectContent>
                                                {coloresOptions.map(option => (
                                                    <SelectItem key={option} value={option}>{option}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <label className="text-xs font-bold uppercase tracking-wider text-slate-700">Texto a estampar/bordar</label>
                                        <Textarea
                                            className="min-h-[80px] mt-1.5 bg-white border-slate-200 focus-visible:ring-indigo-500 resize-y"
                                            placeholder={"Ej: CUERPO DE BOMBEROS\nDE MAIPÚ"}
                                            value={m.valor_fijo || ""}
                                            onChange={e => {
                                                const nm = [...matrices]; nm[i].valor_fijo = e.target.value; updateGrupo(grupoIndex, { matrices: nm })
                                            }}
                                        />
                                    </div>

                                    {m.valor_fijo && (
                                        <div className="mt-2 p-4 rounded-md flex flex-col items-center justify-center min-h-[100px] min-w-0 transition-colors"
                                            style={{
                                                backgroundColor: m.color && ['blanco', 'plateado', 'amarillo', 'gris claro', 'celeste', 'silver'].some(c => m.color.toLowerCase().includes(c)) ? '#1e293b' : '#ffffff',
                                                border: '1px solid #cbd5e1'
                                            }}>
                                            <span className="w-full min-w-0 font-bold text-2xl tracking-wide uppercase text-center [overflow-wrap:anywhere]" style={{
                                                color: (() => {
                                                    const c = m.color?.toLowerCase() || '';
                                                    if (c.includes('blanco')) return '#ffffff';
                                                    if (c.includes('negro')) return '#0f172a';
                                                    if (c.includes('rojo')) return '#ef4444';
                                                    if (c.includes('azul') || c.includes('marino')) return '#2563eb';
                                                    if (c.includes('amarillo')) return '#eab308';
                                                    if (c.includes('verde')) return '#16a34a';
                                                    if (c.includes('dorado')) return '#d97706';
                                                    if (c.includes('plateado') || c.includes('silver')) return '#cbd5e1';
                                                    if (c.includes('naranja')) return '#f97316';
                                                    if (c.includes('rosado')) return '#ec4899';
                                                    if (c.includes('morado')) return '#9333ea';
                                                    if (c.includes('burdeo')) return '#9f1239';
                                                    if (c.includes('gris obscuro') || c.includes('gris oscuro')) return '#334155';
                                                    if (c.includes('gris')) return '#64748b';
                                                    return '#334155';
                                                })(),
                                                textShadow: m.color && ['blanco', 'plateado', 'amarillo', 'gris claro', 'celeste'].some(c => m.color.toLowerCase().includes(c)) ? 'none' : '1px 1px 2px rgba(255,255,255,0.8)',
                                                whiteSpace: 'pre-wrap'
                                            }}>
                                                {m.valor_fijo}
                                            </span>
                                            <span className="text-[10px] mt-4 uppercase font-semibold text-slate-400">
                                                Vista Previa Texto Fijo
                                            </span>
                                        </div>
                                    )}
                                </div>
                            )}
                            {m.tipo_contenido === 'texto_variable' && (
                                <div className="space-y-4">
                                    <div>
                                        <label className="text-xs font-bold uppercase tracking-wider text-amber-800">Color del Texto</label>
                                        <Select value={m.color || ""} onValueChange={v => {
                                            const nm = [...matrices]; nm[i].color = v; updateGrupo(grupoIndex, { matrices: nm })
                                        }}>
                                            <SelectTrigger className="h-9 mt-1.5"><SelectValue placeholder="Selecciona..." /></SelectTrigger>
                                            <SelectContent>
                                                {coloresOptions.map(option => (
                                                    <SelectItem key={option} value={option}>{option}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="bg-blue-50 text-blue-800 border-[1.5px] border-blue-200 border-dashed rounded-lg flex items-center justify-center p-3 text-xs font-semibold tracking-wide">
                                        NOMBRES DEFINIDOS SEGÚN LISTADO (PASO 3)
                                    </div>
                                </div>
                            )}

                            {/* Campo de Comentarios Opcional para todas las tarjetas */}
                            <div className="pt-2 border-t mt-4 border-slate-100">
                                <label className="text-xs font-bold uppercase tracking-wider text-slate-700">Comentarios (Opcional)</label>
                                <Textarea
                                    className="min-h-[60px] mt-1.5 bg-slate-50 border-slate-200 focus-visible:ring-indigo-500 resize-y text-sm"
                                    placeholder="Ej: Bordar 3cm más arriba, o enviar prueba antes..."
                                    value={m.comentarios || ""}
                                    onChange={e => {
                                        const nm = [...matrices]; nm[i].comentarios = e.target.value; updateGrupo(grupoIndex, { matrices: nm })
                                    }}
                                />
                            </div>
                        </div>
                    ))}

                    {matrices.length === 0 && (
                        <div className="col-span-full p-8 text-center text-muted-foreground border-2 border-dashed rounded-xl bg-slate-50/50">
                            No hay posiciones configuradas todavía.
                        </div>
                    )}
                </div>
            </div>
        )
    }

    const renderVariablesStep = (grupoIndex: number) => {
        const grupo = configuracion[grupoIndex]
        if (!grupo) return null;
        let variables = grupo?.variables || []

        const itemEnCotizacion = itemActual
        const maxRowsPermitted = itemEnCotizacion?.cantidad || 999;

        // Auto-generar filas según tallas del GRUPO
        const generarFilas = () => {
            if (!grupo?.tallas) return
            const nuevasVariables: any[] = []

            let count = 0;
            const prendasAsignadasAlGrupo = calcularTotalTallasGrupo(grupoIndex);

            grupo.tallas.forEach((t: any) => {
                for (let i = 0; i < t.cantidad; i++) {
                    if (count < prendasAsignadasAlGrupo) {
                        nuevasVariables.push({ talla: t.talla, valor_variable: variables[count]?.valor_variable || "" })
                        count++;
                    }
                }
            })
            if (count === prendasAsignadasAlGrupo && nuevasVariables.length < grupo.tallas.reduce((sum: number, t: any) => sum + Number(t.cantidad || 0), 0)) {
                toast.warning(`Generación limitada a las prendas configuradas (${prendasAsignadasAlGrupo}).`)
            }
            updateGrupo(grupoIndex, { variables: nuevasVariables })
        }

        // Si el usuario redujo manualmente la cantidad de tallas después de haber autogenerado, evitamos mostrar filas muertas adicionales:
        const prendasAsignadasAlGrupo = calcularTotalTallasGrupo(grupoIndex)

        // El verdadero limite a renderizar es la cantidad de prendas asignadas a este grupo, 
        // pero con un fallback de seguridad en caso de que sea 0 momentaneamente
        const limiteRealDeFilas = prendasAsignadasAlGrupo > 0 ? prendasAsignadasAlGrupo : maxRowsPermitted;

        if (variables.length > limiteRealDeFilas) {
            variables = variables.slice(0, limiteRealDeFilas)
        }

        return (
            <div className="space-y-4 pt-4">
                <div className="flex justify-between items-center">
                    <h3 className="text-sm font-semibold text-slate-700">Listado de Nombres Variables ({grupo.nombre_grupo})</h3>
                    <div className="flex items-center gap-2">
                        {grupo?.archivo_adjunto ? (
                            <div className="flex items-center gap-2 px-3 py-1.5 bg-green-50 border border-green-200 rounded-md">
                                <a href={grupo.archivo_adjunto?.startsWith("http") ? grupo.archivo_adjunto : `${process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000"}${grupo.archivo_adjunto}`} target="_blank" rel="noreferrer" className="text-xs font-semibold text-green-700 hover:underline flex items-center gap-1">
                                    <CheckCircle className="h-3.5 w-3.5" />
                                    Archivo de nombres adjunto
                                </a>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-6 w-6 p-0 text-red-500 hover:text-red-700 hover:bg-red-50"
                                    onClick={() => updateGrupo(grupoIndex, { archivo_adjunto: null })}
                                >
                                    <XCircle className="h-4 w-4" />
                                </Button>
                            </div>
                        ) : (
                            <div className="relative">
                                <input
                                    type="file"
                                    id={`file-upload-${grupoIndex}`}
                                    className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                    accept=".pdf,.xls,.xlsx,.csv,.doc,.docx,.txt"
                                    onChange={async (e) => {
                                        const file = e.target.files?.[0]
                                        if (!file) return
                                        if (uploadingRef.current) return
                                        uploadingRef.current = true
                                        setUploading(true)

                                        const toastId = toast.loading("Subiendo archivo de nombres...")
                                        try {
                                            const formData = new FormData()
                                            formData.append('file', file)

                                            const res = await api.post(`/api/cotizacion-items/${selectedItemId}/personalizacion/upload-names`, formData)

                                            // Auto-completar filas si el backend logró parsear excel/csv/txt
                                            if (res.nombres_extraidos && res.nombres_extraidos.length > 0) {
                                                const nombres = res.nombres_extraidos;
                                                const nv = [...variables];
                                                const nuevas: any[] = [];

                                                for (let i = 0; i < nombres.length && i < maxRowsPermitted; i++) {
                                                    nuevas.push({
                                                        talla: nv[i]?.talla || grupo?.tallas?.[0]?.talla || "S",
                                                        valor_variable: nombres[i]
                                                    });
                                                }
                                                updateGrupo(grupoIndex, { archivo_adjunto: res.url, variables: nuevas })
                                                if (nombres.length > maxRowsPermitted) {
                                                    toast.success(`Archivo subido. Se importaron los primeros ${maxRowsPermitted} nombres respetando el límite del ítem.`, { id: toastId })
                                                } else {
                                                    toast.success(`Archivo subido y ${nuevas.length} nombres importados exitosamente.`, { id: toastId })
                                                }
                                            } else {
                                                updateGrupo(grupoIndex, { archivo_adjunto: res.url })
                                                toast.success("Archivo subido correctamente", { id: toastId })
                                            }

                                        } catch (err: any) {
                                            console.error(err)
                                            toast.error("No pudimos subir el documento. Revisa tu conexión e intenta nuevamente.", { id: toastId })
                                        } finally {
                                            uploadingRef.current = false
                                            setUploading(false)
                                        }
                                        e.target.value = '' // reset input
                                    }}
                                />
                                <Button variant="outline" size="sm" type="button" className="bg-white border-slate-300 pointer-events-none">
                                    <UploadCloud className="h-4 w-4 mr-2" /> Adjuntar Planilla/PDF
                                </Button>
                            </div>
                        )}

                        <Button variant="secondary" size="sm" onClick={generarFilas} className="bg-blue-50 text-blue-600 hover:bg-blue-100 hover:text-blue-700">
                            <Plus className="h-4 w-4 mr-2" /> Autogenerar según tallas (Paso 1)
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => {
                            if (variables.length >= maxRowsPermitted) {
                                toast.error(`No puedes agregar más de ${maxRowsPermitted} filas, ya que corresponde al total de este ítem en la cotización.`)
                                return
                            }
                            const nv = [...variables];
                            nv.push({ talla: grupo?.tallas?.[0]?.talla || "S", valor_variable: "" });
                            updateGrupo(grupoIndex, { variables: nv });
                        }}>
                            <Plus className="h-4 w-4 mr-2" /> Fila Manual
                        </Button>
                    </div>
                </div>

                <div className="border rounded-xl overflow-hidden bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-100 border-b">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600 w-16">N°</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600 w-32">Talla</th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">Nombre a bordar/estampar</th>
                                <th className="px-4 py-3 w-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {variables.map((v: any, i: number) => (
                                <tr key={i} className="border-b last:border-0 hover:bg-slate-50 transition-colors">
                                    <td className="px-4 py-3 font-medium text-slate-500">{i + 1}</td>
                                    <td className="px-4 py-3 font-bold text-slate-800">
                                        <Select value={v.talla} onValueChange={val => {
                                            const nv = [...variables]; nv[i].talla = val; updateGrupo(grupoIndex, { variables: nv })
                                        }}>
                                            <SelectTrigger className="w-[80px] h-8 bg-slate-50"><SelectValue placeholder="Talla" /></SelectTrigger>
                                            <SelectContent>
                                                {grupo?.tallas?.map((t: any) => (
                                                    <SelectItem key={t.talla} value={t.talla}>{t.talla}</SelectItem>
                                                )) || <SelectItem value="S">S</SelectItem>}
                                            </SelectContent>
                                        </Select>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Input
                                            className="h-9 w-full max-w-sm bg-white"
                                            placeholder="Ej: J. VALLEJOS"
                                            value={v.valor_variable || ""}
                                            onChange={e => {
                                                const nv = [...variables]; nv[i].valor_variable = e.target.value; updateGrupo(grupoIndex, { variables: nv })
                                            }}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-red-500 hover:bg-red-50 hover:text-red-700" onClick={() => {
                                            const nv = [...variables];
                                            nv.splice(i, 1);
                                            updateGrupo(grupoIndex, { variables: nv });
                                        }}>
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {variables.length === 0 && (
                        <div className="p-12 text-center text-muted-foreground text-sm bg-slate-50/30">
                            Presiona el botón superior "Autogenerar" para crear las filas exactas requeridas.
                        </div>
                    )}
                </div>
            </div>
        )
    }

    const renderItemStatus = (groups: any[] | undefined, permits: boolean) => {
        const hasMatrices = groups?.some(group => group.matrices?.length > 0)
        const status = !permits ? 'Entrega Directa' : !groups?.length ? 'Pendiente' : !hasMatrices ? 'Entrega Directa' : isSentToProduction ? 'Enviado' : 'En preparación'
        const style = status === 'Entrega Directa' ? 'bg-slate-100 text-slate-500 border-slate-200' : status === 'Pendiente' ? 'bg-amber-50 text-amber-600 border-amber-200' : 'bg-amber-50 text-amber-800 border-amber-200'
        return <span className={`border px-1.5 py-0.5 rounded text-[10px] uppercase font-black ${style}`}>{status}</span>
    }

    const isLoadingCotizacion = !cotizacion;
    const isSentToProduction = cotizacion?.estado_personalizacion === 'sent_to_personalization';
    const isDirectDelivery = cotizacion?.estado_personalizacion === 'direct_delivery';
    const isCompletedOrSent = isSentToProduction || isDirectDelivery;

    // Detectar si ningún ítem tiene matrices de personalización configuradas (todos entrega directa)
    const hasAnyMatrices = [...Object.entries(configsLocales).filter(([id]) => !kitsLocales[Number(id)]).map(([, groups]) => groups), ...Object.values(kitsLocales).flatMap(kit => kitChildren(kit).map(child => child.grupos))].some((groups: any) =>
        Array.isArray(groups) && groups.some((g: any) => g.matrices && g.matrices.length > 0)
    );

    return (
        <div className="flex flex-col min-h-screen md:h-screen bg-slate-50">
            <div className="flex-shrink-0 bg-white border-b px-4 sm:px-6 py-4 flex flex-wrap items-center justify-between gap-3 z-10 sticky top-0 shadow-sm">
                <div className="flex items-center gap-4">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => dirty ? setLeaveOpen(true) : router.push("/comercial/cotizaciones")}
                        className="rounded-full hover:bg-slate-100 h-10 w-10 flex-shrink-0"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Button>
                    <div>
                        <h1 className="text-lg sm:text-xl font-bold tracking-tight text-slate-900">Personalización de Cotización</h1>
                        <p className="text-sm text-slate-500 mt-0.5">
                            <span className="font-semibold text-red-600">{cotizacion?.numero || `CT-${cotizacionId}`}</span>
                            <span className="mx-2">•</span>
                            Cliente: <span className="font-medium text-slate-700">{cotizacion?.cuerpo?.nombre || cotizacion?.compania?.nombre || cotizacion?.cliente?.nombre_empresa || 'Cargando...'}</span>
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2 min-w-0">
                    {isSentToProduction && (
                        <span className="text-xs font-semibold text-emerald-600 mr-4 bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-200 flex items-center gap-1.5">
                            <CheckCircle2 className="w-4 h-4" />
                            Enviado a Producción
                        </span>
                    )}
                    {isDirectDelivery && (
                        <span className="text-xs font-semibold text-teal-700 mr-4 bg-teal-50 px-3 py-1.5 rounded-full border border-teal-200 flex items-center gap-1.5">
                            <Package className="w-4 h-4 text-teal-600" />
                            Confirmado: Entrega Directa (Sin Taller)
                        </span>
                    )}
                    {!isCompletedOrSent && getInvalidItemNames().length > 0 && (
                        <span className="text-xs font-semibold text-red-500 mr-4">
                            ⚠️ Error de Tallas en: {getInvalidItemNames().join(", ")}
                        </span>
                    )}
                    {!isCompletedOrSent && <Button variant="outline" onClick={() => guardarConfiguracion(false)}
                        disabled={saving || uploading || loadingConfig || !!loadError || !cotizacion || !itemsDelCot.length}>Guardar borrador</Button>}
                    <Button
                        onClick={() => guardarConfiguracion(true)}
                        disabled={saving || uploading || loadingConfig || !!loadError || !cotizacion || !itemsDelCot.length || isCompletedOrSent}
                        size="lg"
                        className={`max-w-full h-auto min-h-10 py-2 whitespace-normal text-sm shadow-md ${isCompletedOrSent
                            ? 'bg-slate-300 text-slate-500'
                            : hasAnyMatrices
                                ? 'bg-indigo-600 hover:bg-indigo-700'
                                : 'bg-teal-600 hover:bg-teal-700 text-white'}`}
                    >
                        {saving ? (
                            <Loader2 className="mr-2 h-5 w-5 animate-spin" />
                        ) : isSentToProduction ? (
                            <CheckCircle2 className="mr-2 h-5 w-5" />
                        ) : isDirectDelivery ? (
                            <Package className="mr-2 h-5 w-5" />
                        ) : (
                            <Save className="mr-2 h-5 w-5" />
                        )}
                        {isSentToProduction
                            ? "Ya Enviado a Producción"
                            : isDirectDelivery
                                ? "Confirmado Entrega Directa"
                                : hasAnyMatrices
                                    ? "Guardar y enviar al taller"
                                    : "Confirmar Entrega Directa (Sin Taller)"}
                    </Button>
                </div>
            </div>

            {loadError && <div role="alert" className="mx-6 mt-4 flex flex-wrap items-center gap-3 text-sm text-red-800">
                <p>{loadError}</p><Button variant="outline" onClick={() => cargarCotizacion()}>Reintentar carga</Button>
            </div>}
            {informationalItems.length > 0 && <div role="note" className="mx-6 mt-3 flex items-start gap-2 text-sm text-slate-700">
                <Info aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0 text-indigo-600" />
                <p>Los ítems indicados como «No va al taller» permanecen en la cotización y el PDF, pero no forman parte de la personalización.</p>
            </div>}
            <div className="flex flex-col md:flex-row flex-1 min-h-0 overflow-hidden mx-3 lg:mx-6 my-3 md:my-4 bg-white rounded-xl border border-slate-200">
                {/* SIDEBAR ITEMS */}
                <div className={`relative isolate w-full md:w-[320px] h-[240px] md:h-auto shrink-0 min-h-0 border-b md:border-b-0 bg-slate-50/50 flex flex-col ${parentActual ? "md:before:absolute md:before:inset-y-0 md:before:right-0 md:before:w-[3px] md:before:bg-indigo-400" : "md:border-r"}`}>
                    <div className="p-4 border-b bg-slate-100/50">
                        <h2 className="font-semibold text-slate-700 text-sm tracking-wide uppercase">
                            Para personalizar ({itemsDelCot.length})
                        </h2>
                        <p className="mt-1 text-xs leading-relaxed text-slate-600">Elige un producto. En los kits, elige la prenda que quieres configurar.</p>
                    </div>
                    <ScrollArea className="relative z-10 flex-1 min-h-0">
                        {isLoadingCotizacion ? (
                            <div className="p-8 flex justify-center">
                                <Loader2 className="h-6 w-6 animate-spin text-slate-400" />
                            </div>
                        ) : (
                            <div className="p-3 space-y-2">
                                {itemsDelCot.map((it: any) => {
                                    const kit = kitsLocales[it.id]
                                    const children = kit ? kitChildren(kit) : []
                                    const selected = selectedItemId === it.id
                                    const groups = kit ? children.flatMap(child => child.grupos) : configsLocales[it.id]
                                    const itemCardClass = `w-full rounded-xl transition-all ${selected
                                        ? "relative border-[3px] bg-indigo-50 border-indigo-400 md:rounded-r-none md:border-r-0 md:w-[calc(100%+0.75rem)]"
                                        : "border bg-white border-slate-200 text-slate-600 hover:border-slate-300 hover:shadow-sm"}`
                                    const selectParent = () => {
                                        if (kit && children.length) {
                                            const current = selected && selectedChild ? children.find(child => child.composicion_id === selectedChild.compositionId && child.sku_generico_id === selectedChild.childId) : null
                                            if (!current) configureChild(it.id, children[0])
                                        } else selectItem(it.id)
                                    }
                                    const parentContent = <>
                                        <div className="flex items-start justify-between gap-2">
                                            <div className={`font-semibold text-[15px] break-words min-w-0 ${selected ? 'text-indigo-950' : 'text-slate-700'}`}>
                                                {it.producto || `SKU: ${it.sku}`}
                                            </div>
                                            {selected && <span className="shrink-0 rounded bg-indigo-600 px-2 py-1 text-[10px] font-semibold text-white">{isCompletedOrSent ? 'En vista' : kit ? 'Kit activo' : 'Editando'}</span>}
                                        </div>
                                        <div className={`text-xs mt-2 flex flex-wrap gap-2 items-center justify-between font-medium ${selected ? 'text-indigo-800' : 'text-slate-600'}`}>
                                            <div className="flex items-center gap-1.5">
                                                <span className="bg-slate-100 px-2 py-1 rounded-md border text-slate-600">Cant: {it.cantidad}</span>
                                                {renderItemStatus(groups, kit ? true : it.permite_personalizar !== false)}
                                            </div>
                                            <span>Item #{it.n_item || '--'}</span>
                                        </div>
                                    </>
                                    if (!kit) return <button key={it.id} aria-label={`Personalizar ${it.producto}`} aria-pressed={selected} disabled={saving || uploading} onClick={selectParent} className={`${itemCardClass} text-left p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600`}>{parentContent}</button>
                                    return <section key={it.id} aria-label={`Item #${it.n_item || it.id}: ${it.producto}`} className={`${itemCardClass} overflow-hidden`}>
                                        <button aria-label={`Personalizar kit ${it.producto} · Item #${it.n_item || it.id}`} aria-pressed={selected} disabled={saving || uploading} onClick={selectParent} className="block w-full text-left p-4 pb-3 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600">{parentContent}</button>
                                        <div className="px-4 pb-3 flex items-center justify-between gap-2">
                                            <span className="rounded border border-indigo-100 bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700">Kit/Traje</span>
                                            <Button size="sm" variant="outline" className="text-xs h-7" aria-label={`Elegir SKU hijos de ${it.producto} · Item #${it.n_item || it.id}`} disabled={saving || uploading || loadingConfig || !!loadError || isCompletedOrSent} onClick={() => openKitPicker(it.id)}>Elegir prendas</Button>
                                        </div>
                                        {children.length ? <div className="border-t border-slate-200 bg-slate-50/70 px-3 py-3">
                                            <p className="mb-2 text-[11px] font-medium text-slate-500">Prendas del kit ({children.length})</p>
                                            <ul aria-label={`Prendas de ${it.producto} · Item #${it.n_item || it.id}`} className="ml-1 border-l border-slate-300 pl-3 space-y-1">
                                                {children.map(child => {
                                                    const childSelected = selected && selectedChild?.compositionId === child.composicion_id && selectedChild?.childId === child.sku_generico_id
                                                    return <li key={child.key} className="relative before:absolute before:top-5 before:-left-3 before:w-3 before:border-t before:border-slate-300">
                                                        <button aria-label={`Personalizar ${child.nombre} · ${it.producto} · Item #${it.n_item || it.id} · ${child.composicion_nombre}`} aria-pressed={childSelected} disabled={saving || uploading} onClick={() => configureChild(it.id, child)} className={`w-full text-left rounded-lg border px-3 py-2 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 ${childSelected ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-transparent text-slate-700 hover:border-slate-200 hover:bg-white'}`}>
                                                            <span className="flex items-start justify-between gap-2"><span className="text-[13px] font-semibold break-words min-w-0">{child.nombre}</span>{childSelected && <span className="shrink-0 text-[10px] font-semibold">{isCompletedOrSent ? 'En vista' : 'Editando'}</span>}</span>
                                                            <span className={`mt-1 flex flex-wrap items-center gap-2 text-[11px] ${childSelected ? 'text-indigo-100' : 'text-slate-600'}`}><span>Cant: {child.cantidad}</span>{renderItemStatus(child.grupos, child.permite_personalizar !== false)}</span>
                                                            {kit.composiciones.length > 1 && <span className={`block mt-1 text-[11px] ${childSelected ? 'text-indigo-100' : 'text-slate-600'}`}>{child.composicion_nombre}</span>}
                                                        </button>
                                                    </li>
                                                })}
                                            </ul>
                                        </div> : <p className="border-t border-slate-100 px-4 py-3 text-xs text-slate-500">Sin prendas elegidas</p>}
                                    </section>
                                })}
                                {itemsDelCot.length === 0 && (
                                    <div className="text-center p-4 text-xs text-slate-500">
                                        No hay ítems que requieran personalización
                                    </div>
                                )}
                                {informationalItems.length > 0 && <section aria-labelledby="informational-items-title" className="mt-4 border-t border-slate-200 pt-4">
                                    <div className="mb-2 flex items-center gap-2 px-1">
                                        <Info aria-hidden="true" className="h-4 w-4 shrink-0 text-slate-500" />
                                        <h3 id="informational-items-title" className="text-xs font-semibold uppercase tracking-wide text-slate-600">
                                            Incluidos sin personalización ({informationalItems.length})
                                        </h3>
                                    </div>
                                    <div className="space-y-2">
                                        {informationalItems.map((it: any) => <div key={it.id} className="rounded-xl border border-slate-200 bg-slate-100/70 p-3 text-slate-700">
                                            <div className="flex min-w-0 items-start justify-between gap-3">
                                                <span className="min-w-0 break-words text-sm font-semibold">{it.producto || `SKU: ${it.sku}`}</span>
                                                <span className="shrink-0 rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-medium tabular-nums text-slate-600">Cant: {it.cantidad}</span>
                                            </div>
                                            <p className="mt-2 text-xs font-medium text-slate-600">No va al taller · Se mantiene en cotización y PDF</p>
                                        </div>)}
                                    </div>
                                </section>}
                            </div>
                        )}
                    </ScrollArea>
                </div>

{/* MAIN CONTENT WORKSPACE */}
                <fieldset aria-labelledby={parentActual && !loadingConfig && !loadError ? "active-product-title" : undefined} disabled={saving || uploading || loadingConfig || !!loadError || isCompletedOrSent} className={`flex-1 min-h-[640px] md:min-h-0 min-w-0 flex flex-col overflow-hidden relative ${parentActual ? "border-[3px] border-indigo-400 md:border-l-0 bg-indigo-50 md:rounded-r-xl" : "bg-white"}`}>
                    {parentActual && !loadingConfig && !loadError && <section aria-labelledby="active-product-title" aria-live="polite" aria-atomic="true" className="shrink-0 bg-indigo-50 px-4 sm:px-6 py-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0 flex-1">
                                <h2 id="active-product-title" className="text-lg sm:text-xl font-semibold leading-snug text-indigo-950 break-words">{isCompletedOrSent ? 'Estás viendo' : activeKit && !activeChild ? 'Configura las prendas de' : 'Estás editando'}: {itemActual?.producto || `SKU: ${itemActual?.sku}`}</h2>
                                <p className="mt-1 text-sm text-indigo-900">Ítem #{activeItemNumber}{activeChild ? ` · Prenda del kit: ${parentActual.producto}` : activeKit ? ' · Kit/Traje' : ' · Producto individual'}{activeChild && activeKit && activeKit.composiciones.length > 1 ? ` · ${activeChild.composicion_nombre}` : ''}</p>
                                {itemActual?.sku && <p className="mt-1 text-xs text-indigo-800 break-all">SKU: {itemActual.sku}</p>}
                            </div>
                            <p className="shrink-0 text-sm font-medium text-indigo-950">{activeKit && !activeChild ? `${parentActual.cantidad} ${parentActual.cantidad === 1 ? 'kit' : 'kits'}` : `${itemActual?.cantidad} ${itemActual?.cantidad === 1 ? 'unidad' : 'unidades'}`}{isCompletedOrSent && <span className="block mt-1 text-xs font-normal text-indigo-800">Solo lectura</span>}</p>
                        </div>
                    </section>}
                    {uploading && <p role="status" className="px-6 py-2 text-sm text-slate-700">Subiendo archivo… espera antes de cambiar de producto o guardar.</p>}
                    <div className={`relative flex flex-col flex-1 min-h-0 bg-white ${parentActual ? "mx-3 mb-3 overflow-hidden rounded-xl" : ""}`}>
                    {loadingConfig ? (
                        <div className="absolute inset-0 bg-white/80 backdrop-blur-sm z-50 flex items-center justify-center flex-col gap-3">
                            <Loader2 className="h-8 w-8 animate-spin text-indigo-500" />
                            <span className="text-sm font-medium text-slate-600">Cargando diseño...</span>
                        </div>
                    ) : null}

                    {activeKit && !selectedChild ? <div className="flex-1 flex flex-col items-center justify-center p-8 text-center gap-3">
                        <Package className="h-8 w-8 text-indigo-500" />
                        <h2 className="text-lg font-semibold text-slate-800">{parentActual.producto}</h2>
                        <p className="max-w-md text-sm text-slate-500">Elige los SKU hijos del kit. Las prendas aparecerán dentro de este ítem y usarán el editor habitual de personalización.</p>
                        <Button onClick={() => selectedItemId && openKitPicker(selectedItemId)}>Elegir SKU hijos</Button>
                    </div> : (!selectedItemId) ? (
                        <div className="flex-1 flex flex-col items-center justify-center text-slate-400 gap-4">
                            <div className="p-8 text-center">
                                <Plus className="h-8 w-8 text-slate-300 mx-auto mb-4" />
                                <p className="text-sm font-medium">{loadError ? "Reintenta la carga antes de continuar." : cotizacion && !itemsDelCot.length ? "Esta cotización no tiene productos que configurar en Personalización." : "Selecciona un ítem de la izquierda para comenzar"}</p>
                            </div>
                        </div>
                    ) : (() => {
                        const hasMatrices = configuracion.some((g: any) => g.matrices && g.matrices.length > 0);
                        
                        if (!hasMatrices) {
                            return (
                                <div className="flex-1 flex flex-col items-center justify-center bg-slate-50/50 p-8">
                                    {activeChild && <div className="w-full max-w-3xl mb-6">{configuracion.map((_: any, index: number) => <div key={index}>{renderTallasStep(index)}</div>)}</div>}
                                    <div className="bg-white border border-slate-200/80 shadow-md rounded-2xl p-8 max-w-xl text-center flex flex-col items-center">
                                        <div className="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mb-6">
                                            <CheckCircle2 className="h-8 w-8 text-slate-600" />
                                        </div>
                                        <h2 className="text-xl font-bold text-slate-800 mb-2">
                                            Entrega Directa (Sin Personalización)
                                        </h2>
                                        <p className="text-sm text-slate-500 mb-8 max-w-sm leading-relaxed">
                                            Este producto se despachará directamente de bodega en su estado original. No requiere bordado, estampado, parches ni nombres.
                                        </p>
                                        
                                        <div className="flex gap-4">
                                            <Button
                                                disabled={itemActual?.permite_personalizar === false}
                                                onClick={() => {
                                                    // Inicializamos el primer grupo con una matriz vacía para activar la UI de personalización
                                                    setDirty(true)
                                                    const newConf = configuracion.map((g: any, idx: number) => {
                                                        if (idx === 0) {
                                                            return {
                                                                ...g,
                                                                matrices: [{
                                                                    posicion: 'Frente',
                                                                    tecnica: 'Bordado',
                                                                    tipo_contenido: 'logo',
                                                                    texto_fijo: '',
                                                                    logotipo_url: null,
                                                                    comentarios: ''
                                                                }]
                                                            };
                                                        }
                                                        return g;
                                                    });
                                                    
                                                    // Si no hay grupos configurados, creamos uno nuevo con matriz por defecto
                                                    const finalConf = newConf.length > 0 ? newConf : [{
                                                        nombre_grupo: 'Grupo A',
                                                        tallas: [],
                                                        matrices: [{
                                                            posicion: 'Frente',
                                                            tecnica: 'Bordado',
                                                            tipo_contenido: 'logo',
                                                            texto_fijo: '',
                                                            logotipo_url: null,
                                                            comentarios: ''
                                                        }],
                                                        variables: []
                                                    }];

                                                    setConfiguracion(finalConf);
                                                    if (selectedItemId) {
                                                        writeCurrentGroups(finalConf);
                                                    }
                                                    setGrupoActivoIndex(0);
                                                    toast.success("Personalización activada. Ahora puedes configurar logos y tallas.");
                                                }}
                                                className="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow-sm px-6"
                                            >
                                                🔧 Activar Personalización
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            );
                        }

                        const isNombresEnabled = configuracion.some((grupo: any) =>
                            grupo?.matrices?.some((m: any) => m.tipo_contenido === 'texto_variable')
                        );
                        return (
                            <div className="flex flex-col flex-1 min-h-0">



                                {/* BARRA DEL SELECTOR DE GRUPOS */}
                                <div className="shrink-0 px-4 sm:px-6 border-b py-3 bg-slate-50 flex flex-wrap items-center gap-2">
                                    <span className="text-sm font-medium text-slate-600 mr-2 flex-shrink-0">Grupos de Personalización:</span>
                                    {configuracion.map((g: any, idx: number) => (
                                        <div key={idx} className="relative group inline-flex">
                                            <Button
                                                variant={grupoActivoIndex === idx ? "default" : "outline"}
                                                size="sm"
                                                onClick={() => setGrupoActivoIndex(idx)}
                                                className={`rounded-full px-4 h-8 transition-all duration-300 font-semibold ${grupoActivoIndex === idx ? 'bg-indigo-600 text-white shadow-sm ring-2 ring-indigo-200' : 'text-slate-600 hover:bg-white bg-transparent border-slate-300'}`}
                                            >
                                                {g.nombre_grupo || `Grupo ${String.fromCharCode(65 + idx)}`}
                                            </Button>
                                            {configuracion.length > 1 && (
                                                <button
                                                    onClick={() => {
                                                        const newConf = configuracion.filter((_: any, i: number) => i !== idx);
                                                        // Si eliminamos A y queda B, renombramos a A para mantener coherencia
                                                        newConf.forEach((c: any, newIdx: number) => {
                                                            c.nombre_grupo = `Grupo ${String.fromCharCode(65 + newIdx)}`;
                                                        });
                                                        setDirty(true)
                                                        setConfiguracion(newConf);
                                                        if (selectedItemId) {
                                                            writeCurrentGroups(newConf);
                                                        }
                                                        if (grupoActivoIndex >= idx && grupoActivoIndex > 0) {
                                                            setGrupoActivoIndex(grupoActivoIndex - 1);
                                                        } else if (grupoActivoIndex > newConf.length - 1) {
                                                            setGrupoActivoIndex(newConf.length - 1);
                                                        }
                                                    }}
                                                    className="absolute -top-1.5 -right-1.5 bg-red-100 text-red-600 rounded-full w-4 h-4 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-500 hover:text-white border shadow-sm z-10"
                                                    title="Eliminar grupo"
                                                >
                                                    <XCircle className="w-3 h-3" />
                                                </button>
                                            )}
                                        </div>
                                    ))}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            const idx = configuracion.length;
                                            const nextLetter = String.fromCharCode(65 + idx);
                                            const newConf = [...configuracion, {
                                                nombre_grupo: `Grupo ${nextLetter}`,
                                                tallas: [],
                                                matrices: [],
                                                variables: []
                                            }];
                                            setDirty(true)
                                            setConfiguracion(newConf);
                                            if (selectedItemId) {
                                                writeCurrentGroups(newConf);
                                            }
                                            setGrupoActivoIndex(idx);
                                        }}
                                        className="rounded-full px-3 h-8 text-indigo-600 hover:bg-indigo-50 bg-white ml-2 flex-shrink-0 shadow-sm border border-slate-200"
                                    >
                                        <Plus className="h-4 w-4 mr-1" />
                                        Añadir Grupo
                                    </Button>

                                    <div className="ml-auto flex flex-wrap items-center gap-3">
                                        <div className="text-xs font-medium text-slate-500 bg-white px-3 py-1.5 rounded-full border shadow-sm">
                                            Total General: <span className="font-bold text-slate-700">{calcularTotalTallasGlobal()}</span> de {itemActual?.cantidad || 0}
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={!configuracion.some((g: any) => g.matrices && g.matrices.length > 0)}
                                            onClick={() => {
                                                if (!configuracion.some((g: any) => g.matrices && g.matrices.length > 0)) {
                                                    return;
                                                }
                                                setRemovePersonalizationOpen(true);
                                            }}
                                            className="bg-white text-slate-500 hover:text-red-600 hover:border-red-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <Trash2 className="h-4 w-4 mr-2" />
                                            No personalizar
                                        </Button>
                                    </div>
                                </div>

                                <Tabs value={activeStep === 'variables' && !isNombresEnabled ? 'tallas' : activeStep} onValueChange={setActiveStep} className="flex flex-col flex-1 min-h-0 overflow-hidden">
                                    <div className="shrink-0 px-4 sm:px-6 py-3 border-b space-y-2 bg-white">
                                        <TabsList className="bg-slate-100 p-1 h-10 w-full max-w-lg grid grid-cols-3 rounded-lg overflow-hidden">
                                            <TabsTrigger value="tallas" className="rounded-md text-slate-700 data-[state=active]:bg-indigo-600 data-[state=active]:text-white text-[11px] sm:text-xs font-semibold">
                                                1. Tallas
                                            </TabsTrigger>
                                            <TabsTrigger value="matriz" className="rounded-md text-slate-700 data-[state=active]:bg-indigo-600 data-[state=active]:text-white text-[11px] sm:text-xs font-semibold">
                                                2. Matriz Visual
                                            </TabsTrigger>
                                            <TabsTrigger
                                                value="variables"
                                                disabled={!isNombresEnabled}
                                                title={!isNombresEnabled ? 'Para cargar nombres, elige Variable (Lista) en Matriz Visual.' : 'Completa los nombres de cada prenda.'}
                                                className="rounded-md text-slate-700 data-[state=active]:bg-indigo-600 data-[state=active]:text-white text-[11px] sm:text-xs font-semibold whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                3. Nombres (Datos)
                                            </TabsTrigger>
                                        </TabsList>
                                        <p className="text-xs leading-relaxed text-slate-600">{activeStep === 'matriz'
                                            ? 'Define dónde irá el bordado o estampado. Para nombres distintos, elige «Variable (Lista)».'
                                            : activeStep === 'variables' && isNombresEnabled
                                                ? 'Completa los nombres de las prendas o adjunta una planilla con los nombres.'
                                                : 'Asigna tallas, colores y cantidades. La suma debe coincidir con las unidades de este producto.'}</p>
                                    </div>

                                    <div className="flex-1 min-h-0 overflow-y-auto p-4 sm:p-8 pb-32">
                                        <TabsContent value="tallas" className="m-0 border-none outline-none animate-in fade-in zoom-in-95 duration-200">
                                            <div className="max-w-4xl mx-auto">
                                                {renderTallasStep(grupoActivoIndex)}
                                            </div>
                                        </TabsContent>

                                        <TabsContent value="matriz" className="m-0 border-none outline-none animate-in fade-in zoom-in-95 duration-200">
                                            <div className="w-full mx-auto">
                                                {renderMatrizStep(grupoActivoIndex)}
                                            </div>
                                        </TabsContent>

                                        <TabsContent value="variables" className="m-0 border-none outline-none animate-in fade-in zoom-in-95 duration-200">
                                            <div className="max-w-5xl mx-auto">
                                                {renderVariablesStep(grupoActivoIndex)}
                                            </div>
                                        </TabsContent>
                                    </div>
                                </Tabs>
                            </div>
                        )
                    })()}
                    </div>
                </fieldset>
            </div>
            {kitPickerId && kitsLocales[kitPickerId] && <KitChildrenPicker key={kitPickerId} name={itemsDelCot.find((item: any) => item.id === kitPickerId)?.producto || 'Kit'} itemNumber={itemsDelCot.find((item: any) => item.id === kitPickerId)?.n_item} quantity={itemsDelCot.find((item: any) => item.id === kitPickerId)?.cantidad || 0} kit={kitsLocales[kitPickerId]} disabled={saving || uploading || loadingConfig || !!loadError || isCompletedOrSent} onClose={() => setKitPickerId(null)} onApply={applyKitSelection} />}
            <AlertDialog open={!!feedback} onOpenChange={open => { if (!open) setFeedback(null) }}>
                <AlertDialogContent><AlertDialogHeader><AlertDialogTitle>No se pudo completar la operación</AlertDialogTitle>
                    <AlertDialogDescription className="whitespace-pre-line">{feedback}</AlertDialogDescription>
                </AlertDialogHeader><AlertDialogFooter><AlertDialogAction onClick={() => setFeedback(null)}>Revisar configuración</AlertDialogAction></AlertDialogFooter></AlertDialogContent>
            </AlertDialog>
            <AlertDialog open={leaveOpen} onOpenChange={setLeaveOpen}>
                <AlertDialogContent><AlertDialogHeader><AlertDialogTitle>Tienes cambios sin guardar</AlertDialogTitle>
                    <AlertDialogDescription>Puedes volver al formulario y guardar un borrador, o salir y perder los cambios de esta sesión.</AlertDialogDescription>
                </AlertDialogHeader><AlertDialogFooter><AlertDialogCancel>Seguir editando</AlertDialogCancel>
                    <AlertDialogAction onClick={() => router.push("/comercial/cotizaciones")}>Salir sin guardar</AlertDialogAction></AlertDialogFooter></AlertDialogContent>
            </AlertDialog>
            <AlertDialog open={removePersonalizationOpen} onOpenChange={setRemovePersonalizationOpen}>
                <AlertDialogContent><AlertDialogHeader><AlertDialogTitle>¿Dejar {itemActual?.producto || 'este producto'} sin personalización?</AlertDialogTitle>
                    <AlertDialogDescription>Se quitarán sus matrices y nombres.{activeChild ? ' Las otras prendas del kit conservarán su configuración.' : ''} El cambio se aplicará cuando guardes.</AlertDialogDescription>
                </AlertDialogHeader><AlertDialogFooter><AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction onClick={() => {
                        const groups = configuracion.map(g => ({ ...g, matrices: [], variables: [] }))
                        setConfiguracion(groups)
                        if (selectedItemId) writeCurrentGroups(groups)
                        setDirty(true)
                    }}>Quitar personalización</AlertDialogAction></AlertDialogFooter></AlertDialogContent>
            </AlertDialog>
        </div>
    )
}
