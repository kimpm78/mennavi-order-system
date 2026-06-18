<script setup lang="ts">
import { ref } from 'vue'
import {
  Clock3,
  CreditCard,
  ReceiptText,
  ShoppingBag,
  TrendingUp,
  Users,
} from 'lucide-vue-next'

type SummaryCard = {
  label: string
  value: string | number
  change?: string | number | null
  icon?: string
}

type AdminOrder = {
  id?: number
  order_id: number | string
  number?: string | null
  order_number?: string | null
  title?: string | null
  note?: string | null
  type?: string | null
  status?: string | null
  customer_name?: string | null
  recipient_name?: string | null
  total_amount?: number | null
  total_price?: number | null
  order_status?: string | null
  payment_status?: string | null
  order_date?: string | null
  ordered_at?: string | null
  created_at?: string | null
  elapsed_minutes?: number | null
  receipt_type?: string | null
}

type ActiveOrderFilterItem = {
  key: string
  label: string
}

type KitchenBar = {
  label: string
  value: number
  orderCount?: number
  max?: number
}

type KitchenStatus = {
  load: number
  averageCookingMinutes: number
  cookingOrderCount: number
}

const props = defineProps<{
  summaryCards: SummaryCard[]
  filteredActiveOrders: AdminOrder[]
  activeOrderFilter: string
  activeOrderFilterItems: ActiveOrderFilterItem[]
  kitchenBars: KitchenBar[]
  kitchenStatus: KitchenStatus
  lastUpdatedLabel: string
  adminPageLoading?: boolean
  formatPrice: (price: number) => string
  formatChangeRate: (value: string | number | null | undefined) => string
  formatElapsed: (value: string | number | null | undefined) => string
  orderStatusLabel: (status: string | null | undefined) => string
  paymentStatusLabel: (status: string | null | undefined) => string
  paymentStatusClass: (status: string | null | undefined) => string
  nextOrderStatus: (order: AdminOrder) => string | null | undefined
  orderActionLabel: (order: AdminOrder) => string
}>()

const emit = defineEmits<{
  updateActiveOrderFilter: [filter: string]
  updateOrderStatus: [order: AdminOrder, deliveryStaffName?: string]
}>()

const deliveryStaffNames = ['佐藤A', '鈴木B']
const deliveryTargetOrder = ref<AdminOrder | null>(null)
const selectedDeliveryStaffName = ref(deliveryStaffNames[0])

const summaryIconMap = {
  orders: ShoppingBag,
  sales: TrendingUp,
  users: Users,
  payment: CreditCard,
  receipt: ReceiptText,
  time: Clock3,
}

const resolveSummaryIcon = (icon?: string) => {
  if (!icon) {
    return ReceiptText
  }

  return summaryIconMap[icon as keyof typeof summaryIconMap] || ReceiptText
}

const orderTotalAmount = (order: AdminOrder) => {
  return Number(order.total_amount ?? order.total_price ?? 0)
}

const orderCustomerName = (order: AdminOrder) => {
  return order.customer_name || order.recipient_name || 'ゲスト'
}

const orderStatusValue = (order: AdminOrder) => {
  return order.order_status || order.status || null
}

const orderElapsedValue = (order: AdminOrder) => {
  return order.elapsed_minutes ?? 0
}

const openDeliveryStaffModal = (order: AdminOrder) => {
  selectedDeliveryStaffName.value = deliveryStaffNames[0]
  deliveryTargetOrder.value = order
}

const closeDeliveryStaffModal = () => {
  deliveryTargetOrder.value = null
  selectedDeliveryStaffName.value = deliveryStaffNames[0]
}

const requestOrderStatusUpdate = (order: AdminOrder) => {
  if (props.nextOrderStatus(order) === 'delivering') {
    openDeliveryStaffModal(order)
    return
  }

  emit('updateOrderStatus', order)
}

const confirmDeliveryStart = () => {
  if (!deliveryTargetOrder.value) {
    return
  }

  emit('updateOrderStatus', deliveryTargetOrder.value, selectedDeliveryStaffName.value)
  closeDeliveryStaffModal()
}
</script>

