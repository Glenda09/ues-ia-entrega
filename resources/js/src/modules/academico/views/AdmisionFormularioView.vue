<template>
  <div class="mx-auto max-w-4xl p-4">
    <header class="mb-6 flex flex-wrap items-center justify-between gap-2 border-b border-secondary-100 pb-3">
      <nav aria-label="Navegación pública" class="flex flex-wrap gap-4 text-sm">
        <RouterLink to="/carreras" class="underline">Carreras</RouterLink>
        <RouterLink to="/asistente-aspirantes" class="underline">Asistente de aspirantes</RouterLink>
        <RouterLink to="/login" class="underline">Iniciar sesión</RouterLink>
      </nav>
    </header>

    <div v-if="!sesion" class="rounded-xl border border-secondary-100 bg-surface-card p-6">
      <h1 class="mb-2 text-2xl font-semibold">Solicitud de admisión</h1>
      <p class="mb-4 text-secondary">Necesitas iniciar sesión con tu cuenta de aspirante para completar la solicitud.</p>
      <div class="flex flex-wrap gap-2">
        <RouterLink :to="`/login?redirect=${encodeURIComponent(ruta.fullPath)}`"><Button label="Iniciar sesión" /></RouterLink>
        <RouterLink to="/admision/registro"><Button outlined label="Crear cuenta" /></RouterLink>
      </div>
    </div>

    <EstadoVista v-else :cargando="cargando" :error="error" :reintentar="cargar">
      <template v-if="d">
        <h1 class="text-2xl font-semibold">{{ d.convocatoria.titulo_publico }}</h1>
        <p class="mb-4 text-secondary">{{ d.convocatoria.descripcion_publica }}</p>

        <div v-if="d.solicitud" class="rounded-xl border border-secondary-100 bg-surface-card p-4">
          <p>Tu solicitud con folio <strong>{{ d.solicitud.folio }}</strong> está <EstadoTag :valor="d.solicitud.estado" />.</p>
          <RouterLink to="/admision/mis-solicitudes"><Button class="mt-3" label="Ver mis solicitudes" /></RouterLink>
        </div>

        <template v-else>
          <p v-if="aviso" role="status" class="mb-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">{{ aviso }}</p>
          <p v-if="errorGlobal" role="alert" class="mb-3 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            {{ errorGlobal }}
            <Button v-if="recargable" class="ml-2" size="small" outlined label="Recargar" @click="cargar" />
          </p>

          <ol class="mb-6 flex flex-wrap gap-2" aria-label="Pasos de la solicitud">
            <li v-for="(t, i) in PASOS" :key="t">
              <button type="button" class="rounded-full border px-3 py-1 text-sm" :class="paso === i + 1 ? 'border-primary bg-primary text-white' : 'border-secondary-100'" :aria-current="paso === i + 1 ? 'step' : undefined" @click="paso = i + 1">{{ i + 1 }}. {{ t }}</button>
            </li>
          </ol>

          <section v-if="paso === 1" aria-labelledby="p1">
            <h2 id="p1" class="mb-2 text-lg font-semibold">Cuenta</h2>
            <p class="text-sm">Estás aplicando con la cuenta <strong>{{ emailSesion || 'de tu sesión actual' }}</strong>. Los avisos se enviarán a ese correo.</p>
            <Button class="mt-4" label="Continuar" @click="paso = 2" />
          </section>

          <form v-else-if="paso === 2" aria-labelledby="p2" class="space-y-3" @submit.prevent="guardarPaso(2, CAMPOS_P2)">
            <h2 id="p2" class="text-lg font-semibold">Datos personales</h2>
            <div class="grid gap-3 md:grid-cols-2">
              <div v-for="c in camposPersonales" :key="c.k">
                <label :for="`f-${c.k}`" class="mb-1 block text-sm font-medium">{{ c.t }}</label>
                <Select v-if="c.opciones" v-model="datos[c.k]" :inputId="`f-${c.k}`" :options="c.opciones" showClear class="w-full" />
                <input v-else-if="c.type === 'date'" :id="`f-${c.k}`" v-model="datos[c.k]" type="date" class="w-full rounded border border-secondary-100 p-2" />
                <InputText v-else :id="`f-${c.k}`" v-model="datos[c.k]" class="w-full" />
                <p v-if="err(c.k)" role="alert" class="text-xs text-red-600">{{ err(c.k) }}</p>
              </div>
            </div>
            <Pie :ocupado="ocupado" @atras="paso = 1" />
          </form>

          <form v-else-if="paso === 3" aria-labelledby="p3" class="space-y-3" @submit.prevent="guardarPaso(3, CAMPOS_P3)">
            <h2 id="p3" class="text-lg font-semibold">Información académica</h2>
            <div class="grid gap-3 md:grid-cols-2">
              <div><label for="f-institucion_procedencia" class="mb-1 block text-sm font-medium">Institución de procedencia</label><InputText id="f-institucion_procedencia" v-model="datos.institucion_procedencia" class="w-full" /><p v-if="err('institucion_procedencia')" role="alert" class="text-xs text-red-600">{{ err('institucion_procedencia') }}</p></div>
              <div><label for="f-tipo_bachillerato" class="mb-1 block text-sm font-medium">Tipo de bachillerato</label><InputText id="f-tipo_bachillerato" v-model="datos.tipo_bachillerato" class="w-full" /><p v-if="err('tipo_bachillerato')" role="alert" class="text-xs text-red-600">{{ err('tipo_bachillerato') }}</p></div>
              <div><label for="f-estado_graduacion" class="mb-1 block text-sm font-medium">Estado de graduación</label><Select v-model="datos.estado_graduacion" inputId="f-estado_graduacion" :options="['graduado', 'pendiente']" class="w-full" /><p v-if="err('estado_graduacion')" role="alert" class="text-xs text-red-600">{{ err('estado_graduacion') }}</p></div>
              <div><label for="f-anio_graduacion" class="mb-1 block text-sm font-medium">Año de graduación</label><InputText id="f-anio_graduacion" v-model="datos.anio_graduacion" inputmode="numeric" class="w-full" /><p v-if="err('anio_graduacion')" role="alert" class="text-xs text-red-600">{{ err('anio_graduacion') }}</p></div>
              <div><label for="f-tipo_prueba" class="mb-1 block text-sm font-medium">Tipo de prueba</label><Select v-model="datos.tipo_prueba" inputId="f-tipo_prueba" :options="['PAES', 'AVANZO']" class="w-full" /><p v-if="err('tipo_prueba')" role="alert" class="text-xs text-red-600">{{ err('tipo_prueba') }}</p></div>
              <div><label for="f-resultado_paes_avanzo" class="mb-1 block text-sm font-medium">Resultado PAES/AVANZO</label><InputText id="f-resultado_paes_avanzo" v-model="datos.resultado_paes_avanzo" class="w-full" /><p v-if="err('resultado_paes_avanzo')" role="alert" class="text-xs text-red-600">{{ err('resultado_paes_avanzo') }}</p></div>
              <div class="md:col-span-2">
                <label for="f-oferta" class="mb-1 block text-sm font-medium">Carrera, sede, facultad y modalidades</label>
                <Select v-model="ofertaSel" inputId="f-oferta" :options="opcionesOferta" optionLabel="etiqueta" optionValue="idx" placeholder="Selecciona una opción" class="w-full" />
                <p v-for="k in ['id_carrera', 'id_sede', 'id_facultad', 'modalidad_estudio', 'modalidad_ingreso']" :key="k" v-show="err(k)" role="alert" class="text-xs text-red-600">{{ err(k) }}</p>
              </div>
              <div><label for="f-referencia_comprobante" class="mb-1 block text-sm font-medium">Referencia del comprobante de pago</label><InputText id="f-referencia_comprobante" v-model="datos.referencia_comprobante" class="w-full" /><p v-if="err('referencia_comprobante')" role="alert" class="text-xs text-red-600">{{ err('referencia_comprobante') }}</p></div>
            </div>
            <div v-if="d.orden_pago" class="rounded-lg bg-secondary-100/40 p-3 text-sm">Orden de pago <strong>{{ d.orden_pago.folio }}</strong> · ${{ d.orden_pago.monto }} · <EstadoTag :valor="d.orden_pago.estado" /><span v-if="d.orden_pago.observacion"> — {{ d.orden_pago.observacion }}</span></div>
            <Pie :ocupado="ocupado" @atras="paso = 2" />
          </form>

          <section v-else-if="paso === 4" aria-labelledby="p4" class="space-y-4">
            <h2 id="p4" class="text-lg font-semibold">Documentos</h2>
            <p v-if="!anexosAplicables.length" class="text-sm text-secondary">No hay documentos por subir.</p>
            <div v-for="r in anexosAplicables" :key="r.codigo" class="rounded-lg border border-secondary-100 p-3">
              <label :for="`up-${r.codigo}`" class="block text-sm font-medium">{{ r.nombre }} <span v-if="r.obligatorio" class="text-red-600">(obligatorio)</span></label>
              <p v-if="r.descripcion" class="text-xs text-secondary">{{ r.descripcion }}</p>
              <p v-if="r.codigo === 'fotografia'" class="text-xs text-secondary">Fotografía en formato JPG de 150×195 píxeles.</p>
              <input :id="`up-${r.codigo}`" type="file" :accept="aceptar(r)" class="mt-1 text-sm" :disabled="subiendo === r.codigo" @change="(ev) => subir(r, ev)" />
              <p v-if="d.borrador?.anexos?.[r.codigo]" class="mt-1 text-xs text-green-700">Archivo actual: {{ d.borrador.anexos[r.codigo].nombre_seguro }} ({{ Math.max(1, Math.round(d.borrador.anexos[r.codigo].tamano / 1024)) }} KB)</p>
              <p v-if="err(`anexos.${r.codigo}`) || (errArchivo === r.codigo && err('archivo'))" role="alert" class="text-xs text-red-600">{{ err(`anexos.${r.codigo}`) || err('archivo') }}</p>
            </div>
            <div class="flex gap-2"><Button type="button" text label="Atrás" @click="paso = 3" /><Button type="button" label="Continuar" @click="paso = 5" /></div>
          </section>

          <section v-else aria-labelledby="p5" class="space-y-4">
            <h2 id="p5" class="text-lg font-semibold">Confirmación</h2>
            <dl class="grid gap-x-6 gap-y-1 text-sm md:grid-cols-2"><div v-for="(v, k) in resumen" :key="k"><dt class="inline font-medium">{{ k.replaceAll('_', ' ') }}: </dt><dd class="inline">{{ v }}</dd></div></dl>
            <h3 class="font-semibold">Documentos</h3>
            <ul class="text-sm"><li v-for="r in anexosAplicables" :key="r.codigo">{{ r.nombre }}: <span v-if="d.borrador?.anexos?.[r.codigo]" class="text-green-700">{{ d.borrador.anexos[r.codigo].nombre_seguro }}</span><span v-else :class="r.obligatorio ? 'text-red-600' : 'text-secondary'">{{ r.obligatorio ? 'Falta (obligatorio)' : 'No adjuntado' }}</span></li></ul>
            <p v-if="faltantes.length" role="alert" class="text-sm text-red-600">Faltan documentos obligatorios: {{ faltantes.map((r) => r.nombre).join(', ') }}.</p>
            <p v-if="errorEnvio" role="alert" class="text-sm text-red-600">{{ errorEnvio }}</p>
            <div class="flex gap-2"><Button type="button" text label="Atrás" @click="paso = 4" /><Button type="button" label="Enviar solicitud" :loading="ocupado" :disabled="faltantes.length > 0 || !d.borrador" @click="enviar" /></div>
          </section>
        </template>
      </template>
    </EstadoVista>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Button, InputText, Select } from 'primevue'
