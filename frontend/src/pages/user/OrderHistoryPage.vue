<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Download, ReceiptText, SlidersHorizontal, Star, X } from 'lucide-vue-next'
import FallbackImage from '@/components/common/FallbackImage.vue'
import { downloadReceiptPdfFile } from '@/lib/receiptPdf'
import { apiRequest, authHeaders } from '../../lib/api'
import { getCustomerToken } from '../../lib/authStorage'

type OrderHistoryItem = {
  id: number
  product_id?: number
  product_name: string
  imagePath?: string | null
  selected_options?: SelectedOption[]
  unit_price: number
  quantity: number
  subtotal: number
}

type SelectedOption = {
  product_id?: number | null
  name: string
  price: number
}

type OrderHistory = {
  id: number
  order_number: string
  total_amount: number
  subtotal_amount?: number
  delivery_fee?: number
  membership_discount_rate?: number
  membership_discount_amount?: number
  delivery_discount_amount?: number
  applied_subscription_code?: string | null
  tax_rate?: number
  tax_amount?: number
  earned_points?: number
  receipt_type?: string
  order_status: string
  payment_status?: string
  payment_method?: string | null
  delivery_staff_name?: string | null
  store_name?: string | null
  store_invoice_number?: string | null
  review?: {
    id: number
    rating: number
    content?: string | null
  } | null
  ordered_at?: string | null
  items: OrderHistoryItem[]
}

const orders = ref<OrderHistory[]>([])
const pointBalance = ref<number | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const actionMessage = ref('')
const selectedReceiptOrder = ref<OrderHistory | null>(null)
const selectedDetailOrder = ref<OrderHistory | null>(null)
const isUsageModalOpen = ref(false)
const isFilterOpen = ref(false)
const receiptPdfLoading = ref(false)
const filters = reactive({
  status: '',
  paymentMethod: '',
  receiptType: '',
  dateFrom: '',
  dateTo: '',
})
const reviewForms = reactive<Record<number, { rating: number; content: string }>>({})

const emit = defineEmits<{
  openPlus: []
}>()

const totalAmount = computed(() =>
  filteredOrders.value.reduce((total, order) => total + order.total_amount, 0),
)
const points = computed(() =>
  hasActiveFilters.value
    ? filteredOrders.value.reduce((total, order) => total + (order.earned_points ?? 0), 0)
    : pointBalance.value ?? orders.value.reduce((total, order) => total + (order.earned_points ?? 0), 0),
)
const filteredOrders = computed(() =>
  orders.value.filter((order) => {
    if (filters.status && order.order_status !== filters.status) {
      return false
    }

    if (filters.paymentMethod && order.payment_method !== filters.paymentMethod) {
      return false
    }

    if (filters.receiptType && order.receipt_type !== filters.receiptType) {
      return false
    }

    if (!order.ordered_at) {
      return !(filters.dateFrom || filters.dateTo)
    }

    const orderedDate = order.ordered_at.slice(0, 10)

    if (filters.dateFrom && orderedDate < filters.dateFrom) {
      return false
    }

    if (filters.dateTo && orderedDate > filters.dateTo) {
      return false
    }

    return true
  }),
)
const hasActiveFilters = computed(() =>
  Boolean(filters.status || filters.paymentMethod || filters.receiptType || filters.dateFrom || filters.dateTo),
)
const usageOrders = computed(() => filteredOrders.value)
const usageTotalAmount = computed(() =>
  usageOrders.value.reduce((total, order) => total + order.total_amount, 0),
)
const usageAverageAmount = computed(() =>
  usageOrders.value.length ? Math.round(usageTotalAmount.value / usageOrders.value.length) : 0,
)
const usageCompletedCount = computed(() =>
  usageOrders.value.filter((order) => order.order_status === 'completed').length,
)
const usageActiveCount = computed(() =>
  usageOrders.value.filter((order) => ['received', 'cooking', 'delivering'].includes(order.order_status)).length,
)
const usageDeliveryCount = computed(() =>
  usageOrders.value.filter((order) => order.receipt_type === 'delivery').length,
)
const usagePickupCount = computed(() =>
  usageOrders.value.filter((order) => order.receipt_type === 'pickup').length,
)
const usagePlusSavings = computed(() =>
  usageOrders.value.reduce(
    (total, order) =>
      total + (order.membership_discount_amount ?? 0) + (order.delivery_discount_amount ?? 0),
    0,
  ),
)
const thisMonthUsageAmount = computed(() => {
  const now = new Date()

  return usageOrders.value.reduce((total, order) => {
    if (!order.ordered_at) {
      return total
    }

    const orderedAt = new Date(order.ordered_at)

    if (orderedAt.getFullYear() !== now.getFullYear() || orderedAt.getMonth() !== now.getMonth()) {
      return total
    }

    return total + order.total_amount
  }, 0)
})

onMounted(() => {
  loadOrders()
})

