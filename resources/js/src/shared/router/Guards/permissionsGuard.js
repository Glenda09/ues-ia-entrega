import { useAuthStore } from '@/shared/stores/auth'

let cargaCuenta = null

export async function permissionsGuard(to, from, next) {
  const requiresAuth = to.matched.some((r) => r.meta?.requiresAuth)
  const requiredPerms = to.matched
    .flatMap((r) => Array.isArray(r.meta?.permisos) ? r.meta.permisos : [])
    .filter(Boolean)

  if (!requiresAuth && requiredPerms.length === 0) return next()
  if (!localStorage.getItem('access_token')) return next()

  const auth = useAuthStore()
  if (!auth.id_usuario || !auth.usuario) {
    cargaCuenta ??= auth.obtenerCuenta({ redirigir: false }).finally(() => { cargaCuenta = null })
    const cargada = await cargaCuenta
    if (!cargada) {
      localStorage.removeItem('access_token')
      localStorage.removeItem('refresh_token')
      return next({ name: 'auth.login', query: { expired: '1', redirect: to.fullPath } })
    }
  }

  if (auth.usuario?.two_factor_setup_pending) {
    if (to.name === 'auth.configurar-2fa') return next()
    return next({ name: 'auth.configurar-2fa', query: { redirect: to.fullPath } })
  }
  if (to.name === 'auth.configurar-2fa') return next('/')

  // EST-01: con cambio de contraseña inicial pendiente solo se permite esa vista.
  if (auth.usuario?.password_change_required) {
    if (to.name === 'estudiante.cambiar-password') return next()
    return next({ name: 'estudiante.cambiar-password', query: { redirect: to.fullPath } })
  }
  if (to.name === 'estudiante.cambiar-password') return next('/')

  const perms = Array.isArray(auth.permisos) ? auth.permisos : []
  if (requiredPerms.length && !perms.includes('root') && !requiredPerms.some((permiso) => perms.includes(permiso))) {
    return next({ name: 'not-found', query: { from: to.fullPath } })
  }
  next()
}
