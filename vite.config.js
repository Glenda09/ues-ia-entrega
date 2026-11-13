import { defineConfig } from 'vite'
import { fileURLToPath, URL } from 'node:url'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig(({ command }) => ({
  publicDir: command === 'serve' ? 'public' : false,
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      refresh: true
    }),
    vue(),
    tailwindcss()
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js/src', import.meta.url))
    }
  }
}))
