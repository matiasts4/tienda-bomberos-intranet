import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { CargoSelector } from "@/components/comercial/CargoSelector"
import { formatPhone, formatRut } from "@/lib/utils"

export type ContactoFormState = {
    nombre: string
    rut: string
    cargo: string
    cargo_id: number | null
    telefono: string
    email: string
}

// Mismos atributos que el alta de contactos (Prospección): nombre, cargo, RUT, teléfono y email.
export function ContactoFields({
    form,
    onChange,
}: {
    form: ContactoFormState
    onChange: (form: ContactoFormState) => void
}) {
    return (
        <div className="grid grid-cols-2 gap-4">
            <div className="col-span-2 flex flex-col gap-2">
                <Label>Nombre Contacto</Label>
                <Input
                    value={form.nombre}
                    onChange={e => onChange({ ...form, nombre: e.target.value })}
                    placeholder="Ej: Juan Pérez"
                />
            </div>
            <div className="col-span-2">
                <CargoSelector
                    value={form.cargo_id}
                    onChange={(cargo) => onChange({ ...form, cargo_id: cargo.id, cargo: cargo.nombre })}
                />
            </div>
            <div className="flex flex-col gap-2">
                <Label>RUT</Label>
                <Input
                    value={form.rut}
                    onChange={e => onChange({ ...form, rut: formatRut(e.target.value) })}
                    placeholder="12.345.678-9"
                />
            </div>
            <div className="flex flex-col gap-2">
                <Label>Teléfono</Label>
                <Input
                    value={form.telefono}
                    onChange={e => onChange({ ...form, telefono: formatPhone(e.target.value) })}
                    placeholder="+569 xxxxxxxx"
                />
            </div>
            <div className="col-span-2 flex flex-col gap-2">
                <Label>Email</Label>
                <Input
                    type="email"
                    value={form.email}
                    onChange={e => onChange({ ...form, email: e.target.value.toLowerCase().trim() })}
                    placeholder="correo@ejemplo.com"
                />
            </div>
        </div>
    )
}
