<template>
  <div class="space-y-8 text-text">

    <!-- ── Dev Panel: selector de paleta y layout ── -->
    <section class="rounded-2xl border border-brand-50/40 p-6 shadow-sm" :style="surfaceCardStyle">
      <div class="flex items-center justify-between gap-4 mb-5">
        <div>
          <h2 class="text-h3">Configuración del proyecto</h2>
          <p class="text-secondary text-sm mt-1">
            Selecciona la paleta y el layout para este cliente/proyecto. Copia el snippet y pégalo en
            <code>theme.config.js</code> o en tu archivo <code>.env</code>.
          </p>
        </div>
        <Tag value="Dev panel" severity="warn" />
      </div>

      <!-- Paletas -->
      <p class="text-sm font-semibold uppercase tracking-wide opacity-70 mb-3">Paleta de colores</p>
      <div class="flex flex-wrap gap-3 mb-6">
        <button
          v-for="palette in allPalettes"
          :key="palette.id"
          type="button"
          class="flex items-center gap-3 px-4 py-3 rounded-xl border-2 transition-all duration-200 cursor-pointer"
          :style="paletteSwatchStyle(palette)"
          :class="themeStore.activePaletteId === palette.id ? 'ring-2 ring-offset-2' : 'opacity-70 hover:opacity-100'"
          @click="themeStore.applyPalette(palette.id)"
        >
          <span class="flex gap-1.5">
            <span class="w-4 h-4 rounded-full border border-white/30" :style="{ background: palette.preview.surface }" />
            <span class="w-4 h-4 rounded-full border border-white/30" :style="{ background: palette.preview.brand }" />
            <span class="w-4 h-4 rounded-full border border-white/30" :style="{ background: palette.preview.accent }" />
          </span>
          <span class="text-sm font-medium" :style="{ color: isLightColor(palette.preview.brand) ? '#1E293B' : '#FFFFFF' }">
            {{ palette.label }}
          </span>
          <Icon v-if="themeStore.activePaletteId === palette.id" name="check_circle" class="text-base" :style="{ color: palette.preview.accent }" />
        </button>
      </div>

      <!-- Layouts -->
      <p class="text-sm font-semibold uppercase tracking-wide opacity-70 mb-3">Layout</p>
      <div class="flex flex-wrap gap-3 mb-6">
        <button
          v-for="layout in allLayouts"
          :key="layout.id"
          type="button"
          class="flex flex-col items-start gap-2 px-4 py-3 rounded-xl border-2 transition-all duration-200 cursor-pointer min-w-40"
          :style="layoutBtnStyle(layout.id)"
          @click="themeStore.setLayout(layout.id)"
        >
          <div class="flex items-center gap-2">
            <Icon :name="layout.icon" class="text-lg" />
            <span class="text-sm font-semibold">{{ layout.label }}</span>
            <Icon v-if="themeStore.activeLayoutId === layout.id" name="check_circle" class="text-sm" />
          </div>
          <span class="text-xs opacity-70">{{ layout.description }}</span>
        </button>
      </div>

      <!-- Snippet -->
      <div class="rounded-xl p-4 border border-brand-50/20 font-mono text-xs leading-relaxed" :style="surfaceStyle">
        <p class="text-secondary mb-2 font-sans text-sm">Copia esto en <code>theme.config.js</code>:</p>
        <pre class="whitespace-pre-wrap">{{ configSnippet }}</pre>
        <p class="text-secondary mt-3 mb-1 font-sans text-sm">O en tu <code>.env</code>:</p>
        <pre class="whitespace-pre-wrap">{{ envSnippet }}</pre>
      </div>
    </section>
    <!-- ── fin Dev Panel ── -->

    <section
      class="rounded-2xl border border-brand-50/40 shadow-lg overflow-hidden"
      :style="surfaceCardStyle"
    >
      <div
        class="p-8 md:p-10 flex flex-col gap-8 md:flex-row md:items-center md:justify-between"
        :style="heroStyle"
      >
        <div class="space-y-4 max-w-2xl">
          <p class="text-sm uppercase tracking-wide opacity-80">Paleta activa</p>
          <h1 class="text-h1 leading-tight">Previsualización del tema</h1>
          <p class="text-secondary">
            Referencia rápida de los tokens declarados en <code>theme.css</code>. Usa esta vista
            para validar contrastes y estados antes de publicar cambios de color.
          </p>
          <div class="flex flex-wrap gap-3 pt-1">
            <Button label="Primario" class="font-semibold px-5 py-3" />
            <Button
              label="Secundario"
              outlined
              severity="secondary"
              class="border-brand-contrast! text-brand-contrast!"
            />
            <Button label="Acción de texto" text severity="secondary" class="text-brand-contrast!" />
          </div>
        </div>

        <div class="w-full md:w-80">
          <div class="rounded-xl shadow-md border border-brand-50/30" :style="miniCardStyle">
            <div
              class="flex items-center justify-between px-4 py-3 rounded-lg"
              :style="miniNavStyle"
            >
              <span class="font-semibold">NavBar</span>
              <span class="text-xs opacity-80">bg brand</span>
            </div>
            <div class="grid grid-cols-2 gap-3 mt-4">
              <div
                class="rounded-lg border shadow-sm p-3"
                :style="{ ...surfaceStyle, borderColor: valueOf('--color-brand-hover') }"
              >
                <p class="text-xs text-secondary">Surface</p>
                <p class="font-semibold mt-1">{{ valueOf('--color-surface') }}</p>
              </div>
              <div
                class="rounded-lg border shadow-sm p-3"
                :style="{ ...brandTileStyle, borderColor: valueOf('--color-brand-hover') }"
              >
                <p class="text-xs text-secondary">Acento</p>
                <p class="font-semibold mt-1">{{ valueOf('--color-brand-200') }}</p>
              </div>
              <div
                class="rounded-lg border shadow-sm p-3 col-span-2"
                :style="{ ...inputTileStyle, borderColor: valueOf('--color-brand-hover') }"
              >
                <p class="text-xs text-secondary">Inputs</p>
                <p class="font-semibold mt-1 truncate">{{ valueOf('--color-input') }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="grid lg:grid-cols-2 gap-6">
      <article
        class="rounded-2xl border border-brand-50/40 p-6 shadow-sm"
        :style="surfaceCardStyle"
      >
        <div class="flex items-center justify-between gap-4">
          <div>
            <h3 class="text-h3">Paleta base</h3>
            <p class="text-secondary text-sm">Fondos principales, texto y estados globales.</p>
          </div>
          <Tag value="theme.css" />
        </div>

        <div class="grid md:grid-cols-3 gap-4 mt-6">
          <div
            v-for="token in basePalette"
            :key="token.token"
            class="palette-card"
            :style="paletteStyle(token)"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs uppercase text-secondary">{{ token.label }}</p>
                <p class="font-semibold leading-tight mt-1">{{ valueOf(token.token) }}</p>
              </div>
              <Tag :value="token.token.replace('--color-', '')" severity="secondary" />
            </div>
            <p class="text-xs mt-3 opacity-80">{{ token.usage }}</p>
          </div>
        </div>
      </article>

      <article class="rounded-2xl border border-brand-50/40 p-6 shadow-sm" :style="surfaceCardStyle">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h3 class="text-h3">Escala de marca</h3>
            <p class="text-secondary text-sm">Estados hover/active y contrastes de la marca.</p>
          </div>
          <Tag value="brand" severity="success" />
        </div>

        <div
          class="rounded-xl mt-4 p-4 border border-brand-50/30 shadow-inner"
          :style="surfaceStyle"
        >
          <div
            class="h-2 w-full rounded-full"
            :style="{
              background: `linear-gradient(90deg, ${valueOf('--color-brand-50')}, ${valueOf('--color-brand-100')}, ${valueOf('--color-brand-200')})`
            }"
          ></div>
          <div class="grid sm:grid-cols-2 gap-3 mt-4">
            <div
              v-for="token in brandPalette"
              :key="token.token"
              class="palette-card"
              :style="paletteStyle(token)"
            >
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-xs uppercase text-secondary">{{ token.label }}</p>
                  <p class="font-semibold leading-tight mt-1">{{ valueOf(token.token) }}</p>
                </div>
                <Tag :value="token.token.replace('--color-', '')" severity="success" />
              </div>
              <p class="text-xs mt-3 opacity-80">{{ token.usage }}</p>
            </div>
          </div>
        </div>
      </article>
    </section>

    <section class="rounded-2xl border border-brand-50/40 p-6 shadow-sm" :style="surfaceCardStyle">
      <h3 class="text-h3">Botones</h3>
      <p class="text-secondary text-sm">
        Cada severidad de PrimeVue resuelta con los tokens de esta paleta, en sus tres variantes.
      </p>

      <div class="mt-5 overflow-x-auto">
        <table class="w-full border-collapse">
          <thead>
            <tr>
              <th class="text-left text-xs uppercase text-secondary pb-2 pr-4">Severidad</th>
              <th class="text-left text-xs uppercase text-secondary pb-2 pr-4">Filled</th>
              <th class="text-left text-xs uppercase text-secondary pb-2 pr-4">Outlined</th>
              <th class="text-left text-xs uppercase text-secondary pb-2">Text</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="sev in buttonSeverities" :key="sev.value" class="border-t border-secondary-100">
              <td class="py-3 pr-4 text-sm text-secondary whitespace-nowrap">{{ sev.label }}</td>
              <td class="py-3 pr-4">
                <Button :label="sev.label" :severity="sev.value" size="small" />
              </td>
              <td class="py-3 pr-4">
                <Button :label="sev.label" :severity="sev.value" variant="outlined" size="small" />
              </td>
              <td class="py-3">
                <Button :label="sev.label" :severity="sev.value" variant="text" size="small" />
              </td>
            </tr>
            <tr class="border-t border-secondary-100">
              <td class="py-3 pr-4 text-sm text-secondary whitespace-nowrap">Link</td>
              <td class="py-3 pr-4 text-xs text-secondary" colspan="3">
                <Button label="Enlace" link size="small" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="grid lg:grid-cols-[1.1fr_0.9fr] gap-6">
      <article class="rounded-2xl border border-brand-50/40 p-6 shadow-sm" :style="surfaceCardStyle">
        <h3 class="text-h3">Componentes con el tema</h3>
        <p class="text-secondary text-sm">
          Ejemplos basados en LoginView y SideBar para validar legibilidad.
        </p>

        <div class="grid md:grid-cols-2 gap-5 mt-5">
          <div class="rounded-xl p-4 border border-brand-50/30 shadow-sm" :style="surfaceStyle">
            <p class="text-sm text-secondary mb-3">Formulario</p>
            <div class="flex flex-col gap-3">
              <InputText
                placeholder="Usuario o correo"
                class="w-full"
                :pt="{
                  root: {
                    style: {
                      background: cssVar('--color-input'),
                      color: valueOf('--color-text')
                    }
                  }
                }"
              />
              <InputText
                placeholder="Password"
                type="password"
                class="w-full"
                :pt="{
                  root: {
                    style: {
                      background: cssVar('--color-input'),
                      color: valueOf('--color-text')
                    }
                  }
                }"
              />
              <Button label="Acceder" class="w-full font-semibold py-3" />
              <RouterLink class="text-sm text-link hover:underline" to="/">Ver políticas</RouterLink>
            </div>
          </div>

          <div class="rounded-xl p-4 border border-brand-50/30 shadow-sm" :style="surfaceStyle">
            <p class="text-sm text-secondary mb-3">Drawer / menú</p>
            <div class="flex items-center justify-between p-3 rounded-lg" :style="miniNavStyle">
              <span class="font-semibold">SideBar</span>
              <Icon name="menu" />
            </div>
            <ul class="mt-3 space-y-2">
              <li
                v-for="token in ['dashboard.index', 'reportes', 'configuracion']"
                :key="token"
                class="flex items-center justify-between px-3 py-2 rounded-lg hover:opacity-90 transition"
                :style="{
                  background: cssVar('--color-brand-secondary'),
                  color: valueOf('--color-text')
                }"
              >
                <span class="capitalize">{{ token }}</span>
                <Icon name="chevron_right" />
              </li>
            </ul>
          </div>
        </div>
      </article>

      <article class="rounded-2xl border border-brand-50/40 p-6 shadow-sm" :style="surfaceCardStyle">
        <h3 class="text-h3">Estados y rellenos</h3>
        <p class="text-secondary text-sm">Tokens auxiliares para inputs y toggles.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
          <div
            v-for="token in uiPalette"
            :key="token.token"
            class="palette-card"
            :style="paletteStyle(token)"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs uppercase text-secondary">{{ token.label }}</p>
                <p class="font-semibold leading-tight mt-1">{{ valueOf(token.token) }}</p>
              </div>
              <Tag :value="token.token.replace('--color-', '')" severity="secondary" />
            </div>
            <p class="text-xs mt-3 opacity-80">{{ token.usage }}</p>
          </div>
        </div>

        <div class="rounded-xl p-4 border border-brand-50/30 shadow-sm mt-4" :style="surfaceStyle">
          <div class="flex items-center gap-3">
            <ToggleSwitch v-model="toggleValue" />
            <span class="text-sm">
              Toggle usa {{ valueOf('--color-toggle-bg') }} y handle {{ valueOf('--color-toggle-handle') }}
            </span>
          </div>
          <div class="flex items-center gap-3 mt-4">
            <div class="spinner" />
            <span class="text-sm">Color de carga: {{ valueOf('--color-loading-spinner') }}</span>
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 mt-4">
          <div
            v-for="token in autofillPalette"
            :key="token.token"
            class="palette-card"
            :style="paletteStyle(token)"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs uppercase text-secondary">{{ token.label }}</p>
                <p class="font-semibold leading-tight mt-1">{{ valueOf(token.token) }}</p>
              </div>
              <Tag :value="token.token.replace('--color-', '')" severity="secondary" />
            </div>
            <p class="text-xs mt-3 opacity-80">{{ token.usage }}</p>
          </div>
        </div>
      </article>
    </section>

    <section class="rounded-2xl border border-brand-50/40 p-6 shadow-sm" :style="surfaceCardStyle">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.16em] text-brand-200">
            <Icon name="upload_file" :font-size="18" />
            <span>VueUse</span>
          </div>
          <h3 class="text-h3 mt-2">Carga local de archivos</h3>
          <p class="text-secondary text-sm mt-1 max-w-2xl">
            Ejemplo del componente reutilizable con selección, arrastre, validación y vista previa.
            Todo sucede en el navegador: esta demostración no llama al backend.
          </p>
        </div>
        <Tag value="Sin backend" severity="info" />
      </div>

      <div class="grid lg:grid-cols-[minmax(0,1.2fr)_minmax(16rem,0.8fr)] gap-6 mt-6">
        <FileUploader
          v-model="previewFiles"
          data-testid="theme-preview-file-uploader"
          accept="image/jpeg,image/png,.pdf,.mp4"
          :multiple="true"
          :max-files="4"
          :max-size="50 * 1024 * 1024"
          title="Choose a file or drag & drop it here."
          drop-title="Drop files here."
          description="JPEG, PNG, PDF, and MP4 formats, up to 50 MB."
          action-label="Browse File"
        />

        <aside class="rounded-xl border border-brand-50/30 p-4" :style="surfaceStyle">
          <div class="flex items-center justify-between gap-3">
            <span class="text-xs font-bold uppercase tracking-wide text-secondary">Salida del componente</span>
            <span class="text-xs font-semibold" :class="previewFiles.length ? 'text-success' : 'text-secondary'">
              {{ previewFiles.length ? 'v-model actualizado' : 'Esperando selección' }}
            </span>
          </div>

          <p class="text-sm mt-4">
            El padre recibe un arreglo de objetos <code>File</code> listo para conectarlo después a un
            <code>FormData</code>.
          </p>

          <dl class="grid grid-cols-2 gap-3 mt-5">
            <div class="rounded-lg p-3" :style="{ background: cssVar('--color-brand-secondary') }">
              <dt class="text-xs text-secondary">Archivos</dt>
              <dd class="text-h4 mt-1">{{ previewFiles.length }}</dd>
            </div>
            <div class="rounded-lg p-3" :style="{ background: cssVar('--color-brand-secondary') }">
              <dt class="text-xs text-secondary">Peso total</dt>
              <dd class="text-h4 mt-1">{{ formatPreviewBytes(totalPreviewBytes) }}</dd>
            </div>
          </dl>

          <ul v-if="previewFiles.length" class="mt-4 space-y-2">
            <li v-for="file in previewFiles" :key="`${file.name}-${file.lastModified}`" class="flex items-center gap-2 text-sm">
              <Icon name="check_circle" class="text-success" :font-size="18" />
              <span class="truncate" :title="file.name">{{ file.name }}</span>
            </li>
          </ul>
          <p v-else class="text-secondary text-xs mt-4">Selecciona un archivo para ver el valor emitido.</p>
        </aside>
      </div>
    </section>
  </div>
