<template>
  <div>
    <header class="mb-10 border-b border-secondary-100 pb-8">
      <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-secondary">Identidad de la plataforma</p>
      <h1 class="text-3xl font-semibold">Personalización</h1>
      <p class="mt-3 max-w-2xl text-secondary">Define la marca de tu proyecto. Los campos vacíos quedan sin personalizar; no se aplica ningún preset.</p>
    </header>

    <p v-if="error" role="alert" class="mb-6 rounded-lg border border-danger p-4 text-danger">{{ error }}</p>
    <p v-if="cargando" role="status">Cargando personalización…</p>
    <div v-else-if="disponible" class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
      <div class="space-y-8">
        <form class="rounded-xl border border-secondary-100 bg-surface-card p-6 md:p-8" @submit.prevent="guardar">
          <h2 class="mb-6 text-xl font-semibold">Nombre y textos</h2>
          <fieldset :disabled="!puedeEditar || ocupado" class="space-y-5">
            <div v-for="campo in campos" :key="campo.clave">
              <label :for="campo.clave" class="mb-2 block font-medium">{{ campo.etiqueta }}</label>
              <InputText :id="campo.clave" v-model="form.textos[campo.clave]" :maxlength="campo.maximo" class="w-full" />
            </div>
            <div class="border-t border-secondary-100 pt-6">
              <label for="color-primario" class="mb-2 block font-medium">Color primario</label>
              <div class="flex items-center gap-3">
                <span class="h-10 w-10 shrink-0 rounded-lg border border-secondary" :style="{ backgroundColor: colorValido ? form.color_primario : 'transparent' }" aria-hidden="true"></span>
                <InputText id="color-primario" v-model="form.color_primario" maxlength="7" placeholder="#RRGGBB" aria-describedby="ayuda-color" class="min-w-0 flex-1" />
                <Button type="button" text label="Quitar" @click="form.color_primario = ''" />
              </div>
              <p id="ayuda-color" class="mt-2 text-sm text-secondary">Opcional. Debe permitir leer texto blanco con contraste de al menos 4.5:1.</p>
            </div>
            <Button v-if="puedeEditar" type="submit" label="Guardar textos y color" :loading="ocupado" />
          </fieldset>
        </form>

        <section class="rounded-xl border border-secondary-100 bg-surface-card p-6 md:p-8" aria-labelledby="imagenes-titulo">
          <h2 id="imagenes-titulo" class="text-xl font-semibold">Imágenes</h2>
          <p class="mb-6 mt-2 text-sm text-secondary">Las imágenes se aplican al subirlas. PNG, WebP o JPEG; hasta 8192 píxeles por lado.</p>
          <div class="grid gap-4 sm:grid-cols-2">
            <article v-for="imagen in imagenes" :key="imagen.slot" class="rounded-lg border border-secondary-100 p-4">
              <div class="mb-4 flex h-28 items-center justify-center overflow-hidden rounded-lg" :class="imagen.oscuro ? 'bg-black' : 'bg-white'">
                <img v-if="marca.asset(imagen.slot)" :src="marca.asset(imagen.slot)" :alt="imagen.etiqueta" class="max-h-full max-w-full object-contain" />
                <span v-else class="text-sm text-gray-500">Sin imagen</span>
              </div>
              <h3 class="font-semibold">{{ imagen.etiqueta }}</h3>
              <p class="mb-4 mt-1 text-sm text-secondary">Hasta {{ imagen.limite }}</p>
              <template v-if="puedeEditar">
                <label :for="`imagen-${imagen.slot}`" class="mb-2 block text-sm">Seleccionar imagen</label>
                <input :id="`imagen-${imagen.slot}`" type="file" accept="image/png,image/webp,image/jpeg" :disabled="ocupado" class="w-full text-sm file:mr-2 file:rounded file:border-0 file:bg-secondary-100 file:p-2 file:text-text" @change="subir(imagen, $event)" />
                <Button v-if="marca.asset(imagen.slot)" type="button" text severity="danger" label="Retirar imagen" :disabled="ocupado" class="mt-3" @click="quitar(imagen)" />
              </template>
            </article>
          </div>
        </section>
      </div>

      <aside class="lg:sticky lg:top-24 lg:self-start">
        <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-secondary">Vista previa de textos</p>
        <div class="overflow-hidden rounded-xl border border-secondary-100 bg-surface-card">
          <div class="border-b border-secondary-100 px-4 py-3 text-sm text-secondary">{{ form.textos.titulo_pestana || form.textos.nombre || 'Sin título' }}</div>
          <div class="space-y-4 p-6">
            <img v-if="marca.asset('logo')" :src="marca.asset('logo')" alt="Logotipo configurado" class="max-h-20 max-w-full object-contain" />
            <h2 class="break-words text-xl font-semibold">{{ form.textos.nombre || 'Sin nombre' }}</h2>
            <p class="break-words text-sm text-secondary">{{ form.textos.descripcion }}</p>
            <div class="h-1 w-12 rounded" :style="{ backgroundColor: colorValido ? form.color_primario : 'var(--color-secondary)' }"></div>
            <p class="break-words text-sm">{{ form.textos.titulo_inicio }}</p>
            <p class="break-words text-sm text-secondary">{{ form.textos.descripcion_inicio }}</p>
            <p class="break-words border-t border-secondary-100 pt-4 text-xs text-secondary">{{ form.textos.texto_pie }}</p>
          </div>
        </div>
        <p class="mt-4 text-sm text-secondary">{{ puedeEditar ? 'Guarda los textos y el color para aplicarlos en toda la plataforma.' : 'Tienes permiso de consulta. La edición requiere un permiso adicional.' }}</p>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Button, InputText } from 'primevue'
