
<template>
  <div class="layout">
    <nav>
      <RouterLink to="/">Главная</RouterLink> |
      <RouterLink to="/attributes">Атрибуты(для теста ответа от api)</RouterLink> |
      <RouterLink to="/products">Создание товара</RouterLink> |
      <RouterLink to="/offers">Предложение продавца</RouterLink> |
      <RouterLink to="/vendor/register">Регистрация продавца</RouterLink> |
      <RouterLink to="/vendor/login">вход</RouterLink> |
      <RouterLink to="/offers/create">Создать предложение</RouterLink> |
      <RouterLink
          v-if="vendorStore.isAuthenticated"
          to="/vendor/dashboard"
          class="user-greeting"
      >
        Привет, {{ vendorStore.profile?.name }}!
      </RouterLink>
    </nav>
    <RouterView />
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import { useVendorStore } from '@/stores/vendor'


const vendorStore = useVendorStore()

// При монтировании приложения — проверяем, есть ли активная сессия
onMounted(() => {
  vendorStore.fetchProfile()
})

// Функция выхода
const logout = async () => {
  await vendorStore.logout()
}
</script>

