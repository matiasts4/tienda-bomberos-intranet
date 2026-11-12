// Creador y ejecutivo responsable de las tarjetas comerciales (leads y cotizaciones).
//
//  - creador: quien creó la tarjeta; no cambia nunca.
//  - ejecutivo (= user_id en el backend): responsable o gestionador actual.
//    Parte siendo el creador y se puede traspasar a otro ejecutivo.
//  - traspasos: historial de cada cambio de responsable.

export type UsuarioRef = { id: number; name?: string | null; nombre?: string | null }

export type Traspaso = {
  id: number
  de_user_id: number | null
  a_user_id: number
  por_user_id: number | null
  motivo?: string | null
  created_at: string
  de?: UsuarioRef | null
  a?: UsuarioRef | null
  por?: UsuarioRef | null
}

export type TarjetaConResponsable = {
  user_id?: number | null
  creado_por_id?: number | null
  ejecutivo?: UsuarioRef | null
  creador?: UsuarioRef | null
  traspasos?: Traspaso[] | null
}

export type TipoTarjeta = "oportunidades" | "cotizaciones"

/** Roles que pueden traspasar tarjetas ajenas (espejo de TraspasoResponsableService). */
export const ROLES_SUPERVISION = ["admin", "superadmin"]

export function nombreUsuario(u?: UsuarioRef | null, fallback = "Sin asignar"): string {
  return (u?.name || u?.nombre || "").trim() || fallback
}

/** "Juan Pérez Soto" -> "Juan P." para tarjetas angostas. */
export function nombreCorto(u?: UsuarioRef | null, fallback = "Sin asignar"): string {
  const full = nombreUsuario(u, "")
  if (!full) return fallback
  const [primero, segundo] = full.split(/\s+/)
  return segundo ? `${primero} ${segundo.charAt(0)}.` : primero
}

/** El creador es distinto del responsable actual (la tarjeta ya fue traspasada o creada para otro). */
export function creadorDistinto(t: TarjetaConResponsable): boolean {
  return !!t.creador && !!t.ejecutivo && t.creador.id !== t.ejecutivo.id
}

/** Solo el responsable actual, o un admin/superadmin, puede traspasar. */
export function puedeTraspasar(
  t: TarjetaConResponsable,
  user?: { id: number; role?: string } | null
): boolean {
  if (!user) return false
  if (t.user_id == null && !t.ejecutivo) return true
  const responsableId = t.user_id ?? t.ejecutivo?.id
  return responsableId === user.id || ROLES_SUPERVISION.includes(user.role ?? "")
}
