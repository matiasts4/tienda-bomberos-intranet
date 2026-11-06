"use client"

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { useRouter } from 'next/navigation'
import { AlertTriangle } from 'lucide-react'
import { GooeyToaster, gooeyToast } from 'goey-toast'
import { useReducedMotion } from 'framer-motion'
import { useAuthContext } from '@/components/auth-provider'
import { api } from '@/lib/api'
import { agendaNotices, noticeSummary, santiagoDay, type AgendaNotice, type AgendaNoticeFeed } from '@/lib/agenda-notifications'

type NoticeContext = {
  notices: AgendaNotice[]; day: string; loading: boolean; error: string | null
  refresh: () => Promise<void>; markRead: (keys: string[]) => Promise<void>
}
const empty: NoticeContext = { notices: [], day: '', loading: false, error: null, refresh: async () => {}, markRead: async () => {} }
const Context = createContext<NoticeContext>(empty)
export const useAgendaNotifications = () => useContext(Context)

export function AgendaNotificationsProvider({ children }: { children: ReactNode }) {
  const { user } = useAuthContext()
  return user ? <AgendaNotificationsSession key={user.id} userId={user.id}>{children}</AgendaNotificationsSession> : <Context.Provider value={empty}>{children}</Context.Provider>
}

export function AgendaNotificationsSession({ children, userId }: { children: ReactNode; userId: number }) {
  const router = useRouter()
  const reducedMotion = useReducedMotion()
  const [feed, setFeed] = useState<AgendaNoticeFeed | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [mobile, setMobile] = useState(false)
  const [localRead, setLocalRead] = useState<Set<string>>(new Set())
  const alive = useRef(false)
  const request = useRef<AbortController | null>(null)
  const version = useRef(0)
  const feedDay = useRef('')
  const toastId = `agenda-notices-${userId}`
  const notices = useMemo(() => feed ? agendaNotices(feed).map(n => ({ ...n, read: n.read || localRead.has(n.key) })) : [], [feed, localRead])

  const refresh = useCallback(async () => {
    if (!alive.current || document.visibilityState !== 'visible') return
    const current = ++version.current
    request.current?.abort()
    const controller = new AbortController()
    request.current = controller
    const timeout = window.setTimeout(() => controller.abort(), 20000)
    try {
      const result = await api.get<AgendaNoticeFeed>('/api/agenda/avisos', { signal: controller.signal })
      if (!alive.current || current !== version.current) return
      if (feedDay.current !== result.day) { feedDay.current = result.day; setLocalRead(new Set()) }
      setFeed(result); setError(null)
      const items = agendaNotices(result)
      // Una cancelación, conversión a venta o reprogramación retira el resumen anterior.
      gooeyToast.dismiss(toastId)
      const shown = new Set(result.receipts.filter(r => r.shown_at || r.read_at).map(r => r.event_key))
      const candidates = items.filter(n => !shown.has(n.key)).slice(0, 200)
      if (!candidates.length || document.visibilityState !== 'visible' || santiagoDay(new Date()) !== result.day) return
      const claimed = await api.post<{ accepted: string[] }>('/api/agenda/avisos/registrar', {
        mode: 'shown', day: result.day, keys: candidates.map(n => n.key),
      }, { signal: controller.signal })
      if (!alive.current || current !== version.current || santiagoDay(new Date()) !== result.day || document.visibilityState !== 'visible') return
      const accepted = new Set(claimed.accepted)
      const fresh = candidates.filter(n => accepted.has(n.key))
      if (!fresh.length) return
      const summary = noticeSummary(fresh, result.day)
      gooeyToast.warning(summary.title, {
        id: toastId, description: summary.description, icon: <AlertTriangle size={18} aria-hidden="true" />,
        duration: 10000, timing: { displayDuration: 10000 }, preset: 'subtle', spring: !reducedMotion,
        fillColor: '#fff7ed', borderColor: '#c2410c', showTimestamp: false, showProgress: true,
        classNames: { wrapper: 'agenda-toast-wrapper', title: 'agenda-toast-title', description: 'agenda-toast-description', actionButton: 'agenda-toast-action' },
        action: { label: fresh.length === 1 ? 'Ver compromiso' : 'Ver agenda', onClick: () => {
          gooeyToast.dismiss(toastId)
          router.push(fresh.length === 1 ? fresh[0].href : '/comercial/agenda')
        } },
      })
    } catch {
      if (!alive.current || current !== version.current) return
      if (controller.signal.aborted && request.current !== controller) return
      setError('No se pudieron actualizar los avisos. Reintenta la carga.')
    } finally {
      window.clearTimeout(timeout)
      if (alive.current && current === version.current) setLoading(false)
    }
  }, [toastId, router, reducedMotion])

  const markRead = useCallback(async (keys: string[]) => {
    if (!feed || !keys.length) return
    try {
      // Batches limitados por la API; mostrar ≠ leer ≠ completar.
      for (let index = 0; index < keys.length; index += 200) {
        const chunk = keys.slice(index, index + 200)
        await api.post('/api/agenda/avisos/registrar', { mode: 'read', day: feed.day, keys: chunk })
        if (!alive.current) return
        setLocalRead(old => new Set([...old, ...chunk])); setError(null)
      }
      gooeyToast.dismiss(toastId)
    } catch {
      if (alive.current) setError('No se pudo guardar la lectura. Reintenta.')
    }
  }, [feed, toastId])

  useEffect(() => {
    alive.current = true
    const media = window.matchMedia('(max-width: 640px)')
    const updateMobile = () => setMobile(media.matches)
    updateMobile(); media.addEventListener('change', updateMobile)
    void refresh()
    const onVisible = () => { if (document.visibilityState === 'visible') void refresh() }
    const onChanged = () => { void refresh() }
    document.addEventListener('visibilitychange', onVisible)
    window.addEventListener('agenda:changed', onChanged)
    const interval = window.setInterval(onVisible, 5 * 60 * 1000)
    // Recalcular al cruzar medianoche aunque no cambie la pestaña.
    let lastDay = santiagoDay(new Date())
    const midnight = window.setInterval(() => {
      const day = santiagoDay(new Date())
      if (lastDay !== day) { lastDay = day; setLocalRead(new Set()); void refresh() }
    }, 30000)
    return () => {
      alive.current = false; version.current++; request.current?.abort()
      gooeyToast.dismiss(toastId)
      window.clearInterval(interval); window.clearInterval(midnight)
      document.removeEventListener('visibilitychange', onVisible)
      window.removeEventListener('agenda:changed', onChanged)
      media.removeEventListener('change', updateMobile)
    }
  }, [refresh, toastId])

  const value = useMemo(() => ({ notices, day: feed?.day || '', loading, error, refresh, markRead }), [notices, feed?.day, loading, error, refresh, markRead])
  return <Context.Provider value={value}>
    {children}
    <GooeyToaster position={mobile ? 'bottom-center' : 'top-right'} offset={mobile ? 24 : 80} closeButton="top-right" closeOnEscape swipeToDismiss preset="subtle" spring={!reducedMotion} showTimestamp={false} visibleToasts={1} />
  </Context.Provider>
}
