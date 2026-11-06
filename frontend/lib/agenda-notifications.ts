import { RRule } from 'rrule'

export type AgendaNoticeEvent = {
  id: string
  title: string
  start: string | null
  end?: string | null
  allDay?: boolean
  rrule?: string | null
  fingerprint: string
  extendedProps: { tipo: string; cotizacion_id?: number; entrega_pendiente?: boolean }
}
export type AgendaReceipt = { event_key: string; shown_at: string | null; read_at: string | null }
export type AgendaNoticeFeed = { day: string; end: string; timezone: string; events: AgendaNoticeEvent[]; receipts: AgendaReceipt[] }
export type AgendaNotice = { key: string; title: string; date: string; bucket: 'today' | 'upcoming' | 'overdue' | 'undated'; href: string; read: boolean }

const zone = 'America/Santiago'
const dayFormat = new Intl.DateTimeFormat('en-CA', { timeZone: zone, year: 'numeric', month: '2-digit', day: '2-digit' })
export function santiagoDay(value: string | Date): string {
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) return value
  const parts = dayFormat.formatToParts(new Date(value))
  const get = (type: string) => parts.find(p => p.type === type)?.value
  return `${get('year')}-${get('month')}-${get('day')}`
}

// RRule recibe fechas flotantes: conservar la hora de pared chilena evita
// que la zona del navegador cambie el día de una ocurrencia o cruce de DST.
function floating(value: string): Date {
  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return new Date(`${value}T00:00:00Z`)
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone: zone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).formatToParts(new Date(value))
  const n = (type: string) => Number(parts.find(p => p.type === type)?.value)
  return new Date(Date.UTC(n('year'), n('month') - 1, n('day'), n('hour'), n('minute'), n('second')))
}

export function agendaNotices(feed: AgendaNoticeFeed): AgendaNotice[] {
  const read = new Set(feed.receipts.filter(r => r.read_at).map(r => r.event_key))
  const start = new Date(`${feed.day}T00:00:00Z`)
  const end = new Date(`${feed.end}T00:00:00Z`)
  const notices = feed.events.flatMap(event => {
    const href = event.extendedProps.cotizacion_id
      ? `/comercial/cotizaciones/${event.extendedProps.cotizacion_id}`
      : '/comercial/agenda'
    const make = (date: string, bucket: AgendaNotice['bucket'], occurrence = date): AgendaNotice => {
      const key = `${event.id}:${occurrence}:${event.fingerprint}`
      return { key, title: event.title, date, bucket, href, read: read.has(key) }
    }
    if (!event.start) return event.extendedProps.entrega_pendiente ? [make(feed.day, 'undated')] : []
    if (event.extendedProps.tipo === 'pago') return []
    const virtual = ['vencimiento_automatico', 'entrega_venta', 'prospeccion', 'oportunidad_seguimiento'].includes(event.extendedProps.tipo)
    const dates: { date: string; occurrence: string }[] = []
    if (event.rrule) {
      try {
        const duration = event.end ? Math.max(0, floating(event.end).getTime() - floating(event.start).getTime()) : 0
        const rule = new RRule({ ...RRule.parseString(event.rrule), tzid: undefined, dtstart: floating(event.start) })
        // Solo necesitamos días, no miles de avisos de una regla por segundos.
        const days = new Set<string>()
        rule.between(new Date(start.getTime() - duration), new Date(end.getTime() - 1), true, date => {
          const occurrence = date.toISOString().slice(0, 10)
          const active = date.getTime() + duration > start.getTime() || date >= start
          const displayDay = occurrence < feed.day ? feed.day : occurrence
          if (active && !days.has(displayDay)) { dates.push({ date: displayDay, occurrence }); days.add(displayDay) }
          return dates.length < 4
        })
      } catch { return [] }
    } else {
      const date = santiagoDay(event.start)
      const ongoing = !virtual && event.end && floating(event.end) > start
      if (date < feed.end && (date >= feed.day || virtual || ongoing)) dates.push({ date: ongoing && date < feed.day ? feed.day : date, occurrence: date })
    }
    return dates.map(({ date, occurrence }) => make(date, date < feed.day ? 'overdue' : date === feed.day ? 'today' : 'upcoming', occurrence))
  })
  const unique = new Map(notices.map(n => [n.key, n]))
  const order = { overdue: 0, today: 1, undated: 2, upcoming: 3 }
  return [...unique.values()].sort((a, b) => order[a.bucket] - order[b.bucket] || a.date.localeCompare(b.date) || a.title.localeCompare(b.title))
}

export function noticeLabel(notice: AgendaNotice, day: string): string {
  if (notice.bucket === 'undated') return 'Entrega pendiente de definir'
  if (notice.bucket === 'overdue') return 'Pendiente vencido'
  if (notice.bucket === 'today') return 'Para hoy'
  const days = Math.round((Date.parse(notice.date) - Date.parse(day)) / 86400000)
  return `En ${days} ${days === 1 ? 'día' : 'días'}`
}

export function noticeSummary(notices: AgendaNotice[], day: string): { title: string; description: string } {
  if (notices.length === 1) return { title: noticeLabel(notices[0], day), description: notices[0].title }
  const count = (bucket: AgendaNotice['bucket']) => notices.filter(n => n.bucket === bucket).length
  const parts = [count('overdue') && `${count('overdue')} ${count('overdue') === 1 ? 'vencido' : 'vencidos'}`, count('today') && `${count('today')} para hoy`, count('upcoming') && `${count('upcoming')} dentro de los próximos 3 días`, count('undated') && `${count('undated')} sin fecha de entrega`].filter(Boolean)
  return { title: `Tienes ${notices.length} compromisos pendientes`, description: parts.join(' · ') }
}
