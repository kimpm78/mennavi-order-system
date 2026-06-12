<script setup lang="ts">
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
</script>

<template>
  <form class="auth-form" @submit.prevent="emit('submit')">
    <label>
      メールアドレス
      <input v-model="form.email" autocomplete="email" name="email" required type="email" />
    </label>

    <label>
      パスワード
      <input
        v-model="form.password"
        autocomplete="current-password"
        minlength="8"
        name="password"
        required
        type="password"
      />
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
