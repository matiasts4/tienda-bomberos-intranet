"use client"

import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { useEffect } from "react"
import { Loader2 } from "lucide-react"
import { Checkbox } from "@/components/ui/checkbox"
import { useAuthContext } from "@/components/auth-provider"
import { cn, formatPhone } from "@/lib/utils"
import { PALETA_CARTERA } from "@/lib/reporte-red-bomberos"

const modulesList = [
    { id: "Dashboard General", label: "Dashboard General" },
    { id: "Comercial", label: "Comercial" },
    { id: "Marketplaces", label: "Marketplaces (Portales y Cotizaciones)" },
    { id: "Fábrica", label: "Fábrica" },
    { id: "Personalización", label: "Personalización" },
    { id: "Inventario", label: "Inventario PTE" },
    { id: "Inventario MP", label: "Inventario MP" },
    { id: "Centro de Alertas", label: "Centro de Alertas" },
    { id: "Despacho", label: "Despacho" },
]

const userSchema = z.object({
    name: z.string().min(2, "El nombre debe tener al menos 2 caracteres"),
    email: z.string().email("Correo electrónico inválido"),
    telefono: z.string().optional(),
    password: z.string().optional(),
    role: z.enum(["superadmin", "ejecutivo", "fabrica", "bodega", "personalizacion"], {
        required_error: "Selecciona un rol",
    }),
    modulos: z.array(z.string()).optional(),
    // Código del ejecutivo en el nombre de contactos del celular (V1..V4)
    codigo_vendedor: z.string().regex(/^(V\d{1,2})?$/, "Formato: V1, V2…").optional(),
    // Color en el mapa de la Red de Bomberos; null = automático
    color_cartera: z.string().nullable().optional(),
})

type UserFormValues = z.infer<typeof userSchema>

type Props = {
    defaultValues?: Partial<UserFormValues>
    onSubmit: (data: UserFormValues) => Promise<void>
    isEditing?: boolean
    loading?: boolean
    /** Color de cartera => nombre del otro usuario que ya lo tiene. */
    coloresEnUso?: Record<string, string>
}

