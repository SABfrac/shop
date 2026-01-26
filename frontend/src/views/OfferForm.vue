<template>
  <div class="offer-form-page">
    <div class="form-container">
      <div class="form-header">
        <h2>{{ isEditingSingle ? 'Редактировать предложение' : 'Добавить предложение' }}</h2>
        <button @click="goBack" class="btn-secondary">← Назад к списку</button>
      </div>

      <form @submit.prevent="saveOffers" class="offer-form">
        <!-- ШАГ 1: Выбор категории -->
        <CategorySelector v-model="categoryId" :fetcher="catalog.fetchCategories" />

        <!-- ШАГ 2: Загрузка и отображение не-вариативных атрибутов GlobalProduct -->
        <div v-if="categoryId && nonVariantAttributes.length > 0" class="non-variant-attributes-section">
          <h3>Информация о товаре</h3>
          <div v-for="attr in nonVariantAttributes" :key="attr.id" class="form-group">
            <label :for="`nv-attr-${attr.id}`">{{ attr.name }} <span v-if="attr.is_required" class="required">*</span></label>
            <select
                v-if="attr.type === 'select'"
                :id="`nv-attr-${attr.id}`"
                v-model="nonVariantAttributeValues[attr.name]"
                class="form-select"
            >
              <option value="">Выберите...</option>
              <option
                  v-for="opt in getAttributeOptions(attr.id)"
                  :key="opt.id"
                  :value="opt.value"
              >
                {{ opt.value }}
              </option>
            </select>
            <input
                v-else-if="attr.type === 'string'"
                :id="`nv-attr-${attr.id}`"
                v-model="nonVariantAttributeValues[attr.name]"
                type="text"
                class="form-input"
            />
            <input
                v-else-if="attr.type === 'integer'"
                :id="`nv-attr-${attr.id}`"
                v-model.number="nonVariantAttributeValues[attr.name]"
                type="number"
                class="form-input"
            />
            <input
                v-else-if="attr.type === 'float'"
                :id="`nv-attr-${attr.id}`"
                v-model.number="nonVariantAttributeValues[attr.name]"
                type="number"
                step="any"
                class="form-input"
            />
            <select
                v-else-if="attr.type === 'bool'"
                :id="`nv-attr-${attr.id}`"
                v-model="nonVariantAttributeValues[attr.name]"
                class="form-select"
            >
              <option :value="null">Не указано</option>
              <option :value="true">Да</option>
              <option :value="false">Нет</option>
            </select>
            <input
                v-else
                :id="`nv-attr-${attr.id}`"
                v-model="nonVariantAttributeValues[attr.name]"
                type="text"
                class="form-input"
            />
          </div>
        </div>

        <!-- ШАГ 3: Выбор бренда (опционально) -->
        <BrandSelector
            v-if="categoryId"
            v-model="brandId"
            :category-id="categoryId"
            :fetcher="catalog.fetchBrands"
            :enable-search="true"
        />

        <!-- ШАГ 4: Ввод названия товара -->
        <div v-if="categoryId" class="form-group">
          <label>Найдите товар или создайте новый</label>

          <!-- Поиск по существующим товарам -->
          <ProductSearch
              v-model="searchQuery"
              :results="searchResults"
              :loading="searchLoading"
              placeholder="Поиск по товарам в категории..."
              @select="onProductSelect"
          />

          <!-- Ручной ввод (если ничего не найдено или создать новый) -->
          <div v-if="!globalProductId" class="manual-input-section">
            <label for="product-name">Название товара <span class="required">*</span></label>
            <input
                id="product-name"
                v-model="productName"
                type="text"
                class="form-input"
                placeholder="Или введите название вручную..."
                required
            />
          </div>

          <!-- Если выбран товар — отображаем его -->
          <div v-else class="selected-product-info">
            <p><strong>Выбран товар:</strong> {{ productName }}</p>
            <button type="button" class="btn-link" @click="clearSelectedProduct">Отменить выбор</button>
          </div>
        </div>

        <!-- ШАГ 5: Ввод GTIN/Model Number -->
        <div v-if="categoryId" class="form-group">
          <label for="gtin">GTIN (EAN/UPC)</label>
          <input
              id="gtin"
              v-model="gtin"
              type="text"
              class="form-input"
          />
        </div>
        <div v-if="categoryId" class="form-group">
          <label for="model-number">Модель</label>
          <input
              id="model-number"
              v-model="modelNumber"
              type="text"
              class="form-input"
          />
        </div>

        <!-- ШАГ 6: Поиск/Создание GlobalProduct -->
        <div v-if="categoryId && productName && !globalProductId" class="global-product-section">
          <button type="button" @click="findOrCreateGlobalProduct" class="btn-secondary">
            Найти/Создать товар
          </button>
        </div>

        <!-- ШАГ 7: Ввод вариативных атрибутов SKU (если GlobalProduct выбран) -->
        <div v-if="globalProductId" class="sku-section">
          <h3>Варианты товара (SKU)</h3>
          <AttributesForm
              :attributes="variantAttributes"
              :value="skuAttributeValues"
              @save-attributes="updateSkuAttributeValues"
          />
          <button
              type="button"
              class="btn-primary mt-2"
              :disabled="isCreatingSku"
              @click="handleCreateSku"
          >
            Создать SKU и продолжить
          </button>
        </div>

        <!-- ШАГ 8: Таблица SKU и Offers (если SKU созданы) -->
        <div v-if="skus.length > 0" class="sku-table-wrapper">
          <div v-if="!isEditingSingle" class="toolbar">
            <input
                type="number"
                placeholder="Цена для выбранных"
                @change="setMassPrice(+$event.target.value)"
            />
            <input
                type="number"
                placeholder="Склад для выбранных"
                @change="setMassStock(+$event.target.value)"
            />
          </div>

          <table class="sku-table">
            <thead>
            <tr>
              <th v-if="!isEditingSingle">
                <input
                    type="checkbox"
                    :checked="selectedSkuIds.length === skus.length && skus.length > 0"
                    @change="toggleSelectAll"
                />
              </th>
              <th>Ваш артикул</th>
              <th>Товар</th>
              <th>Вариант</th>
              <th>Цена</th>
              <th>Склад</th>
              <th>Гарантия</th>
              <th>Состояние</th>
              <th v-if="!isEditingSingle">Статус</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="s in skus" :key="s.id">
              <td v-if="!isEditingSingle">
                <input type="checkbox" :value="s.id" v-model="selectedSkuIds" />
              </td>
              <td>
                <input
                    v-model="offers[s.id].vendor_sku"
                    :placeholder="`Авто: ${s.code || ''}`"
                    class="form-input"
                    style="width: 120px"
                />
              </td>
              <td>{{ productName || '—' }}</td>
              <td>{{ getVariantLabel(s) }}</td>
              <td>
                <input
                    type="number"
                    v-model.number="offers[s.id].price"
                    min="0"
                    step="0.01"
                    required
                />
              </td>
              <td>
                <input
                    type="number"
                    v-model.number="offers[s.id].stock"
                    min="0"
                    required
                />
              </td>
              <td>
                <select
                    v-model.number="offers[s.id].warranty"
                    @change="handleWarrantyChange(s.id, $event)"
                    class="form-select"
                >
                  <option :value="null">Без гарантии</option>
                  <option :value="1">1 мес.</option>
                  <option :value="3">3 мес.</option>
                  <option :value="6">6 мес.</option>
                  <option :value="12">12 мес.</option>
                  <option :value="24">24 мес.</option>
                  <option :value="36">36 мес.</option>
                  <option value="custom">Другое...</option>
                </select>

                <input
                    v-if="customWarrantyInputs[s.id]"
                    type="number"
                    min="1"
                    :value="offers[s.id].warranty"
                    @input="e => offers[s.id].warranty = +e.target.value"
                    @blur="() => customWarrantyInputs[s.id] = false"
                    class="form-input mt-1"
                    placeholder="мес"
                    style="width: 80px"
                />
              </td>
              <td>
                <select v-model="offers[s.id].condition" class="form-select">
                  <option value="new">Новый</option>
                  <option value="used">Б/у</option>
                  <option value="refurbished">Восстановленный</option>
                </select>
              </td>
              <td v-if="!isEditingSingle">
                <select v-model.number="offers[s.id].status" class="form-select">
                  <option :value="0">Неактивно</option>
                  <option :value="1">Активно</option>
                  <option :value="2">На модерации</option>
                </select>
              </td>
            </tr>
            </tbody>
          </table>

          <button
              type="submit"
              class="btn-primary"
              :disabled="!canSubmit"
          >
            {{ isEditingSingle ? 'Обновить' : 'Сохранить' }}
          </button>
        </div>



        <div v-else-if="loading" class="loading">Загрузка...</div>
        <div v-else class="no-product">Выберите категорию и введите информацию о товаре</div>
        <div class="image-upload-section" :class="{ disabled: !isOfferSaved }">
          <h3>Изображения товара</h3>
          <p v-if="!isOfferSaved" class="hint">Сначала сохраните предложение.</p>

          <div v-else>
            <div class="image-grid">
              <!-- Существующие изображения -->
              <div v-for="img in uploadedImages" :key="img.id" class="image-item">
                <img :src="img.preview_url" :alt="img.filename || ''" />
                <label>
                  <input
                      type="radio"
                      name="main-image"
                      :checked="img.is_main"
                      @change="setMainImage(img.id)"
                  /> Главное
                </label>
              </div>

              <!-- Кнопка загрузки (если <5 изображений) -->
              <div v-if="uploadedImages.length < 5" class="upload-placeholder">
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    multiple
                    @change="handleFileSelect"
                    style="display: none"
                />
                <button type="button" @click="$refs.fileInput?.click()" class="btn-secondary">
                  + Добавить фото
                </button>
              </div>
            </div>
          </div>
        </div>


      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRouter, useRoute } from 'vue-router'
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import http from "@/services/api/http"
import CategorySelector from '../components/CategorySelector.vue'
import ProductSearch from '../components/ProductSearch.vue'
import BrandSelector from '../components/BrandSelector.vue'
import AttributesForm from '../components/AttributesForm.vue'
import { useCatalogSelection } from '../composables/useCatalogSelection'
import { useVendorStore } from '../stores/vendor'
import {AttributeDef} from "../services/api/categoryAttributes";
import { useSearch } from '../composables/useSearch'
import { useProductImages } from '../composables/useProductImages'


