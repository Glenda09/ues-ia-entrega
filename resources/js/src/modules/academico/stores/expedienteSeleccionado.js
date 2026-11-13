import { defineStore } from 'pinia'
import { api } from '../services/academico'

/**
 * Expediente seleccionado por el estudiante (compartido entre vistas del portal).
 */
export const useExpedienteSeleccionado = defineStore('expedienteSeleccionado', {
  state: () => ({
    perfil: null,
    idExpediente: Number(localStorage.getItem('academico_expediente')) || null
  }),
  getters: {
    expediente: (s) => s.perfil?.expedientes?.find((e) => e.id_expediente === s.idExpediente) ?? null
  },
  actions: {
    /** Carga el perfil y valida la selección; `preferido` (query ?expediente=) tiene prioridad. */
    async cargarPerfil(preferido = null) {
      const r = await api.get('/academico/mi-perfil')
      this.perfil = r.data
      const ids = (r.data?.expedientes ?? []).map((e) => e.id_expediente)
      const pref = Number(preferido) || null
      if (pref && ids.includes(pref)) this.seleccionar(pref)
      else if (!ids.includes(this.idExpediente)) this.seleccionar(ids[0] ?? null)
      return this.perfil
    },
    seleccionar(id) {
      this.idExpediente = id
      if (id) localStorage.setItem('academico_expediente', String(id))
      else localStorage.removeItem('academico_expediente')
    }
  }
})
