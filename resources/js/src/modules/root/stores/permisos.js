import { defineStore } from 'pinia'
import permisosService from '@/modules/root/services/permisos'

export const useRootPermisosStore = defineStore('useRootPermisosStore', {
  state: () => ({
    items: [],
    loading: false,
    filters: {
      search: '',
      solo_activos: false,
      per_page: 10
    },
    pagination: {
      current_page: 1,
      per_page: 10,
      total: 0,
      last_page: 1
    }
  }),
  actions: {
    setSearch(value) {
      this.filters.search = value ?? ''
    },
    setSoloActivos(value) {
      this.filters.solo_activos = Boolean(value)
    },
    setPerPage(value) {
      const size = Number(value) || 10
      this.filters.per_page = size
      this.pagination.per_page = size
      this.pagination.current_page = 1
    },
    setPage(value) {
      this.pagination.current_page = Number(value) || 1
    },
    resetFilters() {
      this.filters.search = ''
      this.filters.solo_activos = false
      this.filters.per_page = 10
      this.pagination.current_page = 1
      this.pagination.per_page = 10
    },
    async listar() {
      this.loading = true
      try {
        const payload = {
          search: this.filters.search || undefined,
          solo_activos: this.filters.solo_activos,
          per_page: this.filters.per_page,
          page: this.pagination.current_page
        }
        const { data, meta } = await permisosService.listar(payload)
        this.items = Array.isArray(data) ? data : []
        this.pagination = {
          current_page: Number(meta?.current_page ?? 1),
          per_page: Number(meta?.per_page ?? this.filters.per_page),
          total: Number(meta?.total ?? this.items.length),
          last_page: Number(meta?.last_page ?? 1)
        }
        // Mantiene los filtros sincronizados con la respuesta
        this.filters.per_page = this.pagination.per_page
      } catch (e) {
        this.items = []
        this.pagination = {
          current_page: 1,
          per_page: this.filters.per_page,
          total: 0,
          last_page: 1
        }
        throw e
      } finally {
        this.loading = false
      }
    }
  }
})
