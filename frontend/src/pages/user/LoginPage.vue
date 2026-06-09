<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AuthLayout from '../../components/auth/AuthLayout.vue'
import AuthMessage from '../../components/auth/AuthMessage.vue'
import { apiRequest, authHeaders } from '../../lib/api'
import { clearCustomerToken, getCustomerToken, setCustomerToken } from '../../lib/authStorage'
import type { AuthResponse, User } from '../../types/auth'
import HomePage from './HomePage.vue'

type AuthMode = 'login' | 'register'

defineProps<{
  goTo: (path: string) => void
}>()

const mode = ref<AuthMode>('login')
const loading = ref(false)
const checkingSession = ref(true)
const errorMessage = ref('')
const successMessage = ref('')
const user = ref<User | null>(null)

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
})

const isRegister = computed(() => mode.value === 'register')
const buttonLabel = computed(() => {
  if (loading.value) {
    return isRegister.value ? '登録中...' : 'ログイン中...'
  }

  return isRegister.value ? '会員登録' : 'ログイン'
})

onMounted(async () => {
  const token = getCustomerToken()

  if (!token) {
    checkingSession.value = false
    return
  }

  try {
    const response = await apiRequest<{ user: User }>('/me', {
      headers: authHeaders(token),
    })
    user.value = response.user
  } catch {
    clearCustomerToken()
  } finally {
    checkingSession.value = false
  }
})

function switchMode(nextMode: AuthMode) {
  mode.value = nextMode
  errorMessage.value = ''
  successMessage.value = ''
}

async function submitAuth() {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  const endpoint = isRegister.value ? '/register' : '/login'
  const payload = isRegister.value
    ? { ...form }
    : { email: form.email, password: form.password }

  try {
    const response = await apiRequest<AuthResponse>(endpoint, {
      method: 'POST',
      body: JSON.stringify(payload),
    })

    if (response.user.role !== 'user') {
      throw new Error('ユーザーアカウントでログインしてください。')
    }

    setCustomerToken(response.token)
    user.value = response.user
    successMessage.value = isRegister.value ? '会員登録が完了しました。' : 'ログインしました。'
    form.password = ''
    form.password_confirmation = ''
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : '処理に失敗しました。'
  } finally {
    loading.value = false
  }
}

async function logout() {
  const token = getCustomerToken()
  loading.value = true
  errorMessage.value = ''

  try {
    if (token) {
      await apiRequest('/logout', {
        method: 'POST',
        headers: authHeaders(token),
      })
    }
  } finally {
    clearCustomerToken()
    user.value = null
    loading.value = false
    successMessage.value = 'ログアウトしました。'
  }
}

function updateUser(nextUser: User) {
  user.value = nextUser
}
</script>

<template>
  <HomePage
    v-if="user"
    :user="user"
    :loading="loading"
    @update-user="updateUser"
    @logout="logout"
  />

  <div
    v-else-if="checkingSession"
    class="grid min-h-screen place-items-center bg-neutral-50 text-sm font-bold text-neutral-500"
  >
    読み込み中...
  </div>

  <AuthLayout
    v-else
    eyebrow="Mennavi Order"
    title="麺ナビ"
    lead="気分に合う一杯を見つけて、待たずにスマートに注文。お気に入りのラーメン体験をもっと身近に。"
    panel-label="Customer Account"
    :panel-title="isRegister ? '会員登録' : 'ログイン'"
  >
    <template #default>
      <div class="mode-tabs" role="tablist" aria-label="Authentication mode">
        <button type="button" :class="{ active: mode === 'login' }" @click="switchMode('login')">
          ログイン
        </button>
        <button
          type="button"
          :class="{ active: mode === 'register' }"
          @click="switchMode('register')"
        >
          会員登録
        </button>
      </div>

      <form class="auth-form" @submit.prevent="submitAuth">
        <label v-if="isRegister">
          お名前
          <input v-model="form.name" autocomplete="name" name="name" required type="text" />
        </label>

        <label v-if="isRegister">
          電話番号
          <input v-model="form.phone" autocomplete="tel" name="phone" type="tel" />
        </label>

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

        <label v-if="isRegister">
          パスワード確認
          <input
            v-model="form.password_confirmation"
            autocomplete="new-password"
            minlength="8"
            name="password_confirmation"
            required
            type="password"
          />
        </label>

        <AuthMessage type="error" :message="errorMessage" />
        <AuthMessage type="success" :message="successMessage" />

        <button class="primary-button" type="submit" :disabled="loading">
          {{ buttonLabel }}
        </button>

        <button class="text-link auth-switch-link" type="button" @click="goTo('/admin/login')">
          管理者ログインはこちら
        </button>
      </form>
    </template>
  </AuthLayout>
</template>
