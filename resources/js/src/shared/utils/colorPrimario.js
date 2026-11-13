const BLANCO = '#FFFFFF'
const NEGRO = '#000000'
const TEXTO_OSCURO = '#111318'
const SUPERFICIE_CLARA = '#FAFAFA'
const SUPERFICIE_OSCURA = '#0D0F1A'
const TARJETA_OSCURA = '#161A2C'
const BARRA_LATERAL_OSCURA = '#1C1F26'
const PATRON_HEX = /^#[0-9a-f]{6}$/i
const PASO_LUMINOSIDAD = 0.02

export const CONTRASTE_MINIMO = 4.5

/**
 * Indica si un texto es un color hexadecimal de seis dígitos con almohadilla.
 *
 * @param {string} valor Texto a evaluar.
 * @returns {boolean} Verdadero si es `#RRGGBB`.
 */
export function esColorHex(valor) {
  return PATRON_HEX.test(String(valor ?? ''))
}

/**
 * Normaliza un color a mayúsculas con almohadilla.
 *
 * @param {string} valor Color con o sin almohadilla.
 * @returns {string} Color `#RRGGBB` o cadena vacía si no es válido.
 */
export function normalizarHex(valor) {
  const texto = String(valor ?? '').trim()
  const conAlmohadilla = texto.startsWith('#') ? texto : `#${texto}`
  return esColorHex(conAlmohadilla) ? conAlmohadilla.toUpperCase() : ''
}

function aRgb(hex) {
  return [1, 3, 5].map((inicio) => Number.parseInt(hex.slice(inicio, inicio + 2), 16))
}

function aHex([rojo, verde, azul]) {
  return `#${[rojo, verde, azul]
    .map((canal) => Math.round(Math.min(255, Math.max(0, canal))).toString(16).padStart(2, '0'))
    .join('')}`.toUpperCase()
}

function luminancia(hex) {
  const [rojo, verde, azul] = aRgb(hex).map((canal) => {
    const valor = canal / 255
    return valor <= 0.03928 ? valor / 12.92 : ((valor + 0.055) / 1.055) ** 2.4
  })
  return 0.2126 * rojo + 0.7152 * verde + 0.0722 * azul
}

/**
 * Relación de contraste WCAG entre dos colores.
 *
 * @param {string} primero Color `#RRGGBB`.
 * @param {string} segundo Color `#RRGGBB`.
 * @returns {number} Relación entre 1 y 21.
 */
export function contraste(primero, segundo) {
  const [mayor, menor] = [luminancia(primero), luminancia(segundo)].sort((a, b) => b - a)
  return (mayor + 0.05) / (menor + 0.05)
}

/**
 * Indica si el texto blanco se lee sobre el color, requisito para usarlo como primario.
 *
 * @param {string} hex Color `#RRGGBB`.
 * @returns {boolean} Verdadero si el contraste con blanco alcanza el mínimo.
 */
export function legibleConBlanco(hex) {
  return esColorHex(hex) && contraste(hex, BLANCO) >= CONTRASTE_MINIMO
}

/** Mezcla dos colores; `peso` es la proporción del primero */
function mezclar(primero, segundo, peso) {
  const [a, b] = [aRgb(primero), aRgb(segundo)]
  return aHex(a.map((canal, indice) => canal * peso + b[indice] * (1 - peso)))
}

function aHsl(hex) {
  const [rojo, verde, azul] = aRgb(hex).map((canal) => canal / 255)
  const maximo = Math.max(rojo, verde, azul)
  const minimo = Math.min(rojo, verde, azul)
  const luminosidad = (maximo + minimo) / 2
  const delta = maximo - minimo
  if (delta === 0) return [0, 0, luminosidad]
  const saturacion = delta / (1 - Math.abs(2 * luminosidad - 1))
  let tono
  if (maximo === rojo) tono = ((verde - azul) / delta) % 6
  else if (maximo === verde) tono = (azul - rojo) / delta + 2
  else tono = (rojo - verde) / delta + 4
  return [(tono * 60 + 360) % 360, saturacion, luminosidad]
}

