<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Bell, LogOut, Menu, UserCircle } from 'lucide-vue-next'
import type { AdminPageKey } from '@/pages/admin/adminTypes'

type AdminUser = {
  name?: string | null
  email?: string | null
}

type AdminNotification = {
  id: string
  orderId?: number | string
  title: string
  message: string
  tone?: 'order' | 'success' | 'warning'
  time?: string
}

const props = defineProps<{
  admin: AdminUser
  title: string
  loading?: boolean
  notifications?: AdminNotification[]
}>()

const emit = defineEmits<{
  selectPage: [page: AdminPageKey]
  openNotification: [notification: AdminNotification]
  logout: []
}>()

const notificationPanelOpen = ref(false)
const notificationAreaRef = ref<HTMLElement | null>(null)

const notificationCount = computed(() => props.notifications?.length ?? 0)

const notificationCountLabel = computed(() => {
  return notificationCount.value > 99 ? '+99' : String(notificationCount.value)
})

const notificationToneClass = (tone: AdminNotification['tone']) => {
  if (tone === 'warning') {
    return 'bg-amber-50 text-amber-700'
  }

  if (tone === 'success') {
    return 'bg-green-50 text-green-700'
  }

  return 'bg-red-50 text-red-700'
}

const openOrdersFromNotification = (notification: AdminNotification) => {
  notificationPanelOpen.value = false
  emit('openNotification', notification)
}

const closeNotificationPanelOnOutsideClick = (event: PointerEvent) => {
  if (!notificationPanelOpen.value) {
    return
  }

  const target = event.target

  if (!(target instanceof Node)) {
    return
  }

  if (!notificationAreaRef.value?.contains(target)) {
    notificationPanelOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('pointerdown', closeNotificationPanelOnOutsideClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', closeNotificationPanelOnOutsideClick)
})
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
      <div ref="notificationAreaRef" class="relative">
        <button
          type="button"
          class="relative grid h-10 w-10 place-items-center rounded-full border border-red-100 text-red-700 hover:bg-red-50"
          aria-label="通知"
          :aria-expanded="notificationPanelOpen"
          @click="notificationPanelOpen = !notificationPanelOpen"
        >
          <Bell class="h-5 w-5" />
          <span
            v-if="notificationCount > 0"
            class="absolute -right-1 -top-1 grid min-w-5 place-items-center rounded-full bg-red-700 px-1.5 py-0.5 text-[10px] font-black leading-none text-white ring-2 ring-white"
          >
            {{ notificationCountLabel }}
          </span>
        </button>

        <div
          v-if="notificationPanelOpen"
          class="absolute right-0 top-12 z-40 w-[min(360px,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-red-100 bg-white shadow-2xl"
        >
          <div class="flex items-center justify-between border-b border-red-100 px-4 py-3">
            <div>
              <p class="text-sm font-black text-neutral-900">通知</p>
              <p class="mt-0.5 text-xs font-bold text-neutral-500">注文・遅延アラートを受信</p>
            </div>
            <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-black text-red-700">
              {{ notificationCountLabel }}件
            </span>
          </div>

          <div v-if="notificationCount > 0" class="max-h-96 overflow-y-auto p-2">
            <button
              v-for="notification in notifications"
              :key="notification.id"
              type="button"
              class="grid w-full gap-1 rounded-xl px-3 py-3 text-left hover:bg-red-50"
              @click="openOrdersFromNotification(notification)"
            >
              <div class="flex items-center justify-between gap-3">
                <span
                  class="rounded-full px-2.5 py-1 text-xs font-black"
                  :class="notificationToneClass(notification.tone)"
                >
                  {{ notification.title }}
                </span>
                <span v-if="notification.time" class="shrink-0 text-xs font-bold text-neutral-400">
                  {{ notification.time }}
                </span>
              </div>
              <p class="line-clamp-2 text-sm font-bold leading-6 text-neutral-700">
                {{ notification.message }}
              </p>
            </button>
          </div>

          <div v-else class="px-4 py-8 text-center">
            <p class="text-sm font-bold text-neutral-500">新しい通知はありません。</p>
          </div>
        </div>
      </div>

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
