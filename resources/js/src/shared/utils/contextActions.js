import { toast } from 'vue3-toastify'

async function copyToClipboard(message) {
  if (!message) return
  try {
    const canWriteClipboard =
      typeof navigator !== 'undefined' && typeof navigator.clipboard?.writeText === 'function'
    if (!canWriteClipboard) {
      throw new Error('Clipboard API no disponible')
    }
    await navigator.clipboard.writeText(message)
    toast.success('Mensaje copiado al portapapeles')
  } catch (error) {
    toast.error(error?.message || 'No se pudo copiar el mensaje')
  }
}

export { copyToClipboard }
