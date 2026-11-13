<template>
  <div class="relative" :class="wrapperClassList" ref="rootEl">
    <Icon
      v-if="hasLeftIcon"
      v-bind="leftIconBindings"
      :class="leftIconClassList"
      aria-hidden="true"
    />
    <InputText
      ref="inputRef"
      :model-value="modelValue"
      @update:model-value="onUpdateModel"
      v-bind="inputAttrs"
      :pt="mergedPt"
      :class="inputClassList"
    />
    <Icon
      v-if="hasRightIcon"
      v-bind="rightIconBindings"
      :class="rightIconClassList"
      aria-hidden="true"
    />
  </div>
</template>

<script setup>
import { ref, computed, useAttrs } from 'vue'
import { InputText } from 'primevue'
import Icon from '@/shared/components/Icon.vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: ''
  },
  leftIcon: {
    type: [String, Object],
    default: null
  },
  rightIcon: {
    type: [String, Object],
    default: null
  },
  rootClass: {
    type: [String, Array, Object],
    default: ''
  },
  inputClass: {
    type: [String, Array, Object],
    default: ''
  },
  leftIconClass: {
    type: [String, Array, Object],
    default: ''
  },
  rightIconClass: {
    type: [String, Array, Object],
    default: ''
  },
  pt: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:modelValue'])

const attrs = useAttrs()

const rootEl = ref(null)
const inputRef = ref(null)

const hasLeftIcon = computed(() => Boolean(props.leftIcon))
const hasRightIcon = computed(() => Boolean(props.rightIcon))

const wrapperClassList = computed(() => normalizeToArray(props.rootClass))

const mergedPt = computed(() => {
  const hasEntries = props.pt && Object.keys(props.pt).length > 0
  return hasEntries ? props.pt : undefined
})

const inputAttrs = computed(() => {
  const result = {}
  Object.entries(attrs).forEach(([key, value]) => {
    if (key !== 'class' && key !== 'pt') {
      result[key] = value
    }
  })
  return result
})

const inputClassList = computed(() => {
  const classes = ['w-full']
  if (hasLeftIcon.value) classes.push('pl-10!')
  if (hasRightIcon.value) classes.push('pr-10!')
  classes.push(...normalizeToArray(attrs.class))
  classes.push(...normalizeToArray(props.inputClass))
  return classes.filter(Boolean)
})

const defaultIconClasses = 'absolute top-1/2 -translate-y-1/2 leading-none pointer-events-none z-[1]'

const leftIconClassList = computed(() => {
  return [
    defaultIconClasses,
    'left-3',
    ...normalizeToArray(props.leftIconClass)
  ].filter(Boolean)
})

const rightIconClassList = computed(() => {
  return [
    defaultIconClasses,
    'right-3',
    ...normalizeToArray(props.rightIconClass)
  ].filter(Boolean)
})

const leftIconBindings = computed(() => normalizeIcon(props.leftIcon))
const rightIconBindings = computed(() => normalizeIcon(props.rightIcon))

function onUpdateModel(value) {
  emit('update:modelValue', value)
}

function getNativeInput() {
  const inputComponentEl = inputRef.value?.$el
  if (inputComponentEl) {
    if (inputComponentEl.tagName === 'INPUT') return inputComponentEl
    const nested = inputComponentEl.querySelector?.('input')
    if (nested) return nested
  }
  const fromRoot = rootEl.value?.querySelector?.('input')
  return fromRoot || null
}

function focus() {
  const el = getNativeInput()
  if (el && typeof el.focus === 'function') el.focus()
}

function normalizeToArray(value) {
  if (!value) return []
  if (Array.isArray(value)) {
    return value.flatMap((item) => normalizeToArray(item)).filter(Boolean)
  }
  if (typeof value === 'string') {
    return value.split(' ').filter(Boolean)
  }
  if (typeof value === 'object') {
    return Object.entries(value)
      .filter(([, active]) => Boolean(active))
      .map(([className]) => className)
  }
  return []
}

function normalizeIcon(value) {
  if (!value) return undefined
  if (typeof value === 'string') {
    return { name: value }
  }
  return { ...value }
}

defineExpose({ focus })
</script>
