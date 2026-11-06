"use client"

import React, { useState, useEffect, useMemo, useRef } from "react"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { api } from "@/lib/api"
import {
  Loader2, Search, Archive, UploadCloud, Save, X, Box,
  ArrowLeft, Package, Palette, Ruler, Eye, EyeOff,
  ImageIcon, ShieldCheck, Tag, Plus, Zap, CheckCircle2,
  AlertTriangle, RotateCcw, ChevronLeft, ChevronRight
} from "lucide-react"
import { useToast } from "@/hooks/use-toast"
import { useInventoryPermissions } from "@/hooks/useInventoryPermissions"
import { Badge } from "@/components/ui/badge"
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip"
import { VariantNameCell } from "./VariantNameCell"
import { searchColors, sortSizes } from "../lib/variant-options"
import { genericCatalogChanges } from "../lib/generic-catalog-update"
import { KitCatalogEditor } from "./KitCatalogEditor"
import type { KitDefinition } from '@/lib/kit-plan'

// ─── Types ──────────────────────────────────────────────────────────────────

export type EstadoCatalogo = "borrador" | "publicado" | "archivado"

interface GenericSku {
  id: number
  sku_base: string
  nombre: string
  imagen_url: string | null
  imagen_back_url: string | null
  imagen_side_url: string | null
  precio_venta: number | null
  estado_catalogo: EstadoCatalogo
  activo: boolean
  permite_personalizar?: boolean
  permite_cotizar?: boolean
  modo_kit?: boolean
  componentes_kit?: KitDefinition[]
  kit_catalogo?: KitDefinition[]
  archived_at?: string | null
  archive_reason?: string | null
  // Detail fields
  variants?: Variant[]
  colores_disponibles?: string[]
  tallas_disponibles?: string[]
  stock_total?: number
  stock_reservado_total?: number
}

interface Variant {
  sku: string
  nombre: string
  color: string
  talla: string
  genero: string
  stock: number
  reserved_stock?: number
  activo: boolean
}

interface ArchivePreview {
  generic_id: number
  generic_nombre: string
  variantes_afectadas: number
  variantes_totales: number
  stock_fisico_total: number
  stock_reservado: number
  solicitudes_pendientes: number
}

// ─── Helpers ────────────────────────────────────────────────────────────────

