"use client"

import { Search } from "lucide-react"
import { Input } from "@/components/ui/input"
import { SidebarTrigger } from "@/components/ui/sidebar"
import { AgendaNotificationsBell } from "@/components/agenda-notifications-bell"

interface AppHeaderProps { title?: string }

export function AppHeader({ title }: AppHeaderProps) {
  return (
    <header className="sticky top-0 z-10 flex h-16 items-center gap-4 border-b bg-background px-4 md:px-6">
      <SidebarTrigger />
      {title && <h1 className="truncate text-lg font-semibold">{title}</h1>}
      <div className="ml-auto flex items-center gap-4">
        <div className="relative hidden w-64 md:block">
          <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
          <Input type="search" aria-label="Buscar pedidos y clientes" placeholder="Buscar pedidos, clientes..." className="pl-8" />
        </div>
        <AgendaNotificationsBell />
      </div>
    </header>
  )
}
