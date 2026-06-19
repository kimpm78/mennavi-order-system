<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Eye, EyeOff } from 'lucide-vue-next'
import AuthLayout from '../../components/auth/AuthLayout.vue'
import AuthMessage from '../../components/auth/AuthMessage.vue'
import { apiRequest, authHeaders } from '../../lib/api'
import { clearCustomerToken, getCustomerToken, setCustomerToken } from '../../lib/authStorage'
import type { AuthResponse, User } from '../../types/auth'
import HomePage from './HomePage.vue'

type AuthMode = 'login' | 'register' | 'resetPassword'

const props = defineProps<{
  currentPath: string
  goTo: (path: string) => void
}>()

const mode = ref<AuthMode>('login')
const loading = ref(false)
const checkingSession = ref(true)
const errorMessage = ref('')
const successMessage = ref('')
const user = ref<User | null>(null)
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
})

const isRegister = computed(() => mode.value === 'register')
const isResetPassword = computed(() => mode.value === 'resetPassword')
const panelTitle = computed(() => {
  if (isResetPassword.value) {
    return 'パスワード変更'
  }

  return isRegister.value ? '会員登録' : 'ログイン'
})
const buttonLabel = computed(() => {
  if (loading.value) {
    if (isResetPassword.value) {
      return '変更中...'
    }

    return isRegister.value ? '登録中...' : 'ログイン中...'
  }

  if (isResetPassword.value) {
    return 'パスワードを変更'
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
    if (props.currentPath === '/login') {
      props.goTo('/stores')
    }
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
  showPassword.value = false
  showPasswordConfirmation.value = false
}

async function submitAuth() {
  if (isResetPassword.value) {
    await submitPasswordReset()
    return
  }

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
    if (props.currentPath === '/login') {
      props.goTo('/stores')
    }
    successMessage.value = isRegister.value ? '会員登録が完了しました。' : 'ログインしました。'
    form.password = ''
    form.password_confirmation = ''
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : '処理に失敗しました。'
  } finally {
    loading.value = false
  }
}

async function submitPasswordReset() {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await apiRequest<{ message: string }>('/password/reset', {
      method: 'POST',
      body: JSON.stringify({
        email: form.email,
        password: form.password,
        password_confirmation: form.password_confirmation,
      }),
    })

    mode.value = 'login'
    successMessage.value = response.message
    form.password = ''
    form.password_confirmation = ''
    showPassword.value = false
    showPasswordConfirmation.value = false
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'パスワード変更に失敗しました。'
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
    :current-path="props.currentPath"
    :go-to="props.goTo"
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
    :panel-title="panelTitle"
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
          {{ isResetPassword ? '新しいパスワード' : 'パスワード' }}
          <span class="password-field">
            <input
              v-model="form.password"
              :autocomplete="isRegister || isResetPassword ? 'new-password' : 'current-password'"
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

        <label v-if="isRegister || isResetPassword">
          {{ isResetPassword ? '新しいパスワード確認' : 'パスワード確認' }}
          <span class="password-field">
            <input
              v-model="form.password_confirmation"
              autocomplete="new-password"
              minlength="8"
              name="password_confirmation"
              required
              :type="showPasswordConfirmation ? 'text' : 'password'"
            />
            <button
              class="password-toggle"
              type="button"
              :aria-label="showPasswordConfirmation ? 'パスワード確認を非表示' : 'パスワード確認を表示'"
              @click="showPasswordConfirmation = !showPasswordConfirmation"
            >
              <EyeOff v-if="showPasswordConfirmation" class="h-5 w-5" />
              <Eye v-else class="h-5 w-5" />
            </button>
          </span>
        </label>

        <AuthMessage type="error" :message="errorMessage" />
        <AuthMessage type="success" :message="successMessage" />

        <button class="primary-button" type="submit" :disabled="loading">
          {{ buttonLabel }}
        </button>

        <button
          v-if="mode === 'login'"
          class="text-link auth-switch-link"
          type="button"
          @click="switchMode('resetPassword')"
        >
          パスワードをお忘れですか？
        </button>

        <button
          v-if="isResetPassword"
          class="text-link auth-switch-link"
          type="button"
          @click="switchMode('login')"
        >
          ログインへ戻る
        </button>

        <button
          v-if="!isRegister"
          class="text-link auth-switch-link"
          type="button"
          @click="props.goTo('/admin/login')"
        >
          管理者ログインはこちら
        </button>
      </form>
    </template>
  </AuthLayout>
</template>
