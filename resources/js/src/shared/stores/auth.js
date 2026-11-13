import { defineStore } from 'pinia'
import { jwtDecode } from 'jwt-decode'
import authService from '@/modules/authentication/services/auth'
import cuentaService from '@/modules/authentication/services/cuenta'
import { useRootStore } from './root'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    id_usuario: null,
    usuario: null,
    perfiles: [],
    permisos: [],
    jwt: null // payload decodificada del access_token
  }),
  actions: {
    async login({ identifier, password }) {
      const res = await authService.login({ identifier, password })
      if (res?.status === 200) {
        const data = res.data
        this.setTokens({ access: data?.access_token, refresh: data?.refresh_token })
        this.decodeToken()
        return { twoFactorRequired: false, success: true }
      }
      if (res?.status === 202) {
        return { twoFactorRequired: true, success: true }
      }
      return { twoFactorRequired: false, success: false }
    },
    /**
     * Carga la cuenta del usuario autenticado.
     *
     * @param {{ redirigir?: boolean }} [opciones] Con `redirigir` activo, un fallo limpia la sesión y envía a login.
     * @returns {Promise<boolean>} Indica si la cuenta se cargó.
     */
    async obtenerCuenta({ redirigir = true } = {}) {
      const root = useRootStore()

      try {
        const { data } = await cuentaService.cuenta()
        this.usuario = {
          username: data.username,
          email: data.email,
          two_factor_status: data.two_factor_status,
          two_factor_type: data.two_factor_type,
          two_factor_required: data.two_factor_required === true,
          two_factor_required_method: data.two_factor_required_method ?? 'email',
          two_factor_can_disable: data.two_factor_can_disable !== false,
          two_factor_setup_pending: data.two_factor_setup_pending === true,
          password_change_required: data.password_change_required === true,
          temporary_password_expires_at: data.temporary_password_expires_at ?? null
        }

        this.id_usuario = data.id_usuario ?? null
        this.perfiles = data.perfiles || []
        this.permisos = data.permisos || []

        root.setMenu(data.menu || [])
        return true
      } catch (e) {
        console.error('Error obteniendo cuenta', e)
        this.id_usuario = null
        this.usuario = null
        this.perfiles = []
        this.permisos = []
        root.setMenu([])
        if (redirigir) this.redirigirALogin()
        return false
      }
    },
    /**
     * Elimina la sesión local y redirige a login conservando la ruta actual.
     */
    redirigirALogin() {
      localStorage.removeItem('access_token')
      localStorage.removeItem('refresh_token')
      this.jwt = null
      const actual = window.location.pathname
      if (actual === '/login') return
      window.location.assign('/login?expired=1&redirect=' + encodeURIComponent(actual + window.location.search))
    },
    async logout() {
      try {
        await authService.logout()
      } catch (e) {
        console.error('Error en logout', e)
      } finally {
        localStorage.removeItem('access_token')
        localStorage.removeItem('refresh_token')
        this.$reset()
        window.location.assign('/login?closed=1')
      }
    },
    setTokens({ access, refresh }) {
      this.id_usuario = null
      this.usuario = null
      this.perfiles = []
      this.permisos = []
      if (access) localStorage.setItem('access_token', access)
      if (refresh) localStorage.setItem('refresh_token', refresh)
      this.decodeToken()
    },
    decodeToken(token) {
      try {
        const t = token || localStorage.getItem('access_token')
        if (!t) {
          this.jwt = null
          return null
        }
        const payload = jwtDecode(t)
        this.jwt = payload

        return payload
      } catch (e) {
        console.error('Error decodificando token', e)
        this.jwt = null
        this.id_usuario = null
        return null
      }
    },
    initFromStorage() {
      this.decodeToken()
    },
    isTokenExpired(graceSeconds = 0) {
      const payload = this.jwt || this.decodeToken()
      const exp = payload?.exp
      if (!exp) return false
      const now = Math.floor(Date.now() / 1000)
      return now >= exp - graceSeconds
    }
  }
})