interface ImageRecord {
  id: number
  storage_path: string
  filename: string | null
  is_main: boolean
  sort_order: number
  preview_url: string
}
// image
const isOfferSaved = ref(false)
const savedEntityType = ref<'global_product' | 'offer' | null>(null)
const savedEntityId = ref<number | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const {
  images: uploadedImages,
  loadImages,
  requestUploadUrls,
  confirmImages,
  setMainImage,
} = useProductImages()

// === Router & Store ===
const router = useRouter()
const route = useRoute()
const vendorStore = useVendorStore()

const searchQuery = ref('')

const catalog = useCatalogSelection()

const { categoryId, brandId /*, productId, product, attributes */ } = catalog


const searchFilters = computed(() => ({
  category_id: categoryId.value
}));

const { results: searchResults, loading: searchLoading } = useSearch(searchQuery, searchFilters);
// === Catalog Selection ===


// === State ===
const loading = ref(false)
const customWarrantyInputs = ref<Record<number, boolean>>({})

// --- Новые поля ---
const nonVariantAttributes = ref<AttributeDef[]>([])
const variantAttributes = ref<AttributeDef[]>([])
const nonVariantAttributeValues = ref<Record<string, any>>({})
const skuAttributeValues = ref<AttributeValue[]>([])
const productName = ref('')
const gtin = ref('')
const modelNumber = ref('')
const globalProductId = ref<number | null>(null)
const attributeOptions = ref<Record<number, any[]>>({}) // Кэш опций атрибутов

