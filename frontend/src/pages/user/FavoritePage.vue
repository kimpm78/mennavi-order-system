<script setup lang="ts">
import { Heart, Star } from 'lucide-vue-next'
import FallbackImage from '@/components/common/FallbackImage.vue'

type StoreSummary = {
  id: number
  name: string
  description?: string
  budget?: string
  phone?: string | null
  rating: string
  reviews?: string
  imagePath?: string | null
  imageClass: string
}

defineProps<{
  stores: StoreSummary[]
}>()

const emit = defineEmits<{
  back: []
  openStore: [store: StoreSummary]
  toggleFavorite: [store: StoreSummary]
}>()

function getReviewCount(store: StoreSummary) {
  return Number.parseInt((store.reviews ?? '0').replace(/,/g, ''), 10) || 0
}
</script>

<template>
  <main class="mx-auto w-full max-w-7xl px-5 py-10 md:px-8">
    <div class="mb-8">
      <h1 class="flex items-center gap-3 text-3xl font-black">
        <Heart class="h-8 w-8 fill-current text-red-700" />
        お気に入り店舗
      </h1>

      <p class="mt-2 text-sm font-bold text-neutral-500">
        お気に入りに登録した店舗を確認できます。
      </p>
    </div>

    <div
      v-if="stores.length"
      class="grid gap-6 md:grid-cols-2 lg:grid-cols-3"
    >
      <article
        v-for="store in stores"
        :key="store.id"
        class="cursor-pointer overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-red-200 hover:shadow-md"
        role="button"
        tabindex="0"
        @click="emit('openStore', store)"
        @keydown.enter.prevent="emit('openStore', store)"
      >
        <div class="relative h-48 overflow-hidden bg-neutral-100">
          <FallbackImage
            :src="store.imagePath"
            :alt="`${store.name}の画像`"
          />

          <span
            class="absolute right-4 top-4 inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-sm font-black text-red-700 shadow-sm"
          >
            <Star class="h-4 w-4 fill-current" />
            {{ getReviewCount(store) > 0 ? store.rating : 'レビューなし' }}
          </span>
        </div>

        <div class="p-5">
          <h2 class="text-xl font-black">
            {{ store.name }}
          </h2>

          <p class="mt-4 min-h-20 text-sm font-medium leading-7 text-neutral-600">
            {{ store.description }}
          </p>

          <div class="mt-5 flex items-center justify-between border-t border-neutral-100 pt-4">
            <span class="text-sm font-black text-neutral-600">
              {{ store.budget }}
            </span>

            <button
              class="grid h-10 w-10 place-items-center rounded-full text-red-700 hover:bg-red-50"
              type="button"
              aria-label="お気に入りを解除"
              @click.stop="emit('toggleFavorite', store)"
            >
              <Heart class="h-5 w-5 fill-current" />
            </button>
          </div>
        </div>
      </article>
    </div>

    <div
      v-else
      class="rounded-lg border border-dashed border-neutral-300 bg-white px-6 py-16 text-center"
    >
      <Heart class="mx-auto h-10 w-10 text-neutral-300" />

      <p class="mt-4 text-lg font-black text-neutral-800">
        お気に入り店舗がありません
      </p>

      <p class="mt-2 text-sm font-bold text-neutral-500">
        店舗のハートを押すと、ここに表示されます。
      </p>

      <button
        class="mt-6 rounded-full bg-red-700 px-6 py-3 text-sm font-black text-white hover:bg-red-800"
        type="button"
        @click="emit('back')"
      >
        店舗を探す
      </button>
    </div>
  </main>
</template>
