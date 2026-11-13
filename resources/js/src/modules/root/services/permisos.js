import http from '@/shared/services/http'

const BASE_URL = '/seguridad/permisos'

async function listar({ solo_activos, search, per_page = 10, page = 1 } = {}) {
  const params = { per_page, page }

  if (typeof solo_activos === 'boolean') params.solo_activos = solo_activos
  if (search) params.search = search

  const { data } = await http.get(BASE_URL, { params, toastSuccess: false })

  const arrayData = Array.isArray(data) ? data : []
  const list = Array.isArray(data?.data) ? data.data : arrayData
  const meta = data?.meta

  return {
    data: list,
    meta: meta ?? {
      total: list.length,
      current_page: page,
      per_page,
      last_page: 1
    }
  }
}

export default { listar }
