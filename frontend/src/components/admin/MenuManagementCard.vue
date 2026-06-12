<script setup lang="ts">
import FallbackImage from '@/components/common/FallbackImage.vue'
import { Plus, SquarePen, Trash2 } from 'lucide-vue-next'

type MenuRow = {
  id: number | string
  name: string
  description?: string | null
  price: number
  imagePath?: string | null
  image_path?: string | null
  isDisplay?: boolean
  is_display?: boolean | string | number
}

defineProps<{
  menuRows: MenuRow[]
  loading?: boolean
  formatPrice: (price: number) => string
}>()

const emit = defineEmits<{
  openProductModal: [menu?: MenuRow]
  deleteMenu: [menu: MenuRow]
}>()

const menuImagePath = (menu: MenuRow) => {
  return menu.imagePath || menu.image_path || null
}

const isMenuVisible = (menu: MenuRow) => {
  const displayValue = menu.isDisplay ?? menu.is_display

  return displayValue === true || displayValue === '1' || displayValue === 1
}
</script>

<template>
  <section class="min-w-0 overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-red-100 px-6 py-4">
      <div>
        <h2 class="text-lg font-black tracking-normal">メニュー管理</h2>
        <p class="mt-1 text-xs font-bold text-neutral-500">
          {{ loading ? '読み込み中...' : `${menuRows.length}件` }}
        </p>
      </div>

      <button
        class="inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-black text-white hover:bg-red-800 disabled:opacity-60"
        type="button"
        :disabled="loading"
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
            :src="menuImagePath(menu)"
            :alt="`${menu.name}の画像`"
          />
        </div>

        <div class="min-w-0 flex-1">
          <div class="flex min-w-0 flex-wrap items-center gap-2">
            <p class="truncate text-sm font-black text-neutral-900">{{ menu.name }}</p>
            <span
              class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-black"
              :class="isMenuVisible(menu) ? 'bg-green-50 text-green-700' : 'bg-neutral-100 text-neutral-500'"
            >
              {{ isMenuVisible(menu) ? '表示中' : '非表示' }}
            </span>
          </div>
          <p class="mt-1 truncate text-xs font-bold text-neutral-500">{{ menu.description || '説明未登録' }}</p>
        </div>

        <p class="shrink-0 text-sm font-black text-neutral-900">
          {{ formatPrice(menu.price) }}
        </p>

        <div class="flex shrink-0 items-center gap-2">
          <button
            class="grid h-9 w-9 place-items-center rounded-lg border border-red-100 text-red-700 hover:bg-red-50 disabled:opacity-60"
            type="button"
            :disabled="loading"
            aria-label="メニュー編集"
            @click="emit('openProductModal', menu)"
          >
            <SquarePen class="h-4 w-4" />
          </button>
          <button
            class="grid h-9 w-9 place-items-center rounded-lg border border-red-100 text-red-700 hover:bg-red-50 disabled:opacity-60"
            type="button"
            :disabled="loading"
            aria-label="メニュー削除"
            @click="emit('deleteMenu', menu)"
          >
            <Trash2 class="h-4 w-4" />
          </button>
        </div>
      </div>

      <div
        v-if="menuRows.length === 0"
        class="rounded-xl border border-dashed border-red-200 p-8 text-center"
      >
        <p class="text-sm font-bold text-neutral-500">メニュー情報がありません。</p>
      </div>
    </div>
  </section>
</template>