export function UserForm({ defaultValues, onSubmit, isEditing = false, loading = false, coloresEnUso = {} }: Props) {
    const form = useForm<UserFormValues>({
        resolver: zodResolver(userSchema),
        defaultValues: {
            name: "",
            email: "",
            telefono: "",
            password: "",
            role: "ejecutivo",
            modulos: [],
            ...defaultValues,
        },
    })

    // Reset form when defaultValues change (e.g. opening edit modal)
    useEffect(() => {
        if (defaultValues) {
            form.reset({
                name: "",
                email: "",
                telefono: "",
                password: "",
                role: "ejecutivo",
                modulos: [],
                ...defaultValues,
            })
        }
    }, [defaultValues, form])

    const handleSubmit = async (data: UserFormValues) => {
        // If editing and password is empty, remove it so it doesn't update
        if (isEditing && !data.password) {
            delete data.password
        }
        await onSubmit(data)
    }

    const { user } = useAuthContext()
    const isSuperAdmin = user?.role === "superadmin"

    return (
        <Form {...form}>
            <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
                <FormField
                    control={form.control}
                    name="name"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Nombre completo</FormLabel>
                            <FormControl>
                                <Input placeholder="Juan Pérez" {...field} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="email"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Correo electrónico</FormLabel>
                            <FormControl>
                                <Input placeholder="juan@ejemplo.com" {...field} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="telefono"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Teléfono</FormLabel>
                            <FormControl>
                                <Input placeholder="+569 xxxxxxxx" {...field} onChange={e => field.onChange(formatPhone(e.target.value))} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                {isSuperAdmin && (
                    <FormField
                        control={form.control}
                        name="codigo_vendedor"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>Código vendedor</FormLabel>
                                <FormControl>
                                    <Input
                                        placeholder="V1"
                                        maxLength={3}
                                        {...field}
                                        value={field.value ?? ""}
                                        onChange={e => field.onChange(e.target.value.toUpperCase().replace(/\s/g, ""))}
                                    />
                                </FormControl>
                                <p className="text-xs text-muted-foreground">
                                    Va al final del nombre con que se guardan los contactos en el celular. Vacío si no es ejecutivo de ventas.
                                </p>
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                )}

                {isSuperAdmin && (
                    <FormField
                        control={form.control}
                        name="color_cartera"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>Color en el mapa de la Red</FormLabel>
                                <div className="flex flex-wrap items-center gap-3">
                                    <div className="grid w-fit grid-cols-8 gap-1.5">
                                        {PALETA_CARTERA.map(({ color, nombre }) => {
                                            const elegido = field.value === color
                                            const usadoPor = coloresEnUso[color]
                                            return (
                                                <button
                                                    key={color}
                                                    type="button"
                                                    aria-label={nombre}
                                                    aria-pressed={elegido}
                                                    title={usadoPor ? `${nombre} (lo usa ${usadoPor})` : nombre}
                                                    onClick={() => field.onChange(color)}
                                                    className={cn(
                                                        "relative size-6 rounded-full border border-black/10 transition-transform hover:scale-110",
                                                        elegido && "ring-2 ring-slate-900 ring-offset-2",
                                                    )}
                                                    style={{ background: color }}
                                                >
                                                    {usadoPor && !elegido && (
                                                        <span className="absolute inset-0 m-auto size-1.5 rounded-full bg-white" />
                                                    )}
                                                </button>
                                            )
                                        })}
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-6 px-2 text-xs"
                                        disabled={!field.value}
                                        onClick={() => field.onChange(null)}
                                    >
                                        Automático
                                    </Button>
                                </div>
                                {/* Solo avisa: bloquear impediría intercambiar colores entre dos usuarios */}
                                {field.value && coloresEnUso[field.value] && (
                                    <p className="text-xs text-amber-700">
                                        {coloresEnUso[field.value]} ya usa este color: sus carteras se verán iguales en el mapa.
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Identifica su cartera en el mapa. Con punto blanco: ya lo usa otro usuario.
                                </p>
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                )}

                <FormField
                    control={form.control}
                    name="role"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Rol {isSuperAdmin ? "" : "(Solo lectura)"}</FormLabel>
                            <Select
                                onValueChange={field.onChange}
                                defaultValue={field.value}
                                value={field.value}
                                disabled={!isSuperAdmin}
                            >
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona un rol" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem value="superadmin">Super Admin</SelectItem>
                                    <SelectItem value="ejecutivo">Ejecutivo Comercial</SelectItem>
                                    <SelectItem value="fabrica">Fábrica</SelectItem>
                                    <SelectItem value="bodega">Bodega</SelectItem>
                                    <SelectItem value="personalizacion">Personalización</SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="password"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>{isEditing ? "Contraseña (opcional)" : "Contraseña"}</FormLabel>
                            <FormControl>
                                <Input type="password" placeholder={isEditing ? "••••••" : "Contraseña segura"} {...field} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <FormField
                    control={form.control}
                    name="modulos"
                    render={() => (
                        <FormItem>
                            <div className="mb-4">
                                <FormLabel className="text-base">Módulos permitidos {isSuperAdmin ? "" : "(Solo lectura)"}</FormLabel>
                                {!isSuperAdmin && (
                                    <p className="text-xs text-muted-foreground">Solo un superadmin puede modificar estos permisos.</p>
                                )}
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                {modulesList.map((item) => (
                                    <FormField
                                        key={item.id}
                                        control={form.control}
                                        name="modulos"
                                        render={({ field }) => {
                                            return (
                                                <FormItem
                                                    key={item.id}
                                                    className="flex flex-row items-start space-x-3 space-y-0"
                                                >
                                                    <FormControl>
                                                        <Checkbox
                                                            checked={field.value?.includes(item.id)}
                                                            disabled={!isSuperAdmin}
                                                            onCheckedChange={(checked) => {
                                                                return checked
                                                                    ? field.onChange([...(field.value || []), item.id])
                                                                    : field.onChange(
                                                                        field.value?.filter(
                                                                            (value) => value !== item.id
                                                                        )
                                                                    )
                                                            }}
                                                        />
                                                    </FormControl>
                                                    <FormLabel className="font-normal">
                                                        {item.label}
                                                    </FormLabel>
                                                </FormItem>
                                            )
                                        }}
                                    />
                                ))}
                            </div>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                <div className="flex justify-end gap-2 pt-2">
                    <Button type="submit" disabled={loading}>
                        {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        {isEditing ? "Guardar cambios" : "Crear usuario"}
                    </Button>
                </div>
            </form>
        </Form>
    )
}
