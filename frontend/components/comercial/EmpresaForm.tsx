"use client"

import { useState } from "react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Image as ImageIcon } from "lucide-react"
import { api } from "@/lib/api"
import { toast } from "sonner"
import { Region, Provincia, useComunas } from "@/hooks/use-geografia"
import { cambiarComuna, cambiarProvincia, cambiarRegion } from "@/lib/ubicacion"
import { formatPhone, formatRut, validateRut } from "@/lib/utils"
import { CargoSelector } from "./CargoSelector"
import { ContactosSection } from "./ContactosSection"
import { ComunaSelect } from "./ComunaSelect"
import { GiroSelect } from "./GiroSelect"

export type EmpresaContacto = {
    nombre: string
    rut: string
    telefono: string
    email: string
    cargo: string
    cargo_id: number | null
}

export const emptyEmpresaContacto: EmpresaContacto = {
    nombre: "",
    rut: "",
    telefono: "",
    email: "",
    cargo: "",
    cargo_id: null,
}

export type EmpresaData = {
    id?: number | string
    nombre_empresa: string
    rut_empresa?: string | null
    giro?: string | null
    direccion?: string | null
    telefono?: string | null
    correo?: string | null
    region_id?: number | null
    provincia_id?: number | null
    comuna_id?: number | null
    logo_url?: string | null
    contacto: EmpresaContacto
}

export const emptyEmpresa: EmpresaData = {
    nombre_empresa: "",
    rut_empresa: "",
    giro: "",
    direccion: "",
    telefono: "",
    correo: "",
    region_id: null,
    provincia_id: null,
    comuna_id: null,
    logo_url: null,
    contacto: { ...emptyEmpresaContacto },
}

type Props = {
    /** "particular" = persona natural: sin giro ni persona de contacto aparte. */
    tipo?: "empresa" | "particular"
    data: EmpresaData
    onChange: (data: EmpresaData) => void
    error?: string | null
    regiones: Region[]
    provincias: Provincia[]
}

