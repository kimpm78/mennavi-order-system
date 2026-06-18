<script setup lang="ts">
import { CircleUserRound, Search, ShoppingCart } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps<{
  isAuthenticated?: boolean
  isPlusMember?: boolean
  cartCount?: number
  searchQuery?: string
  activeNav?: 'stores' | 'favorites' | ''
}>()

const emit = defineEmits<{
  logout: []
  openCart: []
  brandClick: []
  storesClick: []
  favoritesClick: []
  openAccountSection: [section: 'profile' | 'orders' | 'delivery']
  'update:searchQuery': [value: string]
}>()

const isProfileMenuOpen = ref(false)

onMounted(() => {
  window.addEventListener('click', closeProfileMenu)
  window.addEventListener('keydown', closeProfileMenuWithEscape)
})

onBeforeUnmount(() => {
  window.removeEventListener('click', closeProfileMenu)
  window.removeEventListener('keydown', closeProfileMenuWithEscape)
})

function toggleProfileMenu(event: MouseEvent) {
  event.stopPropagation()
  isProfileMenuOpen.value = !isProfileMenuOpen.value
}

function closeProfileMenu() {
  isProfileMenuOpen.value = false
}

function closeProfileMenuWithEscape(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    closeProfileMenu()
  }
}

function handleLogout() {
  closeProfileMenu()
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
  closeProfileMenu()
  emit('openAccountSection', section)
}
</script>

<template>
  <header class="sticky top-0 z-20 border-b border-neutral-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex min-h-16 w-full max-w-7xl items-center gap-5 px-5 md:px-8">
      <a
        class="shrink-0 text-2xl font-black tracking-normal text-red-700"
        href="/login"
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
            class="absolute right-0 top-full mt-3 w-40 border border-neutral-100 bg-white py-4 text-sm font-bold text-neutral-600 shadow-lg"
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
          href="/login"
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
