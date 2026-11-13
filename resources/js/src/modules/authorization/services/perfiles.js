import http from '@/shared/services/http'

const BASE = '/seguridad/perfil'

// Lista perfiles. Admite: solo_activos (bool), search (string), per_page (int)
async function listar({ solo_activos, search, per_page } = {}) {
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
  async mostrar(codigo) {
    const { data } = await http.get(`${BASE}/${codigo}`)
    return data
  },
  async actualizar(codigo, payload) {
    const { data } = await http.put(`${BASE}/${codigo}`, payload)
    return data
  },
  async eliminar(codigo) {
    const { data } = await http.delete(`${BASE}/${codigo}`)
    return data
  },
  async alternarEstado(codigo) {
    const { data } = await http.put(`${BASE}/${codigo}/estado`)
    return data
  }
}
