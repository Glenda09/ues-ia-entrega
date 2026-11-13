import http from '@/shared/services/http'

const BASE_URL = '/jobs'

async function listar({ queue, created_at, order = 'desc', per_page = 10, page = 1 } = {}) {
  const params = {
    page,
    per_page,
    order
  }

  if (queue) params.queue = queue
  if (created_at) params.created_at = created_at

  const { data } = await http.get(BASE_URL, { params })
  const payload = data?.data

  const list = Array.isArray(payload?.data) ? payload.data : []
  const meta = payload
    ? {
        current_page: Number(payload.current_page ?? page),
        per_page: Number(payload.per_page ?? per_page),
        total: Number(payload.total ?? list.length),
        last_page: Number(payload.last_page ?? 1)
      }
    : {
        current_page: Number(page),
        per_page: Number(per_page),
        total: list.length,
        last_page: 1
      }

  return { data: list, meta }
}

async function testQueue() {
  const { data } = await http.get('/queue-test', { toastSuccess: false})
  return data
}

export default { listar, testQueue }