const API_BASE = (process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000").replace(/\/$/, "")

function imgUrl(path: string | null | undefined): string | null {
  if (!path) return null
  if (path.startsWith("http")) return path
  return `${API_BASE}/${path.replace(/^\//, "")}`
}

const DECORATIVE_PRODUCT_NAMES = [
  "NOMBRE",
  "CARGO",
  "BANDERA",
  "FRASE",
  "PARCHE 2,5 X 10",
  "PARCHE 5 X 10",
  "PARCHE 10 X 10",
  "PARCHE 25 X 10",
  "PARCHE 2.5 X 10",
  "LOGO",
]

function isDecorativeItem(item: { nombre?: string; sku_base?: string; modo_kit?: boolean }): boolean {
  if (item.modo_kit) return false
  const norm = (item.nombre || "").trim().toUpperCase()
  if (DECORATIVE_PRODUCT_NAMES.includes(norm)) return true
  if (norm.startsWith("PARCHE ") && norm.includes("X 10")) return true
  if (item.sku_base && item.sku_base.startsWith("117700000")) return true
  return false
}

// ─── Component ──────────────────────────────────────────────────────────────

export function ManageGenericosModal({
  isOpen,
  onClose,
  catalogos,
  loadingCatalogos,
  catalogosError,
  onRetryCatalogos,
  initialCreateMode = false,
  onCatalogChange,
}: {
  isOpen: boolean
  onClose: () => void
  catalogos: any
  loadingCatalogos: boolean
  catalogosError: string | null
  onRetryCatalogos: () => void
  initialCreateMode?: boolean
  onCatalogChange?: () => void
}) {
  // ── State ───────────────────────────────────────────────────────────────
  const [skus, setSkus] = useState<GenericSku[]>([])
  const [loading, setLoading] = useState(false)
  const [search, setSearch] = useState("")
  const [estadoFilter, setEstadoFilter] = useState<"todos" | EstadoCatalogo>("todos")
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)

  const { toast } = useToast()
  const { canManageStock } = useInventoryPermissions()

  // Creation View
  const [isCreatingTemplate, setIsCreatingTemplate] = useState(false)
  const [submittingTemplate, setSubmittingTemplate] = useState(false)
  const [templateForm, setTemplateForm] = useState({
    nombre: "",
    precio_venta: "",
    unidad_negocio_id: 1,
    origen_id: 1,
    grp_familia_id: 1,
    familia_id: -1,
    subfamilia_id: 0,
    familia_tipo_id: 0,
    familia_formato_id: 0,
    generos: [1] as number[],
    permite_personalizar: true,
    permite_cotizar: true,
    modo_kit: false,
  })

  // Detail view
  const [detail, setDetail] = useState<GenericSku | null>(null)
  const savedDetailRef = useRef<GenericSku | null>(null)
  const [loadingDetail, setLoadingDetail] = useState(false)
  const [uploadingField, setUploadingField] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [publishConfirmOpen, setPublishConfirmOpen] = useState(false)
  const [reactivating, setReactivating] = useState(false)
  const hasMutatedRef = useRef(false)

  const handleClose = () => {
    if (hasMutatedRef.current) {
      onCatalogChange?.()
      hasMutatedRef.current = false
    }
    onClose()
  }

  // Subfamilias válidas para la familia elegida (Genérico, id 0, siempre disponible).
  const subfamiliaPerteneceA = (subfamiliaId: number, familiaId: number) =>
    subfamiliaId === 0 ||
    (catalogos.subfamilias ?? []).some((s: any) => s.id === subfamiliaId && Number(s.familia_id) === familiaId)

  const subfamiliasDeFamilia = useMemo(() => {
    return (catalogos.subfamilias ?? []).filter(
      (s: any) => s.id === 0 || Number(s.familia_id) === templateForm.familia_id
    )
  }, [catalogos.subfamilias, templateForm.familia_id])

  const activeVariantsCount = useMemo(() => {
    return detail?.variants?.filter((v) => v.activo).length ?? 0
  }, [detail?.variants])

  // Archive with Impact Preview
  const [archiveDialogOpen, setArchiveDialogOpen] = useState(false)
  const [archivePreview, setArchivePreview] = useState<ArchivePreview | null>(null)
  const [loadingPreview, setLoadingPreview] = useState(false)
  const [archiveReason, setArchiveReason] = useState("")
  const [archiving, setArchiving] = useState(false)

  // Variant Selection & Bulk Actions
  const [selectedVariantSkus, setSelectedVariantSkus] = useState<string[]>([])
  const [bulkProcessing, setBulkProcessing] = useState(false)

  // Add Variants State
  const [selectedColores, setSelectedColores] = useState<number[]>([])
  const [selectedTallas, setSelectedTallas] = useState<number[]>([])
  const [addingVariants, setAddingVariants] = useState(false)
  const [colorSearch, setColorSearch] = useState("")
  const visibleColors = useMemo(() => searchColors(catalogos.colores ?? [], colorSearch), [catalogos.colores, colorSearch])
  const orderedSizes = useMemo(() => sortSizes(catalogos.tallas ?? []), [catalogos.tallas])
  const chosenColors = (catalogos.colores ?? []).filter((color: { id: number }) => selectedColores.includes(color.id))

  // Abort controller ref to cancel outdated requests
  const abortControllerRef = useRef<AbortController | null>(null)

  // ── Effects ─────────────────────────────────────────────────────────────
  useEffect(() => {
    if (isOpen) {
      if (initialCreateMode) {
        setIsCreatingTemplate(true)
      } else {
        setIsCreatingTemplate(false)
      }
      fetchSkus()
    } else {
      setSkus([])
      setSearch("")
      setPage(1)
      setEstadoFilter("todos")
      setDetail(null)
      setIsCreatingTemplate(false)
      setSelectedColores([])
      setColorSearch("")
      setSelectedTallas([])
      setSelectedVariantSkus([])
      setArchiveDialogOpen(false)
      setArchivePreview(null)
      setArchiveReason("")
      setTemplateForm({
        nombre: "",
        precio_venta: "",
        unidad_negocio_id: 1,
        origen_id: 1,
        grp_familia_id: 1,
        familia_id: -1,
        subfamilia_id: 0,
        familia_tipo_id: 0,
        familia_formato_id: 0,
        generos: [1],
        permite_personalizar: true,
        permite_cotizar: true,
        modo_kit: false,
      })
    }
  }, [isOpen, initialCreateMode])

  useEffect(() => {
    if (!isOpen) return
    const t = setTimeout(() => {
      setPage(1)
      fetchSkus()
    }, 350)
    return () => clearTimeout(t)
  }, [search, estadoFilter])

  useEffect(() => {
    if (isOpen) {
      fetchSkus()
    }
  }, [page])

  // ── API ─────────────────────────────────────────────────────────────────
  const fetchSkus = async () => {
    if (abortControllerRef.current) {
      abortControllerRef.current.abort()
    }
    const controller = new AbortController()
    abortControllerRef.current = controller

    setLoading(true)
    try {
      const params = new URLSearchParams()
      params.append("page", page.toString())
      params.append("per_page", "24")
      if (estadoFilter !== "todos") {
        params.append("estado", estadoFilter)
      }
      if (search.trim()) {
        params.append("search", search.trim())
      }

      const res: any = await api.get(`/api/sku-genericos/admin?${params.toString()}`)
      if (controller.signal.aborted) return

      if (res && res.data && Array.isArray(res.data)) {
        setSkus(res.data)
        setTotal(res.total ?? res.data.length)
        setLastPage(res.last_page ?? 1)
      } else if (Array.isArray(res)) {
        setSkus(res)
        setTotal(res.length)
        setLastPage(1)
      }
    } catch (err: any) {
      if (err?.name === "AbortError" || err?.code === "ERR_CANCELED") return
      toast({ title: "Error", description: "No se pudo cargar el catálogo.", variant: "destructive" })
    } finally {
      if (!controller.signal.aborted) {
        setLoading(false)
      }
    }
  }

  const openDetail = async (id: number) => {
    setLoadingDetail(true)
    setSelectedColores([])
    setColorSearch("")
    setSelectedTallas([])
    setSelectedVariantSkus([])
    try {
      const res = await api.get(`/api/sku-genericos/admin/${id}`)
      savedDetailRef.current = res
      setDetail(res)
    } catch {
      toast({ title: "Error", description: "No se pudo cargar el detalle del producto.", variant: "destructive" })
    } finally {
      setLoadingDetail(false)
    }
  }

  // Crear plantilla exclusiva (sin variantes ni stock)
  const handleCreateTemplate = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!templateForm.nombre.trim()) {
      return toast({ title: "Atención", description: "Ingresa el nombre del producto base.", variant: "destructive" })
    }
    if (templateForm.familia_id <= 0) {
      return toast({ title: "Atención", description: "Selecciona una Familia válida.", variant: "destructive" })
    }

    setSubmittingTemplate(true)
    try {
      const payload = {
        nombre: templateForm.nombre.trim(),
        precio_venta: templateForm.precio_venta ? Number(templateForm.precio_venta) : null,
        unidad_negocio_id: templateForm.unidad_negocio_id,
        origen_id: templateForm.origen_id,
        grp_familia_id: templateForm.grp_familia_id,
        familia_id: templateForm.familia_id,
        subfamilia_id: templateForm.subfamilia_id,
        familia_tipo_id: templateForm.familia_tipo_id,
        familia_formato_id: templateForm.familia_formato_id,
        generos: templateForm.generos.length > 0 ? templateForm.generos : [1],
        permite_personalizar: templateForm.permite_personalizar,
        permite_cotizar: templateForm.permite_cotizar,
        modo_kit: templateForm.modo_kit,
      }

      const res: any = await api.post("/api/sku-genericos", payload)
      toast({
        title: "Plantilla creada en borrador",
        description: res.message || "Añade al menos una variante para poder publicarla.",
      })

      hasMutatedRef.current = true
      onCatalogChange?.()
      setIsCreatingTemplate(false)
      setTemplateForm({
        nombre: "",
        precio_venta: "",
        unidad_negocio_id: 1,
        origen_id: 1,
        grp_familia_id: 1,
        familia_id: -1,
        subfamilia_id: 0,
        familia_tipo_id: 0,
        familia_formato_id: 0,
        generos: [1],
        permite_personalizar: true,
        permite_cotizar: true,
        modo_kit: false,
      })
      fetchSkus()

      if (res.data?.id) {
        openDetail(res.data.id)
      }
    } catch (err: any) {
      toast({
        title: "No se pudo crear la plantilla",
        description: err.response?.data?.message || "Ocurrió un conflicto al generar el producto.",
        variant: "destructive",
      })
    } finally {
      setSubmittingTemplate(false)
    }
  }

  // Publicar plantilla (requiere >= 1 variante activa o confirmación de activación)
  const handlePublish = async (activateVariants = false) => {
    if (!detail) return
    setPublishing(true)
    try {
      const payload = activateVariants ? { activate_variants: true } : {}
      const res: any = await api.post(`/api/sku-genericos/${detail.id}/publish`, payload)
      toast({
        title: "Plantilla publicada",
        description: res.message || "Ya está disponible para cotizaciones e inventario.",
      })
      hasMutatedRef.current = true
      onCatalogChange?.()
      setPublishConfirmOpen(false)
      openDetail(detail.id)
      fetchSkus()
    } catch (err: any) {
      const msg = err.response?.data?.errors?.publicar?.[0] || err.response?.data?.message || "No se pudo publicar."
      toast({ title: "No se puede publicar", description: msg, variant: "destructive" })
    } finally {
      setPublishing(false)
    }
  }

  // Abrir diálogo de impacto para archivar
  const handleOpenArchiveDialog = async () => {
    if (!detail) return
    setArchiveReason("")
    setArchivePreview(null)
    setArchiveDialogOpen(true)
    setLoadingPreview(true)
    try {
      const preview: any = await api.get(`/api/sku-genericos/${detail.id}/archive-preview`)
      setArchivePreview(preview)
    } catch {
      toast({ title: "Advertencia", description: "No se pudo calcular el impacto previo.", variant: "destructive" })
    } finally {
      setLoadingPreview(false)
    }
  }

  // Confirmar archivado
  const handleConfirmArchive = async () => {
    if (!canManageStock || !detail) return
    setArchiving(true)
    try {
      await api.del(`/api/sku-genericos/${detail.id}`, {
        data: { archive_reason: archiveReason.trim() || undefined }
      })
      toast({
        title: "Producto archivado",
        description: "Su historial y stock fueron preservados. Se desactivaron sus variantes.",
      })
      hasMutatedRef.current = true
      onCatalogChange?.()
      setArchiveDialogOpen(false)
      openDetail(detail.id)
      fetchSkus()
    } catch (err: any) {
      toast({
        title: "Error al archivar",
        description: err.response?.data?.message || "Inténtalo nuevamente.",
        variant: "destructive",
      })
    } finally {
      setArchiving(false)
    }
  }

  // Reactivar plantilla
  const handleReactivate = async () => {
    if (!detail) return
    setReactivating(true)
    try {
      const res: any = await api.post(`/api/sku-genericos/${detail.id}/reactivate`)
      toast({
        title: "Plantilla reactivada como borrador",
        description: "Selecciona las variantes que deseas activar antes de publicarla.",
      })
      hasMutatedRef.current = true
      onCatalogChange?.()
      openDetail(detail.id)
      fetchSkus()
    } catch (err: any) {
      toast({
        title: "Error al reactivar",
        description: err.response?.data?.message || "Inténtalo nuevamente.",
        variant: "destructive",
      })
    } finally {
      setReactivating(false)
    }
  }

  // Acciones en lote de variantes
  const handleArchiveSelectedVariants = async () => {
    if (!detail || selectedVariantSkus.length === 0) return
    setBulkProcessing(true)
    try {
      const res: any = await api.post(`/api/sku-genericos/${detail.id}/variants/archive`, {
        skus: selectedVariantSkus,
      })
      toast({ title: "Variantes archivadas", description: res.message })
      hasMutatedRef.current = true
      onCatalogChange?.()
      setSelectedVariantSkus([])
      openDetail(detail.id)
    } catch (err: any) {
      toast({ title: "Error", description: err.response?.data?.message || "No se pudieron archivar.", variant: "destructive" })
    } finally {
      setBulkProcessing(false)
    }
  }

  const handleReactivateSelectedVariants = async () => {
    if (!detail || selectedVariantSkus.length === 0) return
    setBulkProcessing(true)
    try {
      const res: any = await api.post(`/api/sku-genericos/${detail.id}/variants/reactivate`, {
        skus: selectedVariantSkus,
      })
      toast({ title: "Variantes reactivadas", description: res.message })
      hasMutatedRef.current = true
      onCatalogChange?.()
      setSelectedVariantSkus([])
      openDetail(detail.id)
    } catch (err: any) {
      toast({ title: "Error", description: err.response?.data?.message || "No se pudieron reactivar.", variant: "destructive" })
    } finally {
      setBulkProcessing(false)
    }
  }

  const handleReactivateAllVariants = async () => {
    if (!detail || !detail.variants || detail.variants.length === 0) return
    const allSkus = detail.variants.map((v) => v.sku)
    setBulkProcessing(true)
    try {
      const res: any = await api.post(`/api/sku-genericos/${detail.id}/variants/reactivate`, {
        skus: allSkus,
      })
      toast({ title: "Variantes reactivadas", description: res.message || "Todas las variantes fueron activadas." })
      hasMutatedRef.current = true
      onCatalogChange?.()
      openDetail(detail.id)
    } catch (err: any) {
      toast({ title: "Error", description: err.response?.data?.message || "No se pudieron reactivar.", variant: "destructive" })
    } finally {
      setBulkProcessing(false)
    }
  }

  const toggleSelectAllVariants = () => {
    if (!detail?.variants) return
    if (selectedVariantSkus.length === detail.variants.length) {
      setSelectedVariantSkus([])
    } else {
      setSelectedVariantSkus(detail.variants.map((v) => v.sku))
    }
  }

  const toggleVariantSelection = (sku: string) => {
    setSelectedVariantSkus((prev) =>
      prev.includes(sku) ? prev.filter((s) => s !== sku) : [...prev, sku]
    )
  }

  // Agregar nuevas variantes
  const handleAddVariants = async () => {
    if (!canManageStock || !detail || (selectedColores.length === 0 && selectedTallas.length === 0)) return
    if (selectedColores.length === 0) return toast({ title: "Atención", description: "Selecciona al menos un color." })
    if (selectedTallas.length === 0) return toast({ title: "Atención", description: "Selecciona al menos una talla." })

    setAddingVariants(true)
    try {
      const res: any = await api.post(`/api/sku-genericos/${detail.id}/add-variants`, {
        colores: selectedColores,
        tallas: selectedTallas,
      })
      toast({ title: "Variantes procesadas", description: res.message })
      hasMutatedRef.current = true
      onCatalogChange?.()
      openDetail(detail.id)
      setSelectedColores([])
      setSelectedTallas([])
    } catch (e: any) {
      toast({
        title: "Error",
        description: e.response?.data?.message || "No se pudo agregar variantes",
        variant: "destructive",
      })
    } finally {
      setAddingVariants(false)
    }
  }

  // Guardar únicamente los campos modificados del producto base.
  const saveDetail = async () => {
    if (!canManageStock || !detail || savedDetailRef.current?.id !== detail.id) return
    const payload = genericCatalogChanges(savedDetailRef.current, detail)
    if (Object.keys(payload).length === 0) {
      toast({ title: "Sin cambios", description: "No hay cambios para guardar." })
      return
    }
    setSaving(true)
    try {
      await api.put(`/api/sku-genericos/${detail.id}`, payload)
      toast({ title: "Guardado", description: "Cambios aplicados al catálogo." })
      hasMutatedRef.current = true
      onCatalogChange?.()
      await openDetail(detail.id)
      fetchSkus()
    } catch (error: any) {
      toast({ title: "Error", description: Object.values(error.data?.errors || {}).flat().join(' ') || error.data?.message || "No se pudo guardar.", variant: "destructive" })
    } finally {
      setSaving(false)
    }
  }

  // Subir imagen
  const uploadImage = async (field: "imagen" | "imagen_back" | "imagen_side", file: File) => {
    if (!canManageStock || !detail) return
    const fd = new FormData()
    fd.append(field, file)
    setUploadingField(field)
    try {
      const res = await api.post(`/api/sku-genericos/${detail.id}/upload`, fd)
      setDetail({ ...detail, ...res })
      toast({ title: "Imagen actualizada" })
      fetchSkus()
    } catch {
      toast({ title: "Error", description: "No se pudo subir la imagen.", variant: "destructive" })
    } finally {
      setUploadingField(null)
    }
  }

  // ═══════════════════════════════════════════════════════════════════════
  //  RENDER
  // ═══════════════════════════════════════════════════════════════════════

  return (
    <Dialog open={isOpen} onOpenChange={(v) => !v && handleClose()}>
      <DialogContent aria-describedby="catalog-modal-desc" className="max-w-[95vw] sm:max-w-[95vw] w-[1400px] h-[92vh] flex flex-col p-0 overflow-hidden gap-0 bg-white">
        <span id="catalog-modal-desc" className="sr-only">
          Panel de administración del Catálogo Comercial de Productos PTE para gestionar plantillas, variantes y sincronización.
        </span>

        {/* ────────────────────────────── DETAIL VIEW ───────────────────────── */}
        {detail !== null && (
          <div className="absolute inset-0 z-50 bg-white flex flex-col animate-in fade-in-0 duration-200">
            {/* Detail Header */}
            <div className="flex items-center justify-between px-8 py-4 border-b bg-white shrink-0">
              <div className="flex items-center gap-4">
                <button
                  onClick={() => setDetail(null)}
                  className="flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg"
                >
                  <ArrowLeft className="h-3.5 w-3.5" /> Volver al Catálogo
                </button>
                <div className="h-5 w-px bg-slate-200" />
                <div>
                  <div className="flex items-center gap-2.5">
                    <h2 className="text-lg font-bold text-slate-900 leading-tight">{detail.nombre}</h2>
                    {detail.estado_catalogo === "publicado" && (
                      <Badge className="bg-emerald-100 text-emerald-800 hover:bg-emerald-100 border-emerald-300 font-semibold text-[11px]">
                        ● Publicado
                      </Badge>
                    )}
                    {detail.estado_catalogo === "borrador" && (
                      <Badge className="bg-amber-100 text-amber-800 hover:bg-amber-100 border-amber-300 font-semibold text-[11px]">
                        ● Borrador
                      </Badge>
                    )}
                    {detail.estado_catalogo === "archivado" && (
                      <Badge className="bg-zinc-100 text-zinc-700 hover:bg-zinc-100 border-zinc-300 font-semibold text-[11px]">
                        ● Archivado
                      </Badge>
                    )}
                  </div>
                  <p className="text-xs text-slate-400 font-mono mt-0.5">SKU Base: {detail.sku_base}</p>
                </div>
              </div>

              {canManageStock && <div className="flex items-center gap-3">
                {/* Botón de Publicación (para borradores) */}
                {detail.estado_catalogo === "borrador" && (() => {
                  const hasNoVariants = detail.modo_kit ? !(savedDetailRef.current?.modo_kit && savedDetailRef.current.componentes_kit?.length) || JSON.stringify(detail.componentes_kit) !== JSON.stringify(savedDetailRef.current.componentes_kit) : (detail.variants?.length ?? 0) === 0
                  const publishButton = (
                    <Button
                      size="sm"
                      className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs h-9 px-4 gap-1.5 shadow-sm"
                      onClick={() => {
                        if (activeVariantsCount === 0 && !detail.modo_kit) {
                          setPublishConfirmOpen(true)
                        } else {
                          void handlePublish(false)
                        }
                      }}
                      disabled={publishing || hasNoVariants}
                    >
                      {publishing ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />}
                      Publicar en Comercial
                    </Button>
                  )

                  if (!hasNoVariants) return publishButton

                  // Un botón deshabilitado no recibe eventos del mouse: el span sostiene el tooltip.
                  return (
                    <Tooltip>
                      <TooltipTrigger asChild>
                        <span tabIndex={0} className="inline-flex cursor-not-allowed">
                          {publishButton}
                        </span>
                      </TooltipTrigger>
                      <TooltipContent side="bottom" className="max-w-xs text-xs">
                        {detail.modo_kit ? 'Define y guarda los componentes del kit antes de publicar.' : 'Agrega al menos un color y una talla en Incorporar Nuevas Variantes para poder publicar.'}
                      </TooltipContent>
                    </Tooltip>
                  )
                })()}

                {/* Botón de Reactivación (para archivados) */}
                {detail.estado_catalogo === "archivado" && (
                  <Button
                    size="sm"
                    variant="outline"
                    className="border-blue-300 text-blue-700 bg-blue-50 hover:bg-blue-100 font-semibold text-xs h-9 px-4 gap-1.5"
                    onClick={handleReactivate}
                    disabled={reactivating}
                  >
                    {reactivating ? <Loader2 className="h-4 w-4 animate-spin" /> : <RotateCcw className="h-4 w-4" />}
                    Reactivar Plantilla
                  </Button>
                )}

                {/* Botón de Archivado (para publicados o borradores) */}
                {detail.estado_catalogo !== "archivado" && (
                  <Button
                    size="sm"
                    variant="outline"
                    className="border-red-200 text-red-600 hover:bg-red-50 font-semibold text-xs h-9 px-3 gap-1.5"
                    onClick={handleOpenArchiveDialog}
                  >
                    <Archive className="h-4 w-4" />
                    Archivar
                  </Button>
                )}

                {/* Guardar cambios básicos */}
                <Button
                  size="sm"
                  className="bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs h-9 px-4 gap-1.5"
                  onClick={saveDetail}
                  disabled={saving}
                >
                  {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                  Guardar
                </Button>
              </div>}
            </div>

            {/* Detail Body */}
            {loadingDetail ? (
              <div className="flex-1 flex flex-col items-center justify-center gap-3 text-slate-400">
                <Loader2 className="h-8 w-8 animate-spin" />
                <p className="text-sm font-medium">Cargando detalle del producto…</p>
              </div>
            ) : (
              <div className="flex-1 overflow-y-auto px-8 py-6 space-y-8 bg-slate-50/50">
                {/* Banner de advertencia si es borrador sin variantes activas */}
                {detail.estado_catalogo === "borrador" && activeVariantsCount === 0 && (detail.variants?.length ?? 0) > 0 && (
                  <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
                    <div className="flex items-start gap-2.5">
                      <AlertTriangle className="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                      <div>
                        <p className="text-xs font-bold text-amber-900">Plantilla con variantes inactivas (0/{detail.variants?.length})</p>
                        <p className="text-xs text-amber-700 mt-0.5">
                          Todas las variantes están archivadas. Debes activar al menos una variante para publicar en el catálogo comercial, o reactivarlas todas automáticamente.
                        </p>
                      </div>
                    </div>
                    {canManageStock && (
                      <Button
                        size="sm"
                        variant="outline"
                        className="border-amber-300 bg-white hover:bg-amber-100 text-amber-900 font-semibold text-xs shrink-0 gap-1.5"
                        onClick={handleReactivateAllVariants}
                        disabled={bulkProcessing}
                      >
                        {bulkProcessing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RotateCcw className="h-3.5 w-3.5" />}
                        Reactivar Todas
                      </Button>
                    )}
                  </div>
                )}

                {/* 1. Información General y Precios */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                  <div className="md:col-span-2 bg-white rounded-xl border p-5 space-y-4 shadow-sm">
                    <h3 className="text-sm font-bold text-slate-800 flex items-center gap-2">
                      <Tag className="h-4 w-4 text-blue-600" />
                      Información Comercial
                    </h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div>
                        <Label className="text-xs font-semibold text-slate-600">Nombre del Producto</Label>
                        <Input
                          className="mt-1 h-9 text-sm"
                          value={detail.nombre}
                          disabled={!canManageStock}
                          onChange={(e) => setDetail({ ...detail, nombre: e.target.value })}
                        />
                      </div>
                      <div>
                        <Label className="text-xs font-semibold text-slate-600">Precio de Venta (CLP)</Label>
                        <Input
                          type="number"
                          className="mt-1 h-9 text-sm font-mono"
                          value={detail.precio_venta ?? ""}
                          disabled={!canManageStock}
                          onChange={(e) => setDetail({ ...detail, precio_venta: e.target.value ? Number(e.target.value) : null })}
                        />
                      </div>
                    </div>

                    <div className="pt-2 border-t flex items-center justify-between">
                      <div className="pr-4"><Label htmlFor="detail-modo-kit" className="text-xs font-semibold">Modo Kit/Traje</Label><p className="text-[11px] text-slate-500">Configura y prepara sus productos hijos. El kit conserva su precio comercial.</p></div>
                      <Switch id="detail-modo-kit" checked={detail.modo_kit ?? false} disabled={!canManageStock || saving} onCheckedChange={modo_kit => setDetail({ ...detail, modo_kit })} />
                    </div>

                    <div className="pt-2 border-t flex items-center justify-between">
                      <div className="space-y-0.5 pr-4">
                        <Label htmlFor="detail-permite-cotizar" className="text-xs font-semibold text-slate-700">Permite cotización</Label>
                        <p id="detail-cotizar-description" className="text-[11px] text-slate-500">Disponible para seleccionar en nuevas cotizaciones. Los ítems existentes se conservan.</p>
                      </div>
                      <Switch id="detail-permite-cotizar" aria-describedby="detail-cotizar-description"
                        checked={detail.permite_cotizar ?? true} disabled={!canManageStock || saving}
                        onCheckedChange={permite_cotizar => setDetail({ ...detail, permite_cotizar })} />
                    </div>

                    <div className="pt-2 border-t flex items-center justify-between">
                      <div className="space-y-0.5">
                        <Label className="text-xs font-semibold text-slate-700">Permite Personalización</Label>
                        <p className="text-[11px] text-slate-500">
                        {detail.modo_kit ? 'Cada producto hijo usa su propio permiso de personalización.' : 'Habilita bordados, parches, estampados o insignias para esta prenda en el cotizador.'}
                        </p>
                      </div>
                      <Switch
                        checked={detail.permite_personalizar ?? true}
                        disabled={!canManageStock || !!detail.modo_kit}
                        onCheckedChange={(v) => setDetail({ ...detail, permite_personalizar: v })}
                      />
                    </div>
                  </div>

                  {/* Resumen de Stock */}
                  <div className="bg-white rounded-xl border p-5 flex flex-col justify-between shadow-sm">
                    <h3 className="text-sm font-bold text-slate-800 flex items-center gap-2">
                      <Package className="h-4 w-4 text-emerald-600" />
                      {detail.modo_kit ? 'Stock por componentes' : 'Stock Global'}
                    </h3>
                    {detail.modo_kit ? <p className="text-sm text-slate-600 py-4">Las existencias y reservas pertenecen a las variantes de los productos hijos. Este kit conserva su nombre y precio comercial.</p> : <div className="grid grid-cols-2 gap-4 my-auto py-2">
                      <div className="bg-slate-50 p-3 rounded-lg border text-center">
                        <p className="text-[11px] font-semibold text-slate-500 uppercase">Físico</p>
                        <p className="text-2xl font-black text-slate-900 mt-0.5">{detail.stock_total ?? 0}</p>
                      </div>
                      <div className="bg-amber-50/60 p-3 rounded-lg border border-amber-200/60 text-center">
                        <p className="text-[11px] font-semibold text-amber-700 uppercase">Reservado</p>
                        <p className="text-2xl font-black text-amber-800 mt-0.5">{detail.stock_reservado_total ?? 0}</p>
                      </div>
                    </div>}
                    <p className="text-[11px] text-slate-400 text-center">
                      {detail.modo_kit ? `${detail.componentes_kit?.length ?? 0} componentes definidos` : `${detail.variants?.length ?? 0} variantes registradas`}
                    </p>
                  </div>
                </div>

                {detail.modo_kit && <KitCatalogEditor parentId={detail.id} value={detail.componentes_kit ?? []} names={detail.kit_catalogo ?? []} disabled={!canManageStock || saving} onChange={componentes_kit => setDetail({ ...detail, componentes_kit })} />}

                {/* 2. Vistas e Imágenes del Producto */}
                <div className="bg-white rounded-xl border p-5 shadow-sm space-y-4">
                  <h3 className="text-sm font-bold text-slate-800 flex items-center gap-2">
                    <ImageIcon className="h-4 w-4 text-purple-600" />
                    Vistas del Producto (Catálogo y Cotizador)
                  </h3>
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    {(["imagen", "imagen_back", "imagen_side"] as const).map((field) => {
                      const url = field === "imagen" ? detail.imagen_url : field === "imagen_back" ? detail.imagen_back_url : detail.imagen_side_url
                      const label = field === "imagen" ? "Vista Frontal" : field === "imagen_back" ? "Vista Trasera" : "Vista Lateral"
                      return (
                        <div key={field} className="border rounded-xl p-4 flex flex-col items-center gap-3 bg-slate-50/50">
                          <span className="text-xs font-bold text-slate-700">{label}</span>
                          <div className="w-full aspect-square bg-white border rounded-lg flex items-center justify-center overflow-hidden relative shadow-inner">
                            {url ? (
                              <img src={imgUrl(url)!} alt={label} className="h-full w-full object-contain p-3" />
                            ) : (
                              <Box className="h-10 w-10 text-slate-200" />
                            )}
                            {uploadingField === field && (
                              <div className="absolute inset-0 bg-white/80 flex items-center justify-center">
                                <Loader2 className="h-6 w-6 animate-spin text-blue-600" />
                              </div>
                            )}
                          </div>
                          {canManageStock && (
                            <label className="w-full">
                              <input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                className="hidden"
                                disabled={uploadingField !== null}
                                onChange={(e) => {
                                  const f = e.target.files?.[0]
                                  if (f) uploadImage(field, f)
                                }}
                              />
                              <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="w-full text-xs h-8 gap-1.5 border-slate-300"
                                disabled={uploadingField !== null}
                                onClick={(e) => {
                                  (e.currentTarget.parentElement?.querySelector('input[type="file"]') as HTMLInputElement)?.click()
                                }}
                              >
                                <UploadCloud className="h-3.5 w-3.5" />
                                {url ? "Reemplazar" : "Subir Imagen"}
                              </Button>
                            </label>
                          )}
                        </div>
                      )
                    })}
                  </div>
                </div>

                {/* 3. Variantes Existentes y Acciones en Lote */}
                {!detail.modo_kit && <div className="bg-white rounded-xl border shadow-sm overflow-hidden space-y-0">
                  <div className="px-5 py-4 border-b flex items-center justify-between bg-white">
                    <div className="flex items-center gap-3">
                      <h3 className="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <Box className="h-4 w-4 text-indigo-600" />
                        Variantes del Producto ({detail.variants?.length ?? 0})
                      </h3>
                      {selectedVariantSkus.length > 0 && (
                        <Badge className="bg-blue-100 text-blue-800 border-blue-200">
                          {selectedVariantSkus.length} seleccionadas
                        </Badge>
                      )}
                    </div>

                    {/* Acciones en lote */}
                    {canManageStock && selectedVariantSkus.length > 0 && (
                      <div className="flex items-center gap-2">
                        <Button
                          size="sm"
                          variant="outline"
                          className="h-8 text-xs border-red-200 text-red-600 hover:bg-red-50"
                          onClick={handleArchiveSelectedVariants}
                          disabled={bulkProcessing}
                        >
                          <Archive className="h-3.5 w-3.5 mr-1" />
                          Archivar Seleccionadas
                        </Button>
                        <Button
                          size="sm"
                          variant="outline"
                          className="h-8 text-xs border-emerald-200 text-emerald-700 hover:bg-emerald-50"
                          onClick={handleReactivateSelectedVariants}
                          disabled={bulkProcessing}
                        >
                          <CheckCircle2 className="h-3.5 w-3.5 mr-1" />
                          Reactivar Seleccionadas
                        </Button>
                      </div>
                    )}

                    {canManageStock && selectedVariantSkus.length === 0 && detail.variants && detail.variants.some((v) => !v.activo) && (
                      <Button
                        size="sm"
                        variant="outline"
                        className="h-8 text-xs border-emerald-200 text-emerald-700 hover:bg-emerald-50"
                        onClick={handleReactivateAllVariants}
                        disabled={bulkProcessing}
                      >
                        {bulkProcessing ? <Loader2 className="h-3.5 w-3.5 mr-1 animate-spin" /> : <RotateCcw className="h-3.5 w-3.5 mr-1" />}
                        Reactivar Todas ({detail.variants.length})
                      </Button>
                    )}
                  </div>

                  {/* Tabla de variantes */}
                  {(!detail.variants || detail.variants.length === 0) ? (
                    <div className="py-12 text-center text-slate-400 space-y-2">
                      <Box className="h-10 w-10 mx-auto opacity-30" />
                      <p className="text-sm font-medium">Esta plantilla aún no tiene variantes creadas.</p>
                      <p className="text-xs text-slate-400">Usa la sección inferior para incorporar colores y tallas.</p>
                    </div>
                  ) : (
                    <div className="overflow-x-auto">
                      <table className="w-full text-left text-xs border-collapse">
                        <thead>
                          <tr className="border-b bg-slate-50 text-slate-600 font-semibold uppercase text-[10px]">
                            {canManageStock && (
                              <th className="p-3 w-10 text-center">
                                <input
                                  type="checkbox"
                                  className="rounded border-slate-300 text-blue-600"
                                  checked={selectedVariantSkus.length > 0 && selectedVariantSkus.length === detail.variants.length}
                                  onChange={toggleSelectAllVariants}
                                />
                              </th>
                            )}
                            <th className="p-3">SKU Variante</th>
                            <th className="p-3">Nombre</th>
                            <th className="p-3">Color</th>
                            <th className="p-3">Talla</th>
                            <th className="p-3 text-right">Stock Físico</th>
                            <th className="p-3 text-right">Reservado</th>
                            <th className="p-3 text-center">Estado</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                          {detail.variants.map((v) => {
                            const isSelected = selectedVariantSkus.includes(v.sku)
                            return (
                              <tr
                                key={v.sku}
                                className={`transition-colors hover:bg-slate-50/80 ${
                                  !v.activo ? "opacity-60 bg-slate-50/40" : ""
                                } ${isSelected ? "bg-blue-50/60" : ""}`}
                              >
                                {canManageStock && (
                                  <td className="p-3 text-center">
                                    <input
                                      type="checkbox"
                                      className="rounded border-slate-300 text-blue-600"
                                      checked={isSelected}
                                      onChange={() => toggleVariantSelection(v.sku)}
                                    />
                                  </td>
                                )}
                                <td className="p-3 font-mono font-medium text-slate-800">{v.sku}</td>
                                <td className="p-3">
                                  <VariantNameCell genericId={detail.id} sku={v.sku} name={v.nombre ?? ""} canEdit={canManageStock}
                                    onSaved={nombre => {
                                      const genericId = detail.id
                                      setDetail(current => current?.id === genericId ? {
                                        ...current, variants: current.variants?.map(variant => variant.sku === v.sku ? { ...variant, nombre } : variant),
                                      } : current)
                                      hasMutatedRef.current = true
                                      onCatalogChange?.()
                                    }} />
                                </td>
                                <td className="p-3 font-semibold text-slate-700">{v.color}</td>
                                <td className="p-3 font-semibold text-slate-700">{v.talla}</td>
                                <td className="p-3 text-right font-mono font-bold text-slate-900">{v.stock}</td>
                                <td className="p-3 text-right font-mono text-amber-700 font-semibold">{v.reserved_stock ?? 0}</td>
                                <td className="p-3 text-center">
                                  <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                    v.activo ? "bg-emerald-100 text-emerald-800" : "bg-zinc-100 text-zinc-600"
                                  }`}>
                                    {v.activo ? "Activa" : "Archivada"}
                                  </span>
                                </td>
                              </tr>
                            )
                          })}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>

                }
                {/* 4. Añadir Variantes (Colores y Tallas) */}
                {canManageStock && !detail.modo_kit && detail.estado_catalogo !== "archivado" && (
                  <section className="bg-white rounded-xl border p-5 shadow-sm space-y-4">
                    <div className="flex items-center justify-between border-b pb-3">
                      <div>
                        <h3 className="text-sm font-bold text-slate-800 flex items-center gap-2">
                          <Plus className="h-4 w-4 text-orange-600" />
                          Incorporar Nuevas Variantes
                        </h3>
                        <p className="text-xs text-slate-500 mt-0.5">
                          Selecciona colores y tallas para generar las combinaciones canónicas con stock inicial cero.
                        </p>
                      </div>
                      <Button
                        size="sm"
                        className="bg-orange-600 hover:bg-orange-700 text-white font-semibold text-xs h-9 px-4 gap-1.5"
                        onClick={handleAddVariants}
                        disabled={addingVariants || selectedColores.length === 0 || selectedTallas.length === 0}
                      >
                        {addingVariants ? <Loader2 className="h-4 w-4 animate-spin" /> : <Zap className="h-4 w-4" />}
                        Generar Variantes
                      </Button>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                      {/* Colores */}
                      <div>
                        <Label className="text-xs font-bold text-slate-700 mb-2 block">
                          Colores Disponibles ({selectedColores.length} seleccionados)
                        </Label>
                        <div className="relative mb-2">
                          <Search aria-hidden="true" className="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                          <Input aria-label="Buscar colores disponibles" placeholder="Buscar color, por ejemplo: azul marino"
                            value={colorSearch} onChange={event => setColorSearch(event.target.value)} className="pl-9 pr-20" />
                          {colorSearch && <Button type="button" variant="ghost" size="sm" className="absolute right-1 top-1 h-8 text-xs" onClick={() => setColorSearch("")}>Limpiar</Button>}
                        </div>
                        {chosenColors.length > 0 && <div className="mb-2 flex flex-wrap gap-1.5" aria-label="Colores seleccionados">
                          {chosenColors.map((color: { id: number; nombre: string }) => <button key={color.id} type="button"
                            aria-label={`Quitar color ${color.nombre}`} disabled={addingVariants}
                            onClick={() => setSelectedColores(current => current.filter(id => id !== color.id))}
                            className="flex items-center gap-1 rounded-md border border-orange-200 bg-orange-50 px-2 py-1 text-xs font-medium text-orange-800 hover:bg-orange-100 focus-visible:outline-2 focus-visible:outline-orange-600">
                            {color.nombre}<X aria-hidden="true" className="h-3 w-3" />
                          </button>)}
                        </div>}
                        <div className="flex flex-wrap gap-1.5 max-h-48 overflow-y-auto p-2 border rounded-lg bg-slate-50/50">
                          {visibleColors.length === 0 && <p role="status" className="p-2 text-xs text-slate-500">No hay colores que coincidan con la búsqueda.</p>}
                          {visibleColors.map((c: any) => {
                            const isSelected = selectedColores.includes(c.id)
                            return (
                              <button
                                key={c.id}
                                type="button"
                                aria-pressed={isSelected}
                                disabled={addingVariants}
                                onClick={() =>
                                  setSelectedColores((prev) =>
                                    prev.includes(c.id) ? prev.filter((x) => x !== c.id) : [...prev, c.id]
                                  )
                                }
                                className={`px-2.5 py-1.5 rounded-lg text-xs font-semibold border transition-all ${
                                  isSelected
                                    ? "bg-orange-600 border-orange-600 text-white shadow-sm"
                                    : "bg-white border-slate-200 text-slate-700 hover:bg-slate-100"
                                }`}
                              >
                                {c.nombre}
                              </button>
                            )
                          })}
                        </div>
                      </div>

                      {/* Tallas */}
                      <div>
                        <Label className="text-xs font-bold text-slate-700 mb-2 block">
                          Tallas Disponibles ({selectedTallas.length} seleccionadas)
                        </Label>
                        <div className="flex flex-wrap gap-1.5 max-h-48 overflow-y-auto p-2 border rounded-lg bg-slate-50/50">
                          {orderedSizes.map((t: any) => {
                            const isSelected = selectedTallas.includes(t.id)
                            return (
                              <button
                                key={t.id}
                                type="button"
                                aria-pressed={isSelected}
                                disabled={addingVariants}
                                onClick={() =>
                                  setSelectedTallas((prev) =>
                                    prev.includes(t.id) ? prev.filter((x) => x !== t.id) : [...prev, t.id]
                                  )
                                }
                                className={`px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all ${
                                  isSelected
                                    ? "bg-orange-600 border-orange-600 text-white shadow-sm"
                                    : "bg-white border-slate-200 text-slate-700 hover:bg-slate-100"
                                }`}
                              >
                                {t.nombre}
                              </button>
                            )
                          })}
                        </div>
                      </div>
                    </div>
                  </section>
                )}
              </div>
            )}
          </div>
        )}

        {/* ────────────────────── CREATE TEMPLATE VIEW ────────────────────── */}
        {isCreatingTemplate && (
          <div className="absolute inset-0 z-40 bg-white flex flex-col animate-in fade-in-0 duration-200">
            <div className="flex items-center justify-between px-8 py-5 border-b bg-white shrink-0">
              <div className="flex items-center gap-4">
                <button
                  onClick={() => setIsCreatingTemplate(false)}
                  className="flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg"
                >
                  <ArrowLeft className="h-3.5 w-3.5" /> Volver al Catálogo
                </button>
                <div className="h-5 w-px bg-slate-200" />
                <div>
                  <h2 className="text-lg font-bold text-slate-900">Nueva Plantilla Base (PTE)</h2>
                  <p className="text-xs text-slate-400">
                    Se creará en estado <b>Borrador</b> sin variantes ni stock inicial.
                  </p>
                </div>
              </div>
            </div>

            <form onSubmit={handleCreateTemplate} className="flex-1 overflow-y-auto px-8 py-6 space-y-6 bg-slate-50/50">
              <div className="bg-white rounded-xl border p-6 space-y-5 shadow-sm max-w-4xl mx-auto">
                {/* Nombre y Precio */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                  <div className="sm:col-span-2">
                    <Label className="text-xs font-bold text-slate-700">Nombre del Producto Base *</Label>
                    <Input
                      required
                      placeholder="Ej: Parka Impermeable Térmica"
                      className="mt-1.5 h-10 text-sm"
                      value={templateForm.nombre}
                      onChange={(e) => setTemplateForm({ ...templateForm, nombre: e.target.value })}
                    />
                  </div>
                  <div>
                    <Label className="text-xs font-bold text-slate-700">Precio Sugerido (CLP)</Label>
                    <Input
                      type="number"
                      placeholder="Ej: 34990"
                      className="mt-1.5 h-10 text-sm font-mono"
                      value={templateForm.precio_venta}
                      onChange={(e) => setTemplateForm({ ...templateForm, precio_venta: e.target.value })}
                    />
                  </div>
                </div>

                {/* Segmentos del Catálogo Canónico */}
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 pt-2 border-t">
                  <div>
                    <Label className="text-xs font-semibold text-slate-600">Unidad de Negocio *</Label>
                    <select
                      className="mt-1 w-full h-9 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-800"
                      value={templateForm.unidad_negocio_id}
                      onChange={(e) => setTemplateForm({ ...templateForm, unidad_negocio_id: Number(e.target.value) })}
                    >
                      {catalogos.unidades?.map((u: any) => (
                        <option key={u.id} value={u.id}>{u.nombre}</option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <Label className="text-xs font-semibold text-slate-600">Origen *</Label>
                    <select
                      className="mt-1 w-full h-9 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-800"
                      value={templateForm.origen_id}
                      onChange={(e) => setTemplateForm({ ...templateForm, origen_id: Number(e.target.value) })}
                    >
                      {catalogos.origenes?.map((o: any) => (
                        <option key={o.id} value={o.id}>{o.nombre}</option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <Label className="text-xs font-semibold text-slate-600">Grupo Familia *</Label>
                    <select
                      className="mt-1 w-full h-9 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-800"
                      value={templateForm.grp_familia_id}
                      onChange={(e) => setTemplateForm({ ...templateForm, grp_familia_id: Number(e.target.value) })}
                    >
                      {catalogos.grupos?.map((g: any) => (
                        <option key={g.id} value={g.id}>{g.nombre}</option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <Label className="text-xs font-semibold text-slate-600">Familia *</Label>
                    <select
                      className="mt-1 w-full h-9 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-800"
                      value={templateForm.familia_id}
                      onChange={(e) => {
                        const familiaId = Number(e.target.value)
                        // La subfamilia elegida deja de ser válida si pertenece a otra familia.
                        const sigueValida = subfamiliaPerteneceA(templateForm.subfamilia_id, familiaId)
                        setTemplateForm({
                          ...templateForm,
                          familia_id: familiaId,
                          subfamilia_id: sigueValida ? templateForm.subfamilia_id : 0,
                        })
                      }}
                    >
                      <option value={-1}>-- Seleccione Familia --</option>
                      {catalogos.familias?.map((f: any) => (
                        <option key={f.id} value={f.id}>{f.nombre}</option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <div>
                    <Label className="text-xs font-semibold text-slate-600">Subfamilia</Label>
                    <select
                      className="mt-1 w-full h-9 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-800"
                      value={templateForm.subfamilia_id}
                      onChange={(e) => setTemplateForm({ ...templateForm, subfamilia_id: Number(e.target.value) })}
                    >
                      {subfamiliasDeFamilia.map((s: any) => (
                        <option key={s.id} value={s.id}>{s.nombre}</option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <Label className="text-xs font-semibold text-slate-600">Tipo de Familia</Label>
                    <select
                      className="mt-1 w-full h-9 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-800"
                      value={templateForm.familia_tipo_id}
                      onChange={(e) => setTemplateForm({ ...templateForm, familia_tipo_id: Number(e.target.value) })}
                    >
                      {catalogos.tipos?.map((t: any) => (
                        <option key={t.id} value={t.id}>{t.nombre}</option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <Label className="text-xs font-semibold text-slate-600">Formato de Familia</Label>
                    <select
                      className="mt-1 w-full h-9 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-800"
                      value={templateForm.familia_formato_id}
                      onChange={(e) => setTemplateForm({ ...templateForm, familia_formato_id: Number(e.target.value) })}
                    >
                      {catalogos.formatos?.map((f: any) => (
                        <option key={f.id} value={f.id}>{f.nombre}</option>
                      ))}
                    </select>
                  </div>
                </div>

                {/* Géneros */}
                <div className="pt-2 border-t space-y-2">
                  <Label className="text-xs font-bold text-slate-700">Género(s) del Producto</Label>
                  <div className="flex flex-wrap gap-2">
                    {catalogos.generos?.map((g: any) => {
                      const isSelected = templateForm.generos.includes(g.id)
                      return (
                        <button
                          key={g.id}
                          type="button"
                          onClick={() => {
                            setTemplateForm((prev) => ({
                              ...prev,
                              generos: isSelected
                                ? prev.generos.filter((x) => x !== g.id)
                                : [...prev.generos, g.id],
                            }))
                          }}
                          className={`px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all ${
                            isSelected
                              ? "bg-blue-600 border-blue-600 text-white shadow-sm"
                              : "bg-white border-slate-200 text-slate-700 hover:bg-slate-100"
                          }`}
                        >
                          {g.nombre}
                        </button>
                      )
                    })}
                  </div>
                </div>

                <div className="pt-2 border-t flex items-center justify-between">
                  <div className="space-y-0.5 pr-4">
                    <Label htmlFor="template-permite-cotizar" className="text-xs font-bold text-slate-700">Permite cotización</Label>
                    <p id="template-cotizar-description" className="text-xs text-slate-500">Podrá seleccionarse en cotizaciones cuando la plantilla esté publicada y tenga variantes activas.</p>
                  </div>
                  <Switch id="template-permite-cotizar" aria-describedby="template-cotizar-description"
                    checked={templateForm.permite_cotizar} disabled={submittingTemplate}
                    onCheckedChange={permite_cotizar => setTemplateForm({ ...templateForm, permite_cotizar })} />
                </div>

                <div className="pt-2 border-t flex items-center justify-between">
                  <div className="pr-4"><Label htmlFor="template-modo-kit" className="text-xs font-bold text-slate-700">Modo Kit/Traje</Label><p className="text-xs text-slate-500">Después de crear la plantilla, define sus productos hijos en el catálogo.</p></div>
                  <Switch id="template-modo-kit" checked={templateForm.modo_kit} disabled={submittingTemplate} onCheckedChange={modo_kit => setTemplateForm({ ...templateForm, modo_kit })} />
                </div>

                {/* Permite Personalización */}
                <div className="pt-2 border-t flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label className="text-xs font-bold text-slate-700">Permite Personalización</Label>
                    <p className="text-xs text-slate-500">
                      Habilita si la prenda o producto admite bordados, parches, estampados o insignias en el cotizador.
                    </p>
                  </div>
                  <Switch
                    checked={templateForm.permite_personalizar}
                    onCheckedChange={(v) => setTemplateForm({ ...templateForm, permite_personalizar: v })}
                  />
                </div>

                {/* Botón Guardar */}
                <div className="pt-4 border-t flex justify-end gap-3">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setIsCreatingTemplate(false)}
                    disabled={submittingTemplate}
                  >
                    Cancelar
                  </Button>
                  <Button
                    type="submit"
                    className="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6"
                    disabled={submittingTemplate}
                  >
                    {submittingTemplate && <Loader2 className="h-4 w-4 animate-spin mr-2" />}
                    Crear Plantilla Base
                  </Button>
                </div>
              </div>
            </form>
          </div>
        )}

        {/* ────────────────────────── CATALOGUE GRID ───────────────────────── */}

        {/* Header */}
        <div className="px-8 py-5 border-b bg-white shrink-0">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <DialogTitle className="text-xl font-bold text-slate-900">Catálogo Comercial de Productos PTE</DialogTitle>
              <DialogDescription className="text-xs text-slate-500 mt-0.5">
                Centro único para administrar plantillas, variantes, estados de catálogo y sincronización con cotizaciones.
              </DialogDescription>
            </div>

            <div className="flex items-center gap-3">
              <div className="relative">
                <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                <Input
                  placeholder="Buscar por nombre o SKU…"
                  className="pl-9 w-64 md:w-80 h-9 bg-slate-50 border-slate-200 text-xs"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                />
              </div>

              {canManageStock && (
                <Button
                  size="sm"
                  className="bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs h-9 px-4 gap-1.5 shadow-sm"
                  onClick={() => setIsCreatingTemplate(true)}
                >
                  <Plus className="h-4 w-4" />
                  Nueva Plantilla
                </Button>
              )}
            </div>
          </div>

          {/* Filtros de Estado por Pestaña */}
          <div className="flex items-center gap-2 mt-4 pt-3 border-t">
            {(
              [
                { key: "todos", label: "Todos los Estados" },
                { key: "publicado", label: "Publicados" },
                { key: "borrador", label: "Borradores" },
                { key: "archivado", label: "Archivados" },
              ] as const
            ).map((tab) => {
              const active = estadoFilter === tab.key
              return (
                <button
                  key={tab.key}
                  type="button"
                  onClick={() => {
                    setEstadoFilter(tab.key)
                    setPage(1)
                  }}
                  className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all border ${
                    active
                      ? "bg-slate-900 border-slate-900 text-white shadow-sm"
                      : "bg-white border-slate-200 text-slate-600 hover:bg-slate-100"
                  }`}
                >
                  {tab.label}
                </button>
              )
            })}
          </div>
        </div>

        {/* Grid de Productos */}
        <div className="flex-1 overflow-y-auto px-8 py-6 bg-slate-50/40">
          {loading ? (
            <div className="flex flex-col items-center justify-center h-64 gap-3 text-slate-400">
              <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
              <p className="text-sm font-medium">Consultando catálogo paginado…</p>
            </div>
          ) : skus.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-64 gap-3 text-slate-400">
              <Package className="h-14 w-14 opacity-20" />
              <p className="text-base font-medium">No se encontraron productos en este estado o criterio.</p>
            </div>
          ) : (
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
              {skus.filter((item) => !isDecorativeItem(item)).map((item) => {
                const isArchived = item.estado_catalogo === "archivado"
                const isDraft = item.estado_catalogo === "borrador"
                return (
                  <button
                    key={item.id}
                    onClick={() => openDetail(item.id)}
                    className={`group text-left bg-white border rounded-xl overflow-hidden transition-all duration-200 hover:shadow-md hover:border-blue-300 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-blue-400/20 ${
                      isArchived ? "opacity-60 grayscale bg-slate-50" : ""
                    }`}
                  >
                    {/* Image */}
                    <div className="aspect-square bg-slate-100/60 flex items-center justify-center overflow-hidden relative">
                      {item.imagen_url ? (
                        <img
                          src={imgUrl(item.imagen_url)!}
                          alt={item.nombre}
                          className="h-full w-full object-contain p-4 group-hover:scale-105 transition-transform duration-300"
                        />
                      ) : (
                        <Box className="h-8 w-8 text-slate-300" />
                      )}

                      {/* Badge de Estado sobre la tarjeta */}
                      <div className="absolute top-2 right-2">
                        {item.estado_catalogo === "publicado" && (
                          <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                            Publicado
                          </span>
                        )}
                        {item.estado_catalogo === "borrador" && (
                          <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                            Borrador
                          </span>
                        )}
                        {item.estado_catalogo === "archivado" && (
                          <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-zinc-200 text-zinc-700">
                            Archivado
                          </span>
                        )}
                      </div>
                    </div>

                    {/* Info */}
                    <div className="px-3.5 py-3 space-y-1">
                      <p className="text-[10px] font-mono text-slate-400 leading-none">{item.sku_base}</p>
                      <h4 className="text-xs font-bold text-slate-800 leading-tight line-clamp-2">
                        {item.nombre}
                      </h4>
                      <div className="flex items-center justify-between pt-1">
                        <span className="text-xs font-extrabold text-slate-900">
                          ${item.precio_venta?.toLocaleString("es-CL") ?? "0"}
                        </span>
                      </div>
                    </div>
                  </button>
                )
              })}
            </div>
          )}
        </div>

        {/* Footer de Paginación */}
        <div className="px-8 py-3.5 border-t bg-white flex items-center justify-between shrink-0 text-xs text-slate-600">
          <p className="font-medium">
            Página <span className="font-bold text-slate-900">{page}</span> de{" "}
            <span className="font-bold text-slate-900">{lastPage}</span> ·{" "}
            <span className="font-bold text-slate-900">{total}</span> productos registrados
          </p>

          <div className="flex items-center gap-2">
            <Button
              size="sm"
              variant="outline"
              className="h-8 px-3 text-xs gap-1 border-slate-200"
              disabled={page <= 1 || loading}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
            >
              <ChevronLeft className="h-3.5 w-3.5" /> Anterior
            </Button>
            <Button
              size="sm"
              variant="outline"
              className="h-8 px-3 text-xs gap-1 border-slate-200"
              disabled={page >= lastPage || loading}
              onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
            >
              Siguiente <ChevronRight className="h-3.5 w-3.5" />
            </Button>
          </div>
        </div>

        {/* ────────────────────── ARCHIVE IMPACT DIALOG ────────────────────── */}
        <AlertDialog open={archiveDialogOpen} onOpenChange={setArchiveDialogOpen}>
          <AlertDialogContent className="max-w-md bg-white">
            <AlertDialogHeader>
              <AlertDialogTitle>¿Archivar este producto?</AlertDialogTitle>
              <AlertDialogDescription className="text-xs text-slate-500">
                Al archivar, la plantilla y sus variantes quedarán ocultas para nuevas cotizaciones comerciales.
                El stock histórico y los registros existentes se conservarán intactos.
              </AlertDialogDescription>
            </AlertDialogHeader>

            {loadingPreview ? (
              <div className="py-6 flex flex-col items-center justify-center gap-2 text-slate-400">
                <Loader2 className="h-6 w-6 animate-spin text-blue-600" />
                <p className="text-xs font-medium">Calculando impacto en inventario y peticiones…</p>
              </div>
            ) : archivePreview ? (
              <div className="space-y-4 py-2">
                <div className="grid grid-cols-2 gap-3">
                  <div className="bg-slate-50 border rounded-lg p-3 text-center">
                    <p className="text-[10px] font-semibold text-slate-500 uppercase">Variantes Afectadas</p>
                    <p className="text-xl font-bold text-slate-800 mt-0.5">
                      {archivePreview.variantes_afectadas}
                    </p>
                  </div>
                  <div className="bg-slate-50 border rounded-lg p-3 text-center">
                    <p className="text-[10px] font-semibold text-slate-500 uppercase">Stock Físico Total</p>
                    <p className="text-xl font-bold text-slate-800 mt-0.5">
                      {archivePreview.stock_fisico_total}
                    </p>
                  </div>
                  <div className="bg-amber-50/60 border border-amber-200 rounded-lg p-3 text-center">
                    <p className="text-[10px] font-semibold text-amber-700 uppercase">Stock Reservado</p>
                    <p className="text-xl font-bold text-amber-800 mt-0.5">
                      {archivePreview.stock_reservado}
                    </p>
                  </div>
                  <div className="bg-blue-50/60 border border-blue-200 rounded-lg p-3 text-center">
                    <p className="text-[10px] font-semibold text-blue-700 uppercase">Peticiones Pendientes</p>
                    <p className="text-xl font-bold text-blue-800 mt-0.5">
                      {archivePreview.solicitudes_pendientes}
                    </p>
                  </div>
                </div>

                <div>
                  <Label className="text-xs font-bold text-slate-700">Motivo del Archivado (Opcional)</Label>
                  <Input
                    placeholder="Ej: Fin de temporada, descontinuado por fábrica…"
                    className="mt-1.5 h-9 text-xs"
                    value={archiveReason}
                    onChange={(e) => setArchiveReason(e.target.value)}
                  />
                </div>
              </div>
            ) : null}

            <AlertDialogFooter>
              <AlertDialogCancel disabled={archiving}>Cancelar</AlertDialogCancel>
              <AlertDialogAction
                disabled={archiving || loadingPreview}
                onClick={(e) => {
                  e.preventDefault()
                  void handleConfirmArchive()
                }}
                className="bg-red-600 text-white hover:bg-red-700 text-xs font-semibold"
              >
                {archiving && <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />}
                Confirmar y Archivar
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>

        {/* Diálogo de confirmación para publicar activando variantes */}
        <AlertDialog open={publishConfirmOpen} onOpenChange={setPublishConfirmOpen}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle className="flex items-center gap-2 text-emerald-700">
                <CheckCircle2 className="h-5 w-5 text-emerald-600" />
                ¿Activar variantes y publicar?
              </AlertDialogTitle>
              <AlertDialogDescription className="space-y-2 text-xs text-slate-600 pt-1">
                <span>
                  Esta plantilla tiene todas sus variantes archivadas. Para publicarla en el catálogo comercial,
                  se reactivarán automáticamente sus {detail?.variants?.length ?? 0} variantes.
                </span>
                <span className="block font-medium text-slate-800">
                  ¿Deseas reactivar todas las variantes y publicar el producto ahora?
                </span>
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel disabled={publishing}>Cancelar</AlertDialogCancel>
              <AlertDialogAction
                disabled={publishing}
                onClick={(e) => {
                  e.preventDefault()
                  void handlePublish(true)
                }}
                className="bg-emerald-600 text-white hover:bg-emerald-700 text-xs font-semibold"
              >
                {publishing && <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />}
                Activar Variantes y Publicar
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>

      </DialogContent>
    </Dialog>
  )
}
