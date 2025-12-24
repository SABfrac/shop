<template>
  <div class="vendor-dashboard">
    <div class="dashboard-header">
      <h1>Личный кабинет продавца</h1>
      <div class="header-actions">
        <button @click="toggleTheme" class="btn-theme-toggle">
          {{ isDarkMode ? '☀️ Светлая' : '🌙 Тёмная' }}
        </button>
        <button @click="logout" class="btn-logout">Выйти</button>
      </div>
    </div>

    <div class="dashboard-content">
      <!-- Профиль вендора -->
      <div class="profile-section">
        <h2>Информация о продавце</h2>
        <div class="profile-info">
          <div class="info-item">
            <label>Название компании:</label>
            <span>{{ vendorProfile.name }}</span>
          </div>
          <div class="info-item">
            <label>Email:</label>
            <span>{{ vendorProfile.email }}</span>
          </div>
          <div class="info-item">
            <label>Паспорт/ИНН:</label>
            <span>{{ vendorProfile.passport }}</span>
          </div>
          <div class="info-item">
            <label>Баланс:</label>
            <span class="balance">{{ vendorProfile.balance }} ₽</span>
          </div>
          <div class="info-item">
            <label>Статус:</label>
            <span :class="statusClass">{{ vendorProfile.status === 1 ? 'Активен' : 'Не активен' }}</span>
          </div>
        </div>
      </div>

      <!-- Управление офферами -->
      <div class="offers-section">
        <div class="section-header">
          <h2>Мои предложения</h2>
          <!-- ✅ Заменено на router-link -->
          <router-link to="/vendors/offers/new" class="btn-primary">
            + Добавить предложение
          </router-link>
        </div>

        <!-- Фильтры и поиск -->
        <div class="offers-filters">
          <input
              v-model="searchQuery"
              type="text"
              placeholder="Поиск по названию товара..."
              class="search-input"
          />
          <select v-model="filterStatus" class="filter-select">
            <option value="">Все статусы</option>
            <option value="1">Активные</option>
            <option value="0">Неактивные</option>
            <option value="2">На модерации</option>
          </select>
        </div>

        <!-- Список офферов -->
        <div class="offers-list">
          <div v-if="loading" class="loading">Загрузка...</div>

          <div v-else-if="offers.length === 0" class="no-offers">
            Нет предложений
          </div>

          <div
              v-for="offer in offers"
              :key="offer.id"
              class="offer-item"
          >
            <div class="offer-info">
              <h3>{{ offer.sku?.product?.name || 'Товар не найден' }}</h3>
              <div class="offer-details">
                <span>Цена: {{ offer.price }} ₽</span>
                <span>Количество: {{ offer.stock }} шт</span>
                <span>Состояние: {{ getConditionText(offer.condition) }}</span>
                <span  :class="getOfferStatusClass(offer.status)">
                  {{ getOfferStatusText(offer.status) }}
                </span>
              </div>
            </div>

            <div class="offer-actions">
              <!-- ✅ Заменено на router-link -->
              <router-link
                  :to="`/vendors/offer/${offer.id}/edit`"
                  class="btn-edit"
              >
                Редактировать
              </router-link>
              <button @click="handleDeleteOffer(offer.id)" class="btn-delete">Удалить</button>
            </div>
          </div>
        </div>

        <!-- Пагинация -->
        <div v-if="totalPages > 1" class="pagination">
          <button
              v-for="page in totalPages"
              :key="page"
              @click="changePage(page)"
              :class="{ active: currentPage === page }"
              class="page-btn"
          >
            {{ page }}
          </button>
        </div>
      </div>
      <div class="feed-actions">
        <router-link to="/feed/upload" class="btn-primary btn-feed-upload">
          📤 Массовая загрузка товара и прредложений цены
        </router-link>
      </div>


    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useVendorStore } from '@/stores/vendor'
import http from '@/services/api/http'
import {deleteOffer} from '@/services/api/offers'
import { useOfferStore } from '@/stores/vendor'

const router = useRouter()
const vendorStore = useVendorStore()

const offerStore = useOfferStore()

const editOffer = (offer) => {

  router.push(`/vendors/offer/${offer.id}/edit`)
}

// === Тема ===
const isDarkMode = ref(false)
const savedTheme = localStorage.getItem('vendorTheme') || 'light'
isDarkMode.value = savedTheme === 'dark'

const toggleTheme = () => {
  isDarkMode.value = !isDarkMode.value
  if (isDarkMode.value) {
    document.body.classList.add('dark-theme')
    localStorage.setItem('vendorTheme', 'dark')
  } else {
    document.body.classList.remove('dark-theme')
    localStorage.setItem('vendorTheme', 'light')
  }
}

