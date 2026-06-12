<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { ChevronLeft, ChevronRight, Heart, MapPinned, Star, Store, Trash2, X } from 'lucide-vue-next'
import FallbackImage from '../../components/common/FallbackImage.vue'
import AppFooter from '../../components/layout/AppFooter.vue'
import AppHeader from '../../components/layout/AppHeader.vue'
import { apiRequest, authHeaders } from '../../lib/api'
import { getCustomerToken } from '../../lib/authStorage'
import type { User } from '../../types/auth'
import CheckoutPage from './CheckoutPage.vue'
import DeliveryInfoPage from './DeliveryInfoPage.vue'
import OrderCompletePage from './OrderCompletePage.vue'
import OrderHistoryPage from './OrderHistoryPage.vue'
import StoreDetailPage from './StoreDetailPage.vue'

defineProps<{
  user: User
  loading?: boolean
}>()

const emit = defineEmits<{
  logout: []
  updateUser: [user: User]
}>()

const activeCategory = ref('すべて')
const searchQuery = ref('')
const selectedStore = ref<StoreSummary | null>(null)
const currentView = ref<'home' | 'delivery' | 'orderHistory' | 'checkout' | 'orderComplete'>('home')
const accountInitialSection = ref<'profile' | 'orders' | 'delivery'>('delivery')
const cartItems = ref<CartItem[]>([])
const cartExpiresAt = ref<string | null>(null)
const currentTime = ref(Date.now())
const isCartOpen = ref(false)
const pendingDifferentStoreItem = ref<CartItem | null>(null)
const toastMessage = ref('')
const nearbyScroller = ref<HTMLElement | null>(null)
let cartResetTimer: number | undefined
let cartClockTimer: number | undefined
let toastTimer: number | undefined

type StoreSummary = {
  id: number
  name: string
  categories?: string[]
  tags?: string[]
  description?: string
  budget?: string
  rating: string
  reviews?: string
  imagePath?: string | null
  imageClass: string
  products?: MenuItem[]
}

type MenuItem = {
  id: number
  name: string
  category: string
  price: number
  status?: string
  description?: string | null
  imagePath?: string | null
  imageClass?: string
  toppings?: string[]
}

type CartItem = {
  storeName: string
  menuItemId: number
  name: string
  price: number
  quantity: number
}

type CartResponse = {
  store_name: string | null
  expires_at: string | null
  items: CartItem[]
  total: number
}

const stores = ref<StoreSummary[]>([])
const categories = [
  'すべて',
  '人気店',
  '高評価',
  '醤油が人気',
  '味噌が人気',
  '豚骨が人気',
  'つけ麺が人気',
]

const normalizedSearchQuery = computed(() => searchQuery.value.trim().toLowerCase())
const filteredRecommendedStores = computed(() =>
  stores.value.filter((store) => matchesCategory(store) && matchesSearch(store, normalizedSearchQuery.value)),
)
const filteredNearbyStores = computed(() =>
  stores.value.filter((store) => matchesCategory(store) && matchesSearch(store, normalizedSearchQuery.value)),
)
const totalSearchResults = computed(
  () => filteredRecommendedStores.value.length + filteredNearbyStores.value.length,
)
const isSearching = computed(() => normalizedSearchQuery.value.length > 0)
const isFiltering = computed(() => activeCategory.value !== 'すべて')
const cartItemCount = computed(() =>
  cartItems.value.reduce((total, item) => total + item.quantity, 0),
)
const cartTotal = computed(() =>
  cartItems.value.reduce((total, item) => total + item.price * item.quantity, 0),
)
const cartRemainingMs = computed(() => {
  if (!cartExpiresAt.value) {
    return 0
  }

  return Math.max(new Date(cartExpiresAt.value).getTime() - currentTime.value, 0)
})
const cartRemainingText = computed(() => {
  if (!cartRemainingMs.value) {
    return '残り0分'
  }

  const totalSeconds = Math.ceil(cartRemainingMs.value / 1000)
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = totalSeconds % 60

  return `残り${minutes}:${seconds.toString().padStart(2, '0')}`
})

