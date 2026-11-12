import React, { memo } from "react"
import type { ReactNode } from "react"
import { Badge } from "@/components/ui/badge"
import { cn } from "@/lib/utils"
import { useDroppable } from "@dnd-kit/core"
import { Lock } from "lucide-react"

interface KanbanColumnProps {
  title: string
  count: number
  children: ReactNode
  className?: string
  columnId: string
  headerAction?: ReactNode
  locked?: boolean // When true, the column rejects drops and shows a lock icon
}

export const KanbanColumn = memo(function KanbanColumn({
  title,
  count,
  children,
  className,
  columnId,
  headerAction,
  locked = false,
}: KanbanColumnProps) {
  const { setNodeRef, isOver } = useDroppable({
    id: columnId,
    disabled: locked, // Disables drop target entirely when locked
  })

  return (
    <div
      ref={setNodeRef}
      className={cn(
        "flex w-[320px] flex-shrink-0 flex-col rounded-lg border p-4 transition-colors",
        !locked && isOver && "bg-primary/5 border-primary",
        className,
      )}
    >
      <div className="mb-3 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <h3 className="text-sm font-semibold">{title}</h3>
          <Badge variant="secondary" className="rounded-full">
            {count}
          </Badge>
          {locked && (
            <span className="flex items-center gap-1 text-[10px] text-emerald-600 font-semibold">
              <Lock className="w-3 h-3" />
              Estado final
            </span>
          )}
        </div>
        {headerAction}
      </div>
      <div
        className="flex-1 space-y-3"
        style={{ contentVisibility: 'auto' } as any}
      >
        {children}
      </div>
    </div>
  )
})
