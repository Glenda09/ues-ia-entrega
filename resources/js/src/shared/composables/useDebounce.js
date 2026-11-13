import { ref, watch, onBeforeUnmount } from 'vue';

// Constante por defecto para evitar números mágicos (SonarQube)
const DEFAULT_DELAY = 300;

// Uso típico:
// const { input, debounced, isDebouncing } = useDebounce('', 400)
// <input v-model="input" />
// debounced.value se actualiza después del retraso
// API flexible:
// - useDebounce(initial, delayNumber, onDebounced?)
// - useDebounce(initial, { delay, onDebounced })
/**
 * Debounce reactivo.
 * @param {any} initialValue Valor inicial del input y debounced
 * @param {number|{delay:number,onDebounced?:(val:any)=>void}} delayOrOptions Milisegundos o config
 * @param {(val:any)=>void} [maybeCallback] Callback opcional al debounced
 * @returns {{ input: import('vue').Ref<any>, debounced: import('vue').Ref<any>, isDebouncing: import('vue').Ref<boolean> }}
 */
export function useDebounce(initialValue, delayOrOptions, maybeCallback) {
  // Defaults internos para cumplir "default parameters should be last"
  const initial = typeof initialValue === 'undefined' ? '' : initialValue;
  const optionsArg = typeof delayOrOptions === 'undefined' ? DEFAULT_DELAY : delayOrOptions;

  const input = ref(initial);
  const debounced = ref(initial);
  const isDebouncing = ref(false);
  let timeoutId = null;

  // Normalizar opciones
  let delay = DEFAULT_DELAY;
  let onDebounced;
  if (typeof optionsArg === 'number') {
    delay = optionsArg;
    onDebounced = maybeCallback;
  } else if (typeof optionsArg === 'object' && optionsArg !== null) {
    delay = typeof optionsArg.delay === 'number' ? optionsArg.delay : DEFAULT_DELAY;
    onDebounced = optionsArg.onDebounced;
  }

  watch(
    input,
    (val) => {
      // Cancelar cualquier temporizador en curso de forma explícita (Sonar: evitar truthy checks implícitos)
      if (timeoutId !== null) {
        clearTimeout(timeoutId);
        timeoutId = null;
      }
      isDebouncing.value = true;
      timeoutId = setTimeout(() => {
        debounced.value = val;
        isDebouncing.value = false;
        if (typeof onDebounced === 'function') {
          try {
            onDebounced(val);
          } catch (e) {
            // Intencional: no propagamos errores del callback del consumidor para no romper el flujo.
            // Registrar para diagnóstico en desarrollo.
            // eslint-disable-next-line no-console
            console.error('useDebounce onDebounced callback failed:', e);
          }
        }
      }, delay);
    },
    { immediate: false }
  );

  onBeforeUnmount(() => {
    if (timeoutId !== null) {
      clearTimeout(timeoutId);
      timeoutId = null;
    }
  });

  return { input, debounced, isDebouncing };
}
