<template>
  <div>
    <div v-if="$slots.filtros" class="mb-4 flex flex-wrap items-end gap-3"><slot name="filtros" :recargar="recargar" /></div>
    <EstadoVista :cargando="cargando" :error="error" :vacio="!filas.length" :textoVacio="textoVacio" :reintentar="cargar">
      <div class="overflow-x-auto rounded-xl border border-secondary-100 bg-surface-card">
        <table class="w-full text-sm">
          <thead class="border-b border-secondary-100 text-left text-secondary">
            <tr>
              <th v-for="c in columnas" :key="c.campo" scope="col" class="px-3 py-2 font-medium">{{ c.titulo }}</th>
              <th v-if="$slots.acciones" scope="col" class="px-3 py-2 text-right font-medium">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(f, i) in filas" :key="f[clave] ?? i" class="border-b border-secondary-100 last:border-0 hover:bg-secondary-100/40">
              <td v-for="c in columnas" :key="c.campo" class="px-3 py-2 align-top">
                <slot :name="`celda-${c.campo}`" :fila="f">{{ valor(f, c.campo) }}</slot>
              </td>
              <td v-if="$slots.acciones" class="px-3 py-2 text-right whitespace-nowrap"><slot name="acciones" :fila="f" :recargar="recargar" /></td>
            </tr>
          </tbody>
        </table>
      </div>
      <TablePagination :totalItems="meta.total" v-model:page="pagina" v-model:pageSize="porPagina" @change="cargar" />
    </EstadoVista>
  </div>
</template>

<script setup>
/**
 * Tabla paginada contra un endpoint con contrato `{ data, meta: { total } }`.
 * Expone `recargar()` para refrescar tras mutaciones.
 */
import { onMounted, ref, watch } from 'vue'
import TablePagination from '@/shared/components/TablePagination.vue'
import EstadoVista from './EstadoVista.vue'
import { api, mensajeError } from '../services/academico'

const props = defineProps({
  url: { type: String, required: true },
  columnas: { type: Array, required: true },
  filtros: { type: Object, default: () => ({}) },
  clave: { type: String, default: 'id' },
  textoVacio: { type: String, default: 'No hay registros para mostrar.' }
})
const filas = ref([])
const meta = ref({ total: 0 })
const pagina = ref(1)
const porPagina = ref(20)
const cargando = ref(true)
const error = ref('')

function valor(f, campo) {
  const v = campo.split('.').reduce((o, k) => o?.[k], f)
  return v ?? '—'
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const params = { page: pagina.value, per_page: porPagina.value, ...Object.fromEntries(Object.entries(props.filtros).filter(([, v]) => v !== '' && v !== null && v !== undefined)) }
    const r = await api.get(props.url, params)
    filas.value = r.data
    meta.value = r.meta
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

function recargar() {
  pagina.value = 1
  return cargar()
}

watch(() => props.url, recargar)
onMounted(cargar)
defineExpose({ recargar, cargar })
</script>
