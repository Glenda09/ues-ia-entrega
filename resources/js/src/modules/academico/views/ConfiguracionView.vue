<template>
  <div>
    <Encabezado contexto="BASE-02" titulo="Configuración académica" descripcion="Parámetros generales de calendario, inscripción, calificaciones, admisión, credenciales y consumo de IA. Cada cambio queda versionado con motivo." />
    <EstadoVista :cargando="cargando" :error="error" :reintentar="cargar">
      <div v-if="conf" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <form class="space-y-6" @submit.prevent="guardar">
          <fieldset :disabled="!puedeEditar || guardando" class="space-y-6">
            <section v-for="grupo in grupos" :key="grupo.clave" class="rounded-xl border border-secondary-100 bg-surface-card p-5">
              <h2 class="mb-4 text-lg font-semibold">{{ grupo.titulo }}</h2>
              <div class="grid gap-4 md:grid-cols-2">
                <div v-for="campo in grupo.campos" :key="campo.k" :class="campo.ancho ? 'md:col-span-2' : ''">
                  <label :for="`${grupo.clave}-${campo.k}`" class="mb-1 block text-sm font-medium">{{ campo.l }}</label>
                  <ToggleSwitch v-if="campo.t === 'bool'" :inputId="`${grupo.clave}-${campo.k}`" v-model="form[grupo.clave][campo.k]" />
                  <Select v-else-if="campo.t === 'zona'" :inputId="`${grupo.clave}-${campo.k}`" v-model="form[grupo.clave][campo.k]" :options="conf.catalogos.zonas" filter class="w-full" />
                  <Select v-else-if="campo.t === 'escala'" :inputId="`${grupo.clave}-${campo.k}`" v-model="form[grupo.clave][campo.k]" :options="conf.escalas" :optionLabel="(e) => `${e.codigo} v${e.version_politica} · ${e.nombre}`" optionValue="id_escala" showClear class="w-full" placeholder="Sin escala vigente" />
                  <MultiSelect v-else-if="campo.t === 'permisos'" :inputId="`${grupo.clave}-${campo.k}`" v-model="form[grupo.clave][campo.k]" :options="conf.catalogos.permisos_operativos" class="w-full" placeholder="Nadie opera en gracia" />
                  <InputText v-else :id="`${grupo.clave}-${campo.k}`" v-model="form[grupo.clave][campo.k]" :placeholder="campo.p" class="w-full" />
                  <p v-if="campo.ayuda" class="mt-1 text-xs text-secondary">{{ campo.ayuda }}</p>
                </div>
              </div>
            </section>
            <section v-if="puedeEditar" class="rounded-xl border border-secondary-100 bg-surface-card p-5">
              <label for="motivo" class="mb-1 block text-sm font-medium">Motivo del cambio</label>
              <InputText id="motivo" v-model="motivo" class="w-full" placeholder="Ej.: Calendario del ciclo I 2027" />
              <p v-if="errorGuardar" role="alert" class="mt-3 text-sm text-red-600">{{ errorGuardar }}</p>
              <Button type="submit" class="mt-4" label="Guardar configuración" icon="pi pi-save" :loading="guardando" :disabled="motivo.trim().length < 3" />
            </section>
          </fieldset>
        </form>

        <aside class="space-y-6">
          <section class="rounded-xl border border-secondary-100 bg-surface-card p-5">
            <h2 class="text-lg font-semibold">Revisión vigente</h2>
            <p class="mt-1 text-3xl font-bold">v{{ conf.version }}</p>
            <h3 class="mt-4 text-sm font-semibold">Reglas confirmadas</h3>
            <p class="mt-1 text-xs text-secondary">{{ conf.catalogos.formato_carnet }}</p>
            <ul class="mt-2 space-y-1 text-xs">
              <li v-for="(destinos, origen) in conf.catalogos.matriz_estados" :key="origen">
                <b>{{ conf.catalogos.estados_estudiante[origen] }}</b> → {{ destinos.map((d) => conf.catalogos.estados_estudiante[d]).join(', ') }}
              </li>
            </ul>
          </section>

          <section class="rounded-xl border border-secondary-100 bg-surface-card p-5">
            <h2 class="mb-3 text-lg font-semibold">Escalas de calificación</h2>
            <ul class="space-y-2 text-sm">
              <li v-for="e in conf.escalas" :key="e.id_escala" class="rounded border border-secondary-100 p-2">
                <b>{{ e.codigo }} v{{ e.version_politica }}</b> · {{ e.nombre }}<br />
                <span class="text-xs text-secondary">{{ Number(e.minimo) }}–{{ Number(e.maximo) }}, aprueba {{ Number(e.umbral_aprobacion) }}, {{ e.precision }} dec., {{ e.redondeo }}, total {{ Number(e.ponderacion_total) }}, ausencia {{ e.ausencia }}</span>
              </li>
              <li v-if="!conf.escalas.length" class="text-secondary">Sin escalas registradas.</li>
            </ul>
            <form v-if="puedeEditar" class="mt-4 grid grid-cols-2 gap-2" @submit.prevent="crearEscala">
              <InputText v-model="escala.codigo" placeholder="Código" aria-label="Código" />
              <InputText v-model="escala.nombre" placeholder="Nombre" aria-label="Nombre" />
              <InputText v-model="escala.minimo" placeholder="Mínimo" aria-label="Mínimo" />
              <InputText v-model="escala.maximo" placeholder="Máximo" aria-label="Máximo" />
              <InputText v-model="escala.umbral_aprobacion" placeholder="Aprobación" aria-label="Umbral" />
              <InputText v-model="escala.precision" placeholder="Precisión" aria-label="Precisión" />
              <Select v-model="escala.redondeo" :options="conf.catalogos.redondeos" aria-label="Redondeo" />
              <Select v-model="escala.ausencia" :options="conf.catalogos.ausencias" aria-label="Ausencia" />
              <InputText v-model="escala.ponderacion_total" placeholder="Total ponderación" aria-label="Total" class="col-span-2" />
              <Button type="submit" label="Crear versión de escala" size="small" class="col-span-2" outlined />
            </form>
          </section>

          <section v-if="consumo" class="rounded-xl border border-secondary-100 bg-surface-card p-5">
            <h2 class="text-lg font-semibold">Consumo de IA</h2>
            <p class="text-xs text-secondary">{{ consumo.proveedor }} · {{ consumo.modelo }} · ámbito {{ consumo.ambito }}</p>
            <dl class="mt-3 grid grid-cols-2 gap-1 text-sm">
              <dt>Límite</dt><dd class="text-right">USD {{ usd(consumo.limite_usd) }}</dd>
              <dt>Gastado</dt><dd class="text-right">USD {{ usd(consumo.gastado_usd) }}</dd>
              <dt>Reservado</dt><dd class="text-right">USD {{ usd(consumo.reservado_usd) }}</dd>
              <dt>Saldo</dt><dd class="text-right font-semibold">USD {{ usd(consumo.saldo_usd) }}</dd>
            </dl>
            <p class="mt-2"><EstadoTag :valor="consumo.disponibilidad.disponible ? 'activa' : 'cerrada'" :texto="consumo.disponibilidad.disponible ? 'Agentes disponibles' : 'Agentes ocultos'" /></p>
            <ul class="mt-3 text-xs text-secondary">
              <li v-for="a in consumo.por_agente" :key="a.agente">{{ a.agente }}: {{ a.ejecuciones }} turnos, USD {{ usd(a.gasto) }}</li>
            </ul>
          </section>

          <section class="rounded-xl border border-secondary-100 bg-surface-card p-5">
            <h2 class="mb-2 text-lg font-semibold">Historial de revisiones</h2>
            <ul class="space-y-1 text-sm">
              <li v-for="r in revisiones" :key="r.revision"><b>v{{ r.revision }}</b> · {{ r.motivo }} <span class="text-xs text-secondary">({{ fecha(r.instante) }})</span></li>
            </ul>
          </section>
        </aside>
      </div>
    </EstadoVista>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Button, InputText, MultiSelect, Select, ToggleSwitch } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import { api, fecha, mensajeError } from '../services/academico'
