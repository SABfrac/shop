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



                <div v-if="uploadStatus.isFinished" class="stats-grid mt-2">
                  <div class="stat-item success">
                    <span class="stat-value">{{ uploadStatus.successCount }}</span>
                    <span class="stat-label">✅ Успешно</span>
                  </div>
                  <div v-if="uploadStatus.errorCount > 0" class="stat-item error">
                    <span class="stat-value">{{ uploadStatus.errorCount }}</span>
                    <span class="stat-label">❌ Ошибки</span>
                  </div>
                  <div class="stat-item total">
                    <span class="stat-value">{{ uploadStatus.totalRows }}</span>
                    <span class="stat-label">📊 Всего</span>
                  </div>
                </div>

                <p>{{ uploadStatus.message }}</p>

                <!-- Метрики -->
                <div v-if="uploadStatus.metrics" class="metrics-display mt-2">
                  <span class="metric-item">⏱ Импорт: {{ uploadStatus.metrics.importTime?.toFixed(2) }}s</span>
                  <span class="metric-item">🔍 Индексация: {{ uploadStatus.metrics.indexTime?.toFixed(2) }}s</span>
                </div>

                <!-- === ПРОГРЕСС БАР (Только в процессе) === -->
                <div v-if="uploadStatus.progressPercent !== undefined && !uploadStatus.isFinished" class="progress-container mt-3">
                  <div class="progress-info">
                    <span>Обработано: {{ uploadStatus.progressPercent }}%</span>
                    <span v-if="uploadStatus.etaSeconds" class="eta-text">
                      Осталось: {{ formatEta(uploadStatus.etaSeconds) }}
                    </span>
                  </div>
                  <div class="progress-bar-bg">
                    <div class="progress-bar-fill" :style="{ width: uploadStatus.progressPercent + '%' }"></div>
                  </div>
                </div>
                <!-- ================================ -->

                <button
                    v-if="uploadStatus.errorFileUrl"
                    @click="downloadErrorReport"
                    class="btn-text-icon mt-2"
                >
                  📥 Скачать отчёт об ошибках ({{ uploadStatus.errorCount }} шт.)
                </button>
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

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import http from '@/services/api/http'
import CategorySelector from '@/components/CategorySelector.vue'
import { fetchCategories } from '@/services/api/categories'

// === State ===
const selectedCategoryId = ref<number | null>(null)
const selectedCategoryName = ref('')
const downloadingTemplate = ref(false)
const fileInput = ref(null)
const selectedFile = ref(null)
const isDragOver = ref(false)
const isUploading = ref(false)
const uploadStatus = ref<any>(null)
const uploadHistory = ref([])
const loadingHistory = ref(false)

// === Polling State (Вынесено на уровень модуля) ===
let pollTimer: number | null = null
let currentReportId: number | null = null

const FEED_REPORT_FINAL_STATUSES = ['completed', 'completed_with_errors', 'failed']

