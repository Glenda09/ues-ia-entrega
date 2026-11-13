<template>
  <span :class="iconClass" :style="{ fontSize: fontSizeStyle }" aria-hidden="true" v-bind="$attrs">
    {{ name }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  // Nombre del ícono de Material Symbols (p. ej., 'home', 'search')
  name: {
    type: String,
    required: true
  },
  // Variante de familia: outlined | rounded | sharp
  type: {
    type: String,
    default: 'outlined',
    validator: (v) => ['outlined', 'rounded', 'sharp'].includes(v)
  },
  // Tamaño de fuente. Número => px, o string (e.g. '20px')
  fontSize: {
    type: [Number, String],
    default: 24
  },
  // Clase de color de texto (tokens del tema), e.g., 'text-brand', 'text-secondary'
  color: {
    type: String,
    default: ''
  }
})

const iconClass = computed(() => {
  const base = `material-symbols-${props.type}`
  return [base, props.color].filter(Boolean).join(' ')
})

const fontSizeStyle = computed(() => {
  const v = props.fontSize
  if (v === undefined || v === null || v === '') return undefined
  if (typeof v === 'number') return `${v}px`
  // Si sólo son dígitos, asume px; si no, respeta tal cual
  return /^\d+$/.test(v) ? `${v}px` : String(v)
})
</script>
