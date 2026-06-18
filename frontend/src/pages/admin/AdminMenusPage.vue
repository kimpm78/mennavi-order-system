<script setup lang="ts">
import FallbackImage from '@/components/common/FallbackImage.vue'
import { Plus, SquarePen, Trash2, X } from 'lucide-vue-next'
import type { AdminStoreRow, MenuRowView } from './adminTypes'

const holidayOptions = ['月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日', '日曜日', '祝日']

type StoreProfileForm = {
  name: string
  description: string
  address: string
  phone: string
  weekday_hours: string
  weekend_hours: string
  holidays: string[]
  budget_min: number | string | null
  budget_max: number | string | null
}

defineProps<{
  adminStores: AdminStoreRow[]
  selectedAdminStore: AdminStoreRow | null
  storeProfileForm: StoreProfileForm
  menuRows: MenuRowView[]
  adminPageLoading?: boolean
  formatPrice: (price: number) => string
}>()

const emit = defineEmits<{
  selectStore: [store: AdminStoreRow]
  uploadStoreImage: [event: Event]
  saveStoreProfile: []
  openProductModal: [menu?: MenuRowView]
  deleteMenu: [menu: MenuRowView]
}>()

const removeHoliday = (form: StoreProfileForm, holiday: string) => {
  form.holidays = form.holidays.filter((selectedHoliday) => selectedHoliday !== holiday)
}

const addHoliday = (form: StoreProfileForm, event: Event) => {
  const select = event.target as HTMLSelectElement
  const holiday = select.value

  if (holiday && !form.holidays.includes(holiday)) {
    form.holidays = [...form.holidays, holiday]
  }

  select.value = ''
}
</script>

