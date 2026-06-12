<script setup lang="ts">

import {
  BarChart3,
  Bell,
  LayoutDashboard,
  LogOut,
  Menu,
  Plus,
  Settings,
  ShoppingBag,
  Store,
  UserCircle,
} from 'lucide-vue-next'

type AdminUser = {
  name?: string | null
  email?: string | null
}

type AdminPageKey = 'dashboard' | 'orders' | 'menus' | 'storeCreate' | 'sales' | 'settings'

type AdminNavItem = {
  key: AdminPageKey
  label: string
  icon: typeof LayoutDashboard
}

defineProps<{
  admin: AdminUser
  activePage: AdminPageKey
  title: string
  loading?: boolean
}>()

const emit = defineEmits<{
  selectPage: [page: AdminPageKey]
  logout: []
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
  <div class="min-h-screen bg-[#fff8f5] text-neutral-900">
    <div class="grid min-h-screen lg:grid-cols-[280px_minmax(0,1fr)]">
      <!-- 管理画面サイドバー -->
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

      <div class="min-w-0">
        <!-- 管理画面ヘッダー -->
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-red-100 bg-white/95 px-4 backdrop-blur lg:px-8">
          <div class="flex min-w-0 items-center gap-3">
            <Menu class="h-5 w-5 text-red-700 lg:hidden" />
            <div class="min-w-0">
              <p class="truncate text-lg font-black text-neutral-900">{{ title }}</p>
              <p class="text-xs font-bold text-neutral-500">管理者用ページ</p>
            </div>
          </div>

          <div class="flex shrink-0 items-center gap-3">
            <button
              type="button"
              class="grid h-10 w-10 place-items-center rounded-full border border-red-100 text-red-700 hover:bg-red-50"
              aria-label="通知"
            >
              <Bell class="h-5 w-5" />
            </button>

            <div class="hidden items-center gap-3 rounded-full border border-red-100 bg-red-50 py-1 pl-1 pr-4 sm:flex">
              <div class="grid h-9 w-9 place-items-center rounded-full bg-white text-red-700">
                <UserCircle class="h-6 w-6" />
              </div>
              <div>
                <p class="text-sm font-black leading-none text-neutral-900">
                  {{ admin.name || '管理者' }}
                </p>
                <p class="mt-1 text-xs font-bold text-neutral-500">
                  {{ admin.email || 'admin' }}
                </p>
              </div>
            </div>

            <button
              type="button"
              class="inline-flex h-10 items-center gap-2 rounded-lg border border-red-100 px-3 text-sm font-black text-red-700 hover:bg-red-50 disabled:opacity-60"
              :disabled="loading"
              @click="emit('logout')"
            >
              <LogOut class="h-4 w-4" />
              <span class="hidden sm:inline">ログアウト</span>
            </button>
          </div>
        </header>

        <main class="min-w-0 px-4 py-6 lg:px-8 lg:py-8">
          <slot />
        </main>
      </div>
    </div>
  </div>
</template>