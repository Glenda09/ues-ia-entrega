import http from '@/shared/services/http'

const BASE = '/seguridad/grupo-accion'

// Lista grupos de acción. Admite: search (string), solo_activos (bool), per_page (int)
async function listar({ search, solo_activos, per_page } = {}) {
  const params = {}
  if (typeof solo_activos !== 'undefined') params.solo_activos = solo_activos
  if (search) params.search = search
  if (per_page) params.per_page = per_page
  const { data } = await http.get(BASE, { params })
  return data
}

export default {
  listar,
  async crear(payload) {
    const { data } = await http.post(BASE, payload)
    return data
  },
  async mostrar(id_grupo) {
    const { data } = await http.get(`${BASE}/${id_grupo}`)
    return data
  },
  async actualizar(id_grupo, payload) {
    const { data } = await http.put(`${BASE}/${id_grupo}`, payload)
    return data
  },
  async eliminar(id_grupo) {
    const { data } = await http.delete(`${BASE}/${id_grupo}`)
    return data
  },
  async actualizarEstado(id_grupo) {
    const { data } = await http.put(`${BASE}/${id_grupo}/estado`)
    return data
  }
}
