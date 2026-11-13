<template>
  <!-- Hero -->
  <header role="banner" aria-labelledby="hero-title" class="bg-surface-card w-full">
    <div class="mx-auto max-w-7xl px-4 sm:px-4 lg:px-8 py-16 sm:py-24">
      <div class="flex flex-col items-center text-center">
        <img
          v-if="appLogoUrl"
          :src="appLogoUrl"
          :alt="appName"
          class="h-24 w-24 md:h-32 md:w-32 mb-4"
          loading="eager"
          decoding="async"
        />
        <h1 id="hero-title" class="text-3xl sm:text-4xl font-bold text-text">{{ marca.texto('titulo_inicio') || '¡Bienvenido!' }}</h1>
        <p class="mt-3 max-w-2xl text-base sm:text-lg text-secondary">
          {{ marca.texto('descripcion_inicio') }}
        </p>
      </div>
    </div>
  </header>

  <section aria-labelledby="features-title" class="bg-secondary-100">
    <div class="mx-auto max-w-7xl px-4 sm:px-4 lg:px-8 py-12 sm:py-16">
      <h2 id="features-title" class="sr-only">Características</h2>
      <div class="flex flex-col lg:flex-row gap-4">
        <article
          class="rounded-lg bg-surface-card p-4 shadow-sm ring-1 ring-secondary-100"
          aria-labelledby="feature-1-title"
        >
          <h3 id="feature-1-title" class="text-lg font-semibold">Título 1</h3>
          <p class="mt-2 text-secondary">
            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Suspendisse varius, nunc non
            placerat faucibus, arcu lacus feugiat elit, sit amet facilisis sapien lorem non purus.
          </p>
        </article>
        <article
          class="rounded-lg bg-surface-card p-4 shadow-sm ring-1 ring-secondary-100"
          aria-labelledby="feature-2-title"
        >
          <h3 id="feature-2-title" class="text-lg font-semibold">Título 2</h3>
          <p class="mt-2 text-secondary">
            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Suspendisse varius, nunc non
            placerat faucibus, arcu lacus feugiat elit, sit amet facilisis sapien lorem non purus.
          </p>
        </article>
        <article
          class="rounded-lg bg-surface-card p-4 shadow-sm ring-1 ring-secondary-100"
          aria-labelledby="feature-3-title"
        >
          <h3 id="feature-3-title" class="text-lg font-semibold">Título 3</h3>
          <p class="mt-2 text-secondary">
            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Suspendisse varius, nunc non
            placerat faucibus, arcu lacus feugiat elit, sit amet facilisis sapien lorem non purus.
          </p>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { useAppBrand } from '@/shared/composables/useAppBrand'
import { usePersonalizacionStore } from '@/shared/stores/personalizacion'

const marca = usePersonalizacionStore()
const { appName, appLogoUrl } = useAppBrand()
</script>
