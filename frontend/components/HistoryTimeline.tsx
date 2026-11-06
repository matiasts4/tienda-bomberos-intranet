import { format } from "date-fns"
import { es } from "date-fns/locale"
import { CircleUser, ArrowRight, UserCog } from "lucide-react"
import { cn } from "@/lib/utils"
import { nombreUsuario, type Traspaso } from "@/lib/responsables"

export interface HistorialEtapa {
    id: number
    model_type: string
    model_id: number
    user_id: number
    estado_anterior_id: number | null
    estado_nuevo_id: number
    created_at: string
    user: {
        id: number
        name: string
    }
    estado_anterior?: {
        id: number
        nombre: string
        color: string
    } | null // Allow null explicitly
    estado_nuevo: {
        id: number
        nombre: string
        color: string
    }
    glosa?: string | null
    /** Nota del cambio de etapa que guarda el backend (p. ej. el motivo de No Vigente). */
    observaciones?: string | null
    tipo?: 'cambio_estado' | 'gestion' | 'traspaso'
    traspaso?: Traspaso
}

interface HistoryTimelineProps {
    historial: HistorialEtapa[]
    creationDate?: string | null
    createdByName?: string | null
    /** Cambios de ejecutivo responsable; se mezclan con el historial por fecha. */
    traspasos?: Traspaso[] | null
}

export function HistoryTimeline({ historial, creationDate, createdByName, traspasos }: HistoryTimelineProps) {
    // Traspasos de responsable como eventos del historial
    const eventosTraspaso: HistorialEtapa[] = (traspasos ?? []).map((t) => ({
        id: -1_000_000 - t.id, // ID negativo para evitar colisión con historial y gestiones
        model_type: "TraspasoResponsable",
        model_id: t.id,
        user_id: t.por_user_id ?? 0,
        estado_anterior_id: null,
        estado_nuevo_id: 0,
        created_at: t.created_at,
        user: { id: t.por_user_id ?? 0, name: nombreUsuario(t.por, "Sistema") },
        estado_nuevo: { id: 0, nombre: "Traspaso", color: "#7c3aed" },
        glosa: t.motivo,
        tipo: "traspaso",
        traspaso: t,
    }))

    // Combinar historial con evento de creación (los más recientes primero)
    const items = [...historial, ...eventosTraspaso].sort(
        (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
    )

    if (creationDate) {
        items.push({
            id: -1, // ID ficticio
            model_type: "",
            model_id: 0,
            user_id: 0,
            estado_anterior_id: null,
            estado_nuevo_id: 0,
            created_at: creationDate,
            user: {
                id: 0,
                name: createdByName || "Sistema",
            },
            estado_nuevo: {
                id: 0,
                nombre: "Creado",
                color: "gray",
            },
            estado_anterior: null
        } as HistorialEtapa)
    }

    if (items.length === 0) {
        return (
            <div className="flex h-32 flex-col items-center justify-center rounded-lg border border-dashed text-muted-foreground bg-muted/20">
                <p className="text-sm">No hay historial de cambios registrados.</p>
            </div>
        )
    }

    return (
        <div className="space-y-6 pl-2">
            {items.map((item, index) => {
                const isCreation = item.id === -1
                const isTraspaso = item.tipo === 'traspaso'
                return (
                    <div key={item.id} className="relative pl-10 pb-8 last:pb-0">
                        {/* Line vertical */}
                        {index !== items.length - 1 && (
                            <div className="absolute left-[7px] top-1 h-full w-px bg-border" />
                        )}

                        {/* Dot */}
                        <div className="absolute left-0 top-0 h-4 w-4 rounded-full border bg-background flex items-center justify-center mt-1">
                            <div className={cn("h-1.5 w-1.5 rounded-full", isCreation ? "bg-gray-400" : "bg-primary/60")} />
                        </div>

                        <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-2 text-sm min-h-[1.5rem]">
                                {isTraspaso ? (
                                    <>
                                        <span className="inline-flex items-center gap-1 rounded-md border border-violet-200 bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700">
                                            <UserCog className="h-3 w-3" />
                                            Traspaso
                                        </span>
                                        <span className="text-muted-foreground line-through opacity-70">
                                            {nombreUsuario(item.traspaso?.de, "Sin asignar")}
                                        </span>
                                        <ArrowRight className="h-3.5 w-3.5 text-muted-foreground" />
                                        <span className="font-semibold text-foreground">
                                            {nombreUsuario(item.traspaso?.a)}
                                        </span>
                                    </>
                                ) : (!isCreation && item.estado_anterior?.id !== item.estado_nuevo.id) ? (
                                    <>
                                        {item.tipo !== 'gestion' && (
                                            <span className="font-semibold text-foreground">
                                                {item.estado_anterior ? (
                                                    <span className="text-muted-foreground line-through opacity-70">
                                                        {item.estado_anterior.nombre}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground italic">Inicio</span>
                                                )}
                                            </span>
                                        )}

                                        {item.tipo !== 'gestion' && (
                                            <ArrowRight className="h-3.5 w-3.5 text-muted-foreground" />
                                        )}

                                        <span className={cn(
                                            "font-medium text-foreground rounded-md px-2 py-0.5 text-xs border"
                                        )}
                                            style={{
                                                borderColor: item.estado_nuevo?.color,
                                                color: item.estado_nuevo?.color,
                                                backgroundColor: item.estado_nuevo?.color ? `${item.estado_nuevo.color}10` : 'transparent'
                                            }}>
                                            {item.estado_nuevo.nombre}
                                        </span>
                                    </>
                                ) : (
                                    /* Same State -> Modificacion */
                                    <span className={cn(
                                        "font-medium text-foreground rounded-md px-2 py-0.5 text-xs border border-blue-200 bg-blue-50 text-blue-700"
                                    )}>
                                        Modificación
                                    </span>
                                )}
                            </div>

                            {(item.glosa || item.observaciones) && (
                                <div className="text-sm text-foreground bg-muted/50 p-3 rounded-lg mt-2 border border-border/50">
                                    "{item.glosa || item.observaciones}"
                                </div>
                            )}

                            <div className="flex items-center gap-2 text-xs text-muted-foreground mt-1">
                                <div className="flex items-center gap-1">
                                    <CircleUser className="h-3 w-3" />
                                    <span>{item.user?.name || 'Sistema'}</span>
                                </div>
                                <span>•</span>
                                <span>{format(new Date(item.created_at), "dd MMM yyyy HH:mm", { locale: es })}</span>
                            </div>
                        </div>
                    </div>
                )
            })}
        </div>
    )
}
