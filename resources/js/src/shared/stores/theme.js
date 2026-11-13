import { defineStore } from 'pinia'
import { usePersonalizacionStore } from '@/shared/stores/personalizacion'
import { esColorHex, variablesPrimario } from '@/shared/utils/colorPrimario'
import { palettes } from '@/shared/config/palettes'
import { ACTIVE_PALETTE, ACTIVE_LAYOUT, DEFAULT_COLOR_MODE } from '@/shared/config/theme.config'

export const useThemeStore = defineStore('theme', {
  state: () => ({
    activePaletteId: ACTIVE_PALETTE,
    activeLayoutId: ACTIVE_LAYOUT,
    colorMode: DEFAULT_COLOR_MODE
  }),
  getters: {
    activePalette: (state) => palettes.find((p) => p.id === state.activePaletteId) ?? palettes[0]
  },
  actions: {
    applyPalette(id) {
      const palette = palettes.find((p) => p.id === id)
      if (!palette) return
      const root = document.documentElement
      for (const [key, value] of Object.entries(palette.vars)) {
        root.style.setProperty(key, value)
      }
      const primario = usePersonalizacionStore().colorPrimario
      if (esColorHex(primario)) {
        for (const [key, value] of Object.entries(variablesPrimario(primario, id === 'dark-navy'))) {
          root.style.setProperty(key, value)
        }
      }
      this.activePaletteId = id
    },
    setColorMode(paletteId, mode) {
      this.applyPalette(paletteId)
      if (mode) this.colorMode = mode
    },
    setLayout(id) {
      this.activeLayoutId = id
    },
    init() {
      this.applyPalette(this.activePaletteId)
    }
  }
})
