import { ref, shallowRef } from 'vue'
import {
  downloadBlob as downloadBlobUtil,
  downloadFromResponse as downloadFromResponseUtil
} from '../utils/download'

/**
 * Composable para realizar peticiones streaming a la API.
 * Soporta dos modos:
 *  - as: 'text' -> Transmisión de texto incremental (concatena en `text` y emite `chunks`)
 *  - as: 'blob' -> Descarga binaria por stream con progreso; al terminar expone `blob`
 *
 * Expone:
 *  - loading, error
 *  - text, chunks (modo text)
 *  - progress, bytesReceived, totalBytes, contentType, blob (modo blob)
 *  - start(url, options), cancel(), triggerDownload(opts)
 */
export function useStreaming() {
  const loading = ref(false)
  const error = ref(null)

  // Modo texto
  const text = ref('')
  const chunks = ref([]) // string[]

  // Modo blob
  const progress = ref(0)
  const bytesReceived = ref(0)
  const totalBytes = ref(0)
  const contentType = ref('application/octet-stream')
  const contentDisposition = ref(null)
  const responseHeaders = shallowRef(null)
  const blob = shallowRef(null)

  let controller = null

  function buildUrl(path, params) {
    const isAbs = /^https?:\/\//i.test(path)
    const base = import.meta.env.VITE_API_BASE_URL || ''
    const clean = (s) => (s || '').replace(/\/+$/, '')
    const cleanPath = (s) => (s || '').replace(/^\/+/, '')
    const url = isAbs ? path : `${clean(base)}/${cleanPath(path)}`
    if (!params || typeof params !== 'object') return url
    const usp = new URLSearchParams()
    Object.entries(params).forEach(([k, v]) => {
      if (v === undefined || v === null) return
      if (Array.isArray(v)) v.forEach((it) => usp.append(k, String(it)))
      else usp.append(k, String(v))
    })
    const qs = usp.toString()
    return qs ? `${url}?${qs}` : url
  }

  function withAuthHeaders(h = {}) {
    const headers = new Headers(h)
    const token = typeof localStorage !== 'undefined' ? localStorage.getItem('access_token') : null
    if (token && !headers.has('Authorization')) headers.set('Authorization', `Bearer ${token}`)
    return headers
  }

  async function start(
    path,
    {
      method = 'POST',
      headers = {},
      body = undefined,
      params = undefined,
      as = 'text', // 'text' | 'blob'
      onChunk // (textChunk | Uint8Array) => void
    } = {}
  ) {
    cancel()
    loading.value = true
    error.value = null

    // Reset refs
    text.value = ''
    chunks.value = []
    progress.value = 0
    bytesReceived.value = 0
    totalBytes.value = 0
    contentType.value = 'application/octet-stream'
    blob.value = null
    contentDisposition.value = null
    responseHeaders.value = null

    controller = new AbortController()
    const url = buildUrl(path, params)

    try {
      const res = await fetch(url, {
        method,
        headers: withAuthHeaders(headers),
        body,
        signal: controller.signal
      })

      if (!res.ok) {
        const message = `HTTP ${res.status} ${res.statusText}`
        throw { status: res.status, code: 'HTTP_ERROR', message }
      }

      // Capturamos headers relevantes para progreso y nombre de archivo
      const lenHeader = res.headers.get('content-length')
      totalBytes.value = lenHeader ? parseInt(lenHeader, 10) : 0
      const ct = res.headers.get('content-type') || 'application/octet-stream'
      contentType.value = ct
      contentDisposition.value = res.headers.get('content-disposition')
      // Guardar copia plana de headers
      const headersObj = {}
      res.headers.forEach((v, k) => {
        headersObj[k] = v
      })
      responseHeaders.value = headersObj

      if (!res.body) {
        // No stream disponible; fallback a lectura completa
        if (as === 'blob') {
          const b = await res.blob()
          blob.value = b
          bytesReceived.value = b.size
          progress.value = 100
        } else {
          const t = await res.text()
          text.value = t
          chunks.value = [t]
        }
        return
      }

      const reader = res.body.getReader()
      if (as === 'blob') {
        const parts = []
        while (true) {
          const { value, done } = await reader.read()
          if (done) break
          if (value) {
            parts.push(value)
            bytesReceived.value += value.byteLength
            if (typeof onChunk === 'function') onChunk(value)
            if (totalBytes.value > 0) {
              progress.value = Math.round((bytesReceived.value / totalBytes.value) * 100)
            }
          }
        }
        blob.value = new Blob(parts, { type: ct })
        if (totalBytes.value > 0 && bytesReceived.value >= totalBytes.value) progress.value = 100
      } else {
        const decoder = new TextDecoder('utf-8')
        while (true) {
          const { value, done } = await reader.read()
          if (done) break
          if (value) {
            bytesReceived.value += value.byteLength
            const chunkText = decoder.decode(value, { stream: true })
            text.value += chunkText
            chunks.value.push(chunkText)
            if (typeof onChunk === 'function') onChunk(chunkText)
            if (totalBytes.value > 0) {
              progress.value = Math.round((bytesReceived.value / totalBytes.value) * 100)
            }
          }
        }
        // Flush final por si quedó algo en el decoder
        const flush = decoder.decode()
        if (flush) {
          text.value += flush
          chunks.value.push(flush)
        }
        if (totalBytes.value > 0 && bytesReceived.value >= totalBytes.value) progress.value = 100
      }
    } catch (e) {
      if (e?.name === 'AbortError') {
        // cancelado, no marcar como error
      } else {
        error.value = e
        throw e
      }
    } finally {
      loading.value = false
      controller = null
    }
  }

  function cancel() {
    if (controller) {
      try {
        controller.abort()
      } catch {}
      controller = null
    }
  }

  /**
   * Dispara la descarga del blob recibido (modo 'blob').
   * @param {object} [opts]
   * @param {string} [opts.nombre] Base del nombre de archivo (sin extensión)
   * @param {string} [opts.extension] Extensión sin el punto
   */
  function triggerDownload(opts = {}) {
    if (!blob.value) return
    // Si tenemos headers (incluyendo content-disposition), aprovechamos el parser
    if (responseHeaders.value) {
      const syntheticResponse = { data: blob.value, headers: responseHeaders.value }
      try {
        downloadFromResponseUtil(syntheticResponse, opts?.nombre)
        return
      } catch (e) {
        console.warn('Error en downloadFromResponseUtil:', e)
      }
    }
    // Fallback a descarga directa con nombre/extension proporcionados
    downloadBlobUtil(blob.value, { contentType: contentType.value, ...opts })
  }

  return {
    // estado general
    loading,
    error,
    cancel,

    // texto
    text,
    chunks,

    // binario
    progress,
    bytesReceived,
    totalBytes,
    contentType,
    contentDisposition,
    blob,
    responseHeaders,
    triggerDownload,

    // acción principal
    start
  }
}