function deHsl(tono, saturacion, luminosidad) {
  const croma = (1 - Math.abs(2 * luminosidad - 1)) * saturacion
  const auxiliar = croma * (1 - Math.abs(((tono / 60) % 2) - 1))
  const desfase = luminosidad - croma / 2
  const sector = Math.floor(tono / 60) % 6
  const [rojo, verde, azul] = [
    [croma, auxiliar, 0],
    [auxiliar, croma, 0],
    [0, croma, auxiliar],
    [0, auxiliar, croma],
    [auxiliar, 0, croma],
    [croma, 0, auxiliar]
  ][sector]
  return aHex([(rojo + desfase) * 255, (verde + desfase) * 255, (azul + desfase) * 255])
}

/** Un fondo puede ser un color fijo o una función del color candidato */
function seLeeSobre(color, fondos) {
  return fondos.every((fondo) => contraste(color, typeof fondo === 'function' ? fondo(color) : fondo) >= CONTRASTE_MINIMO)
}

/** Aclara el color hasta que se lea sobre todos los fondos indicados */
function aclararHasta(hex, ...fondos) {
  const [tono, saturacion, inicial] = aHsl(hex)
  let luminosidad = inicial
  let actual = hex
  while (!seLeeSobre(actual, fondos) && luminosidad < 1) {
    luminosidad = Math.min(1, luminosidad + PASO_LUMINOSIDAD)
    actual = deHsl(tono, saturacion, luminosidad)
  }
  return actual
}

/** Oscurece el color hasta que se lea sobre todos los fondos indicados */
function oscurecerHasta(hex, ...fondos) {
  const [tono, saturacion, inicial] = aHsl(hex)
  let luminosidad = inicial
  let actual = hex
  while (!seLeeSobre(actual, fondos) && luminosidad > 0) {
    luminosidad = Math.max(0, luminosidad - PASO_LUMINOSIDAD)
    actual = deHsl(tono, saturacion, luminosidad)
  }
  return actual
}

function mejorTexto(fondo) {
  return contraste(fondo, BLANCO) >= contraste(fondo, TEXTO_OSCURO) ? BLANCO : TEXTO_OSCURO
}

/**
 * Variables de la paleta de la aplicación derivadas del color primario.
 *
 * @param {string} hex Color primario `#RRGGBB` legible con texto blanco.
 * @param {boolean} oscuro Verdadero para la paleta de modo oscuro.
 * @returns {Record<string, string>} Variables CSS `--color-*` de la escala de marca.
 */
export function variablesPrimario(hex, oscuro) {
  if (!oscuro) {
    const menuActivo = mezclar(hex, BLANCO, 0.1)
    const enlace = oscurecerHasta(hex, SUPERFICIE_CLARA, menuActivo)
    return {
      '--color-brand': hex,
      '--color-brand-50': mezclar(hex, BLANCO, 0.06),
      '--color-brand-100': mezclar(hex, BLANCO, 0.14),
      '--color-brand-200': mezclar(hex, BLANCO, 0.62),
      '--color-brand-active': mezclar(hex, NEGRO, 0.72),
      '--color-brand-hover': mezclar(hex, BLANCO, 0.05),
      '--color-brand-button-hover': mezclar(hex, NEGRO, 0.88),
      '--color-brand-secondary': mezclar(hex, BLANCO, 0.05),
      '--color-brand-contrast': BLANCO,
      '--color-link': enlace,
      '--color-sidebar-active': mezclar(hex, BLANCO, 0.1),
      '--color-loading-spinner': hex
    }
  }

  const marca = aclararHasta(
    hex,
    SUPERFICIE_OSCURA,
    TARJETA_OSCURA,
    (candidato) => mezclar(candidato, BARRA_LATERAL_OSCURA, 0.18)
  )
  return {
    '--color-brand': marca,
    '--color-brand-50': mezclar(marca, SUPERFICIE_OSCURA, 0.12),
    '--color-brand-100': mezclar(marca, SUPERFICIE_OSCURA, 0.2),
    '--color-brand-200': mezclar(marca, BLANCO, 0.75),
    '--color-brand-active': mezclar(marca, BLANCO, 0.82),
    '--color-brand-hover': mezclar(marca, TARJETA_OSCURA, 0.1),
    '--color-brand-button-hover': mezclar(marca, BLANCO, 0.9),
    '--color-brand-secondary': mezclar(marca, SUPERFICIE_OSCURA, 0.1),
    '--color-brand-contrast': mejorTexto(marca),
    '--color-link': marca,
    '--color-sidebar-active': mezclar(marca, BARRA_LATERAL_OSCURA, 0.18),
    '--color-loading-spinner': marca
  }
}
