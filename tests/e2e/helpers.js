// Utilidades compartidas de la suite E2E (recorrido integral de aceptación, docs/ENTREGA.md).
import { expect } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

export const PASSWORD = 'Demo2026!'
export const APP_DIR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
export const FIXTURES = path.resolve(APP_DIR, '../output/fixtures')
export const MAILPIT = process.env.E2E_MAILPIT_URL || 'http://localhost:8025'

/** Ejecuta un comando artisan dentro del contenedor (operaciones de operador, p. ej. presupuesto). */
export function artisan(...args) {
  return execFileSync('docker', ['compose', 'exec', '-T', 'app', 'php', 'artisan', ...args], { cwd: APP_DIR, encoding: 'utf8' })
}

/** Inicia sesión por la interfaz con identificador (carnet, usuario o correo). */
export async function login(page, identifier, password = PASSWORD) {
  await page.goto('/login')
  await page.evaluate(() => localStorage.clear())
  await page.goto('/login')
  await page.getByLabel('Usuario o correo').fill(identifier)
  await page.getByPlaceholder('Contraseña').fill(password)
  await page.getByRole('button', { name: /iniciar sesión|ingresar|entrar/i }).click()
  await expect(page).not.toHaveURL(/\/login/, { timeout: 15_000 })
}

/** Token JWT por API (para comprobaciones directas de aislamiento). */
export async function token(request, identifier, password = PASSWORD) {
  const r = await request.post('/api/auth/login', { data: { identifier, password } })
  expect(r.status()).toBe(200)
  return (await r.json()).access_token
}

/** Último código de 6 dígitos enviado a un destinatario en Mailpit. */
export async function codigoMailpit(request, destinatario) {
  for (let i = 0; i < 30; i++) {
    const r = await request.get(`${MAILPIT}/api/v1/search?query=${encodeURIComponent('to:' + destinatario)}`)
    const d = await r.json()
    const msg = (d.messages || []).find((m) => /verificación/i.test(m.Subject))
    if (msg) {
      const det = await (await request.get(`${MAILPIT}/api/v1/message/${msg.ID}`)).json()
      const m = /\b(\d{6})\b/.exec(det.Text || det.HTML || '')
      if (m) return m[1]
    }
    await new Promise((res) => setTimeout(res, 1000))
  }
  throw new Error('No llegó el código de verificación a Mailpit')
}

/** Espera un correo con asunto dado en Mailpit. */
export async function esperarCorreo(request, destinatario, asunto) {
  for (let i = 0; i < 30; i++) {
    const r = await request.get(`${MAILPIT}/api/v1/search?query=${encodeURIComponent('to:' + destinatario)}`)
    const d = await r.json()
    if ((d.messages || []).some((m) => m.Subject.includes(asunto))) return true
    await new Promise((res) => setTimeout(res, 1000))
  }
  return false
}