import { useValidatePermission } from '@/shared/composables/useValidatePermission'

const { can } = useValidatePermission()
const puedeEditar = computed(() => can('academico_configuracion_editar'))
const cargando = ref(true)
const error = ref('')
const errorGuardar = ref('')
const guardando = ref(false)
const conf = ref(null)
const consumo = ref(null)
const revisiones = ref([])
const motivo = ref('')
const form = reactive({})
const escala = reactive({ codigo: 'UES', nombre: '', minimo: '0', maximo: '10', umbral_aprobacion: '6', precision: '2', redondeo: 'half_up', ponderacion_total: '100', ausencia: 'bloquear' })

const grupos = [
  { clave: 'calendario', titulo: 'Calendario institucional', campos: [
    { k: 'zona', l: 'Zona horaria (IANA)', t: 'zona' },
    { k: 'ciclo_inicio', l: 'Inicio de ciclo de referencia', p: 'AAAA-MM-DD' },
    { k: 'ciclo_fin', l: 'Fin de ciclo de referencia', p: 'AAAA-MM-DD' },
    { k: 'inscripcion_inicio', l: 'Inicio inscripción', p: 'AAAA-MM-DD HH:mm' },
    { k: 'inscripcion_fin', l: 'Fin regular inscripción', p: 'AAAA-MM-DD HH:mm' },
    { k: 'notas_inicio', l: 'Inicio registro de notas', p: 'AAAA-MM-DD HH:mm' },
    { k: 'notas_fin', l: 'Fin regular de notas', p: 'AAAA-MM-DD HH:mm' },
    { k: 'gracia_horas_inscripcion', l: 'Gracia de inscripción (horas)' },
    { k: 'gracia_horas_notas', l: 'Gracia de notas (horas)' }
  ] },
  { clave: 'aplicacion', titulo: 'Aplicación del calendario', campos: [
    { k: 'permisos_gracia_inscripcion', l: 'Permisos que operan en gracia de inscripción', t: 'permisos', ancho: true },
    { k: 'permisos_gracia_notas', l: 'Permisos que operan en gracia de notas', t: 'permisos', ancho: true },
    { k: 'permitir_inscripcion_antes_clases', l: 'Permitir inscripción antes del inicio de clases', t: 'bool' },
    { k: 'permitir_ciclos_solapados', l: 'Permitir ciclos publicados solapados', t: 'bool' }
  ] },
  { clave: 'inscripcion', titulo: 'Inscripción', campos: [
    { k: 'cupo_default', l: 'Cupo por defecto de grupo' },
    { k: 'carga_maxima_creditos', l: 'Carga máxima (créditos, vacío = sin límite)' },
    { k: 'validar_choque_horario', l: 'Validar choque de horario', t: 'bool' },
    { k: 'permitir_repetir_reprobadas', l: 'Permitir repetir reprobadas', t: 'bool' },
    { k: 'correquisitos_activos', l: 'Activar correquisitos', t: 'bool' },
    { k: 'equivalencias_activas', l: 'Activar equivalencias', t: 'bool' }
  ] },
  { clave: 'calificaciones', titulo: 'Calificaciones', campos: [{ k: 'id_escala_vigente', l: 'Escala vigente para nuevos grupos', t: 'escala', ancho: true }] },
  { clave: 'admision', titulo: 'Admisión', campos: [
    { k: 'mayoria_edad', l: 'Mayoría de edad (años)' },
    { k: 'correccion_horas', l: 'Plazo de corrección (horas)' },
    { k: 'correccion_tras_cierre', l: 'Permitir correcciones tras el cierre', t: 'bool' },
    { k: 'max_bytes_archivo', l: 'Máximo por archivo (bytes)' },
    { k: 'max_bytes_peticion', l: 'Máximo por petición (bytes)' },
    { k: 'monto_pago_usd', l: 'Monto de orden de pago (USD)' }
  ] },
  { clave: 'credenciales', titulo: 'Credenciales y contacto', campos: [
    { k: 'password_temporal_horas', l: 'Vigencia de contraseña temporal (horas)' },
    { k: 'remitente_nombre', l: 'Nombre del remitente' },
    { k: 'contacto_email', l: 'Correo de orientación' },
    { k: 'contacto_telefono', l: 'Teléfono de orientación' },
    { k: 'contacto_url', l: 'Sitio de orientación', ancho: true }
  ] }
]

