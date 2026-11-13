<template>
  <div>
    <Encabezado contexto="Portal del estudiante" titulo="Asistente académico" descripcion="Consulta tu avance, tu carrera y recibe propuestas de inscripción. La decisión final es tuya y se valida en Inscripción.">
      <SelectorExpediente />
    </Encabezado>

    <p v-if="estado === 'verificando'" role="status" class="py-10 text-center text-secondary"><i class="pi pi-spin pi-spinner mr-2" aria-hidden="true"></i>Preparando el asistente…</p>
    <AsistenteNoDisponible v-else-if="estado === 'no_disponible'" :mensaje="textoNoDisponible">
      <RouterLink :to="enlace('/estudiante/avance')" class="font-medium underline">Ver mi avance</RouterLink>
      <RouterLink :to="enlace('/estudiante/inscripcion')" class="font-medium underline">Ir a inscripción</RouterLink>
    </AsistenteNoDisponible>
    <div v-else class="grid min-w-0 grid-cols-1 gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
      <aside aria-labelledby="t-conv" class="min-w-0 rounded-xl border border-secondary-100 bg-surface-card p-3">
        <div class="mb-2 flex items-center justify-between gap-2">
          <h2 id="t-conv" class="font-semibold">Conversaciones</h2>
          <Button size="small" label="Nueva conversación" outlined @click="nueva" />
        </div>
        <p v-if="errorLista" role="alert" class="text-sm text-danger">{{ errorLista }}</p>
        <p v-else-if="!conversaciones.length" class="text-sm text-secondary">Aún no tienes conversaciones.</p>
        <ul v-else class="space-y-1">
          <li v-for="c in conversaciones" :key="c.id_conversacion">
            <button type="button" class="w-full rounded-lg px-2 py-1.5 text-left text-sm hover:bg-secondary-100/40" :class="{ 'bg-secondary-100/60 font-medium': c.id_conversacion === idConversacion }" :aria-current="c.id_conversacion === idConversacion ? 'true' : undefined" @click="abrir(c.id_conversacion)">
              <span class="block truncate">{{ c.titulo || 'Conversación sin título' }}</span>
              <span class="block text-xs text-secondary">{{ fecha(c.updated_at) }}</span>
            </button>
          </li>
        </ul>
      </aside>

      <section aria-labelledby="t-chat" class="flex min-h-[28rem] min-w-0 flex-col overflow-hidden rounded-xl border border-secondary-100 bg-surface-card">
        <h2 id="t-chat" class="sr-only">Mensajes</h2>
        <div ref="contenedor" class="flex-1 space-y-5 overflow-y-auto overflow-x-hidden bg-surface p-3 sm:p-4" style="max-height: 62vh">
          <p v-if="cargandoMensajes" role="status" class="text-secondary">Cargando mensajes…</p>
          <p v-else-if="errorMensajes" role="alert" class="text-danger">{{ errorMensajes }}</p>
          <div v-else-if="!mensajes.length" class="flex flex-col items-center gap-3 py-8 text-center">
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brand text-brand-contrast" aria-hidden="true"><i class="pi pi-sparkles"></i></span>
            <p class="font-medium">¿En qué te ayudo hoy?</p>
            <p class="max-w-md text-sm text-secondary">Respondo con los datos de tu expediente y de tu carrera, y te muestro de dónde sale cada dato.</p>
            <div class="mt-1 flex flex-wrap justify-center gap-2">
              <button v-for="s in sugerencias" :key="s" type="button" class="rounded-full border border-secondary-100 bg-surface-card px-3 py-1.5 text-sm hover:bg-brand-hover disabled:opacity-50" :disabled="!store.idExpediente" @click="usarSugerencia(s)">{{ s }}</button>
            </div>
          </div>
          <template v-for="m in mensajes" :key="m.clave">
            <MensajeUsuario v-if="esUsuario(m)" :contenido="m.contenido" />
            <MensajeAsistente v-else :mensaje="m" />
          </template>
          <EscribiendoAsistente v-if="enviando" />
        </div>

        <div class="border-t border-secondary-100 p-3" aria-live="polite">
          <p v-if="avisoError" role="alert" class="mb-2 rounded-lg border border-danger/30 bg-danger/10 p-2 text-sm text-danger">
            {{ avisoError }}
            <Button v-if="pendiente" class="ml-2" size="small" label="Reintentar" outlined @click="enviar(true)" />
          </p>
          <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="enviar(false)">
            <label for="mensaje" class="sr-only">Mensaje para el asistente</label>
            <Textarea id="mensaje" v-model="texto" rows="2" autoResize class="flex-1" maxlength="2000" placeholder="Escribe tu pregunta" :disabled="enviando || !store.idExpediente" @keydown.enter.exact.prevent="enviar(false)" />
            <Button type="submit" label="Enviar" icon="pi pi-send" :loading="enviando" :disabled="!texto.trim() || !store.idExpediente" />
          </form>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Button, Textarea } from 'primevue'
import Encabezado from '../components/Encabezado.vue'
import AsistenteNoDisponible from '../components/AsistenteNoDisponible.vue'
import MensajeAsistente from '../components/MensajeAsistente.vue'
import MensajeUsuario from '../components/MensajeUsuario.vue'
import EscribiendoAsistente from '../components/EscribiendoAsistente.vue'
import SelectorExpediente from '../components/SelectorExpediente.vue'
import { useExpedienteSeleccionado } from '../stores/expedienteSeleccionado'
import { api, fecha, mensajeError, nuevaClave, enviarTurnoIA } from '../services/academico'