</template>

<script setup>
import { RouterLink } from 'vue-router'
import { Button, InputText, Tag, ToggleSwitch } from 'primevue'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useThemeStore } from '@/shared/stores/theme'
import { palettes as allPalettes } from '@/shared/config/palettes'
import { layouts as allLayouts } from '@/shared/config/layouts'
import Icon from '@/shared/components/Icon.vue'
import FileUploader from '@/shared/components/FileUploader.vue'

const themeStore = useThemeStore()
const previewFiles = ref([])
const totalPreviewBytes = computed(() => previewFiles.value.reduce((total, file) => total + file.size, 0))

const buttonSeverities = [
  { value: null, label: 'Primary' },
  { value: 'secondary', label: 'Secondary' },
  { value: 'success', label: 'Success' },
  { value: 'info', label: 'Info' },
  { value: 'warn', label: 'Warn' },
  { value: 'help', label: 'Help' },
  { value: 'danger', label: 'Danger' },
  { value: 'contrast', label: 'Contrast' }
]

const tokenFallbacks = {
  '--color-surface': '#FAFAFA',
  '--color-surface-contrast': '#26272B',
  '--color-surface-card': '#FFFFFF',
  '--color-text': '#26272B',
  '--color-link': '#2B3AA8',
  '--color-danger': '#C22222',
  '--color-secondary': '#5B5D63',
  '--color-secondary-100': '#EEF0F8',
  '--color-brand-contrast': '#FFFFFF',
  '--color-loading-spinner': '#20265A',
  '--color-input': 'rgba(38, 39, 43, 0.06)',
  '--color-brand': '#20265A',
  '--color-brand-50': '#E3E6F6',
  '--color-brand-100': '#B7C0EC',
  '--color-brand-200': '#4C58A8',
  '--color-brand-active': '#141733',
  '--color-brand-hover': '#EEF1FA',
  '--color-brand-secondary': '#F3F4FA',
  '--color-autofill-bg': '#FFFFFF',
  '--color-autofill-fg': '#26272B',
  '--color-autofill-dark-bg': '#EEF1FA',
  '--color-autofill-dark-fg': '#26272B',
  '--color-toggle-bg': '#C7CCEC',
  '--color-toggle-handle': '#20265A'
}