// --- Состояния для SKU/Offers ---
const skus = ref<any[]>([])
const selectedSkuIds = ref<number[]>([])
const offers = ref<Record<number, any>>({})
const isCreatingSku = ref(false)

// === Computed ===
const isEditingSingle = computed(() => {
  const id = route.params.id
  return !!id && id !== '' && !isNaN(Number(id))
})

const offerId = computed(() => (isEditingSingle.value ? Number(route.params.id) : null))

const canSubmit = computed(() => {
  return isEditingSingle.value
      ? skus.value.length > 0
      : selectedSkuIds.value.length > 0 && globalProductId.value
})

// === Watchers ===
watch(categoryId, async (newCategoryId) => {
  if (newCategoryId) {

    // Сброс состояния при смене категории
    globalProductId.value = null
    skus.value = []
    selectedSkuIds.value = []
    offers.value = {}
    // variantAttributes.value = []
    attributeOptions.value = {}
    nonVariantAttributeValues.value = {}
    productName.value = ''
    gtin.value = ''
    modelNumber.value = ''



  }
})

// === Methods ===
const goBack = () => router.push('/vendor/dashboard')

const onProductSelect = (product: Product) => {
  // Заполняем данные из выбранного товара
  globalProductId.value = product.product_id; // ← ваш Product содержит product_id
  productName.value = product.product_name;
  brandId.value = product.brand_id;
  gtin.value = product.flat_attributes?.gtin || product.gtin || '';
  modelNumber.value = product.flat_attributes?.model_number || '';

  // non-variant атрибуты: берём из flat_attributes
  nonVariantAttributeValues.value = { ...(product.flat_attributes || {}) };

  // Очищаем searchQuery, чтобы не мешало
  searchQuery.value = product.product_name;

  // Загружаем существующие SKU и офферы (если нужно)
  loadSkusAndOffersForGlobalProduct(product.product_id);
};

