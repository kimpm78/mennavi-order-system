<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ChevronLeft, ExternalLink, Info, MapPin, Phone, ShoppingBag, Utensils } from 'lucide-vue-next'
import FallbackImage from '@/components/common/FallbackImage.vue'
import AppFooter from '@/components/layout/AppFooter.vue'
import AppHeader from '@/components/layout/AppHeader.vue'

const props = defineProps<{
  store: {
    name: string
    tags?: string[]
    description?: string
    budget?: string
    weekdayHours?: string | null
    weekendHours?: string | null
    holiday?: string | null
    rating: string
    reviews?: string
    reviewItems?: StoreReview[]
    imagePath?: string | null
    imageClass: string
    address?: string | null
    phone?: string | null
    products?: MenuItem[]
  }
  cartCount: number
  cartRemainingText?: string
  backLabel?: string
  activeNav?: 'stores' | 'favorites' | ''
}>()

const emit = defineEmits<{
  back: []
  logout: []
  openCart: []
  navigatePage: [path: string]
  openAccountSection: [section: 'profile' | 'orders' | 'delivery']
  addCart: [item: CartItem]
}>()

const searchQuery = ref('')
const activeCategory = ref('すべて')

type MenuItem = {
  id: number
  name: string
  category: string
  price: number
  status?: string
  badge?: string
  imagePath?: string | null
  imageClass?: string
  description?: string | null
  toppings?: SelectedOption[]
}

type SelectedOption = {
  product_id?: number | null
  name: string
  price: number
}

type StoreReview = {
  id: number
  rating: number
  content?: string | null
  userName?: string | null
  createdAt?: string | null
}

type CartItem = {
  storeName: string
  menuItemId: number
  name: string
  category: string
  price: number
  quantity: number
  selectedOptions?: SelectedOption[]
}

const quantities = reactive<Record<number, number>>(
  Object.fromEntries((props.store.products ?? []).map((item) => [item.id, 1])),
)
const selectedToppings = reactive<Record<number, SelectedOption[]>>({})

const menuItems = computed(() => props.store.products ?? [])
const categories = computed(() => [
  'すべて',
  ...Array.from(new Set(menuItems.value.map((item) => categoryDisplayLabel(item.category)))),
])

const filteredMenuItems = computed(() => {
  if (activeCategory.value === 'すべて') {
    return menuItems.value
  }

  return menuItems.value.filter((item) => categoryDisplayLabel(item.category) === activeCategory.value)
})
const reviewCount = computed(() => Number.parseInt((props.store.reviews ?? '0').replace(/,/g, ''), 10) || 0)
const hasReviews = computed(() => reviewCount.value > 0)

function updateQuantity(itemId: number, amount: number) {
  if (!quantities[itemId]) {
    quantities[itemId] = 1
  }
  quantities[itemId] = Math.max(1, quantities[itemId] + amount)
}

function isSoldOut(item: MenuItem) {
  return item.status === 'sold_out'
}

function formatPrice(price: number) {
  return `${price.toLocaleString('ja-JP')}円`
}

function categoryDisplayLabel(category?: string | null) {
  if (category === 'メイン') {
    return 'ラーメン'
  }

  if (category === 'ドリンク & お酒') {
    return 'ドリンク'
  }

  return category || '未分類'
}

function canSelectToppings(item: MenuItem) {
  return item.category === 'メイン' && (item.toppings?.length ?? 0) > 0
}

function isToppingSelected(itemId: number, topping: SelectedOption) {
  return (selectedToppings[itemId] ?? []).some((option) => option.product_id === topping.product_id)
}

function toggleTopping(itemId: number, topping: SelectedOption) {
  const current = selectedToppings[itemId] ?? []

  if (isToppingSelected(itemId, topping)) {
    selectedToppings[itemId] = current.filter((option) => option.product_id !== topping.product_id)
    return
  }

  selectedToppings[itemId] = [...current, topping]
}

function formatReviewDate(value?: string | null) {
  if (!value) {
    return ''
  }

  return new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date(value))
}

function googleMapEmbedUrl(address?: string | null) {
  if (!address) {
    return ''
  }

  return `https://www.google.com/maps?q=${encodeURIComponent(address)}&output=embed`
}

