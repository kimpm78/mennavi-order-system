<script setup lang="ts">
import { Bell, LogOut, Menu, UserCircle } from 'lucide-vue-next'

type AdminUser = {
  name?: string | null
  email?: string | null
}

defineProps<{
  admin: AdminUser
  title: string
  loading?: boolean
}>()

const emit = defineEmits<{
  logout: []
}>()
</script>

<template>
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
</template>