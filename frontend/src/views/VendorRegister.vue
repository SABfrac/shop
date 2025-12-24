<template>
  <div class="register-form">
    <h2>Регистрация вендора</h2>
    <form @submit.prevent="handleRegister">
      <div class="form-group">
        <label for="name">Название компании *</label>
        <input
            id="name"
            v-model="form.name"
            type="text"
            required
            :class="{ 'error': errors.name }"
        />
        <span v-if="errors.name" class="error-text">{{ errors.name }}</span>
      </div>

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

      <div class="form-group">
        <label for="passport">Паспорт *</label>
        <input
            id="passport"
            v-model="form.passport"
            type="text"
            required
            :class="{ 'error': errors.passport }"
        />
        <span v-if="errors.passport" class="error-text">{{ errors.passport }}</span>
      </div>

      <button type="submit" :disabled="loading" class="btn-primary">
        {{ loading ? 'Регистрация...' : 'Зарегистрироваться' }}
      </button>

      <div v-if="message" class="message" :class="messageType">
        {{ message }}
      </div>

      <div class="form-footer">
        <router-link to="/vendor/login">Уже есть аккаунт? Войти</router-link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import http from '@/services/api/http'

const router = useRouter()

const form = reactive({
  name: '',
  email: '',
  password: '',
  passport: ''
})

const errors = ref({})
const loading = ref(false)
const message = ref('')
const messageType = ref('')

const handleRegister = async () => {
  try {
    loading.value = true
    errors.value = {}
    message.value = ''

    const response = await http.post('/vendors/register', {
      name: form.name,
      email: form.email,
      password: form.password,
      passport: form.passport
    })
    console.log(response)

    if (response.data.success) {
      messageType.value = 'success'
      message.value = 'Регистрация успешна! Проверьте ваш email для подтверждения.'
      // Очищаем форму
      Object.keys(form).forEach(key => {
        form[key] = ''
      })
    } else {
      errors.value = response.data.errors || {}
      messageType.value = 'error'
      message.value = response.data.message || 'Ошибка регистрации'
    }
  } catch (error) {
    messageType.value = 'error'
    message.value = 'Произошла ошибка при регистрации'
    console.error('Registration error:', error)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.register-form {
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