const clearSelectedProduct = () => {
  globalProductId.value = null;
  productName.value = '';
  brandId.value = null;
  gtin.value = '';
  modelNumber.value = '';
  nonVariantAttributeValues.value = {};
  searchQuery.value = '';
  skus.value = [];
  offers.value = {};
  selectedSkuIds.value = [];
};

const updateSkuAttributeValues = (vals: AttributeValue[]) => {
  skuAttributeValues.value = vals
}

watch(() => catalog.attributes.value, async (newAttributes) => {
  if (!newAttributes || newAttributes.length === 0) {
    nonVariantAttributes.value = []
    variantAttributes.value = []
    return
  }

  // Распределяем атрибуты
  nonVariantAttributes.value = newAttributes.filter(attr => !attr.is_variant)
  variantAttributes.value = newAttributes.filter(attr => attr.is_variant)


  if (categoryId.value) {
    await loadAttributeOptions(categoryId.value, newAttributes)
  }
}, { immediate: true, deep: true })

const loadAttributeOptions = async (catId: number, allAttrs: AttributeDef[]) => {
  const selectAttrIds = allAttrs
      .filter(attr => attr.type === 'select')
      .map(attr => attr.id)

  if (selectAttrIds.length > 0) {
    try {
      const optionsResponse = await http.get('/vendor-product/get-category-attribute-options', {
        params: { category_id: catId, attribute_ids: selectAttrIds.join(',') }
      })
      const options = optionsResponse.data.items || []
      const groupedOptions: Record<number, any[]> = {}
      options.forEach((opt: any) => {
        if (!groupedOptions[opt.attribute_id]) {
          groupedOptions[opt.attribute_id] = []
        }
        groupedOptions[opt.attribute_id].push(opt)
      })
      attributeOptions.value = groupedOptions
    } catch (e) {
      console.error('Ошибка загрузки опций', e)
    }
  }
}


// Имитируем fetchCategoryAttributes, используя catalog.attributes
const fetchCategoryAttributes = async (catId: number) => {
  // catalog.attributes уже загружены для текущей categoryId
  // если catId не совпадает с catalog.categoryId, нужно загрузить заново
  // но в нашем случае, watch(categoryId) вызывает loadCategoryAttributes,
  // и catalog.attributes уже содержит атрибуты для текущей categoryId
  if (catId === catalog.categoryId.value) {
    let cat =catalog.attributes.value;
    return catalog.attributes.value;
  } else {

    console.warn("Loading attributes for catId different from catalog.categoryId might not be intended.");

    return catalog.attributes.value;
  }
};

const getAttributeOptions = (attrId: number) => {
  return attributeOptions.value[attrId] || []
}

