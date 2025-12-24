<script setup lang="ts">
import CategorySelector from '../components/CategorySelector.vue';
import { toRefs } from 'vue';
import BrandSelector from '../components/BrandSelector.vue';
import ProductSelector from '../components/ProductSelector.vue';
import { useCatalogSelection } from '../composables/useCatalogSelection';
import { useOfferCreate } from '../composables/useOfferCreate';

const sel = useCatalogSelection();
const { categoryId, brandId, productId } = toRefs(sel);
const  {
  skus,
  selectedSkuIds,
  offers,
  hasProduct,
  setMassPrice,
  setMassStock,
  saveOffers
} = useOfferCreate({ productId: sel.productId });
</script>

<template>
  <form @submit.prevent="saveOffers">
    <CategorySelector v-model="categoryId" :fetcher="sel.fetchCategories" />
    <BrandSelector v-if="sel.isLeaf"
                   v-model="brandId"
                   :category-id="categoryId"
                   :fetcher="sel.fetchBrands"
                   :enable-search="true"
    />
    <ProductSelector v-if="sel.isLeaf && sel.brandId"
                     v-model="productId"
                     :category-id="categoryId"
                     :brand-id="brandId"
                     :fetcher="sel.fetchProducts" />

    <!-- Таблица SKU с массовыми операциями -->
    <div v-if="hasProduct">
      <div class="toolbar">
        <input type="number" placeholder="Цена для выбранных" @change="setMassPrice(+($event.target as HTMLInputElement).value)" />
        <input type="number" placeholder="Склад для выбранных" @change="setMassStock(+($event.target as HTMLInputElement).value)" />
      </div>

      <table>
        <thead>
        <tr>
          <th></th>
          <th>SKU</th>
          <th>Вариант</th>
          <th>Штрихкод</th>
          <th>Цена</th>
          <th>Склад</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="s in skus" :key="s.id">
          <td><input type="checkbox"
                     :value="s.id"
                     v-model="selectedSkuIds" /></td>
          <td>{{ s.id }}</td>
          <td>{{ s.variant_values ? Object.values(s.variant_values).join(' / ') : '' }}</td>
          <td>{{ s.barcode || '' }}</td>
          <td>
            <input type="number" v-model.number="offers[s.id].price" min="0" />
          </td>
          <td>
            <input type="number" v-model.number="offers[s.id].stock" min="0" />
          </td>
        </tr>
        </tbody>
      </table>

      <button type="submit" :disabled="!selectedSkuIds.length">Сохранить офферы</button>
    </div>
  </form>
</template>