export function EmpresaForm({ tipo = "empresa", data, onChange, error, regiones, provincias }: Props) {
    const esPersona = tipo === "particular"
    const { comunas } = useComunas()
    const [autocompletarContacto, setAutocompletarContacto] = useState(false)
    // El RUT se valida al salir del campo para avisar antes de guardar; el
    // backend lo vuelve a validar igual (App\Rules\RutChileno).
    const [rutError, setRutError] = useState<string | null>(null)

    const validarRutAlSalir = () => {
        const rut = data.rut_empresa?.trim()
        setRutError(rut && !validateRut(rut) ? "RUT inválido: revisa el dígito verificador." : null)
    }

    const handleChange = (field: keyof EmpresaData, value: any) => {
        onChange({ ...data, [field]: value })
    }

    const toggleAutocompletar = (checked: boolean) => {
        setAutocompletarContacto(checked)
        if (checked) {
            onChange({
                ...data,
                contacto: {
                    ...data.contacto,
                    nombre: data.nombre_empresa,
                    telefono: data.telefono || "",
                    email: data.correo || "",
                    rut: data.rut_empresa || "",
                },
            })
        } else {
            onChange({ ...data, contacto: { ...emptyEmpresaContacto } })
        }
    }

    const handleContactoChange = (field: keyof EmpresaContacto, value: any) => {
        onChange({ ...data, contacto: { ...data.contacto, [field]: value } })
    }

    const filteredProvincias = data.region_id
        ? provincias.filter(p => p.region_id === Number(data.region_id))
        : provincias

    const selectClass = "flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"

    return (
        <div className="grid gap-5">
            <div className="grid gap-3 border p-3 rounded-md">
                <h3 className="font-medium text-sm text-gray-500">{esPersona ? "Datos Persona Natural" : "Datos Empresa / Cliente"}</h3>

                <div className="flex flex-col md:flex-row gap-5">
                    {/* Logo - solo al editar (necesita el ID del cliente) */}
                    <div className="flex flex-col items-center justify-start gap-2 min-w-[120px]">
                        <div className="w-24 h-24 rounded-md border border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden">
                            {data.logo_url ? (
                                <img
                                    src={data.logo_url.startsWith('http') ? data.logo_url : `${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}${data.logo_url}`}
                                    alt="Logo"
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
                                                const res = await api.post(`/api/clientes/${data.id}/logo`, formData)
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

                    <div className="flex-1 grid gap-3">
                        <div className="grid gap-1">
                            <Label>{esPersona ? "Nombre completo" : "Nombre empresa / cliente"} <span className="text-red-500">*</span></Label>
                            <Input
                                value={data.nombre_empresa}
                                onChange={(e) => handleChange("nombre_empresa", e.target.value)}
                                placeholder={esPersona ? "Ej: Juan Pérez Soto" : "Ej: Constructora Los Andes SpA"}
                            />
                        </div>

                        <div className={esPersona ? "grid gap-3" : "grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)] gap-3"}>
                            <div className="grid gap-1 content-start">
                                <Label>RUT <span className="text-xs text-muted-foreground">(opcional)</span></Label>
                                <Input
                                    value={data.rut_empresa || ""}
                                    onChange={(e) => {
                                        handleChange("rut_empresa", formatRut(e.target.value))
                                        if (rutError) setRutError(null)
                                    }}
                                    onBlur={validarRutAlSalir}
                                    aria-invalid={!!rutError}
                                    className={rutError ? "border-red-500 focus-visible:ring-red-500" : undefined}
                                    placeholder={esPersona ? "12.345.678-9" : "76.123.456-7"}
                                />
                                {rutError && <p className="text-xs text-red-600">{rutError}</p>}
                            </div>
                            {!esPersona && (
                                <div className="grid gap-1 content-start">
                                    <Label>Giro <span className="text-xs text-muted-foreground">(opcional)</span></Label>
                                    <GiroSelect
                                        value={data.giro || ""}
                                        onChange={(v) => handleChange("giro", v)}
                                    />
                                </div>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-1">
                                <Label>Teléfono <span className="text-xs text-muted-foreground">(opcional)</span></Label>
                                <Input
                                    value={data.telefono || ""}
                                    onChange={(e) => handleChange("telefono", formatPhone(e.target.value))}
                                    placeholder="+569 xxxxxxxx"
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label>Correo <span className="text-xs text-muted-foreground">(opcional)</span></Label>
                                <Input
                                    type="email"
                                    value={data.correo || ""}
                                    onChange={(e) => handleChange("correo", e.target.value)}
                                    placeholder={esPersona ? "persona@correo.cl" : "contacto@empresa.cl"}
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-1">
                                <Label>Dirección <span className="text-xs text-muted-foreground">(opcional)</span></Label>
                                <Input
                                    value={data.direccion || ""}
                                    onChange={(e) => handleChange("direccion", e.target.value)}
                                    placeholder="Calle y número"
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label>Comuna <span className="text-xs text-muted-foreground">(opcional)</span></Label>
                                <ComunaSelect
                                    value={data.comuna_id ?? null}
                                    // Elegir la comuna completa región y provincia (queda coherente aunque se elija primero)
                                    onChange={(comunaId) => onChange({ ...data, ...cambiarComuna(data, comunaId, comunas) })}
                                    regionId={data.region_id ?? null}
                                    regiones={regiones}
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="grid gap-3 border p-3 rounded-md">
                <h3 className="font-medium text-sm text-gray-500">Ubicación <span className="text-xs font-normal">(opcional)</span></h3>
                <div className="grid grid-cols-2 gap-3">
                    <div className="grid gap-1">
                        <Label>Región</Label>
                        <select
                            value={data.region_id || ""}
                            onChange={(e) => {
                                const val = e.target.value
                                // Conserva la comuna si pertenece a la región elegida (antes se borraba siempre)
                                onChange({ ...data, ...cambiarRegion(data, val === "" ? null : Number(val), comunas) })
                            }}
                            className={selectClass}
                        >
                            <option value="">{regiones.length === 0 ? "Cargando..." : "Seleccione Región"}</option>
                            {regiones.map(r => (
                                <option key={r.id} value={r.id}>{r.nombre}</option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1">
                        <Label>Provincia</Label>
                        <select
                            value={data.provincia_id || ""}
                            onChange={(e) => {
                                const val = e.target.value
                                onChange({ ...data, ...cambiarProvincia(data, val === "" ? null : Number(val), comunas) })
                            }}
                            disabled={!data.region_id}
                            className={selectClass}
                        >
                            <option value="">Seleccione Provincia</option>
                            {filteredProvincias.map(p => (
                                <option key={p.id} value={p.id}>{p.nombre}</option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>

            {/* CONTACTO INICIAL - solo al crear; al editar se usa la lista de contactos */}
            {!data.id && !esPersona && (
                <div className="grid gap-3 border p-3 rounded-md bg-gray-50">
                    <h3 className="font-medium text-sm text-gray-700">Persona de Contacto <span className="text-xs font-normal text-muted-foreground">(opcional)</span></h3>

                    <label className="flex items-center gap-2 text-sm cursor-pointer">
                        <Checkbox
                            checked={autocompletarContacto}
                            onCheckedChange={(v) => toggleAutocompletar(v === true)}
                        />
                        <span>Autocompletar con los datos ingresados arriba (nombre, RUT, teléfono y correo)</span>
                    </label>

                    <div className="grid gap-1">
                        <Label>Nombre completo</Label>
                        <Input
                            value={data.contacto.nombre}
                            onChange={(e) => handleContactoChange("nombre", e.target.value)}
                            placeholder="Ej: Juan Pérez"
                        />
                    </div>

                    <CargoSelector
                        value={data.contacto.cargo_id}
                        onChange={(cargo) => onChange({ ...data, contacto: { ...data.contacto, cargo_id: cargo.id, cargo: cargo.nombre } })}
                    />

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label>Teléfono</Label>
                            <Input
                                value={data.contacto.telefono}
                                onChange={(e) => handleContactoChange("telefono", formatPhone(e.target.value))}
                                placeholder="+569 xxxxxxxx"
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label>Email</Label>
                            <Input
                                type="email"
                                value={data.contacto.email}
                                onChange={(e) => handleContactoChange("email", e.target.value)}
                                placeholder="ejemplo@correo.cl"
                            />
                        </div>
                    </div>
                </div>
            )}

            {/* CONTACTS SECTION - Only if editing */}
            {data.id && <ContactosSection entityId={data.id} kind="cliente" entityNombre={esPersona ? undefined : data.nombre_empresa} />}

            {error && <div className="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">{error}</div>}
        </div>
    )
}
