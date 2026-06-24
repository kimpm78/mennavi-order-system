<script setup lang="ts">
import FallbackImage from '@/components/common/FallbackImage.vue'
import { Bell, CreditCard, ImageUp, Pencil, Settings, ShieldCheck, Store, Truck, X } from 'lucide-vue-next'
import { ref } from 'vue'
import type { MainVisualSetting } from './adminTypes'

type SettingRow = {
  key: 'admin_name' | 'admin_email' | 'admin_notifications_enabled'
  label: string
  value?: string | number | boolean | null
  description?: string | null
  category?: string | null
}

type MainVisualForm = {
  title: string
  description: string
  image: File | null
  image_path: string
  image_name: string
}

const props = defineProps<{
  settingRows: SettingRow[]
  mainVisualSetting: MainVisualSetting
  mainVisualForm: MainVisualForm
  adminPageLoading?: boolean
}>()

const emit = defineEmits<{
  uploadMainVisualImage: [event: Event]
  saveBasicSettings: [payload: { name?: string; email?: string; admin_notifications_enabled?: boolean }]
  saveMainVisualSetting: []
}>()

const editingRow = ref<SettingRow | null>(null)
const editingValue = ref('')
const editingNotificationEnabled = ref(true)
const isBasicSettingConfirmationOpen = ref(false)

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

const openBasicSettingEditor = (row: SettingRow) => {
  editingRow.value = row
  editingValue.value = typeof row.value === 'string' ? row.value : ''
  editingNotificationEnabled.value = Boolean(row.value)
  isBasicSettingConfirmationOpen.value = false
}

const closeBasicSettingEditor = () => {
  editingRow.value = null
  editingValue.value = ''
  isBasicSettingConfirmationOpen.value = false
}

const openBasicSettingConfirmation = () => {
  if (!editingRow.value) {
    return
  }

  if (editingRow.value.key !== 'admin_notifications_enabled' && !editingValue.value.trim()) {
    return
  }

  isBasicSettingConfirmationOpen.value = true
}

const saveBasicSetting = () => {
  if (!editingRow.value) {
    return
  }

  const payload = editingRow.value.key === 'admin_name'
    ? { name: editingValue.value.trim() }
    : editingRow.value.key === 'admin_email'
      ? { email: editingValue.value.trim() }
      : { admin_notifications_enabled: editingNotificationEnabled.value }

  emit('saveBasicSettings', payload)
  closeBasicSettingEditor()
}
</script>

