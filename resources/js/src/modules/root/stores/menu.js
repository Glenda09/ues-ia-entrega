import { defineStore } from 'pinia'
import menuService from '@/modules/root/services/menu'

export const useRootMenuStore = defineStore('useRootMenuStore', {
  state: () => ({
    items: [],
    loading: false,
    filters: {
      id_modulo: '',
      search: ''
    },
    modules: [],
    parents: [],
    detail: null,
    detailLoading: false,
    saving: false
  }),
  actions: {
    setModule(idModulo) {
      this.filters.id_modulo = idModulo || ''
    },
    setSearch(value) {
      this.filters.search = value || ''
    },
    resetFilters() {
      this.filters.id_modulo = ''
      this.filters.search = ''
    },
    async listar() {
      this.loading = true
      try {
        const payload = {}
        if (this.filters.id_modulo) payload.id_modulo = this.filters.id_modulo
        if (this.filters.search) payload.search = this.filters.search
        const data = await menuService.listar(payload)
        this.items = Array.isArray(data) ? data : []
      } catch (error) {
        this.items = []
        throw error
      } finally {
        this.loading = false
      }
    },
    async fetchModules() {
      const data = await menuService.listarModulos()
      this.modules = Array.isArray(data) ? data : []
      return this.modules
    },
    async fetchParents() {
      const data = await menuService.listarPadres()
      this.parents = Array.isArray(data) ? data : []
      return this.parents
    },
    async obtenerDetalle(idOpcion) {
      this.detailLoading = true
      try {
        const data = await menuService.obtener(idOpcion)
        this.detail = data ?? null
        return this.detail
      } finally {
        this.detailLoading = false
      }
    },
    async actualizar(idOpcion, payload) {
      this.saving = true
      try {
        const data = await menuService.actualizar(idOpcion, payload)
        return data
      } finally {
        this.saving = false
      }
    }
  }
})