import EstadoVista from '../components/EstadoVista.vue'
import EstadoTag from '../components/EstadoTag.vue'
import { api, mensajeError, nuevaClave, postIdempotente } from '../services/academico'
import http from '@/shared/services/http'

const ruta = useRoute()
const router = useRouter()
const id = ruta.params.convocatoria
const PASOS = ['Cuenta', 'Datos personales', 'Académica', 'Documentos', 'Confirmación']
const NACIONALIDADES = ['salvadoreña', 'guatemalteca', 'hondureña', 'nicaragüense', 'costarricense', 'otra']
const CAMPOS_P2 = ['nombres', 'primer_apellido', 'segundo_apellido', 'fecha_nacimiento', 'nacionalidad', 'dui', 'nie', 'pais_documento', 'pasaporte', 'telefono_celular', 'departamento', 'municipio_distrito', 'direccion', 'sexo_genero', 'estado_civil', 'contacto_emergencia']
const CAMPOS_P3 = ['institucion_procedencia', 'tipo_bachillerato', 'estado_graduacion', 'anio_graduacion', 'tipo_prueba', 'resultado_paes_avanzo', 'modalidad_estudio', 'id_sede', 'id_facultad', 'id_carrera', 'modalidad_ingreso', 'referencia_comprobante']

const Pie = defineComponent({
  props: { ocupado: Boolean },
  emits: ['atras'],
  setup(p, { emit }) {
    return () => h('div', { class: 'flex gap-2' }, [
      h(Button, { type: 'button', text: true, label: 'Atrás', onClick: () => emit('atras') }),
      h(Button, { type: 'submit', label: 'Guardar y continuar', loading: p.ocupado })
    ])
  }
})

