<script setup>
import { ref, onMounted } from 'vue'
import { getAttributes } from '@/services/api/attributes'

const data = ref(null)
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    const res = await getAttributes()
    data.value = res.data
  } catch (e) {
    error.value = e?.response?.data || e.message
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <h1>Attributes</h1>
    <div v-if="loading">Загрузка…</div>
    <pre v-else-if="error">{{ error }}</pre>
    <pre v-else>{{ data }}</pre>
  </div>
</template>