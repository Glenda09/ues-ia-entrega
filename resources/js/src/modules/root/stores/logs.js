import { defineStore } from 'pinia'
import dayjs from 'dayjs'
import logsService from '@/modules/root/services/logs'

const INITIAL_PAGINATION = {
  current_page: 1,
  per_page: 10,
  total: 0,
  last_page: 1
}

const INITIAL_FILTERS = {
  level: null,
  context: '',
  channel: '',
  datetime: null,
  order: 'desc',
  per_page: 10
}

const INITIAL_REQUEST_PAGINATION = {
  current_page: 1,
  per_page: 10,
  total: 0,
  last_page: 1
}

const INITIAL_REQUEST_FILTERS = {
  uri: '',
  type: null,
  creation_date: null,
  order: 'desc',
  per_page: 10
}

export const useLogsStore = defineStore('useLogsStore', {
  state: () => ({
    items: [],
    loading: false,
    pagination: { ...INITIAL_PAGINATION },
    filters: { ...INITIAL_FILTERS }
  }),
  actions: {
    setPage(page) {
      this.pagination.current_page = Number(page) || 1
    },
    setPerPage(perPage) {
      const size = Number(perPage) || INITIAL_PAGINATION.per_page
      this.filters.per_page = size
      this.pagination.per_page = size
      this.pagination.current_page = 1
    },
    updateFilter(key, value) {
      if (Object.hasOwn(this.filters, key)) {
        this.filters[key] = value
      }
    },
    resetFilters() {
      this.filters = { ...INITIAL_FILTERS }
      this.pagination.current_page = 1
      this.pagination.per_page = INITIAL_FILTERS.per_page
    },
    async listar() {
      this.loading = true
      try {
        const payload = {
          page: this.pagination.current_page,
          per_page: this.filters.per_page,
          order: this.filters.order || undefined,
          level: this.filters.level || undefined,
          context: this.filters.context?.trim() || undefined,
          channel: this.filters.channel?.trim() || undefined,
          datetime: this.filters.datetime
            ? dayjs(this.filters.datetime).format('YYYY-MM-DD')
            : undefined
        }

        const { data, meta } = await logsService.listar(payload)
        this.items = Array.isArray(data) ? data : []
        this.pagination = {
          current_page: Number(meta?.current_page ?? INITIAL_PAGINATION.current_page),
          per_page: Number(meta?.per_page ?? this.filters.per_page),
          total: Number(meta?.total ?? INITIAL_PAGINATION.total),
          last_page: Number(meta?.last_page ?? INITIAL_PAGINATION.last_page)
        }
        this.filters.per_page = this.pagination.per_page
      } catch (error) {
        this.items = []
        this.pagination = { ...INITIAL_PAGINATION }
        this.filters.per_page = INITIAL_FILTERS.per_page
        throw error
      } finally {
        this.loading = false
      }
    }
  }
})

export const useRequestLogsStore = defineStore('useRequestLogsStore', {
  state: () => ({
    items: [],
    loading: false,
    pagination: { ...INITIAL_REQUEST_PAGINATION },
    filters: { ...INITIAL_REQUEST_FILTERS }
  }),
  actions: {
    setPage(page) {
      this.pagination.current_page = Number(page) || 1
    },
    setPerPage(perPage) {
      const size = Number(perPage) || INITIAL_REQUEST_PAGINATION.per_page
      this.filters.per_page = size
      this.pagination.per_page = size
      this.pagination.current_page = 1
    },
    updateFilter(key, value) {
      if (Object.hasOwn(this.filters, key)) {
        this.filters[key] = value
      }
    },
    resetFilters() {
      this.filters = { ...INITIAL_REQUEST_FILTERS }
      this.pagination.current_page = 1
      this.pagination.per_page = INITIAL_REQUEST_FILTERS.per_page
    },
    async listar() {
      this.loading = true
      try {
        const payload = {
          page: this.pagination.current_page,
          per_page: this.filters.per_page,
          order: this.filters.order || undefined,
          uri: this.filters.uri?.trim() || undefined,
          type: this.filters.type || undefined,
          creation_date: this.filters.creation_date
            ? dayjs(this.filters.creation_date).format('YYYY-MM-DD')
            : undefined
        }

        const { data, meta } = await logsService.listarRequestLogs(payload)
        this.items = Array.isArray(data) ? data : []
        this.pagination = {
          current_page: Number(meta?.current_page ?? INITIAL_REQUEST_PAGINATION.current_page),
          per_page: Number(meta?.per_page ?? this.filters.per_page),
          total: Number(meta?.total ?? INITIAL_REQUEST_PAGINATION.total),
          last_page: Number(meta?.last_page ?? INITIAL_REQUEST_PAGINATION.last_page)
        }
        this.filters.per_page = this.pagination.per_page
      } catch (error) {
        this.items = []
        this.pagination = { ...INITIAL_REQUEST_PAGINATION }
        this.filters.per_page = INITIAL_REQUEST_FILTERS.per_page
        throw error
      } finally {
        this.loading = false
      }
    }
  }
})