const basePalette = [
  {
    label: 'Surface',
    token: '--color-surface',
    textToken: '--color-text',
    usage: 'Fondo principal de la app.'
  },
  {
    label: 'Surface card',
    token: '--color-surface-card',
    textToken: '--color-text',
    usage: 'Tarjetas y contenedores.'
  },
  { label: 'Texto', token: '--color-text', textToken: '--color-surface', usage: 'Texto base.' },
  { label: 'Link', token: '--color-link', textToken: '--color-surface', usage: 'Enlaces y links.' },
  { label: 'Danger', token: '--color-danger', textToken: '--color-surface', usage: 'Mensajes de error.' },
  {
    label: 'Secondary',
    token: '--color-secondary',
    textToken: '--color-brand',
    usage: 'Controles secundarios claros.'
  },
  {
    label: 'Secondary 100',
    token: '--color-secondary-100',
    textToken: '--color-text',
    usage: 'Fondos oscuros alternos.'
  }
]

const brandPalette = [
  { label: 'Brand', token: '--color-brand', textToken: '--color-brand-contrast', usage: 'Base de marca.' },
  { label: 'Brand 50', token: '--color-brand-50', textToken: '--color-brand-contrast', usage: 'Hover inicial.' },
  { label: 'Brand 100', token: '--color-brand-100', textToken: '--color-brand-contrast', usage: 'Acciones primarias.' },
  { label: 'Brand 200', token: '--color-brand-200', textToken: '--color-brand-contrast', usage: 'Estados destacados.' },
  { label: 'Active', token: '--color-brand-active', textToken: '--color-brand-contrast', usage: 'Estado activo.' },
  { label: 'Hover', token: '--color-brand-hover', textToken: '--color-brand-contrast', usage: 'Hover profundo.' },
  { label: 'Secundario', token: '--color-brand-secondary', textToken: '--color-text', usage: 'Fondos de barra lateral.' },
  { label: 'Contraste', token: '--color-brand-contrast', textToken: '--color-brand', usage: 'Texto sobre brand.' }
]

