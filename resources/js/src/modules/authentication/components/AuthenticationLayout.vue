<template>
  <main id="contenido-principal" class="authentication-layout" :class="{ 'authentication-layout--dark': isDark }">
    <div class="authentication-wallpapers-pila" aria-hidden="true">
      <img
        v-if="fondoClaro"
        :src="fondoClaro"
        alt=""
        class="authentication-wallpaper"
        :class="{ 'authentication-wallpaper--visible': !isDark }"
      />
      <img
        v-if="fondoOscuro"
        :src="fondoOscuro"
        alt=""
        class="authentication-wallpaper authentication-wallpaper--dark"
        :class="{ 'authentication-wallpaper--visible': isDark }"
      />
      <div class="authentication-wallpaper-overlay"></div>
    </div>

    <header class="authentication-header">
      <RouterLink :to="{ name: 'landingPage.index' }" class="authentication-brand" :aria-label="appName || 'Inicio'">
        <img v-if="appLogoUrl" :src="appLogoUrl" :alt="appName" class="authentication-brand-logo" />
        <span class="authentication-brand-name">{{ appName }}</span>
      </RouterLink>
      <div class="flex items-center gap-3">
        <Button
          type="button"
          variant="text"
          severity="secondary"
          :aria-label="isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
          class="rounded-xl! p-2!"
          @click="toggleMode"
        >
          <Icon :name="isDark ? 'light_mode' : 'dark_mode'" fontSize="20" />
        </Button>
      </div>
    </header>

    <div class="authentication-content">
      <section class="authentication-layout-card">
        <slot />
      </section>
    </div>
  </main>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Button } from 'primevue'
import Icon from '@/shared/components/Icon.vue'
import { useAppBrand } from '@/shared/composables/useAppBrand'
import { useColorMode } from '@/shared/composables/useColorMode'
import { usePersonalizacionStore } from '@/shared/stores/personalizacion'
import '@/styles/outfit.css'

const { appLogoUrl, appName } = useAppBrand()
const personalizacion = usePersonalizacionStore()
const fondoClaro = computed(() => personalizacion.asset('fondo_acceso'))
const fondoOscuro = computed(() => personalizacion.asset('fondo_acceso_oscuro') || personalizacion.asset('fondo_acceso'))
const { isDark, toggleMode } = useColorMode()
</script>

