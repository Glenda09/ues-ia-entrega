<template>
  <div>
    <Encabezado contexto="ADM-01" titulo="Convocatorias de admisión" descripcion="Publica convocatorias, administra requisitos y las fuentes que alimentan al asistente de aspirantes.">
      <RouterLink to="/academico/gestion-carreras"><Button outlined label="Volver" icon="pi pi-arrow-left" /></RouterLink>
      <Button label="Nueva convocatoria" icon="pi pi-plus" @click="abrirCrear" />
    </Encabezado>
    <p v-if="errorAccion" role="alert" class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ errorAccion }}</p>
    <EstadoVista :cargando="cargando" :error="error" :vacio="!lista.length" textoVacio="No hay convocatorias para esta carrera." :reintentar="cargar">
      <article v-for="c in lista" :key="c.id_convocatoria" class="mb-4 rounded-xl border border-secondary-100 bg-surface-card p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div>
            <h2 class="text-lg font-semibold">{{ c.codigo }} · {{ c.titulo_publico }} <EstadoTag :valor="c.estado" /> <Tag v-if="c.abierta" value="Abierta" severity="success" /></h2>
            <p class="text-sm text-secondary">{{ fecha(c.inicio) }} – {{ fecha(c.fin) }} ({{ c.zona }}) · versión {{ c.version }} · requisitos v{{ c.version_requisitos }}</p>
            <p v-if="c.requiere_pago" class="text-sm">Requiere pago: ${{ c.monto_pago }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <Button size="small" outlined icon="pi pi-pencil" label="Editar" @click="abrirEditar(c)" />
            <Button v-if="c.estado === 'borrador'" size="small" label="Publicar" @click="transicion(c, 'publicado')" />
            <Button v-if="c.estado === 'publicado'" size="small" severity="secondary" label="Cerrar" @click="transicion(c, 'cerrado')" />
          </div>
        </div>
        <p class="mt-2 text-sm">{{ c.descripcion_publica }}</p>
        <h3 class="mt-3 text-sm font-semibold">Ofertas</h3>
        <ul class="list-disc pl-5 text-sm"><li v-for="(o, i) in c.ofertas" :key="i">{{ o.sede }} · {{ o.facultad }} · {{ o.modalidad_estudio }} · ingreso {{ o.modalidad_ingreso }}</li></ul>
        <h3 class="mt-3 text-sm font-semibold">Vista pública de requisitos</h3>
        <ul class="space-y-1 text-sm">
          <li v-for="r in c.requisitos" :key="r.codigo">
            <strong>{{ r.nombre }}</strong> ({{ r.tipo === 'anexo' ? 'documento' : 'dato' }}, {{ r.obligatorio ? 'obligatorio' : 'opcional' }}, {{ r.etapa === 'aceptacion' ? 'al aceptar' : 'al enviar' }})
            <span v-if="r.descripcion"> — {{ r.descripcion }}</span>
            <span v-if="r.condicion" class="text-secondary"> · {{ condicionTexto(r.condicion) }}</span>
          </li>
        </ul>
      </article>
    </EstadoVista>

    <section v-if="can('academico_fuente_ia_publicar')" class="mt-8 rounded-xl border border-secondary-100 bg-surface-card p-4">
      <h2 class="mb-2 text-lg font-semibold">Fuentes IA</h2>
      <p class="mb-3 text-sm text-secondary">Selecciona las fuentes que el asistente podrá citar.</p>
      <p v-if="errorFuentes" role="alert" class="mb-2 text-sm text-red-600">{{ errorFuentes }}</p>
      <EstadoVista :cargando="cargandoFuentes" :error="errorCargaFuentes" :vacio="!fuentes.length" textoVacio="No hay fuentes disponibles." :reintentar="cargarFuentes">
        <ul class="divide-y divide-secondary-100">
          <li v-for="f in fuentes" :key="clave(f)" class="flex items-start gap-3 py-2 text-sm">
            <Checkbox v-model="seleccion" :inputId="`f-${clave(f)}`" :value="clave(f)" />
            <label :for="`f-${clave(f)}`" class="flex-1">
              <span class="font-medium">{{ f.titulo }}</span> <span class="text-secondary">({{ f.tipo }})</span>
              <span class="block text-secondary">{{ recorte(f.contenido) }}</span>
              <span v-if="f.publicada" class="text-xs">Publicada v{{ f.publicada.version }} el {{ fecha(f.publicada.publicado_en) }}<span v-if="!f.publicada.actualizada" class="text-amber-600"> · desactualizada</span></span>
              <span v-else class="text-xs text-secondary">No publicada</span>
            </label>
          </li>
        </ul>
        <div class="mt-3 flex gap-2">
          <Button label="Publicar" :disabled="!seleccion.length" @click="publicarFuentes('publicada')" />
          <Button label="Retirar" severity="secondary" :disabled="!seleccion.length" @click="publicarFuentes('retirada')" />
        </div>
      </EstadoVista>
    </section>

    <Dialog v-model:visible="dialogoCrear" modal header="Nueva convocatoria" class="w-full max-w-3xl">
      <form class="space-y-3" @submit.prevent="crear">
        <div class="grid gap-3 md:grid-cols-2">
          <div><label for="n-cod" class="mb-1 block text-sm font-medium">Código</label><InputText id="n-cod" v-model="nueva.codigo" class="w-full" /></div>
          <div><label for="n-tit" class="mb-1 block text-sm font-medium">Título público</label><InputText id="n-tit" v-model="nueva.titulo_publico" class="w-full" /></div>
        </div>
        <div><label for="n-desc" class="mb-1 block text-sm font-medium">Descripción pública</label><Textarea id="n-desc" v-model="nueva.descripcion_publica" rows="3" class="w-full" /></div>
        <div class="grid gap-3 md:grid-cols-2">
          <div><label for="n-ini" class="mb-1 block text-sm font-medium">Inicio</label><input id="n-ini" v-model="nueva.inicio" type="datetime-local" class="w-full rounded border border-secondary-100 p-2" /></div>
          <div><label for="n-fin" class="mb-1 block text-sm font-medium">Fin</label><input id="n-fin" v-model="nueva.fin" type="datetime-local" class="w-full rounded border border-secondary-100 p-2" /></div>
        </div>
        <div class="flex flex-wrap items-center gap-4">
          <div class="flex items-center gap-2"><Checkbox v-model="nueva.requiere_pago" inputId="n-pago" binary /><label for="n-pago" class="text-sm">Requiere pago</label></div>
          <div v-if="nueva.requiere_pago"><label for="n-monto" class="mr-2 text-sm">Monto (USD)</label><InputNumber v-model="nueva.monto_pago" inputId="n-monto" mode="decimal" :minFractionDigits="2" :min="0" /></div>
        </div>
        <fieldset>
          <legend class="mb-1 text-sm font-medium">Ofertas</legend>
          <div v-for="(o, i) in nueva.ofertas" :key="i" class="mb-2 grid gap-2 md:grid-cols-5">
            <Select v-model="o.id_sede" :options="sedes" optionLabel="nombre" optionValue="id_sede" :aria-label="`Sede ${i + 1}`" placeholder="Sede" />
            <Select v-model="o.id_facultad" :options="facultades" optionLabel="nombre" optionValue="id_facultad" :aria-label="`Facultad ${i + 1}`" placeholder="Facultad" />
            <Select v-model="o.modalidad_estudio" :options="['presencial', 'distancia']" :aria-label="`Modalidad de estudio ${i + 1}`" />
            <Select v-model="o.modalidad_ingreso" :options="['general', 'especial']" :aria-label="`Modalidad de ingreso ${i + 1}`" />
            <Button type="button" text severity="danger" icon="pi pi-trash" :aria-label="`Quitar oferta ${i + 1}`" @click="nueva.ofertas.splice(i, 1)" />
          </div>
          <Button type="button" outlined size="small" icon="pi pi-plus" label="Agregar oferta" @click="nueva.ofertas.push({ id_sede: null, id_facultad: null, modalidad_estudio: 'presencial', modalidad_ingreso: 'general' })" />
        </fieldset>
        <p v-if="errorForm" role="alert" class="text-sm text-red-600">{{ errorForm }}</p>
        <div class="flex justify-end gap-2"><Button type="button" text label="Cancelar" @click="dialogoCrear = false" /><Button type="submit" label="Crear" :loading="ocupado" /></div>
      </form>
    </Dialog>

    <Dialog v-model:visible="dialogoEditar" modal :header="`Editar ${edit.codigo ?? ''}`" class="w-full max-w-4xl">
      <form class="space-y-3" @submit.prevent="guardarEdicion">
        <div><label for="e-tit" class="mb-1 block text-sm font-medium">Título público</label><InputText id="e-tit" v-model="edit.titulo_publico" class="w-full" /></div>
        <div><label for="e-desc" class="mb-1 block text-sm font-medium">Descripción pública</label><Textarea id="e-desc" v-model="edit.descripcion_publica" rows="3" class="w-full" /></div>
        <div class="grid gap-3 md:grid-cols-2">
          <div><label for="e-ini" class="mb-1 block text-sm font-medium">Inicio</label><input id="e-ini" v-model="edit.inicio" type="datetime-local" class="w-full rounded border border-secondary-100 p-2" /></div>
          <div><label for="e-fin" class="mb-1 block text-sm font-medium">Fin</label><input id="e-fin" v-model="edit.fin" type="datetime-local" class="w-full rounded border border-secondary-100 p-2" /></div>
        </div>
        <div class="flex flex-wrap items-center gap-4">
          <div class="flex items-center gap-2"><Checkbox v-model="edit.requiere_pago" inputId="e-pago" binary /><label for="e-pago" class="text-sm">Requiere pago</label></div>
          <div v-if="edit.requiere_pago"><label for="e-monto" class="mr-2 text-sm">Monto (USD)</label><InputNumber v-model="edit.monto_pago" inputId="e-monto" mode="decimal" :minFractionDigits="2" :min="0" /></div>
        </div>
        <fieldset>
          <legend class="mb-1 text-sm font-medium">Requisitos</legend>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="text-left text-secondary"><tr><th scope="col" class="px-1">Código</th><th scope="col" class="px-1">Tipo</th><th scope="col" class="px-1">Nombre</th><th scope="col" class="px-1">Descripción</th><th scope="col" class="px-1">Oblig.</th><th scope="col" class="px-1">Etapa</th><th scope="col"><span class="sr-only">Quitar</span></th></tr></thead>
              <tbody>
                <tr v-for="(r, i) in edit.requisitos" :key="i">
                  <td class="p-1"><InputText v-model="r.codigo" :aria-label="`Código del requisito ${i + 1}`" class="w-28" /></td>
                  <td class="p-1"><Select v-model="r.tipo" :options="['campo', 'anexo']" :aria-label="`Tipo del requisito ${i + 1}`" /></td>
                  <td class="p-1"><InputText v-model="r.nombre" :aria-label="`Nombre del requisito ${i + 1}`" /></td>
                  <td class="p-1"><InputText v-model="r.descripcion" :aria-label="`Descripción del requisito ${i + 1}`" /></td>
                  <td class="p-1 text-center"><Checkbox v-model="r.obligatorio" binary :aria-label="`Obligatorio ${i + 1}`" /></td>
                  <td class="p-1"><Select v-model="r.etapa" :options="['envio', 'aceptacion']" :aria-label="`Etapa del requisito ${i + 1}`" /></td>
                  <td class="p-1"><Button type="button" text severity="danger" icon="pi pi-trash" :aria-label="`Quitar requisito ${i + 1}`" @click="edit.requisitos.splice(i, 1)" /></td>
                </tr>
              </tbody>
            </table>
          </div>
          <Button type="button" outlined size="small" icon="pi pi-plus" label="Agregar requisito" @click="edit.requisitos.push({ codigo: '', tipo: 'campo', nombre: '', descripcion: '', obligatorio: true, etapa: 'envio', condicion: null })" />
        </fieldset>
        <p v-if="errorForm" role="alert" class="text-sm text-red-600">{{ errorForm }}</p>
        <div class="flex justify-end gap-2"><Button type="button" text label="Cancelar" @click="dialogoEditar = false" /><Button type="submit" label="Guardar" :loading="ocupado" /></div>
      </form>
    </Dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Button, Checkbox, Dialog, InputNumber, InputText, Select, Tag, Textarea } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import { api, aLocal, deLocal, fecha, mensajeError } from '../services/academico'
