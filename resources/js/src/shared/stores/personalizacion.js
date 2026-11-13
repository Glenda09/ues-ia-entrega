import { defineStore } from 'pinia'

export const usePersonalizacionStore = defineStore('personalizacion', {
  state: () => ({ datos: window.__PERSONALIZACION__ ?? {} }),
  getters: {
    texto: (state) => (campo) => state.datos.textos?.[campo] ?? '',
    asset: (state) => (slot) => state.datos.assets?.[slot] ?? null,
    colorPrimario: (state) => state.datos.color_primario ?? null
  },
  actions: {
    actualizar(datos) {
      this.datos = datos
    },
    aplicarCabecera() {
      document.title = this.texto('titulo_pestana') || this.texto('nombre')
      document.querySelector('meta[name="description"]')?.setAttribute('content', this.texto('descripcion'))
      document.querySelector('link[rel="icon"]')?.setAttribute('href', this.asset('favicon') || 'data:,')
    }
  }
})