function googleMapOpenUrl(address?: string | null) {
  if (!address) {
    return 'https://www.google.com/maps'
  }

  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`
}

function businessHourLines(value?: string | null, fallback = '') {
  return (value || fallback).split('\n').filter(Boolean)
}

function holidayLabel(value?: string | null) {
  return value ? value.split(',').filter(Boolean).join('、') : '火曜日'
}

function addToCart(item: MenuItem) {
  if (isSoldOut(item)) {
    return
  }

  const quantity = quantities[item.id] ?? 1

  emit('addCart', {
    storeName: props.store.name,
    menuItemId: item.id,
    name: item.name,
    category: item.category,
      price: item.price,
      quantity,
      selectedOptions: selectedToppings[item.id] ?? [],
    })

  quantities[item.id] = 1
  selectedToppings[item.id] = []
}
</script>

<template>
  <div class="min-h-screen bg-neutral-50 text-neutral-950">
    <AppHeader
      v-model:search-query="searchQuery"
      :is-authenticated="true"
      :cart-count="cartCount"
      :active-nav="activeNav ?? 'stores'"
      @open-cart="emit('openCart')"
      @open-account-section="emit('openAccountSection', $event)"
      @brand-click="emit('back')"
      @stores-click="emit('navigatePage', '/stores')"
      @favorites-click="emit('navigatePage', '/favorites')"
      @logout="emit('logout')"
    />

    <div
      v-if="cartCount > 0"
      class="border-b border-red-100 bg-red-50 px-5 py-3 text-sm font-black text-red-800 md:px-8"
      role="status"
    >
      <div class="mx-auto flex w-full max-w-7xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <span>30分操作がない場合は自動で空になります ({{ cartRemainingText ?? '残り0分' }})</span>
        <button
          class="w-fit rounded-full bg-white px-4 py-2 text-xs font-black text-red-700 shadow-sm ring-1 ring-red-100 hover:bg-red-700 hover:text-white"
          type="button"
          @click="emit('openCart')"
        >
          カートを確認
        </button>
      </div>
    </div>

    <main class="mx-auto w-full max-w-7xl px-5 py-4 md:px-8">
      <button
        class="mb-4 inline-flex items-center gap-2 text-sm font-black text-neutral-600 hover:text-red-700"
        type="button"
        @click="emit('back')"
      >
        <ChevronLeft class="h-4 w-4" />
        {{ backLabel ?? '店舗一覧へ戻る' }}
      </button>

      <section class="relative min-h-[420px] overflow-hidden rounded-lg bg-neutral-100 px-6 py-16 text-white md:px-9 md:py-24">
        <div class="absolute inset-0">
          <FallbackImage :src="store.imagePath" :alt="`${store.name}の画像`" />
        </div>
        <div class="absolute inset-0 z-[1] bg-gradient-to-t from-black/75 via-black/25 to-transparent" />
        <div class="relative z-[2] flex min-h-[280px] flex-col justify-end">
          <div class="mb-3 flex flex-wrap items-center gap-2">
            <span v-if="hasReviews" class="rounded-full bg-red-700 px-3 py-1 text-xs font-black">レビューあり</span>
            <span v-if="Number.parseFloat(store.rating) >= 4" class="rounded-full bg-amber-500 px-3 py-1 text-xs font-black">高評価</span>
            <span class="text-sm font-black">
              {{ hasReviews ? `★ ${store.rating} (${store.reviews ?? '0'}レビュー)` : 'レビューなし' }}
            </span>
          </div>
          <h1 class="text-4xl font-black tracking-normal md:text-5xl">{{ store.name }}</h1>
          <p class="mt-4 text-lg font-bold text-white/90">
            {{ store.description ?? '醤油ラーメン専門店・約15分でお届け' }}
          </p>
        </div>
      </section>

      <div class="mt-8 grid gap-6 lg:grid-cols-[392px_minmax(0,1fr)]">
        <aside class="space-y-6">
          <section class="rounded-lg border border-red-200 bg-white p-6">
            <h2 class="flex items-center gap-2 text-2xl font-black tracking-normal">
              <Info class="h-6 w-6 text-red-700" />
              店舗情報
            </h2>

            <div class="mt-6 space-y-5 text-sm">
              <div>
                <p class="font-bold text-neutral-500">営業時間</p>
                <dl class="mt-3 grid grid-cols-[72px_1fr] gap-y-3 font-bold">
                  <dt>平日</dt>
                  <dd class="text-right leading-6">
                    <template
                      v-for="(line, index) in businessHourLines(store.weekdayHours, '11:00-15:00\n17:00-22:00')"
                      :key="`${line}-${index}`"
                    >
                      {{ line }}<br />
                    </template>
                  </dd>
                  <dt>土日祝</dt>
                  <dd class="text-right">{{ store.weekendHours || '11:00-22:00' }}</dd>
                  <dt>休日</dt>
                  <dd class="text-right">{{ holidayLabel(store.holiday) }}</dd>
                </dl>
              </div>

              <div class="border-t border-red-100 pt-5">
                <p class="font-bold text-neutral-500">所在地</p>
                <p class="mt-2 flex items-center gap-2 font-bold">
                  <MapPin class="h-4 w-4 text-red-700" />
                  {{ store.address ?? '所在地未設定' }}
                </p>
                <div class="store-map mt-3">
                  <iframe
                    v-if="store.address"
                    class="h-full w-full"
                    :src="googleMapEmbedUrl(store.address)"
                    :title="`${store.name}の地図`"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                  />
                  <div
                    v-else
                    class="grid h-full place-items-center px-4 text-center text-xs font-black text-neutral-500"
                  >
                    所在地を登録するとGoogle Mapが表示されます。
                  </div>
                  <a
                    class="absolute bottom-3 right-3 z-[2] inline-flex items-center gap-1 rounded-full bg-white px-4 py-2 text-xs font-black text-neutral-800 shadow-sm"
                    :href="googleMapOpenUrl(store.address)"
                    target="_blank"
                    rel="noreferrer"
                  >
                    <ExternalLink class="h-3.5 w-3.5" />
                    地図を開く
                  </a>
                </div>
                <p v-if="store.phone" class="mt-3 flex items-center gap-2 font-bold">
                  <Phone class="h-4 w-4 text-red-700" />
                  <a class="hover:text-red-700"">
                    {{ store.phone }}
                  </a>
                </p>
              </div>
            </div>
          </section>

          <section class="rounded-lg bg-amber-100 p-6 text-neutral-900">
            <div class="flex gap-4">
              <Utensils class="mt-1 h-8 w-8 shrink-0 text-amber-900" />
              <div>
                <h2 class="text-lg font-black tracking-normal">シェフのこだわり</h2>
                <p class="mt-2 text-sm font-bold leading-6">
                  受賞歴のある特製醤油ブレンドを、ぜひ本日お試しください。
                </p>
              </div>
            </div>
          </section>
        </aside>

        <section>
          <div class="flex gap-3 overflow-x-auto pb-2" aria-label="メニューカテゴリ">
            <button
              v-for="category in categories"
              :key="category"
              :class="[
                'shrink-0 rounded-full px-7 py-3 text-sm font-black transition',
                activeCategory === category
                  ? 'bg-red-700 text-white'
                  : 'bg-neutral-200 text-neutral-600 hover:bg-red-50 hover:text-red-700',
              ]"
              type="button"
              @click="activeCategory = category"
            >
              {{ category }}
            </button>
          </div>

          <div class="mt-5 grid gap-6 xl:grid-cols-2">
            <article
              v-for="item in filteredMenuItems"
              :key="item.id"
              class="overflow-hidden rounded-lg border border-red-200 bg-white shadow-sm"
            >
              <div class="relative h-[196px] overflow-hidden bg-neutral-100">
                <FallbackImage :src="item.imagePath" :alt="`${item.name}の画像`" />
                <span
                  v-if="item.badge"
                  class="absolute right-3 top-3 z-[2] rounded bg-white px-3 py-2 text-xs font-black text-red-700 shadow-sm"
                >
                  {{ item.badge }}
                </span>
              </div>

              <div class="p-5">
                <div class="flex items-start justify-between gap-4">
                  <h3 class="text-xl font-black tracking-normal">{{ item.name }}</h3>
                  <p class="shrink-0 text-lg font-black text-red-700">{{ formatPrice(item.price) }}</p>
                </div>
                <p class="mt-3 text-sm font-bold leading-7 text-neutral-600">{{ item.description }}</p>

                <div v-if="canSelectToppings(item)" class="mt-4">
                  <p class="text-xs font-black text-neutral-500">トッピング</p>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <button
                      v-for="topping in item.toppings"
                      :key="`${item.id}-${topping.product_id}`"
                      :class="[
                        'text-xs rounded px-3 font-bold leading-tight transition',
                        isToppingSelected(item.id, topping)
                          ? 'bg-red-700 text-white'
                          : 'bg-neutral-200 text-neutral-600 hover:bg-red-50 hover:text-red-700',
                      ]"
                      type="button"
                      :disabled="isSoldOut(item)"
                      @click="toggleTopping(item.id, topping)"
                    >
                      <span class="text-xs leading-tight">
                        + {{ topping.name }}（+{{ formatPrice(topping.price) }}）
                      </span>
                    </button>
                  </div>
                </div>

                <div class="mt-6 flex items-center justify-between gap-4">
                  <div
                    :class="[
                      'inline-flex items-center rounded-full px-3 py-2',
                      isSoldOut(item) ? 'bg-neutral-200 opacity-60' : 'bg-neutral-100',
                    ]"
                  >
                    <button
                      class="grid h-7 w-7 place-items-center rounded-full font-black text-neutral-700 hover:bg-white"
                      type="button"
                      :disabled="isSoldOut(item)"
                      @click="updateQuantity(item.id, -1)"
                    >
                      -
                    </button>
                    <span class="w-9 text-center text-sm font-black">{{ quantities[item.id] }}</span>
                    <button
                      class="grid h-7 w-7 place-items-center rounded-full font-black text-red-700 hover:bg-white"
                      type="button"
                      :disabled="isSoldOut(item)"
                      @click="updateQuantity(item.id, 1)"
                    >
                      +
                    </button>
                  </div>

                  <button
                    :class="[
                      'inline-flex min-h-11 items-center justify-center gap-2 rounded-full px-6 text-sm font-black text-white',
                      isSoldOut(item)
                        ? 'cursor-not-allowed bg-neutral-400'
                        : 'bg-red-700 hover:bg-red-800',
                    ]"
                    type="button"
                    :disabled="isSoldOut(item)"
                    @click="addToCart(item)"
                  >
                    <ShoppingBag class="h-4 w-4" />
                    {{ isSoldOut(item) ? '売り切れ' : 'カートに追加' }}
                  </button>
                </div>
              </div>
            </article>
          </div>
        </section>
      </div>

      <section class="mt-10 rounded-lg border border-red-200 bg-white p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <p class="text-sm font-black text-red-700">Reviews</p>
            <h2 class="text-2xl font-black tracking-normal">店舗レビュー</h2>
          </div>
          <p class="text-sm font-black text-neutral-600">
            {{ hasReviews ? `★ ${store.rating} / ${store.reviews ?? '0'}件` : 'レビューなし' }}
          </p>
        </div>

        <div v-if="store.reviewItems?.length" class="mt-5 grid gap-4 md:grid-cols-2">
          <article
            v-for="review in store.reviewItems"
            :key="review.id"
            class="rounded-lg border border-red-100 bg-neutral-50 p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-sm font-black text-neutral-900">{{ review.userName ?? 'ゲスト' }}</p>
                <p class="mt-1 text-sm font-black text-amber-600">
                  {{ '★'.repeat(review.rating) }}{{ '☆'.repeat(5 - review.rating) }}
                </p>
              </div>
              <p class="text-xs font-bold text-neutral-500">{{ formatReviewDate(review.createdAt) }}</p>
            </div>
            <p v-if="review.content" class="mt-3 text-sm font-bold leading-6 text-neutral-600">
              {{ review.content }}
            </p>
          </article>
        </div>

        <p v-else class="mt-5 rounded-lg bg-neutral-50 px-5 py-8 text-center text-sm font-black text-neutral-500">
          まだレビューはありません。
        </p>
      </section>
    </main>

    <AppFooter />
  </div>
</template>
