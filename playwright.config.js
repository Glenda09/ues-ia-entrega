// Suite E2E del recorrido integral de aceptación (docs/ENTREGA.md).
// Requiere el stack Docker levantado y la base sembrada: php artisan migrate:fresh --seed (APP_ENV=local).
import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  timeout: 90_000,
  expect: { timeout: 10_000 },
  reporter: [['list'], ['html', { open: 'never', outputFolder: '../output/playwright/e2e-report' }]],
  outputDir: '../output/playwright/e2e-results',
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://localhost:8090',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    locale: 'es-SV'
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }]
})