onMounted(() => {
  cartClockTimer = window.setInterval(() => {
    currentTime.value = Date.now()
  }, 1000)
  loadCart()
  loadStores()
})

onBeforeUnmount(() => {
  if (cartResetTimer) {
    window.clearTimeout(cartResetTimer)
  }
  if (cartClockTimer) {
    window.clearInterval(cartClockTimer)
  }
  if (toastTimer) {
    window.clearTimeout(toastTimer)
  }
})

function matchesSearch(
  store: StoreSummary,
  keyword: string,
) {
  if (!keyword) {
    return true
  }

  return [store.name, store.description, ...(store.tags ?? [])].some((value) =>
    value?.toLowerCase().includes(keyword),
  ) || (store.products ?? []).some((product) =>
    [product.name, product.category, product.description].some((value) =>
      value?.toLowerCase().includes(keyword),
    ),
  )
}

function matchesCategory(store: StoreSummary) {
  if (activeCategory.value === 'すべて') {
    return true
  }

  if (activeCategory.value === '人気店') {
    return getReviewCount(store) >= 1000 || getStoreSearchText(store).includes('人気')
  }

  if (activeCategory.value === '高評価') {
    return Number.parseFloat(store.rating) >= 4.7 || getStoreSearchText(store).includes('高評価')
  }

  const categoryKeyword = activeCategory.value.replace('が人気', '')

  return getStoreSearchText(store).includes(categoryKeyword.toLowerCase())
}

function getReviewCount(store: StoreSummary) {
  return Number.parseInt((store.reviews ?? '0').replace(/,/g, ''), 10) || 0
}