import http from '@/shared/services/http'
import { useAuthStore } from '@/shared/stores/auth'
import { usePersonalizacionStore } from '@/shared/stores/personalizacion'
import { esColorHex } from '@/shared/utils/colorPrimario'

const marca = usePersonalizacionStore()
const auth = useAuthStore()
const puedeEditar = computed(() => auth.permisos.some((p) => ['root', 'personalizacion_editar'].includes(p)))
const cargando = ref(true)
const disponible = ref(false)
const ocupado = ref(false)
const error = ref('')
const campos = [
  { clave: 'nombre', etiqueta: 'Nombre de la plataforma', maximo: 60 },
  { clave: 'titulo_pestana', etiqueta: 'Título de la pestaña', maximo: 80 },
  { clave: 'descripcion', etiqueta: 'Descripción de la plataforma', maximo: 200 },
  { clave: 'titulo_inicio', etiqueta: 'Título de bienvenida', maximo: 100 },
  { clave: 'descripcion_inicio', etiqueta: 'Descripción de bienvenida', maximo: 300 },
  { clave: 'texto_pie', etiqueta: 'Texto del pie de página', maximo: 100 }
]
const imagenes = [
  { slot: 'logo', etiqueta: 'Logotipo claro', limite: '1 MB', bytes: 1048576 },
  { slot: 'logo_oscuro', etiqueta: 'Logotipo oscuro', limite: '1 MB', bytes: 1048576, oscuro: true },
  { slot: 'logo_vertical', etiqueta: 'Logotipo de acceso', limite: '1 MB', bytes: 1048576 },
  { slot: 'favicon', etiqueta: 'Ícono de la pestaña', limite: '256 KB', bytes: 262144 },
  { slot: 'fondo_acceso', etiqueta: 'Fondo de acceso claro', limite: '6 MB', bytes: 6291456 },
  { slot: 'fondo_acceso_oscuro', etiqueta: 'Fondo de acceso oscuro', limite: '6 MB', bytes: 6291456, oscuro: true }
]
const form = reactive({ textos: Object.fromEntries(campos.map((campo) => [campo.clave, ''])), color_primario: '' })
const colorValido = computed(() => esColorHex(form.color_primario))

function informarError(e) {
  error.value = Object.values(e.details ?? {}).flat().join(' ') || e.message || 'No se pudo guardar la personalización'
}

async function ejecutar(operacion) {
  ocupado.value = true
  error.value = ''
  try {
    const { data } = await operacion()
    marca.actualizar(data.data)
  } catch (e) {
    informarError(e)
  } finally {
    ocupado.value = false
  }
}

function guardar() {
  return ejecutar(() => http.put('/personalizacion', {
    textos: Object.fromEntries(Object.entries(form.textos).map(([campo, valor]) => [campo, valor.trim() || null])),
    color_primario: form.color_primario.trim() || null
  }))
}

async function subir(imagen, event) {
  const archivo = event.target.files?.[0]
  event.target.value = ''
  if (!archivo) return
  if (archivo.size > imagen.bytes) {
    error.value = `La imagen supera el máximo de ${imagen.limite}`
    return
  }
  const datos = new FormData()
  datos.append('archivo', archivo)
  await ejecutar(() => http.post(`/personalizacion/assets/${imagen.slot}`, datos, { headers: { 'Content-Type': 'multipart/form-data' } }))
}

function quitar(imagen) {
  if (!window.confirm(`¿Retirar ${imagen.etiqueta.toLowerCase()}?`)) return
  return ejecutar(() => http.delete(`/personalizacion/assets/${imagen.slot}`))
}

onMounted(async () => {
  try {
    const { data } = await http.get('/personalizacion', { toastSuccess: false })
    marca.actualizar(data.data)
    for (const campo of campos) form.textos[campo.clave] = marca.texto(campo.clave)
    form.color_primario = marca.colorPrimario ?? ''
    disponible.value = true
  } catch (e) {
    informarError(e)
  } finally {
    cargando.value = false
  }
})
</script>
