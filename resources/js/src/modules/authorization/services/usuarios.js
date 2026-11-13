import http from '@/shared/services/http'

const BASE = '/auth/usuario'

async function listar({ query, created_at_desde, created_at_hasta, per_page, page } = {}) {
  const params = {}
  if (query) params.query = query
  if (created_at_desde) params.created_at_desde = created_at_desde
  if (created_at_hasta) params.created_at_hasta = created_at_hasta
  if (per_page) params.per_page = per_page
  if (page) params.page = page
  const { data } = await http.get(BASE, { params })
  return data
}

async function crear({ username, email, perfiles }) {
  const { data } = await http.post(BASE, { username, email, perfiles })
  return data
}

async function mostrar(id_usuario) {
  const { data } = await http.get(`${BASE}/${id_usuario}`)
  return data
}

async function actualizar(id_usuario, payload) {
  const { data } = await http.put(`${BASE}/${id_usuario}`, payload)
  return data
}

async function restablecerPassword(id_usuario) {
  const { data } = await http.post(`${BASE}/${id_usuario}/restablecer-password`)
  return data
}

async function alternarEstado(id_usuario) {
  const { data } = await http.put(`${BASE}/${id_usuario}/estado`)
  return data
}

export default {
  listar,
  crear,
  mostrar,
  actualizar,
  restablecerPassword,
  alternarEstado
}