const NUMERICOS = ['gracia_horas_inscripcion', 'gracia_horas_notas', 'cupo_default', 'mayoria_edad', 'correccion_horas', 'max_bytes_archivo', 'max_bytes_peticion', 'password_temporal_horas']

function usd(v) {
  return v == null ? '—' : Number(v).toFixed(6)
}

function normalizar(grupo, valores) {
  const out = {}
  for (const [k, v] of Object.entries(valores)) {
    if (v === '' || v === undefined) out[k] = null
    else if (NUMERICOS.includes(k)) out[k] = Number(v)
    else if (k === 'carga_maxima_creditos') out[k] = v === null ? null : Number(v)
    else out[k] = v
  }
  return out
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const { data } = await api.get('/academico/configuracion')
    conf.value = data
    for (const g of grupos) form[g.clave] = { ...data.valores[g.clave] }
    revisiones.value = (await api.get('/academico/configuracion/revisiones', { per_page: 10 })).data
    if (can('academico_ia_consumo_ver')) consumo.value = (await api.get('/academico/ia/consumo')).data
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

async function guardar() {
  guardando.value = true
  errorGuardar.value = ''
  try {
    const grupos_ = Object.fromEntries(grupos.map((g) => [g.clave, normalizar(g.clave, form[g.clave])]))
    await api.put('/academico/configuracion', { version: conf.value.version, motivo: motivo.value, grupos: grupos_ })
    motivo.value = ''
    await cargar()
  } catch (e) {
    errorGuardar.value = e.code === 'VERSION_CONFLICTO' ? 'Otra persona modificó la configuración. Se recargaron los valores actuales.' : mensajeError(e)
    if (e.code === 'VERSION_CONFLICTO') await cargar()
  } finally {
    guardando.value = false
  }
}

async function crearEscala() {
  try {
    await api.post('/academico/configuracion/escalas', { ...escala, minimo: Number(escala.minimo), maximo: Number(escala.maximo), umbral_aprobacion: Number(escala.umbral_aprobacion), precision: Number(escala.precision), ponderacion_total: Number(escala.ponderacion_total) })
    await cargar()
  } catch (e) {
    errorGuardar.value = mensajeError(e)
  }
}

onMounted(cargar)
</script>
