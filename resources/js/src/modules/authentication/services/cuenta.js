import http from '@/shared/services/http'

const cuenta = async () => {
  return await http.get('/cuenta')
}

const setup2FA = async () => {
  return await http.post('/cuenta/2fa/setup')
}

const update2FA = async ({ mode, token }) => {
  return await http.put('/cuenta/2fa', { mode, ...(token ? { token } : {}) })
}

export default {
  cuenta,
  setup2FA,
  update2FA,
}
