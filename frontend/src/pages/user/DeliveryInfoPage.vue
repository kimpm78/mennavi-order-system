<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { ChevronLeft, Crown, LogOut, MapPin, Package, UserRound } from 'lucide-vue-next'
import { apiRequest, authHeaders } from '../../lib/api'
import { getCustomerToken } from '../../lib/authStorage'
import type { User } from '../../types/auth'

const props = defineProps<{
  user: User
  initialSection: AccountSection
  subscription?: UserSubscription | null
}>()

const emit = defineEmits<{
  back: []
  logout: []
  openOrderHistory: []
  openPlus: []
  updated: [user: User]
}>()

type AccountSection = 'profile' | 'orders' | 'delivery'
type UserSubscription = {
  status?: string
  current_period_end?: string | null
  cancel_at_period_end?: boolean
}
type OrderHistoryItem = {
  id: number
  product_name: string
  unit_price: number
  quantity: number
  subtotal: number
}
type OrderHistory = {
  id: number
  order_number: string
  total_amount: number
  order_status: string
  payment_method?: string | null
  ordered_at?: string | null
  items: OrderHistoryItem[]
}

const activeSection = ref<AccountSection>(props.initialSection)
const form = reactive({
  name: props.user.name ?? '',
  phone: props.user.phone ?? '',
  postal_code: props.user.postal_code ?? '',
  address: props.user.address ?? '',
})
const loading = ref(false)
const ordersLoading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const ordersErrorMessage = ref('')
const postalCodeLoading = ref(false)
const postalCodeMessage = ref('')
const orders = ref<OrderHistory[]>([])
const loadedOrders = ref(false)
let postalCodeTimer: number | undefined
let lastSearchedPostalCode = ''

const sectionTitle = computed(() => {
  if (activeSection.value === 'profile') {
    return '会員情報'
  }
  if (activeSection.value === 'orders') {
    return '注文履歴'
  }
  return '配送先情報'
})

const sectionLead = computed(() => {
  if (activeSection.value === 'profile') {
    return 'アカウントの基本情報を確認・編集します。'
  }
  if (activeSection.value === 'orders') {
    return 'これまでの注文内容と注文状況を確認します。'
  }
  return '注文時に利用するお名前、電話番号、住所を登録します。'
})
const isPlusActive = computed(() => {
  const subscription = props.subscription
  if (subscription?.status !== 'active') {
    return false
  }
  if (!subscription.current_period_end) {
    return true
  }
  return new Date(subscription.current_period_end).getTime() > Date.now()
})

const activeSubscription = computed(() => isPlusActive.value ? props.subscription ?? null : null)

const plusStatusLabel = computed(() => {
  if (!isPlusActive.value) {
    return '未加入'
  }

  return activeSubscription.value?.cancel_at_period_end ? '解約予約中' : '利用中'
})
const plusStatusMessage = computed(() => {
  if (!isPlusActive.value) {
    return '麺ナビ Plusに加入すると、配送料無料と注文割引を利用できます。'
  }

  if (activeSubscription.value?.cancel_at_period_end) {
    return '現在の利用期限まではPlus特典を利用できます。'
  }

  return 'Plus特典が注文時に自動で適用されます。'
})
const plusStatusClass = computed(() =>
  isPlusActive.value
    ? 'bg-red-700 text-white'
    : 'bg-neutral-100 text-neutral-600',
)
const plusActionLabel = computed(() =>
  isPlusActive.value ? '麺ナビ Plusへ移動' : '今すぐアップグレード',
)

watch(
  () => props.user,
  (user) => {
    form.name = user.name ?? ''
    form.phone = user.phone ?? ''
    form.postal_code = user.postal_code ?? ''
    form.address = user.address ?? ''
  },
)

watch(
  () => props.initialSection,
  (section) => {
    selectSection(section)
  },
)

watch(
  () => form.postal_code,
  (postalCode) => {
    postalCodeMessage.value = ''

    if (postalCodeTimer) {
      window.clearTimeout(postalCodeTimer)
    }

    const normalizedPostalCode = normalizePostalCode(postalCode)
    if (normalizedPostalCode.length !== 7) {
      lastSearchedPostalCode = ''
      return
    }

    if (normalizedPostalCode === lastSearchedPostalCode) {
      return
    }

    postalCodeTimer = window.setTimeout(() => {
      searchAddressByPostalCode(normalizedPostalCode)
    }, 400)
  },
)

