<script setup lang="ts">

import { BarChart3, CalendarDays, ReceiptText, TrendingUp } from 'lucide-vue-next'

type SalesRow = {
  label?: string
  date?: string
  store_name?: string | null
  order_count?: number | null
  total_sales?: number | null
  sales_amount?: number | null
  average_order_amount?: number | null
  average_amount?: number | null
  change_rate?: number | string | null
}

type KitchenBar = {
  label: string
  value: number
  max?: number
}

const props = defineProps<{
  salesRows: SalesRow[]
  kitchenBars?: KitchenBar[]
  adminPageLoading?: boolean
  formatPrice: (price: number) => string
  formatChangeRate?: (value: string | number | null | undefined) => string
}>()

const rowLabel = (row: SalesRow) => {
  return row.label || row.date || row.store_name || '未設定'
}

const rowSalesAmount = (row: SalesRow) => {
  return Number(row.total_sales ?? row.sales_amount ?? 0)
}

const rowAverageAmount = (row: SalesRow) => {
  return Number(row.average_order_amount ?? row.average_amount ?? 0)
}

const totalSalesAmount = () => {
  return props.salesRows.reduce((total, row) => total + rowSalesAmount(row), 0)
}

const totalOrderCount = () => {
  return props.salesRows.reduce((total, row) => total + Number(row.order_count ?? 0), 0)
}

const averageOrderAmount = () => {
  const orderCount = totalOrderCount()

  if (orderCount === 0) {
    return 0
  }

  return Math.round(totalSalesAmount() / orderCount)
}

const displayChangeRate = (value: string | number | null | undefined) => {
  if (props.formatChangeRate) {
    return props.formatChangeRate(value)
  }

  if (value === undefined || value === null || value === '') {
    return '-'
  }

  return `${value}%`
}
</script>

<template>
  <div class="grid gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm font-bold text-red-600">売上分析</p>
        <h1 class="text-2xl font-black text-neutral-900">売上状況</h1>
      </div>

      <div class="rounded-full border border-red-100 bg-white px-4 py-2 text-sm font-bold text-neutral-600 shadow-sm">
        {{ adminPageLoading ? '集計中...' : `${salesRows.length}件` }}
      </div>
    </div>

    <!-- 売上サマリー -->
    <section class="grid gap-4 md:grid-cols-3">
      <article class="rounded-xl border border-red-100 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-bold text-neutral-500">売上合計</p>
            <p class="mt-2 truncate text-2xl font-black text-neutral-900">
              {{ formatPrice(totalSalesAmount()) }}
            </p>
          </div>
          <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-red-50 text-red-700">
            <TrendingUp class="h-5 w-5" />
          </div>
        </div>
      </article>

      <article class="rounded-xl border border-red-100 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-bold text-neutral-500">注文数</p>
            <p class="mt-2 truncate text-2xl font-black text-neutral-900">
              {{ totalOrderCount() }}件
            </p>
          </div>
          <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-red-50 text-red-700">
            <ReceiptText class="h-5 w-5" />
          </div>
        </div>
      </article>

      <article class="rounded-xl border border-red-100 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-bold text-neutral-500">平均注文単価</p>
            <p class="mt-2 truncate text-2xl font-black text-neutral-900">
              {{ formatPrice(averageOrderAmount()) }}
            </p>
          </div>
          <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-red-50 text-red-700">
            <BarChart3 class="h-5 w-5" />
          </div>
        </div>
      </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
      <!-- 売上一覧 -->
      <section class="min-w-0 rounded-xl border border-red-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-red-100 px-6 py-4">
          <div>
            <h2 class="text-lg font-black tracking-normal">売上一覧</h2>
            <p class="mt-1 text-xs font-bold text-neutral-500">
              日別または店舗別の売上を確認できます。
            </p>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-red-100 text-left text-sm">
            <thead class="bg-red-50 text-xs font-black text-red-800">
              <tr>
                <th class="whitespace-nowrap px-6 py-3">対象</th>
                <th class="whitespace-nowrap px-6 py-3 text-right">注文数</th>
                <th class="whitespace-nowrap px-6 py-3 text-right">売上</th>
                <th class="whitespace-nowrap px-6 py-3 text-right">平均単価</th>
                <th class="whitespace-nowrap px-6 py-3 text-right">前期比</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-red-50 bg-white">
              <tr
                v-for="row in salesRows"
                :key="rowLabel(row)"
                class="hover:bg-red-50/50"
              >
                <td class="whitespace-nowrap px-6 py-4 font-black text-neutral-900">
                  {{ rowLabel(row) }}
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right font-bold text-neutral-700">
                  {{ row.order_count ?? 0 }}件
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right font-black text-neutral-900">
                  {{ formatPrice(rowSalesAmount(row)) }}
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right font-bold text-neutral-700">
                  {{ formatPrice(rowAverageAmount(row)) }}
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right font-black text-red-700">
                  {{ displayChangeRate(row.change_rate) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          v-if="salesRows.length === 0"
          class="border-t border-red-100 p-8 text-center"
        >
          <p class="text-sm font-bold text-neutral-500">売上データがありません。</p>
        </div>
      </section>

      <!-- 処理状況 -->
      <section class="rounded-xl border border-red-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="grid h-11 w-11 place-items-center rounded-xl bg-red-50 text-red-700">
            <CalendarDays class="h-5 w-5" />
          </div>
          <div>
            <h2 class="text-lg font-black tracking-normal">分析メモ</h2>
            <p class="mt-1 text-xs font-bold text-neutral-500">売上と注文状況の簡易確認</p>
          </div>
        </div>

        <div class="mt-5 grid gap-4 rounded-xl bg-red-50 p-4 text-sm font-bold text-red-900">
          <p>売上合計：{{ formatPrice(totalSalesAmount()) }}</p>
          <p>注文数：{{ totalOrderCount() }}件</p>
          <p>平均注文単価：{{ formatPrice(averageOrderAmount()) }}</p>
        </div>

        <div
          v-if="kitchenBars && kitchenBars.length > 0"
          class="mt-6 grid gap-5"
        >
          <div
            v-for="bar in kitchenBars"
            :key="bar.label"
            class="grid gap-2"
          >
            <div class="flex items-center justify-between gap-3 text-sm font-black">
              <span class="text-neutral-700">{{ bar.label }}</span>
              <span class="text-red-700">{{ bar.value }}</span>
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
  </div>
</template>