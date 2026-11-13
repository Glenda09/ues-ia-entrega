import http from '@/shared/services/http'
import { dayjs } from '@/shared/plugins/dayjs'

/**
 * Cliente de la API académica (/api/academico) y pública (/api/publico).
 * Las consultas no muestran toast de éxito; las mutaciones sí (mensaje del servidor).
 */
export const api = {
  get: (url, params = {}, config = {}) => http.get(url, { params, toastSuccess: false, ...config }).then((r) => r.data),
  post: (url, body = {}, config = {}) => http.post(url, body, config).then((r) => r.data),
  put: (url, body = {}) => http.put(url, body).then((r) => r.data),
  patch: (url, body = {}) => http.patch(url, body).then((r) => r.data),
  del: (url) => http.delete(url).then((r) => r.data)
}

/** Genera una clave de idempotencia por intento de operación. */
export function nuevaClave() {
  return (crypto.randomUUID?.() ?? `${Date.now()}-${Math.random()}`).slice(0, 128)
}

/**
 * POST con cabecera Idempotency-Key.
 * @param {object} [opciones] `silencioso`: sin overlay global de carga ni toast de éxito; `timeout`: ms (por defecto el del cliente).
 */
export function postIdempotente(url, body, clave = nuevaClave(), opciones = {}) {
  const extra = opciones.silencioso ? { silencioso: true, toastSuccess: false } : {}
  if (opciones.timeout) extra.timeout = opciones.timeout
  return http.post(url, body, { headers: { 'Idempotency-Key': clave }, ...extra }).then((r) => r.data)
}

/**
 * Envía un turno a un asistente de IA y espera su respuesta.
 *
 * El servidor devuelve `202` con `{ id_ejecucion, estado }` mientras el turno sigue en curso (p. ej. tras un
 * reintento por corte de red). En ese caso se vuelve a consultar con la misma clave de idempotencia, sin
 * duplicar el mensaje ni el cargo, hasta obtener la respuesta o agotar `plazoMs`.
 *
 * @param {string} url
 * @param {object} body
 * @param {string} clave Idempotency-Key del turno.
 * @param {{ plazoMs?: number, intervaloMs?: number }} [opciones]
 * @returns {Promise<object>} `data` de la respuesta final (incluye `respuesta`).
 */
export async function enviarTurnoIA(url, body, clave, { plazoMs = 150_000, intervaloMs = 2_500 } = {}) {
  const limite = Date.now() + plazoMs
  for (;;) {
    const restante = Math.max(limite - Date.now(), 5_000)
    const r = await postIdempotente(url, body, clave, { silencioso: true, timeout: restante })
    const d = r?.data ?? {}
    if (typeof d.respuesta === 'string') return d
    if (Date.now() + intervaloMs >= limite) {
      throw { status: 504, code: 'IA_RESPUESTA_PENDIENTE', message: 'El asistente sigue procesando tu mensaje.' }
    }
    await new Promise((res) => setTimeout(res, intervaloMs))
  }
}

/** Mensaje legible de un error normalizado por el cliente HTTP. */
export function mensajeError(e) {
  const detalles = e?.details ? Object.values(e.details).flat().join(' ') : ''
  return [e?.message, detalles].filter(Boolean).join(' ') || 'Ocurrió un error inesperado'
}

/** Instante ISO → texto en la zona institucional. */
export function fecha(iso, formato = 'DD/MM/YYYY HH:mm') {
  return iso ? dayjs(iso).format(formato) : '—'
}

/** Instante ISO → valor "YYYY-MM-DD HH:mm" para inputs datetime-local convertidos. */
export function aLocal(iso) {
  return iso ? dayjs(iso).format('YYYY-MM-DDTHH:mm') : ''
}

/** Valor de input datetime-local → "YYYY-MM-DD HH:mm" esperado por la API. */
export function deLocal(v) {
  return v ? v.replace('T', ' ').slice(0, 16) : null
}

export const ESTADOS_ESTUDIANTE = { ACTIVO: 'Activo', INACTIVO: 'Inactivo', EGRESADO: 'Egresado', RETIRADO: 'Retirado' }

export const SEVERIDAD = {
  activa: 'success', activo: 'success', publicado: 'success', publicada: 'success', aceptada: 'success', validado: 'success', emitido: 'success', ACTIVO: 'success', aprobada: 'success', regular: 'success',
  borrador: 'secondary', pendiente: 'warn', recibida: 'info', comprobante_recibido: 'info', en_curso: 'info', gracia: 'warn', anticipado: 'info',
  archivada: 'secondary', inactivo: 'secondary', INACTIVO: 'secondary', cerrado: 'secondary', cerrada: 'secondary', retirada: 'secondary', retirado: 'secondary', RETIRADO: 'danger', EGRESADO: 'info',
  cancelado: 'danger', observada: 'danger', observado: 'danger', fallido: 'danger', reprobada: 'danger'
}