onBeforeUnmount(() => {
  if (postalCodeTimer) {
    window.clearTimeout(postalCodeTimer)
  }
})

async function saveDeliveryInfo() {
  const token = getCustomerToken()
  if (!token) {
    errorMessage.value = '認証が必要です。'
    return
  }

  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await apiRequest<{ user: User }>('/me', {
      method: 'PATCH',
      headers: authHeaders(token),
      body: JSON.stringify({
        name: form.name,
        phone: form.phone || null,
        postal_code: form.postal_code || null,
        address: form.address || null,
      }),
    })

    emit('updated', response.user)
    successMessage.value = '配送先情報を保存しました。'
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : '保存に失敗しました。'
  } finally {
    loading.value = false
  }
}

async function selectSection(section: AccountSection) {
  activeSection.value = section
  errorMessage.value = ''
  successMessage.value = ''

  if (section === 'orders' && !loadedOrders.value) {
    await loadOrders()
  }
}

async function loadOrders() {
  const token = getCustomerToken()
  if (!token) {
    ordersErrorMessage.value = '認証が必要です。'
    return
  }

  ordersLoading.value = true
  ordersErrorMessage.value = ''

  try {
    const response = await apiRequest<{ orders: OrderHistory[] }>('/orders', {
      headers: authHeaders(token),
    })
    orders.value = response.orders
    loadedOrders.value = true
  } catch (error) {
    ordersErrorMessage.value = error instanceof Error ? error.message : '注文履歴の取得に失敗しました。'
  } finally {
    ordersLoading.value = false
  }
}

async function searchAddressByPostalCode(postalCode: string) {
  postalCodeLoading.value = true
  postalCodeMessage.value = ''
  lastSearchedPostalCode = postalCode

  try {
    const response = await apiRequest<{
      postal_code: string
      address: string
    }>(`/postal-code/${postalCode}`)

    form.postal_code = response.postal_code
    form.address = response.address
    postalCodeMessage.value = '郵便番号から住所を入力しました。番地・建物名を追記してください。'
  } catch (error) {
    postalCodeMessage.value = error instanceof Error ? error.message : '住所検索に失敗しました。'
    lastSearchedPostalCode = ''
  } finally {
    postalCodeLoading.value = false
  }
}

function normalizePostalCode(value: string) {
  return value.replace(/\D/g, '')
}

function formatPrice(price: number) {
  return `${price.toLocaleString('ja-JP')}円`
}