const route = useRoute()
const store = useExpedienteSeleccionado()
const contenedor = ref()
const conversaciones = ref([])
const errorLista = ref('')
const idConversacion = ref(null)
const mensajes = ref([])
const cargandoMensajes = ref(false)
const errorMensajes = ref('')
const texto = ref('')
const enviando = ref(false)
// verificando | disponible | no_disponible. Se consulta una sola vez al entrar a la opción de menú.
const estado = ref('verificando')
const agotado = ref(false)
const avisoError = ref('')
const pendiente = ref(null) // { mensaje, clave } para reintentos del mismo mensaje
let contadorLocal = 0
// Un turno puede encadenar varias inferencias del proveedor (timeout de 60 s cada una en servidor).
const TIEMPO_RESPUESTA_IA = 150_000

const textoNoDisponible = computed(() => (agotado.value
  ? 'El asistente alcanzó su límite de uso por ahora. Mientras tanto, puedes revisar tu avance e inscribir materias desde el portal.'
  : 'Estamos trabajando para habilitarlo de nuevo. Mientras tanto, puedes revisar tu avance e inscribir materias desde el portal.'))
const enlace = (ruta) => (store.idExpediente ? { path: ruta, query: { expediente: store.idExpediente } } : ruta)
const esUsuario = (m) => m.rol === 'user' || m.rol === 'usuario'
const sugerencias = ['¿Qué materias puedo inscribir este ciclo?', '¿Cómo va mi avance?', '¿De qué trata mi carrera y cuál es mi pensum?', '¿Cuándo es el periodo de inscripción?']
function usarSugerencia(s) {
  texto.value = s
  enviar(false)
}
const clavear = (lista) => lista.map((m) => ({ ...m, clave: `s${m.secuencia}` }))

/** Consulta única de disponibilidad al abrir la vista (sin sondeo periódico). */
async function comprobarDisponibilidad() {
  try {
    const d = (await api.get('/academico/ia/disponibilidad')).data
    agotado.value = d?.motivo === 'PRESUPUESTO_AGOTADO'
    estado.value = d?.disponible ? 'disponible' : 'no_disponible'
  } catch {
    estado.value = 'no_disponible'
  }
}
function bajar() {
  nextTick(() => { if (contenedor.value) contenedor.value.scrollTop = contenedor.value.scrollHeight })
}

async function cargarLista() {
  if (!store.idExpediente) { conversaciones.value = []; return }
  errorLista.value = ''
  try {
    conversaciones.value = (await api.get('/academico/mi-asistente/conversaciones', { expediente: store.idExpediente })).data ?? []
  } catch (e) {
    errorLista.value = mensajeError(e)
  }
}

async function abrir(id) {
  idConversacion.value = id
  mensajes.value = []
  avisoError.value = ''
  pendiente.value = null
  cargandoMensajes.value = true
  errorMensajes.value = ''
  try {
    mensajes.value = clavear((await api.get(`/academico/mi-asistente/conversaciones/${id}/mensajes`, { expediente: store.idExpediente, per_page: 100 })).data ?? [])
    bajar()
  } catch (e) {
    errorMensajes.value = mensajeError(e)
  } finally {
    cargandoMensajes.value = false
  }
}

function nueva() {
  idConversacion.value = null
  mensajes.value = []
  errorMensajes.value = ''
  avisoError.value = ''
  pendiente.value = null
  texto.value = ''
}

async function enviar(reintento) {
  const intento = reintento && pendiente.value ? pendiente.value : { mensaje: texto.value.trim(), clave: nuevaClave() }
  if (!intento.mensaje || enviando.value) return
  enviando.value = true
  avisoError.value = ''
  pendiente.value = null
  if (!reintento) {
    mensajes.value.push({ clave: `l${++contadorLocal}`, rol: 'user', contenido: intento.mensaje })
    texto.value = ''
    bajar()
  }
  try {
    const body = { mensaje: intento.mensaje, id_expediente: store.idExpediente }
    if (idConversacion.value) body.id_conversacion = idConversacion.value
    const r = await enviarTurnoIA('/academico/mi-asistente/mensajes', body, intento.clave, { plazoMs: TIEMPO_RESPUESTA_IA })
    idConversacion.value = r.id_conversacion
    mensajes.value.push({ clave: `l${++contadorLocal}`, rol: 'assistant', contenido: r.respuesta, created_at: new Date().toISOString(), referencias: { fuentes: r.fuentes, propuesta: r.propuesta, advertencias: r.advertencias } })
    bajar()
    cargarLista()
  } catch (e) {
    if (e?.code === 'IA_PRESUPUESTO_AGOTADO' || (e?.status === 409 && /presupuesto/i.test(e?.message ?? ''))) {
      agotado.value = true
      estado.value = 'no_disponible'
    } else if (e?.status === 429) {
      avisoError.value = 'Alcanzaste el límite de mensajes. Espera un momento antes de volver a intentar.'
      pendiente.value = intento
    } else if (e?.status === 504) {
      avisoError.value = 'El asistente está tardando más de lo normal. Pulsa Reintentar para recuperar la respuesta sin duplicar el mensaje.'
      pendiente.value = intento
    } else if (!e?.status) {
      avisoError.value = 'No hay conexión con el servidor. Puedes reintentar el mismo mensaje.'
      pendiente.value = intento
    } else {
      avisoError.value = mensajeError(e)
    }
  } finally {
    enviando.value = false
  }
}

watch(() => store.idExpediente, (n, o) => {
  if (o === null || n === o || estado.value !== 'disponible') return
  nueva()
  cargarLista()
})

onMounted(async () => {
  const perfil = store.perfil ? Promise.resolve() : store.cargarPerfil(route.query.expediente)
  await comprobarDisponibilidad()
  try {
    await perfil
  } catch (e) {
    errorLista.value = mensajeError(e)
  }
  if (estado.value === 'disponible') cargarLista()
})
</script>