const uiPalette = [
  { label: 'Inputs', token: '--color-input', textToken: '--color-text', usage: 'Campos y selects.' },
  {
    label: 'Spinner',
    token: '--color-loading-spinner',
    textToken: '--color-brand-contrast',
    usage: 'Indicador de carga.'
  },
  { label: 'Toggle bg', token: '--color-toggle-bg', textToken: '--color-brand', usage: 'Fondo del switch.' },
  {
    label: 'Toggle handle',
    token: '--color-toggle-handle',
    textToken: '--color-brand-contrast',
    usage: 'Punto de control.'
  }
]

const autofillPalette = [
  {
    label: 'Autofill claro',
    token: '--color-autofill-bg',
    textToken: '--color-autofill-fg',
    usage: 'Relleno de navegador claro.'
  },
  {
    label: 'Autofill oscuro',
    token: '--color-autofill-dark-bg',
    textToken: '--color-autofill-dark-fg',
    usage: 'Relleno de navegador oscuro.'
  }
]

const resolvedTokens = reactive({ ...tokenFallbacks })
const toggleValue = ref(true)

function refreshTokens() {
  const styles = getComputedStyle(document.documentElement)
  Object.keys(tokenFallbacks).forEach((token) => {
    const value = styles.getPropertyValue(token)
    if (value) resolvedTokens[token] = value.trim()
  })
}