async function loadOrders() {
  const token = getCustomerToken()
  if (!token) {
    errorMessage.value = '認証が必要です。'
    loading.value = false
    return
  }

  try {
    const response = await apiRequest<{
      orders: OrderHistory[]
      points?: {
        balance: number
        earned_total: number
      }
    }>('/orders', {
      headers: authHeaders(token),
    })
    orders.value = response.orders
    pointBalance.value = response.points?.balance ?? null
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : '注文履歴の取得に失敗しました。'
  } finally {
    loading.value = false
  }
}

function formatPrice(price: number) {
  return `¥${price.toLocaleString('ja-JP')}`
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

function orderStatusLabel(status: string) {
  const labels: Record<string, string> = {
    received: '受付済み',
    cooking: '調理中',
    delivering: '配送中',
    completed: '完了',
    canceled: 'キャンセル',
  }

  return labels[status] ?? status
}

function orderSummary(order: OrderHistory) {
  return order.items
    .map((item) => `${item.product_name} ×${item.quantity}`)
    .join('、')
}

function orderTitle(order: OrderHistory) {
  return order.items[0]?.product_name ?? '注文商品'
}

function orderImagePath(order: OrderHistory) {
  return order.items[0]?.imagePath ?? null
}

function receiptTypeLabel(type?: string) {
  const labels: Record<string, string> = {
    delivery: '配達',
    pickup: 'お持ち帰り',
  }

  return labels[type ?? ''] ?? '-'
}

function paymentMethodLabel(method?: string | null) {
  const labels: Record<string, string> = {
    card: 'クレジットカード',
    paypay: 'PayPay / QR決済',
    cash: '現金',
  }

  return labels[method ?? ''] ?? method ?? '-'
}

function paymentStatusLabel(status?: string) {
  const labels: Record<string, string> = {
    pending: '未決済',
    paid: '決済済み',
    failed: '決済失敗',
    partial_refunded: '一部返金',
    refunded: '返金済み',
  }

  return labels[status ?? ''] ?? '-'
}

function paymentStatusClass(status?: string) {
  const classes: Record<string, string> = {
    paid: 'bg-emerald-50 text-emerald-700',
    pending: 'bg-amber-50 text-amber-700',
    failed: 'bg-red-100 text-red-700',
    partial_refunded: 'bg-sky-50 text-sky-700',
    refunded: 'bg-neutral-200 text-neutral-700',
  }

  return classes[status ?? ''] ?? 'bg-neutral-100 text-neutral-500'
}

function orderSubtotal(order: OrderHistory) {
  return order.subtotal_amount ?? order.items.reduce((total, item) => total + item.subtotal, 0)
}

function membershipDiscountLabel(order: OrderHistory) {
  const rate = order.membership_discount_rate ?? 0
  return rate > 0 ? `麺ナビ Plus ${Number(rate).toLocaleString('ja-JP')}%割引` : '麺ナビ Plus 割引'
}

function effectiveDeliveryFee(order: OrderHistory) {
  return Math.max((order.delivery_fee ?? 0) - (order.delivery_discount_amount ?? 0), 0)
}

function hasPlusBenefit(order: OrderHistory) {
  return Boolean((order.membership_discount_amount ?? 0) > 0 || (order.delivery_discount_amount ?? 0) > 0)
}

function clearFilters() {
  filters.status = ''
  filters.paymentMethod = ''
  filters.receiptType = ''
  filters.dateFrom = ''
  filters.dateTo = ''
}

function exportCsv() {
  const csvRows = [
    [
      '注文番号',
      '注文日時',
      '店舗名',
      '注文内容',
      '受け取り方法',
      '支払方法',
      '注文ステータス',
      '決済ステータス',
      '合計金額',
      '獲得ポイント',
    ],
    ...filteredOrders.value.map((order) => [
      order.order_number,
      formatOrderedAt(order.ordered_at),
      order.store_name ?? '',
      orderSummary(order),
      receiptTypeLabel(order.receipt_type),
      paymentMethodLabel(order.payment_method),
      orderStatusLabel(order.order_status),
      paymentStatusLabel(order.payment_status),
      String(order.total_amount),
      String(order.earned_points ?? 0),
    ]),
  ]
  const csv = csvRows.map((row) => row.map(escapeCsvValue).join(',')).join('\n')
  const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = `mennavi_orders_${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

function escapeCsvValue(value: string) {
  return `"${value.replace(/"/g, '""')}"`
}

function closeOrderModal() {
  selectedReceiptOrder.value = null
  selectedDetailOrder.value = null
  isUsageModalOpen.value = false
}

async function downloadReceiptPdf() {
  const order = selectedReceiptOrder.value

  if (!order) {
    return
  }

  receiptPdfLoading.value = true
  actionMessage.value = ''

  try {
    await downloadReceiptPdfFile(order)
  } catch (error) {
    actionMessage.value = error instanceof Error ? error.message : 'PDFの作成に失敗しました。'
  } finally {
    receiptPdfLoading.value = false
  }
}

function reviewForm(order: OrderHistory) {
  if (!reviewForms[order.id]) {
    reviewForms[order.id] = { rating: 5, content: '' }
  }

  return reviewForms[order.id]
}