<style scoped>
.authentication-layout {
  position: relative;
  isolation: isolate;
  display: flex;
  min-height: 100dvh;
  flex-direction: column;
  background-color: var(--color-surface, #f6fbff);
  color: var(--color-text, #1a1d23);
  font-family: 'Outfit', sans-serif;
  transition: background-color 300ms ease, color 300ms ease;
}

.authentication-wallpapers-pila {
  position: absolute;
  inset: 0;
  z-index: -1;
  overflow: hidden;
  pointer-events: none;
  user-select: none;
}

.authentication-wallpaper {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: bottom;
  opacity: 0;
  transition: opacity 520ms ease;
}

.authentication-wallpaper--visible {
  opacity: 0.35;
}

.authentication-wallpaper-overlay {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: color-mix(in srgb, var(--color-surface) 35%, transparent);
}

.authentication-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem clamp(1rem, 4vw, 2.5rem);
}

.authentication-brand {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  color: var(--color-text, #1a1d23);
  text-decoration: none;
}

.authentication-brand-logo {
  height: 2rem;
  width: 1.75rem;
  object-fit: contain;
}

.authentication-brand-name {
  font-size: 0.9375rem;
  font-weight: 600;
}

.authentication-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.875rem;
  border-radius: 0.75rem;
  background-color: color-mix(in srgb, var(--color-surface-card) 90%, transparent);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid var(--color-secondary-100, rgba(0, 0, 0, 0.08));
  color: var(--color-text, #1a1d23);
  font-size: 0.875rem;
  font-weight: 500;
  text-decoration: none;
  box-shadow: 0 2px 8px color-mix(in srgb, var(--color-text) 6%, transparent);
  transition: background-color 180ms ease, border-color 180ms ease, color 180ms ease, transform 180ms ease;
}

.authentication-back-btn:hover {
  background-color: var(--color-brand-secondary, #e7f0ff);
  color: var(--color-link);
}

.authentication-content {
  display: grid;
  flex: 1;
  grid-template-columns: minmax(0, 1fr);
  place-items: center;
  padding: 24px 16px 48px;
}

.authentication-layout-card {
  width: min(100%, 30rem);
  border-radius: 1.25rem;
  background-color: color-mix(in srgb, var(--color-surface-card) 94%, transparent);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid var(--color-secondary-100, transparent);
  box-shadow: 0 16px 48px color-mix(in srgb, var(--color-text) 10%, transparent);
  transition: background-color 300ms ease, border-color 300ms ease, box-shadow 300ms ease;
}

:deep(.authentication-form) {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: clamp(24px, 5vw, 32px);
  color: var(--color-text, #1a1d23);
}

:deep(.authentication-title) {
  margin: 0;
  color: var(--color-link);
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 2rem;
}

:deep(.authentication-description) {
  margin: -0.75rem 0 0;
  color: var(--color-secondary, #5b6472);
  font-size: 0.875rem;
  line-height: 1.3125rem;
}

:deep(.authentication-fields) {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

:deep(.authentication-label) {
  display: block;
  margin-bottom: 0.5rem;
  color: var(--color-text, #1a1d23);
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.3125rem;
}

:deep(.authentication-field) {
  position: relative;
  display: flex;
  min-height: 44px;
  align-items: center;
  width: 100%;
  padding: 0.5rem 0.75rem;
  border-radius: 0.625rem;
  background-color: var(--color-input, #f0f6ff);
  border: 1px solid var(--color-secondary-100, transparent);
  color: var(--color-text, #1a1d23);
  transition: background-color 180ms ease, border-color 180ms ease;
}

:deep(.authentication-field:focus-within) {
  outline: 2px solid var(--color-link);
  outline-offset: 2px;
}

:deep(.authentication-field-icon) {
  flex: 0 0 1.5rem;
  width: 1.5rem;
  height: 1.5rem;
  margin-right: 0.625rem;
}

:deep(.authentication-field-input) {
  min-width: 0;
  flex: 1;
  height: 1.5rem !important;
  padding: 0 !important;
  border: 0 !important;
  border-radius: 0 !important;
  background: transparent !important;
  color: var(--color-text, #1a1d23) !important;
  font-size: 1rem;
  line-height: 1.5rem;
  box-shadow: none !important;
}

:deep(.authentication-field .authentication-field-input.pl-10\!) {
  padding-left: 2rem !important;
}

:deep(.authentication-field-input::placeholder) {
  color: var(--color-secondary, #5b6472) !important;
  opacity: 0.8;
}

:deep(.authentication-otp) {
  display: flex;
  flex-wrap: nowrap;
  justify-content: center;
  gap: 0.5rem;
}

:deep(.authentication-otp .p-inputotp-input) {
  width: 2.5rem !important;
  height: 2.5rem !important;
}

:deep(.authentication-submit) {
  width: 100%;
  min-height: 44px;
  padding: 0.625rem 1rem !important;
  border: 0 !important;
  border-radius: 0.625rem !important;
  background-color: var(--color-brand, #0047ab) !important;
  color: var(--color-brand-contrast, #fff) !important;
  font-size: 1rem;
  font-weight: 600;
  line-height: 1.5rem;
  cursor: pointer;
  transition: opacity 180ms ease, background-color 180ms ease;
}

:deep(.authentication-submit:hover:not(:disabled)) {
  background-color: var(--color-brand-button-hover, #003b8e) !important;
}

:deep(.authentication-link) {
  align-self: center;
  color: var(--color-link, #0047ab);
  font-size: 0.8125rem;
  text-align: center;
  text-decoration: none;
  font-weight: 500;
}

:deep(.authentication-link:hover) {
  text-decoration: underline;
}

@media (prefers-reduced-motion: no-preference) {
  .authentication-layout-card {
    animation: authentication-enter 180ms ease-out;
  }
}

@keyframes authentication-enter {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
