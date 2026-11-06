import { useState } from "react"
import { FechaInput } from "@/components/ui/fecha-input"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"
import { Image as ImageIcon } from "lucide-react"
import { api } from "@/lib/api"
import { toast } from "sonner"
import { Region, Provincia, useComunas } from "@/hooks/use-geografia"
import { cambiarComuna, cambiarRegion } from "@/lib/ubicacion"
import { formatRut, validateRut } from "@/lib/utils"
import { ContactosSection } from "./ContactosSection"
import { ComunaSelect } from "./ComunaSelect"
import { GiroSelect } from "./GiroSelect"
import type { Cuerpo } from "@/lib/red-bomberos"

export type ClientData = {
    id?: string
    kind: "cuerpo" | "compania"
    nombre_empresa: string
    telefono: string
    correo: string
    region_id?: number | null
    provincia_id?: number | null
    comuna_id?: number | null
    rut_empresa?: string | null
    fecha_fundacion?: string | null
    direccion?: string | null
    giro?: string | null
    numero_companias?: number | null
    numero_compania?: number | null
    logo_url?: string | null
    /** El Cuerpo dueño, solo cuando kind === "compania". */
    cuerpo?: Cuerpo | null
}

type Props = {
    data: ClientData
    onChange: (data: ClientData) => void
    error?: string | null
    regiones: Region[]
    provincias: Provincia[]
}