onMounted(refreshTokens)

watch(() => themeStore.activePaletteId, () => {
  // nextTick para que las CSS vars estén aplicadas antes de leer
  setTimeout(refreshTokens, 0)
})

const valueOf = (token) => resolvedTokens[token] || tokenFallbacks[token] || token
const cssVar = (token) => `var(${token}, ${tokenFallbacks[token] || '#000'})`

const surfaceCardStyle = computed(() => ({
  background: cssVar('--color-surface-card'),
  color: cssVar('--color-text'),
  borderColor: cssVar('--color-brand-secondary')
}))

const surfaceStyle = computed(() => ({
  background: cssVar('--color-surface'),
  color: cssVar('--color-text')
}))

const heroStyle = computed(() => ({
  background: `linear-gradient(135deg, ${valueOf('--color-brand-hover')}, ${valueOf('--color-brand-200')})`,
  color: valueOf('--color-brand-contrast')
}))

const miniCardStyle = computed(() => ({
  background: cssVar('--color-surface'),
  color: valueOf('--color-text')
}))

const miniNavStyle = computed(() => ({
  background: cssVar('--color-brand'),
  color: valueOf('--color-brand-contrast')
}))

const brandTileStyle = computed(() => ({
  background: cssVar('--color-brand-200'),
  color: valueOf('--color-brand-contrast')
}))

