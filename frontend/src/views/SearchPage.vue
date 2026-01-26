<template>
  <header class="search-header">
    <div class="search-box">
      <div class="input-wrapper">
        <SearchInput
            v-model="query"
            @keyup.enter="searchNow"
            placeholder="Найти товар..."
        />
        <!-- Отображение подсказок -->
        <div v-if="suggestions.length > 0" class="suggestions-list">
          <div
              v-for="(suggestion, index) in suggestions"
              :key="index"
              class="suggestion-item"
              @click="selectSuggestion(suggestion)"
          >
            {{ suggestion }}
          </div>
        </div>
      </div>
      <button class="search-btn" @click="searchNow" :disabled="loading" title="Искать">
        🔍
      </button>
    </div>
  </header>

  <div class="results">
    <div v-if="loading">Поиск...</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <div v-else-if="results.length === 0 && query.trim().length >= 1">
      Ничего не найдено
    </div>
    <div v-else class="results-grid">
      <div
          v-for="item in results"
          :key="item.id"
          class="result-item"
      >
        <!-- Превью изображения -->
        <div class="product-image">
          <img
              v-if="item.preview_url"
              :src="item.preview_url"
              :alt="item.product_name"
              @error="onImageError($event)"
          />
          <div v-else class="no-image">Нет фото</div>
        </div>
        <h3>{{ item.product_name }}</h3>
        <p>Бренд: {{ item.brand_name }}</p>
        <p>Цена: {{ item.price }} ₽</p>
        <p>Остаток: {{ item.stock }}</p>
      </div>
    </div>
  </div>
  <div v-if="hasNext && !loading" class="load-more" @click="loadMore">
    Загрузить ещё...
  </div>

</template>

<script setup lang="ts">
import { ref, nextTick, watch } from 'vue';
import { useSearch } from '../composables/useSearch';
import SearchInput from '../components/SearchInput.vue';
import { useSuggest } from '../composables/useSuggest';

const query = ref('');
const isSelecting = ref(false);

const { suggestions, loading: suggestLoading, clearSuggestions } = useSuggest(query, { block: isSelecting });
const { results, loading, error, hasNext, searchNow: originalSearchNow, loadMore } = useSearch(query, {}, { minQueryLength: 1 });

// Оборачиваем searchNow, чтобы чистить подсказки
const searchNow = () => {
  clearSuggestions(); // ← Убираем автодополнение при любом поиске
  originalSearchNow();
};

// Выбор подсказки
const selectSuggestion = async (suggestion: string) => {
  isSelecting.value = true;
  clearSuggestions(); // уже вызывается, но на всякий случай
  query.value = suggestion;
  searchNow(); // запускаем поиск
  await nextTick();
  isSelecting.value = false;
};



// Обработка ошибок загрузки изображения (опционально)
const onImageError = (e: Event) => {
  const img = e.target as HTMLImageElement;
  img.style.opacity = '0.5';
  // или можно показать placeholder
};

// Дополнительно: при вводе очищаем подсказки, если query уменьшился до <2 или стало пустым
watch(query, (newVal) => {
  if (newVal.trim().length < 2) {
    clearSuggestions();
  }
});
</script>

<style scoped>
.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  position: relative;
}
.input-wrapper {
  position: relative;
  flex: 1;
}
.suggestions-list {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #ccc;
  border-radius: 4px;
  z-index: 10;
  max-height: 200px;
  overflow-y: auto;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.suggestion-item {
  padding: 8px 12px;
  cursor: pointer;
}
.suggestion-item:hover {
  background-color: #f0f0f0;
}
.search-btn {
  background: none;
  border: none;
  font-size: 1.2em;
  cursor: pointer;
  padding: 4px;
}
.search-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.results-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
  padding: 20px;
  margin-top: 16px;
}

/* Стиль карточки */
.result-item {
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.result-item:hover {
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
  transform: translateY(-2px);
}

.result-item h3 {
  margin: 0 0 12px 0;
  font-size: 1.1rem;
  color: #333;
}

.result-item p {
  margin: 6px 0;
  font-size: 0.95rem;
  color: #555;
}

/* Кнопка "Загрузить ещё" */
.load-more {
  text-align: center;
  padding: 12px 24px;
  margin: 20px auto;
  background: #f0f5ff;
  border-radius: 8px;
  cursor: pointer;
  color: #3366cc;
  font-weight: 600;
  width: fit-content;
  transition: background 0.2s;
}

.load-more:hover {
  background: #e0eaff;
}

/* Адаптивность на маленьких экранах */
@media (max-width: 600px) {
  .results-grid {
    grid-template-columns: 1fr;
    padding: 12px;
    gap: 16px;
  }

  .result-item {
    padding: 14px;
  }
}
</style>



