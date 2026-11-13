import { defineStore } from 'pinia'

// Store global para manejar el overlay de carga con técnica de conteo
export const useLoadingStore = defineStore('loading', {
  state: () => ({
    counter: 0
  }),
  getters: {
    isLoading: (s) => s.counter > 0
  },
  actions: {
    start() {
      this.counter += 1
    },
    done() {
      this.counter = Math.max(0, this.counter - 1)
    },
    reset() {
      this.counter = 0
    }
  }
})

