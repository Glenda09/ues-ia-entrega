import http from '@/shared/services/http'

const BASE = '/seguridad/permisos'

async function listar({ per_page = 200 } = {}) {
  const params = { per_page }
  const { data } = await http.get(BASE, { params })
  const list = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
  return {
    data: list,
    meta: data?.meta ?? null
  }
}

export default { listar }
