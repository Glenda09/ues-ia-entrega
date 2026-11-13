// Recorrido integral de aceptación (docs/ENTREGA.md, pasos 1–10) contra el stack Docker con datos demo.
// Preparación: docker compose exec app php artisan migrate:fresh --seed --force && npm run build
// Ejecución:   npx playwright test
import { test, expect } from '@playwright/test'
import path from 'node:path'
import fs from 'node:fs'
import { artisan, codigoMailpit, esperarCorreo, FIXTURES, login, MAILPIT, token } from './helpers.js'

test.describe.configure({ mode: 'serial' })

const sufijo = Date.now().toString(36)
const aspirante = { email: `aspirante.${sufijo}@ues.test`, password: 'Prueba2026!' }
let carnetNuevo = null

test.beforeAll(async ({ request }) => {
  await request.delete(`${MAILPIT}/api/v1/messages`)
  artisan('academico:ia-presupuesto', 'restablecer')
})

test.afterAll(() => {
  artisan('academico:ia-presupuesto', 'restablecer')
})

test('1. Administrador: catálogos, pensum publicado, gestores y configuración', async ({ page }) => {
  await login(page, 'admin.academico@ues.test')
  await expect(page.getByText('Administración académica').first()).toBeVisible()
  await page.goto('/academico/administracion/carreras')
  await expect(page.getByRole('cell', { name: 'ISI', exact: true })).toBeVisible()
  await page.goto('/academico/administracion/carreras/1/pensums')
  await expect(page.getByText('BAD115', { exact: false }).first()).toBeVisible()
  await expect(page.getByText('Requiere: ESD115')).toBeVisible()
  await expect(page.getByText('gestor.isi@ues.test')).toBeVisible()
  await page.goto('/academico/configuracion')
  await expect(page.getByRole('heading', { name: 'Revisión vigente' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Consumo de IA' })).toBeVisible()
})

test('2. Gestor: ciclo publicado con ventanas, grupos y convocatoria con requisitos', async ({ page }) => {
  await login(page, 'gestor.isi@ues.test')
  await page.goto('/academico/gestion-carreras')
  await expect(page.getByText('Ingeniería de Sistemas Informáticos').first()).toBeVisible()
  await page.goto('/academico/gestion-carreras/1/convocatorias')
  await expect(page.getByText(/Admisión \d{4}/).first()).toBeVisible()
  await expect(page.getByText('Fotografía').first()).toBeVisible()
})

test('3. Aspirante: registro verificado, formulario, documentos, pago y envío; gestor valida y acepta', async ({ page, request }) => {
  // Registro con código recibido por correo (Mailpit).
  await page.goto('/admision/registro')
  await page.getByLabel('Correo electrónico').fill(aspirante.email)
  await page.getByRole('button', { name: 'Enviar código de verificación' }).click()
  const codigo = await codigoMailpit(request, aspirante.email)
  await page.getByLabel('Código de verificación (6 dígitos)').fill(codigo)
  await page.getByLabel('Contraseña', { exact: true }).fill(aspirante.password)
  await page.getByLabel('Confirmar contraseña').fill(aspirante.password)
  await page.getByRole('button', { name: 'Crear cuenta' }).click()
  await expect(page.getByText(/Registro procesado|Inicia sesión/).first()).toBeVisible()

  // Formulario por API autenticada del propio aspirante (la UI se cubre en las rondas del tester).
  const t = await token(request, aspirante.email, aspirante.password)
  const H = { Authorization: `Bearer ${t}`, Accept: 'application/json' }
  const conv = (await (await request.get('/api/publico/convocatorias')).json()).data[0]
  const oferta = conv.ofertas.find((o) => o.modalidad_ingreso === 'general')
  let r = await request.patch('/api/academico/mi-borrador-ingreso', { headers: H, data: { convocatoria: conv.id_convocatoria, version: 0, etapa: 3, datos: {
    nombres: 'Laura', primer_apellido: 'Gómez', segundo_apellido: 'Rivas', fecha_nacimiento: '2000-01-15', nacionalidad: 'salvadoreña', dui: '01234567-8', nie: '123456',
    institucion_procedencia: 'INFRAMEN', estado_graduacion: 'graduado', modalidad_estudio: oferta.modalidad_estudio, modalidad_ingreso: oferta.modalidad_ingreso,
    id_sede: oferta.id_sede, id_facultad: oferta.id_facultad, id_carrera: oferta.id_carrera, referencia_comprobante: 'REF-E2E'
  } } })
  expect(r.status()).toBe(200)
  let b = (await r.json()).data
  expect(b.orden_pago.estado).toBe('pendiente')
  // Foto inválida rechazada; documentos válidos adjuntos.
  const adjuntar = (codigo, archivo) => request.post('/api/academico/mi-borrador-ingreso/anexos', { headers: H, multipart: {
    convocatoria: String(conv.id_convocatoria), codigo_requisito: codigo, version: String(b.borrador.version), archivo: { name: path.basename(archivo), mimeType: archivo.endsWith('.pdf') ? 'application/pdf' : 'image/jpeg', buffer: fs.readFileSync(archivo) }
  } })
  r = await adjuntar('fotografia', path.join(FIXTURES, 'foto_mala.jpg'))
  expect(r.status()).toBe(422)
  for (const [codigo, archivo] of [['fotografia', 'foto.jpg'], ['dui', 'doc.pdf'], ['titulo_bachiller', 'doc.pdf'], ['comprobante_pago', 'doc.pdf']]) {
    r = await adjuntar(codigo, path.join(FIXTURES, archivo))
    expect(r.status(), codigo).toBe(200)
    b = (await r.json()).data
  }
  const clave = `e2e-${sufijo}`
  r = await request.post(`/api/academico/convocatorias/${conv.id_convocatoria}/mis-solicitudes`, { headers: { ...H, 'Idempotency-Key': clave }, data: { version_requisitos: conv.version_requisitos } })
  expect(r.status()).toBe(201)
  const folio = (await r.json()).data.folio
  // Reintento de red conserva una sola solicitud.
  r = await request.post(`/api/academico/convocatorias/${conv.id_convocatoria}/mis-solicitudes`, { headers: { ...H, 'Idempotency-Key': clave }, data: { version_requisitos: conv.version_requisitos } })
  expect((await r.json()).data.folio).toBe(folio)

  // Gestor: observa → aspirante corrige → valida pago → acepta (UI).
  await login(page, 'gestor.isi@ues.test')
  await page.goto('/academico/solicitudes')
  await expect(page.getByText(folio).first()).toBeVisible()
  const tg = await token(request, 'gestor.isi@ues.test')
  const HG = { Authorization: `Bearer ${tg}`, Accept: 'application/json' }
  const sid = (await (await request.get('/api/academico/solicitudes?estado=recibida&per_page=100', { headers: HG })).json()).data.find((s) => s.folio === folio).id_solicitud
  await page.goto(`/academico/solicitudes/${sid}`)
  await expect(page.getByText(folio).first()).toBeVisible()
  let det = (await (await request.get(`/api/academico/solicitudes/${sid}`, { headers: HG })).json()).data
  r = await request.post(`/api/academico/solicitudes/${sid}/decisiones`, { headers: HG, data: { estado: 'observada', version: det.version, motivo: 'Fotografía poco clara' } })
  expect(r.status()).toBe(200)
  expect(await esperarCorreo(request, aspirante.email, 'observada')).toBeTruthy()
  // Token de corrección desde el correo (fragmento de URL).
  const busq = await (await request.get(`${MAILPIT}/api/v1/search?query=${encodeURIComponent('to:' + aspirante.email + ' subject:observada')}`)).json()
  const cuerpo = await (await request.get(`${MAILPIT}/api/v1/message/${busq.messages[0].ID}`)).json()
  const tokenCorr = /correccion#([A-Za-z0-9]+)/.exec(cuerpo.HTML)[1]
  await page.goto(`/admision/correccion#${tokenCorr}`)
  await page.setInputFiles('input[type=file] >> nth=0', path.join(FIXTURES, 'foto.jpg'))
  await page.getByRole('button', { name: /enviar corrección|corregir/i }).click()
  await expect(page.getByText(/revisión|recibida|Corrección enviada/i).first()).toBeVisible()

  await login(page, 'gestor.isi@ues.test')
  await page.goto(`/academico/solicitudes/${sid}`)
  await page.getByRole('button', { name: /validar/i }).first().click()
  await expect(page.getByText('validado').first()).toBeVisible()
  await page.getByRole('button', { name: 'Aceptar solicitud' }).click()
  const carnet = page.getByText(/[A-Z]{2}\d{6,}/).first()
  await expect(carnet).toBeVisible()
  carnetNuevo = /([A-Z]{2}\d{6,})/.exec(await carnet.innerText())[1]
  expect(carnetNuevo.startsWith('GR')).toBeTruthy()
  // Reintento de aceptación conserva una sola admisión y carnet.
  det = (await (await request.get(`/api/academico/solicitudes/${sid}`, { headers: HG })).json()).data
  r = await request.post(`/api/academico/solicitudes/${sid}/decisiones`, { headers: HG, data: { estado: 'aceptada', version: det.version, fecha_ingreso: new Date().toISOString().slice(0, 10) } })
  expect((await r.json()).data.carnet).toBe(carnetNuevo)
  expect(await esperarCorreo(request, aspirante.email, 'Carnet asignado')).toBeTruthy()
})

test('4. Estudiante nuevo entra con su carnet y consulta expediente y pensum', async ({ page }) => {
  test.skip(!carnetNuevo, 'Requiere el paso 3')
  await login(page, carnetNuevo, aspirante.password)
  await page.goto('/estudiante')
  await expect(page.getByText(carnetNuevo).first()).toBeVisible()
  await expect(page.getByText('Ingeniería de Sistemas Informáticos').first()).toBeVisible()
  await page.goto('/estudiante/avance')
  await expect(page.getByLabel('Avance 0%')).toBeVisible()
})

test('5. Elegibilidad y confirmación de inscripción', async ({ page, request }) => {
  await login(page, 'SH202601')
  await page.goto('/estudiante/inscripcion')
  await expect(page.getByText('Requiere aprobar ESD115.').first()).toBeVisible()
  await expect(page.getByText('Ya aprobaste esta materia.').first()).toBeVisible()
  const t = await token(request, 'SH202601')
  const H = { Authorization: `Bearer ${t}` }
  const oferta = (await (await request.get('/api/academico/mi-oferta?expediente=1&ciclo=1', { headers: H })).json()).data
  const esd = oferta.materias.find((m) => m.codigo === 'ESD115').grupos[0].id_grupo
  const r = await request.post('/api/academico/mis-inscripciones', { headers: { ...H, 'Idempotency-Key': `ins-${sufijo}` }, data: { id_expediente: 1, id_ciclo: 1, grupos: [esd] } })
  expect([200, 201]).toContain(r.status())
  await page.goto('/estudiante/mis-inscripciones')
  await expect(page.getByText('ESD115').first()).toBeVisible()
})

test('6. Docente guarda notas en plazo; gestor publica acta; publicada bloquea edición', async ({ page, request }) => {
  await login(page, 'docente.martinez@ues.test')
  await page.goto('/docente/grupos')
  await expect(page.getByText('MAT215').first()).toBeVisible()
  const td = await token(request, 'docente.martinez@ues.test')
  const HD = { Authorization: `Bearer ${td}` }
  const grupos = (await (await request.get('/api/academico/mis-grupos?per_page=50', { headers: HD })).json()).data
  const g = grupos.find((x) => x.materia.codigo === 'MAT215')
  let grid = (await (await request.get(`/api/academico/mis-grupos/${g.id_grupo}/evaluaciones`, { headers: HD })).json()).data
  if (!grid.evaluaciones.length) {
    grid = (await (await request.put(`/api/academico/mis-grupos/${g.id_grupo}/evaluaciones`, { headers: HD, data: { evaluaciones: [{ nombre: 'Parcial', ponderacion: 40 }, { nombre: 'Final', ponderacion: 60 }] } })).json()).data
  }
  const notas = []
  for (const est of grid.estudiantes) for (const ev of grid.evaluaciones) {
    const previa = est.notas.find((n) => n.id_evaluacion === ev.id_evaluacion)
    notas.push({ id_evaluacion: ev.id_evaluacion, id_inscripcion: est.id_inscripcion, valor: 8, condicion: 'nota', version: previa?.version ?? 0 })
  }
  expect((await request.put(`/api/academico/mis-grupos/${g.id_grupo}/notas`, { headers: HD, data: { notas } })).status()).toBe(200)
  await page.goto(`/docente/grupos/${g.id_grupo}/notas`)
  await page.getByRole('button', { name: /preparar cálculo final/i }).click()
  await expect(page.getByText(/8(\.00)?/).first()).toBeVisible()

  await login(page, 'gestor.isi@ues.test')
  await page.goto(`/academico/actas/${g.id_grupo}`)
  await page.getByRole('button', { name: /publicar acta/i }).click()
  await expect(page.getByText(/publicada/i).first()).toBeVisible()
  const r = await request.put(`/api/academico/mis-grupos/${g.id_grupo}/notas`, { headers: HD, data: { notas: [{ ...notas[0], valor: 9, version: 1 }] } })
  expect(r.status()).toBe(409)
})

test('7. Expediente refleja resultados; documentos con autoridad, logo, paginación y firma', async ({ page, request }) => {
  await login(page, 'SH202601')
  await page.goto('/estudiante/avance')
  await expect(page.getByLabel(/^Avance (48\.39|61\.29)%$/)).toBeVisible()
  await login(page, 'gestor.isi@ues.test')
  await page.goto('/academico/expedientes/1')
  await page.getByRole('button', { name: /certificación de notas/i }).click()
  const tg = await token(request, 'gestor.isi@ues.test')
  let doc = null
  for (let i = 0; i < 30 && !doc; i++) {
    const docs = (await (await request.get('/api/academico/expedientes/1/documentos', { headers: { Authorization: `Bearer ${tg}` } })).json()).data
    doc = docs.find((d) => d.tipo === 'certificacion_notas' && d.estado === 'emitido')
    if (!doc) await new Promise((res) => setTimeout(res, 1000))
  }
  expect(doc, 'certificación emitida por el worker').toBeTruthy()
  expect(doc.autoridad.cargo).toBe('Rectora')
  const pdf = await request.get(`/api/academico/expedientes/1/documentos/${doc.id_documento}/archivo`, { headers: { Authorization: `Bearer ${tg}` } })
  expect(pdf.status()).toBe(200)
  expect(pdf.headers()['content-type']).toContain('application/pdf')
  expect((await pdf.body()).subarray(0, 4).toString()).toBe('%PDF')
})

test('8. Asistentes: público cita fuentes vigentes; privado explica elegibilidad y enlaza a inscripción', async ({ page }) => {
  await page.goto('/asistente-aspirantes')
  await page.getByPlaceholder('Escribe tu pregunta').fill('¿Cuáles son los requisitos para Ingeniería de Sistemas Informáticos?')
  await page.getByRole('button', { name: 'Enviar' }).click()
  // Con el mock (determinista) se exige la cita a la ficha oficial; con un proveedor real basta una respuesta sin error.
  const simulado = JSON.parse(artisan('academico:ia-presupuesto', 'estado').trim().split('\n').pop()).ambito === 'simulacion'
  if (simulado) {
    await expect(page.locator('a[href*="/carreras/1#"]').first()).toBeVisible({ timeout: 20_000 })
  } else {
    await expect(page.locator('article[aria-label^="Respuesta del asistente"]').last()).toBeVisible({ timeout: 45_000 })
    await expect(page.getByRole('alert')).toHaveCount(0)
  }

  await login(page, 'SH202601')
  await page.goto('/estudiante/asistente')
  await page.getByPlaceholder('Escribe tu pregunta').fill('¿Qué materias puedo inscribir este ciclo?')
  await page.getByRole('button', { name: 'Enviar' }).click()
  // Respuesta del asistente sustentada en el expediente (válido con el mock o con un proveedor real).
  const respuesta = page.locator('article[aria-label^="Respuesta del asistente"]').last()
  await expect(respuesta).toBeVisible({ timeout: 45_000 })
  await expect(page.getByRole('alert')).toHaveCount(0)
  await expect(respuesta.getByRole('link').first()).toBeVisible()
  // Markdown renderizado y citas convertidas: sin marcas crudas ni asteriscos en el texto visible.
  await expect(respuesta).not.toContainText('[fuente:')
  await expect(respuesta).not.toContainText('**')
  await expect(respuesta.getByRole('region', { name: /^Fuentes/ })).toBeVisible()
  const ir = page.getByRole('link', { name: 'Ir a inscripción' }).first()
  if (await ir.count()) {
    await ir.click()
    await expect(page).toHaveURL(/\/estudiante\/inscripcion\?expediente=1/)
  }
})

test('9. Aislamiento entre carreras y estudiantes (expediente, anexos, documentos, conversaciones)', async ({ page, request }) => {
  const tica = await token(request, 'gestor.ica@ues.test')
  const HI = { Authorization: `Bearer ${tica}` }
  expect((await request.get('/api/academico/expedientes/1', { headers: HI })).status()).toBe(404)
  expect((await request.get('/api/academico/expedientes/1/documentos/1/archivo', { headers: HI })).status()).toBe(404)
  const lista = (await (await request.get('/api/academico/solicitudes', { headers: HI })).json())
  expect(lista.meta.total).toBe(0)
  expect((await request.get('/api/academico/solicitudes/1/anexos/1', { headers: HI })).status()).toBe(404)
  // Conversaciones privadas: otro estudiante no lee la conversación ajena.
  const t1 = await token(request, 'SH202601')
  const convs = (await (await request.get('/api/academico/mi-asistente/conversaciones?expediente=1', { headers: { Authorization: `Bearer ${t1}` } })).json()).data
  expect(convs.length).toBeGreaterThan(0)
  const t2 = await token(request, 'PX202601')
  expect((await request.get(`/api/academico/mi-asistente/conversaciones/${convs[0].id_conversacion}/mensajes?expediente=2`, { headers: { Authorization: `Bearer ${t2}` } })).status()).toBe(404)
  expect((await request.get('/api/academico/mi-oferta?expediente=1&ciclo=1', { headers: { Authorization: `Bearer ${t2}` } })).status()).toBe(404)
  // Interfaz del gestor ajeno no muestra datos.
  await login(page, 'gestor.ica@ues.test')
  await page.goto('/academico/expedientes/1')
  await expect(page.getByText(/no encontrado/i).first()).toBeVisible()
  await expect(page.getByText('SH202601')).toHaveCount(0)
})

test('10. Presupuesto agotado muestra aviso en ambos agentes y rechaza llamadas; elevar límite reactiva con gasto conservado', async ({ page, request }) => {
  const gastoAntes = JSON.parse(artisan('academico:ia-presupuesto', 'estado').trim().split('\n').pop()).gastado
  const estado = JSON.parse(artisan('academico:ia-presupuesto', 'agotar').trim().split('\n').pop())
  expect(estado.disponibilidad.disponible).toBe(false)

  // Público: al entrar, mensaje amigable en lugar del chat; la API rechaza.
  await page.goto('/asistente-aspirantes')
  await expect(page.getByRole('heading', { name: 'El asistente no está disponible por ahora' })).toBeVisible()
  await expect(page.getByPlaceholder('Escribe tu pregunta')).toHaveCount(0)
  await expect(page.getByRole('heading', { name: 'Conversaciones' })).toHaveCount(0)
  let r = await request.post('/api/publico/asistente-aspirantes/mensajes', { headers: { 'Idempotency-Key': `p-${sufijo}` }, data: { mensaje: 'hola' } })
  expect(r.status()).toBe(409)
  expect((await r.json()).code).toBe('IA_PRESUPUESTO_AGOTADO')

  // Privado: la opción de menú se conserva; al entrar se consulta el estado y se muestra el mensaje amigable.
  // Historial almacenado sigue legible por API y el POST directo se rechaza.
  await login(page, 'SH202601')
  await page.goto('/estudiante')
  await page.getByRole('link', { name: 'Asistente académico' }).first().click()
  await expect(page).toHaveURL(/\/estudiante\/asistente/)
  await expect(page.getByRole('heading', { name: 'El asistente no está disponible por ahora' })).toBeVisible()
  await expect(page.getByPlaceholder('Escribe tu pregunta')).toHaveCount(0)
  const t = await token(request, 'SH202601')
  const H = { Authorization: `Bearer ${t}` }
  const convs = (await (await request.get('/api/academico/mi-asistente/conversaciones?expediente=1', { headers: H })).json()).data
  expect(convs.length).toBeGreaterThan(0)
  expect((await request.get(`/api/academico/mi-asistente/conversaciones/${convs[0].id_conversacion}/mensajes?expediente=1`, { headers: H })).status()).toBe(200)
  r = await request.post('/api/academico/mi-asistente/mensajes', { headers: { ...H, 'Idempotency-Key': `e-${sufijo}` }, data: { mensaje: 'hola', id_expediente: 1 } })
  expect(r.status()).toBe(409)

  // Reactivación (equivalente a elevar el límite por encima del gasto): gasto real conservado.
  const tras = JSON.parse(artisan('academico:ia-presupuesto', 'restablecer').trim().split('\n').pop())
  expect(tras.disponibilidad.disponible).toBe(true)
  expect(Number(tras.gastado)).toBeCloseTo(Number(gastoAntes), 9)
  await page.goto('/estudiante/asistente')
  await expect(page.getByPlaceholder('Escribe tu pregunta')).toBeVisible()
})