import { useValidatePermission } from '@/shared/composables/useValidatePermission'

const { can } = useValidatePermission()
const carrera = useRoute().params.carrera
const lista = ref([])
const cargando = ref(true)
const error = ref('')
const errorAccion = ref('')
const errorForm = ref('')
const ocupado = ref(false)
const sedes = ref([])
const facultades = ref([])

function condicionTexto(c) {
  if (!c) return ''
  const etiqueta = (v) => (v === true ? 'sí' : v === false ? 'no' : Array.isArray(v) ? v.join(' o ') : String(v))
  const partes = []
  if (Array.isArray(c.todas ?? c.all)) {
    for (const x of c.todas ?? c.all) partes.push(`${x.campo ?? x.field} ${x.operador === '!=' ? '≠' : '='} ${etiqueta(x.valor ?? x.value)}`)
  } else if (typeof c === 'object') {
    for (const [k, v] of Object.entries(c)) partes.push(`${k.replaceAll('_', ' ')} = ${etiqueta(v)}`)
  } else {
    return String(c)
  }
  return `Aplica si ${partes.join(' y ')}`
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    lista.value = (await api.get(`/academico/carreras/${carrera}/convocatorias`)).data
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

async function cargarCatalogos() {
  if (sedes.value.length) return
  try {
    sedes.value = (await api.get('/academico/sedes', { per_page: 100, estado: 'activa' })).data
    facultades.value = (await api.get('/academico/facultades', { per_page: 100, estado: 'activa' })).data
  } catch (e) {
    errorForm.value = mensajeError(e)
  }
}

const vacia = () => ({ codigo: '', titulo_publico: '', descripcion_publica: '', inicio: '', fin: '', requiere_pago: false, monto_pago: null, ofertas: [{ id_sede: null, id_facultad: null, modalidad_estudio: 'presencial', modalidad_ingreso: 'general' }] })
const dialogoCrear = ref(false)
const nueva = reactive(vacia())

function abrirCrear() {
  Object.assign(nueva, vacia())
  errorForm.value = ''
  dialogoCrear.value = true
  cargarCatalogos()
}

async function crear() {
  ocupado.value = true
  errorForm.value = ''
  try {
    await api.post(`/academico/carreras/${carrera}/convocatorias`, {
      codigo: nueva.codigo, titulo_publico: nueva.titulo_publico, descripcion_publica: nueva.descripcion_publica,
      inicio: deLocal(nueva.inicio), fin: deLocal(nueva.fin), requiere_pago: nueva.requiere_pago,
      monto_pago: nueva.requiere_pago ? nueva.monto_pago : null, ofertas: nueva.ofertas
    })
    dialogoCrear.value = false
    await cargar()
  } catch (e) {
    errorForm.value = mensajeError(e)
  } finally {
    ocupado.value = false
  }
}

const dialogoEditar = ref(false)
const edit = reactive({})
let original = null

function abrirEditar(c) {
  original = c
  Object.keys(edit).forEach((k) => delete edit[k])
  Object.assign(edit, {
    codigo: c.codigo, titulo_publico: c.titulo_publico, descripcion_publica: c.descripcion_publica,
    inicio: aLocal(c.inicio), fin: aLocal(c.fin), requiere_pago: c.requiere_pago, monto_pago: c.monto_pago ? Number(c.monto_pago) : null,
    requisitos: JSON.parse(JSON.stringify(c.requisitos ?? []))
  })
  errorForm.value = ''
  dialogoEditar.value = true
}

async function guardarEdicion() {
  const o = original
  const body = { version: o.version }
  for (const k of ['titulo_publico', 'descripcion_publica']) if (edit[k] !== o[k]) body[k] = edit[k]
  if (edit.inicio !== aLocal(o.inicio)) body.inicio = deLocal(edit.inicio)
  if (edit.fin !== aLocal(o.fin)) body.fin = deLocal(edit.fin)
  if (edit.requiere_pago !== o.requiere_pago) body.requiere_pago = edit.requiere_pago
  if (edit.requiere_pago && Number(edit.monto_pago) !== Number(o.monto_pago)) body.monto_pago = edit.monto_pago
  if (JSON.stringify(edit.requisitos) !== JSON.stringify(o.requisitos)) {
    body.requisitos = edit.requisitos
    body.version_requisitos = o.version_requisitos
  }
  ocupado.value = true
  errorForm.value = ''
  try {
    await api.patch(`/academico/convocatorias/${o.id_convocatoria}`, body)
    dialogoEditar.value = false
    await cargar()
  } catch (e) {
    errorForm.value = mensajeError(e)
  } finally {
    ocupado.value = false
  }
}

async function transicion(c, estado) {
  errorAccion.value = ''
  try {
    await api.patch(`/academico/convocatorias/${c.id_convocatoria}`, { version: c.version, estado })
  } catch (e) {
    errorAccion.value = mensajeError(e)
  }
  await cargar()
}

const fuentes = ref([])
const seleccion = ref([])
const cargandoFuentes = ref(false)
const errorCargaFuentes = ref('')
const errorFuentes = ref('')
const clave = (f) => `${f.tipo}:${f.id_origen}`
const recorte = (t) => (t && t.length > 160 ? `${t.slice(0, 160)}…` : (t ?? ''))

async function cargarFuentes() {
  cargandoFuentes.value = true
  errorCargaFuentes.value = ''
  try {
    fuentes.value = (await api.get(`/academico/carreras/${carrera}/fuentes-ia`)).data
    seleccion.value = []
  } catch (e) {
    errorCargaFuentes.value = mensajeError(e)
  } finally {
    cargandoFuentes.value = false
  }
}

async function publicarFuentes(estado) {
  errorFuentes.value = ''
  const elegidas = fuentes.value.filter((f) => seleccion.value.includes(clave(f))).map((f) => ({ tipo: f.tipo, id_origen: f.id_origen }))
  try {
    await api.post(`/academico/carreras/${carrera}/fuentes-ia/publicaciones`, { estado, fuentes: elegidas })
    await cargarFuentes()
  } catch (e) {
    errorFuentes.value = mensajeError(e)
  }
}

onMounted(() => {
  cargar()
  if (can('academico_fuente_ia_publicar')) cargarFuentes()
})
</script>
