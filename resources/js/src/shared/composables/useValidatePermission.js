import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/shared/stores/auth'

export function useValidatePermission() {
  const authStore = useAuthStore()
  const { permisos, perfiles } = storeToRefs(authStore)

  function can(perm) {
    if (permisos.value.includes('root')) return true
    return permisos.value.includes(perm)
  }

  function hasRole(codigo) {
    return (perfiles.value || []).some((perfil) => {
      if (typeof perfil === 'string') return perfil === codigo
      return perfil?.codigo === codigo
    })
  }

  return { can, hasRole }
}
