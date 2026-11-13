import { defineStore } from 'pinia'
import dayjs from 'dayjs'
import jobsService from '@/modules/root/services/jobs'

const INITIAL_PAGINATION = {
  current_page: 1,
  per_page: 10,
  total: 0,
  last_page: 1
}

const INITIAL_FILTERS = {
  queue: '',
  created_at: null,
  order: 'desc',
  per_page: 10
}

export const useJobsStore = defineStore('useJobsStore', {
  state: () => ({
    items: [],
    loading: false,
    testing: false,
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
    async testQueue() {
      this.testing = true
      try {
        await jobsService.testQueue()
      } finally {
        this.testing = false
      }
    },
    async listar() {
      this.loading = true
      try {
        const payload = {
          page: this.pagination.current_page,
          per_page: this.filters.per_page,
          order: this.filters.order || undefined,
          queue: this.filters.queue?.trim() || undefined,
          created_at: this.filters.created_at
            ? dayjs(this.filters.created_at).format('YYYY-MM-DD')
            : undefined
        }

        const { data, meta } = await jobsService.listar(payload)
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
