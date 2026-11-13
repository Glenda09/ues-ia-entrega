import { createApp, watch } from 'vue'
import { usePersonalizacionStore } from '@/shared/stores/personalizacion'
import { useThemeStore } from '@/shared/stores/theme'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import Ripple from 'primevue/ripple'
import 'primeicons/primeicons.css'
import 'vue3-toastify/dist/index.css'
import App from '@/App.vue'
import router from '@/shared/router'
import DayjsPlugin from '@/shared/plugins/dayjs'
import { useAuthStore } from '@/shared/stores/auth'
import { initColorMode } from '@/shared/composables/useColorMode'
import Icon from '@/shared/components/Icon.vue'
import { Tooltip } from 'primevue'

const app = createApp(App)

const pinia = createPinia()
app.use(pinia)

useAuthStore(pinia).initFromStorage()
initColorMode()

const personalizacion = usePersonalizacionStore(pinia)
watch(() => personalizacion.datos, () => {
  personalizacion.aplicarCabecera()
  const tema = useThemeStore(pinia)
  tema.applyPalette(tema.activePaletteId)
}, { immediate: true })

app.use(router)
app.use(DayjsPlugin)
app.directive('tooltip', Tooltip);

app.use(PrimeVue, {
  theme: {
    preset: Aura,
    options: {
      prefix: 'p',
      darkModeSelector: '.dark',
      cssLayer: false
    }
  }
})

app.directive('ripple', Ripple)

// Componentes globales
app.component('Icon', Icon)

app.mount('#app')