<template>
  <div class="grid gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm font-bold text-red-600">店舗・メニュー管理</p>
        <h1 class="text-2xl font-black text-neutral-900">店舗情報とメニューを管理</h1>
      </div>

      <button
        class="inline-flex h-11 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-black text-white shadow-sm hover:bg-red-800 disabled:opacity-60"
        type="button"
        :disabled="!selectedAdminStore || adminPageLoading"
        @click="emit('openProductModal')"
      >
        <Plus class="h-4 w-4" />
        メニュー追加
      </button>
    </div>

    <section
      v-if="selectedAdminStore"
      class="grid gap-6 2xl:grid-cols-[360px_minmax(0,1fr)]"
    >
      <!-- 店舗リスト -->
      <section class="rounded-xl border border-red-200 bg-white p-4 shadow-sm">
        <h2 class="text-lg font-black tracking-normal">店舗リスト</h2>

        <div class="mt-4 grid gap-3">
          <button
            v-for="store in adminStores"
            :key="store.store_id ?? store.id"
            type="button"
            class="rounded-lg border px-4 py-3 text-left text-sm font-bold transition hover:bg-red-50"
            :class="(selectedAdminStore.store_id ?? selectedAdminStore.id) === (store.store_id ?? store.id) ? 'border-red-400 bg-red-50 text-red-800' : 'border-red-100 bg-white text-neutral-800'"
            @click="emit('selectStore', store)"
          >
            {{ store.name }}
          </button>
        </div>
      </section>

      <div class="grid min-w-0 gap-6">
        <!-- 店舗プロフィール -->
        <section class="overflow-hidden rounded-xl border border-red-200 bg-white p-6 shadow-sm">
          <h2 class="text-lg font-black tracking-normal">店舗プロフィール</h2>
          <p class="mt-1 text-xs font-bold text-neutral-500">店舗画像はJPEG / PNG / WebP・5MBまで</p>

          <div class="relative mt-4 h-70 overflow-hidden rounded-lg bg-neutral-100">
            <FallbackImage
              class="h-full w-full object-cover"
              :src="selectedAdminStore.image_path"
              :alt="`${selectedAdminStore.name}の画像`"
            />
            <label
              class="absolute bottom-3 right-3 grid h-10 w-10 cursor-pointer place-items-center rounded-full bg-white text-red-700 shadow-md ring-1 ring-red-100 hover:bg-red-50"
              aria-label="店舗画像を編集"
            >
              <SquarePen class="h-5 w-5" />
              <input
                class="sr-only"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                @change="emit('uploadStoreImage', $event)"
              />
            </label>
          </div>

          <div class="mt-5 grid gap-4">
            <label class="grid gap-2 text-sm font-black text-[#5c4644]">
              店舗名
              <input
                v-model="storeProfileForm.name"
                class="h-14 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
              />
            </label>

            <label class="grid gap-2 text-sm font-black text-[#5c4644]">
              店舗説明
              <textarea
                v-model="storeProfileForm.description"
                class="min-h-28 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 py-3 text-sm font-bold leading-7 text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
              />
            </label>

            <label class="grid gap-2 text-sm font-black text-[#5c4644]">
              所在地
              <input
                v-model="storeProfileForm.address"
                class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
                placeholder="東京都渋谷区神南 1-2-3"
              />
            </label>

            <label class="grid gap-2 text-sm font-black text-[#5c4644]">
              電話番号
              <input
                v-model="storeProfileForm.phone"
                class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
                maxlength="20"
                placeholder="03-1234-5678"
                type="tel"
              />
            </label>

            <div class="grid gap-4 rounded-xl border border-red-100 bg-red-50/40 p-4">
              <p class="text-sm font-black text-[#5c4644]">営業時間</p>

              <label class="grid gap-2 text-sm font-black text-[#5c4644]">
                平日
                <textarea
                  v-model="storeProfileForm.weekday_hours"
                  class="min-h-20 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 py-3 text-sm font-bold leading-6 text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50"
                  placeholder="11:00-15:00&#10;17:00-22:00"
                />
              </label>

              <label class="grid gap-2 text-sm font-black text-[#5c4644]">
                土日祝
                <input
                  v-model="storeProfileForm.weekend_hours"
                  class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50"
                  placeholder="11:00-22:00"
                />
              </label>

              <div class="grid gap-2 text-sm font-black text-[#5c4644]">
                休日
                <select
                  class="h-12 w-full rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50"
                  @change="addHoliday(storeProfileForm, $event)"
                >
                  <option value="">休日を選択</option>
                  <option
                    v-for="holiday in holidayOptions"
                    :key="holiday"
                    :disabled="storeProfileForm.holidays.includes(holiday)"
                    :value="holiday"
                  >
                    {{ holiday }}
                  </option>
                </select>
                <div v-if="storeProfileForm.holidays.length" class="flex flex-wrap gap-2">
                  <span
                    v-for="holiday in storeProfileForm.holidays"
                    :key="holiday"
                    class="inline-flex items-center gap-2 rounded-full bg-red-700 px-3 py-1.5 text-xs font-black text-white"
                  >
                    {{ holiday }}
                    <button
                      type="button"
                      class="grid h-5 w-5 place-items-center rounded-full bg-white/20 text-white transition hover:bg-white/30"
                      :aria-label="`${holiday}を解除`"
                      @click="removeHoliday(storeProfileForm, holiday)"
                    >
                      <X class="h-3.5 w-3.5" />
                    </button>
                  </span>
                </div>
                <p v-else class="text-xs font-bold text-neutral-400">休日は未選択です。</p>
                <p class="text-xs font-bold text-neutral-500">選択すると下に追加されます。複数登録できます。</p>
              </div>
            </div>

            <div class="grid gap-2 text-sm font-black text-[#5c4644]">
              予算
              <div class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2">
                <input
                  v-model="storeProfileForm.budget_min"
                  class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
                  min="0"
                  type="number"
                  placeholder="1000"
                />
                <span class="text-neutral-500">〜</span>
                <input
                  v-model="storeProfileForm.budget_max"
                  class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
                  min="0"
                  type="number"
                  placeholder="2000"
                />
              </div>
            </div>

            <div class="flex justify-end">
              <button
                class="h-11 rounded-lg bg-red-700 px-5 text-sm font-black text-white hover:bg-red-800 disabled:opacity-60"
                type="button"
                :disabled="adminPageLoading"
                @click="emit('saveStoreProfile')"
              >
                保存する
              </button>
            </div>
          </div>
        </section>

        <!-- メニュー管理 -->
        <section class="min-w-0 overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-red-100 px-6 py-4">
            <h2 class="text-lg font-black tracking-normal">メニュー管理</h2>
            <button
              class="inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-black text-white hover:bg-red-800 disabled:opacity-60"
              type="button"
              :disabled="adminPageLoading"
              @click="emit('openProductModal')"
            >
              <Plus class="h-4 w-4" />
              追加
            </button>
          </div>

          <div class="grid gap-3 p-4">
            <div
              v-for="menu in menuRows"
              :key="menu.id"
              class="flex min-w-0 items-center gap-3 rounded-lg border border-red-100 bg-white p-3"
            >
              <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-neutral-100">
                <FallbackImage
                  class="h-full w-full object-cover"
                  :src="menu.imagePath"
                  :alt="`${menu.name}の画像`"
                />
              </div>

              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-black text-neutral-900">{{ menu.name }}</p>
                <p class="truncate text-xs font-bold text-neutral-500">{{ menu.description }}</p>
              </div>

              <p class="shrink-0 text-sm font-black text-neutral-900">
                {{ formatPrice(menu.price) }}
              </p>

              <div class="flex shrink-0 items-center gap-2">
                <button
                  class="grid h-9 w-9 place-items-center rounded-lg border border-red-100 text-red-700 hover:bg-red-50"
                  type="button"
                  aria-label="メニュー編集"
                  @click="emit('openProductModal', menu)"
                >
                  <SquarePen class="h-4 w-4" />
                </button>
                <button
                  class="grid h-9 w-9 place-items-center rounded-lg border border-red-100 text-red-700 hover:bg-red-50"
                  type="button"
                  aria-label="メニュー削除"
                  @click="emit('deleteMenu', menu)"
                >
                  <Trash2 class="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
        </section>
      </div>
    </section>

    <section
      v-else
      class="rounded-xl border border-red-200 bg-white p-8 text-center shadow-sm"
    >
      <p class="text-sm font-bold text-neutral-500">店舗情報がありません。</p>
    </section>
  </div>
</template>
