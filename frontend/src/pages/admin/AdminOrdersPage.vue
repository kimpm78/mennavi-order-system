<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, Eye, X } from 'lucide-vue-next'

type AdminOrderItem = {
  product_name?: string | null
  name?: string | null
  store_name?: string | null
  quantity?: number | null
  price?: number | null
  unit_price?: number | null
}

type AdminOrder = {
  order_id: number | string
  order_number?: string | null
  store_name?: string | null
  store_invoice_number?: string | null
  applied_subscription_code?: string | null
  customer_name?: string | null
  recipient_name?: string | null
  customer_phone?: string | null
  recipient_phone?: string | null
  shipping_address?: string | null
  shipping_address_snapshot?: string | null
  total_amount?: number | null
  total_price?: number | null
  order_status?: string | null
  payment_status?: string | null
  receipt_type?: string | null
  delivery_staff_name?: string | null
  type?: string | null
  order_date?: string | null
  ordered_at?: string | null
  created_at?: string | null
  items?: AdminOrderItem[]
}

const props = defineProps<{
  orders: AdminOrder[]
  detailOrderId?: number | string | null
  adminPageLoading?: boolean
  formatPrice: (price: number) => string
  formatElapsed: (value: string | null | undefined) => string
  orderStatusLabel: (status: string | null | undefined) => string
  paymentStatusLabel: (status: string | null | undefined) => string
  paymentStatusClass: (status: string | null | undefined) => string
  nextOrderStatus: (order: AdminOrder) => string | null | undefined
  orderActionLabel: (order: AdminOrder) => string
}>()

const emit = defineEmits<{
  updateOrderStatus: [order: AdminOrder, deliveryStaffName?: string]
  cancelOrder: [order: AdminOrder]
  detailOrderOpened: []
}>()

const PAGE_SIZE = 10
const DELIVERY_STAFF_NAMES = ['佐藤A', '鈴木B'] as const

const currentPage = ref(1)
const selectedOrder = ref<AdminOrder | null>(null)
const deliveryTargetOrder = ref<AdminOrder | null>(null)
const cancelTargetOrder = ref<AdminOrder | null>(null)
const selectedDeliveryStaffName = ref<string>(DELIVERY_STAFF_NAMES[0])

const totalPages = computed(() => {
  return Math.max(1, Math.ceil(props.orders.length / PAGE_SIZE))
})

const paginatedOrders = computed(() => {
  const startIndex = (currentPage.value - 1) * PAGE_SIZE
  return props.orders.slice(startIndex, startIndex + PAGE_SIZE)
})

const displayedOrderRange = computed(() => {
  if (props.orders.length === 0) {
    return { start: 0, end: 0 }
  }

  return {
    start: (currentPage.value - 1) * PAGE_SIZE + 1,
    end: Math.min(currentPage.value * PAGE_SIZE, props.orders.length),
  }
})

watch(
  () => props.orders.length,
  () => {
    if (currentPage.value > totalPages.value) {
      currentPage.value = totalPages.value
    }
  },
)

const orderTotalAmount = (order: AdminOrder) => {
  return Number(order.total_amount ?? order.total_price ?? 0)
}

const orderCustomerName = (order: AdminOrder) => {
  return order.customer_name || order.recipient_name || 'ゲスト'
}

const orderStoreName = (order: AdminOrder) => {
  return order.store_name || order.items?.find((item) => item.store_name)?.store_name || '店舗名未登録'
}

const hasPlusBenefit = (order: AdminOrder) => order.applied_subscription_code === 'mennavi_plus'

const orderDateValue = (order: AdminOrder) => {
  return order.order_date || order.ordered_at || order.created_at || null
}

