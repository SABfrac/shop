<template>
  <div class="feed-upload-page">
    <div class="page-header">
      <div>
        <h1>Импорт товаров</h1>
        <p class="subtitle">Массовая загрузка товаров и обновление цен</p>
      </div>
      <router-link to="/vendor/dashboard" class="btn-secondary">← В кабинет</router-link>
    </div>

    <div class="content-grid">
      <!-- ЛЕВАЯ КОЛОНКА: Загрузка -->
      <div class="main-column">
        <!-- ШАГ 1: Категория -->
        <div class="card step-card">
          <div class="step-header">
            <div class="step-number">1</div>
            <h3>Выберите категорию</h3>
          </div>
          <div class="step-content">
            <CategorySelector
                v-model="selectedCategoryId"
                :fetcher="fetchCategories"
                :root-parent-id="null"
                @category-selected="onCategorySelected"
            />

            <div v-if="selectedCategoryId" class="template-actions">
              <button @click="downloadTemplate" class="btn-text-icon" :disabled="downloadingTemplate">
                <span v-if="downloadingTemplate">⏳</span>
                <span v-else>📥</span>
                Скачать шаблон CSV для «{{ selectedCategoryName }}»
              </button>
            </div>
          </div>
        </div>

        <!-- ШАГ 2: Файл -->
        <div class="card step-card" :class="{ disabled: !selectedCategoryId }">
          <div class="step-header">
            <div class="step-number">2</div>
            <h3>Загрузите файл</h3>
          </div>

          <div class="step-content">
            <!-- Кликабельная область загрузки -->
            <div
                class="upload-area"
                :class="{ 'drag-over': isDragOver, 'has-file': !!selectedFile }"
                @dragover.prevent
                @dragenter.prevent="isDragOver = true"
                @dragleave.prevent="isDragOver = false"
                @drop.prevent="handleDrop"
                @click="triggerFileSelect"
            >
              <input
                  type="file"
                  ref="fileInput"
                  accept=".csv,.json"
                  @change="handleFileSelect"
                  hidden
              />

              <div v-if="!selectedFile" class="upload-placeholder">
                <div class="icon-cloud">☁️</div>
                <p><strong>Нажмите</strong> или перетащите файл сюда</p>
                <span class="file-types">Поддерживаются: CSV, JSON</span>
              </div>

              <div v-else class="file-preview">
                <div class="file-icon">📄</div>
                <div class="file-details">
                  <div class="file-name">{{ selectedFile.name }}</div>
                  <div class="file-size">{{ formatFileSize(selectedFile.size) }}</div>
                </div>
                <button @click.stop="clearFile" class="btn-remove" title="Удалить">✕</button>
              </div>
            </div>

            <button
                @click="uploadFeed"
                :disabled="!selectedFile || !selectedCategoryId || isUploading"
                class="btn-primary full-width mt-4"
            >
              <span v-if="isUploading" class="spinner"></span>
              {{ isUploading ? 'Загрузка и обработка...' : 'Начать импорт' }}
            </button>

            <!-- Статус текущей загрузки -->
            <div v-if="uploadStatus" class="status-alert" :class="uploadStatus.type">
              <div class="status-icon">{{ getStatusIcon(uploadStatus.type) }}</div>
              <div class="status-content">
                <strong>{{ uploadStatus.title }}</strong>
                <p>{{ uploadStatus.message }}</p>

                <button
                    v-if="uploadStatus.errorFileUrl"
                    @click="downloadErrorReport"
                    class="btn-text-icon mt-2"
                >
                  📥 Скачать полный отчёт об ошибках ({{ uploadStatus.errorCount }} шт.)
                </button>

                <!-- Прогресс бар (фейковый или реальный если есть инфо о чанках) -->
                <div v-if="isUploading && uploadStatus.type === 'info'" class="progress-bar-container">
                  <div class="progress-bar-indeterminate"></div>
                </div>

                <ul v-if="uploadStatus.previewErrors && uploadStatus.previewErrors.length" class="error-list mt-2">
                  <li v-for="(err, idx) in uploadStatus.previewErrors" :key="idx">
                    <span class="row-badge">Строка {{ err.line + 1 }}</span>
                    <span v-if="err.sku" class="sku-badge">{{ err.sku }}</span>
                    {{ err.msg }}
                  </li>
                  <li v-if="uploadStatus.errorCount > uploadStatus.previewErrors.length" class="more-errors">
                    ... и ещё {{ uploadStatus.errorCount - uploadStatus.previewErrors.length }} ошибок в файле
                  </li>
                </ul>

                <ul v-if="uploadStatus.errors" class="error-list">
                  <li v-for="(err, key) in uploadStatus.errors" :key="key">
                    <span class="row-badge">Строка {{ parseInt(key) + 1 }}</span> {{ err }}
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ПРАВАЯ КОЛОНКА: История -->
      <div class="side-column">
        <div class="card history-card">
          <h3>История загрузок</h3>
          <div v-if="loadingHistory" class="loading-state">Загрузка...</div>
          <div v-else-if="uploadHistory.length === 0" class="empty-state">История пуста</div>

          <div v-else class="history-list">
            <div v-for="item in uploadHistory" :key="item.id" class="history-item">
              <div @click.stop="downloadFile(item.errorFileUrl)" v-if="item.errorFileUrl">📥</div>
              <div class="history-header">
                <span class="history-date">{{ formatDate(item.created_at) }}</span>
                <span class="status-badge" :class="getStatusClass(item.status)">
                  {{ getStatusLabel(item.status) }}
                </span>
              </div>
              <div class="history-file" :title="item.filename">{{ item.filename }}</div>
              <div class="history-meta" v-if="item.metrics">
                ⏱ {{ item.metrics.importTime?.toFixed(1) }}s
                🔍 {{ item.metrics.indexTime?.toFixed(1) }}s
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import http from '@/services/api/http'
import CategorySelector from '@/components/CategorySelector.vue'
import { fetchCategories } from '@/services/api/categories'

