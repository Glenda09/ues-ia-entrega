import { useValidatePermission } from '@/shared/composables/useValidatePermission'

/**
 * Guarda de UX por rol. No autoriza: las rutas protegidas deben usar meta.permisos.
 */
export function roleGuard(to, from, next) {
  const need = to.meta.roles || []
  if (need.length === 0) return next()

  const { hasRole } = useValidatePermission()
  if (!need.some((codigo) => hasRole(codigo))) {
    return next({ name: 'not-found', query: { from: to.fullPath } })
  }

  next()
}
