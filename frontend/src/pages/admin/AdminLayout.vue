<script setup lang="ts">
// 管理画面共通レイアウト
// サイドバー・ヘッダー・メイン領域のみを管理します。

import AdminHeader from '../../components/admin/AdminHeader.vue'
import AdminSidebar from '../../components/admin/AdminSidebar.vue'
import type { AdminPageKey } from './adminTypes'

type AdminUser = {
  name?: string | null
  email?: string | null
}

type AdminNotification = {
  id: string
  orderId?: number | string
  title: string
  message: string
  tone: 'order' | 'success' | 'warning'
  time?: string
}

defineProps<{
  admin: AdminUser
  activePage: AdminPageKey
  title: string
  loading?: boolean
  notifications?: AdminNotification[]
}>()

const emit = defineEmits<{
  selectPage: [page: AdminPageKey]
  openNotification: [notification: AdminNotification]
  logout: []
}>()
</script>

<template>
  <div class="min-h-screen bg-[#fff8f5] text-neutral-900">
    <div class="grid min-h-screen lg:grid-cols-[280px_minmax(0,1fr)]">
      <AdminSidebar
        :active-page="activePage"
        @select-page="emit('selectPage', $event)"
      />

      <div class="min-w-0">
        <AdminHeader
          :admin="admin"
          :title="title"
          :loading="loading"
          :notifications="notifications"
          @select-page="emit('selectPage', $event)"
          @open-notification="emit('openNotification', $event)"
          @logout="emit('logout')"
        />

        <main class="min-w-0 px-4 py-6 lg:px-8 lg:py-8">
          <slot />
        </main>
      </div>
    </div>
  </div>
</template>
