import { defineStore } from 'pinia'
import apiKeysService from '@/modules/api/services/api-keys'

export const useApiKeysStore = defineStore('useApiKeysStore', {
  state: () => ({
    items: [],
    pagination: {
      current_page: 1,
      per_page: 10,
      total: 0,
      last_page: 1
    },
    filters: {
      cliente: '',
      estado: null,
      per_page: 10
    },
    loading: false
  }),
  actions: {
    setCliente(cliente) {
      this.filters.cliente = cliente ?? ''
    },
    setEstado(estado) {
      this.filters.estado = estado || null
    },
    setPerPage(value) {
      const size = Number(value) || 10
      this.filters.per_page = size
      this.pagination.per_page = size
      this.pagination.current_page = 1
    },
    setPage(page) {
      this.pagination.current_page = Number(page) || 1
    },
    async listar() {
      this.loading = true
      try {
        const payload = {
          cliente: this.filters.cliente || undefined,
          estado: this.filters.estado || undefined,
          per_page: this.filters.per_page,
          page: this.pagination.current_page
        }
        const res = await apiKeysService.listar(payload)
        this.items = Array.isArray(res?.data) ? res.data : []
        if (res?.pagination) {
          this.pagination = {
            current_page: res.pagination.current_page,
            per_page: res.pagination.per_page,
            total: res.pagination.total,
            last_page: res.pagination.last_page
          }
        } else {
          this.pagination.total = this.items.length
          this.pagination.last_page = 1
        }
      } finally {
        this.loading = false
      }
    },
    async crear(payload) {
      const res = await apiKeysService.crear(payload)
      return res
    },
    async alternarEstado(apiKey) {
      const res = await apiKeysService.alternarEstado(apiKey)
      return res
    },
    async sincronizarPermisos(apiKey, permisos) {
      const res = await apiKeysService.sincronizarPermisos(apiKey, permisos)
      return res
    }
  }
})