export function ClientForm({ data, onChange, error, regiones, provincias }: Props) {
    const { comunas } = useComunas()
    const esCompania = data.kind === "compania"
    // El RUT se valida al salir del campo para avisar antes de guardar; el
    // backend lo vuelve a validar igual (App\Rules\RutChileno).
    const [rutError, setRutError] = useState<string | null>(null)

    const validarRutAlSalir = () => {
        const rut = data.rut_empresa?.trim()
        setRutError(rut && !validateRut(rut) ? "RUT inválido: revisa el dígito verificador." : null)
    }

    const handleChange = (field: keyof ClientData, value: any) => {
        onChange({ ...data, [field]: value })
    }

    return (
        <div className="grid gap-5">
            <div className="grid gap-3 border p-3 rounded-md">
                <h3 className="font-medium text-sm text-gray-500">Datos Empresa</h3>
                <div className="grid gap-1">
                    <Label>Nombre {esCompania ? "Compañía" : "cuerpo (Empresa)"}</Label>
                    <Input
                        value={data.nombre_empresa}
                        onChange={(e) => handleChange("nombre_empresa", e.target.value)}
                        placeholder={esCompania ? "Ej: 1ra Compañía" : "Ej: Cuerpo de Bomberos de Santiago"}
                        disabled={!!data.id && !esCompania} // Solo se bloquea el nombre del cuerpo al editar, no de compañía.
                    />
                </div>

                {!esCompania && (
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label>Región</Label>
                            <select
                                value={data.region_id || ""}
                                onChange={(e) => {
                                    const val = e.target.value;
                                    // La comuna se conserva si pertenece a la región elegida; si no, hay que volver a elegirla.
                                    onChange({ ...data, ...cambiarRegion(data, val === "" ? null : Number(val), comunas) })
                                }}
                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">{regiones.length === 0 ? "Cargando..." : "Seleccione Región"}</option>
                                {regiones.map(r => (
                                    <option key={r.id} value={r.id}>{r.nombre}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                )}

                {esCompania && (
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label>N° de Compañía</Label>
                            <Input
                                type="number"
                                value={data.numero_compania || ""}
                                onChange={(e) => handleChange("numero_compania", Number(e.target.value))}
                                placeholder="Ej: 1, 2, 3..."
                                disabled={!!data.id} // Solo bloqueado al editar
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label>
                                Fecha Fundación <span className="font-normal text-muted-foreground">(opcional)</span>
                            </Label>
                            <FechaInput conAnios
                                value={data.fecha_fundacion || ""}
                                onChange={v => handleChange("fecha_fundacion", v)}
                            />
                        </div>
                    </div>
                )}
            </div>

            {/* CUERPO ASOCIADO (para Companias) - "Datos Cuerpo de Bomberos Asociado" */}
            {esCompania && data.cuerpo && (
                <div className="grid gap-3 border p-3 rounded-md bg-blue-50 border-blue-200">
                    <h3 className="font-medium text-sm text-blue-700">Datos Cuerpo de Bomberos Asociado</h3>
                    <div className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span className="font-semibold block text-blue-900">Cuerpo:</span>
                            <span className="text-blue-800">{data.cuerpo.nombre}</span>
                        </div>
                        <div>
                            <span className="font-semibold block text-blue-900">RUT:</span>
                            <span className="text-blue-800">{data.cuerpo.rut || "—"}</span>
                        </div>
                        <div>
                            <span className="font-semibold block text-blue-900">Dirección:</span>
                            <span className="text-blue-800">{data.cuerpo.direccion || "—"}</span>
                        </div>
                        <div>
                            <span className="font-semibold block text-blue-900">Fecha Fundación:</span>
                            <span className="text-blue-800">{data.cuerpo.fecha_fundacion || "—"}</span>
                        </div>
                        <div>
                            <span className="font-semibold block text-blue-900">N° Compañías:</span>
                            <span className="text-blue-800">{data.cuerpo.numero_companias || "—"}</span>
                        </div>
                    </div>
                </div>
            )}

            {/* Detalles Institucionales - Hide for Companies */}
            {!esCompania && (
                <div className="grid gap-3 border p-3 rounded-md">
                    <div className="flex items-center justify-between">
                        <h3 className="font-medium text-sm text-gray-500">Detalles Institucionales</h3>
                    </div>

                    <div className="flex flex-col md:flex-row gap-5">
                        {/* Logo Upload/Viz Display */}
                        <div className="flex flex-col items-center justify-start gap-2 min-w-[120px]">
                            <div className="w-24 h-24 rounded-md border border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden">
                                {data.logo_url ? (
                                    <img
                                        src={data.logo_url.startsWith('http') ? data.logo_url : `${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}${data.logo_url}`}
                                        alt="Logo FD"
                                        className="max-w-full max-h-full object-contain p-1"
                                    />
                                ) : (
                                    <ImageIcon className="h-8 w-8 text-slate-300" />
                                )}
                            </div>

                            {data.id ? (
                                <div className="relative w-full">
                                    <input
                                        type="file"
                                        className="absolute inset-0 cursor-pointer opacity-0"
                                        accept=".jpg,.jpeg,.png,.webp,.svg"
                                        onChange={async (e) => {
                                            const file = e.target.files?.[0]
                                            if (file) {
                                                if (file.size > 5 * 1024 * 1024) {
                                                    toast.error("La imagen debe pesar menos de 5MB")
                                                    e.target.value = ""
                                                    return
                                                }
                                                toast.info("Subiendo logo...")
                                                try {
                                                    const formData = new FormData()
                                                    formData.append("image", file)
                                                    const res = await api.post(`/api/cuerpos/${data.id}/logo`, formData)
                                                    handleChange("logo_url", res.url)
                                                    toast.success("Logo insertado correctamente")
                                                } catch (err) {
                                                    toast.error("Error subiendo el logo")
                                                }
                                            }
                                            e.target.value = ""
                                        }}
                                    />
                                    <Button type="button" variant="outline" size="sm" className="w-full text-xs h-7">
                                        Cambiar Logo
                                    </Button>
                                </div>
                            ) : (
                                <p className="text-[10px] text-center text-slate-400">Guarda el cliente para adjuntar logo</p>
                            )}
                        </div>

                        {/* Campos de texto */}
                        <div className="flex-1 grid gap-3">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-1 content-start">
                                    <Label>RUT Empresa/Cuerpo</Label>
                                    <Input
                                        value={data.rut_empresa || ""}
                                        onChange={(e) => {
                                            handleChange("rut_empresa", formatRut(e.target.value))
                                            if (rutError) setRutError(null)
                                        }}
                                        onBlur={validarRutAlSalir}
                                        aria-invalid={!!rutError}
                                        className={rutError ? "border-red-500 focus-visible:ring-red-500" : undefined}
                                        placeholder="12.345.678-9"
                                    />
                                    {rutError && <p className="text-xs text-red-600">{rutError}</p>}
                                </div>
                                <div className="grid gap-1 content-start">
                                    <Label>
                                        Fecha Fundación{" "}
                                        <span className="font-normal text-muted-foreground">(opcional)</span>
                                    </Label>
                                    <FechaInput conAnios
                                        value={data.fecha_fundacion || ""}
                                        onChange={v => handleChange("fecha_fundacion", v)}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-1">
                                    <Label>Dirección</Label>
                                    <Input
                                        value={data.direccion || ""}
                                        onChange={(e) => handleChange("direccion", e.target.value)}
                                        placeholder="Calle y número"
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label>Comuna</Label>
                                    <ComunaSelect
                                        value={data.comuna_id ?? null}
                                        onChange={(comunaId) => onChange({ ...data, ...cambiarComuna(data, comunaId, comunas) })}
                                        regionId={data.region_id ?? null}
                                        regiones={regiones}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-1">
                                <Label>Giro</Label>
                                <GiroSelect
                                    value={data.giro || ""}
                                    onChange={(v) => handleChange("giro", v)}
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-1">
                                    <Label>N° Compañías</Label>
                                    <Input
                                        type="number"
                                        value={data.numero_companias || ""}
                                        onChange={(e) => handleChange("numero_companias", Number(e.target.value))}
                                    />
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            )}




            {/* CONTACTS SECTION - Only if editing */}
            {data.id && <ContactosSection entityId={data.id} kind={data.kind} entityNombre={data.nombre_empresa} />}

            {error && <div className="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">{error}</div>}
        </div>
    )
}
