<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import {
  Banknote,
  Bell,
  ChartNoAxesColumn,
  ChevronLeft,
  ChevronRight,
  Clipboard,
  ClipboardList,
  Grid2X2,
  Plus,
  Settings,
  TrendingUp,
  UtensilsCrossed,
} from 'lucide-vue-next'
import AuthLayout from '../../components/auth/AuthLayout.vue'
import AuthMessage from '../../components/auth/AuthMessage.vue'
import { apiRequest, authHeaders } from '../../lib/api'
import { clearAdminToken, getAdminToken, setAdminToken } from '../../lib/authStorage'
import type { AuthResponse, User } from '../../types/auth'

defineProps<{
  goTo: (path: string) => void
}>()

const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const admin = ref<User | null>(null)
const isProfileMenuOpen = ref(false)
const activeAdminPage = ref('dashboard')

const form = reactive({
  email: '',
  password: '',
})

const navItems = [
  { key: 'dashboard', label: 'ダッシュボード', icon: Grid2X2 },
  { key: 'orders', label: '注文管理', icon: Clipboard },
  { key: 'menus', label: '店舗・メニュー管理', icon: UtensilsCrossed },
  { key: 'sales', label: '売上分析', icon: ChartNoAxesColumn },
  { key: 'settings', label: '設定', icon: Settings },
]

const activeAdminTitle = computed(
  () => navItems.find((item) => item.key === activeAdminPage.value)?.label ?? 'ダッシュボード',
)

type AdminOrderRow = {
  id?: number
  number?: string
  order_number?: string
  title: string
  note?: string
  type: string
  elapsed_minutes?: number
  status: string
  total_amount?: number
}

type AdminProductRow = {
  id: number
  name: string
  category: string
  price: number
  status: string
}

type AdminSalesRow = {
  label: string
  amount: number
  orders: number
  rate: string
}

type AdminSettingRow = {
  label: string
  value: string
}

const dashboardSummary = ref({
  today_orders: 0,
  today_sales: 0,
  today_orders_change_rate: 0,
  today_sales_change_rate: 0,
  average_cooking_minutes: 0,
  kitchen_load: 0,
})
const orders = ref<AdminOrderRow[]>([])
const deliveryNetworks = ref([
  { name: 'UberEats', count: 0, status: '停止中' },
  { name: 'Wolt', count: 0, status: '停止中' },
  { name: '出前館', count: 0, status: '停止中' },
])
const kitchenBars = ref([12, 18, 24, 30, 36, 42, 36, 30, 24, 18])
const menuRows = ref<AdminProductRow[]>([])
const salesRows = ref<AdminSalesRow[]>([])
const settingRows = ref<AdminSettingRow[]>([])
const adminPageLoading = ref(false)

const summaryCards = computed(() => [
  {
    label: '本日の注文数',
    value: dashboardSummary.value.today_orders.toLocaleString('ja-JP'),
    note: formatChangeRate(dashboardSummary.value.today_orders_change_rate),
    icon: ClipboardList,
    emphasis: true,
  },
  {
    label: '本日の売上',
    value: formatPrice(dashboardSummary.value.today_sales),
    note: formatChangeRate(dashboardSummary.value.today_sales_change_rate),
    icon: Banknote,
    emphasis: false,
  },
])

onMounted(async () => {
  const token = getAdminToken()

  if (!token) {
    return
  }

  try {
    const response = await apiRequest<{ user: User }>('/admin/me', {
      headers: authHeaders(token),
    })
    admin.value = response.user
    await loadAdminPage('dashboard')
  } catch {
    clearAdminToken()
  }
})

async function submitAdminLogin() {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await apiRequest<AuthResponse>('/admin/login', {
      method: 'POST',
      body: JSON.stringify(form),
    })

    setAdminToken(response.token)
    admin.value = response.user
    form.password = ''
    successMessage.value = '管理者としてログインしました。'
    await loadAdminPage('dashboard')
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : '処理に失敗しました。'
  } finally {
    loading.value = false
  }
}