const findOrCreateGlobalProduct = async () => {
  if (!categoryId.value || !productName.value) {
    alert('Категория и название товара обязательны.')
    return
  }

  const payload = {
    category_id: categoryId.value,
    brand_id: brandId.value,
    product_name: productName.value,
    gtin: gtin.value || null,
    model_number: modelNumber.value || null,
    non_variant_attributes: nonVariantAttributeValues.value,
    // variant_attributes и offer_data не передаём сейчас
  }

  try {
    const response = await http.post('/vendor-product/create-or-update', payload)
    if (response.data.success) {
      globalProductId.value = response.data.data.global_product_id
      alert('Товар найден или создан успешно.')
    } else {
      alert('Ошибка: ' + response.data.error)
    }
  } catch (err) {
    console.error('Ошибка поиска/создания товара:', err)
    alert('Не удалось найти или создать товар.')
  }
}

const handleCreateSku = async () => {
  if (!globalProductId.value) {
    alert('Сначала найдите или создайте товар.')
    return
  }

  // Валидация вариативных атрибутов (аналогично OfferBulkImportService)
  const errors = validateSkuAttributes()
  if (errors.length) {
    alert('Заполните обязательные атрибуты SKU: ' + errors.join(', '))
    return
  }

  const payload = {
    category_id: categoryId.value,
    brand_id: brandId.value,
    product_name: productName.value,
    gtin: gtin.value || null,
    model_number: modelNumber.value || null,
    non_variant_attributes: nonVariantAttributeValues.value,
    variant_attributes: skuAttributeValues.value.reduce((acc, val) => {
      const attrDef = variantAttributes.value.find(a => a.id === val.attribute_id)
      if (attrDef) {
        let value;
        if (val.type === 'select') {
          // Используем attributeOptions.value для поиска строкового значения по ID опции
          const optionValue = attributeOptions.value[val.attribute_id]?.find(opt => opt.id === val.attribute_option_id)?.value;
          value = optionValue; // Будет строковое значение или undefined, если не найдено
        } else {
          // Для других типов выбираем соответствующее поле
          value = val[`value_${val.type}`];
        }
        // Присваиваем значение по *имени* атрибута, если значение определено
        if (value !== undefined && value !== null) {
          acc[attrDef.name] = value;
        }
      }
      return acc;
    }, {} as Record<string, any>),

  }


  isCreatingSku.value = true
  try {

    const response = await http.post('/vendor-product/create-or-update', payload)
    console.log("Raw response from server (SKU creation):", response);

    if (response.data && response.data.success) {
      // Проверяем, что response.data.data существует и является объектом
      if (response.data.data && typeof response.data.data === 'object') {
        const { sku_id } = response.data.data // <-- Получаем только sku_id


        if ( sku_id ) {

          const newSkuStub = {
            id: sku_id,
            variant_hash: "", // Серверный хэш, не важно для UI
            variant_values: skuAttributeValues.value.reduce((acc, val) => {
              // Создаем отображение для UI, аналогично getVariantLabel
              const attrDef = variantAttributes.value.find(a => a.id === val.attribute_id);
              if (attrDef) {
                let valStr = '';
                if (val.type === 'select') {
                  const opt = attributeOptions.value[val.attribute_id]?.find(o => o.id === val.attribute_option_id);
                  valStr = opt ? opt.value : '';
                } else {
                  valStr = String(val[`value_${val.type}`] || '');
                }
                acc.push({ name: attrDef.name, value: valStr, type: val.type });
              }
              return acc;
            }, [] as any[]), // Типизировать как в вашем API
            // ... другие поля SKU, если нужны
          };

          // Проверяем, нет ли уже такого SKU
          if (!skus.value.some(s => s.id === sku_id)) {
            skus.value.push(newSkuStub);
          }

          // Инициализируем пустой/шаблонный offer в offers.value
          if (!offers.value[sku_id]) {
            offers.value[sku_id] = {
              sku_id: sku_id,
              vendor_id: vendorStore.vendorId ?? 1,
              price: 0, // Продавец заполнит
              stock: 0, // Продавец заполнит
              warranty: null,
              condition: 'new',
              status: 2, // На модерации
              sort_order: 0,
              vendor_sku: null, // Продавец заполнит
            };
          }

          // Выбираем только что созданный SKU
          selectedSkuIds.value = [sku_id];

          alert('SKU успешно создан! Пожалуйста, заполните данные предложения (цена, остаток и т.д.) и нажмите "Сохранить".');
          // resetSkuForm() // Сброс формы атрибутов SKU можно добавить, если нужно
        } else {
          console.error("Invalid sku_id in response.data.", { sku_id });
          alert('Ошибка: Некорректный ID SKU в ответе сервера.');
        }
      } else {
        console.error("Response data structure for SKU creation is invalid:", response.data);
        alert('Ошибка: Некорректная структура данных ответа сервера (SKU).');
      }
    } else {
      console.error("Server responded with error or no success flag for SKU creation:", response.data);
      alert('Ошибка: ' + (response.data?.error || 'Неизвестная ошибка сервера (SKU)'));
    }
  } catch (err) {
    console.error('Ошибка создания SKU:', err)
    alert('Не удалось создать SKU.')
  } finally {
    isCreatingSku.value = false
  }
}



