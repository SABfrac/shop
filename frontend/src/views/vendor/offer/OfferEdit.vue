<template>
  <div class="offer-edit-page">
    <div class="form-container">
      <div class="form-header">
        <h2>Редактировать предложение</h2>
        <button @click="goBack" class="btn-secondary">← Назад</button>
      </div>

      <div v-if="loading" class="loading">Загрузка...</div>

      <template v-else-if="offer">
        <!-- Информация о товаре (только чтение) -->
        <div class="product-info-card">
          <h3>Информация о товаре</h3>
          <div class="info-grid">
            <div class="info-item">
              <span class="label">Категория:</span>
              <span class="value">{{ productInfo.category_name }}</span>
            </div>
            <div class="info-item">
              <span class="label">Бренд:</span>
              <span class="value">{{ productInfo.brand_name || '—' }}</span>
            </div>
            <div class="info-item">
              <span class="label">Товар:</span>
              <span class="value">{{ productInfo.product_name }}</span>
            </div>
            <div class="info-item" v-if="productInfo.variant_label">
              <span class="label">Вариант:</span>
              <span class="value">{{ productInfo.variant_label }}</span>
            </div>
          </div>
        </div>

        <!-- Форма редактирования -->
        <form @submit.prevent="saveOffer" class="offer-edit-form">
          <h3>Ваше предложение</h3>

          <div class="form-row">
            <div class="form-group">
              <label for="vendor_sku">Ваш артикул</label>
              <input
                  id="vendor_sku"
                  v-model="offer.vendor_sku"
                  type="text"
                  class="form-input"
              />
            </div>
            <div class="form-group">
              <label for="price">Цена <span class="required">*</span></label>
              <input
                  id="price"
                  v-model.number="offer.price"
                  type="number"
                  min="0"
                  step="0.01"
                  class="form-input"
                  required
              />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="stock">Остаток <span class="required">*</span></label>
              <input
                  id="stock"
                  v-model.number="offer.stock"
                  type="number"
                  min="0"
                  class="form-input"
                  required
              />
            </div>
            <div class="form-group">
              <label for="warranty">Гарантия (мес.)</label>
              <select id="warranty" v-model="offer.warranty" class="form-select">
                <option :value="null">Без гарантии</option>
                <option :value="1">1</option>
                <option :value="3">3</option>
                <option :value="6">6</option>
                <option :value="12">12</option>
                <option :value="24">24</option>
                <option :value="36">36</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="condition">Состояние</label>
              <select id="condition" v-model="offer.condition" class="form-select">
                <option value="new">Новый</option>
                <option value="used">Б/у</option>
                <option value="refurbished">Восстановленный</option>
              </select>
            </div>
            <div class="form-group">
              <label for="status">Статус</label>
              <select id="status" v-model.number="offer.status" class="form-select">
                <option :value="1">Активно</option>
                <option :value="0">Неактивно</option>
                <option :value="2" disabled>На модерации</option>
              </select>
            </div>
          </div>

          <div class="form-actions">
            <button type="button" @click="goBack" class="btn-secondary">Отмена</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Сохранение...' : 'Сохранить' }}
            </button>
          </div>
        </form>
      </template>

      <div v-else class="error">Предложение не найдено</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import http from '@/services/api/http'

const router = useRouter()
const route = useRoute()

const loading = ref(true)
const saving = ref(false)

interface OfferData {
  id: number
  vendor_sku: string
  price: number
  stock: number
  warranty: number | null
  condition: string
  status: number
}

interface ProductInfo {
  product_name: string
  category_name: string
  brand_name: string | null
  variant_label: string | null
}

const offer = ref<OfferData | null>(null)
const productInfo = ref<ProductInfo>({
  product_name: '',
  category_name: '',
  brand_name: null,
  variant_label: null
})

const goBack = () => router.push('/vendor/dashboard')

const loadOffer = async () => {
  const offerId = route.params.id
  if (!offerId) {
    goBack()
    return
  }

  loading.value = true
  try {
    // Используем ваш существующий endpoint
    const response = await http.get(`/offers/view`, {
      params: { id: offerId }
    })

    // Если actionView возвращает напрямую data (без обёртки success)
    const data = response.data.data || response.data

    offer.value = {
      id: data.id,
      vendor_sku: data.vendor_sku || '',
      price: data.price,
      stock: data.stock,
      warranty: data.warranty,
      condition: data.condition || 'new',
      status: data.status
    }

    productInfo.value = {
      product_name: data.product_name || data.sku?.product?.name || '',
      category_name: data.category_name || '',
      brand_name: data.brand_name || null,
      variant_label: data.variant_label || null
    }

  } catch (err: any) {
    console.error('Ошибка загрузки:', err)
    alert(err.response?.data?.message || 'Не удалось загрузить')
    goBack()
  } finally {
    loading.value = false
  }
}

const saveOffer = async () => {
  if (!offer.value) return

  saving.value = true
  try {
    // Отправляем на ваш actionSave
    const response = await http.post('/offers/save', {
      id: offer.value.id,  // Важно: передаём id для режима редактирования
      vendor_sku: offer.value.vendor_sku,
      price: offer.value.price,
      stock: offer.value.stock,
      warranty: offer.value.warranty,
      condition: offer.value.condition,
      status: offer.value.status
    })

    if (response.data.success) {
      alert('Сохранено!')
      goBack()
    } else {
      alert('Ошибка: ' + (response.data.errors?.join(', ') || response.data.message))
    }
  } catch (err: any) {
    console.error('Ошибка сохранения:', err)
    alert(err.response?.data?.message || 'Ошибка сохранения')
  } finally {
    saving.value = false
  }
}

onMounted(() => loadOffer())
</script>e>