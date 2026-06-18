<script setup lang="ts">
import type { Component } from 'vue'
import {
  BarChart3,
  Mail,
  LayoutDashboard,
  Plus,
  Settings,
  ShoppingBag,
  Store,
} from 'lucide-vue-next'
import type { AdminPageKey } from '@/pages/admin/adminTypes'

type AdminNavItem = {
  key: AdminPageKey
  label: string
  icon: Component
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
  { key: 'contactMessages', label: 'お問い合わせ', icon: Mail },
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

    <nav aria-label="管理画面メニュー" class="mt-8 grid gap-2">
      <button
        v-for="item in adminNavItems"
        :key="item.key"
        type="button"
        class="flex h-12 items-center gap-3 rounded-xl px-4 text-sm font-black transition"
        :class="activePage === item.key ? 'bg-red-700 text-white shadow-sm' : 'text-neutral-700 hover:bg-red-50 hover:text-red-800'"
        :aria-current="activePage === item.key ? 'page' : undefined"
        @click="emit('selectPage', item.key)"
      >
        <component :is="item.icon" class="h-5 w-5" />
        {{ item.label }}
      </button>
    </nav>
  </aside>
</template>
