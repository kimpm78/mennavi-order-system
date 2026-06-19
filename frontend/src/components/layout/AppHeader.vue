<script setup lang="ts">
import { Bell, CircleUserRound, Search, ShoppingCart } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted, ref } from 'vue'

type UserOrderNotification = {
  id: string
  title: string
  message: string
  tone: 'order' | 'cooking' | 'delivery'
  time?: string
}

const props = defineProps<{
  isAuthenticated?: boolean
  isPlusMember?: boolean
  cartCount?: number
  orderNotifications?: UserOrderNotification[]
  searchQuery?: string
  activeNav?: 'stores' | 'favorites' | ''
}>()

const emit = defineEmits<{
  logout: []
  openCart: []
  brandClick: []
  storesClick: []
  favoritesClick: []
  openOrderNotification: [notification: UserOrderNotification]
  openAccountSection: [section: 'profile' | 'orders' | 'delivery']
  'update:searchQuery': [value: string]
}>()

const isProfileMenuOpen = ref(false)
const isNotificationMenuOpen = ref(false)
const appBasePath = import.meta.env.BASE_URL.replace(/\/$/, '')
const loginHref = `${appBasePath}/login`

onMounted(() => {
  window.addEventListener('click', closeFloatingMenus)
  window.addEventListener('keydown', closeFloatingMenusWithEscape)
})

onBeforeUnmount(() => {
  window.removeEventListener('click', closeFloatingMenus)
  window.removeEventListener('keydown', closeFloatingMenusWithEscape)
})

function toggleProfileMenu(event: MouseEvent) {
  event.stopPropagation()
  isProfileMenuOpen.value = !isProfileMenuOpen.value
  isNotificationMenuOpen.value = false
}

function toggleNotificationMenu(event: MouseEvent) {
  event.stopPropagation()
  isNotificationMenuOpen.value = !isNotificationMenuOpen.value
  isProfileMenuOpen.value = false
}

function closeFloatingMenus() {
  isProfileMenuOpen.value = false
  isNotificationMenuOpen.value = false
}

function closeFloatingMenusWithEscape(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    closeFloatingMenus()
  }
}

function handleLogout() {
  closeFloatingMenus()
  emit('logout')
}