const sesion = ref(!!localStorage.getItem('access_token'))
const emailSesion = ref('')
const d = ref(null)
const cargando = ref(true)
const error = ref('')
const errorGlobal = ref('')
const recargable = ref(false)
const aviso = ref('')
const errores = ref({})
const ocupado = ref(false)
const subiendo = ref('')
const errArchivo = ref('')
const errorEnvio = ref('')
const paso = ref(1)
const datos = reactive({})
const ofertaSel = ref(null)
let vinculado = false
let claveEnvio = nuevaClave()

const err = (k) => (errores.value[`datos.${k}`] ?? errores.value[k] ?? [])[0] ?? ''

const camposPersonales = computed(() => {
  const sv = datos.nacionalidad === 'salvadoreña'
  const base = [
    { k: 'nombres', t: 'Nombres' }, { k: 'primer_apellido', t: 'Primer apellido' }, { k: 'segundo_apellido', t: 'Segundo apellido' },
    { k: 'fecha_nacimiento', t: 'Fecha de nacimiento', type: 'date' }, { k: 'nacionalidad', t: 'Nacionalidad', opciones: NACIONALIDADES }
  ]
  if (sv) base.push({ k: 'dui', t: 'DUI' })
  base.push({ k: 'nie', t: 'NIE (si aplica)' })
  if (datos.nacionalidad && !sv) base.push({ k: 'pais_documento', t: 'País del documento' }, { k: 'pasaporte', t: 'Pasaporte' })
  base.push({ k: 'telefono_celular', t: 'Teléfono celular' }, { k: 'departamento', t: 'Departamento' }, { k: 'municipio_distrito', t: 'Municipio / distrito' }, { k: 'direccion', t: 'Dirección' }, { k: 'sexo_genero', t: 'Sexo / género' }, { k: 'estado_civil', t: 'Estado civil' }, { k: 'contacto_emergencia', t: 'Contacto de emergencia' })
  return base
})

