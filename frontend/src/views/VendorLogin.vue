<template>
  <div class="login-form">
    <h2>Вход для вендоров</h2>
    <form @submit.prevent="handleLogin">
      <div class="form-group">
        <label for="email">Email *</label>
        <input
            id="email"
            v-model="form.email"
            type="email"
            required
            :class="{ 'error': errors.email }"
        />
        <span v-if="errors.email" class="error-text">{{ errors.email }}</span>
      </div>

      <div class="form-group">
        <label for="password">Пароль *</label>
        <input
            id="password"
            v-model="form.password"
            type="password"
            required
            :class="{ 'error': errors.password }"
        />
        <span v-if="errors.password" class="error-text">{{ errors.password }}</span>
      </div>

      <button type="submit" :disabled="loading" class="btn-primary">
        {{ loading ? 'Вход...' : 'Войти' }}
      </button>

      <div v-if="message" class="message" :class="messageType">
        {{ message }}
      </div>

      <div class="form-footer">
        <router-link to="/vendor/register">Нет аккаунта? Зарегистрироваться</router-link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useVendorStore } from '@/stores/vendor'

const router = useRouter()
const vendorStore = useVendorStore()

const form = reactive({
  email: '',
  password: ''
})

const errors = ref({})
const loading = ref(false)
const message = ref('')
const messageType = ref('')

const handleLogin = async () => {
  try {
    loading.value = true
    errors.value = {}
    message.value = ''

    await vendorStore.login(form.email, form.password)

    messageType.value = 'success'
    message.value = 'Успешный вход!'

    // Перенаправляем на панель вендора
    setTimeout(() => {
      router.push('/vendor/dashboard')
    }, 1000)

  } catch (error) {
    messageType.value = 'error'
    if (error.response?.data?.message) {
      message.value = error.response.data.message
    } else {
      message.value = 'Неверный email или пароль'
    }

    // Ошибки валидации от сервера
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-form {
  max-width: 400px;
  margin: 2rem auto;
  padding: 2rem;
  border: 1px solid #ddd;
  border-radius: 8px;
  background: white;
}

.form-group {
  margin-bottom: 1rem;
}

label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 1rem;
}

input.error {
  border-color: #e74c3c;
}

.error-text {
  color: #e74c3c;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.btn-primary {
  width: 100%;
  padding: 0.75rem;
  background: #3498db;
  color: white;
  border: none;
  border-radius: 4px;
  font-size: 1rem;
  cursor: pointer;
}

.btn-primary:disabled {
  background: #bdc3c7;
  cursor: not-allowed;
}

.message {
  margin-top: 1rem;
  padding: 0.75rem;
  border-radius: 4px;
  text-align: center;
}

.message.success {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.message.error {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.form-footer {
  margin-top: 1rem;
  text-align: center;
}

.form-footer a {
  color: #3498db;
  text-decoration: none;
}

.form-footer a:hover {
  text-decoration: underline;
}
</style>