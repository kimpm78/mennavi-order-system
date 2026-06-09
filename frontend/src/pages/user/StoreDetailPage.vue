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
const categories = ['すべて', 'メイン', 'トッピング', 'サイド', 'ドリンク & お酒']

const menuItems = [
  {
    id: 1,
    name: '特製濃厚醤油ラーメン',
    category: 'メイン',
    price: 1280,
    badge: 'Best Seller',
    imagePath: null,
    imageClass: 'ramen-photo ramen-photo-bowl',
    description:
      '当店自慢のスープは、鶏と魚介を12時間じっくり煮込んで仕上げたものです。最後に、地元の熟成醤油をブレンドし、深みのある味わいに仕上げています。',
    toppings: ['追加チャーシュー（+250円）', '味玉追加（+150円）'],
  },
  {
    id: 2,
    name: '昔ながらの淡麗醤油ラーメン',
    category: 'メイン',
    price: 980,
    imagePath: null,
    imageClass: 'ramen-photo ramen-photo-clear',
    description:
      'あっさりとした伝統的な一杯。澄んだ鶏清湯スープに、魚介だしの風味をほんのり加え、すっきりしながらも旨みのある味わいに仕上げました。',
    toppings: ['メンマ（100円）', '海苔（100円）'],
  },
  {
    id: 3,
    name: '生姜香る旨辛ラーメン',
    category: 'メイン',
    price: 1100,
    imagePath: null,
    imageClass: 'ramen-photo ramen-photo-spicy',
    description:
      '体が温まる、刺激的な一杯。澄んだ醤油スープに生姜エキスを加え、自家製ラー油でピリッとした辛さに仕上げました。',
    toppings: ['辛さレベル2（+50円）', 'パクチー追加（+150円）'],
  },
  {
    id: 4,
    name: '特製濃厚つけ麺',
    category: 'メイン',
    price: 1350,
    imagePath: null,
    imageClass: 'ramen-photo ramen-photo-noodle',
    description:
      '太くもちもちとした麺を冷たい状態で提供し、熱々の濃厚つけ汁につけてお召し上がりいただきます。旨みが凝縮された、食べ応えのある一杯です。',
    toppings: ['麺大盛り（+150円）', 'つけ汁追加（+200円）'],
  },
]

type MenuItem = (typeof menuItems)[number]

type CartItem = {
  storeName: string
  menuItemId: number
  name: string
  price: number
  quantity: number
}

const quantities = reactive<Record<number, number>>(
  Object.fromEntries(menuItems.map((item) => [item.id, 1])),
)

const filteredMenuItems = computed(() => {
  if (activeCategory.value === 'すべて') {
    return menuItems
  }

  return menuItems.filter((item) => item.category === activeCategory.value)
})

function updateQuantity(itemId: number, amount: number) {
  quantities[itemId] = Math.max(1, quantities[itemId] + amount)
}

function formatPrice(price: number) {
  return `${price.toLocaleString('ja-JP')}円`
}

function addToCart(item: MenuItem) {
  emit('addCart', {
    storeName: props.store.name,
    menuItemId: item.id,
    name: item.name,
    price: item.price,
    quantity: quantities[item.id],
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
                  1-2-3 神南, 渋谷区, 東京都
                </p>
                <div class="store-map mt-3">
                  <button
                    class="absolute bottom-3 right-3 z-[2] inline-flex items-center gap-1 rounded-full bg-white px-4 py-2 text-xs font-black text-neutral-800 shadow-sm"
                    type="button"
                  >
                    <ExternalLink class="h-3.5 w-3.5" />
                    地図を開く
                  </button>
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
                  <div class="inline-flex items-center rounded-full bg-neutral-100 px-3 py-2">
                    <button
                      class="grid h-7 w-7 place-items-center rounded-full font-black text-neutral-700 hover:bg-white"
                      type="button"
                      @click="updateQuantity(item.id, -1)"
                    >
                      -
                    </button>
                    <span class="w-9 text-center text-sm font-black">{{ quantities[item.id] }}</span>
                    <button
                      class="grid h-7 w-7 place-items-center rounded-full font-black text-red-700 hover:bg-white"
                      type="button"
                      @click="updateQuantity(item.id, 1)"
                    >
                      +
                    </button>
                  </div>

                  <button
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-red-700 px-6 text-sm font-black text-white hover:bg-red-800"
                    type="button"
                    @click="addToCart(item)"
                  >
                    <ShoppingBag class="h-4 w-4" />
                    カートに追加
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