async function receiveOrder(order: OrderHistory) {
  const token = getCustomerToken()
  if (!token) {
    errorMessage.value = '認証が必要です。'
    return
  }

  try {
    await apiRequest(`/orders/${order.id}/receive`, {
      method: 'PATCH',
      headers: authHeaders(token),
    })
    actionMessage.value = '受け取りを完了しました。レビューを入力できます。'
    await loadOrders()
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : '受け取り処理に失敗しました。'
  }
}

async function submitReview(order: OrderHistory) {
  const token = getCustomerToken()
  if (!token) {
    errorMessage.value = '認証が必要です。'
    return
  }

  const form = reviewForm(order)

  try {
    await apiRequest(`/orders/${order.id}/review`, {
      method: 'POST',
      headers: authHeaders(token),
      body: JSON.stringify({
        rating: form.rating,
        content: form.content,
      }),
    })
    actionMessage.value = 'レビューを投稿しました。'
    await loadOrders()
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'レビュー投稿に失敗しました。'
  }
}
</script>

<template>
  <main class="mx-auto w-full max-w-7xl px-5 py-9 md:px-8">
    <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
      <div>
        <h1 class="text-4xl font-black tracking-normal md:text-5xl">注文履歴</h1>
        <p class="mt-3 text-base font-bold text-neutral-500">
          完了した注文と過去の注文内容を確認できます。
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          :class="[
            'inline-flex h-11 items-center gap-2 rounded-lg border px-4 text-sm font-black hover:bg-red-50',
            hasActiveFilters || isFilterOpen
              ? 'border-red-700 bg-red-50 text-red-700'
              : 'border-red-200 text-neutral-700',
          ]"
          type="button"
          @click="isFilterOpen = !isFilterOpen"
        >
          <SlidersHorizontal
            :class="[
              'h-4 w-4',
              hasActiveFilters || isFilterOpen ? 'text-red-700' : 'text-neutral-500',
            ]"
          />
          絞り込み
        </button>
        <button
          class="inline-flex h-11 items-center gap-2 rounded-lg border border-red-200 px-4 text-sm font-black text-neutral-700 hover:bg-red-50"
          type="button"
          :disabled="!filteredOrders.length"
          @click="exportCsv"
        >
          <Download class="h-4 w-4" />
          CSV出力
        </button>
      </div>
    </div>

    <div class="mt-9 grid gap-6 lg:grid-cols-[minmax(0,1fr)_356px]">
      <section>
        <section
          v-if="isFilterOpen"
          class="mb-5 rounded-lg border border-red-200 bg-white p-5 shadow-sm"
        >
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-black tracking-normal">注文履歴の絞り込み</h2>
              <p class="mt-1 text-sm font-bold text-neutral-500">
                ステータス、支払方法、受け取り方法、注文日で絞り込めます。
              </p>
            </div>
            <button
              class="text-sm font-black text-red-700 hover:text-red-800"
              type="button"
              :disabled="!hasActiveFilters"
              @click="clearFilters"
            >
              条件をクリア
            </button>
          </div>

          <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <label class="grid gap-2 text-sm font-black text-neutral-700">
              注文ステータス
              <select
                v-model="filters.status"
                class="h-11 rounded-lg border border-red-100 bg-white px-3 text-sm font-bold outline-none focus:border-red-400"
              >
                <option value="">すべて</option>
                <option value="received">受付済み</option>
                <option value="cooking">調理中</option>
                <option value="delivering">配送中</option>
                <option value="completed">完了</option>
                <option value="canceled">キャンセル</option>
              </select>
            </label>

            <label class="grid gap-2 text-sm font-black text-neutral-700">
              支払方法
              <select
                v-model="filters.paymentMethod"
                class="h-11 rounded-lg border border-red-100 bg-white px-3 text-sm font-bold outline-none focus:border-red-400"
              >
                <option value="">すべて</option>
                <option value="card">クレジットカード</option>
                <option value="paypay">PayPay / QR決済</option>
                <option value="cash">現金</option>
              </select>
            </label>

            <label class="grid gap-2 text-sm font-black text-neutral-700">
              受け取り方法
              <select
                v-model="filters.receiptType"
                class="h-11 rounded-lg border border-red-100 bg-white px-3 text-sm font-bold outline-none focus:border-red-400"
              >
                <option value="">すべて</option>
                <option value="delivery">配達</option>
                <option value="pickup">お持ち帰り</option>
              </select>
            </label>

            <label class="grid gap-2 text-sm font-black text-neutral-700">
              注文日 From
              <input
                v-model="filters.dateFrom"
                class="h-11 rounded-lg border border-red-100 bg-white px-3 text-sm font-bold outline-none focus:border-red-400"
                type="date"
              />
            </label>

            <label class="grid gap-2 text-sm font-black text-neutral-700">
              注文日 To
              <input
                v-model="filters.dateTo"
                class="h-11 rounded-lg border border-red-100 bg-white px-3 text-sm font-bold outline-none focus:border-red-400"
                type="date"
              />
            </label>
          </div>
        </section>

        <div
          v-if="actionMessage"
          class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-black text-emerald-700"
        >
          {{ actionMessage }}
        </div>

        <div
          v-if="loading"
          class="rounded-lg border border-neutral-200 bg-white px-6 py-16 text-center text-sm font-black text-neutral-500"
        >
          読み込み中...
        </div>

        <div
          v-else-if="errorMessage"
          class="rounded-lg border border-red-200 bg-red-50 px-6 py-12 text-center text-sm font-black text-red-700"
        >
          {{ errorMessage }}
        </div>

        <div
          v-else-if="!orders.length"
          class="rounded-lg border border-dashed border-neutral-300 bg-white px-6 py-16 text-center"
        >
          <ReceiptText class="mx-auto h-12 w-12 text-neutral-300" />
          <p class="mt-5 text-xl font-black tracking-normal">注文履歴はありません</p>
          <p class="mt-2 text-sm font-bold text-neutral-500">
            注文が完了すると、ここに履歴が表示されます。
          </p>
        </div>

        <div
          v-else-if="!filteredOrders.length"
          class="rounded-lg border border-dashed border-neutral-300 bg-white px-6 py-16 text-center"
        >
          <SlidersHorizontal class="mx-auto h-12 w-12 text-neutral-300" />
          <p class="mt-5 text-xl font-black tracking-normal">条件に一致する注文がありません</p>
          <p class="mt-2 text-sm font-bold text-neutral-500">絞り込み条件を変更してください。</p>
        </div>

        <div v-else class="grid gap-6">
          <article
            v-for="order in filteredOrders"
            :key="order.id"
            class="overflow-hidden rounded-lg border border-red-200 bg-white shadow-sm"
          >
            <div class="grid gap-5 p-5 md:grid-cols-[128px_minmax(0,1fr)] md:p-6">
              <div class="aspect-square w-full max-w-32 overflow-hidden rounded-lg bg-neutral-100 shadow-sm ring-1 ring-neutral-100 md:w-32">
                <FallbackImage :src="orderImagePath(order)" :alt="`${orderTitle(order)}の画像`" />
              </div>

              <div class="min-w-0">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <div class="flex flex-wrap items-center gap-2 text-sm font-bold text-neutral-500">
                      <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-black text-neutral-500">
                        {{ orderStatusLabel(order.order_status) }}
                      </span>
                      <span :class="['rounded-full px-3 py-1 text-xs font-black', paymentStatusClass(order.payment_status)]">
                        {{ paymentStatusLabel(order.payment_status) }}
                      </span>
                      <span>注文番号 {{ order.order_number }}・{{ formatOrderedAt(order.ordered_at) }}</span>
                    </div>
                    <h2 class="mt-3 text-2xl font-black tracking-normal">{{ orderTitle(order) }}</h2>
                    <p class="mt-2 text-sm font-bold text-neutral-600">{{ orderSummary(order) }}</p>
                  </div>
                  <p class="shrink-0 text-2xl font-black text-red-700">
                    {{ formatPrice(order.total_amount) }}
                  </p>
                </div>

                <dl class="mt-6 grid gap-2 border-t border-red-100 pt-4 text-sm font-bold text-neutral-600 sm:grid-cols-2">
                  <div class="flex justify-between gap-4">
                    <dt>受け取り方法</dt>
                    <dd class="font-black text-neutral-900">{{ receiptTypeLabel(order.receipt_type) }}</dd>
                  </div>
                  <div class="flex justify-between gap-4">
                    <dt>支払方法</dt>
                    <dd class="font-black text-neutral-900">{{ paymentMethodLabel(order.payment_method) }}</dd>
                  </div>
                </dl>

                <div class="mt-7 grid gap-4">
                  <div
                    v-if="order.order_status === 'delivering'"
                    class="rounded-lg bg-red-50 px-4 py-3 text-sm font-bold text-red-700"
                  >
                    配送中です。商品を受け取ったら受け取り完了してください。
                  </div>

                <div class="flex flex-wrap justify-end gap-2">
                  <button
                    v-if="order.order_status === 'delivering'"
                    class="rounded-lg bg-red-700 px-5 py-3 text-sm font-black text-white hover:bg-red-800"
                    type="button"
                    @click="receiveOrder(order)"
                  >
                    受け取る
                  </button>
                  <button
                    class="rounded-lg border border-red-200 px-5 py-3 text-sm font-black text-neutral-700 hover:bg-red-50"
                    type="button"
                    @click="selectedReceiptOrder = order"
                  >
                    領収書
                  </button>
                  <button
                    class="rounded-lg bg-red-700 px-5 py-3 text-sm font-black text-white hover:bg-red-800"
                    type="button"
                    @click="selectedDetailOrder = order"
                  >
                    詳細
                  </button>
                </div>
              </div>

              <div
                v-if="order.order_status === 'completed'"
                class="mt-5 rounded-lg border border-red-100 bg-neutral-50 p-4"
              >
                <div v-if="order.review" class="text-sm font-bold text-neutral-700">
                  <p class="font-black text-neutral-900">投稿済みレビュー</p>
                  <p class="mt-2 text-amber-600">
                    {{ '★'.repeat(order.review.rating) }}{{ '☆'.repeat(5 - order.review.rating) }}
                  </p>
                  <p v-if="order.review.content" class="mt-2 leading-6">{{ order.review.content }}</p>
                </div>
                <div v-else>
                  <p class="text-sm font-black text-neutral-900">レビューを投稿</p>
                  <div class="mt-3 flex items-center gap-1">
                    <button
                      v-for="rating in 5"
                      :key="rating"
                      class="grid h-9 w-9 place-items-center rounded-full text-amber-500 hover:bg-white"
                      type="button"
                      @click="reviewForm(order).rating = rating"
                    >
                      <Star
                        class="h-5 w-5"
                        :fill="rating <= reviewForm(order).rating ? 'currentColor' : 'none'"
                      />
                    </button>
                  </div>
                  <textarea
                    v-model="reviewForm(order).content"
                    class="mt-3 min-h-24 w-full rounded-lg border border-red-100 bg-white p-3 text-sm font-bold outline-none focus:border-red-500"
                    maxlength="1000"
                    placeholder="レビュー内容"
                  />
                  <button
                    class="mt-3 rounded-lg bg-red-700 px-5 py-3 text-sm font-black text-white hover:bg-red-800"
                    type="button"
                    @click="submitReview(order)"
                  >
                    レビューを投稿
                  </button>
                </div>
              </div>
            </div>
            </div>
          </article>
        </div>
      </section>

      <aside class="grid content-start gap-6">
        <section class="rounded-lg border border-red-200 bg-neutral-100 p-6">
          <h2 class="text-2xl font-black tracking-normal">利用状況</h2>
          <dl class="mt-6 grid gap-0 text-base font-bold">
            <div class="flex justify-between border-b border-neutral-200 py-4">
              <dt class="text-neutral-500">注文数</dt>
              <dd class="text-2xl font-black">{{ filteredOrders.length }}</dd>
            </div>
            <div class="flex justify-between border-b border-neutral-200 py-4">
              <dt class="text-neutral-500">注文金額</dt>
              <dd class="text-2xl font-black">{{ formatPrice(totalAmount) }}</dd>
            </div>
            <div class="flex justify-between py-4">
              <dt class="text-neutral-500">獲得ポイント</dt>
              <dd class="text-2xl font-black text-red-700">{{ points.toLocaleString('ja-JP') }} pt</dd>
            </div>
          </dl>
          <button
            class="mt-5 h-14 w-full rounded-lg bg-neutral-600 text-sm font-black text-white hover:bg-neutral-700 disabled:opacity-40"
            type="button"
            :disabled="!filteredOrders.length"
            @click="isUsageModalOpen = true"
          >
            利用状況を見る
          </button>
        </section>

        <section class="rounded-lg bg-red-700 p-6 text-white shadow-lg">
          <h2 class="text-2xl font-black tracking-normal">麺ナビ Plus</h2>
          <p class="mt-3 text-sm font-bold leading-6 text-white/90">
            全店舗で使える送料無料特典と、ご注文ごとに15%割引をご利用いただけます。
          </p>
          <button
            class="mt-5 rounded-lg bg-white px-6 py-3 text-sm font-black text-red-700 hover:bg-red-50"
            type="button"
            @click="emit('openPlus')"
          >
            今すぐアップグレード
          </button>
        </section>
      </aside>
    </div>
  </main>

  <div
    v-if="selectedReceiptOrder || selectedDetailOrder || isUsageModalOpen"
    class="fixed inset-0 z-50 bg-black/40 print:hidden"
    role="presentation"
    @click="closeOrderModal"
  />

  <section
    v-if="isUsageModalOpen"
    class="fixed left-1/2 top-1/2 z-[60] max-h-[calc(100vh-32px)] w-[calc(100%-32px)] max-w-3xl -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg bg-white p-6 shadow-2xl"
    role="dialog"
    aria-modal="true"
    aria-label="利用状況"
    @click.stop
  >
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-sm font-black text-red-700">Usage Summary</p>
        <h2 class="text-2xl font-black tracking-normal">利用状況</h2>
        <p class="mt-2 text-sm font-bold text-neutral-500">
          {{ hasActiveFilters ? '現在の絞り込み条件で集計しています。' : '注文履歴全体の利用状況です。' }}
        </p>
      </div>
      <button
        class="grid h-10 w-10 place-items-center rounded-full text-neutral-500 hover:bg-neutral-100"
        type="button"
        aria-label="閉じる"
        @click="closeOrderModal"
      >
        <X class="h-5 w-5" />
      </button>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-lg border border-red-100 bg-red-50 p-4">
        <p class="text-sm font-black text-red-700">注文数</p>
        <p class="mt-3 text-3xl font-black text-neutral-900">{{ usageOrders.length }}</p>
      </div>
      <div class="rounded-lg border border-red-100 bg-neutral-50 p-4">
        <p class="text-sm font-black text-neutral-500">今月の注文金額</p>
        <p class="mt-3 text-3xl font-black text-neutral-900">{{ formatPrice(thisMonthUsageAmount) }}</p>
      </div>
      <div class="rounded-lg border border-red-100 bg-neutral-50 p-4">
        <p class="text-sm font-black text-neutral-500">平均注文単価</p>
        <p class="mt-3 text-3xl font-black text-neutral-900">{{ formatPrice(usageAverageAmount) }}</p>
      </div>
      <div class="rounded-lg border border-red-100 bg-neutral-50 p-4">
        <p class="text-sm font-black text-neutral-500">保有ポイント</p>
        <p class="mt-3 text-3xl font-black text-red-700">{{ points.toLocaleString('ja-JP') }} pt</p>
      </div>
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-[minmax(0,1fr)_280px]">
      <dl class="rounded-lg border border-red-100 bg-white p-5 text-sm font-bold text-neutral-700">
        <div class="flex justify-between gap-4 border-b border-red-50 py-3">
          <dt>注文金額合計</dt>
          <dd class="font-black text-neutral-900">{{ formatPrice(usageTotalAmount) }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-red-50 py-3">
          <dt>対応中の注文</dt>
          <dd class="font-black text-neutral-900">{{ usageActiveCount }}件</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-red-50 py-3">
          <dt>完了した注文</dt>
          <dd class="font-black text-neutral-900">{{ usageCompletedCount }}件</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-red-50 py-3">
          <dt>配達</dt>
          <dd class="font-black text-neutral-900">{{ usageDeliveryCount }}件</dd>
        </div>
        <div class="flex justify-between gap-4 py-3">
          <dt>お持ち帰り</dt>
          <dd class="font-black text-neutral-900">{{ usagePickupCount }}件</dd>
        </div>
      </dl>

      <div class="rounded-lg bg-red-700 p-5 text-white">
        <p class="text-lg font-black">麺ナビ Plus 節約額</p>
        <p class="mt-4 text-4xl font-black">{{ formatPrice(usagePlusSavings) }}</p>
        <p class="mt-3 text-sm font-bold leading-6 text-white/85">
          注文履歴に記録された15%割引と配送料無料の合計です。
        </p>
        <button
          class="mt-5 w-full rounded-lg bg-white px-5 py-3 text-sm font-black text-red-700 hover:bg-red-50"
          type="button"
          @click="emit('openPlus')"
        >
          Plusを確認する
        </button>
      </div>
    </div>
  </section>

  <section
    v-if="selectedReceiptOrder"
    class="receipt-print-root fixed left-1/2 top-1/2 z-[60] max-h-[calc(100vh-32px)] w-[calc(100%-32px)] max-w-2xl -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg bg-white p-6 shadow-2xl print:static print:max-h-none print:w-full print:max-w-none print:translate-x-0 print:translate-y-0 print:overflow-visible print:rounded-none print:shadow-none"
    role="dialog"
    aria-modal="true"
    aria-label="領収書"
    @click.stop
  >
    <div class="flex items-start justify-between gap-4 print:hidden">
      <div>
        <p class="text-sm font-black text-red-700">Receipt</p>
        <h2 class="text-2xl font-black tracking-normal">領収書</h2>
      </div>
      <button
        class="grid h-10 w-10 place-items-center rounded-full text-neutral-500 hover:bg-neutral-100 print:hidden"
        type="button"
        aria-label="閉じる"
        @click="closeOrderModal"
      >
        <X class="h-5 w-5" />
      </button>
    </div>

    <div class="receipt-print-content mt-6 rounded-lg border border-neutral-200 p-6 print:mt-0">
      <div class="flex flex-wrap items-start justify-between gap-5 border-b border-neutral-200 pb-5">
        <div>
          <p class="text-sm font-bold text-neutral-500">注文番号</p>
          <p class="mt-1 text-lg font-black text-neutral-900">{{ selectedReceiptOrder.order_number }}</p>
        </div>
        <div class="text-right">
          <p class="text-sm font-bold text-neutral-500">発行日</p>
          <p class="mt-1 font-black text-neutral-900">{{ formatOrderedAt(selectedReceiptOrder.ordered_at) }}</p>
        </div>
      </div>

      <div class="mt-6">
        <p class="text-sm font-bold text-neutral-500">宛名</p>
        <p class="mt-1 text-xl font-black text-neutral-900">お客様</p>
      </div>

      <dl class="mt-6 rounded-lg border border-neutral-200 px-4 py-3 text-sm font-bold text-neutral-700">
        <div class="flex justify-between gap-4">
          <dt>店舗名</dt>
          <dd class="font-black text-neutral-900">{{ selectedReceiptOrder.store_name ?? '-' }}</dd>
        </div>
        <div class="mt-3 flex justify-between gap-4">
          <dt>インボイス番号</dt>
          <dd class="font-black text-neutral-900">{{ selectedReceiptOrder.store_invoice_number ?? '未登録' }}</dd>
        </div>
      </dl>

      <div class="mt-6 rounded-lg bg-neutral-50 p-5">
        <p class="text-sm font-bold text-neutral-500">但し書き</p>
        <p class="mt-1 font-black text-neutral-900">飲食代として</p>
        <p class="mt-5 text-sm font-bold text-neutral-500">領収金額</p>
        <p class="mt-1 text-4xl font-black text-red-700">{{ formatPrice(selectedReceiptOrder.total_amount) }}</p>
      </div>

      <dl class="mt-6 grid gap-3 text-sm font-bold text-neutral-700">
        <div class="flex justify-between gap-4">
          <dt>支払方法</dt>
          <dd>{{ paymentMethodLabel(selectedReceiptOrder.payment_method) }}</dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt>決済状態</dt>
          <dd>{{ paymentStatusLabel(selectedReceiptOrder.payment_status) }}</dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt>小計</dt>
          <dd>{{ formatPrice(orderSubtotal(selectedReceiptOrder)) }}</dd>
        </div>
        <div
          v-if="(selectedReceiptOrder.membership_discount_amount ?? 0) > 0"
          class="flex justify-between gap-4 text-red-700"
        >
          <dt>{{ membershipDiscountLabel(selectedReceiptOrder) }}</dt>
          <dd>-{{ formatPrice(selectedReceiptOrder.membership_discount_amount ?? 0) }}</dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt>配送料</dt>
          <dd>{{ selectedReceiptOrder.delivery_fee ? formatPrice(selectedReceiptOrder.delivery_fee) : '無料' }}</dd>
        </div>
        <div
          v-if="(selectedReceiptOrder.delivery_discount_amount ?? 0) > 0"
          class="flex justify-between gap-4 text-red-700"
        >
          <dt>麺ナビ Plus 送料無料特典</dt>
          <dd>-{{ formatPrice(selectedReceiptOrder.delivery_discount_amount ?? 0) }}</dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt>税金</dt>
          <dd>{{ formatPrice(selectedReceiptOrder.tax_amount ?? 0) }}</dd>
        </div>
        <div
          v-if="hasPlusBenefit(selectedReceiptOrder)"
          class="rounded-lg bg-red-50 px-4 py-3 text-xs font-black text-red-700"
        >
          麺ナビ Plus特典が適用されています。
        </div>
      </dl>

      <div class="mt-6 border-t border-neutral-200 pt-5 text-sm font-bold text-neutral-600">
        <p>麺ナビ</p>
        <p class="mt-1">Mennavi Order</p>
      </div>
    </div>

    <div class="mt-5 flex justify-end gap-3 print:hidden">
      <button
        class="rounded-lg border border-neutral-200 px-5 py-3 text-sm font-black text-neutral-700 hover:bg-neutral-50"
        type="button"
        @click="closeOrderModal"
      >
        閉じる
      </button>
      <button
        class="inline-flex items-center gap-2 rounded-lg bg-red-700 px-5 py-3 text-sm font-black text-white hover:bg-red-800 disabled:opacity-60"
        type="button"
        :disabled="receiptPdfLoading"
        @click="downloadReceiptPdf"
      >
        <Download class="h-4 w-4" />
        {{ receiptPdfLoading ? '作成中...' : 'PDFダウンロード' }}
      </button>
    </div>
  </section>

  <section
    v-if="selectedDetailOrder"
    class="fixed left-1/2 top-1/2 z-[60] max-h-[calc(100vh-32px)] w-[calc(100%-32px)] max-w-3xl -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg bg-white p-6 shadow-2xl"
    role="dialog"
    aria-modal="true"
    aria-label="注文詳細"
    @click.stop
  >
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-sm font-black text-red-700">Order Detail</p>
        <h2 class="text-2xl font-black tracking-normal">注文詳細</h2>
        <p class="mt-2 text-sm font-bold text-neutral-500">
          {{ selectedDetailOrder.order_number }}・{{ formatOrderedAt(selectedDetailOrder.ordered_at) }}
        </p>
      </div>
      <button
        class="grid h-10 w-10 place-items-center rounded-full text-neutral-500 hover:bg-neutral-100"
        type="button"
        aria-label="閉じる"
        @click="closeOrderModal"
      >
        <X class="h-5 w-5" />
      </button>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
      <dl class="rounded-lg border border-red-100 bg-neutral-50 p-4 text-sm font-bold text-neutral-700">
        <div class="flex justify-between gap-4 py-2">
          <dt>注文ステータス</dt>
          <dd>{{ orderStatusLabel(selectedDetailOrder.order_status) }}</dd>
        </div>
        <div class="flex justify-between gap-4 py-2">
          <dt>決済状態</dt>
          <dd>{{ paymentStatusLabel(selectedDetailOrder.payment_status) }}</dd>
        </div>
        <div class="flex justify-between gap-4 py-2">
          <dt>受け取り方法</dt>
          <dd>{{ receiptTypeLabel(selectedDetailOrder.receipt_type) }}</dd>
        </div>
        <div class="flex justify-between gap-4 py-2">
          <dt>支払方法</dt>
          <dd>{{ paymentMethodLabel(selectedDetailOrder.payment_method) }}</dd>
        </div>
      </dl>

      <dl class="rounded-lg border border-red-100 bg-neutral-50 p-4 text-sm font-bold text-neutral-700">
        <div class="flex justify-between gap-4 py-2">
          <dt>店舗</dt>
          <dd>{{ selectedDetailOrder.store_name ?? '-' }}</dd>
        </div>
        <div class="flex justify-between gap-4 py-2">
          <dt>配送担当</dt>
          <dd>{{ selectedDetailOrder.delivery_staff_name ?? '-' }}</dd>
        </div>
        <div class="flex justify-between gap-4 py-2">
          <dt>獲得ポイント</dt>
          <dd>{{ (selectedDetailOrder.earned_points ?? 0).toLocaleString('ja-JP') }} pt</dd>
        </div>
      </dl>
    </div>

    <div class="mt-6 overflow-hidden rounded-lg border border-red-100">
      <div
        v-for="item in selectedDetailOrder.items"
        :key="`${selectedDetailOrder.id}-${item.id}`"
        class="grid grid-cols-[minmax(0,1fr)_auto_auto] gap-3 border-b border-red-50 px-4 py-3 text-sm last:border-b-0"
      >
        <div class="min-w-0">
          <p class="truncate font-black text-neutral-900">{{ item.product_name }}</p>
          <ul
            v-if="item.selected_options?.length"
            class="mt-1 space-y-0.5 text-xs font-bold text-neutral-500"
          >
            <li v-for="option in item.selected_options" :key="`${item.id}-${option.product_id}`">
              + {{ option.name }}（{{ formatPrice(option.price) }}）
            </li>
          </ul>
          <p class="mt-1 font-bold text-neutral-500">{{ formatPrice(item.unit_price) }}</p>
        </div>
        <p class="font-bold text-neutral-500">× {{ item.quantity }}</p>
        <p class="font-black text-neutral-900">{{ formatPrice(item.subtotal) }}</p>
      </div>
    </div>

    <dl class="mt-6 grid gap-3 border-t border-red-100 pt-5 text-sm font-bold text-neutral-700">
      <div class="flex justify-between">
        <dt>小計</dt>
        <dd>{{ formatPrice(orderSubtotal(selectedDetailOrder)) }}</dd>
      </div>
      <div
        v-if="(selectedDetailOrder.membership_discount_amount ?? 0) > 0"
        class="flex justify-between text-red-700"
      >
        <dt>{{ membershipDiscountLabel(selectedDetailOrder) }}</dt>
        <dd>-{{ formatPrice(selectedDetailOrder.membership_discount_amount ?? 0) }}</dd>
      </div>
      <div class="flex justify-between">
        <dt>配送料</dt>
        <dd>{{ selectedDetailOrder.delivery_fee ? formatPrice(selectedDetailOrder.delivery_fee) : '無料' }}</dd>
      </div>
      <div
        v-if="(selectedDetailOrder.delivery_discount_amount ?? 0) > 0"
        class="flex justify-between text-red-700"
      >
        <dt>麺ナビ Plus 送料無料特典</dt>
        <dd>-{{ formatPrice(selectedDetailOrder.delivery_discount_amount ?? 0) }}</dd>
      </div>
      <div
        v-if="(selectedDetailOrder.delivery_discount_amount ?? 0) > 0"
        class="flex justify-between"
      >
        <dt>配送料適用後</dt>
        <dd>{{ effectiveDeliveryFee(selectedDetailOrder) ? formatPrice(effectiveDeliveryFee(selectedDetailOrder)) : '無料' }}</dd>
      </div>
      <div class="flex justify-between">
        <dt>税金</dt>
        <dd>{{ formatPrice(selectedDetailOrder.tax_amount ?? 0) }}</dd>
      </div>
      <div
        v-if="hasPlusBenefit(selectedDetailOrder)"
        class="rounded-lg bg-red-50 px-4 py-3 text-xs font-black text-red-700"
      >
        麺ナビ Plus特典が適用されています。
      </div>
      <div class="flex justify-between text-lg font-black text-neutral-900">
        <dt>合計</dt>
        <dd class="text-red-700">{{ formatPrice(selectedDetailOrder.total_amount) }}</dd>
      </div>
    </dl>

    <div class="mt-5 flex justify-end">
      <button
        class="rounded-lg bg-red-700 px-5 py-3 text-sm font-black text-white hover:bg-red-800"
        type="button"
        @click="closeOrderModal"
      >
        閉じる
      </button>
    </div>
  </section>
</template>

<style>
@media print {
  @page {
    margin: 14mm;
  }

  html,
  body {
    width: 100%;
    height: auto;
    overflow: visible !important;
  }

  body * {
    visibility: hidden !important;
  }

  .receipt-print-root,
  .receipt-print-root * {
    visibility: visible !important;
  }

  .receipt-print-root {
    display: block !important;
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    inset: auto !important;
    width: 100% !important;
    max-width: none !important;
    max-height: none !important;
    padding: 0 !important;
    transform: none !important;
    overflow: visible !important;
    background: #fff !important;
    color: #171717 !important;
    box-shadow: none !important;
  }

  .receipt-print-root > :not(.receipt-print-content) {
    display: none !important;
  }

  .receipt-print-content {
    margin: 0 !important;
    break-inside: avoid;
    page-break-inside: avoid;
  }
}
</style>