const validateSkuAttributes = (): string[] => {
  const errors: string[] = []
  for (const attr of variantAttributes.value) {
    const v = skuAttributeValues.value.find(x => x.attribute_id === attr.id)
    if (!attr.is_required) continue

    if (!v) {
      errors.push(attr.name)
      continue
    }

    // Проверяем по типу атрибута
    if (attr.type === 'select') {
      // Для select проверяем attribute_option_id
      if (v.attribute_option_id === null || v.attribute_option_id === undefined || v.attribute_option_id === '') {
        errors.push(attr.name)
      }
    } else {
      // Для других типов проверяем соответствующее поле
      const valueField = `value_${v.type}`;
      if (v[valueField] === null || v[valueField] === undefined || v[valueField] === '') {
        errors.push(attr.name)
      }
    }
  }
  return errors
}

// const resetSkuForm = () => {
//   skuAttributeValues.value = []
//   newSkuCode.value = ''
//   newSkuBarcode.value = ''
// }

const loadSkusAndOffersForGlobalProduct = async (gpId: number) => {
  try {
    const response = await http.get(`/vendor-product/get-skus-and-offers`, {
      params: { global_product_id: gpId } // Передаём как GET параметр
    })

    if (response.data.success) {
      const data = response.data.data
      skus.value = data.skus || []
      offers.value = data.offers || {}
      selectedSkuIds.value = data.selected_sku_ids || []
      console.log("Загружены SKU и Offers:", data) // Для отладки
    } else {
      console.error('Ошибка загрузки SKU и Offers:', response.data.error)
      skus.value = []
      offers.value = {}
      selectedSkuIds.value = []
      alert('Ошибка загрузки SKU и Offers: ' + response.data.error)
    }
  } catch (err) {
    console.error('Ошибка при вызове API загрузки SKU и Offers:', err)
    skus.value = []
    offers.value = {}
    selectedSkuIds.value = []
    alert('Не удалось загрузить SKU и Offers.')
  }
}

const setMassPrice = (val: number) => {
  selectedSkuIds.value.forEach(id => {
    if (offers.value[id]) offers.value[id].price = val;
  });
}

const setMassStock = (val: number) => {
  selectedSkuIds.value.forEach(id => {
    if (offers.value[id]) offers.value[id].stock = val;
  });
}

const handleWarrantyChange = (skuId: number, event: Event) => {
  const value = (event.target as HTMLSelectElement).value
  customWarrantyInputs.value[skuId] = value === 'custom'
}

const getVariantLabel = (sku: any): string => {
  if (!sku.variant_values?.length) return ''
  // Аналогично существующей логике
  const attrMap = new Map(variantAttributes.value.map(attr => [attr.name, attr]))
  return sku.variant_values
      .map((v: any) => {
        const attr = attrMap.get(v.name)
        if (!attr) return String(v.value)

        if (v.type === 'select' && v.value && attr?.options) {
          const option = attr.options.find((opt: any) => opt.label === v.value)
          return option ? option.label : v.value
        }
        return v.value !== null && v.value !== undefined ? String(v.value) : '?'
      })
      .filter(Boolean)
      .join(' / ')
}

const toggleSelectAll = () => {
  selectedSkuIds.value = selectedSkuIds.value.length === skus.value.length ? [] : skus.value.map(s => s.id)
}

