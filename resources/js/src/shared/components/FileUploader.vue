<template>
  <div class="file-uploader" :class="{ 'file-uploader--disabled': disabled }">
    <div
      ref="dropZoneRef"
      class="file-uploader__dropzone"
      :class="{
        'file-uploader__dropzone--active': isOverDropZone && !disabled,
        'file-uploader__dropzone--filled': selectedFiles.length
      }"
      role="region"
      :aria-label="title"
      :aria-disabled="disabled"
      @click="openDialog"
    >
      <Icon
        class="file-uploader__upload-icon"
        :name="isOverDropZone ? 'file_download' : 'cloud_upload'"
        :font-size="22"
        aria-hidden="true"
      />

      <div class="file-uploader__copy">
        <p class="file-uploader__title">
          {{ isOverDropZone ? dropTitle : title }}
        </p>
        <p class="file-uploader__description">{{ description }}</p>
      </div>

      <button
        type="button"
        class="file-uploader__action"
        :disabled="disabled"
        @click.stop="openDialog"
      >
        {{ actionLabel }}
      </button>
    </div>

    <p class="file-uploader__screen-reader-status" aria-live="polite">
      {{ selectedFiles.length }} {{ selectedFiles.length === 1 ? 'archivo seleccionado' : 'archivos seleccionados' }}
    </p>

    <ul v-if="selectedFiles.length" class="file-uploader__file-list" aria-label="Archivos seleccionados">
      <li v-for="(file, index) in selectedFiles" :key="fileKey(file, index)" class="file-uploader__file">
        <div class="file-uploader__file-type" aria-hidden="true">
          <img
            v-if="previewUrl && previewFile === file"
            :src="previewUrl"
            :alt="file.name"
          />
          <span v-else class="file-uploader__document-icon">
            <Icon :name="fileIcon(file)" :font-size="21" />
            <span v-if="isPdf(file)" class="file-uploader__document-badge">PDF</span>
          </span>
        </div>

        <div class="file-uploader__file-copy">
          <div class="file-uploader__file-header">
            <strong :title="file.name">{{ file.name }}</strong>
            <button
              type="button"
              class="file-uploader__remove"
              :aria-label="`Quitar ${file.name}`"
              :disabled="disabled"
              @click="removeFile(index)"
            >
              <Icon name="delete_outline" :font-size="17" />
            </button>
          </div>
          <div class="file-uploader__file-status">
            <span>{{ formatBytes(file.size) }} de {{ formatBytes(file.size) }} ·</span>
            <span class="file-uploader__completed-status">
              <Icon name="check_circle" :font-size="14" />
              {{ completedLabel }}
            </span>
          </div>
        </div>
      </li>
    </ul>

    <div v-if="errors.length" class="file-uploader__errors" role="alert">
      <p v-for="error in errors" :key="`${error.file.name}-${error.reason}`">
        <Icon name="error" :font-size="18" />
        <span><strong>{{ error.file.name }}</strong>: {{ error.reason }}</span>
      </p>
    </div>

  </div>
</template>

<script setup>
import { computed, ref, toRef, watch } from 'vue'
import { useDropZone, useFileDialog, useObjectUrl } from '@vueuse/core'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  accept: {
    type: String,
    default: ''
  },
  multiple: {
    type: Boolean,
    default: false
  },
  maxFiles: {
    type: Number,
    default: null
  },
  maxSize: {
    type: Number,
    default: null
  },
  disabled: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Carga tus archivos'
  },
  dropTitle: {
    type: String,
    default: 'Suelta los archivos aquí'
  },
  description: {
    type: String,
    default: 'Arrástralos aquí o selecciónalos desde tu dispositivo.'
  },
  actionLabel: {
    type: String,
    default: 'Elegir archivos'
  },
  completedLabel: {
    type: String,
    default: 'Completed'
  }
})

const emit = defineEmits(['update:modelValue', 'change', 'invalid', 'remove', 'clear'])

const dropZoneRef = ref(null)
const selectedFiles = ref(normalizeFiles(props.modelValue))
const errors = ref([])

const { open, reset, onChange } = useFileDialog({
  accept: toRef(props, 'accept'),
  multiple: toRef(props, 'multiple'),
  reset: true
})

const { isOverDropZone } = useDropZone(dropZoneRef, {
  multiple: props.multiple,
  onDrop: (files) => handleIncomingFiles(files)
})

const previewFile = computed(() =>
  selectedFiles.value.find((file) => file.type?.startsWith('image/')) || null
)
const previewUrl = useObjectUrl(previewFile)
const maxSize = computed(() => props.maxSize && props.maxSize > 0 ? props.maxSize : null)

onChange((fileList) => handleIncomingFiles(fileList))

