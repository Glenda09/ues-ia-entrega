/**
 * Utilidad para descargar archivos a partir de una respuesta HTTP (Axios)
 * con soporte para distintos tipos/extension. Determina el nombre del archivo
 * con el siguiente orden de prioridad:
 *   1) nombrePreferido (sin extensión, opcional)
 *   2) filename del header Content-Disposition
 *   3) UUID v4 generado
 * La extensión se obtiene de:
 *   a) filename del Content-Disposition (si incluye extensión)
 *   b) mapeo por Content-Type
 *   c) sin extensión en último caso
 */

const MIME_EXTENSION_MAP = {
  'application/pdf': 'pdf',
  'text/csv': 'csv',
  'application/vnd.ms-excel': 'xls',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
  'application/msword': 'doc',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
  'image/png': 'png',
  'image/jpeg': 'jpg',
  'image/jpg': 'jpg',
  'image/gif': 'gif',
  'image/webp': 'webp',
  'image/svg+xml': 'svg',
  'application/json': 'json',
  'text/plain': 'txt',
  'application/zip': 'zip',
  'video/mp4': 'mp4',
  'audio/mpeg': 'mp3'
}

function safeUUID() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID()
  // Fallback simple
  return 'xxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

function parseContentDispositionFilename(contentDisposition) {
  if (!contentDisposition) return null
  try {
    // Soporta filename="..." y filename*=UTF-8''...
    const fnameStarMatch = contentDisposition.match(/filename\*=(?:UTF-8''|)([^;]+)/i)
    if (fnameStarMatch && fnameStarMatch[1]) {
      try {
        return decodeURIComponent(fnameStarMatch[1].replace(/\"/g, '').trim())
      } catch (error) {
        console.error(error)
        return fnameStarMatch[1].replace(/\"/g, '').trim()
      }
    }
    const fnameMatch = contentDisposition.match(/filename=([^;]+)/i)
    if (fnameMatch && fnameMatch[1]) {
      return fnameMatch[1].replace(/(^\s*\"|\"\s*$)/g, '').trim()
    }
  } catch (error) {
    console.error(error)
  }
  return null
}

function extractExtensionFromName(filename) {
  if (!filename) return ''
  const lastDot = filename.lastIndexOf('.')
  if (lastDot <= 0 || lastDot === filename.length - 1) return ''
  return filename.slice(lastDot + 1)
}

function ensureBlob(data, contentType) {
  if (data instanceof Blob) return data
  if (data instanceof ArrayBuffer)
    return new Blob([data], { type: contentType || 'application/octet-stream' })
  // Si llega algo distinto, intenta serializar
  if (typeof data === 'string') return new Blob([data], { type: contentType || 'text/plain' })
  return new Blob([JSON.stringify(data)], { type: contentType || 'application/json' })
}

/**
 * Descarga un archivo usando una respuesta de Axios (o estructura similar)
 * @param {object} response AxiosResponse con { data, headers }
 * @param {string} [nombrePreferido] Nombre base deseado SIN extensión (opcional)
 */
export function downloadFromResponse(response, nombrePreferido) {
  if (!response) throw new Error('downloadFromResponse: response es requerido')
  const headers = response.headers || {}
  // axios normaliza headers a minúsculas; pero soportamos ambos
  const contentDisposition = headers['content-disposition'] || headers['Content-Disposition']
  const contentType =
    headers['content-type'] || headers['Content-Type'] || 'application/octet-stream'

  const filenameFromHeader = parseContentDispositionFilename(contentDisposition)
  const extFromHeader = extractExtensionFromName(filenameFromHeader)
  const extFromMime = MIME_EXTENSION_MAP[String(contentType).split(';')[0].trim()] || ''

  // Prioridad nombre base
  const baseName =
    (nombrePreferido && String(nombrePreferido).trim()) ||
    (filenameFromHeader ? filenameFromHeader.replace(/\.[^.]+$/, '') : '') ||
    safeUUID()

  // Prioridad extensión
  const extension = (extFromHeader && extFromHeader.trim()) || extFromMime || ''
  const finalName = extension ? `${baseName}.${extension}` : baseName

  const blob = ensureBlob(response.data, contentType)
  const url = URL.createObjectURL(blob)

  try {
    const a = document.createElement('a')
    a.href = url
    a.download = finalName
    a.style.display = 'none'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
  } finally {
    URL.revokeObjectURL(url)
  }
}

/**
 * Descarga un blob/arrayBuffer directo (por si ya tienes los datos)
 * @param {Blob|ArrayBuffer|string|any} data
 * @param {object} [opts]
 * @param {string} [opts.nombre] Base sin extensión (opcional)
 * @param {string} [opts.extension] Extensión sin punto (opcional)
 * @param {string} [opts.contentType] Content-Type del blob (opcional)
 */
export function downloadBlob(data, opts = {}) {
  const { nombre, extension, contentType } = opts
  const baseName = (nombre && String(nombre).trim()) || safeUUID()
  const ext = (extension && String(extension).trim()) || ''
  const finalName = ext ? `${baseName}.${ext}` : baseName
  const blob = ensureBlob(data, contentType)
  const url = URL.createObjectURL(blob)
  try {
    const a = document.createElement('a')
    a.href = url
    a.download = finalName
    a.style.display = 'none'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
  } finally {
    URL.revokeObjectURL(url)
  }
}

export default {
  downloadFromResponse,
  downloadBlob
}
