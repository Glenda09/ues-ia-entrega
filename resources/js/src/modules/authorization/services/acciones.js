import http from '@/shared/services/http'

const BASE = '/seguridad/accion'

// Lista acciones. Admite: id_grupo (string), accion (string)
// Devuelve un array de acciones
async function listar({ id_grupo, accion } = {}) {
  const params = {}
  if (id_grupo) params.id_grupo = id_grupo
  if (accion) params.accion = accion
  const { data } = await http.get(BASE, { params })
  // El backend podría devolver { data: [] } o []
  if (Array.isArray(data)) return data
  if (Array.isArray(data?.data)) return data.data
  return []
}

export default {
  listar,
  async crear(payload) {
    const { data } = await http.post(BASE, payload)
    return data
  },
  async mostrar(id_accion) {
    const { data } = await http.get(`${BASE}/${id_accion}`)
    return data?.data ?? data
  },
  async actualizar(id_accion, payload) {
    const { data } = await http.put(`${BASE}/${id_accion}`, payload)
    return data?.data ?? data
  },
  async eliminar(id_accion) {
    const { data } = await http.delete(`${BASE}/${id_accion}`)
    return data?.data ?? data
  },
  async actualizarEstado(id_accion) {
    const { data } = await http.put(`${BASE}/${id_accion}/estado`)
    return data?.data ?? data
  },
  async syncPermisos(id_accion, permisos) {
    const { data } = await http.put(`${BASE}/${id_accion}/permisos`, { permisos })
    return data?.data ?? data
  },
  async asignarPermisos(id_accion, permisos) {
    const { data } = await http.post(`${BASE}/${id_accion}/permisos`, { permisos })
    return data?.data ?? data
  },
  async removerPermisos(id_accion, permisos) {
    const { data } = await http.delete(`${BASE}/${id_accion}/permisos`, { data: { permisos } })
    return data?.data ?? data
  }
}