watch(
  () => props.modelValue,
  (value) => {
    const nextFiles = normalizeFiles(value)
    if (!sameFiles(selectedFiles.value, nextFiles)) selectedFiles.value = nextFiles
  },
  { deep: true }
)

function normalizeFiles(value) {
  if (!value) return []
  return Array.from(value).filter((file) =>
    typeof File === 'undefined' ? file && typeof file.name === 'string' : file instanceof File
  )
}

function sameFiles(first, second) {
  return first.length === second.length && first.every((file, index) => fileKey(file) === fileKey(second[index]))
}

function fileKey(file, index = 0) {
  return `${file.name}-${file.size}-${file.lastModified}-${index}`
}

function openDialog() {
  if (!props.disabled) open()
}

function handleIncomingFiles(value) {
  if (props.disabled) return

  const incomingFiles = uniqueFiles(normalizeFiles(value))
  if (!incomingFiles.length) return

  const previousFiles = selectedFiles.value
  const nextFiles = props.multiple ? [...previousFiles] : []
  const rejectedFiles = []
  const limit = props.multiple && props.maxFiles > 0 ? props.maxFiles : props.multiple ? null : 1

  incomingFiles.forEach((file) => {
    if (nextFiles.some((selectedFile) => fileKey(selectedFile) === fileKey(file))) return

    const reason = validateFile(file)
    if (reason) {
      rejectedFiles.push({ file, reason })
      return
    }

    if (limit && nextFiles.length >= limit) {
      rejectedFiles.push({ file, reason: `Solo puedes seleccionar ${limit} archivos.` })
      return
    }

    nextFiles.push(file)
  })

  errors.value = rejectedFiles
  if (rejectedFiles.length) emit('invalid', rejectedFiles)

  if (nextFiles.length && !sameFiles(previousFiles, nextFiles)) {
    updateSelection(nextFiles)
  }
}

function uniqueFiles(files) {
  return files.filter((file, index, allFiles) =>
    allFiles.findIndex((candidate) => fileKey(candidate) === fileKey(file)) === index
  )
}

function validateFile(file) {
  if (props.accept && !matchesAccept(file, props.accept)) return 'El formato no está permitido.'
  if (maxSize.value && file.size > maxSize.value) return `Supera el límite de ${formatBytes(maxSize.value)}.`
  return null
}

function matchesAccept(file, accept) {
  const fileName = file.name.toLowerCase()
  const fileType = (file.type || '').toLowerCase()

  return accept.split(',').some((rawToken) => {
    const token = rawToken.trim().toLowerCase()
    if (!token) return false
    if (token === '*' || token === '*/*') return true
    if (token.startsWith('.')) return fileName.endsWith(token)
    if (token.endsWith('/*')) return fileType.startsWith(token.slice(0, -1))
    return fileType === token
  })
}

function isPdf(file) {
  return file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')
}

function fileIcon(file) {
  if (file.type.startsWith('image/')) return 'image'
  if (file.type.startsWith('video/')) return 'movie'
  return 'description'
}

function updateSelection(files) {
  selectedFiles.value = files
  emit('update:modelValue', files)
  emit('change', files)
}

function removeFile(index) {
  const [removedFile] = selectedFiles.value.slice(index, index + 1)
  const nextFiles = selectedFiles.value.filter((_, fileIndex) => fileIndex !== index)
  errors.value = []
  updateSelection(nextFiles)
  emit('remove', removedFile)
}

function clearFiles() {
  selectedFiles.value = []
  errors.value = []
  reset()
  emit('update:modelValue', [])
  emit('change', [])
  emit('clear')
}

function formatBytes(bytes) {
  if (bytes === 0) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  const unitIndex = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)
  const value = bytes / (1024 ** unitIndex)
  return `${value < 10 && unitIndex > 0 ? value.toFixed(1) : Math.round(value)} ${units[unitIndex]}`
}

defineExpose({ clear: clearFiles, open: openDialog, remove: removeFile })
</script>

<style scoped>
.file-uploader {
  position: relative;
  color: var(--color-text);
}

.file-uploader__dropzone {
  display: flex;
  min-height: 9.8rem;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  padding: 1.2rem;
  border: 1px dashed color-mix(in srgb, var(--color-secondary-100) 82%, var(--color-secondary));
  border-radius: 0.7rem;
  background: var(--color-surface-card);
  text-align: center;
  cursor: pointer;
  transition: border-color 180ms ease, background 180ms ease, box-shadow 180ms ease;
}

.file-uploader__dropzone:hover,
.file-uploader__dropzone:focus-within,
.file-uploader__dropzone--active {
  border-color: var(--color-brand-200);
  background: color-mix(in srgb, var(--color-brand-hover) 46%, var(--color-surface-card));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-brand-200) 10%, transparent);
}

.file-uploader__upload-icon {
  color: var(--color-secondary);
  margin-bottom: 0.35rem;
}

