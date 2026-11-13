<template>
  <article class="flex min-w-0 items-start gap-2 sm:gap-3" :aria-label="`Respuesta del asistente${fechaTexto ? ', ' + fechaTexto : ''}`">
    <span class="mt-1 hidden h-8 w-8 shrink-0 sm:flex items-center justify-center rounded-full bg-brand text-brand-contrast" aria-hidden="true">
      <i class="pi pi-sparkles text-sm"></i>
    </span>
    <div class="min-w-0 flex-1 rounded-2xl rounded-tl-sm border border-secondary-100 bg-surface-card px-3 py-3 shadow-sm sm:px-4">
      <div ref="cuerpo" class="mensaje-md" @click="alHacerClic" v-html="html"></div>

      <ul v-if="advertencias.length" class="mt-3 space-y-1 rounded-lg border border-warning/30 bg-warning/10 px-3 py-2 text-sm text-warning">
        <li v-for="(a, i) in advertencias" :key="i" class="flex gap-2"><i class="pi pi-info-circle mt-0.5 text-xs" aria-hidden="true"></i><span>{{ a }}</span></li>
      </ul>

      <div v-if="propuesta" class="mt-3 rounded-xl border border-brand-100 bg-brand-50 p-3 text-sm">
        <p class="flex items-center gap-2 font-semibold"><i class="pi pi-list-check" aria-hidden="true"></i>Propuesta de inscripción</p>
        <p v-if="propuesta.grupos?.length" class="mt-1 text-secondary">{{ propuesta.grupos.length }} {{ propuesta.grupos.length === 1 ? 'grupo propuesto' : 'grupos propuestos' }} · calculada {{ fecha(propuesta.calculado_en) }}</p>
        <ul v-if="propuesta.razones?.length" class="mt-1 list-disc pl-5">
          <li v-for="(r, i) in propuesta.razones" :key="i">{{ r.message ?? r }}</li>
        </ul>
        <RouterLink v-if="propuesta.enlace" :to="propuesta.enlace" class="mt-2 inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 font-medium text-brand-contrast hover:bg-brand-button-hover">
          Ir a inscripción<i class="pi pi-arrow-right text-xs" aria-hidden="true"></i>
        </RouterLink>
      </div>

      <section v-if="fuentes.length" class="mt-3 border-t border-secondary-100 pt-2" :aria-label="`Fuentes (${fuentes.length})`">
        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-secondary">Fuentes</p>
        <ol class="space-y-1">
          <li v-for="f in fuentes" :key="f.id" :id="anclaFuente(f.n)" class="flex items-start gap-2 rounded-md text-sm transition-colors" :class="{ 'bg-brand-50': resaltada === f.n }">
            <span class="mt-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-secondary-100 px-1 text-[0.7rem] font-semibold text-secondary">{{ f.n }}</span>
            <span class="min-w-0">
              <RouterLink v-if="esInterna(f.url)" :to="f.url" class="font-medium text-link underline-offset-2 hover:underline">{{ f.titulo }}</RouterLink>
              <a v-else-if="f.url" :href="f.url" target="_blank" rel="noopener noreferrer" class="font-medium text-link underline-offset-2 hover:underline">{{ f.titulo }}<i class="pi pi-external-link ml-1 text-[0.65rem]" aria-hidden="true"></i></a>
              <span v-else class="font-medium">{{ f.titulo }}</span>
              <span v-if="detalleFuente(f)" class="block text-xs text-secondary">{{ detalleFuente(f) }}</span>
            </span>
          </li>
        </ol>
      </section>
    </div>
  </article>
</template>

<script setup>
/**
 * Mensaje del asistente con Markdown saneado, citas numeradas en línea y lista de fuentes.
 *
 * Las marcas `[fuente:ID]` (y variantes antiguas como `[Fuente: a, b]`) se convierten en
 * superíndices numerados que apuntan a la fuente; solo se numeran IDs presentes en `referencias.fuentes`.
 */
import { computed, ref, useId } from 'vue'
import { useRouter } from 'vue-router'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import { fecha } from '../services/academico'

