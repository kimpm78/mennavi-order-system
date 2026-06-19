<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  ArrowRight,
  ChevronLeft,
  ChevronRight,
  Crown,
  Heart,
  MapPinned,
  Star,
  Store,
  Trash2,
  X,
} from 'lucide-vue-next'
import FallbackImage from '@/components/common/FallbackImage.vue'
import AppFooter from '@/components/layout/AppFooter.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import { apiBaseUrl, apiRequest, authHeaders } from '@/lib/api'
import { getCustomerToken } from '@/lib/authStorage'
import type { User } from '@/types/auth'
import AboutPage from './AboutPage.vue'
import CheckoutPage from './CheckoutPage.vue'
import ContactPage from './ContactPage.vue'
import DeliveryInfoPage from './DeliveryInfoPage.vue'
import FavoritePage from './FavoritePage.vue'
import OrderCompletePage from './OrderCompletePage.vue'
import OrderHistoryPage from './OrderHistoryPage.vue'
import PrivacyPage from './PrivacyPage.vue'
import StoreDetailPage from './StoreDetailPage.vue'
import TermsPage from './TermsPage.vue'
import PlusPage from './PlusPage.vue'

const props = defineProps<{
  user: User
  loading?: boolean
  currentPath: string
  goTo: (path: string) => void
}>()

const emit = defineEmits<{
  logout: []
  updateUser: [user: User]
}>()

const activeCategory = ref('すべて')
const searchQuery = ref('')
const selectedStore = ref<StoreSummary | null>(null)
const currentView = ref<
  | 'home'
  | 'favorite'
  | 'plus'
  | 'delivery'
  | 'orderHistory'
  | 'checkout'
  | 'orderComplete'
  | 'about'
  | 'contact'
  | 'terms'
  | 'privacy'
  >('home')

const accountInitialSection = ref<'profile' | 'orders' | 'delivery'>('delivery')
const cartItems = ref<CartItem[]>([])
const cartExpiresAt = ref<string | null>(null)
const currentTime = ref(Date.now())
const isCartOpen = ref(false)
const pendingDifferentStoreItem = ref<CartItem | null>(null)
const toastMessage = ref('')
const nearbyScroller = ref<HTMLElement | null>(null)
const favoriteStoreIds = ref<Set<number>>(new Set())
const plusReturnPath = ref('/stores')
const userOrderNotifications = ref<UserOrderNotification[]>([])
const readUserOrderNotificationIds = ref<Set<string>>(new Set())
const mainVisualSetting = ref<MainVisualSetting>({
  title: '今日の一杯を見つけよう',
  description: '厳選された究極のラーメン店ガイド。あなたの気分に合わせた最高の一杯をご提案します。',
  image_path: null,
})
let cartResetTimer: number | undefined
let cartClockTimer: number | undefined
let toastTimer: number | undefined
let orderNotificationTimer: number | undefined

type StoreSummary = {
  id: number
  name: string
  categories?: string[]
  tags?: string[]
  description?: string
  budget?: string
  weekdayHours?: string | null
  weekendHours?: string | null
  holiday?: string | null
  phone?: string | null
  rating: string
  reviews?: string
  orderCount?: number
  reviewItems?: StoreReview[]
  imagePath?: string | null
  imageClass: string
  products?: MenuItem[]
}

type StoreReview = {
  id: number
  rating: number
  content?: string | null
  userName?: string | null
  createdAt?: string | null
}

type MainVisualSetting = {
  title: string
  description?: string | null
  image_path?: string | null
}

type UserSubscription = {
  status?: string
  current_period_end?: string | null
  cancel_at_period_end?: boolean
}

type UserOrderNotification = {
  id: string
  title: string
  message: string
  tone: 'order' | 'cooking' | 'delivery'
  time?: string
}

type UserOrderSummary = {
  id: number
  order_number: string
  order_status: string
  ordered_at?: string | null
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
  toppings?: SelectedOption[]
}

type SelectedOption = {
  product_id?: number | null
  name: string
  price: number
}