// === State ===
const selectedCategoryId = ref(null)
const selectedCategoryName = ref('')
const downloadingTemplate = ref(false)

const fileInput = ref(null)
const selectedFile = ref(null)
const isDragOver = ref(false)
const isUploading = ref(false)
const uploadStatus = ref(null) // { type: 'info'|'success'|'error', title: string, message: string, errors?: obj }

const pollInterval = ref(null)
const uploadHistory = ref([])
const loadingHistory = ref(false)

const FEED_REPORT_FINAL_STATUSES = [
  'completed',
  'completed_with_errors',
  'failed'
];

const FEED_REPORT_ACTIVE_STATUSES = [
  'queued',
  'parsing',
  'processing',
  'chunks_queued'
];

// === Category & Template ===
const onCategorySelected = async (categoryId) => {
  try {
    const { data } = await http.get(`/vendor/feed/template/${categoryId}`)
    selectedCategoryName.value = data.name || `Категория ${categoryId}`
  } catch {
    selectedCategoryName.value = `Категория ${categoryId}`
  }
}

const downloadFile = (url) => {
  if (url) window.open(url, '_blank');
};


const downloadTemplate = async () => {
  if (!selectedCategoryId.value) return
  downloadingTemplate.value = true

  try {
    const response = await http({
      url: `/vendor/feed/template/${selectedCategoryId.value}`,
      method: 'GET',
      params: { download: 1 },
      responseType: 'blob',
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    // Пытаемся достать имя файла из заголовков, если бэкенд отдает Content-Disposition
    const contentDisposition = response.headers['content-disposition']
    let fileName = `template_${selectedCategoryId.value}.csv`
    if (contentDisposition) {
      const match = contentDisposition.match(/filename="?([^"]+)"?/)
      if (match) fileName = match[1]
    }

    link.setAttribute('download', fileName)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error(error)
    alert('Ошибка скачивания шаблона')
  } finally {
    downloadingTemplate.value = false
  }
}

// === File Handling ===
const triggerFileSelect = () => fileInput.value?.click()

const handleFileSelect = (e) => {
  if (e.target.files?.length) processFile(e.target.files[0])
}

const handleDrop = (e) => {
  isDragOver.value = false
  if (e.dataTransfer.files?.length) processFile(e.dataTransfer.files[0])
}

const processFile = (file) => {
  const ext = file.name.split('.').pop().toLowerCase()
  if (!['csv', 'json'].includes(ext)) {
    alert('Разрешены только файлы .csv и .json')
    return
  }
  selectedFile.value = file
  uploadStatus.value = null // Сброс статуса
}

const clearFile = () => {
  selectedFile.value = null
  if (fileInput.value) fileInput.value.value = ''
  uploadStatus.value = null
}

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

// === Upload & Polling ===
const uploadFeed = async () => {
  if (!selectedFile.value || !selectedCategoryId.value) return

  const formData = new FormData()
  formData.append('feed', selectedFile.value)
  formData.append('category_id', selectedCategoryId.value)

  try {
    isUploading.value = true
    uploadStatus.value = {
      type: 'info',
      title: 'Файл загружается...',
      message: 'Пожалуйста, не закрывайте вкладку до начала обработки.'
    }

    const { data } = await http.post('/vendor/feed/upload', formData)

    uploadStatus.value = {
      type: 'info',
      title: 'Обработка данных',
      message: 'Файл принят сервером. Ожидаем завершения фоновых задач...'
    }

    // Сразу обновляем историю, чтобы показать "В обработке"
    loadHistory()
    startPolling(data.reportId)

  } catch (error) {
    isUploading.value = false
    uploadStatus.value = {
      type: 'error',
      title: 'Ошибка загрузки',
      message: error.response?.data?.message || 'Сервер не отвечает'
    }
  }
}
const downloadErrorReport =  () => {
  downloadFile(uploadStatus.value?.errorFileUrl);
};
const startPolling = (reportId) => {
  if (pollInterval.value) clearInterval(pollInterval.value)

  let attempts = 0
  const maxAttempts = 600 // ~20 минут макс (если интервал 2 сек)

  pollInterval.value = setInterval(async () => {
    attempts++
    if (attempts > maxAttempts) {
      stopPolling()
      uploadStatus.value = { type: 'warning', title: 'Таймаут', message: 'Обработка идет слишком долго. Проверьте статус в истории позже.' }
      return
    }

    try {
      const { data } = await http.get(`/vendor/feed/report-status/${reportId}`)

      if (data.isFinished) {
        stopPolling();
        isUploading.value = false;
        selectedFile.value = null;
        const finalStatuses = ['completed', 'completed_with_errors', 'failed'];
        if (!finalStatuses.includes(data.status)) {
          console.warn('isFinished=true, но статус не финальный:', data.status);
          // Не останавливаем polling — продолжаем
          return;
        }

        // === Формируем основное сообщение ===
        let baseMessage = `Обработано товаров: ${data.totalRows}`;
        if (data.errors && data.errors.total_errors  > 0) {
          baseMessage += `. Ошибок: ${data.errors.total_errors}`;
        }

        // === Добавляем метрики, если есть ===
        const metrics = data.metrics || {};
        let metricsLines = [];
        if (metrics.importTime !== undefined) {
          metricsLines.push(`⏱ Импорт в БД: ${metrics.importTime.toFixed(1)} сек`);
        }
        if (metrics.indexTime !== undefined) {
          metricsLines.push(`🔍 Индексация: ${metrics.indexTime.toFixed(1)} сек`);
        }
        if (metrics.totalElapsed !== undefined) {
          metricsLines.push(`🕗 Общее время: ${formatDuration(metrics.totalElapsed)}`);
        }

        const fullMessage = baseMessage + (metricsLines.length ? '\n' + metricsLines.join('\n') : '');

        if (data.errors && data.errors.total_errors > 0) {
          uploadStatus.value = {
            type: 'warning',
            title: 'Загружено с ошибками',
            message: fullMessage,
            errorFileUrl: data.errorFileUrl,               // ← URL файла
            errorCount: data.errors.total_errors || 0,     // ← общее число ошибок
            previewErrors: data.errors.preview || [],
          };
        } else {
          uploadStatus.value = {
            type: 'success',
            title: 'Успешно!',
            message: fullMessage,
            errorFileUrl: data.errorFileUrl,
          };
        }
        loadHistory()
      } else {
        // Обновляем прогресс
        uploadStatus.value = {
          type: 'info',
          title: 'Обработка данных...',
          message: `Обработано ${data.successCount + (data.errorCount || 0)} из ${data.totalRows}`
        }
      }
    } catch (e) {
      console.warn('Ошибка опроса', e)
    }
  }, 2000)
}

const stopPolling = () => {
  if (pollInterval.value) clearInterval(pollInterval.value)
  pollInterval.value = null
}

// === History ===
const loadHistory = async () => {
  loadingHistory.value = true
  try {
    const { data } = await http.get('/vendor/feed/history')
    uploadHistory.value = data.items || []
    const activeReports = uploadHistory.value.filter(item =>
        !FEED_REPORT_FINAL_STATUSES.includes(item.status)
    )

    // Останавливаем текущий polling (на случай, если он уже идёт)
    stopPolling()

    // Запускаем polling для самого нового активного отчёта
    if (activeReports.length > 0) {
      // Предполагается, что история отсортирована по убыванию (новые сверху)
      // Если нет — отсортируйте явно:
      const sorted = [...activeReports].sort((a, b) =>
          new Date(b.created_at) - new Date(a.created_at)
      )
      const latest = sorted[0]
      startPolling(latest.id)
    }
  } finally {
    loadingHistory.value = false
  }
}

const formatDate = (dateStr) => {
  return new Date(dateStr).toLocaleDateString('ru-RU', {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'
  })
}

const getStatusClass = (status) => {
  if (['completed'].includes(status)) return 'success'
  if (['completed_with_errors'].includes(status)) return 'warning'
  if (['failed'].includes(status)) return 'error'
  return 'processing' // для queued, parsing, processing, chunks_queued
}

const getStatusLabel = (status) => {
  const labels = {
    queued: 'В очереди',
    parsing: 'Парсинг',
    processing: 'Обработка',
    chunks_queued: 'Чанки в очереди',
    completed: 'Готово',
    completed_with_errors: 'Готово с ошибками',
    failed: 'Ошибка'
  }
  return labels[status] || 'Неизвестно'
}

const formatDuration = (seconds) => {
  if (seconds < 60) {
    return `${Math.floor(seconds)}с`
  }

  const totalSeconds = Math.round(seconds)
  const hours = Math.floor(totalSeconds / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  const secs = totalSeconds % 60

  const parts = []
  if (hours > 0) parts.push(`${hours}ч`)
  if (minutes > 0) parts.push(`${minutes}мин`)
  if (secs > 0 || parts.length === 0) parts.push(`${secs}с`)

  return parts.join(' ')
}

const getStatusIcon = (type) => {
  const icons = { info: '⏳', success: '✅', warning: '⚠️', error: '❌' }
  return icons[type]
}

// === Lifecycle ===
onMounted(() => loadHistory())
onUnmounted(() => stopPolling())
</script>

<style scoped>
/* Layout */
.content-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 24px;
  align-items: start;
}
@media (max-width: 900px) {
  .content-grid { grid-template-columns: 1fr; }
}

/* Cards */
.card {
  background: white;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 24px;
  margin-bottom: 24px;
}
.step-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}
.step-number {
  background: #3b82f6;
  color: white;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
}
.disabled {
  opacity: 0.5;
  pointer-events: none;
}

