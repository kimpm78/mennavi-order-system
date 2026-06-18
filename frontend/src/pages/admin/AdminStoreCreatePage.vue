<script setup lang="ts">
import FallbackImage from '@/components/common/FallbackImage.vue'
import { ImagePlus, Save, Store, X } from 'lucide-vue-next'

const holidayOptions = ['月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日', '日曜日', '祝日']

type StoreForm = {
  name: string
  description: string
  address: string
  phone: string
  weekday_hours: string
  weekend_hours: string
  holidays: string[]
  budget_min: number | string | null
  budget_max: number | string | null
  image?: File | null
  image_path?: string | null
}

defineProps<{
  storeForm: StoreForm
  adminPageLoading?: boolean
}>()

const emit = defineEmits<{
  createStore: []
  uploadStoreImage: [event: Event]
}>()

const removeHoliday = (form: StoreForm, holiday: string) => {
  form.holidays = form.holidays.filter((selectedHoliday) => selectedHoliday !== holiday)
}

const addHoliday = (form: StoreForm, event: Event) => {
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
        <p class="text-sm font-bold text-red-600">店舗追加</p>
        <h1 class="text-2xl font-black text-neutral-900">新しい店舗を登録</h1>
      </div>

    </div>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
      <!-- 店舗入力フォーム -->
      <section class="min-w-0 rounded-xl border border-red-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-3 border-b border-red-100 pb-4">
          <div class="grid h-11 w-11 place-items-center rounded-xl bg-red-50 text-red-700">
            <Store class="h-5 w-5" />
          </div>
          <div>
            <h2 class="text-lg font-black tracking-normal">店舗情報</h2>
            <p class="mt-1 text-xs font-bold text-neutral-500">
              店舗名・説明・所在地・営業時間・予算を入力してください。
            </p>
          </div>
        </div>

        <div class="mt-6 grid gap-5">
          <label class="grid gap-2 text-sm font-black text-[#5c4644]">
            店舗名
              <input
                v-model="storeForm.name"
                class="h-14 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
              placeholder="例：麺ナビ 渋谷本店"
            />
          </label>

          <div class="grid gap-4 rounded-xl border border-red-100 bg-red-50/40 p-4">
            <p class="text-sm font-black text-[#5c4644]">営業時間</p>

            <label class="grid gap-2 text-sm font-black text-[#5c4644]">
              平日
              <textarea
                v-model="storeForm.weekday_hours"
                class="min-h-20 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 py-3 text-sm font-bold leading-6 text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50"
                placeholder="11:00-15:00&#10;17:00-22:00"
              />
            </label>

            <label class="grid gap-2 text-sm font-black text-[#5c4644]">
              土日祝
              <input
                v-model="storeForm.weekend_hours"
                class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50"
                placeholder="11:00-22:00"
              />
            </label>

            <div class="grid gap-2 text-sm font-black text-[#5c4644]">
              休日
              <select
                class="h-12 w-full rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50"
                @change="addHoliday(storeForm, $event)"
              >
                <option value="">休日を選択</option>
                <option
                  v-for="holiday in holidayOptions"
                  :key="holiday"
                  :disabled="storeForm.holidays.includes(holiday)"
                  :value="holiday"
                >
                  {{ holiday }}
                </option>
              </select>
              <div v-if="storeForm.holidays.length" class="flex flex-wrap gap-2">
                <span
                  v-for="holiday in storeForm.holidays"
                  :key="holiday"
                  class="inline-flex items-center gap-2 rounded-full bg-red-700 px-3 py-1.5 text-xs font-black text-white"
                >
                  {{ holiday }}
                  <button
                    type="button"
                    class="grid h-5 w-5 place-items-center rounded-full bg-white/20 text-white transition hover:bg-white/30"
                    :aria-label="`${holiday}を解除`"
                    @click="removeHoliday(storeForm, holiday)"
                  >
                    <X class="h-3.5 w-3.5" />
                  </button>
                </span>
              </div>
              <p v-else class="text-xs font-bold text-neutral-400">休日は未選択です。</p>
              <p class="text-xs font-bold text-neutral-500">選択すると下に追加されます。複数登録できます。</p>
            </div>
          </div>

          <label class="grid gap-2 text-sm font-black text-[#5c4644]">
            店舗説明
              <textarea
                v-model="storeForm.description"
                class="min-h-32 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 py-3 text-sm font-bold leading-7 text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
              placeholder="例：濃厚醤油ラーメンを中心に提供する店舗です。"
            />
          </label>

          <label class="grid gap-2 text-sm font-black text-[#5c4644]">
            所在地
              <input
                v-model="storeForm.address"
                class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
              placeholder="東京都渋谷区神南 1-2-3"
            />
          </label>

          <label class="grid gap-2 text-sm font-black text-[#5c4644]">
            電話番号
              <input
                v-model="storeForm.phone"
                class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
              maxlength="20"
              placeholder="03-1234-5678"
              type="tel"
            />
          </label>

          <div class="grid gap-2 text-sm font-black text-[#5c4644]">
            予算
            <div class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2">
              <input
                v-model="storeForm.budget_min"
                class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
                min="0"
                type="number"
                placeholder="1000"
              />
              <span class="text-neutral-500">〜</span>
              <input
                v-model="storeForm.budget_max"
                class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
                min="0"
                type="number"
                placeholder="2000"
              />
            </div>
          </div>

          <div class="flex justify-end pt-2">
            <button
              class="inline-flex h-11 items-center gap-2 rounded-lg bg-red-700 px-5 text-sm font-black text-white hover:bg-red-800 disabled:opacity-60"
              type="button"
              :disabled="adminPageLoading"
              @click="emit('createStore')"
            >
              <Save class="h-4 w-4" />
              登録する
            </button>
          </div>
        </div>
      </section>

      <!-- 画像登録 -->
      <aside class="rounded-xl border border-red-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="grid h-11 w-11 place-items-center rounded-xl bg-red-50 text-red-700">
            <ImagePlus class="h-5 w-5" />
          </div>
          <div>
            <h2 class="text-lg font-black tracking-normal">店舗画像</h2>
            <p class="mt-1 text-xs font-bold text-neutral-500">JPEG / PNG / WebP・5MBまで</p>
          </div>
        </div>

        <div
          v-if="storeForm.image_path"
          class="mt-5 h-44 overflow-hidden rounded-xl border border-red-100 bg-neutral-100"
        >
          <FallbackImage
            class="h-full w-full object-cover"
            :src="storeForm.image_path"
            :alt="`${storeForm.name || '新規店舗'}の画像プレビュー`"
          />
        </div>

        <label
          class="mt-5 flex min-h-40 cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-red-200 bg-red-50 px-4 py-8 text-center hover:bg-red-100/60"
        >
          <ImagePlus class="h-10 w-10 text-red-700" />
          <div>
            <p class="text-sm font-black text-red-800">画像を選択</p>
            <p class="mt-1 text-xs font-bold text-red-600">
              店舗一覧・店舗プロフィールで表示されます。5MBまで。
            </p>
          </div>
          <input
            class="sr-only"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            @change="emit('uploadStoreImage', $event)"
          />
        </label>

        <div class="mt-6 grid gap-3 text-sm font-bold text-neutral-600">
          <div class="rounded-lg border border-red-100 px-4 py-3">
            <p class="font-black text-neutral-900">入力確認</p>
            <p class="mt-2 truncate">店舗名：{{ storeForm.name || '未入力' }}</p>
            <p class="mt-1 truncate">所在地：{{ storeForm.address || '未入力' }}</p>
            <p class="mt-1 truncate">電話番号：{{ storeForm.phone || '未入力' }}</p>
            <p class="mt-1 truncate">休日：{{ storeForm.holidays.length ? storeForm.holidays.join('、') : '未入力' }}</p>
            <p class="mt-1 truncate">
              予算：{{ storeForm.budget_min || '未入力' }} 〜 {{ storeForm.budget_max || '未入力' }}
            </p>
          </div>
        </div>
      </aside>
    </section>
  </div>
</template>