const props = defineProps({
  mensaje: { type: Object, required: true }
})

const router = useRouter()
const uid = useId()
const cuerpo = ref()
const resaltada = ref(null)
const PATRON_CITA = /[[【]\s*fuentes?\s*:\s*([^\]】]+?)\s*[\]】]/giu

const referencias = computed(() => props.mensaje.referencias ?? {})
const advertencias = computed(() => (referencias.value.advertencias ?? []).map((a) => a?.message ?? a))
const propuesta = computed(() => referencias.value.propuesta ?? null)
const fechaTexto = computed(() => (props.mensaje.created_at ? fecha(props.mensaje.created_at) : ''))

/** Corchetes con solo IDs y sin prefijo (`[avance:1]`), excluyendo enlaces Markdown `[texto](url)`. */
const PATRON_SIN_PREFIJO = /\[([A-Za-z0-9:_-]+(?:\s*[,;]\s*[A-Za-z0-9:_-]+)*)\](?!\()/g

const separar = (grupo) => grupo.split(/\s*[,;]\s*|\s+y\s+|\s+/u).map((p) => p.replace(/^fuentes?\s*:\s*/iu, '').trim()).filter(Boolean)

const disponibles = computed(() => new Map((referencias.value.fuentes ?? []).map((f) => [String(f.id), f])))

/** Contenido con citas en formato canónico; acepta `[avance:1]` si todos los IDs son fuentes del mensaje. */
const contenido = computed(() => String(props.mensaje.contenido ?? '').replace(PATRON_SIN_PREFIJO, (todo, grupo) => {
  const ids = separar(grupo)
  return ids.length && ids.every((id) => disponibles.value.has(id)) ? `[fuente:${ids.join(', ')}]` : todo
}))

/** Fuentes numeradas por orden de primera cita; las no citadas en el texto van al final. */
const fuentes = computed(() => {
  const disponibles = new Map((referencias.value.fuentes ?? []).map((f) => [String(f.id), f]))
  const orden = []
  for (const m of contenido.value.matchAll(PATRON_CITA)) {
    for (const id of separar(m[1])) if (disponibles.has(id) && !orden.includes(id)) orden.push(id)
  }
  for (const id of disponibles.keys()) if (!orden.includes(id)) orden.push(id)
  return orden.map((id, i) => ({ ...disponibles.get(id), n: i + 1 }))
})

const anclaFuente = (n) => `${uid}-fuente-${n}`
const esInterna = (url) => typeof url === 'string' && url.startsWith('/')

function detalleFuente(f) {
  const partes = []
  if (f.version !== undefined && f.version !== null && f.version !== '') partes.push(`versión ${f.version}`)
  if (f.calculado_en) partes.push(`consultado ${fecha(f.calculado_en)}`)
  if (f.vigente_hasta) partes.push(`vigente hasta ${fecha(f.vigente_hasta, 'DD/MM/YYYY')}`)
  return partes.join(' · ')
}

const html = computed(() => {
  const numero = new Map(fuentes.value.map((f) => [String(f.id), f.n]))
  // 1) Citas → marcadores neutros (se sustituyen tras el parseo para no interferir con Markdown).
  const texto = contenido.value.replace(PATRON_CITA, (_, grupo) =>
    separar(grupo).filter((id) => numero.has(id)).map((id) => `@@CITA${numero.get(id)}@@`).join('')
  )
  // 2) Markdown → HTML saneado (sin HTML crudo del modelo, sin imágenes ni scripts).
  const crudo = marked.parse(texto.replace(/[ \t]+([.,;:])/g, '$1'), { gfm: true, breaks: true })
  const limpio = DOMPurify.sanitize(crudo, {
    ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'b', 'i', 'ul', 'ol', 'li', 'code', 'pre', 'blockquote', 'a', 'h1', 'h2', 'h3', 'h4', 'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'del'],
    ALLOWED_ATTR: ['href', 'title']
  })
  // 3) Marcadores → superíndices enlazados a la lista de fuentes.
  return limpio
    .replace(/(?:@@CITA(\d+)@@)+/g, (grupo) => {
      const nums = [...grupo.matchAll(/@@CITA(\d+)@@/g)].map((m) => m[1])
      return `<sup class="citas">${nums.map((n) => `<a href="#${anclaFuente(n)}" class="cita" data-cita="${n}" aria-label="Fuente ${n}">${n}</a>`).join('')}</sup>`
    })
    .replace(/<a href="([^"#][^"]*)"/g, (m, href) => (href.startsWith('/') ? `<a href="${href}" data-interno="1"` : `<a href="${href}" target="_blank" rel="noopener noreferrer"`))
})

/** Citas: desplaza y resalta la fuente. Enlaces internos: navegación SPA. */
function alHacerClic(e) {
  const a = e.target.closest('a')
  if (!a) return
  if (a.dataset.cita) {
    e.preventDefault()
    const n = Number(a.dataset.cita)
    document.getElementById(anclaFuente(n))?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
    resaltada.value = n
    setTimeout(() => { if (resaltada.value === n) resaltada.value = null }, 1600)
  } else if (a.dataset.interno) {
    e.preventDefault()
    router.push(a.getAttribute('href'))
  }
}
</script>

<style scoped>
.mensaje-md {
  line-height: 1.6;
  overflow-wrap: anywhere;
}
.mensaje-md :deep(p) { margin: 0 0 0.6rem; }
.mensaje-md :deep(p:last-child),
.mensaje-md :deep(ul:last-child),
.mensaje-md :deep(ol:last-child) { margin-bottom: 0; }
.mensaje-md :deep(ul),
.mensaje-md :deep(ol) { margin: 0.25rem 0 0.6rem; padding-left: 1.25rem; }
.mensaje-md :deep(ul) { list-style: disc; }
.mensaje-md :deep(ol) { list-style: decimal; }
.mensaje-md :deep(li) { margin: 0.15rem 0; }
.mensaje-md :deep(li > ul),
.mensaje-md :deep(li > ol) { margin: 0.15rem 0; }
.mensaje-md :deep(strong) { font-weight: 600; }
.mensaje-md :deep(h1),
.mensaje-md :deep(h2),
.mensaje-md :deep(h3),
.mensaje-md :deep(h4) { font-weight: 600; font-size: 1rem; margin: 0.75rem 0 0.35rem; }
.mensaje-md :deep(code) { font-size: 0.85em; padding: 0.1rem 0.3rem; border-radius: 0.3rem; background: color-mix(in srgb, currentColor 8%, transparent); }
.mensaje-md :deep(pre) { overflow-x: auto; padding: 0.6rem; border-radius: 0.5rem; background: color-mix(in srgb, currentColor 6%, transparent); }
.mensaje-md :deep(pre code) { padding: 0; background: none; }
.mensaje-md :deep(blockquote) { border-left: 3px solid color-mix(in srgb, currentColor 25%, transparent); padding-left: 0.75rem; opacity: 0.9; }
.mensaje-md :deep(a:not(.cita)) { text-decoration: underline; text-underline-offset: 2px; }
.mensaje-md :deep(table) { display: block; overflow-x: auto; border-collapse: collapse; margin: 0.5rem 0; font-size: 0.875rem; }
.mensaje-md :deep(th),
.mensaje-md :deep(td) { border: 1px solid color-mix(in srgb, currentColor 15%, transparent); padding: 0.3rem 0.5rem; text-align: left; }
.mensaje-md :deep(sup.citas) { margin-left: 0.15rem; white-space: nowrap; }
.mensaje-md :deep(a.cita) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.15rem;
  height: 1.15rem;
  margin: 0 0.08rem;
  padding: 0 0.25rem;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 600;
  line-height: 1;
  text-decoration: none;
  vertical-align: super;
  color: var(--color-brand-contrast);
  background: var(--color-brand);
}
.mensaje-md :deep(a.cita:hover),
.mensaje-md :deep(a.cita:focus-visible) { background: var(--color-brand-button-hover); outline: 2px solid var(--color-link); outline-offset: 1px; }
</style>
