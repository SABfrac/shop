<template>
  <div>
    <h2>Создание продукта</h2>
    <form @submit.prevent>

      <CategorySelector v-model="categoryId"
                        :fetcher="fetchCategories"
      />

<!--       Показываем выбор бренда, только если категория — лист-->
      <BrandSelector v-if="isLeaf"
                     v-model="brandId"
                     :category-id="categoryId"
                     :fetcher="fetchBrands"
                     :creator="createBrand"
                     :enable-search="true"
      />

      <ProductSelector
          v-if="isLeaf && brandId"
          v-model="productId"
          :category-id="categoryId"
          :brand-id="brandId"
          :fetcher="fetchProducts"


      />

      <input v-model="product.name" placeholder="Название----" :readonly="!!productId"/>
      <input v-model="product.slug" placeholder="Slug" :readonly="!!productId"/>
      <textarea v-model="product.description" placeholder="Описание" :readonly="!!productId"></textarea>

      <AttributesForm
          ref="attrsForm"
          :attributes="attributes"
          :value="attributesValues"
          @save-attributes="setAttributes"/>

      <div class="actions">
        <!-- Создание SPU доступно только когда НЕ выбран существующий SPU -->
        <button type="button" @click="saveProduct" :disabled="!!productId">
          Создать SPU
        </button>

        <!-- Создание SKU доступно только когда выбран SPU -->
        <button type="button"
                :disabled="!canCreateSku"
                @click="handleSaveSku"
                title="Заполните обязательные атрибуты">
          Создать SKU
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import CategorySelector from '../components/CategorySelector.vue';
import BrandSelector from '../components/BrandSelector.vue';
import ProductSelector from '../components/ProductSelector.vue';
import AttributesForm from '../components/AttributesForm.vue';


import { useProductCreate } from '../composables/useProductCreate';

const {
  // state
  categoryId,
  brandId,
  productId,
  product,
  attributes,
  attributesValues,
  sku,

  // computed
  isLeaf,
  canCreateSku,

  // fetchers
  fetchCategories,
  fetchBrands,
  createBrand,
  fetchProducts,

  // mutations/actions
  setAttributes,
  saveProduct,
  saveSku
} = useProductCreate();

// Валидация формы атрибутов перед сохранением SKU остается в родителе:
const attrsForm = ref<any | null>(null);
const handleSaveSku = async () => {
  if (attrsForm.value?.submitForm) {
    await attrsForm.value.submitForm();
  }
  await saveSku();
};
</script>
