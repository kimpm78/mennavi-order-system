<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Download, ReceiptText, SlidersHorizontal } from 'lucide-vue-next'
import FallbackImage from '@/components/common/FallbackImage.vue'
import { apiRequest, authHeaders } from '../../lib/api'
import { getCustomerToken } from '../../lib/authStorage'

type OrderHistoryItem = {
  id: number
  product_id?: number
  product_name: string
  imagePath?: string | null
  unit_price: number
  quantity: number
  subtotal: number
}

type OrderHistory = {
  id: number
  order_number: string
  total_amount: number
  subtotal_amount?: number
  delivery_fee?: number
  tax_rate?: number
  tax_amount?: number
  receipt_type?: string
  order_status: string
  payment_status?: string
  payment_method?: string | null
  ordered_at?: string | null
  items: OrderHistoryItem[]
}

const orders = ref<OrderHistory[]>([])
const loading = ref(true)
const errorMessage = ref('')

const totalAmount = computed(() =>
  orders.value.reduce((total, order) => total + order.total_amount, 0),
)
// TODO: DBへカラム追加後、ポイント付与ルールを変更する
const points = computed(() => Math.floor(totalAmount.value * 0.03))

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
    const response = await apiRequest<{ orders: OrderHistory[] }>('/orders', {
      headers: authHeaders(token),
    })
    orders.value = response.orders
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
          class="inline-flex h-11 items-center gap-2 rounded-lg border border-red-200 px-4 text-sm font-black text-neutral-700 hover:bg-red-50"
          type="button"
        >
          <SlidersHorizontal class="h-4 w-4" />
          絞り込み
        </button>
        <button
          class="inline-flex h-11 items-center gap-2 rounded-lg border border-red-200 px-4 text-sm font-black text-neutral-700 hover:bg-red-50"
          type="button"
          :disabled="!orders.length"
        >
          <Download class="h-4 w-4" />
          CSV出力
        </button>
      </div>
    </div>

    <div class="mt-9 grid gap-6 lg:grid-cols-[minmax(0,1fr)_356px]">
      <section>
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

        <div v-else class="grid gap-6">
          <article
            v-for="order in orders"
            :key="order.id"
            class="overflow-hidden rounded-lg border border-red-200 bg-white shadow-sm md:grid md:grid-cols-[192px_minmax(0,1fr)]"
          >
            <div class="min-h-[188px] bg-neutral-100 md:min-h-full">
              <FallbackImage :src="orderImagePath(order)" :alt="`${orderTitle(order)}の画像`" />
            </div>

            <div class="p-6">
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
                <div class="flex justify-between gap-4">
                  <dt>小計</dt>
                  <dd>{{ formatPrice(order.subtotal_amount ?? 0) }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                  <dt>配送料</dt>
                  <dd>{{ order.delivery_fee ? formatPrice(order.delivery_fee) : '無料' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                  <dt>税金</dt>
                  <dd>{{ formatPrice(order.tax_amount ?? 0) }}</dd>
                </div>
              </dl>

              <div class="mt-7 flex flex-col gap-4 md:flex-row md:items-center md:justify-end">

                <div class="flex gap-2">
                  <button
                    class="rounded-lg border border-red-200 px-5 py-3 text-sm font-black text-neutral-700 hover:bg-red-50"
                    type="button"
                  >
                    領収書
                  </button>
                  <button
                    class="rounded-lg bg-red-700 px-5 py-3 text-sm font-black text-white hover:bg-red-800"
                    type="button"
                  >
                    詳細
                  </button>
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
              <dd class="text-2xl font-black">{{ orders.length }}</dd>
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
            :disabled="!orders.length"
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
            class="mt-5 rounded-lg bg-white px-6 py-3 text-sm font-black text-red-700"
            type="button"
          >
            今すぐアップグレード
          </button>
        </section>
      </aside>
    </div>
  </main>
</template>