const formatOrderDateTime = (value: string | null | undefined) => {
  if (!value) {
    return '-'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hour = String(date.getHours()).padStart(2, '0')
  const minute = String(date.getMinutes()).padStart(2, '0')

  return `${year}-${month}-${day} (${hour}:${minute})`
}

const orderPhone = (order: AdminOrder) => {
  return order.recipient_phone || order.customer_phone || '未登録'
}

const orderAddress = (order: AdminOrder) => {
  return order.shipping_address || order.shipping_address_snapshot || '住所未登録'
}

const itemName = (item: AdminOrderItem) => {
  return item.product_name || item.name || '商品名未登録'
}

const itemPrice = (item: AdminOrderItem) => {
  return Number(item.price ?? item.unit_price ?? 0)
}

const canCancelOrder = (order: AdminOrder) => {
  return !['completed', 'canceled', 'cancelled'].includes(order.order_status ?? '')
}

const cancelActionLabel = (order: AdminOrder) => {
  return canCancelOrder(order) ? '返品（キャンセル）' : '返品（不可）'
}

const orderKey = (order: AdminOrder) => {
  return String(order.order_id)
}

const findOrderByKey = (order: AdminOrder | null) => {
  if (!order) {
    return null
  }

  return props.orders.find((item) => orderKey(item) === orderKey(order)) ?? null
}

const openOrderDetail = (order: AdminOrder) => {
  selectedOrder.value = order
}

const openDetailFromNotification = () => {
  if (!props.detailOrderId) {
    return
  }

  const targetIndex = props.orders.findIndex((order) => String(order.order_id) === String(props.detailOrderId))
  if (targetIndex < 0) {
    return
  }

  currentPage.value = Math.floor(targetIndex / PAGE_SIZE) + 1
  selectedOrder.value = props.orders[targetIndex]
  emit('detailOrderOpened')
}

watch(
  [() => props.detailOrderId, () => props.orders],
  () => {
    openDetailFromNotification()
  },
  { immediate: true },
)

watch(
  () => props.orders,
  () => {
    if (selectedOrder.value) {
      selectedOrder.value = findOrderByKey(selectedOrder.value) ?? selectedOrder.value
    }

    if (deliveryTargetOrder.value) {
      deliveryTargetOrder.value = findOrderByKey(deliveryTargetOrder.value) ?? deliveryTargetOrder.value
    }

    if (cancelTargetOrder.value) {
      cancelTargetOrder.value = findOrderByKey(cancelTargetOrder.value) ?? cancelTargetOrder.value
    }
  },
)

const closeOrderDetail = () => {
  selectedOrder.value = null
  cancelTargetOrder.value = null
}

const goToPage = (page: number) => {
  currentPage.value = Math.min(Math.max(page, 1), totalPages.value)
}

const openDeliveryStaffModal = (order: AdminOrder) => {
  selectedDeliveryStaffName.value = order.delivery_staff_name || DELIVERY_STAFF_NAMES[0]
  deliveryTargetOrder.value = order
}

const closeDeliveryStaffModal = () => {
  deliveryTargetOrder.value = null
  selectedDeliveryStaffName.value = DELIVERY_STAFF_NAMES[0]
}

const openCancelConfirmModal = (order: AdminOrder) => {
  cancelTargetOrder.value = order
}

const closeCancelConfirmModal = () => {
  cancelTargetOrder.value = null
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

const confirmCancelOrder = () => {
  if (!cancelTargetOrder.value) {
    return
  }

  emit('cancelOrder', cancelTargetOrder.value)
  closeCancelConfirmModal()
}
</script>

<template>
  <div class="grid gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm font-bold text-red-600">注文管理</p>
        <h1 class="text-2xl font-black text-neutral-900">注文一覧</h1>
      </div>

      <div class="rounded-full border border-red-100 bg-white px-4 py-2 text-sm font-bold text-neutral-600 shadow-sm">
        {{ adminPageLoading ? '読み込み中...' : `${orders.length}件` }}
      </div>
    </div>

    <section class="overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm">
      <div class="border-b border-red-100 px-6 py-4">
        <h2 class="text-lg font-black tracking-normal">注文状況</h2>
        <p class="mt-1 text-xs font-bold text-neutral-500">
          受付済み・調理中・配送中・完了などのステータスを確認できます。
        </p>
      </div>

      <div class="grid gap-4 p-4">
        <article
          v-for="order in paginatedOrders"
          :key="order.order_id"
          class="rounded-xl border border-red-100 bg-white p-4 shadow-sm"
        >
          <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
            <div class="min-w-0">
              <div class="flex flex-wrap items-start gap-2">
                <div class="min-w-0">
                  <p class="text-sm font-black text-neutral-900">
                    注文番号 #{{ order.order_number || order.order_id }}
                  </p>
                  <div class="mt-1 flex min-w-0 items-center gap-2">
                    <p class="truncate text-xs font-bold text-neutral-500">{{ orderStoreName(order) }}</p>
                    <span
                      v-if="hasPlusBenefit(order)"
                      class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-black text-amber-800"
                    >
                      麺ナビ Plus
                    </span>
                  </div>
                </div>

                <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700">
                  {{ orderStatusLabel(order.order_status) }}
                </span>

                <span
                  class="rounded-full px-3 py-1 text-xs font-black"
                  :class="paymentStatusClass(order.payment_status)"
                >
                  {{ paymentStatusLabel(order.payment_status) }}
                </span>
              </div>
            </div>

            <div class="grid shrink-0 gap-3 xl:min-w-40">
              <p class="text-right text-xl font-black text-neutral-900 xl:text-2xl">
                {{ formatPrice(orderTotalAmount(order)) }}
              </p>

              <button
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-neutral-200 px-4 text-sm font-black text-neutral-700 hover:bg-neutral-50"
                type="button"
                @click="openOrderDetail(order)"
              >
                <Eye class="h-4 w-4" />
                詳細
              </button>
            </div>
          </div>
        </article>

        <div
          v-if="orders.length === 0"
          class="rounded-xl border border-dashed border-red-200 p-8 text-center"
        >
          <p class="text-sm font-bold text-neutral-500">注文情報がありません。</p>
        </div>
      </div>

      <div
        v-if="orders.length > PAGE_SIZE"
        class="flex flex-col gap-3 border-t border-red-100 px-4 py-4 text-sm font-bold text-neutral-500 sm:flex-row sm:items-center sm:justify-between"
      >
        <p>
          {{ orders.length }}件中 {{ displayedOrderRange.start }}〜{{ displayedOrderRange.end }}件を表示
        </p>

        <div class="flex flex-wrap items-center gap-1">
          <button
            class="grid h-9 w-9 place-items-center rounded-lg border border-red-100 bg-white text-neutral-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
            type="button"
            :disabled="currentPage === 1"
            aria-label="前のページ"
            @click="goToPage(currentPage - 1)"
          >
            <ChevronLeft class="h-4 w-4" />
          </button>

          <button
            v-for="page in totalPages"
            :key="page"
            class="grid h-9 min-w-9 place-items-center rounded-lg border px-2 text-sm font-black"
            :class="currentPage === page ? 'border-red-700 bg-red-700 text-white' : 'border-red-100 bg-white text-neutral-700 hover:bg-red-50'"
            type="button"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>

          <button
            class="grid h-9 w-9 place-items-center rounded-lg border border-red-100 bg-white text-neutral-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
            type="button"
            :disabled="currentPage === totalPages"
            aria-label="次のページ"
            @click="goToPage(currentPage + 1)"
          >
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>
      </div>
    </section>

    <div
      v-if="selectedOrder"
      class="fixed inset-0 z-50 grid place-items-center bg-black/40 px-4 py-6"
      @click.self="closeOrderDetail"
    >
      <section class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
        <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-red-100 bg-white px-6 py-4">
          <div>
            <p class="text-sm font-black text-red-700">注文詳細</p>
            <h2 class="mt-1 text-xl font-black text-neutral-900">
              注文番号 #{{ selectedOrder.order_number || selectedOrder.order_id }}
            </h2>
            <div class="mt-1 flex flex-wrap items-center gap-2">
              <p class="text-sm font-bold text-neutral-500">{{ orderStoreName(selectedOrder) }}</p>
              <span
                v-if="hasPlusBenefit(selectedOrder)"
                class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-black text-amber-800"
              >
                麺ナビ Plus
              </span>
            </div>
          </div>

          <button
            class="grid h-10 w-10 place-items-center rounded-full border border-red-100 text-red-700 hover:bg-red-50"
            type="button"
            aria-label="詳細を閉じる"
            @click="closeOrderDetail"
          >
            <X class="h-5 w-5" />
          </button>
        </div>

        <div class="grid gap-6 p-6">
          <div class="flex flex-wrap gap-2">
            <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700">
              {{ orderStatusLabel(selectedOrder.order_status) }}
            </span>
            <span
              class="rounded-full px-3 py-1 text-xs font-black"
              :class="paymentStatusClass(selectedOrder.payment_status)"
            >
              {{ paymentStatusLabel(selectedOrder.payment_status) }}
            </span>
          </div>

          <div class="flex items-center justify-between rounded-xl border border-red-100 bg-white px-4 py-4">
            <span class="text-sm font-black text-neutral-500">合計金額</span>
            <span class="text-2xl font-black text-neutral-900">
              {{ formatPrice(orderTotalAmount(selectedOrder)) }}
            </span>
          </div>

          <dl class="grid gap-4 rounded-xl border border-red-100 bg-red-50/40 p-4 text-sm md:grid-cols-2">
            <div>
              <dt class="font-black text-neutral-500">注文者</dt>
              <dd class="mt-1 font-bold text-neutral-900">{{ orderCustomerName(selectedOrder) }}</dd>
            </div>
            <div>
              <dt class="font-black text-neutral-500">注文日時</dt>
              <dd class="mt-1 font-bold text-neutral-900">
                {{ formatOrderDateTime(orderDateValue(selectedOrder)) }}
              </dd>
            </div>
            <div>
              <dt class="font-black text-neutral-500">インボイス番号</dt>
              <dd class="mt-1 font-bold text-neutral-900">
                {{ selectedOrder.store_invoice_number || '未登録' }}
              </dd>
            </div>
            <div>
              <dt class="font-black text-neutral-500">電話番号</dt>
              <dd class="mt-1 font-bold text-neutral-900">{{ orderPhone(selectedOrder) }}</dd>
            </div>
            <div>
              <dt class="font-black text-neutral-500">受け取り方法</dt>
              <dd class="mt-1 font-bold text-neutral-900">{{ selectedOrder.type || '-' }}</dd>
            </div>
            <div>
              <dt class="font-black text-neutral-500">配送担当</dt>
              <dd class="mt-1 font-bold text-neutral-900">
                {{ selectedOrder.delivery_staff_name || '未設定' }}
              </dd>
            </div>
            <div class="md:col-span-2">
              <dt class="font-black text-neutral-500">配送先</dt>
              <dd class="mt-1 break-words font-bold text-neutral-900">
                {{ orderAddress(selectedOrder) }}
              </dd>
            </div>
          </dl>

          <section>
            <h3 class="text-base font-black text-neutral-900">注文商品</h3>
            <div class="mt-3 overflow-hidden rounded-xl border border-red-100">
              <div
                v-for="item in selectedOrder.items ?? []"
                :key="`${selectedOrder.order_id}-${itemName(item)}`"
                class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-3 border-b border-red-50 px-4 py-3 text-sm last:border-b-0"
              >
                <p class="min-w-0 truncate font-bold text-neutral-800">{{ itemName(item) }}</p>
                <p class="shrink-0 font-bold text-neutral-500">× {{ item.quantity ?? 1 }}</p>
                <p class="shrink-0 font-black text-neutral-900">{{ formatPrice(itemPrice(item)) }}</p>
              </div>

              <p
                v-if="!selectedOrder.items || selectedOrder.items.length === 0"
                class="px-4 py-6 text-center text-sm font-bold text-neutral-500"
              >
                商品情報がありません。
              </p>
            </div>
          </section>

          <div class="flex flex-col gap-3 border-t border-red-100 pt-4 sm:flex-row sm:justify-end">
            <button
              class="h-11 rounded-lg bg-red-700 px-5 text-sm font-black text-white hover:bg-red-800 disabled:opacity-50"
              type="button"
              :disabled="adminPageLoading || !nextOrderStatus(selectedOrder)"
              @click="requestOrderStatusUpdate(selectedOrder)"
            >
              {{ orderActionLabel(selectedOrder) }}
            </button>

            <button
              class="h-11 rounded-lg border border-red-200 px-5 text-sm font-black text-red-700 hover:bg-red-50 disabled:opacity-50"
              type="button"
              :disabled="adminPageLoading || !canCancelOrder(selectedOrder)"
              @click="openCancelConfirmModal(selectedOrder)"
            >
              {{ cancelActionLabel(selectedOrder) }}
            </button>
          </div>
        </div>
      </section>
    </div>

    <div
      v-if="deliveryTargetOrder"
      class="fixed inset-0 z-[60] grid place-items-center bg-black/40 px-4"
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
            <option v-for="staff in DELIVERY_STAFF_NAMES" :key="staff" :value="staff">
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

    <div
      v-if="cancelTargetOrder"
      class="fixed inset-0 z-[70] grid place-items-center bg-black/40 px-4"
      @click.self="closeCancelConfirmModal"
    >
      <section class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <div>
          <p class="text-sm font-black text-red-700">返品（キャンセル）</p>
          <h2 class="mt-1 text-xl font-black text-neutral-900">キャンセルしてもよろしいですか？</h2>
          <p class="mt-2 text-sm font-bold leading-6 text-neutral-500">
            注文番号 #{{ cancelTargetOrder.order_number || cancelTargetOrder.order_id }} をキャンセルします。
          </p>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            class="h-11 rounded-xl border border-neutral-200 px-5 text-sm font-black text-neutral-600 hover:bg-neutral-50"
            @click="closeCancelConfirmModal"
          >
            いいえ
          </button>
          <button
            type="button"
            class="h-11 rounded-xl bg-red-700 px-5 text-sm font-black text-white hover:bg-red-800 disabled:opacity-50"
            :disabled="adminPageLoading"
            @click="confirmCancelOrder"
          >
            はい
          </button>
        </div>
      </section>
    </div>
  </div>
</template>
