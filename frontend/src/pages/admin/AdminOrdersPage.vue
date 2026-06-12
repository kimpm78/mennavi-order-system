<script setup lang="ts">

type AdminOrderItem = {
  product_name?: string | null
  name?: string | null
  quantity?: number | null
  price?: number | null
  unit_price?: number | null
}

type AdminOrder = {
  order_id: number | string
  order_number?: string | null
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
  order_date?: string | null
  ordered_at?: string | null
  created_at?: string | null
  items?: AdminOrderItem[]
}

defineProps<{
  orders: AdminOrder[]
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
  updateOrderStatus: [order: AdminOrder]
}>()

const orderTotalAmount = (order: AdminOrder) => {
  return Number(order.total_amount ?? order.total_price ?? 0)
}

const orderCustomerName = (order: AdminOrder) => {
  return order.customer_name || order.recipient_name || 'ゲスト'
}

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

    <section class="rounded-xl border border-red-200 bg-white shadow-sm">
      <div class="border-b border-red-100 px-6 py-4">
        <h2 class="text-lg font-black tracking-normal">注文状況</h2>
        <p class="mt-1 text-xs font-bold text-neutral-500">
          受付済み・調理中・完了などのステータスを確認できます。
        </p>
      </div>

      <div class="grid gap-4 p-4">
        <article
          v-for="order in orders"
          :key="order.order_id"
          class="rounded-xl border border-red-100 bg-white p-4 shadow-sm"
        >
          <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-black text-neutral-900">
                  注文番号 #{{ order.order_number || order.order_id }}
                </p>

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

              <div class="mt-3 grid gap-2 text-sm font-bold text-neutral-600 md:grid-cols-2">
                <p class="min-w-0 truncate">注文者：{{ orderCustomerName(order) }}</p>
                <p class="min-w-0 truncate">注文日時：{{ formatOrderDateTime(orderDateValue(order)) }}</p>
                <p class="min-w-0 truncate">電話番号：{{ orderPhone(order) }}</p>
                <p class="min-w-0 truncate">配送先：{{ orderAddress(order) }}</p>
              </div>

              <div
                v-if="order.items && order.items.length > 0"
                class="mt-4 overflow-hidden rounded-lg border border-red-100"
              >
                <div
                  v-for="item in order.items"
                  :key="`${order.order_id}-${itemName(item)}`"
                  class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-3 border-b border-red-50 px-4 py-3 text-sm last:border-b-0"
                >
                  <p class="min-w-0 truncate font-bold text-neutral-800">{{ itemName(item) }}</p>
                  <p class="shrink-0 font-bold text-neutral-500">× {{ item.quantity ?? 1 }}</p>
                  <p class="shrink-0 font-black text-neutral-900">{{ formatPrice(itemPrice(item)) }}</p>
                </div>
              </div>
            </div>

            <div class="grid shrink-0 gap-3 xl:min-w-40">
              <p class="text-right text-xl font-black text-neutral-900 xl:text-2xl">
                {{ formatPrice(orderTotalAmount(order)) }}
              </p>

              <button
                class="h-10 rounded-lg bg-red-700 px-4 text-sm font-black text-white hover:bg-red-800 disabled:opacity-50"
                type="button"
                :disabled="adminPageLoading || !nextOrderStatus(order)"
                @click="emit('updateOrderStatus', order)"
              >
                {{ orderActionLabel(order) }}
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
    </section>
  </div>
</template>
