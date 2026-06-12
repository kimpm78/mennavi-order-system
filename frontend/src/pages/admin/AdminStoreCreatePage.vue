<script setup lang="ts">
import { ImagePlus, Save, Store } from 'lucide-vue-next'

type StoreForm = {
  name: string
  description: string
  address: string
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
</script>

<template>
  <div class="grid gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm font-bold text-red-600">店舗追加</p>
        <h1 class="text-2xl font-black text-neutral-900">新しい店舗を登録</h1>
      </div>

      <button
        class="inline-flex h-11 items-center gap-2 rounded-lg bg-red-700 px-5 text-sm font-black text-white shadow-sm hover:bg-red-800 disabled:opacity-60"
        type="button"
        :disabled="adminPageLoading"
        @click="emit('createStore')"
      >
        <Save class="h-4 w-4" />
        登録する
      </button>
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
              店舗名・説明・所在地・予算を入力してください。
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
            <p class="mt-1 text-xs font-bold text-neutral-500">JPEG / PNG / WebP</p>
          </div>
        </div>

        <label
          class="mt-5 flex min-h-48 cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-red-200 bg-red-50 px-4 py-8 text-center hover:bg-red-100/60"
        >
          <ImagePlus class="h-10 w-10 text-red-700" />
          <div>
            <p class="text-sm font-black text-red-800">画像を選択</p>
            <p class="mt-1 text-xs font-bold text-red-600">
              店舗一覧・店舗プロフィールで表示されます。
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
            <p class="mt-1 truncate">
              予算：{{ storeForm.budget_min || '未入力' }} 〜 {{ storeForm.budget_max || '未入力' }}
            </p>
          </div>
        </div>
      </aside>
    </section>
  </div>
</template>