const inputTileStyle = computed(() => ({
  background: cssVar('--color-input'),
  color: valueOf('--color-text')
}))

const paletteStyle = (token) => ({
  background: cssVar(token.token),
  color: valueOf(token.textToken || '--color-text'),
  borderColor: valueOf('--color-brand-hover')
})

// ── Dev panel helpers ──
function isLightColor(hex) {
  const c = hex.replace('#', '')
  const r = parseInt(c.slice(0, 2), 16)
  const g = parseInt(c.slice(2, 4), 16)
  const b = parseInt(c.slice(4, 6), 16)
  return (r * 299 + g * 587 + b * 114) / 1000 > 180
}

const paletteSwatchStyle = (palette) => ({
  background: palette.preview.brand,
  borderColor: themeStore.activePaletteId === palette.id ? palette.preview.accent : 'transparent'
})

const layoutBtnStyle = (id) => ({
  background: cssVar('--color-surface'),
  color: cssVar('--color-text'),
  borderColor: themeStore.activeLayoutId === id ? cssVar('--color-brand-200') : cssVar('--color-secondary-100')
})

const configSnippet = computed(() =>
  `export const ACTIVE_PALETTE = '${themeStore.activePaletteId}'\nexport const ACTIVE_LAYOUT  = '${themeStore.activeLayoutId}'`
)

const envSnippet = computed(() =>
  `VITE_THEME_PALETTE=${themeStore.activePaletteId}\nVITE_THEME_LAYOUT=${themeStore.activeLayoutId}`
)

function formatPreviewBytes(bytes) {
  if (bytes === 0) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  const unitIndex = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)
  const value = bytes / (1024 ** unitIndex)
  return `${value < 10 && unitIndex > 0 ? value.toFixed(1) : Math.round(value)} ${units[unitIndex]}`
}
</script>

<style scoped>
.palette-card {
  border: 1px solid color-mix(in srgb, var(--color-brand-hover, #EEF1FA) 35%, transparent);
  border-radius: 0.9rem;
  padding: 1rem;
  background: color-mix(in srgb, var(--color-surface, #FAFAFA) 94%, black 6%);
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-brand-secondary, #F3F4FA) 30%, transparent);
}

.spinner {
  width: 1.75rem;
  height: 1.75rem;
  border: 3px solid color-mix(in srgb, var(--color-loading-spinner, #20265A) 40%, transparent);
  border-top-color: var(--color-loading-spinner, #20265A);
  border-radius: 9999px;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