// === Данные профиля ===
const vendorProfile = computed(() => vendorStore.profile || {})

// === Офферы и пагинация ===
const offers = ref([])
const loading = ref(false)
const currentPage = ref(1)
const itemsPerPage = ref(10)
const totalPages = ref(1)

// === Фильтры ===
const searchQuery = ref('')
const filterStatus = ref('')

// === Вычисляемые классы ===
const statusClass = computed(() => {
  return vendorProfile.value.status == 1 ? 'status-active' : 'status-inactive'
})

// === Загрузка офферов с фильтрами и пагинацией ===
const loadOffers = async () => {
  try {
    loading.value = true

    const params = {
      page: currentPage.value,
      'per-page': itemsPerPage.value,
    }

    if (searchQuery.value) params.search = searchQuery.value
    if (filterStatus.value !== '') params.status = filterStatus.value

    const response = await http.get('/vendors/offers', { params })

    offers.value = response.data.items || []
    const meta = response.data.meta || {}
    totalPages.value = meta.totalPages || 1
  } catch (error) {
    console.error('Ошибка загрузки офферов:', error)
    offers.value = []
    totalPages.value = 1
  } finally {
    loading.value = false
  }
}

const getOfferStatusText = (status) => {
  switch (status) {
    case 1: return 'Активен'
    case 2: return 'На модерации'
    case 0:
    default: return 'Не активен'
  }
}

const getOfferStatusClass = (status) => {
  switch (status) {
    case 1: return 'status-active'
    case 2: return 'status-moderation'
    case 0:
    default: return 'status-inactive'
  }
}

// === Утилиты ===
const getConditionText = (condition) => {
  const conditions = {
    new: 'Новый',
    used: 'Б/у',
    refurbished: 'Восстановленный',
  }
  return conditions[condition] || condition
}

// === Удаление оффера (остаётся, т.к. не требует формы) ===
const handleDeleteOffer = async (offerId) => {
  if (confirm('Вы уверены, что хотите удалить это предложение?')) {
    try {
      await deleteOffer(offerId);
      await loadOffers();
    } catch (error) {
      alert('Ошибка удаления предложения');
      console.error('Ошибка удаления:', error);
    }
  }
}

const changePage = (page) => {
  currentPage.value = page
  loadOffers()
}

const logout = () => {
  vendorStore.logout()
  router.push('/vendors/login')
}

// === Watchers ===
watch([searchQuery, filterStatus], () => {
  currentPage.value = 1
  loadOffers()
})

watch(currentPage, loadOffers)

// === Инициализация ===
onMounted(async () => {
  if (!vendorStore.isAuthenticated) {
    await vendorStore.fetchProfile()
  }
  await loadOffers()
})
</script>

<style scoped>
/* Стили остаются почти без изменений */
.vendor-dashboard {
  padding: 20px;
}

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.dashboard-header h1 {
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 12px;
}

.btn-theme-toggle,
.btn-logout {
  padding: 8px 16px;
  border: 1px solid #ccc;
  border-radius: 4px;
  background: white;
  cursor: pointer;
}

.btn-primary {
  padding: 8px 16px;
  background: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  text-decoration: none; /* важно для router-link */
  display: inline-block;
}

.btn-primary:hover {
  background: #0056b3;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.offers-filters {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.search-input,
.filter-select {
  padding: 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
}

.offer-item {
  display: flex;
  justify-content: space-between;
  padding: 16px;
  border: 1px solid #eee;
  border-radius: 8px;
  margin-bottom: 12px;
  background: #fafafa;
}

.offer-info h3 {
  margin: 0 0 8px 0;
}

.offer-details {
  display: flex;
  gap: 16px;
  font-size: 0.9rem;
  color: #555;
}

.offer-actions {
  display: flex;
  gap: 8px;
}

.btn-edit,
.btn-delete {
  padding: 6px 12px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn-edit {
  background: #28a745;
  color: white;
  text-decoration: none; /* для router-link */
}

.btn-delete {
  background: #dc3545;
  color: white;
}

.pagination {
  margin-top: 20px;
  text-align: center;
}

.page-btn {
  padding: 6px 12px;
  margin: 0 4px;
  border: 1px solid #ccc;
  background: white;
  cursor: pointer;
}

.page-btn.active {
  background: #007bff;
  color: white;
}

.status-active {
  color: #28a745;
}
.status-inactive {
  color: #dc3545;
}
.status-moderation {
  color: #ffc107; /* Жёлтый — стандартный цвет для "в ожидании" */
}

.loading, .no-offers {
  text-align: center;
  padding: 20px;
  color: #666;
}

.btn-feed-upload {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}

</style>