async function selectAdminPage(page: string) {
  activeAdminPage.value = page
  await loadAdminPage(page)
}

async function loadAdminPage(page: string) {
  const token = getAdminToken()
  if (!token) {
    return
  }

  adminPageLoading.value = true
  errorMessage.value = ''

  try {
    if (page === 'dashboard') {
      const response = await apiRequest<{
        summary: typeof dashboardSummary.value
        orders: AdminOrderRow[]
        delivery_networks: Array<{ name: string; count: number; status: string }>
        kitchen_bars: number[]
      }>('/admin/dashboard', { headers: authHeaders(token) })

      dashboardSummary.value = response.summary
      orders.value = response.orders
      deliveryNetworks.value = response.delivery_networks
      kitchenBars.value = response.kitchen_bars
      return
    }

    if (page === 'orders') {
      const response = await apiRequest<{ orders: AdminOrderRow[] }>('/admin/orders', {
        headers: authHeaders(token),
      })
      orders.value = response.orders
      return
    }

    if (page === 'menus') {
      const response = await apiRequest<{ products: AdminProductRow[] }>('/admin/products', {
        headers: authHeaders(token),
      })
      menuRows.value = response.products
      return
    }

    if (page === 'sales') {
      const response = await apiRequest<{ summary: AdminSalesRow[]; bars: number[] }>('/admin/sales', {
        headers: authHeaders(token),
      })
      salesRows.value = response.summary
      kitchenBars.value = response.bars
      return
    }

    if (page === 'settings') {
      const response = await apiRequest<{ settings: AdminSettingRow[] }>('/admin/settings', {
        headers: authHeaders(token),
      })
      settingRows.value = response.settings
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : '管理データの取得に失敗しました。'
  } finally {
    adminPageLoading.value = false
  }
}

async function logout() {
  const token = getAdminToken()
  loading.value = true
  errorMessage.value = ''
  isProfileMenuOpen.value = false

  try {
    if (token) {
      await apiRequest('/logout', {
        method: 'POST',
        headers: authHeaders(token),
      })
    }
  } finally {
    clearAdminToken()
    admin.value = null
    loading.value = false
    successMessage.value = 'ログアウトしました。'
  }
}

function formatPrice(value: number) {
  return `¥${value.toLocaleString('ja-JP')}`
}

function formatChangeRate(value: number) {
  const sign = value >= 0 ? '+' : ''
  return `前日比 ${sign}${value}%`
}

function formatElapsed(minutes?: number) {
  const value = Math.max(0, Math.floor(minutes ?? 0))
  return `${Math.floor(value / 60).toString().padStart(2, '0')}:${(value % 60).toString().padStart(2, '0')}`
}

function orderStatusLabel(status: string) {
  const labels: Record<string, string> = {
    received: '待機中',
    cooking: '調理中',
    completed: '完了',
    canceled: '取消',
  }

  return labels[status] ?? status
}

function productStatusLabel(status: string) {
  const labels: Record<string, string> = {
    active: '販売中',
    sold_out: '売切',
    hidden: '非表示',
  }

  return labels[status] ?? status
}

function networkTone(status: string, index: number) {
  if (status === '停止中') {
    return 'bg-neutral-300'
  }

  return index === 0 ? 'bg-amber-700' : 'bg-red-700'
}
</script>

<template>
  <div v-if="admin" class="min-h-screen bg-[#fbf9f9] text-neutral-950">
    <div class="grid min-h-screen lg:grid-cols-[256px_minmax(0,1fr)]">
      <aside class="flex flex-col border-r border-red-100 bg-white px-4 py-8 lg:min-h-screen">
        <div>
          <p class="text-2xl font-black tracking-normal text-red-700">麺ナビ</p>
          <p class="mt-2 text-sm font-bold text-neutral-700">レストランマネージャー</p>
        </div>

        <nav class="mt-11 grid gap-2">
          <button
            v-for="item in navItems"
            :key="item.label"
            :class="[
              'flex h-10 items-center gap-3 rounded-lg px-4 text-sm font-black transition',
              activeAdminPage === item.key
                ? 'bg-red-700 text-white'
                : 'text-[#5c4644] hover:bg-red-50 hover:text-red-700',
            ]"
            type="button"
            @click="selectAdminPage(item.key)"
          >
            <component :is="item.icon" class="h-5 w-5" />
            {{ item.label }}
          </button>
        </nav>

        <button
          class="mt-10 flex h-12 items-center justify-center gap-2 rounded-lg bg-red-600 px-4 text-sm font-black text-white hover:bg-red-700 lg:mt-auto"
          type="button"
        >
          <Plus class="h-5 w-5" />
          期間限定メニュー追加
        </button>
      </aside>

      <div class="min-w-0">
        <header class="flex h-16 items-center justify-between border-b border-red-100 bg-white px-6 lg:px-10">
          <h1 class="text-2xl font-black tracking-normal">{{ activeAdminTitle }}</h1>
          <div class="flex items-center gap-5">
            <button
              class="grid h-10 w-10 place-items-center rounded-full text-neutral-800 hover:bg-red-50"
              type="button"
              aria-label="通知"
            >
              <Bell class="h-5 w-5" />
            </button>
            <div class="text-right">
              <p class="text-sm font-black">{{ admin.name }}</p>
              <p class="text-xs font-bold text-neutral-500">シフト責任者</p>
            </div>
            <div class="relative">
              <button
                class="grid h-10 w-10 place-items-center rounded-full bg-neutral-950 text-sm font-black text-white ring-2 ring-red-100 hover:bg-red-800"
                type="button"
                aria-label="プロフィールメニュー"
                @click="isProfileMenuOpen = !isProfileMenuOpen"
              >
                管
              </button>
              <div
                v-if="isProfileMenuOpen"
                class="absolute right-0 top-12 z-20 w-44 rounded-lg border border-red-100 bg-white py-2 shadow-xl"
              >
                <button
                  class="flex w-full items-center px-4 py-3 text-left text-sm font-black text-neutral-700 hover:bg-red-50 hover:text-red-700"
                  type="button"
                  :disabled="loading"
                  @click="logout"
                >
                  ログアウト
                </button>
              </div>
            </div>
          </div>
        </header>

        <main class="mx-auto grid w-full max-w-[1000px] gap-6 px-6 py-10 lg:px-10">
          <p
            v-if="errorMessage"
            class="rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm font-black text-red-700"
          >
            {{ errorMessage }}
          </p>
          <p
            v-if="adminPageLoading"
            class="rounded-lg border border-red-100 bg-white px-4 py-3 text-sm font-black text-neutral-500"
          >
            読み込み中...
          </p>

          <template v-if="activeAdminPage === 'dashboard'">
          <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(380px,2fr)]">
            <article
              v-for="card in summaryCards"
              :key="card.label"
              class="rounded-xl border border-red-200 bg-white p-5 shadow-sm"
            >
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-sm font-black text-[#5c4644]">{{ card.label }}</p>
                  <p
                    :class="[
                      'mt-4 text-4xl font-black tracking-normal',
                      card.emphasis ? 'text-red-700' : 'text-neutral-950',
                    ]"
                  >
                    {{ card.value }}
                  </p>
                  <p
                    :class="[
                      'mt-2 flex items-center gap-1 text-sm font-black',
                      card.emphasis ? 'text-amber-800' : 'text-neutral-500',
                    ]"
                  >
                    <TrendingUp v-if="card.emphasis" class="h-4 w-4" />
                    {{ card.note }}
                  </p>
                </div>
                <component :is="card.icon" class="h-6 w-6 text-neutral-500" />
              </div>
            </article>

            <article class="rounded-xl bg-red-700 p-7 text-white shadow-sm">
              <div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_1px_120px] md:items-center">
                <div>
                  <h2 class="text-2xl font-black tracking-normal">ピークタイム警告</h2>
                  <p class="mt-2 text-sm font-bold leading-6 text-white/90">
                    平均調理時間が延びています。追加のスタッフ配置を検討してください。
                  </p>
                </div>
                <div class="hidden h-12 bg-white/20 md:block" />
                <div class="text-center">
                  <p class="text-4xl font-black tracking-normal">{{ dashboardSummary.average_cooking_minutes }}分</p>
                  <p class="text-sm font-black">平均調理時間</p>
                </div>
              </div>
            </article>
          </section>

          <section class="overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-red-100 px-4 py-5 md:flex-row md:items-center md:justify-between">
              <h2 class="text-2xl font-black tracking-normal">対応中の注文一覧</h2>
              <div class="flex flex-wrap gap-2">
                <button class="rounded-full bg-neutral-200 px-5 py-2 text-sm font-black text-neutral-600" type="button">
                  すべて
                </button>
                <button class="rounded-full border border-red-200 px-5 py-2 text-sm font-black text-neutral-800" type="button">
                  新規注文
                </button>
                <button class="rounded-full border border-red-200 px-5 py-2 text-sm font-black text-neutral-800" type="button">
                  調理中
                </button>
              </div>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full min-w-[820px] text-left text-sm">
                <thead class="bg-[#f4f1f1] text-[#5c4644]">
                  <tr>
                    <th class="px-4 py-3 font-black">注文番号</th>
                    <th class="px-4 py-3 font-black">注文内容</th>
                    <th class="px-4 py-3 font-black">種別</th>
                    <th class="px-4 py-3 font-black">経過時間</th>
                    <th class="px-4 py-3 font-black">ステータス</th>
                    <th class="px-4 py-3 text-right font-black">操作</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="order in orders"
                    :key="order.order_number ?? order.number"
                    class="border-b border-red-100 last:border-b-0"
                  >
                    <td class="px-4 py-6 font-medium">{{ order.number ?? order.order_number }}</td>
                    <td class="px-4 py-6">
                      <p class="font-black">{{ order.title }}</p>
                      <p class="mt-1 text-xs font-bold text-neutral-500">{{ order.note ?? '-' }}</p>
                    </td>
                    <td class="px-4 py-6">
                      <span
                        :class="[
                          'rounded-full px-3 py-1 text-xs font-black',
                          order.type === '店内'
                            ? 'bg-amber-100 text-amber-800'
                            : 'bg-red-100 text-red-700',
                        ]"
                      >
                        {{ order.type }}
                      </span>
                    </td>
                    <td
                      :class="[
                        'px-4 py-6 font-black',
                        (order.elapsed_minutes ?? 0) >= 20
                          ? 'text-red-700'
                          : 'text-neutral-950',
                      ]"
                    >
                      <span v-if="(order.elapsed_minutes ?? 0) >= 20">⚠</span>
                      <span v-else>◷</span>
                      {{ formatElapsed(order.elapsed_minutes) }}
                    </td>
                    <td class="px-4 py-6">
                      <span
                        :class="[
                          'rounded-full px-4 py-1 text-xs font-black',
                          order.status === 'received'
                            ? 'bg-neutral-200 text-neutral-600'
                            : order.status === 'cooking'
                              ? 'bg-amber-700 text-white'
                              : 'bg-red-100 text-red-700',
                        ]"
                      >
                        {{ orderStatusLabel(order.status) }}
                      </span>
                    </td>
                    <td class="px-4 py-6 text-right">
                      <button
                        :class="[
                          'h-9 rounded-lg px-6 text-sm font-black',
                          order.status === 'cooking'
                            ? 'border border-red-700 bg-white text-red-700'
                            : 'bg-red-700 text-white',
                        ]"
                        type="button"
                      >
                        {{ order.status === 'cooking' ? '完了' : '調理開始' }}
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="flex items-center justify-between px-4 py-4 text-sm font-bold text-neutral-600">
              <p>42件中 15件を表示</p>
              <div class="flex items-center gap-3 text-neutral-900">
                <ChevronLeft class="h-5 w-5" />
                <span class="grid h-8 w-7 place-items-center rounded-md bg-red-700 text-white">1</span>
                <span>2</span>
                <span>3</span>
                <ChevronRight class="h-5 w-5" />
              </div>
            </div>
          </section>

          <section class="grid gap-4 lg:grid-cols-[minmax(320px,0.9fr)_minmax(0,1.3fr)]">
            <article class="rounded-xl border border-red-200 bg-white p-5 shadow-sm">
              <h2 class="text-2xl font-black tracking-normal">配送ネットワーク状況</h2>
              <div class="mt-5 grid gap-6">
                <div
                  v-for="(network, index) in deliveryNetworks"
                  :key="network.name"
                  class="flex items-center justify-between gap-5"
                >
                  <div class="flex items-center gap-3">
                    <span :class="['h-2 w-2 rounded-full', networkTone(network.status, index)]" />
                    <span class="font-bold">{{ network.name }}</span>
                  </div>
                  <span class="font-black">
                    {{ network.count ? `${network.count} 件 ${network.status}` : network.status }}
                  </span>
                </div>
              </div>
            </article>

            <article class="rounded-xl border border-red-200 bg-white p-5 shadow-sm">
              <div class="flex items-start justify-between">
                <h2 class="text-2xl font-black tracking-normal">キッチン稼働率</h2>
                <div class="text-right">
                  <p class="text-xs font-bold text-neutral-500">現在の負荷</p>
                  <p class="text-2xl font-black text-red-700">{{ dashboardSummary.kitchen_load }}%</p>
                </div>
              </div>
              <div class="mt-8 flex h-32 items-end gap-2">
                <div
                  v-for="(bar, index) in kitchenBars"
                  :key="index"
                  :class="[
                    'flex-1 rounded-t-sm',
                    index === 5 ? 'bg-red-700' : index < 5 ? 'bg-red-100' : 'bg-neutral-200',
                  ]"
                  :style="{ height: `${bar}%` }"
                />
              </div>
              <div class="mt-3 flex justify-between border-t border-neutral-200 pt-2 text-xs font-bold text-neutral-500">
                <span>10:00</span>
                <span>12:00（ピーク）</span>
                <span>14:00</span>
              </div>
            </article>
          </section>
          </template>

          <template v-else-if="activeAdminPage === 'orders'">
            <section class="overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm">
              <div class="flex flex-col gap-4 border-b border-red-100 px-5 py-5 md:flex-row md:items-center md:justify-between">
                <div>
                  <h2 class="text-2xl font-black tracking-normal">注文管理</h2>
                  <p class="mt-1 text-sm font-bold text-neutral-500">受付中・調理中・完了済みの注文を管理します。</p>
                </div>
                <button class="rounded-lg bg-red-700 px-5 py-3 text-sm font-black text-white" type="button">
                  新規注文を確認
                </button>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-sm">
                  <thead class="bg-[#f4f1f1] text-[#5c4644]">
                    <tr>
                      <th class="px-5 py-3 font-black">注文番号</th>
                      <th class="px-5 py-3 font-black">注文内容</th>
                      <th class="px-5 py-3 font-black">種別</th>
                      <th class="px-5 py-3 font-black">ステータス</th>
                      <th class="px-5 py-3 text-right font-black">操作</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="order in orders" :key="order.order_number ?? order.number" class="border-b border-red-100 last:border-b-0">
                      <td class="px-5 py-5 font-medium">{{ order.order_number ?? order.number }}</td>
                      <td class="px-5 py-5">
                        <p class="font-black">{{ order.title }}</p>
                        <p class="mt-1 text-xs font-bold text-neutral-500">{{ order.note }}</p>
                      </td>
                      <td class="px-5 py-5">
                        <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700">
                          {{ order.type }}
                        </span>
                      </td>
                      <td class="px-5 py-5">
                        <span class="rounded-full bg-neutral-200 px-4 py-1 text-xs font-black text-neutral-700">
                          {{ orderStatusLabel(order.status) }}
                        </span>
                      </td>
                      <td class="px-5 py-5 text-right">
                        <button class="h-9 rounded-lg bg-red-700 px-5 text-sm font-black text-white" type="button">
                          詳細
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>
          </template>

          <template v-else-if="activeAdminPage === 'menus'">
            <section class="rounded-xl border border-red-200 bg-white p-6 shadow-sm">
              <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                  <h2 class="text-2xl font-black tracking-normal">店舗・メニュー管理</h2>
                  <p class="mt-1 text-sm font-bold text-neutral-500">店舗情報、カテゴリ、販売メニューを編集します。</p>
                </div>
                <button class="rounded-lg bg-red-700 px-5 py-3 text-sm font-black text-white" type="button">
                  メニュー追加
                </button>
              </div>
              <div class="mt-6 grid gap-3">
                <article
                  v-for="menu in menuRows"
                  :key="menu.name"
                  class="grid gap-3 rounded-lg border border-red-100 p-4 md:grid-cols-[minmax(0,1fr)_120px_100px_100px]"
                >
                  <p class="font-black">{{ menu.name }}</p>
                  <p class="text-sm font-bold text-neutral-500">{{ menu.category }}</p>
                  <p class="font-black text-red-700">{{ formatPrice(menu.price) }}</p>
                  <p class="text-sm font-black text-emerald-700">{{ productStatusLabel(menu.status) }}</p>
                </article>
              </div>
            </section>
          </template>

          <template v-else-if="activeAdminPage === 'sales'">
            <section class="grid gap-4 lg:grid-cols-3">
              <article
                v-for="row in salesRows"
                :key="row.label"
                class="rounded-xl border border-red-200 bg-white p-6 shadow-sm"
              >
                <p class="text-sm font-black text-[#5c4644]">{{ row.label }}</p>
                <p class="mt-4 text-3xl font-black tracking-normal">{{ formatPrice(row.amount) }}</p>
                <div class="mt-4 flex items-center justify-between text-sm font-black">
                  <span class="text-neutral-500">{{ row.orders }}件</span>
                  <span class="text-red-700">{{ row.rate }}</span>
                </div>
              </article>
            </section>
            <section class="rounded-xl border border-red-200 bg-white p-6 shadow-sm">
              <h2 class="text-2xl font-black tracking-normal">売上推移</h2>
              <div class="mt-8 flex h-52 items-end gap-3">
                <div
                  v-for="(bar, index) in kitchenBars"
                  :key="index"
                  :class="['flex-1 rounded-t-sm', index === 5 ? 'bg-red-700' : 'bg-red-100']"
                  :style="{ height: `${bar + 20}%` }"
                />
              </div>
            </section>
          </template>

          <template v-else>
            <section class="rounded-xl border border-red-200 bg-white p-6 shadow-sm">
              <h2 class="text-2xl font-black tracking-normal">設定</h2>
              <p class="mt-1 text-sm font-bold text-neutral-500">店舗運用に必要な基本設定を管理します。</p>
              <div class="mt-6 grid gap-3">
                <article
                  v-for="setting in settingRows"
                  :key="setting.label"
                  class="flex items-center justify-between gap-5 rounded-lg border border-red-100 p-4"
                >
                  <span class="font-black">{{ setting.label }}</span>
                  <span class="text-sm font-bold text-neutral-500">{{ setting.value }}</span>
                </article>
              </div>
            </section>
          </template>
        </main>
      </div>
    </div>
  </div>

  <AuthLayout
    v-else
    eyebrow="Mennavi Admin"
    title="管理者ログイン"
    lead="商品、カテゴリ、注文情報を管理するための管理者専用画面です。"
    panel-label="Admin Account"
    panel-title="管理者ログイン"
  >
      <form class="auth-form" @submit.prevent="submitAdminLogin">
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

        <button class="text-link auth-switch-link" type="button" @click="goTo('/login')">
          ユーザーログインはこちら
        </button>
      </form>
  </AuthLayout>
</template>
