import { computed } from 'vue'
import { usePersonalizacionStore } from '@/shared/stores/personalizacion'
import { useColorMode } from '@/shared/composables/useColorMode'

export function useAppBrand() {
  const marca = usePersonalizacionStore()
  const { isDark } = useColorMode()
  const appName = computed(() => marca.texto('nombre'))
  const appLogoHorizontalUrl = computed(() => marca.asset('logo'))
  const appLogoDarkUrl = computed(() => marca.asset('logo_oscuro') || marca.asset('logo'))
  const appLogoVerticalUrl = computed(() => marca.asset('logo_vertical') || appLogoUrl.value)
  const appLogoUrl = computed(() => isDark.value ? appLogoDarkUrl.value : appLogoHorizontalUrl.value)
  const fondoAcceso = computed(() => isDark.value
    ? marca.asset('fondo_acceso_oscuro') || marca.asset('fondo_acceso')
    : marca.asset('fondo_acceso'))
  const estiloAcceso = computed(() => fondoAcceso.value ? {
    backgroundImage: `url("${fondoAcceso.value}")`, backgroundSize: 'cover', backgroundPosition: 'center'
  } : {})

  return {
    baseUrl: import.meta.env.VITE_PUBLIC_BASE_URL || '/',
    appName, appLogoHorizontalUrl, appLogoVerticalUrl, appLogoUrl, appLogoDarkUrl, estiloAcceso
  }
}
