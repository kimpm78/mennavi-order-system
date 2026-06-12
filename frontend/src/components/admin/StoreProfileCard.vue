<script setup lang="ts">
import FallbackImage from '@/components/common/FallbackImage.vue'
import { SquarePen } from 'lucide-vue-next'

type AdminStore = {
  store_id: number | string
  name: string
  image_path?: string | null
}

type StoreProfileForm = {
  name: string
  description: string
  address: string
  budget_min: number | string | null
  budget_max: number | string | null
}

defineProps<{
  selectedStore: AdminStore
  storeProfileForm: StoreProfileForm
  loading?: boolean
}>()

const emit = defineEmits<{
  uploadStoreImage: [event: Event]
  saveStoreProfile: []
}>()
</script>

<template>
  <section class="overflow-hidden rounded-xl border border-red-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-black tracking-normal">店舗プロフィール</h2>

    <div class="relative mt-4 h-40 overflow-hidden rounded-lg bg-neutral-100">
      <FallbackImage
        class="h-full w-full object-cover"
        :src="selectedStore.image_path"
        :alt="`${selectedStore.name}の画像`"
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
          :disabled="loading"
          @click="emit('saveStoreProfile')"
        >
          保存する
        </button>
      </div>
    </div>
  </section>
</template>
