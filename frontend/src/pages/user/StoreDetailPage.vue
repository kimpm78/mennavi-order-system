<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ChevronLeft, ExternalLink, Info, MapPin, ShoppingBag, Utensils } from 'lucide-vue-next'
import FallbackImage from '../../components/common/FallbackImage.vue'
import AppFooter from '../../components/layout/AppFooter.vue'
import AppHeader from '../../components/layout/AppHeader.vue'

const props = defineProps<{
  store: {
    name: string
    tags?: string[]
    description?: string
    budget?: string
    rating: string
    reviews?: string
    imagePath?: string | null
    imageClass: string
    address?: string | null
    products?: MenuItem[]
  }
  cartCount: number
}>()

const emit = defineEmits<{
  back: []
  logout: []
  openCart: []
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
  toppings?: string[]
}

type CartItem = {
  storeName: string
  menuItemId: number
  name: string
  price: number
  quantity: number
}

const quantities = reactive<Record<number, number>>(
  Object.fromEntries((props.store.products ?? []).map((item) => [item.id, 1])),
)

const menuItems = computed(() => props.store.products ?? [])
const categories = computed(() => [
  'すべて',
  ...Array.from(new Set(menuItems.value.map((item) => item.category))),
])

const filteredMenuItems = computed(() => {
  if (activeCategory.value === 'すべて') {
    return menuItems.value
  }

  return menuItems.value.filter((item) => item.category === activeCategory.value)
})

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

function addToCart(item: MenuItem) {
  if (isSoldOut(item)) {
    return
  }

  const quantity = quantities[item.id] ?? 1

  emit('addCart', {
    storeName: props.store.name,
    menuItemId: item.id,
    name: item.name,
    price: item.price,
    quantity,
  })

  quantities[item.id] = 1
}
</script>

<template>
  <div class="min-h-screen bg-neutral-50 text-neutral-950">
    <AppHeader
      v-model:search-query="searchQuery"
      :is-authenticated="true"
      :cart-count="cartCount"
      @open-cart="emit('openCart')"
      @open-account-section="emit('openAccountSection', $event)"
      @brand-click="emit('back')"
      @logout="emit('logout')"
    />

    <main class="mx-auto w-full max-w-7xl px-5 py-4 md:px-8">
      <button
        class="mb-4 inline-flex items-center gap-2 text-sm font-black text-neutral-600 hover:text-red-700"
        type="button"
        @click="emit('back')"
      >
        <ChevronLeft class="h-4 w-4" />
        店舗一覧へ戻る
      </button>

      <section class="relative min-h-[420px] overflow-hidden rounded-lg bg-neutral-100 px-6 py-16 text-white md:px-9 md:py-24">
        <div class="absolute inset-0">
          <FallbackImage :src="store.imagePath" :alt="`${store.name}の画像`" />
        </div>
        <div class="absolute inset-0 z-[1] bg-gradient-to-t from-black/75 via-black/25 to-transparent" />
        <div class="relative z-[2] flex min-h-[280px] flex-col justify-end">
          <div class="mb-3 flex flex-wrap items-center gap-2">
            <span class="rounded-full bg-red-700 px-3 py-1 text-xs font-black">人気</span>
            <span class="rounded-full bg-amber-500 px-3 py-1 text-xs font-black">高評価</span>
            <span class="text-sm font-black">★ {{ store.rating }} ({{ store.reviews ?? '2,400+' }}レビュー)</span>
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
                  <dd class="text-right leading-6">11:00-15:00<br />17:00-22:00</dd>
                  <dt>土日祝</dt>
                  <dd class="text-right">11:00-22:00</dd>
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

                <div class="mt-4">
                  <p class="text-xs font-black text-neutral-500">人気トッピング</p>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <span
                    v-for="topping in item.toppings"
                      :key="topping"
                      class="rounded bg-neutral-200 px-3 py-1 text-xs font-bold text-neutral-600"
                    >
                      + {{ topping }}
                    </span>
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
    </main>

    <AppFooter />
  </div>
</template>
