<script setup lang="ts">

import { Bell, CreditCard, Settings, ShieldCheck, Store, Truck } from 'lucide-vue-next'

type SettingRow = {
  label: string
  value?: string | number | boolean | null
  description?: string | null
  category?: string | null
}

const props = defineProps<{
  settingRows: SettingRow[]
  adminPageLoading?: boolean
}>()

const categoryIconMap = {
  store: Store,
  payment: CreditCard,
  delivery: Truck,
  notification: Bell,
  security: ShieldCheck,
  default: Settings,
}

const categoryLabelMap = {
  store: '店舗設定',
  payment: '決済設定',
  delivery: '配送設定',
  notification: '通知設定',
  security: 'セキュリティ設定',
  default: '基本設定',
}

const resolveCategory = (row: SettingRow) => {
  return row.category || 'default'
}

const resolveCategoryLabel = (category: string) => {
  return categoryLabelMap[category as keyof typeof categoryLabelMap] || category
}

const resolveCategoryIcon = (category: string) => {
  return categoryIconMap[category as keyof typeof categoryIconMap] || Settings
}

const settingValue = (value: SettingRow['value']) => {
  if (value === true) {
    return '有効'
  }

  if (value === false) {
    return '無効'
  }

  if (value === null || value === undefined || value === '') {
    return '未設定'
  }

  return String(value)
}

const groupedSettings = () => {
  return props.settingRows.reduce<Record<string, SettingRow[]>>((groups, row) => {
    const category = resolveCategory(row)

    if (!groups[category]) {
      groups[category] = []
    }

    groups[category].push(row)
    return groups
  }, {})
}
</script>

<template>
  <div class="grid gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm font-bold text-red-600">設定</p>
        <h1 class="text-2xl font-black text-neutral-900">管理画面設定</h1>
      </div>

      <div class="rounded-full border border-red-100 bg-white px-4 py-2 text-sm font-bold text-neutral-600 shadow-sm">
        {{ adminPageLoading ? '読み込み中...' : `${settingRows.length}件` }}
      </div>
    </div>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
      <!-- 設定一覧 -->
      <div class="grid min-w-0 gap-6">
        <section
          v-for="(rows, category) in groupedSettings()"
          :key="category"
          class="overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm"
        >
          <div class="flex items-center gap-3 border-b border-red-100 px-6 py-4">
            <div class="grid h-10 w-10 place-items-center rounded-xl bg-red-50 text-red-700">
              <component :is="resolveCategoryIcon(String(category))" class="h-5 w-5" />
            </div>
            <div>
              <h2 class="text-lg font-black tracking-normal">
                {{ resolveCategoryLabel(String(category)) }}
              </h2>
              <p class="mt-1 text-xs font-bold text-neutral-500">
                {{ rows.length }}件の設定項目
              </p>
            </div>
          </div>

          <div class="divide-y divide-red-50">
            <div
              v-for="row in rows"
              :key="`${category}-${row.label}`"
              class="grid gap-3 px-6 py-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
            >
              <div class="min-w-0">
                <p class="truncate text-sm font-black text-neutral-900">{{ row.label }}</p>
                <p
                  v-if="row.description"
                  class="mt-1 text-xs font-bold leading-5 text-neutral-500"
                >
                  {{ row.description }}
                </p>
              </div>

              <span
                class="inline-flex min-h-9 max-w-full items-center justify-center rounded-full bg-red-50 px-4 text-sm font-black text-red-800 md:justify-end"
              >
                {{ settingValue(row.value) }}
              </span>
            </div>
          </div>
        </section>

        <section
          v-if="settingRows.length === 0"
          class="rounded-xl border border-dashed border-red-200 bg-white p-8 text-center shadow-sm"
        >
          <p class="text-sm font-bold text-neutral-500">設定情報がありません。</p>
        </section>
      </div>

      <!-- 補足 -->
      <aside class="rounded-xl border border-red-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="grid h-11 w-11 place-items-center rounded-xl bg-red-50 text-red-700">
            <Settings class="h-5 w-5" />
          </div>
          <div>
            <h2 class="text-lg font-black tracking-normal">設定メモ</h2>
            <p class="mt-1 text-xs font-bold text-neutral-500">管理画面の基本設定</p>
          </div>
        </div>

        <div class="mt-5 grid gap-4 rounded-xl bg-red-50 p-4 text-sm font-bold leading-6 text-red-900">
          <p>店舗情報、決済、配送、通知などの設定を確認できます。</p>
          <p>編集機能は必要に応じて後続対応で追加する想定です。</p>
        </div>

        <div class="mt-6 grid gap-3 text-sm font-bold text-neutral-600">
          <div class="flex items-center justify-between gap-3 rounded-lg border border-red-100 px-4 py-3">
            <span>設定項目数</span>
            <span class="font-black text-red-700">{{ settingRows.length }}件</span>
          </div>
          <div class="flex items-center justify-between gap-3 rounded-lg border border-red-100 px-4 py-3">
            <span>状態</span>
            <span class="font-black text-red-700">{{ adminPageLoading ? '更新中' : '確認済み' }}</span>
          </div>
        </div>
      </aside>
    </section>
  </div>
</template>