.file-uploader__copy {
  display: grid;
  gap: 0.2rem;
  min-width: 0;
  max-width: 19rem;
}

.file-uploader__title,
.file-uploader__description,
.file-uploader__file-copy strong,
.file-uploader__file-copy span,
.file-uploader__errors p {
  margin: 0;
}

.file-uploader__title {
  color: var(--color-text);
  font-size: 0.78rem;
  font-weight: 600;
  line-height: 1.35;
}

.file-uploader__description {
  color: var(--color-secondary);
  font-size: 0.68rem;
  line-height: 1.4;
}

.file-uploader__action {
  margin-top: 0.4rem;
  padding: 0.42rem 0.7rem;
  border: 1px solid var(--color-secondary-100);
  border-radius: 0.35rem;
  color: var(--color-text);
  background: var(--color-surface-card);
  font: inherit;
  font-size: 0.72rem;
  line-height: 1.2;
  cursor: pointer;
  box-shadow: 0 1px 2px color-mix(in srgb, var(--color-text) 8%, transparent);
  transition: border-color 180ms ease, color 180ms ease, box-shadow 180ms ease;
}

.file-uploader__action:hover:not(:disabled) {
  border-color: var(--color-brand-200);
  color: var(--color-brand-200);
  box-shadow: 0 2px 5px color-mix(in srgb, var(--color-brand-200) 13%, transparent);
}

.file-uploader__screen-reader-status {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.file-uploader__file-list {
  display: grid;
  gap: 0.6rem;
  margin: 0.7rem 0 0;
  padding: 0;
  list-style: none;
}

.file-uploader__file {
  display: grid;
  grid-template-columns: 2.1rem minmax(0, 1fr);
  align-items: center;
  gap: 0.65rem;
  padding: 0.62rem 0.7rem;
  border: 1px solid var(--color-secondary-100);
  border-radius: 0.62rem;
  background: var(--color-surface-card);
  box-shadow: 0 1px 2px color-mix(in srgb, var(--color-text) 5%, transparent);
}

.file-uploader__file-type {
  display: grid;
  place-items: center;
  width: 2.1rem;
  height: 2.2rem;
}

.file-uploader__file-type img {
  width: 1.9rem;
  height: 2.15rem;
  border: 1px solid var(--color-secondary-100);
  border-radius: 0.25rem;
  object-fit: cover;
}

.file-uploader__document-icon {
  position: relative;
  display: grid;
  place-items: center;
  width: 1.85rem;
  height: 2.15rem;
  border: 1px solid var(--color-secondary-100);
  border-radius: 0.25rem;
  color: var(--color-secondary-100);
  background: var(--color-surface-card);
}

.file-uploader__document-badge {
  position: absolute;
  right: 0.1rem;
  bottom: 0.25rem;
  left: 0.1rem;
  padding: 0.08rem 0.12rem;
  border: 1px solid #f24d62;
  border-radius: 0.12rem;
  color: #f24d62;
  background: var(--color-surface-card);
  font-size: 0.43rem;
  font-weight: 800;
  line-height: 1;
  text-align: center;
}

.file-uploader__file-copy {
  display: grid;
  min-width: 0;
  gap: 0.16rem;
}

.file-uploader__file-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.4rem;
}

.file-uploader__file-copy strong {
  overflow: hidden;
  text-overflow: ellipsis;
  color: var(--color-text);
  font-size: 0.73rem;
  font-weight: 500;
  line-height: 1.25;
  white-space: nowrap;
}

.file-uploader__file-status {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.18rem;
  color: var(--color-secondary);
  font-size: 0.62rem;
  line-height: 1.2;
}

.file-uploader__completed-status {
  display: inline-flex;
  align-items: center;
  gap: 0.15rem;
  color: var(--color-success);
}

.file-uploader__remove {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  border: 0;
  border-radius: 0.35rem;
  color: var(--color-secondary);
  background: transparent;
  cursor: pointer;
}

.file-uploader__remove:hover:not(:disabled) {
  color: var(--color-danger);
  background: color-mix(in srgb, var(--color-danger) 10%, transparent);
}

.file-uploader__errors {
  display: grid;
  gap: 0.35rem;
  margin-top: 0.65rem;
  padding: 0.6rem 0.7rem;
  border-left: 3px solid var(--color-danger);
  color: var(--color-danger);
  background: color-mix(in srgb, var(--color-danger) 8%, transparent);
}

.file-uploader__errors p {
  display: flex;
  align-items: flex-start;
  gap: 0.35rem;
  font-size: 0.78rem;
}

.file-uploader--disabled {
  opacity: 0.6;
}

.file-uploader--disabled .file-uploader__dropzone {
  cursor: not-allowed;
}

.file-uploader button:disabled {
  cursor: not-allowed;
}
</style>