function getStoreSearchText(store: StoreSummary) {
  return [
    store.name,
    store.description,
    ...(store.categories ?? []),
    ...(store.tags ?? []),
    ...(store.products ?? []).flatMap((product) => [
      product.name,
      product.category,
      product.description,
    ]),
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()
}

async function loadStores() {
  try {
    const response = await apiRequest<{ stores: StoreSummary[] }>('/stores')
    stores.value = response.stores
  } catch {
    stores.value = []
  }
}

function selectCategory(category: string) {
  activeCategory.value = category
  window.location.hash = 'stores'
}

function scrollNearbyStores(direction: 'left' | 'right') {
  if (!nearbyScroller.value) {
    return
  }

  nearbyScroller.value.scrollBy({
    left: direction === 'right' ? nearbyScroller.value.clientWidth : -nearbyScroller.value.clientWidth,
    behavior: 'smooth',
  })
}

function openStoreDetail(store: StoreSummary) {
  selectedStore.value = store
  window.scrollTo({ top: 0 })
}

function closeStoreDetail() {
  selectedStore.value = null
  window.scrollTo({ top: 0 })
}

function openAccountSection(section: 'profile' | 'orders' | 'delivery') {
  selectedStore.value = null

  if (section === 'orders') {
    currentView.value = 'orderHistory'
    window.scrollTo({ top: 0 })
    return
  }

  accountInitialSection.value = section
  currentView.value = 'delivery'
  window.scrollTo({ top: 0 })
}

function closeDeliveryInfo() {
  currentView.value = 'home'
  window.scrollTo({ top: 0 })
}

function openOrderHistory() {
  selectedStore.value = null
  isCartOpen.value = false
  currentView.value = 'orderHistory'
  window.scrollTo({ top: 0 })
}

function openTop() {
  selectedStore.value = null
  isCartOpen.value = false
  currentView.value = 'home'
  window.scrollTo({ top: 0 })
}

function updateUser(user: User) {
  emit('updateUser', user)
}

async function addCartItem(item: CartItem) {
  const currentStoreName = cartItems.value[0]?.storeName

  if (currentStoreName && currentStoreName !== item.storeName) {
    pendingDifferentStoreItem.value = item
    return
  }

  await addCartItemToCurrentCart(item)
}

async function addCartItemToCurrentCart(item: CartItem) {
  await syncCartRequest(
    '/cart/items',
    {
      method: 'POST',
      body: JSON.stringify({
        product_id: item.menuItemId,
        quantity: item.quantity,
        store_name: item.storeName,
      }),
    },
  )
  showCartToast(`${item.name}をカートに追加しました`)
}

async function confirmReplaceCartStore() {
  if (!pendingDifferentStoreItem.value) {
    return
  }

  const item = pendingDifferentStoreItem.value
  pendingDifferentStoreItem.value = null
  await addCartItemToCurrentCart(item)
}

function cancelReplaceCartStore() {
  pendingDifferentStoreItem.value = null
}

async function updateCartItemQuantity(item: CartItem, amount: number, shouldOpenCart = true) {
  const nextQuantity = item.quantity + amount

  if (nextQuantity <= 0) {
    removeCartItem(item)
    return
  }

  await syncCartRequest(
    `/cart/items/${item.menuItemId}`,
    {
      method: 'PATCH',
      body: JSON.stringify({
        quantity: nextQuantity,
      }),
    },
    shouldOpenCart,
  )
}

async function removeCartItem(item: CartItem, shouldOpenCart = true) {
  await syncCartRequest(
    `/cart/items/${item.menuItemId}`,
    {
      method: 'DELETE',
    },
    shouldOpenCart,
  )
}

async function clearCart() {
  await syncCartRequest('/cart', {
    method: 'DELETE',
  })
  closeCart()
}

function openCart() {
  isCartOpen.value = true
}

function closeCart() {
  isCartOpen.value = false
}

function logout() {
  cartItems.value = []
  cartExpiresAt.value = null
  isCartOpen.value = false
  emit('logout')
}

function openCheckout() {
  selectedStore.value = null
  isCartOpen.value = false
  currentView.value = 'checkout'
  window.scrollTo({ top: 0 })
}

function openDeliveryInfoFromCheckout() {
  selectedStore.value = null
  isCartOpen.value = false
  accountInitialSection.value = 'delivery'
  currentView.value = 'delivery'
  window.scrollTo({ top: 0 })
}

function completeOrder() {
  cartItems.value = []
  cartExpiresAt.value = null
  currentView.value = 'orderComplete'
  window.scrollTo({ top: 0 })
}

function formatPrice(price: number) {
  return `${price.toLocaleString('ja-JP')}円`
}

async function loadCart() {
  await syncCartRequest('/cart')
}

async function syncCartRequest(path: string, options: RequestInit = {}, shouldOpenCart = false) {
  const token = getCustomerToken()
  if (!token) {
    cartItems.value = []
    return
  }

  const response = await apiRequest<CartResponse>(path, {
    ...options,
    headers: {
      ...authHeaders(token),
      ...options.headers,
    },
  })

  applyCartResponse(response, shouldOpenCart)
}

function applyCartResponse(response: CartResponse, shouldOpenCart: boolean) {
  cartItems.value = response.items
  cartExpiresAt.value = response.expires_at
  resetCartExpiryTimer(response.expires_at)

  if (shouldOpenCart && cartItems.value.length) {
    isCartOpen.value = true
  }
}

function resetCartExpiryTimer(expiresAt: string | null) {
  if (cartResetTimer) {
    window.clearTimeout(cartResetTimer)
  }

  if (!expiresAt) {
    cartExpiresAt.value = null
    return
  }

  const delay = new Date(expiresAt).getTime() - Date.now()
  if (delay <= 0) {
    cartItems.value = []
    cartExpiresAt.value = null
    isCartOpen.value = false
    return
  }

  cartResetTimer = window.setTimeout(() => {
    cartItems.value = []
    cartExpiresAt.value = null
    isCartOpen.value = false
  }, delay)
}

function showCartToast(message: string) {
  toastMessage.value = message

  if (toastTimer) {
    window.clearTimeout(toastTimer)
  }

  toastTimer = window.setTimeout(() => {
    toastMessage.value = ''
  }, 2600)
}
</script>

<template>
  <div class="min-h-screen bg-neutral-50 text-neutral-950">
    <StoreDetailPage
      v-if="selectedStore"
      :store="selectedStore"
      :cart-count="cartItemCount"
      @back="closeStoreDetail"
      @open-cart="openCart"
      @open-account-section="openAccountSection"
      @add-cart="addCartItem"
      @logout="logout"
    />

    <template v-else>
    <AppHeader
      v-model:search-query="searchQuery"
      :is-authenticated="true"
      :cart-count="cartItemCount"
      @open-cart="openCart"
      @open-account-section="openAccountSection"
      @brand-click="closeDeliveryInfo"
      @logout="logout"
    />

    <div
      v-if="cartItems.length"
      class="border-b border-red-100 bg-red-50 px-5 py-3 text-sm font-black text-red-800 md:px-8"
      role="status"
    >
      <div class="mx-auto flex w-full max-w-7xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <span>30分操作がない場合は自動で空になります ({{ cartRemainingText }})</span>
        <button
          class="w-fit rounded-full bg-white px-4 py-2 text-xs font-black text-red-700 shadow-sm ring-1 ring-red-100 hover:bg-red-700 hover:text-white"
          type="button"
          @click="openCart"
        >
          カートを確認
        </button>
      </div>
    </div>

    <DeliveryInfoPage
      v-if="currentView === 'delivery'"
      :user="user"
      :initial-section="accountInitialSection"
      @back="closeDeliveryInfo"
      @updated="updateUser"
      @open-order-history="openOrderHistory"
      @logout="logout"
    />

    <OrderHistoryPage v-else-if="currentView === 'orderHistory'" />

    <OrderCompletePage
      v-else-if="currentView === 'orderComplete'"
      @top="openTop"
      @order-history="openOrderHistory"
    />

    <CheckoutPage
      v-else-if="currentView === 'checkout'"
      :cart-items="cartItems"
      :cart-total="cartTotal"
      :user="user"
      @back="currentView = 'home'"
      @completed="completeOrder"
      @open-delivery-info="openDeliveryInfoFromCheckout"
      @update-quantity="updateCartItemQuantity($event.item, $event.amount, false)"
      @remove-item="removeCartItem($event, false)"
    />

    <main v-else class="mx-auto w-full max-w-7xl px-5 py-6 md:px-8">
      <section class="ramen-hero overflow-hidden rounded-lg px-6 py-16 text-white md:px-10 md:py-24">
        <div class="max-w-xl">
          <p class="mb-3 text-sm font-black text-white/80">ようこそ、{{ user.name }}さん</p>
          <h1 class="text-4xl font-black leading-tight tracking-normal md:text-5xl">
            今日の一杯を見つけよう
          </h1>
          <p class="mt-6 text-base font-bold leading-8 text-white/90 md:text-lg">
            厳選された究極のラーメン店ガイド。あなたの気分に合わせた最高の一杯をご提案します。
          </p>
          <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <a class="rounded-md bg-red-700 px-8 py-4 text-center text-sm font-black text-white hover:bg-red-800" href="#stores">
              今すぐ探す
            </a>
            <a
              class="rounded-md border border-white/45 bg-white/10 px-8 py-4 text-center text-sm font-black text-white backdrop-blur hover:bg-white/20"
              href="#nearby"
            >
              ランキングを見る
            </a>
          </div>
        </div>
      </section>

      <section class="mt-8 flex gap-4 overflow-x-auto pb-1" aria-label="カテゴリ">
        <button
          v-for="category in categories"
          :key="category"
          :class="[
            'shrink-0 rounded-full border px-6 py-2 text-sm font-black transition',
            activeCategory === category
              ? 'border-red-700 bg-red-700 text-white'
              : 'border-red-200 text-neutral-600 hover:border-red-700 hover:text-red-700',
          ]"
          type="button"
          @click="selectCategory(category)"
        >
          {{ category }}
        </button>
      </section>

      <section id="stores" class="mt-10">
        <div class="mb-7 flex items-center justify-between gap-4">
          <div>
            <h2 class="flex items-center gap-2 text-3xl font-black tracking-normal">
              <Store class="h-7 w-7 text-red-700" />
              おすすめの店舗
            </h2>
            <p v-if="isSearching || isFiltering" class="mt-2 text-sm font-bold text-neutral-500">
              <template v-if="isSearching">「{{ searchQuery }}」</template>
              <template v-if="isSearching && isFiltering"> / </template>
              <template v-if="isFiltering">{{ activeCategory }}</template>
              の表示結果: {{ totalSearchResults }}件
            </p>
          </div>
          <button
            v-if="isSearching || isFiltering"
            class="text-sm font-black text-red-700 hover:text-red-800"
            type="button"
            @click="searchQuery = ''; activeCategory = 'すべて'"
          >
            条件をクリア
          </button>
          <a v-else class="text-sm font-black text-red-700 hover:text-red-800" href="#">すべて表示</a>
        </div>

        <div v-if="filteredRecommendedStores.length" class="grid gap-6 lg:grid-cols-3">
          <article
            v-for="store in filteredRecommendedStores"
            :key="store.name"
            class="overflow-hidden rounded-lg border border-neutral-200 bg-white text-left shadow-sm transition hover:-translate-y-0.5 hover:border-red-200 hover:shadow-md"
            role="button"
            tabindex="0"
            @click="openStoreDetail(store)"
            @keydown.enter.prevent="openStoreDetail(store)"
            @keydown.space.prevent="openStoreDetail(store)"
          >
            <div class="relative h-48 overflow-hidden bg-neutral-100">
              <FallbackImage :src="store.imagePath" :alt="`${store.name}の画像`" />
              <span class="absolute right-4 top-4 inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-sm font-black text-red-700 shadow-sm">
                <Star class="h-4 w-4 fill-current" aria-hidden="true" />
                {{ store.rating }}
              </span>
            </div>
            <div class="p-5">
              <h3 class="text-2xl font-black tracking-normal">{{ store.name }}</h3>
              <div class="mt-3 flex flex-wrap gap-2">
                <span
                  v-for="tag in store.tags"
                  :key="tag"
                  class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700"
                >
                  {{ tag }}
                </span>
              </div>
              <p class="mt-4 min-h-20 text-sm font-medium leading-7 text-neutral-600">
                {{ store.description }}
              </p>
              <div class="mt-5 flex items-center justify-between border-t border-neutral-100 pt-4">
                <span class="text-sm font-black text-neutral-600">{{ store.budget }}</span>
                <button
                  class="grid h-9 w-9 place-items-center rounded-full text-neutral-500 hover:bg-red-50 hover:text-red-700"
                  type="button"
                  aria-label="お気に入り"
                  @click.stop
                >
                  <Heart class="h-5 w-5" aria-hidden="true" />
                </button>
              </div>
            </div>
          </article>
        </div>

        <div
          v-else
          class="rounded-lg border border-dashed border-neutral-300 bg-white px-6 py-12 text-center"
        >
          <p class="text-lg font-black text-neutral-800">該当するおすすめ店舗がありません</p>
          <p class="mt-2 text-sm font-bold text-neutral-500">別の店舗名・料理名で検索してください。</p>
        </div>
      </section>

      <section id="nearby" class="mt-12 rounded-lg bg-white px-5 py-8 shadow-sm md:px-7">
        <div class="mb-7 flex items-center justify-between">
          <h2 class="flex items-center gap-2 text-3xl font-black tracking-normal">
            <MapPinned class="h-7 w-7 text-red-700" />
            近くの人気店
          </h2>
          <div class="hidden gap-2 md:flex">
            <button
              class="grid h-9 w-9 place-items-center rounded-full border border-neutral-200 text-neutral-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700"
              type="button"
              aria-label="前の人気店を見る"
              @click="scrollNearbyStores('left')"
            >
              <ChevronLeft class="h-5 w-5" />
            </button>
            <button
              class="grid h-9 w-9 place-items-center rounded-full border border-neutral-200 text-neutral-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700"
              type="button"
              aria-label="次の人気店を見る"
              @click="scrollNearbyStores('right')"
            >
              <ChevronRight class="h-5 w-5" />
            </button>
          </div>
        </div>

        <div
          v-if="filteredNearbyStores.length"
          ref="nearbyScroller"
          class="flex snap-x gap-5 overflow-x-auto scroll-smooth pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
          <article
            v-for="store in filteredNearbyStores"
            :key="store.name"
            class="w-64 shrink-0 snap-start rounded-md border border-neutral-200 bg-white p-2 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-red-200 hover:shadow-md sm:w-72 lg:w-80"
            role="button"
            tabindex="0"
            @click="openStoreDetail(store)"
            @keydown.enter.prevent="openStoreDetail(store)"
            @keydown.space.prevent="openStoreDetail(store)"
          >
            <div class="h-32 overflow-hidden rounded-md bg-neutral-100">
              <FallbackImage :src="store.imagePath" :alt="`${store.name}の画像`" />
            </div>
            <div class="px-2 py-3">
              <h3 class="font-black tracking-normal">{{ store.name }}</h3>
              <p class="mt-1 inline-flex items-center gap-1 text-sm font-bold text-neutral-600">
                <Star class="h-4 w-4 fill-current text-yellow-500" aria-hidden="true" />
                <span>{{ store.rating }} ({{ store.reviews }} 件のレビュー)</span>
              </p>
            </div>
          </article>
        </div>

        <div
          v-else
          class="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 px-6 py-10 text-center"
        >
          <p class="font-black text-neutral-800">該当する人気店がありません</p>
        </div>
      </section>
    </main>

    <AppFooter />
    </template>

    <div
      v-if="toastMessage"
      class="fixed left-1/2 top-20 z-50 w-[calc(100%-32px)] max-w-xl -translate-x-1/2 rounded-lg border border-red-100 bg-white px-5 py-4 text-sm font-black text-neutral-900 shadow-xl"
      role="status"
    >
      <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <span class="text-red-700">{{ toastMessage }}</span>
        <span v-if="cartItems.length" class="text-xs font-black text-neutral-500">
          カート保持時間 {{ cartRemainingText }}
        </span>
      </div>
    </div>

    <div
      v-if="isCartOpen"
      class="fixed inset-0 z-40 bg-black/30"
      role="presentation"
      @click="closeCart"
    />

    <div
      v-if="pendingDifferentStoreItem"
      class="fixed inset-0 z-[70] bg-black/40"
      role="presentation"
      @click="cancelReplaceCartStore"
    />

    <section
      v-if="pendingDifferentStoreItem"
      class="fixed left-1/2 top-1/2 z-[80] w-[calc(100%-32px)] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-lg bg-white p-6 shadow-2xl"
      aria-label="カート店舗変更確認"
      role="dialog"
      aria-modal="true"
      @click.stop
    >
      <h2 class="text-2xl font-black tracking-normal">カートを入れ替えますか？</h2>
      <p class="mt-3 text-sm font-bold leading-6 text-neutral-600">
        別店舗の商品がカートに入っています。現在のカートを空にして、
        <span class="font-black text-red-700">{{ pendingDifferentStoreItem.storeName }}</span>
        の商品を追加しますか？
      </p>
      <div class="mt-6 grid grid-cols-2 gap-3">
        <button
          class="rounded-full border border-neutral-200 px-5 py-3 text-sm font-black text-neutral-600 hover:bg-neutral-50"
          type="button"
          @click="cancelReplaceCartStore"
        >
          キャンセル
        </button>
        <button
          class="rounded-full bg-red-700 px-5 py-3 text-sm font-black text-white hover:bg-red-800"
          type="button"
          @click="confirmReplaceCartStore"
        >
          入れ替える
        </button>
      </div>
    </section>

    <aside
      v-if="isCartOpen"
      class="fixed right-0 top-0 z-50 flex h-full w-full max-w-md flex-col bg-white shadow-2xl"
      aria-label="カート"
    >
      <div class="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
        <div>
          <h2 class="text-2xl font-black tracking-normal">カート</h2>
          <p class="mt-1 text-sm font-bold text-neutral-500">
            30分操作がない場合は自動で空になります
            <span v-if="cartItems.length" class="text-red-700">({{ cartRemainingText }})</span>
          </p>
        </div>
        <button
          class="grid h-10 w-10 place-items-center rounded-full text-neutral-600 hover:bg-neutral-100"
          type="button"
          aria-label="閉じる"
          @click="closeCart"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <div v-if="cartItems.length" class="flex-1 overflow-y-auto px-5 py-4">
        <article
          v-for="item in cartItems"
          :key="`${item.storeName}-${item.menuItemId}`"
          class="border-b border-neutral-100 py-4"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-black text-red-700">{{ item.storeName }}</p>
              <h3 class="mt-1 font-black tracking-normal">{{ item.name }}</h3>
              <p class="mt-1 text-sm font-bold text-neutral-500">{{ formatPrice(item.price) }}</p>
            </div>
            <button
              class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-neutral-500 hover:bg-red-50 hover:text-red-700"
              type="button"
              aria-label="削除"
              @click="removeCartItem(item)"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </div>

          <div class="mt-4 flex items-center justify-between">
            <div class="inline-flex items-center rounded-full bg-neutral-100 px-3 py-2">
              <button
                class="grid h-7 w-7 place-items-center rounded-full font-black text-neutral-700 hover:bg-white"
                type="button"
                @click="updateCartItemQuantity(item, -1)"
              >
                -
              </button>
              <span class="w-9 text-center text-sm font-black">{{ item.quantity }}</span>
              <button
                class="grid h-7 w-7 place-items-center rounded-full font-black text-red-700 hover:bg-white"
                type="button"
                @click="updateCartItemQuantity(item, 1)"
              >
                +
              </button>
            </div>
            <p class="font-black text-neutral-900">{{ formatPrice(item.price * item.quantity) }}</p>
          </div>
        </article>
      </div>

      <div v-else class="grid flex-1 place-items-center px-6 text-center">
        <div>
          <p class="text-xl font-black tracking-normal">カートは空です</p>
          <p class="mt-2 text-sm font-bold text-neutral-500">店舗のメニューから商品を追加してください。</p>
        </div>
      </div>

      <div class="border-t border-neutral-200 p-5">
        <div class="flex items-center justify-between text-lg font-black">
          <span>合計</span>
          <span class="text-red-700">{{ formatPrice(cartTotal) }}</span>
        </div>
        <div class="mt-4 grid grid-cols-[auto_1fr] gap-3">
          <button
            class="rounded-full border border-neutral-200 px-5 py-3 text-sm font-black text-neutral-600 hover:bg-neutral-50 disabled:opacity-40"
            type="button"
            :disabled="!cartItems.length"
            @click="clearCart"
          >
            空にする
          </button>
          <button
            class="rounded-full bg-red-700 px-5 py-3 text-sm font-black text-white hover:bg-red-800 disabled:opacity-40"
            type="button"
            :disabled="!cartItems.length"
            @click="openCheckout"
          >
            注文へ進む
          </button>
        </div>
      </div>
    </aside>

  </div>
</template>