<template>
  <div class="grid gap-6">
    <div>
      <div>
        <p class="text-sm font-bold text-red-600">設定</p>
        <h1 class="text-2xl font-black text-neutral-900">管理画面設定</h1>
      </div>
    </div>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
      <!-- 設定一覧 -->
      <div class="grid min-w-0 gap-6">
        <section class="overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm">
          <div class="flex items-center gap-3 border-b border-red-100 px-6 py-4">
            <div class="grid h-10 w-10 place-items-center rounded-xl bg-red-50 text-red-700">
              <ImageUp class="h-5 w-5" />
            </div>
            <div>
              <h2 class="text-lg font-black tracking-normal">メイン画面設定</h2>
              <p class="mt-1 text-xs font-bold text-neutral-500">トップの画像、タイトル、説明文を編集します。</p>
            </div>
          </div>

          <div class="grid gap-5 p-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <div>
              <div class="h-44 overflow-hidden rounded-lg bg-neutral-100">
                <FallbackImage
                  :src="mainVisualForm.image_path || mainVisualSetting.image_path"
                  alt="メイン画面画像"
                />
              </div>
              <label
                class="mt-3 inline-flex h-10 cursor-pointer items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 text-sm font-black text-red-700 hover:bg-red-50"
              >
                <ImageUp class="h-4 w-4" />
                画像を選択
                <input
                  class="sr-only"
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  @change="emit('uploadMainVisualImage', $event)"
                />
              </label>
              <p class="mt-2 text-xs font-bold leading-5 text-neutral-500">
                JPEG / PNG / WebP・5MBまで
              </p>
              <p v-if="mainVisualForm.image_name" class="mt-1 truncate text-xs font-black text-red-700">
                {{ mainVisualForm.image_name }}
              </p>
            </div>

            <div class="grid gap-4">
              <label class="grid gap-2 text-sm font-black text-[#5c4644]">
                タイトル
                <input
                  v-model="mainVisualForm.title"
                  class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50"
                  placeholder="今日の一杯を見つけよう"
                />
              </label>

              <label class="grid gap-2 text-sm font-black text-[#5c4644]">
                説明
                <textarea
                  v-model="mainVisualForm.description"
                  class="min-h-28 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 py-3 text-sm font-bold leading-7 text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50"
                  placeholder="メイン画面に表示する説明文"
                />
              </label>

              <div class="flex justify-end">
                <button
                  class="h-11 rounded-lg bg-red-700 px-5 text-sm font-black text-white hover:bg-red-800 disabled:opacity-60"
                  type="button"
                  :disabled="adminPageLoading"
                  @click="emit('saveMainVisualSetting')"
                >
                  保存する
                </button>
              </div>
            </div>
          </div>
        </section>

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

              <div class="flex items-center gap-2 md:justify-end">
                <span
                  class="inline-flex min-h-9 max-w-full items-center justify-center rounded-full bg-red-50 px-4 text-sm font-black text-red-800"
                >
                  {{ settingValue(row.value) }}
                </span>
                <button
                  class="inline-flex h-9 items-center justify-center gap-1 rounded-lg border border-red-200 bg-white px-3 text-xs font-black text-red-700 hover:bg-red-50 disabled:opacity-50"
                  type="button"
                  :disabled="adminPageLoading"
                  @click="openBasicSettingEditor(row)"
                >
                  <Pencil class="h-3.5 w-3.5" />
                  編集
                </button>
              </div>
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
          <p>管理者情報と通知設定を編集できます。</p>
          <p>メイン画面の画像、タイトル、説明文もここから更新できます。</p>
        </div>

        <div class="mt-6 grid gap-3 text-sm font-bold text-neutral-600">
          <div class="flex items-center justify-between gap-3 rounded-lg border border-red-100 px-4 py-3">
            <span>状態</span>
            <span class="font-black text-red-700">{{ adminPageLoading ? '更新中' : '確認済み' }}</span>
          </div>
        </div>
      </aside>
    </section>

    <div
      v-if="editingRow"
      class="fixed inset-0 z-50 grid place-items-center bg-black/40 px-4 py-6"
      @click.self="closeBasicSettingEditor"
    >
      <section class="w-full max-w-md rounded-xl bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-red-100 px-6 py-5">
          <div>
            <p class="text-sm font-black text-red-700">基本設定</p>
            <h2 class="mt-1 text-xl font-black text-neutral-900">
              {{ isBasicSettingConfirmationOpen ? '変更確認' : `${editingRow.label}を編集` }}
            </h2>
          </div>
          <button
            class="grid h-9 w-9 place-items-center rounded-lg border border-red-100 text-red-700 hover:bg-red-50"
            type="button"
            aria-label="編集を閉じる"
            @click="closeBasicSettingEditor"
          >
            <X class="h-4 w-4" />
          </button>
        </div>

        <div class="p-6">
          <template v-if="!isBasicSettingConfirmationOpen">
            <label
              v-if="editingRow.key !== 'admin_notifications_enabled'"
              class="grid gap-2 text-sm font-black text-neutral-700"
            >
              {{ editingRow.label }}
              <input
                v-model="editingValue"
                :type="editingRow.key === 'admin_email' ? 'email' : 'text'"
                class="h-12 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100"
                :maxlength="editingRow.key === 'admin_name' ? 100 : 255"
              />
            </label>

            <div v-else class="flex items-center justify-between gap-4 rounded-lg border border-red-100 bg-red-50/40 p-4">
              <div>
                <p class="text-sm font-black text-neutral-900">注文・遅延アラートを受信</p>
                <p class="mt-1 text-xs font-bold text-neutral-500">管理画面の通知表示を切り替えます。</p>
              </div>
              <button
                class="relative h-7 w-12 shrink-0 rounded-full transition"
                :class="editingNotificationEnabled ? 'bg-red-700' : 'bg-neutral-300'"
                type="button"
                role="switch"
                :aria-checked="editingNotificationEnabled"
                aria-label="通知を切り替える"
                @click="editingNotificationEnabled = !editingNotificationEnabled"
              >
                <span
                  class="absolute top-1 h-5 w-5 rounded-full bg-white shadow transition"
                  :class="editingNotificationEnabled ? 'left-6' : 'left-1'"
                />
              </button>
            </div>

            <div class="mt-6 flex justify-end gap-3">
              <button
                class="h-11 rounded-lg border border-neutral-200 px-5 text-sm font-black text-neutral-600 hover:bg-neutral-50"
                type="button"
                @click="closeBasicSettingEditor"
              >
                キャンセル
              </button>
              <button
                class="h-11 rounded-lg bg-red-700 px-5 text-sm font-black text-white hover:bg-red-800"
                type="button"
                @click="openBasicSettingConfirmation"
              >
                確認
              </button>
            </div>
          </template>

          <template v-else>
            <p class="text-base font-black text-neutral-900">本当に変更しますか？</p>
            <p class="mt-2 text-sm font-bold leading-6 text-neutral-600">
              {{ editingRow.label }}：
              {{ editingRow.key === 'admin_notifications_enabled' ? (editingNotificationEnabled ? '有効' : '無効') : editingValue }}
            </p>
            <div class="mt-6 flex justify-end gap-3">
              <button
                class="h-11 rounded-lg border border-neutral-200 px-5 text-sm font-black text-neutral-600 hover:bg-neutral-50"
                type="button"
                @click="isBasicSettingConfirmationOpen = false"
              >
                いいえ
              </button>
              <button
                class="h-11 rounded-lg bg-red-700 px-5 text-sm font-black text-white hover:bg-red-800 disabled:opacity-50"
                type="button"
                :disabled="adminPageLoading"
                @click="saveBasicSetting"
              >
                はい
              </button>
            </div>
          </template>
        </div>
      </section>
    </div>
  </div>
</template>
