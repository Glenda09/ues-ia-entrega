import axios from 'axios'
import { toast } from 'vue3-toastify'
import { useLoadingStore } from '@/shared/stores/loading'

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || '/api'

const api = axios.create({
  baseURL: apiBaseUrl,
  timeout: 15000,
  headers: { 'Content-Type': 'application/json' }
})

let isRefreshing = false
let pending = []

function onRefreshed(token) {
  pending.forEach((cb) => cb(token))
  pending = []
}

api.interceptors.request.use((cfg) => {
  try {
    // Incrementa el contador global del overlay al iniciar una petición
    // (`silencioso: true` lo omite para vistas con su propio indicador, p. ej. el chat de IA).
    if (!cfg.silencioso) useLoadingStore().start()
  } catch (_e) {
    console.error(_e)
  }
  const token = localStorage.getItem('access_token')
  if (token) cfg.headers.Authorization = `Bearer ${token}`
  // Ejemplo de deduplicación via señal
  cfg.signal = cfg.signal ?? new AbortController().signal
  return cfg
})

api.interceptors.response.use(
  (r) => {
    try {
      if (!r?.config?.silencioso) useLoadingStore().done()
    } catch (_e) {
      console.error(_e)
    }
    const shouldEmitSuccess = r?.config?.toastSuccess !== false
    if (shouldEmitSuccess && r?.status === 200 && r?.data?.message) {
      toast.success(r.data.message)
    }
    return r
  },
  async (error) => {
    try {
      // Asegura decrementar el contador incluso en errores
      if (!error?.config?.silencioso) useLoadingStore().done()
    } catch (_e) {
      console.error(_e)
    }
    const { config, response } = error
    if (response?.status === 403 && response.data?.code === 'DOBLE_FACTOR_CONFIGURACION_PENDIENTE' && window.location.pathname !== '/configurar-doble-factor') {
      window.location.assign('/configurar-doble-factor?redirect=' + encodeURIComponent(window.location.pathname + window.location.search))
    }
    if (response?.status === 403 && ['CAMBIO_PASSWORD_INICIAL_PENDIENTE', 'ACCESO_TEMPORAL_VENCIDO'].includes(response.data?.code) && window.location.pathname !== '/estudiante/cambiar-password-inicial') {
      window.location.assign('/estudiante/cambiar-password-inicial?redirect=' + encodeURIComponent(window.location.pathname + window.location.search))
    }
    // Errores de red: reintentos simples con backoff. Las mutaciones académicas son idempotentes (Idempotency-Key).
    const retriable =
      !response && !config._retry && (config.method === 'get' || config.method === 'post')
    if (retriable) {
      config._retry = true
      await new Promise((res) => setTimeout(res, 500))
      return api(config)
    }

    const rt = localStorage.getItem('refresh_token')

    if (response && response.status === 401 && !config._retryRefresh && rt) {
      // Refresh Token flow
      if (isRefreshing) {
        return new Promise((resolve) => {
          pending.push((token) => {
            config.headers.Authorization = `Bearer ${token}`
            resolve(api(config))
          })
        })
      }
      config._retryRefresh = true
      isRefreshing = true
      try {
        const { data } = await axios.post(
          `${apiBaseUrl}/auth/refresh-token`,
          { refresh_token: rt }
        )
        localStorage.setItem('access_token', data.access_token)
        isRefreshing = false
        onRefreshed(data.access_token)
        config.headers.Authorization = `Bearer ${data.access_token}`
        return api(config)
      } catch (e) {
        isRefreshing = false
        pending = []
        // Limpia sesión
        localStorage.removeItem('access_token')
        localStorage.removeItem('refresh_token')

        const currentPath = window.location.pathname
        if (currentPath !== '/login') {
          window.location.assign('/login?expired=1&redirect=' + encodeURIComponent(currentPath))
        }
        return Promise.reject(e)
      }
    }

    const norm = {
      status: response?.status ?? 0,
      code: response?.data?.code ?? 'UNKNOWN',
      message: response?.data?.message ?? error.message ?? 'Error de red',
      details: response?.data?.errors ?? null,
      meta: response?.data?.meta ?? null
    }
    return Promise.reject(norm)
  }
)

export default api