type CartItem = {
  storeName: string
  menuItemId: number
  name: string
  category?: string
  price: number
  basePrice?: number
  selectedOptions?: SelectedOption[]
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
  stores.value
    .filter((store) => matchesCategory(store) && matchesSearch(store, normalizedSearchQuery.value))
    .sort(compareStoreRanking),
)
const filteredFavoriteStores = computed(() =>
  stores.value.filter((store) =>
    favoriteStoreIds.value.has(store.id) &&
    matchesCategory(store) &&
    matchesSearch(store, normalizedSearchQuery.value),
  ),
)
const totalSearchResults = computed(() =>
  new Set([
    ...filteredRecommendedStores.value,
    ...filteredNearbyStores.value,
    ...filteredFavoriteStores.value,
  ].map((store) => store.id)).size,
)
const isSearching = computed(() => normalizedSearchQuery.value.length > 0)
const isFiltering = computed(() => activeCategory.value !== 'すべて')
const cartItemCount = computed(() =>
  cartItems.value.reduce((total, item) => total + item.quantity, 0),
)
const cartTotal = computed(() =>
  cartItems.value.reduce((total, item) => total + item.price * item.quantity, 0),
)
const hasMainMenuInCart = computed(() =>
  cartItems.value.some((item) => item.category === 'メイン'),
)
const canProceedToCheckout = computed(() => cartItems.value.length > 0 && hasMainMenuInCart.value)
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
const userSubscription = ref<UserSubscription | null>(null)
const isPlusMember = computed(() => {
  if (userSubscription.value?.status !== 'active') {
    return false
  }

  if (!userSubscription.value.current_period_end) {
    return true
  }

  return new Date(userSubscription.value.current_period_end).getTime() > Date.now()
})
const mainVisualHeroStyle = computed(() => {
  const imagePath = mainVisualSetting.value.image_path

  if (!imagePath) {
    return {}
  }

  return {
    backgroundImage: `linear-gradient(90deg, rgba(17, 17, 17, 0.72), rgba(17, 17, 17, 0.34)), url("${resolveImageUrl(imagePath)}")`,
  }
})
const topPopularStoreId = computed(() => {
  const [topStore] = [...stores.value].sort(compareStorePopularity)
  const hasPopularity = topStore && (getOrderCount(topStore) > 0 || getReviewCount(topStore) > 0)

  return hasPopularity ? topStore.id : null
})
const headerActiveNav = computed(() => {
  if (currentView.value === 'favorite') {
    return 'favorites'
  }

  return currentView.value === 'home' ? 'stores' : ''
})
const storeDetailBackLabel = computed(() =>
  currentView.value === 'favorite' ? 'お気に入りへ戻る' : '店舗一覧へ戻る',
)
const storeDetailActiveNav = computed(() =>
  currentView.value === 'favorite' ? 'favorites' : 'stores',
)

onMounted(() => {
  cartClockTimer = window.setInterval(() => {
    currentTime.value = Date.now()
  }, 1000)
  loadCart()
  loadStores()
  loadFavoriteStores()
  loadMainVisualSetting()
  loadUserSubscription()
  loadUserOrderNotifications()
  orderNotificationTimer = window.setInterval(loadUserOrderNotifications, 30000)
  syncPathToView(props.currentPath)
})