/* Upload Area */
.upload-area {
  border: 2px dashed #cbd5e1;
  border-radius: 8px;
  padding: 32px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  background: #f8fafc;
}
.upload-area:hover, .upload-area.drag-over {
  border-color: #3b82f6;
  background: #eff6ff;
}
.upload-area.has-file {
  border-style: solid;
  background: #f0fdf4;
  border-color: #86efac;
}
.icon-cloud { font-size: 40px; margin-bottom: 10px; }
.file-preview {
  display: flex;
  align-items: center;
  gap: 12px;
  text-align: left;
  background: white;
  padding: 12px;
  border-radius: 6px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.file-icon { font-size: 24px; }
.file-name { font-weight: 500; }
.file-size { font-size: 12px; color: #64748b; }
.btn-remove {
  margin-left: auto;
  background: none;
  border: none;
  font-size: 18px;
  cursor: pointer;
  color: #94a3b8;
}

/* Status Alert */
.status-alert {
  margin-top: 20px;
  padding: 16px;
  border-radius: 8px;
  display: flex;
  gap: 12px;
}
.status-alert.info { background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe; }
.status-alert.success { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }
.status-alert.warning { background: #fefce8; color: #854d0e; border: 1px solid #fef9c3; }
.status-alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }

/* Progress Bar (Animation) */
.progress-bar-container {
  height: 4px;
  background: #bfdbfe;
  margin-top: 8px;
  border-radius: 2px;
  overflow: hidden;
}
.progress-bar-indeterminate {
  height: 100%;
  background: #3b82f6;
  width: 50%;
  animation: loading 1.5s infinite ease-in-out;
}
@keyframes loading {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(200%); }
}

/* History List */
.history-item {
  border-bottom: 1px solid #f1f5f9;
  padding: 12px 0;
}
.history-item:last-child { border-bottom: none; }
.history-header {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #64748b;
  margin-bottom: 4px;
}
.history-file { font-weight: 500; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.status-badge {
  padding: 2px 8px;
  border-radius: 99px;
  font-size: 11px;
  font-weight: 600;
}
.status-badge.pending { background: #f1f5f9; color: #475569; }
.status-badge.processing { background: #dbeafe; color: #2563eb; }
.status-badge.success { background: #dcfce7; color: #166534; }
.status-badge.failed { background: #fee2e2; color: #991b1b; }

.error-list {
  margin-top: 8px;
  padding-left: 0;
  list-style: none;
  font-size: 13px;
  max-height: 200px;
  overflow-y: auto;
}
.error-list li { margin-bottom: 6px; }
.row-badge {
  background: rgba(0,0,0,0.1);
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: bold;
  font-size: 11px;
  margin-right: 6px;
}
.btn-primary.full-width { width: 100%; }
.mt-4 { margin-top: 16px; }

.download-icon {
  position: absolute;
  top: 8px;
  right: 8px;
  cursor: pointer;
  opacity: 0.7;
  transition: opacity 0.2s;
}

.download-icon:hover {
  opacity: 1;
}

.history-item {
  position: relative;
  padding-right: 32px; /* место для иконки */
}
</style>