const opcionesOferta = computed(() => (d.value?.convocatoria.ofertas ?? []).map((o, idx) => ({ ...o, idx, etiqueta: `${o.facultad} · ${o.sede} · ${o.modalidad_estudio} · ingreso ${o.modalidad_ingreso}` })))
const anexosAplicables = computed(() => (d.value?.convocatoria.requisitos ?? []).filter((r) => r.tipo === 'anexo' && (d.value.requisitos_aplicables ?? []).includes(r.codigo)))
const faltantes = computed(() => anexosAplicables.value.filter((r) => r.obligatorio && !d.value?.borrador?.anexos?.[r.codigo]))
const resumen = computed(() => Object.fromEntries(Object.entries(d.value?.borrador?.datos ?? {}).filter(([, v]) => v !== null && v !== '').map(([k, v]) => [k, v])))

function aceptar(r) {
  const t = r.tipos_permitidos
  if (!t?.length) return r.codigo === 'fotografia' ? 'image/jpeg' : '.pdf,.jpg,.jpeg,.png'
  return t.map((x) => (x.includes('/') ? x : `.${x}`)).join(',')
}

async function cargar() {
  cargando.value = true
  error.value = ''
  errorGlobal.value = ''
  recargable.value = false
  try {
    d.value = (await api.get('/academico/mi-borrador-ingreso', { convocatoria: id })).data
    Object.keys(datos).forEach((k) => delete datos[k])
    Object.assign(datos, d.value.borrador?.datos ?? {})
    const o = d.value.convocatoria.ofertas.findIndex((x) => x.id_carrera === datos.id_carrera && x.id_sede === datos.id_sede && x.id_facultad === datos.id_facultad && x.modalidad_estudio === datos.modalidad_estudio && x.modalidad_ingreso === datos.modalidad_ingreso)
    ofertaSel.value = o >= 0 ? o : null
    errores.value = {}
  } catch (e) {
    if (e?.status === 403 && !vinculado && /permis/i.test(e.message ?? '')) {
      vinculado = true
      try {
        await api.post('/academico/mi-perfil-aspirante')
        return await cargar()
      } catch (e2) {
        error.value = `${mensajeError(e2)} Cierra sesión y vuelve a iniciar para actualizar tus permisos.`
      }
    } else if (e?.status === 403) {
      error.value = `${mensajeError(e)} Si acabas de crear tu perfil de aspirante, cierra sesión y vuelve a iniciar.`
    } else {
      error.value = mensajeError(e)
    }
  } finally {
    cargando.value = false
  }
}