watch(() => props.currentPath, (path) => {
  syncPathToView(path)
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
  if (orderNotificationTimer) {
    window.clearInterval(orderNotificationTimer)
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
    return store.id === topPopularStoreId.value
  }

  if (activeCategory.value === '高評価') {
    return Number.parseFloat(store.rating) >= 4.0 || getStoreSearchText(store).includes('高評価')
  }

  const categoryKeyword = activeCategory.value.replace('が人気', '')

  return getStoreSearchText(store).includes(categoryKeyword.toLowerCase())
}

function getReviewCount(store: StoreSummary) {
  return Number.parseInt((store.reviews ?? '0').replace(/,/g, ''), 10) || 0
}

function getOrderCount(store: StoreSummary) {
  return store.orderCount ?? 0
}

function compareStoreRanking(left: StoreSummary, right: StoreSummary) {
  const ratingDiff = Number.parseFloat(right.rating) - Number.parseFloat(left.rating)

  if (ratingDiff !== 0) {
    return ratingDiff
  }

  const orderDiff = getOrderCount(right) - getOrderCount(left)

  if (orderDiff !== 0) {
    return orderDiff
  }

  return getReviewCount(right) - getReviewCount(left)
}

function compareStorePopularity(left: StoreSummary, right: StoreSummary) {
  const orderDiff = getOrderCount(right) - getOrderCount(left)

  if (orderDiff !== 0) {
    return orderDiff
  }

  const reviewDiff = getReviewCount(right) - getReviewCount(left)

  if (reviewDiff !== 0) {
    return reviewDiff
  }

  return Number.parseFloat(right.rating) - Number.parseFloat(left.rating)
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

async function loadFavoriteStores() {
  const token = getCustomerToken()
  if (!token) {
    favoriteStoreIds.value = new Set()
    return
  }

  try {
    const response = await apiRequest<{ store_ids: number[] }>('/favorite-stores', {
      headers: authHeaders(token),
    })
    favoriteStoreIds.value = new Set(response.store_ids)
  } catch {
    favoriteStoreIds.value = new Set()
  }
}

async function loadMainVisualSetting() {
  try {
    const response = await apiRequest<{ main_visual_setting: MainVisualSetting }>('/main-visual-setting')
    mainVisualSetting.value = response.main_visual_setting
  } catch {
    mainVisualSetting.value = {
      title: '今日の一杯を見つけよう',
      description: '厳選された究極のラーメン店ガイド。あなたの気分に合わせた最高の一杯をご提案します。',
      image_path: null,
    }
  }
}

async function loadUserSubscription() {
  const token = getCustomerToken()
  if (!token) {
    userSubscription.value = null
    return
  }

  try {
    const response = await apiRequest<{ subscription: UserSubscription | null }>('/me/subscription', {
      headers: authHeaders(token),
    })
    userSubscription.value = response.subscription
  } catch {
    userSubscription.value = null
  }
}

function resolveImageUrl(path: string) {
  if (path.startsWith('/storage/')) {
    return `${apiBaseUrl.replace(/\/api\/?$/, '')}${path}`
  }

  return path
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
    goTo('/orders')
    return
  }

  accountInitialSection.value = section
  goTo(section === 'profile' ? '/profile' : '/delivery')
}

function closeDeliveryInfo() {
  goTo('/stores')
}

function openFavorites() {
  goTo('/favorites')
}

function openOrderHistory() {
  goTo('/orders')
}

function openOrderHistoryFromNotification(notification: UserOrderNotification) {
  readUserOrderNotificationIds.value = new Set([
    ...readUserOrderNotificationIds.value,
    notification.id,
  ])
  userOrderNotifications.value = userOrderNotifications.value.filter((item) => item.id !== notification.id)
  openOrderHistory()
}

function openTop() {
  goTo('/stores')
}

function openPlus(returnPath = '/stores') {
  plusReturnPath.value = returnPath
  goTo('/plus')
}

function goTo(path: string) {
  props.goTo(path)
  syncPathToView(path)
}

function syncPathToView(path: string) {
  selectedStore.value = null
  isCartOpen.value = false

  const pageMap: Record<string, typeof currentView.value> = {
    '/about': 'about',
    '/contact': 'contact',
    '/checkout': 'checkout',
    '/favorites': 'favorite',
    '/plus': 'plus',
    '/order-complete': 'orderComplete',
    '/orders': 'orderHistory',
    '/terms': 'terms',
    '/privacy': 'privacy',
  }

  if (path === '/profile' || path === '/delivery') {
    accountInitialSection.value = path === '/profile' ? 'profile' : 'delivery'
    currentView.value = 'delivery'
    window.scrollTo({ top: 0 })
    return
  }

  if (path === '/ranking') {
    currentView.value = 'home'
    nextTick(() => {
      document.getElementById('nearby')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
    return
  }


  if (path === '/stores' || path === '/login') {
    currentView.value = 'home'
    nextTick(() => {
      document.getElementById('stores')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
    return
  }

  currentView.value = pageMap[path] ?? 'home'
  window.scrollTo({ top: 0 })
}

function updateUser(user: User) {
  emit('updateUser', user)
}

function categoryDisplayLabel(category?: string | null) {
  if (category === 'メイン') {
    return 'ラーメン'
  }

  if (category === 'ドリンク & お酒') {
    return 'ドリンク'
  }

  return category || ''
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
        selected_options: item.selectedOptions ?? [],
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
  favoriteStoreIds.value = new Set()
  userSubscription.value = null
  userOrderNotifications.value = []
  readUserOrderNotificationIds.value = new Set()
  isCartOpen.value = false
  emit('logout')
}

function updateUserSubscription(subscription: UserSubscription | null) {
  userSubscription.value = subscription
}

function openCheckout() {
  if (!hasMainMenuInCart.value) {
    showCartToast('注文にはメインメニューを1点以上追加してください。')
    return
  }

  props.goTo('/checkout')
  syncPathToView('/checkout')
}

function openDeliveryInfoFromCheckout() {
  selectedStore.value = null
  isCartOpen.value = false
  accountInitialSection.value = 'delivery'
  goTo('/delivery')
}

function completeOrder() {
  cartItems.value = []
  cartExpiresAt.value = null
  props.goTo('/order-complete')
  currentView.value = 'orderComplete'
  loadUserOrderNotifications()
  window.scrollTo({ top: 0 })
}

async function loadUserOrderNotifications() {
  const token = getCustomerToken()
  if (!token) {
    userOrderNotifications.value = []
    return
  }

  try {
    const response = await apiRequest<{ orders: UserOrderSummary[] }>('/orders', {
      headers: authHeaders(token),
    })

    userOrderNotifications.value = response.orders
      .filter((order) => ['received', 'cooking', 'delivering'].includes(order.order_status))
      .map(orderNotificationFromOrder)
      .filter((notification) => !readUserOrderNotificationIds.value.has(notification.id))
      .slice(0, 5)
  } catch {
    userOrderNotifications.value = []
  }
}

function orderNotificationFromOrder(order: UserOrderSummary): UserOrderNotification {
  const titleMap: Record<string, UserOrderNotification['title']> = {
    received: '注文受付',
    cooking: '調理開始',
    delivering: '受け取り',
  }
  const toneMap: Record<string, UserOrderNotification['tone']> = {
    received: 'order',
    cooking: 'cooking',
    delivering: 'delivery',
  }
  const messageMap: Record<string, string> = {
    received: `注文 #${order.order_number} を受け付けました。`,
    cooking: `注文 #${order.order_number} の調理が開始されました。`,
    delivering: `注文 #${order.order_number} は配送中です。商品を受け取ったら注文履歴から受け取り完了できます。`,
  }

  return {
    id: `${order.order_status}-${order.id}`,
    title: titleMap[order.order_status] ?? '注文通知',
    message: messageMap[order.order_status] ?? `注文 #${order.order_number} の状況を確認してください。`,
    tone: toneMap[order.order_status] ?? 'order',
    time: formatNotificationTime(order.ordered_at),
  }
}

function formatNotificationTime(value?: string | null) {
  if (!value) {
    return ''
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return ''
  }

  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

function formatPrice(price: number) {
  return `${price.toLocaleString('ja-JP')}円`
}

function isFavoriteStore(store: StoreSummary) {
  return favoriteStoreIds.value.has(store.id)
}

async function toggleFavoriteStore(store: StoreSummary) {
  const token = getCustomerToken()
  if (!token) {
    showCartToast('ログインするとお気に入りを利用できます。')
    return
  }

  const isFavorite = isFavoriteStore(store)

  try {
    const response = await apiRequest<{ store_ids: number[] }>(`/favorite-stores/${store.id}`, {
      method: isFavorite ? 'DELETE' : 'POST',
      headers: authHeaders(token),
    })
    favoriteStoreIds.value = new Set(response.store_ids)
    showCartToast(isFavorite ? `${store.name}をお気に入りから解除しました` : `${store.name}をお気に入りに追加しました`)
  } catch (error) {
    showCartToast(error instanceof Error ? error.message : 'お気に入りの更新に失敗しました。')
  }
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
      :cart-remaining-text="cartRemainingText"
      :back-label="storeDetailBackLabel"
      :active-nav="storeDetailActiveNav"
      @back="closeStoreDetail"
      @navigate-page="goTo"
      @open-cart="openCart"
      @open-account-section="openAccountSection"
      @add-cart="addCartItem"
      @logout="logout"
    />

    <template v-else>
    <AppHeader
      v-model:search-query="searchQuery"
      :is-authenticated="true"
      :is-plus-member="isPlusMember"
      :cart-count="cartItemCount"
      :order-notifications="userOrderNotifications"
      :active-nav="headerActiveNav"
      @open-cart="openCart"
      @open-order-notification="openOrderHistoryFromNotification"
      @open-account-section="openAccountSection"
      @brand-click="closeDeliveryInfo"
      @stores-click="goTo('/stores')"
      @favorites-click="openFavorites"
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
      :subscription="userSubscription"
      @back="closeDeliveryInfo"
      @updated="updateUser"
      @open-order-history="openOrderHistory"
      @open-plus="openPlus('/profile')"
      @logout="logout"
    />

    <FavoritePage
      v-else-if="currentView === 'favorite'"
      :stores="filteredFavoriteStores"
      @back="goTo('/stores')"
      @open-store="openStoreDetail"
      @toggle-favorite="toggleFavoriteStore"
    />

    <PlusPage
      v-else-if="currentView === 'plus'"
      @back="goTo(plusReturnPath)"
      @subscription-updated="updateUserSubscription"
    />

    <OrderHistoryPage
      v-else-if="currentView === 'orderHistory'"
      @open-plus="openPlus('/orders')"
    />

    <OrderCompletePage
      v-else-if="currentView === 'orderComplete'"
      @top="openTop"
      @order-history="openOrderHistory"
    />

    <AboutPage
      v-else-if="currentView === 'about'"
      :go-to="goTo"
    />

    <ContactPage
      v-else-if="currentView === 'contact'"
      :go-to="goTo"
    />

    <TermsPage
      v-else-if="currentView === 'terms'"
      :go-to="goTo"
    />

    <PrivacyPage
      v-else-if="currentView === 'privacy'"
      :go-to="goTo"
    />

    <CheckoutPage
      v-else-if="currentView === 'checkout'"
      :cart-items="cartItems"
      :cart-total="cartTotal"
      :user="user"
      :subscription="userSubscription"
      @back="goTo('/stores')"
      @completed="completeOrder"
      @open-delivery-info="openDeliveryInfoFromCheckout"
      @update-quantity="updateCartItemQuantity($event.item, $event.amount, false)"
      @remove-item="removeCartItem($event, false)"
    />

    <main v-else class="mx-auto w-full max-w-7xl px-5 py-6 md:px-8">
      <section
        class="ramen-hero overflow-hidden rounded-lg px-6 py-16 text-white md:px-10 md:py-24"
        :style="mainVisualHeroStyle"
      >
        <div class="max-w-xl">
          <p class="mb-3 text-sm font-black text-white/80">ようこそ、{{ user.name }}さん</p>
          <h1 class="text-4xl font-black leading-tight tracking-normal md:text-5xl">
            {{ mainVisualSetting.title }}
          </h1>
          <p class="mt-6 text-base font-bold leading-8 text-white/90 md:text-lg">
            {{ mainVisualSetting.description }}
          </p>
          <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <button
              class="rounded-md bg-red-700 px-8 py-4 text-center text-sm font-black text-white hover:bg-red-800"
              type="button"
              @click="goTo('/stores')"
            >
              今すぐ探す
            </button>
            <button
              class="rounded-md border border-white/45 bg-white/10 px-8 py-4 text-center text-sm font-black text-white backdrop-blur hover:bg-white/20"
              type="button"
              @click="goTo('/ranking')"
            >
              ランキングを見る
            </button>
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
          <a
            v-if="isSearching || isFiltering"
            class="text-sm font-black cursor-pointer text-red-700 hover:text-red-800"
            @click="searchQuery = ''; activeCategory = 'すべて'"
          >
            条件をクリア
          </a>
          <button
            v-else
            class="text-sm font-black text-red-700 hover:text-red-800"
            type="button"
            @click="searchQuery = ''; activeCategory = 'すべて'; goTo('/stores')"
          >
            すべて表示
          </button>
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
                {{ getReviewCount(store) > 0 ? store.rating : 'レビューなし' }}
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
                  class="grid h-9 w-9 place-items-center rounded-full transition hover:bg-red-50"
                  :class="isFavoriteStore(store) ? 'text-red-700' : 'text-neutral-500 hover:text-red-700'"
                  type="button"
                  :aria-label="isFavoriteStore(store) ? 'お気に入りを解除' : 'お気に入りに追加'"
                  @click.stop="toggleFavoriteStore(store)"
                >
                  <Heart class="h-5 w-5" :class="isFavoriteStore(store) ? 'fill-current' : ''" aria-hidden="true" />
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
          <div>
            <h2 class="flex items-center gap-2 text-3xl font-black tracking-normal">
              <MapPinned class="h-7 w-7 text-red-700" />
              店舗ランキング
            </h2>
            <p class="mt-2 text-sm font-bold text-neutral-500">
              レビュー評価、注文数、レビュー数をもとに表示しています。
            </p>
          </div>
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
            v-for="(store, index) in filteredNearbyStores"
            :key="store.name"
            class="w-64 shrink-0 snap-start rounded-md border border-neutral-200 bg-white p-2 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-red-200 hover:shadow-md sm:w-72 lg:w-80"
            role="button"
            tabindex="0"
            @click="openStoreDetail(store)"
            @keydown.enter.prevent="openStoreDetail(store)"
            @keydown.space.prevent="openStoreDetail(store)"
          >
            <div class="relative h-32 overflow-hidden rounded-md bg-neutral-100">
              <FallbackImage :src="store.imagePath" :alt="`${store.name}の画像`" />
              <span class="absolute left-3 top-3 rounded-full bg-red-700 px-3 py-1 text-xs font-black text-white shadow-sm">
                No.{{ index + 1 }}
              </span>
            </div>
            <div class="px-2 py-3">
              <h3 class="font-black tracking-normal">{{ store.name }}</h3>
              <p class="mt-1 inline-flex items-center gap-1 text-sm font-bold text-neutral-600">
                <Star class="h-4 w-4 fill-current text-yellow-500" aria-hidden="true" />
                <span>
                  {{ getReviewCount(store) > 0 ? store.rating : 'レビューなし' }}
                </span>
              </p>
              <p class="mt-2 text-xs font-black text-neutral-500">
                注文 {{ getOrderCount(store).toLocaleString('ja-JP') }}件 / レビュー {{ store.reviews }}件
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

      <section class="mt-12 overflow-hidden rounded-lg bg-red-700 px-6 py-7 text-white shadow-sm md:px-8">
        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
          <div class="flex items-start gap-4">
            <div class="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-white/15">
              <Crown class="h-6 w-6" aria-hidden="true" />
            </div>
            <div>
              <p class="text-xl font-black tracking-normal">麺ナビ Plus</p>
              <p class="mt-2 max-w-2xl text-sm font-bold leading-7 text-red-50">
                全店舗で使える送料無料特典と、ご注文ごとに15%割引をご利用いただけます。
              </p>
            </div>
          </div>

          <button
            class="inline-flex h-12 w-fit shrink-0 items-center justify-center gap-2 rounded-lg bg-white px-5 text-sm font-black text-red-700 transition hover:bg-red-50"
            type="button"
            @click="openPlus('/stores')"
          >
            今すぐアップグレード
            <ArrowRight class="h-4 w-4" aria-hidden="true" />
          </button>
        </div>
      </section>
    </main>

    <AppFooter :go-to="goTo" />
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
              <p class="mt-1 text-sm font-bold text-neutral-500">
                <span v-if="item.category">{{ categoryDisplayLabel(item.category) }} ・ </span>{{ formatPrice(item.price) }}
              </p>
              <ul
                v-if="item.selectedOptions?.length"
                class="mt-2 space-y-1 text-xs font-bold text-neutral-500"
              >
                <li v-for="option in item.selectedOptions" :key="`${item.menuItemId}-${option.product_id}`">
                  + {{ option.name }}（{{ formatPrice(option.price) }}）
                </li>
              </ul>
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
            :disabled="!canProceedToCheckout"
            @click="openCheckout"
          >
            注文へ進む
          </button>
        </div>
        <p
          v-if="cartItems.length && !hasMainMenuInCart"
          class="mt-3 rounded-lg bg-red-50 px-4 py-3 text-xs font-black leading-5 text-red-700"
        >
          サイドメニュー、ドリンク & お酒のみでは注文できません。メインメニューを1点以上追加してください。
        </p>
      </div>
    </aside>

  </div>
</template>
