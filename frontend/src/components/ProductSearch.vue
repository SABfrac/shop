<template>
  <div>
    <input
        v-model="localQuery"
        :placeholder="placeholder"
        class="form-input"
    />
    <div v-if="loading">Поиск...</div>
    <div v-else-if="results.length" class="results">
      <div
          v-for="item in results"
          :key="item.id"
          @click="$emit('select', item)"
          class="result-item"
      >
        <strong>{{ item.product_name }}</strong>
        <div>Бренд: {{ item.brand_name }}</div>
        <div v-if="item.gtin">GTIN: {{ item.gtin }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Product } from '../types/product';
import { ref, computed, watch } from 'vue'

const props = defineProps<{
  modelValue: string;
  results: Product[];
  loading: boolean;
  placeholder?: string;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
  (e: 'select', product: Product): void;
}>();

const localQuery = computed({
  get() {
    return props.modelValue
  },
  set(val: string) {
    emit('update:modelValue', val) }
});
</script>