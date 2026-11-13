import { VerifyDuiFtn, VerifyNitFtn } from './document-validations'

export const required = (value, message = 'Este campo es requerido.') => {
  return value ? true : message
}

export const email = (value, message = 'Debe ingresar un e-mail valido.') => {
  const regEmail = /^([a-zA-Z0-9._+-]+)@([a-zA-Z0-9-]+)(\.[a-zA-Z0-9]+)+$/
  if (!value) return true
  return regEmail.test(value) ? true : message
}

export const customEmail = (value, message, custom) => {
  const msg = message || 'Debe ingresar un e-mail válido.'
  const domain = custom || ''
  const regEmail = /^([a-zA-Z0-9._+-]+)@([a-zA-Z0-9-]+)(\.[a-zA-Z0-9]+)+$/
  if (!value) return true
  if (!regEmail.test(value)) return msg
  if (domain && value.includes(domain)) return true
  return msg
}

export const minlength = (value, message, length) => {
  const msg = message || `Debe ingresar al menos ${length} caracteres.`
  if (!value) return true
  return value.length > length ? true : msg
}

export const maxlength = (value, message, length) => {
  const msg = message || `Debe ingresar menos de ${length} caracteres.`
  if (!value) return true
  return value.length < length ? true : msg
}

export const textfield = (value, message = 'Debe ingresar solo letras.') => {
  const regText = /^[A-Za-zñÑáéíóúÁÉÍÓÚüÜ0-9 -]*$/
  if (!value) return true
  return regText.test(value) ? true : message
}

export const textarea = (value, message = 'Debe ingresar solo letras.') => {
  const regText = /^[A-Za-zñÑáéíóúÁÉÍÓÚüÜ0-9 ()/.,\n:;-]*$/
  if (!value) return true
  return regText.test(value) ? true : message
}

export const url = (value, message = 'Debe ingresar una url valida.') => {
  const regUrl = /^(https?:\/\/(www\.)?)?[a-z\d]+([-.][a-z\d]+)*\.[a-z]{2,5}(:\d{1,5})?(\/.*)?$/i
  if (!value) return true
  return regUrl.test(value) ? true : message
}

export const number = (value, message = 'Debe ingresar solo numeros.') => {
  const regNumber = /^\d*$/
  if (!value) return true
  return regNumber.test(value) ? true : message
}

export const phone = (value, message = 'Debe ingresar un teléfono valido(####-####)') => {
  const regPhone = /^\(?\d{4}\)?-+?\d{4}$/
  if (!value) return true
  const first = value.charAt(0)
  if (first === '2' || first === '6' || first === '7') {
    return regPhone.test(value) ? true : message
  }
  return "El teléfono debe iniciar con un '2, 6 o 7'"
}

export const requiredIf = (value, message, condition) => {
  const msg = message || 'Este campo es requerido.'
  if (!condition) return true
  return value ? true : msg
}

export const regex = (value, message, pattern) => {
  const msg = message || 'Debe ingresar un formato valido.'
  if (!value) return true
  if (!(pattern instanceof RegExp)) return msg
  return pattern.test(value) ? true : msg
}

export const equal = (value, message, expected) => {
  const msg = message || 'Los campos no coinciden.'
  if (!value) return true
  return value === expected ? true : msg
}

export const isPdf = (value, message = 'Debe ingresar un archivo PDF.', size = 10000000) => {
  const file = Array.isArray(value) ? value[0] : value
  const regAlfaNumPdf = /^[a-zA-Z0-9ñÑáéíóúÁÉÍÓÚ_\s-]+(?=\.pdf$)/
  if (!file) return true
  if (file.type !== 'application/pdf') return message
  if (file.size > size) return 'El archivo no debe pesar mas de 10MB.'
  if (!regAlfaNumPdf.test(file.name)) return 'El nombre del archivo no debe contener caracteres especiales.'
  return true
}

export const isDui = (value, message = 'Debe ingresar un DUI valido.') => {
  if (!value) return true
  return VerifyDuiFtn(value) ? true : message
}

export const isNit = (value, message = 'Debe ingresar un NIT valido.') => {
  if (!value) return true
  return VerifyNitFtn(value) ? true : message
}
