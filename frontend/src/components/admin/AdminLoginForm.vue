<script setup lang="ts">
import { ref } from 'vue'
import { Eye, EyeOff } from 'lucide-vue-next'
import AuthMessage from '../auth/AuthMessage.vue'

type LoginForm = {
  email: string
  password: string
}

defineProps<{
  form: LoginForm
  loading: boolean
  errorMessage: string
  successMessage: string
}>()

const emit = defineEmits<{
  submit: []
  userLogin: []
}>()

const showPassword = ref(false)
</script>

<template>
  <form class="auth-form" @submit.prevent="emit('submit')">
    <label>
      メールアドレス
      <input v-model="form.email" autocomplete="email" name="email" required type="email" />
    </label>

    <label>
      パスワード
      <span class="password-field">
        <input
          v-model="form.password"
          autocomplete="current-password"
          minlength="8"
          name="password"
          required
          :type="showPassword ? 'text' : 'password'"
        />
        <button
          class="password-toggle"
          type="button"
          :aria-label="showPassword ? 'パスワードを非表示' : 'パスワードを表示'"
          @click="showPassword = !showPassword"
        >
          <EyeOff v-if="showPassword" class="h-5 w-5" />
          <Eye v-else class="h-5 w-5" />
        </button>
      </span>
    </label>

    <AuthMessage type="error" :message="errorMessage" />
    <AuthMessage type="success" :message="successMessage" />

    <button class="primary-button" type="submit" :disabled="loading">
      {{ loading ? 'ログイン中...' : '管理者ログイン' }}
    </button>

    <button class="text-link auth-switch-link" type="button" @click="emit('userLogin')">
      ユーザーログインはこちら
    </button>
  </form>
</template>