export function drainBufferObjects(buffer = '') {
  const objs = []
  let workingBuffer = typeof buffer === 'string' ? buffer : ''

  while (true) {
    const startIdx = workingBuffer.indexOf('{')
    if (startIdx === -1) break

    const found = extractNextJsonObject(workingBuffer, startIdx)
    if (!found) break

    try {
      objs.push(JSON.parse(found.json))
    } catch (error) {
      console.error('Error al parsear JSON:', error)
    }

    workingBuffer = workingBuffer.slice(found.end)
  }

  return { objects: objs, buffer: workingBuffer }
}

function extractNextJsonObject(str, startIndex = 0) {
  const start = findCharOutsideString(str, startIndex, new Set(['{']))
  if (start === -1) return null
  const end = findMatchingBrace(str, start)
  if (end === -1) return null
  return { json: str.slice(start, end + 1), end: end + 1 }
}

function skipString(str, index) {
  let i = index + 1
  while (i < str.length) {
    const ch = str[i]
    if (ch === '\\') {
      i += 2
      continue
    }
    if (ch === '"') return i + 1
    i++
  }
  return i
}

function findCharOutsideString(str, startIndex, targets) {
  let i = startIndex
  while (i < str.length) {
    const ch = str[i]
    if (ch === '"') {
      i = skipString(str, i)
      continue
    }
    if (targets.has(ch)) return i
    i++
  }
  return -1
}

function findMatchingBrace(str, openIndex) {
  let depth = 0
  let i = openIndex
  while (i < str.length) {
    const ch = str[i]
    if (ch === '"') {
      i = skipString(str, i)
      continue
    }
    if (ch === '{') depth++
    else if (ch === '}') {
      depth--
      if (depth === 0) return i
    }
    i++
  }
  return -1
}

export default useStreaming
