import http from '@/shared/services/http'

const login = async ({ identifier, password }) => {
  return await http.post('/auth/login', { identifier, password })
}

const logout = async () => {
  return await http.post('/auth/logout')
}

const solicitarRecuperacionCuenta = async ({ email }) => {
  return await http.post('/auth/request-password-recovery', { email })
}

const validarCodigoRecuperacion = async ({ email, token }) => {
  return await http.post('/auth/validate-recovery-token', { email, token })
}

const validarCodigoDobleFactor = async ({ identifier, token }) => {
  return await http.post('/auth/validate-2fa-token', { identifier, token })
}

const cambiarPassword = async ({ current_password, new_password, password_confirmation }) => {
  return await http.post('/auth/change-password', {
    current_password,
    new_password,
    password_confirmation
  })
}

export default {
  login,
  logout,
  solicitarRecuperacionCuenta,
  validarCodigoRecuperacion,
  validarCodigoDobleFactor,
  cambiarPassword,
}
