import { defineStore } from 'pinia'
import perfilesService from '@/modules/authorization/services/perfiles'

export const usePerfilesStore = defineStore('usePerfilesStore', {
  state: () => ({
    items: [],
    loading: false,
    filters: {
      search: '',
      per_page: 10
    }
  }),
  actions: {
    setSearch(q) {
      this.filters.search = q ?? ''
    },
    setPerPage(n) {
      this.filters.per_page = n ?? 10
    },
    async listar() {
      this.loading = true
      try {
        const res = await perfilesService.listar({
          search: this.filters.search || undefined,
          per_page: this.filters.per_page
        })
        // El backend puede devolver { data: [] }
        let items = []
        if (Array.isArray(res?.data)) {
          items = res.data
        } else if (Array.isArray(res)) {
          items = res
        }
        this.items = items
      } finally {
        this.loading = false
      }
    },
    async crear(payload) {
      return perfilesService.crear(payload)
    },
    async mostrar(codigo) {
      return perfilesService.mostrar(codigo)
    },
    async actualizar(codigo, payload) {
      return perfilesService.actualizar(codigo, payload)
    },
    async eliminar(codigo) {
      return perfilesService.eliminar(codigo)
    },
    async alternarEstado(codigo) {
      return perfilesService.alternarEstado(codigo)
    }
  }
})
