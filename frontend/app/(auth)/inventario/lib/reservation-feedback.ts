import type { ReservationItem } from './reservation-selection'

export interface ReservationFeedback {
    title: string
    description: string
    uncertain?: boolean
}

// The API client throws { status, data }, not Axios errors. Never show SQL or raw server errors.
export function reservationFailure(error: unknown, items: ReservationItem[]): ReservationFeedback {
    const failure = error as { status?: number; data?: { errors?: Record<string, unknown> } } | null
    const status = failure?.status
    if (status === 401 || status === 419) return {
        title: 'Tu sesión necesita actualizarse',
        description: 'Vuelve a iniciar sesión antes de enviar la solicitud. Mientras este formulario siga abierto, conservaremos los datos del pedido.',
    }
    if (status === 403) return {
        title: 'No tienes permiso para crear solicitudes',
        description: 'Pide al superadmin que revise tu acceso. Los productos y cantidades siguen en el formulario.',
    }
    if (status === 404) return {
        title: 'No encontramos la cotización',
        description: 'Busca y selecciona nuevamente la cotización, o revisa su número si es externa. Conservamos los productos de tu pedido.',
    }
    if (status === 422) {
        const errors = failure?.data?.errors ?? {}
        const stockMessages = Array.isArray(errors.items) ? errors.items : []
        const product = items.find(item => stockMessages.includes(`Stock disponible insuficiente para ${item.producto}.`))
        if (stockMessages.some(message => typeof message === 'string' && message.startsWith('Stock disponible insuficiente para '))) return {
            title: 'El stock disponible cambió',
            description: `${product ? `No hay stock suficiente para ${product.producto}.` : 'Uno de los productos ya no tiene stock suficiente.'} Busca el producto nuevamente para consultar su disponibilidad y ajusta la cantidad. No se creó la solicitud; conservamos tu pedido.`,
        }
        return {
            title: 'Revisa los datos del pedido',
            description: Object.keys(errors).some(key => key === 'items' || key.startsWith('items.'))
                ? 'Revisa que los productos sigan disponibles y que sus cantidades sean enteras mayores que cero. No se creó la solicitud; los datos siguen en el formulario.'
                : 'Revisa la cotización y los datos de la orden antes de volver a enviar. No se creó la solicitud; los productos siguen en el formulario.',
        }
    }
    if (status === 429) return {
        title: 'Espera un momento antes de enviar',
        description: 'Se recibieron demasiadas solicitudes. Conservamos tu pedido para que puedas enviarlo en unos momentos.',
    }
    return {
        title: 'No pudimos confirmar el envío',
        description: 'Revisa Pendientes antes de volver a enviar el pedido para evitar duplicados. No recibimos una confirmación del servidor; tus datos siguen en este formulario.',
        uncertain: true,
    }
}
