// Tipos compartidos para la Red de Bomberos (Cuerpo -> Compañía) y su vínculo
// con Contacto/Cliente. Antes de este refactor, Cuerpos y Compañías se
// modelaban como filas de `Cliente` (con `parent`/`parent_id`); ahora son
// entidades propias, sin cliente espejo.

export type Region = { id: number; nombre: string; zona_id?: number }

export type Comuna = { id: number; nombre: string; region_id: number; provincia_id?: number | null }

export type Cuerpo = {
  id: string
  nombre: string
  rut?: string | null
  fecha_fundacion?: string | null
  direccion?: string | null
  giro?: string | null
  telefono?: string | null
  numero_socio?: number | null
  numero_companias?: number | null
  logo_url?: string | null
  region_id?: number | null
  region?: Region | null
  comuna_id?: number | null
  comuna?: Comuna | null
  /** Cartera: ejecutivo a cargo del Cuerpo. */
  ejecutivo_id?: number | null
  ejecutivo?: { id: number; name: string; codigo_vendedor?: string | null; color_cartera?: string | null } | null
}

export type Compania = {
  id: string
  cuerpo_id: string
  nombre: string
  numero: number
  fecha_fundacion?: string | null
  cuerpo?: Cuerpo | null
}

// Tag local (no viene del backend) para saber, al mezclar cuerpos + companias +
// clientes sueltos en una misma lista, a qué tabla/endpoint pertenece cada fila.
export type RedBomberosKind = "cuerpo" | "compania" | "cliente"

export type EntidadConKind =
  | ({ kind: "cuerpo" } & Cuerpo)
  | ({ kind: "compania" } & Compania)
