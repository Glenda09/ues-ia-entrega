import http from '@/shared/services/http'

const BASE_URL = '/system-logs'
const REQUEST_LOGS_URL = '/request-logs'

function parsePaginatedResponse(payload, fallback = {}) {
  const list = Array.isArray(payload?.data) ? payload.data : []
  const {
    page = 1,
    per_page: fallbackPerPage = 10,
    total: fallbackTotal = list.length,
    last_page: fallbackLastPage = 1
  } = fallback

  const meta = {
    current_page: Number(payload?.current_page ?? page),
    per_page: Number(payload?.per_page ?? fallbackPerPage),
    total: Number(payload?.total ?? fallbackTotal),
    last_page: Number(payload?.last_page ?? fallbackLastPage)
  }

  return { data: list, meta }
}

async function listar({
  level,
  context,
  channel,
  datetime,
  order = 'desc',
  per_page = 10,
  page = 1
} = {}) {
  const params = {
    page,
    per_page,
    order
  }

  if (level) params.level = level
  if (context) params.context = context
  if (channel) params.channel = channel
  if (datetime) params.datetime = datetime

  const { data } = await http.get(BASE_URL, { params, toastSuccess: false })
  const payload = data?.data

  return parsePaginatedResponse(payload, { page, per_page })
}

async function listarRequestLogs({
  uri,
  type,
  creation_date,
  order = 'desc',
  per_page = 10,
  page = 1
} = {}) {
  const params = {
    page,
    per_page,
    order
  }

  if (uri) params.uri = uri
  if (type) params.type = type
  if (creation_date) params.creation_date = creation_date

  const { data } = await http.get(REQUEST_LOGS_URL, { params, toastSuccess: false })
  const payload = data?.data

  return parsePaginatedResponse(payload, { page, per_page })
}

export default { listar, listarRequestLogs }
