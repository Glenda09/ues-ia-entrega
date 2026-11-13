import dayjs from 'dayjs'

const cropText = (text, maxLength = 100) => {
  if (text.length <= maxLength) return text
  return text.slice(0, maxLength) + '...'
}

function formatDate(value, format = 'DD/MM/YYYY') {
  if (!value || !dayjs) return ''
  const formatString = format.replace('HH:mm', 'hh:mm a')
  return dayjs(value).format(formatString)
}

export { cropText, formatDate }