<template>
  <div class="grid gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm font-bold text-red-600">ダッシュボード</p>
        <h1 class="text-2xl font-black text-neutral-900">本日の店舗状況</h1>
      </div>

      <div class="rounded-full border border-red-100 bg-white px-4 py-2 text-sm font-bold text-neutral-600 shadow-sm">
        最終更新：{{ adminPageLoading ? '更新中...' : lastUpdatedLabel }}
      </div>
    </div>

    <!-- 集計カード -->
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <article
        v-for="card in summaryCards"
        :key="card.label"
        class="rounded-xl border border-red-100 bg-white p-5 shadow-sm"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-bold text-neutral-500">{{ card.label }}</p>
            <p class="mt-2 truncate text-2xl font-black text-neutral-900">{{ card.value }}</p>
          </div>

          <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-red-50 text-red-700">
            <component :is="resolveSummaryIcon(card.icon)" class="h-5 w-5" />
          </div>
        </div>

        <p
          v-if="card.change !== undefined && card.change !== null && card.change !== ''"
          class="mt-4 text-xs font-black text-red-700"
        >
          {{ formatChangeRate(card.change) }}
        </p>
      </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
      <!-- 対応中注文 -->
      <section class="min-w-0 rounded-xl border border-red-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-red-100 px-6 py-4">
          <div>
            <h2 class="text-lg font-black tracking-normal">対応中の注文</h2>
            <p class="mt-1 text-xs font-bold text-neutral-500">注文状況を確認してステータスを更新できます。</p>
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              v-for="item in activeOrderFilterItems"
              :key="item.key"
              type="button"
              class="h-9 rounded-full px-4 text-xs font-black transition"
              :class="activeOrderFilter === item.key ? 'bg-red-700 text-white' : 'bg-red-50 text-red-700 hover:bg-red-100'"
              @click="emit('updateActiveOrderFilter', item.key)"
            >
              {{ item.label }}
            </button>
          </div>
        </div>

        <div class="grid gap-3 p-4">
          <article
            v-for="order in filteredActiveOrders"
            :key="order.order_id"
            class="grid gap-4 rounded-xl border border-red-100 bg-white p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
          >
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-black text-neutral-900">
                  注文番号 #{{ order.order_number || order.order_id }}
                </p>
                <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700">
                  {{ orderStatusLabel(orderStatusValue(order)) }}
                </span>
                <span
                  class="rounded-full px-3 py-1 text-xs font-black"
                  :class="paymentStatusClass(order.payment_status)"
                >
                  {{ paymentStatusLabel(order.payment_status) }}
                </span>
              </div>

              <p class="mt-2 truncate text-sm font-bold text-neutral-600">
                {{ orderCustomerName(order) }} ・ {{ formatElapsed(orderElapsedValue(order)) }} 経過
              </p>
              <p class="mt-2 text-lg font-black text-neutral-900">
                {{ formatPrice(orderTotalAmount(order)) }}
              </p>
            </div>

            <button
              class="h-10 rounded-lg bg-red-700 px-4 text-sm font-black text-white hover:bg-red-800 disabled:opacity-50"
              type="button"
              :disabled="adminPageLoading || !nextOrderStatus(order)"
              @click="requestOrderStatusUpdate(order)"
            >
              {{ orderActionLabel(order) }}
            </button>
          </article>

          <div
            v-if="filteredActiveOrders.length === 0"
            class="rounded-xl border border-dashed border-red-200 p-8 text-center"
          >
            <p class="text-sm font-bold text-neutral-500">対応中の注文はありません。</p>
          </div>
        </div>
      </section>

      <!-- キッチン状況 -->
      <section class="rounded-xl border border-red-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-black tracking-normal">キッチン状況</h2>
        <p class="mt-1 text-xs font-bold text-neutral-500">時間帯別の注文数と調理負荷を表示します。</p>

        <div class="mt-5 grid gap-3">
          <div class="flex items-center justify-between rounded-lg bg-red-50 px-4 py-3">
            <span class="text-sm font-black text-neutral-700">現在の負荷</span>
            <span class="text-xl font-black text-red-700">{{ kitchenStatus.load }}%</span>
          </div>
          <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-4 py-3">
            <span class="text-sm font-black text-neutral-700">平均調理時間</span>
            <span class="text-xl font-black text-neutral-900">{{ kitchenStatus.averageCookingMinutes }}分</span>
          </div>
          <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-4 py-3">
            <span class="text-sm font-black text-neutral-700">調理中の注文</span>
            <span class="text-xl font-black text-neutral-900">{{ kitchenStatus.cookingOrderCount }}件</span>
          </div>
        </div>

        <div class="mt-6 border-t border-red-100 pt-5">
          <h3 class="text-sm font-black text-neutral-900">時間帯別の注文数 / 負荷</h3>
        </div>

        <div class="mt-4 grid gap-4">
          <div
            v-for="bar in kitchenBars"
            :key="bar.label"
            class="grid gap-2"
          >
            <div class="flex items-center justify-between gap-3 text-sm font-black">
              <span class="text-neutral-700">{{ bar.label }}</span>
              <span class="text-red-700">{{ bar.orderCount ?? 0 }}件 / {{ bar.value }}%</span>
            </div>
            <div class="h-3 overflow-hidden rounded-full bg-red-50">
              <div
                class="h-full rounded-full bg-red-700 transition-all"
                :style="{ width: `${Math.min(100, Math.round((bar.value / Math.max(1, bar.max || 100)) * 100))}%` }"
              />
            </div>
          </div>
        </div>
      </section>
    </section>

    <div
      v-if="deliveryTargetOrder"
      class="fixed inset-0 z-50 grid place-items-center bg-black/40 px-4"
      @click.self="closeDeliveryStaffModal"
    >
      <section class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <div>
          <p class="text-sm font-black text-red-700">配送開始</p>
          <h2 class="mt-1 text-xl font-black text-neutral-900">配送担当を選択</h2>
          <p class="mt-2 text-sm font-bold text-neutral-500">
            注文番号 #{{ deliveryTargetOrder.order_number || deliveryTargetOrder.order_id }} を配送中に変更します。
          </p>
        </div>

        <label class="mt-5 grid gap-2 text-sm font-black text-neutral-700">
          配送担当
          <select
            v-model="selectedDeliveryStaffName"
            class="h-12 rounded-xl border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100"
          >
            <option v-for="staff in deliveryStaffNames" :key="staff" :value="staff">
              {{ staff }}
            </option>
          </select>
        </label>

        <div class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            class="h-11 rounded-xl border border-neutral-200 px-5 text-sm font-black text-neutral-600 hover:bg-neutral-50"
            @click="closeDeliveryStaffModal"
          >
            キャンセル
          </button>
          <button
            type="button"
            class="h-11 rounded-xl bg-red-700 px-5 text-sm font-black text-white hover:bg-red-800 disabled:opacity-50"
            :disabled="adminPageLoading"
            @click="confirmDeliveryStart"
          >
            配送中にする
          </button>
        </div>
      </section>
    </div>
  </div>
</template>
