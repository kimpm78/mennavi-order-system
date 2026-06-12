<script setup lang="ts">
type AdminStore = {
  store_id: number | string
  name: string
  description?: string | null
  address?: string | null
}

defineProps<{
  stores: AdminStore[]
  selectedStore: AdminStore | null
  loading?: boolean
}>()

const emit = defineEmits<{
  selectStore: [store: AdminStore]
}>()
</script>

<template>
  <section class="rounded-xl border border-red-200 bg-white p-4 shadow-sm">
    <div class="flex items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-black tracking-normal">店舗リスト</h2>
        <p class="mt-1 text-xs font-bold text-neutral-500">
          {{ loading ? '読み込み中...' : `${stores.length}件` }}
        </p>
      </div>
    </div>

    <div class="mt-4 grid gap-3">
      <button
        v-for="store in stores"
        :key="store.store_id"
        type="button"
        class="min-w-0 rounded-lg border px-4 py-3 text-left text-sm font-bold transition disabled:opacity-60"
        :class="selectedStore?.store_id === store.store_id ? 'border-red-400 bg-red-50 text-red-800' : 'border-red-100 bg-white text-neutral-800 hover:bg-red-50 hover:text-red-800'"
        :disabled="loading"
        @click="emit('selectStore', store)"
      >
        <span class="block truncate">{{ store.name }}</span>
        <span
          v-if="store.address"
          class="mt-1 block truncate text-xs font-bold text-neutral-500"
        >
          {{ store.address }}
        </span>
      </button>

      <div
        v-if="stores.length === 0"
        class="rounded-lg border border-dashed border-red-200 px-4 py-6 text-center"
      >
        <p class="text-sm font-bold text-neutral-500">店舗情報がありません。</p>
      </div>
    </div>
  </section>
</template>