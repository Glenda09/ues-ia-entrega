import http from '@/shared/services/http'

const BASE = '/auth/api-key'

async function listar({ cliente, estado, per_page, page } = {}) {
  const params = {}
  if (cliente) params.cliente = cliente
  if (estado) params.estado = estado
  if (per_page) params.per_page = per_page
  if (page) params.page = page
  const { data } = await http.get(BASE, { params })
  return data
}

async function crear({ cliente, id_estado, permisos } = {}) {
  const body = {
    cliente,
    permisos: Array.isArray(permisos) ? permisos : []
  }
  if (id_estado) body.id_estado = id_estado
  const { data } = await http.post(BASE, body)
  return data
}

async function alternarEstado(apiKey) {
  const { data } = await http.put(`${BASE}/${encodeURIComponent(apiKey)}/estado`)
  return data
}

async function sincronizarPermisos(apiKey, permisos) {
  const body = { permisos: Array.isArray(permisos) ? permisos : [] }
  const { data } = await http.put(`${BASE}/${encodeURIComponent(apiKey)}/permisos`, body)
  return data
}

export default {
  listar,
  crear,
  alternarEstado,
  sincronizarPermisos
}