// === Helpers ===
const formatEta = (seconds: number) => {
  if (!seconds && seconds !== 0) return '...'
  if (seconds < 60) return `${seconds} сек`
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins} мин ${secs} сек`
}

// === Polling Logic (Short Polling для Highload) ===
const startStatusPolling = (reportId: number) => {
  if (pollTimer) clearInterval(pollTimer)
  currentReportId = reportId

  const checkStatus = async () => {
    try {
      const { data } = await http.get(`/vendor/feed/report-status/${reportId}`)

      uploadStatus.value = {
        type: data.isFinished ? 'success' : 'info',
        title: data.isFinished ? 'Загружено' : 'В обработке',
        message: data.isFinished
            ? `Обработано товаров: ${data.successCount}`
            : `Обработано: ${data.progressPercent}%`,
        metrics: data.metrics,
        progressPercent: data.progressPercent,
        etaSeconds: data.etaSeconds,
        isFinished: data.isFinished,
        errorFileUrl: data.errorFileUrl,
        successCount: data.successCount,
        errorCount: data.errorCount,
        totalRows: data.totalRows,
      }

      if (data.isFinished) {
        stopStatusPolling()
        isUploading.value = false
        selectedFile.value = null
        loadHistory()
      }
    } catch (e) {
      console.error('Polling error', e)
    }
  }

  // Первый запрос сразу
  checkStatus()
  // Далее опрос каждые 2 секунды
  pollTimer = window.setInterval(checkStatus, 2000)
}

const stopStatusPolling = () => {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }

}

// === Category & Template ===
const onCategorySelected = async (categoryId: number) => {
  try {
    const { data } = await http.get(`/vendor/feed/template/${categoryId}`)
    selectedCategoryName.value = data.name || `Категория ${categoryId}`
  } catch {
    selectedCategoryName.value = `Категория ${categoryId}`
  }
}

const downloadFile = (url: string) => {
  if (url) window.open(url, '_blank')
}

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
const handleFileSelect = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files?.length) processFile(target.files[0])
}
const handleDrop = (e: DragEvent) => {
  isDragOver.value = false
  if (e.dataTransfer?.files?.length) processFile(e.dataTransfer.files[0])
}
const processFile = (file: File) => {
  const ext = file.name.split('.').pop().toLowerCase()
  if (!['csv', 'json'].includes(ext)) {
    alert('Разрешены только файлы .csv и .json')
    return
  }
  selectedFile.value = file
  uploadStatus.value = null
}
const clearFile = () => {
  selectedFile.value = null
  if (fileInput.value) fileInput.value.value = ''
  uploadStatus.value = null
}
const formatFileSize = (bytes: number) => {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

// === Upload ===
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
      message: 'Пожалуйста, не закрывайте вкладку.',
    }

    const { data } = await http.post('/vendor/feed/upload', formData)
    const reportId = data.reportId

    uploadStatus.value = {
      type: 'info',
      title: 'Обработка данных',
      message: 'Файл принят. Ожидаем завершения фоновых задач...',
    }

    // Запускаем Short Polling (вместо SSE)
    startStatusPolling(reportId)
  } catch (error: any) {
    isUploading.value = false
    uploadStatus.value = {
      type: 'error',
      title: 'Ошибка загрузки',
      message: error.response?.data?.message || 'Сервер не отвечает',
    }
  }
}

// === History ===
const loadHistory = async () => {
  loadingHistory.value = true
  try {
    const { data } = await http.get('/vendor/feed/history')
    uploadHistory.value = data.items || []

    const activeReports = uploadHistory.value.filter((item: any) =>
        !FEED_REPORT_FINAL_STATUSES.includes(item.status)
    )

    // Если есть активные отчеты и мы сейчас не грузим новый файл
    if (activeReports.length > 0 && !isUploading.value) {
      const sorted = [...activeReports].sort((a: any, b: any) =>
          new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
      )
      const latest = sorted[0]
      startStatusPolling(latest.id)
    }
  } catch (e) {
    console.error('Failed to load history:', e)
  } finally {
    loadingHistory.value = false
  }
}

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('ru-RU', {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'
  })
}
const getStatusClass = (status: string) => {
  if (['completed'].includes(status)) return 'success'
  if (['completed_with_errors'].includes(status)) return 'warning'
  if (['failed'].includes(status)) return 'error'
  return 'processing'
}
const getStatusLabel = (status: string) => {
  const labels: Record<string, string> = {
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
const getStatusIcon = (type: string) => {
  const icons: Record<string, string> = { info: '⏳', success: '✅', warning: '⚠️', error: '❌' }
  return icons[type]
}
const downloadErrorReport = () => {
  if (uploadStatus.value?.errorFileUrl) {
    window.open(uploadStatus.value.errorFileUrl, '_blank')
  }
}

// === Lifecycle ===
onMounted(() => loadHistory())
onUnmounted(() => {
  stopStatusPolling() // Корректно очищает таймер
})
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

.metrics-display {
  display: flex;
  gap: 16px;
  font-size: 13px;
  color: #64748b;
}

.metric-item {
  background: rgba(0, 0, 0, 0.05);
  padding: 4px 8px;
  border-radius: 4px;
}

.status-alert.success {
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #dcfce7;
}

.progress-container {
  margin-top: 1rem;
}
.progress-info {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  color: #666;
  margin-bottom: 0.25rem;
}
.eta-text {
  font-weight: 600;
  color: #333;
}
.progress-bar-bg {
  width: 100%;
  height: 8px;
  background-color: #e0e0e0;
  border-radius: 4px;
  overflow: hidden;
}
.progress-bar-fill {
  height: 100%;
  background-color: #4caf50; /* Зеленый цвет */
  transition: width 0.5s ease;
}


.progress-container { margin-top: 1rem; }
.progress-info {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  color: #666;
  margin-bottom: 0.25rem;
}
.eta-text { font-weight: 600; color: #333; }
.progress-bar-bg {
  width: 100%;
  height: 8px;
  background-color: #e0e0e0;
  border-radius: 4px;
  overflow: hidden;
}
.progress-bar-fill {
  height: 100%;
  background-color: #4caf50;
  transition: width 0.5s ease;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  padding: 1rem;
  background: #f8f9fa;
  border-radius: 8px;
}

.stat-item {
  text-align: center;
  padding: 0.5rem;
}

.stat-item.success { color: #28a745; }
.stat-item.error { color: #dc3545; }
.stat-item.total { color: #6c757d; }

.stat-value {
  display: block;
  font-size: 1.5rem;
  font-weight: 700;
}

.stat-label {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Прогресс бар */
.progress-container { margin-top: 1rem; }
.progress-info {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  color: #666;
  margin-bottom: 0.25rem;
}
.eta-text { font-weight: 600; color: #333; }
.progress-bar-bg {
  width: 100%;
  height: 8px;
  background-color: #e0e0e0;
  border-radius: 4px;
  overflow: hidden;
}
.progress-bar-fill {
  height: 100%;
  background-color: #4caf50;
  transition: width 0.5s ease;
}
</style>