function manejarError(e) {
  errores.value = e?.details ?? {}
  if (e?.code === 'VERSION_CONFLICTO') {
    errorGlobal.value = 'Tu borrador cambió en otra sesión. Recarga para ver la versión más reciente.'
    recargable.value = true
  } else if (e?.code === 'CONVOCATORIA_ACTUALIZADA') {
    errorGlobal.value = 'La convocatoria fue actualizada. Recarga para ver los requisitos vigentes.'
    recargable.value = true
  } else {
    errorGlobal.value = mensajeError(e)
  }
}

async function guardarPaso(n, campos) {
  ocupado.value = true
  errorGlobal.value = ''
  errores.value = {}
  if (n === 3 && ofertaSel.value !== null) {
    const o = d.value.convocatoria.ofertas[ofertaSel.value]
    Object.assign(datos, { id_carrera: o.id_carrera, id_sede: o.id_sede, id_facultad: o.id_facultad, modalidad_estudio: o.modalidad_estudio, modalidad_ingreso: o.modalidad_ingreso })
  }
  const parte = {}
  for (const c of campos) if (datos[c] !== undefined && datos[c] !== '') parte[c] = datos[c]
  try {
    await api.patch('/academico/mi-borrador-ingreso', { convocatoria: Number(id), version: d.value.borrador?.version ?? 0, etapa: n, datos: parte })
    const p = n + 1
    await cargar()
    paso.value = p
  } catch (e) {
    manejarError(e)
    if (e?.code === 'VERSION_CONFLICTO') await cargar()
  } finally {
    ocupado.value = false
  }
}

async function subir(r, ev) {
  const archivo = ev.target.files[0]
  if (!archivo) return
  subiendo.value = r.codigo
  errArchivo.value = r.codigo
  errores.value = {}
  errorGlobal.value = ''
  const fd = new FormData()
  fd.append('convocatoria', id)
  fd.append('codigo_requisito', r.codigo)
  fd.append('version', d.value.borrador?.version ?? 0)
  fd.append('archivo', archivo)
  try {
    await http.post('/academico/mi-borrador-ingreso/anexos', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    const p = paso.value
    await cargar()
    paso.value = p
  } catch (e) {
    manejarError(e)
    ev.target.value = ''
  } finally {
    subiendo.value = ''
  }
}

async function enviar() {
  ocupado.value = true
  errorEnvio.value = ''
  try {
    await postIdempotente(`/academico/convocatorias/${id}/mis-solicitudes`, { version_requisitos: d.value.convocatoria.version_requisitos }, claveEnvio)
    router.push('/admision/mis-solicitudes')
  } catch (e) {
    claveEnvio = nuevaClave()
    if (e?.code === 'CONVOCATORIA_ACTUALIZADA') {
      errorEnvio.value = 'La convocatoria fue actualizada. Recarga y revisa los requisitos.'
      await cargar()
      paso.value = 5
    } else {
      errorEnvio.value = mensajeError(e)
    }
  } finally {
    ocupado.value = false
  }
}

onMounted(() => {
  try {
    const t = localStorage.getItem('access_token')
    if (t) emailSesion.value = JSON.parse(atob(t.split('.')[1].replace(/-/g, '+').replace(/_/g, '/'))).email ?? ''
  } catch {
    emailSesion.value = ''
  }
  if (sesion.value) cargar()
  else cargando.value = false
})
</script>
