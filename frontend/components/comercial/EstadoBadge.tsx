import { Badge } from "@/components/ui/badge"
import { cn } from "@/lib/utils"

function isHexColor(v?: string | null) {
    if (!v) return false
    return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(v.trim())
}

function estadoBadgeClass(color?: string | null) {
    const c = (color || "").toLowerCase()

    if (c.includes("green") || c.includes("verde") || c === "success") return "bg-green-100 text-green-800 border-green-200"
    if (c.includes("red") || c.includes("rojo") || c === "danger") return "bg-red-100 text-red-800 border-red-200"
    if (c.includes("orange") || c.includes("naranja") || c === "warning")
        return "bg-orange-100 text-orange-800 border-orange-200"
    if (c.includes("yellow") || c.includes("amarillo")) return "bg-yellow-100 text-yellow-800 border-yellow-200"
    if (c.includes("blue") || c.includes("azul") || c === "info") return "bg-blue-100 text-blue-800 border-blue-200"
    if (c.includes("purple") || c.includes("morado") || c.includes("violet"))
        return "bg-purple-100 text-purple-800 border-purple-200"

    return "bg-muted text-foreground border-muted"
}

export function EstadoBadge({
    nombre,
    color,
    fallbackClassName,
}: {
    nombre: string
    color?: string | null
    fallbackClassName?: string
}) {
    if (isHexColor(color)) {
        return (
            <Badge
                variant="outline"
                className={cn("font-medium border", fallbackClassName)}
                style={{
                    backgroundColor: color!,
                    color: "white",
                    borderColor: "rgba(0,0,0,0.08)",
                }}
            >
                {nombre}
            </Badge>
        )
    }

    return (
        <Badge variant="outline" className={cn("font-medium border", estadoBadgeClass(color), fallbackClassName)}>
            {nombre}
        </Badge>
    )
}
