import { defineStore } from 'pinia'
import dayjs from 'dayjs'
import usuariosService from '@/modules/authorization/services/usuarios'

export const useUsuariosStore = defineStore('useUsuariosStore', {
  state: () => ({
    items: [],
    pagination: {
      current_page: 1,
      per_page: 10,
      total: 0,
      last_page: 1
    },
    filters: {
      query: '',
      createdAtRange: null, // [Date, Date]
      per_page: 10
    },
    loading: false
  }),
  actions: {
    setQuery(q) {
      this.filters.query = q ?? ''
    },
    setDateRange(range) {
      this.filters.createdAtRange = range ?? null
    },
    setPerPage(n) {
      this.filters.per_page = n ?? 10
      this.pagination.per_page = this.filters.per_page
      this.pagination.current_page = 1
    },
    setPage(p) {
      this.pagination.current_page = Number(p) || 1
    },
    async listar() {
      this.loading = true
      try {
        const payload = {
          query: this.filters.query || undefined,
          created_at_desde: this.filters.createdAtRange?.[0]
            ? dayjs(this.filters.createdAtRange[0]).format('YYYY-MM-DD')
            : undefined,
          created_at_hasta: this.filters.createdAtRange?.[1]
            ? dayjs(this.filters.createdAtRange[1]).format('YYYY-MM-DD')
            : undefined,
          per_page: this.filters.per_page,
          page: this.pagination.current_page
        }
        const res = await usuariosService.listar(payload)
        this.items = res?.data ?? []
        if (res?.pagination) {
          this.pagination = {
            current_page: res.pagination.current_page,
            per_page: res.pagination.per_page,
            total: res.pagination.total,
            last_page: res.pagination.last_page
          }
        }
      } finally {
        this.loading = false
      }
    },
    async crear({ username, email, perfiles }) {
      const res = await usuariosService.crear({ username, email, perfiles })
      return res
    },
    async actualizar(id_usuario, payload) {
      const res = await usuariosService.actualizar(id_usuario, payload)
      return res
    },
    async restablecerPassword(id_usuario) {
      const res = await usuariosService.restablecerPassword(id_usuario)
      return res
    },
    async alternarEstado(id_usuario) {
      const res = await usuariosService.alternarEstado(id_usuario)
      return res
    }
  }
})
