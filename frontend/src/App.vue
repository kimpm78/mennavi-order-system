<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'

type AuthMode = 'login' | 'register'

type User = {
  id: number
  name: string
  email: string
  created_at: string
}

const apiBaseUrl = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const tokenKey = 'mennavi_auth_token'

const mode = ref<AuthMode>('login')
const loading = ref(false)
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
  const token = localStorage.getItem(tokenKey)

  if (!token) {
    return
  }

  try {
    const response = await request<{ user: User }>('/me', {
      headers: authHeaders(token),
    })
    user.value = response.user
  } catch {
    localStorage.removeItem(tokenKey)
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
    const response = await request<{ user: User; token: string }>(endpoint, {
      method: 'POST',
      body: JSON.stringify(payload),
    })

    localStorage.setItem(tokenKey, response.token)
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
  const token = localStorage.getItem(tokenKey)
  loading.value = true
  errorMessage.value = ''

  try {
    if (token) {
      await request('/logout', {
        method: 'POST',
        headers: authHeaders(token),
      })
    }
  } finally {
    localStorage.removeItem(tokenKey)
    user.value = null
    loading.value = false
    successMessage.value = 'ログアウトしました。'
  }
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${apiBaseUrl}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...options.headers,
    },
  })

  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new Error(readErrorMessage(data))
  }

  return data
}

function authHeaders(token: string) {
  return {
    Authorization: `Bearer ${token}`,
  }
}

function readErrorMessage(data: unknown) {
  if (
    data &&
    typeof data === 'object' &&
    'errors' in data &&
    data.errors &&
    typeof data.errors === 'object'
  ) {
    const firstError = Object.values(data.errors as Record<string, string[]>)[0]?.[0]

    if (firstError) {
      return firstError
    }
  }

  if (data && typeof data === 'object' && 'message' in data && typeof data.message === 'string') {
    return data.message
  }

  return '通信に失敗しました。'
}
</script>

<template>
  <main class="app-shell">
    <section class="brand-panel" aria-label="Mennavi order system">
      <div>
        <p class="eyebrow">Mennavi Order</p>
        <h1>飲食店オンライン注文システム</h1>
        <p class="lead">来店客がスマートフォンから注文できるユーザー画面を作成中です。</p>
      </div>
      <div class="status-list">
        <span>Laravel API</span>
        <span>Vue User Screen</span>
        <span>PostgreSQL Ready</span>
      </div>
    </section>

    <section class="auth-panel" aria-label="Account">
      <template v-if="user">
        <div class="panel-header">
          <p class="eyebrow">Account</p>
          <h2>{{ user.name }}</h2>
          <p>{{ user.email }}</p>
        </div>

        <div class="account-summary">
          <div>
            <span>ユーザーID</span>
            <strong>#{{ user.id }}</strong>
          </div>
          <div>
            <span>登録日時</span>
            <strong>{{ new Date(user.created_at).toLocaleString() }}</strong>
          </div>
        </div>

        <p v-if="successMessage" class="message success">{{ successMessage }}</p>
        <button class="primary-button" type="button" :disabled="loading" @click="logout">
          ログアウト
        </button>
      </template>

      <template v-else>
        <div class="panel-header">
          <p class="eyebrow">Customer Account</p>
          <h2>{{ isRegister ? '会員登録' : 'ログイン' }}</h2>
        </div>

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

          <p v-if="errorMessage" class="message error">{{ errorMessage }}</p>
          <p v-if="successMessage" class="message success">{{ successMessage }}</p>

          <button class="primary-button" type="submit" :disabled="loading">
            {{ buttonLabel }}
          </button>
        </form>
      </template>
    </section>
  </main>
</template>
