<script setup lang="ts">
import {
  BarChart3,
  LayoutDashboard,
  Plus,
  Settings,
  ShoppingBag,
  Store,
} from 'lucide-vue-next'

type AdminPageKey = 'dashboard' | 'orders' | 'menus' | 'storeCreate' | 'sales' | 'settings'

type AdminNavItem = {
  key: AdminPageKey
  label: string
  icon: typeof LayoutDashboard
}

defineProps<{
  activePage: AdminPageKey
}>()

const emit = defineEmits<{
  selectPage: [page: AdminPageKey]
}>()

const adminNavItems: AdminNavItem[] = [
  { key: 'dashboard', label: 'ダッシュボード', icon: LayoutDashboard },
  { key: 'orders', label: '注文管理', icon: ShoppingBag },
  { key: 'menus', label: '店舗・メニュー管理', icon: Store },
  { key: 'storeCreate', label: '店舗追加', icon: Plus },
  { key: 'sales', label: '売上分析', icon: BarChart3 },
  { key: 'settings', label: '設定', icon: Settings },
]
</script>

<template>
  <aside class="flex flex-col border-r border-red-100 bg-white px-4 py-8 lg:min-h-screen">
    <div class="flex items-center gap-3 px-3">
      <div class="grid h-11 w-11 place-items-center rounded-xl bg-red-700 text-white shadow-sm">
        <Store class="h-6 w-6" />
      </div>
      <div>
        <p class="text-lg font-black leading-none text-red-800">麺ナビ</p>
        <p class="mt-1 text-xs font-bold text-neutral-500">管理画面</p>
      </div>
    </div>

    <nav class="mt-8 grid gap-2">
      <button
        v-for="item in adminNavItems"
        :key="item.key"
        type="button"
        class="flex h-12 items-center gap-3 rounded-xl px-4 text-sm font-black transition"
        :class="activePage === item.key ? 'bg-red-700 text-white shadow-sm' : 'text-neutral-700 hover:bg-red-50 hover:text-red-800'"
        @click="emit('selectPage', item.key)"
      >
        <component :is="item.icon" class="h-5 w-5" />
        {{ item.label }}
      </button>
    </nav>

    <div class="mt-auto grid gap-3 rounded-xl bg-red-50 p-4 text-sm">
      <p class="font-black text-red-900">店舗管理</p>
      <p class="text-xs font-bold leading-5 text-red-700">
        店舗情報・メニュー・注文状況を管理できます。
      </p>
      <button
        type="button"
        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-black text-white hover:bg-red-800"
        @click="emit('selectPage', 'storeCreate')"
      >
        <Plus class="h-4 w-4" />
        店舗追加
      </button>
    </div>
  </aside>
</template>