const saveOffers = async () => {
  if (!canSubmit.value) return

  const payload = isEditingSingle.value
      ? [offers.value[selectedSkuIds.value[0]]]
      : selectedSkuIds.value.map(id => offers.value[id]).filter(Boolean)

  if (!payload.length) return

  try {
    const response = await http.post('/vendor-product/create-or-update', {
      category_id: categoryId.value,
      brand_id: brandId.value,
      product_name: productName.value,
      gtin: gtin.value || null,
      model_number: modelNumber.value || null,
      non_variant_attributes: nonVariantAttributeValues.value,
       // variant_attributes не передаём, если SKU уже создан
      offer_data: payload[0] // или массив, если нужно обновить несколько
    })

    if (response.data.success) {
      const { global_product_id, offer_id } = response.data.data

      // Определяем тип сущности
      if (isEditingSingle.value && offer_id) {
        savedEntityType.value = 'offer'
        savedEntityId.value = offer_id
      } else {
        savedEntityType.value = 'global_product'
        savedEntityId.value = global_product_id
      }

      isOfferSaved.value = true
      await loadImages(savedEntityType.value!, savedEntityId.value!)

    } else {
      alert('Ошибка сохранения: ' + response.data.error)
    }
  } catch (err) {
    console.error('Ошибка сохранения:', err)
    alert('Не удалось сохранить предложения.')
  }
}

const handleFileSelect = async (e: Event) => {
  const input = e.target as HTMLInputElement
  const files = Array.from(input.files || [])

  if (uploadedImages.value.length + files.length > 5) {
    alert('Можно загрузить не более 5 изображений')
    return
  }
  input.value = '';

  const fileNames = files.map(f => f.name)
  try {
    const  urls  = await requestUploadUrls(
        savedEntityType.value!,
        savedEntityId.value!,
        fileNames
    )

    const uploadPromises = files.map(async (file) => {
      const meta = urls[file.name]
      if (!meta) {
        throw new Error(`Пресигнатура не найдена для файла: ${file.name}`)
      }


      const { upload_url, storage_path } = meta
      const response = await fetch(upload_url, {
        method: 'PUT',
        body: file,
        headers: { 'Content-Type': file.type || 'application/octet-stream' }
      })

      if (!response.ok) {
        throw new Error(`MinIO error: ${response.status} ${response.statusText}`)
      }
      return { storage_path, filename: file.name }
    })

    const uploadResults = await Promise.all(uploadPromises)

    await confirmImages(
        savedEntityType.value!,
        savedEntityId.value!,
        uploadResults
    )

    await loadImages(savedEntityType.value!, savedEntityId.value!)
  } catch (err) {
    console.error('Ошибка загрузки:', err)
    alert('Не удалось загрузить изображения: ' + (err instanceof Error ? err.message : 'неизвестная ошибка'))
  }
}


onMounted(async () => {
  if (isEditingSingle.value) {
    // Загрузка данных для редактирования (реализовать отдельно)
    // loadOfferContext() // Старая логика
    // Нужно реализовать загрузку GlobalProduct, SKU, Offer по offerId
  }
})
</script>

<style scoped>
/* Ваши существующие стили */
.form-group {
  margin-bottom: 1rem;
}
.required {
  color: red;
}
.non-variant-attributes-section, .sku-section {
  border: 1px solid #ccc;
  padding: 1rem;
  margin: 1rem 0;
}
.found-product-info {
  background-color: #e7f3ff;
  padding: 0.5rem;
  margin-top: 0.5rem;
}

.image-upload-section {
  margin-top: 2rem;
  padding: 1.5rem;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  background-color: #fafafa;
}

.image-upload-section.disabled {
  opacity: 0.6;
  pointer-events: none;
}

.image-upload-section h3 {
  margin-top: 0;
  margin-bottom: 1rem;
}

.image-upload-section .hint {
  color: #666;
  font-style: italic;
}

.image-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 16px;
  margin-top: 12px;
}

.image-item,
.upload-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 140px;
  border: 1px dashed #ccc;
  border-radius: 6px;
  background: white;
  position: relative;
}

.image-item img {
  max-width: 100%;
  max-height: 100px;
  object-fit: contain;
  margin-bottom: 6px;
}

.image-item label {
  font-size: 0.85rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 4px;
}

.upload-placeholder button {
  padding: 6px 12px;
  font-size: 0.9rem;
}


</style>