function formatOrderedAt(value?: string | null) {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

function formatSubscriptionEnd(value?: string | null) {
  if (!value) {
    return '未定'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '未定'
  }

  return new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(date)
}

function orderStatusLabel(status: string) {
  const labels: Record<string, string> = {
    received: '受付済み',
    cooking: '調理中',
    completed: '完了',
    canceled: 'キャンセル',
  }

  return labels[status] ?? status
}
</script>

<template>
  <main class="mx-auto w-full max-w-7xl px-5 py-6 md:px-8">
    <button
      class="mb-5 inline-flex items-center gap-2 text-sm font-black text-neutral-600 hover:text-red-700"
      type="button"
      @click="emit('back')"
    >
      <ChevronLeft class="h-4 w-4" />
      店舗一覧へ戻る
    </button>

    <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
      <aside class="self-start rounded-lg border border-neutral-200 bg-white p-3 shadow-sm">
        <div class="border-b border-neutral-100 px-4 py-4">
          <p class="text-xs font-black text-red-700">MY PAGE</p>
          <p class="mt-1 font-black tracking-normal">{{ user.name }}</p>
        </div>

        <nav class="mt-3 grid gap-1" aria-label="マイページメニュー">
          <button
            :class="[
              'flex items-center gap-3 rounded-md px-4 py-3 text-left text-sm font-black',
              activeSection === 'profile'
                ? 'bg-red-700 text-white'
                : 'text-neutral-600 hover:bg-red-50 hover:text-red-700',
            ]"
            type="button"
            @click="selectSection('profile')"
          >
            <UserRound class="h-5 w-5" />
            会員情報
          </button>
          <button
            :class="[
              'flex items-center gap-3 rounded-md px-4 py-3 text-left text-sm font-black',
              activeSection === 'orders'
                ? 'bg-red-700 text-white'
                : 'text-neutral-600 hover:bg-red-50 hover:text-red-700',
            ]"
            type="button"
            @click="emit('openOrderHistory')"
          >
            <Package class="h-5 w-5" />
            注文履歴
          </button>
          <button
            :class="[
              'flex items-center gap-3 rounded-md px-4 py-3 text-left text-sm font-black',
              activeSection === 'delivery'
                ? 'bg-red-700 text-white'
                : 'text-neutral-600 hover:bg-red-50 hover:text-red-700',
            ]"
            type="button"
            @click="selectSection('delivery')"
          >
            <MapPin class="h-5 w-5" />
            配送先情報
          </button>
          <button
            class="flex items-center gap-3 rounded-md px-4 py-3 text-left text-sm font-black text-neutral-600 hover:bg-red-50 hover:text-red-700"
            type="button"
            @click="emit('logout')"
          >
            <LogOut class="h-5 w-5" />
            ログアウト
          </button>
        </nav>
      </aside>

      <section class="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm md:p-8">
        <div class="flex items-start gap-4">
          <div class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-red-50 text-red-700">
            <MapPin class="h-6 w-6" />
          </div>
          <div>
            <h1 class="text-3xl font-black tracking-normal">{{ sectionTitle }}</h1>
            <p class="mt-2 text-sm font-bold leading-6 text-neutral-500">
              {{ sectionLead }}
            </p>
          </div>
        </div>

        <form
          v-if="activeSection === 'profile' || activeSection === 'delivery'"
          class="mt-8 grid gap-5"
          @submit.prevent="saveDeliveryInfo"
        >
          <label class="grid gap-2 text-sm font-bold text-neutral-600">
            お名前
            <input
              v-model="form.name"
              class="h-12 rounded-lg border border-neutral-200 px-4 text-neutral-900 outline-none focus:border-red-300 focus:ring-4 focus:ring-red-50"
              autocomplete="name"
              required
              type="text"
            />
          </label>

          <label
            v-if="activeSection === 'profile'"
            class="grid gap-2 text-sm font-bold text-neutral-600"
          >
            メールアドレス
            <input
              class="h-12 rounded-lg border border-neutral-200 bg-neutral-50 px-4 text-neutral-500 outline-none"
              :value="user.email"
              disabled
              type="email"
            />
          </label>

          <section
            v-if="activeSection === 'profile'"
            class="rounded-xl border border-red-100 bg-red-50/40 p-5"
          >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
              <div class="flex items-start gap-3">
                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white text-red-700 shadow-sm">
                  <Crown class="h-5 w-5" />
                </div>
                <div>
                  <p class="text-sm font-black text-red-700">麺ナビ Plus</p>
                  <h2 class="mt-1 text-xl font-black tracking-normal text-neutral-900">
                    {{ plusStatusLabel }}
                  </h2>
                  <p class="mt-2 text-sm font-bold leading-6 text-neutral-600">
                    {{ plusStatusMessage }}
                  </p>
                </div>
              </div>

              <span
                class="w-fit rounded-full px-3 py-1 text-xs font-black"
                :class="plusStatusClass"
              >
                {{ plusStatusLabel }}
              </span>
            </div>

            <div
              v-if="activeSubscription"
              class="mt-4 rounded-lg bg-white px-4 py-3 text-sm font-bold text-neutral-700"
            >
              <span class="text-neutral-500">現在の利用期限：</span>
              <span class="font-black text-neutral-900">
                {{ formatSubscriptionEnd(activeSubscription.current_period_end) }}
              </span>
            </div>

            <button
              class="mt-4 inline-flex h-11 w-fit items-center justify-center rounded-lg bg-red-700 px-5 text-sm font-black text-white hover:bg-red-800"
              type="button"
              @click="emit('openPlus')"
            >
              {{ plusActionLabel }}
            </button>
          </section>

          <div
            v-if="activeSection === 'delivery'"
            class="grid gap-5 md:grid-cols-2"
          >
            <label class="grid gap-2 text-sm font-bold text-neutral-600">
              電話番号
              <input
                v-model="form.phone"
                class="h-12 rounded-lg border border-neutral-200 px-4 text-neutral-900 outline-none focus:border-red-300 focus:ring-4 focus:ring-red-50"
                autocomplete="tel"
                type="tel"
                placeholder="09012345678"
              />
            </label>

            <label class="grid gap-2 text-sm font-bold text-neutral-600">
              郵便番号
              <input
                v-model="form.postal_code"
                class="h-12 rounded-lg border border-neutral-200 px-4 text-neutral-900 outline-none focus:border-red-300 focus:ring-4 focus:ring-red-50"
                autocomplete="postal-code"
                maxlength="10"
                type="text"
                placeholder="150-0041"
              />
              <span
                v-if="postalCodeLoading || postalCodeMessage"
                :class="[
                  'text-xs font-bold leading-5',
                  postalCodeMessage.includes('失敗') || postalCodeMessage.includes('見つかりません') || postalCodeMessage.includes('入力してください')
                    ? 'text-red-700'
                    : 'text-neutral-500',
                ]"
              >
                {{ postalCodeLoading ? '住所を検索しています...' : postalCodeMessage }}
              </span>
            </label>
          </div>

          <label
            v-if="activeSection === 'delivery'"
            class="grid gap-2 text-sm font-bold text-neutral-600"
          >
            住所
            <input
              v-model="form.address"
              class="h-12 rounded-lg border border-neutral-200 px-4 text-neutral-900 outline-none focus:border-red-300 focus:ring-4 focus:ring-red-50"
              autocomplete="street-address"
              type="text"
              placeholder="東京都渋谷区神南1-2-3"
            />
          </label>

          <p v-if="errorMessage" class="rounded-lg bg-red-50 px-4 py-3 text-sm font-black text-red-700">
            {{ errorMessage }}
          </p>
          <p v-if="successMessage" class="rounded-lg bg-emerald-50 px-4 py-3 text-sm font-black text-emerald-700">
            {{ successMessage }}
          </p>

          <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button
              class="rounded-full border border-neutral-200 px-6 py-3 text-sm font-black text-neutral-600 hover:bg-neutral-50"
              type="button"
              @click="emit('back')"
            >
              キャンセル
            </button>
            <button
              class="rounded-full bg-red-700 px-8 py-3 text-sm font-black text-white hover:bg-red-800 disabled:opacity-50"
              type="submit"
              :disabled="loading"
            >
            {{ loading ? '保存中...' : '保存する' }}
          </button>
        </div>
      </form>

        <section v-else class="mt-8">
          <p v-if="ordersErrorMessage" class="rounded-lg bg-red-50 px-4 py-3 text-sm font-black text-red-700">
            {{ ordersErrorMessage }}
          </p>

          <div v-if="ordersLoading" class="rounded-lg border border-neutral-200 px-5 py-12 text-center text-sm font-black text-neutral-500">
            読み込み中...
          </div>

          <div v-else-if="orders.length" class="grid gap-4">
            <article
              v-for="order in orders"
              :key="order.id"
              class="rounded-lg border border-neutral-200 p-5"
            >
              <div class="flex flex-col gap-3 border-b border-neutral-100 pb-4 md:flex-row md:items-center md:justify-between">
                <div>
                  <p class="text-xs font-black text-red-700">{{ orderStatusLabel(order.order_status) }}</p>
                  <h2 class="mt-1 text-lg font-black tracking-normal">{{ order.order_number }}</h2>
                  <p class="mt-1 text-sm font-bold text-neutral-500">{{ formatOrderedAt(order.ordered_at) }}</p>
                </div>
                <p class="text-xl font-black text-red-700">{{ formatPrice(order.total_amount) }}</p>
              </div>

              <div class="mt-4 grid gap-3">
                <div
                  v-for="item in order.items"
                  :key="item.id"
                  class="flex items-center justify-between gap-4 text-sm"
                >
                  <div>
                    <p class="font-black text-neutral-900">{{ item.product_name }}</p>
                    <p class="mt-1 font-bold text-neutral-500">
                      {{ formatPrice(item.unit_price) }} × {{ item.quantity }}
                    </p>
                  </div>
                  <p class="font-black text-neutral-900">{{ formatPrice(item.subtotal) }}</p>
                </div>
              </div>
            </article>
          </div>

          <div v-else class="rounded-lg border border-dashed border-neutral-300 px-6 py-14 text-center">
            <p class="text-lg font-black tracking-normal">注文履歴はまだありません</p>
            <p class="mt-2 text-sm font-bold text-neutral-500">注文が完了すると、ここに履歴が表示されます。</p>
          </div>
        </section>
      </section>
    </div>
  </main>
</template>