function handleBrandClick(event: MouseEvent) {
  if (!props.isAuthenticated) {
    return
  }

  event.preventDefault()
  emit('brandClick')
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function handleStoresClick(event: MouseEvent) {
  event.preventDefault()
  emit('storesClick')
}

function handleFavoritesClick(event: MouseEvent) {
  event.preventDefault()
  emit('favoritesClick')
}

function updateSearch(event: Event) {
  emit('update:searchQuery', (event.target as HTMLInputElement).value)
}

function openCart() {
  emit('openCart')
}

function openAccountSection(section: 'profile' | 'orders' | 'delivery') {
  closeFloatingMenus()
  emit('openAccountSection', section)
}

function openOrderNotification(notification: UserOrderNotification) {
  closeFloatingMenus()
  emit('openOrderNotification', notification)
}

function notificationCountLabel() {
  const count = props.orderNotifications?.length ?? 0
  return count > 99 ? '+99' : String(count)
}

function notificationToneClass(tone: UserOrderNotification['tone']) {
  if (tone === 'cooking') {
    return 'bg-amber-50 text-amber-700'
  }

  if (tone === 'delivery') {
    return 'bg-emerald-50 text-emerald-700'
  }

  return 'bg-red-50 text-red-700'
}
</script>

<template>
  <header class="sticky top-0 z-20 border-b border-neutral-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex min-h-16 w-full max-w-7xl items-center gap-5 px-5 md:px-8">
      <a
        class="shrink-0 text-2xl font-black tracking-normal text-red-700"
        :href="loginHref"
        @click="handleBrandClick"
      >
        麺ナビ
      </a>

      <nav class="hidden h-16 items-center md:flex">
        <a
          :class="[
            'flex h-full items-center border-b-2 px-6 text-sm font-black transition',
            props.activeNav === 'stores'
              ? 'border-red-700 text-red-700'
              : 'border-transparent text-neutral-600 hover:border-red-200 hover:text-red-700',
          ]"
          href="#stores"
          @click="handleStoresClick"
        >
          店舗一覧
        </a>
        <a
          :class="[
            'flex h-full items-center border-b-2 px-6 text-sm font-black transition',
            props.activeNav === 'favorites'
              ? 'border-red-700 text-red-700'
              : 'border-transparent text-neutral-600 hover:border-red-200 hover:text-red-700',
          ]"
          href="#favorites"
          @click="handleFavoritesClick"
        >
          お気に入り
        </a>
      </nav>

      <form class="mx-auto hidden w-full max-w-md md:block" role="search" @submit.prevent>
        <label class="relative block">
          <Search class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-neutral-500" aria-hidden="true" />
          <input
            class="h-12 w-full rounded-full border-0 bg-neutral-100 pl-14 pr-5 text-sm text-neutral-800 outline-none ring-1 ring-transparent transition focus:bg-white focus:ring-red-200"
            type="search"
            placeholder="店舗名・料理名を検索"
            :value="props.searchQuery"
            @input="updateSearch"
          />
        </label>
      </form>

      <div class="ml-auto flex shrink-0 items-center gap-3">
        <div v-if="isAuthenticated" class="relative">
          <button
            class="relative grid h-11 w-11 place-items-center rounded-full text-red-700 transition hover:bg-red-50"
            type="button"
            aria-label="注文通知"
            :aria-expanded="isNotificationMenuOpen"
            @click="toggleNotificationMenu"
          >
            <Bell class="h-6 w-6" :stroke-width="2.3" />
            <span
              v-if="orderNotifications?.length"
              class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-red-700 px-1 text-xs font-black text-white"
            >
              {{ notificationCountLabel() }}
            </span>
          </button>

          <div
            v-if="isNotificationMenuOpen"
            class="fixed left-4 right-4 top-20 z-50 overflow-hidden rounded-lg border border-neutral-100 bg-white text-sm font-bold text-neutral-700 shadow-lg md:absolute md:left-auto md:right-0 md:top-full md:mt-3 md:w-80"
            role="menu"
            @click.stop
          >
            <div class="border-b border-neutral-100 px-4 py-3">
              <p class="font-black text-neutral-900">注文通知</p>
              <p class="mt-1 text-xs font-bold text-neutral-500">調理開始・配送中の注文を確認できます。</p>
            </div>

            <div v-if="orderNotifications?.length" class="max-h-80 overflow-y-auto">
              <button
                v-for="notification in orderNotifications"
                :key="notification.id"
                class="block w-full border-b border-neutral-100 px-4 py-3 text-left last:border-b-0 hover:bg-red-50"
                type="button"
                role="menuitem"
                @click="openOrderNotification(notification)"
              >
                <div class="flex items-start justify-between gap-3">
                  <span
                    class="rounded-full px-2.5 py-1 text-xs font-black"
                    :class="notificationToneClass(notification.tone)"
                  >
                    {{ notification.title }}
                  </span>
                  <span v-if="notification.time" class="shrink-0 text-xs font-black text-neutral-400">
                    {{ notification.time }}
                  </span>
                </div>
                <p class="mt-2 leading-6 text-neutral-700">{{ notification.message }}</p>
              </button>
            </div>

            <button
              v-else
              class="block w-full px-4 py-5 text-left text-sm font-bold text-neutral-500 hover:bg-neutral-50"
              type="button"
              role="menuitem"
              @click="openAccountSection('orders')"
            >
              現在進行中の注文通知はありません。
            </button>
          </div>
        </div>

        <button
          class="relative grid h-11 w-11 place-items-center rounded-full text-red-700 transition hover:bg-red-50"
          type="button"
          aria-label="カート"
          @click="openCart"
        >
          <ShoppingCart class="h-6 w-6" :stroke-width="2.4" />
          <span
            v-if="cartCount"
            class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-red-700 px-1 text-xs font-black text-white"
          >
            {{ cartCount }}
          </span>
        </button>

        <div v-if="isAuthenticated" class="relative">
          <div class="relative">
            <button
              class="relative grid h-11 w-11 place-items-center rounded-full text-neutral-700 transition hover:bg-neutral-100"
              type="button"
              aria-label="プロフィール"
              :aria-expanded="isProfileMenuOpen"
              @click="toggleProfileMenu"
            >
              <CircleUserRound class="h-7 w-7" :stroke-width="2.1" />
              <span
                v-if="props.isPlusMember"
                class="absolute bottom-0 right-0 text-sm font-black leading-none text-red-700"
                aria-label="Plus会員"
              >
                +
              </span>
            </button>
          </div>

          <div
            v-if="isProfileMenuOpen"
            class="absolute right-0 top-full z-50 mt-3 w-40 border border-neutral-100 bg-white py-4 text-sm font-bold text-neutral-600 shadow-lg"
            role="menu"
            @click.stop
          >
            <button
              class="block w-full px-8 py-3 text-left font-bold hover:bg-red-50 hover:text-red-700"
              type="button"
              role="menuitem"
              @click="openAccountSection('profile')"
            >
              会員情報
            </button>
            <button
              class="block w-full px-8 py-3 text-left font-bold hover:bg-red-50 hover:text-red-700"
              type="button"
              role="menuitem"
              @click="openAccountSection('orders')"
            >
              注文履歴
            </button>
            <button
              class="block w-full px-8 py-3 text-left font-bold hover:bg-red-50 hover:text-red-700"
              type="button"
              role="menuitem"
              @click="openAccountSection('delivery')"
            >
              配送先情報
            </button>
            <button
              class="block w-full px-8 py-3 text-left font-bold hover:bg-red-50 hover:text-red-700"
              type="button"
              role="menuitem"
              @click="handleLogout"
            >
              ログアウト
            </button>
          </div>
        </div>
        <a
          v-else
          class="rounded-full bg-red-700 px-5 py-2 text-sm font-black text-white transition hover:bg-red-800"
          :href="loginHref"
        >
          ログイン
        </a>
      </div>
    </div>

    <div class="border-t border-neutral-100 px-5 py-3 md:hidden">
      <label class="relative block">
        <Search class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-neutral-500" aria-hidden="true" />
        <input
          class="h-11 w-full rounded-full border-0 bg-neutral-100 pl-14 pr-4 text-sm outline-none"
          type="search"
          placeholder="店舗名・料理名を検索"
          :value="props.searchQuery"
          @input="updateSearch"
        />
      </label>
    </div>
  </header>
</template>
