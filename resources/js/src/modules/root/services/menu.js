import http from '@/shared/services/http'

const BASE_URL = '/seguridad/menu'
const MODULES_URL = '/seguridad/modulo'
const PARENTS_URL = '/seguridad/menu/padres'

async function listar({ id_modulo, search } = {}) {
  const params = {}
  if (id_modulo) params.id_modulo = id_modulo
  if (search) params.search = search

  const { data } = await http.get(BASE_URL, { params, toastSuccess: false })
  if (Array.isArray(data?.data)) return data.data
  if (Array.isArray(data)) return data
  return []
}

async function obtener(id_opcion) {
  const { data } = await http.get(`${BASE_URL}/${id_opcion}`, { toastSuccess: false })
  return data
}

async function actualizar(id_opcion, payload) {
  const { data } = await http.put(`${BASE_URL}/${id_opcion}`, payload)
  return data
}

async function listarPadres() {
  const { data } = await http.get(PARENTS_URL)
  if (Array.isArray(data?.data)) return data.data
  if (Array.isArray(data)) return data
  return []
}

async function listarModulos() {
  const { data } = await http.get(MODULES_URL)
  if (Array.isArray(data?.data)) return data.data
  if (Array.isArray(data)) return data
  return []
}

export default {
  listar,
  obtener,
  actualizar,
  listarPadres,
  listarModulos
}
