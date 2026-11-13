import { computed, onScopeDispose, ref, watch } from 'vue'
import { useThemeStore } from '@/shared/stores/theme'
import { DEFAULT_COLOR_MODE } from '@/shared/config/theme.config'

const STORAGE_KEY = 'theme-color-mode'
const VALID_MODES = ['light', 'dark', 'auto']
const DARK_PALETTE_ID = 'dark-navy'
const LIGHT_PALETTE_ID = 'light-soft'

const mode = ref(readStoredMode())
const systemPrefersDark = ref(getSystemPrefersDark())
let mediaQuery = null
let listenersBound = false

function readStoredMode() {
  if (typeof window === 'undefined') return DEFAULT_COLOR_MODE
  const stored = window.localStorage?.getItem(STORAGE_KEY)
  return VALID_MODES.includes(stored) ? stored : DEFAULT_COLOR_MODE
}

function getSystemPrefersDark() {
  if (typeof window === 'undefined' || !window.matchMedia) return false
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

function ensureMediaListener() {
  if (listenersBound || typeof window === 'undefined' || !window.matchMedia) return
  mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
  const handler = (event) => {
    systemPrefersDark.value = event.matches
  }
  mediaQuery.addEventListener('change', handler)
  listenersBound = true
}

export function useColorMode() {
  const themeStore = useThemeStore()
  ensureMediaListener()

  const resolvedMode = computed(() =>
    mode.value === 'auto' ? (systemPrefersDark.value ? 'dark' : 'light') : mode.value
  )

  const isDark = computed(() => resolvedMode.value === 'dark')

  function applyResolved(resolved) {
    const root = document.documentElement
    if (resolved === 'dark') {
      root.classList.add('dark')
      root.classList.remove('light')
      root.setAttribute('data-theme', 'dark')
    } else {
      root.classList.add('light')
      root.classList.remove('dark')
      root.setAttribute('data-theme', 'light')
    }
    themeStore.setColorMode(resolved === 'dark' ? DARK_PALETTE_ID : LIGHT_PALETTE_ID, resolved)
  }

  function setMode(next) {
    if (!VALID_MODES.includes(next)) return
    mode.value = next
    if (typeof window !== 'undefined') {
      window.localStorage?.setItem(STORAGE_KEY, next)
    }
    applyResolved(resolvedMode.value)
  }

  function toggleMode() {
    setMode(resolvedMode.value === 'dark' ? 'light' : 'dark')
  }

  const stopWatcher = watch(resolvedMode, applyResolved)
  onScopeDispose(stopWatcher)

  return {
    mode,
    resolvedMode,
    isDark,
    setMode,
    toggleMode
  }
}

export function initColorMode() {
  const stored = readStoredMode()
  mode.value = stored
  systemPrefersDark.value = getSystemPrefersDark()
  ensureMediaListener()
  const resolved = stored === 'auto' ? (systemPrefersDark.value ? 'dark' : 'light') : stored
  const themeStore = useThemeStore()
  const root = document.documentElement
  if (resolved === 'dark') {
    root.classList.add('dark')
    root.classList.remove('light')
    root.setAttribute('data-theme', 'dark')
  } else {
    root.classList.add('light')
    root.classList.remove('dark')
    root.setAttribute('data-theme', 'light')
  }
  themeStore.setColorMode(resolved === 'dark' ? DARK_PALETTE_ID : LIGHT_PALETTE_ID, resolved)
}
