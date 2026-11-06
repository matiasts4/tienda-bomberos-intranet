"use client"

import { useState } from 'react'
import Link from 'next/link'
import { Bell, Calendar, AlertTriangle, CheckCheck, RotateCw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { useAgendaNotifications } from '@/components/agenda-notifications-provider'
import { noticeLabel } from '@/lib/agenda-notifications'

export function AgendaNotificationsBell() {
  const { notices, day, loading, error, refresh, markRead } = useAgendaNotifications()
  const [saving, setSaving] = useState(false)
  const [open, setOpen] = useState(false)
  const unread = notices.filter(n => !n.read)
  const read = async (keys: string[]) => { setSaving(true); try { await markRead(keys) } finally { setSaving(false) } }
  return <Popover open={open} onOpenChange={setOpen}>
    <PopoverTrigger asChild>
      <Button variant="ghost" size="icon" className="relative" aria-label={`Notificaciones de agenda${unread.length ? `, ${unread.length} sin leer` : ''}`}>
        <Bell className="size-5" />
        {unread.length > 0 && <span aria-hidden="true" className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-orange-700 px-1 text-xs text-white">{unread.length > 99 ? '99+' : unread.length}</span>}
      </Button>
    </PopoverTrigger>
    <PopoverContent className="w-[min(380px,calc(100vw-24px))] p-0" align="end">
      <div className="flex items-center justify-between gap-2 border-b p-4">
        <div><h2 className="text-sm font-semibold">Avisos de agenda</h2><p className="text-xs text-muted-foreground">Hoy y los próximos 3 días</p></div>
        <Button size="icon" variant="ghost" aria-label="Actualizar avisos" onClick={() => void refresh()}><RotateCw className="size-4" /></Button>
      </div>
      {error && <div role="status" className="border-b bg-amber-50 px-4 py-3 text-sm text-amber-900">{error} <button className="underline" onClick={() => void refresh()}>Reintentar</button></div>}
      <div className="max-h-[min(400px,60dvh)] overflow-y-auto">
        {loading && !notices.length ? <p role="status" className="p-6 text-sm text-muted-foreground">Cargando compromisos…</p> : !notices.length && !error ? <div className="p-6 text-center"><Calendar className="mx-auto mb-2 size-6 text-muted-foreground" /><p className="text-sm">No tienes compromisos próximos.</p></div> : notices.map(notice => <div key={notice.key} className={`flex gap-3 border-b p-4 ${notice.read ? '' : 'bg-orange-50/50'}`}>
          <AlertTriangle aria-hidden="true" className={`mt-0.5 size-4 shrink-0 ${notice.bucket === 'overdue' ? 'text-red-700' : 'text-orange-700'}`} />
          <div className="min-w-0 flex-1">
            <p className={`text-xs font-medium ${notice.bucket === 'overdue' ? 'text-red-700' : 'text-orange-800'}`}>{noticeLabel(notice, day)}</p>
            <Link href={notice.href} onClick={() => { setOpen(false); if (!notice.read) void read([notice.key]) }} className="mt-1 block break-words text-sm font-medium hover:underline">{notice.title}</Link>
            {notice.bucket !== 'undated' && <p className="mt-1 text-xs text-muted-foreground">{new Intl.DateTimeFormat('es-CL', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(`${notice.date}T12:00:00Z`))}</p>}
            {!notice.read && <button disabled={saving} onClick={() => void read([notice.key])} className="mt-2 text-xs underline disabled:opacity-50">Marcar como leído</button>}
          </div>
        </div>)}
      </div>
      <div className="flex flex-wrap items-center justify-between gap-2 p-3">
        <Button variant="ghost" size="sm" disabled={saving || !unread.length} onClick={() => void read(unread.map(n => n.key))}><CheckCheck className="mr-1 size-4" />Marcar todo leído</Button>
        <Link href="/comercial/agenda" onClick={() => setOpen(false)} className="text-sm font-medium text-orange-800 hover:underline">Ver agenda</Link>
      </div>
    </PopoverContent>
  </Popover>
}
