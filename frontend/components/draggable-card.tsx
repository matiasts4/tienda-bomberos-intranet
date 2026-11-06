"use client"

import { useDraggable } from "@dnd-kit/core"
import { CSS } from "@dnd-kit/utilities"
import { ReactNode } from "react"
import { cn } from "@/lib/utils"

interface DraggableCardProps {
    id: string
    children: ReactNode
    className?: string
    data?: any
    /** La tarjeta se ve pero no se puede arrastrar. */
    disabled?: boolean
}

export function DraggableCard({ id, children, className, data, disabled = false }: DraggableCardProps) {
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
        id,
        data,
        disabled,
    })

    // We optimize rendering by applying the transform directly to the style
    // without re-rendering the children unnecessarily
    const style = {
        transform: CSS.Translate.toString(transform),
    }

    return (
        <div
            ref={setNodeRef}
            style={style}
            {...listeners}
            {...attributes}
            className={cn(isDragging ? "opacity-50 z-50 ring-2 ring-primary rotate-2" : "", className)}
        >
            {children}
        </div>
    )
}
