
<script setup lang="ts">
import {
  CheckCircle2,
  ChevronRight,
  Clock3,
  MapPin,
  Search,
  ShoppingBag,
  Smartphone,
  Store,
  UtensilsCrossed,
} from 'lucide-vue-next'

const props = defineProps<{
  goTo?: (path: string) => void
}>()

const navigate = (path: string) => {
  if (props.goTo) {
    props.goTo(path)
    return
  }

  window.location.href = path
}

const serviceFeatures = [
  {
    title: '店舗をかんたん検索',
    description: 'エリアや店舗名から、気になるラーメン店をすぐに見つけられます。',
    icon: Search,
  },
  {
    title: 'メニューを見ながら注文',
    description: '商品写真や価格、説明を確認しながら、スマートフォンから注文できます。',
    icon: UtensilsCrossed,
  },
  {
    title: '注文状況を確認',
    description: '注文受付から調理、配送までの状況を画面上で確認できます。',
    icon: Clock3,
  },
]

const usageSteps = [
  {
    number: '01',
    title: '店舗を選ぶ',
    description: '店舗一覧や検索機能から、注文したい店舗を選択します。',
    icon: Store,
  },
  {
    number: '02',
    title: '商品をカートに追加',
    description: 'メニューを確認し、数量やオプションを選んでカートに追加します。',
    icon: ShoppingBag,
  },
  {
    number: '03',
    title: '注文内容を確認',
    description: 'お届け先や注文内容、合計金額を確認して注文を確定します。',
    icon: CheckCircle2,
  },
]
</script>

<template>
  <main class="min-h-screen bg-[#fffaf8] text-neutral-900">
    <!-- サービス紹介ヒーロー -->
    <section class="border-b border-red-100 bg-gradient-to-br from-red-50 via-white to-orange-50">
      <div class="mx-auto grid w-full max-w-7xl gap-10 px-5 py-16 md:px-8 lg:grid-cols-[minmax(0,1fr)_420px] lg:items-center lg:py-24">
        <div>
          <p class="text-sm font-black tracking-[0.18em] text-red-700">ABOUT MEN-NAVI</p>
          <h1 class="mt-4 text-4xl font-black leading-tight tracking-tight text-neutral-950 md:text-5xl">
            お店選びから注文まで、<br class="hidden sm:block" />
            もっとかんたんに。
          </h1>
          <p class="mt-6 max-w-2xl text-base font-bold leading-8 text-neutral-600 md:text-lg">
            麺ナビは、ラーメン店の検索、メニュー確認、オンライン注文をひとつの画面で行えるサービスです。
            気になる店舗を見つけたら、そのまま注文までスムーズに進めます。
          </p>

          <div class="mt-8 flex flex-wrap gap-3">
            <button
              class="inline-flex h-12 items-center gap-2 rounded-lg bg-red-700 px-6 text-sm font-black text-white shadow-sm hover:bg-red-800"
              type="button"
              @click="navigate('/stores')"
            >
              店舗を探す
              <ChevronRight class="h-4 w-4" />
            </button>
            <button
              class="inline-flex h-12 items-center gap-2 rounded-lg border border-red-200 bg-white px-6 text-sm font-black text-red-700 hover:bg-red-50"
              type="button"
              @click="navigate('/contact')"
            >
              お問い合わせ
            </button>
          </div>
        </div>

        <div class="relative overflow-hidden rounded-3xl border border-red-100 bg-white p-8 shadow-xl">
          <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-red-100/70" />
          <div class="absolute -bottom-16 -left-16 h-44 w-44 rounded-full bg-orange-100/70" />

          <div class="relative grid gap-5">
            <div class="flex items-center gap-4 rounded-2xl bg-red-50 p-4">
              <div class="grid h-12 w-12 place-items-center rounded-xl bg-red-700 text-white">
                <Search class="h-6 w-6" />
              </div>
              <div>
                <p class="text-sm font-black text-neutral-900">店舗検索</p>
                <p class="mt-1 text-xs font-bold text-neutral-500">エリア・店舗名から検索</p>
              </div>
            </div>

            <div class="flex items-center gap-4 rounded-2xl border border-red-100 bg-white p-4 shadow-sm">
              <div class="grid h-12 w-12 place-items-center rounded-xl bg-orange-50 text-orange-700">
                <Smartphone class="h-6 w-6" />
              </div>
              <div>
                <p class="text-sm font-black text-neutral-900">スマートフォン注文</p>
                <p class="mt-1 text-xs font-bold text-neutral-500">いつでもかんたん操作</p>
              </div>
            </div>

            <div class="flex items-center gap-4 rounded-2xl border border-red-100 bg-white p-4 shadow-sm">
              <div class="grid h-12 w-12 place-items-center rounded-xl bg-green-50 text-green-700">
                <MapPin class="h-6 w-6" />
              </div>
              <div>
                <p class="text-sm font-black text-neutral-900">店舗情報を確認</p>
                <p class="mt-1 text-xs font-bold text-neutral-500">所在地・予算・メニュー</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- サービスの特徴 -->
    <section class="mx-auto w-full max-w-7xl px-5 py-16 md:px-8 lg:py-20">
      <div class="max-w-2xl">
        <p class="text-sm font-black tracking-[0.16em] text-red-700">FEATURES</p>
        <h2 class="mt-3 text-3xl font-black text-neutral-950">麺ナビでできること</h2>
        <p class="mt-4 text-sm font-bold leading-7 text-neutral-600 md:text-base">
          店舗探しから注文完了まで、迷わず操作できるシンプルな構成を目指しています。
        </p>
      </div>

      <div class="mt-10 grid gap-5 md:grid-cols-3">
        <article
          v-for="feature in serviceFeatures"
          :key="feature.title"
          class="rounded-2xl border border-red-100 bg-white p-6 shadow-sm"
        >
          <div class="grid h-12 w-12 place-items-center rounded-xl bg-red-50 text-red-700">
            <component :is="feature.icon" class="h-6 w-6" />
          </div>
          <h3 class="mt-5 text-lg font-black text-neutral-900">{{ feature.title }}</h3>
          <p class="mt-3 text-sm font-bold leading-7 text-neutral-600">{{ feature.description }}</p>
        </article>
      </div>
    </section>

    <!-- ご利用の流れ -->
    <section class="border-y border-red-100 bg-white">
      <div class="mx-auto w-full max-w-7xl px-5 py-16 md:px-8 lg:py-20">
        <div class="max-w-2xl">
          <p class="text-sm font-black tracking-[0.16em] text-red-700">HOW TO USE</p>
          <h2 class="mt-3 text-3xl font-black text-neutral-950">ご利用の流れ</h2>
        </div>

        <div class="mt-10 grid gap-5 lg:grid-cols-3">
          <article
            v-for="step in usageSteps"
            :key="step.number"
            class="relative overflow-hidden rounded-2xl border border-red-100 bg-[#fffaf8] p-6"
          >
            <span class="absolute right-5 top-4 text-5xl font-black text-red-100">{{ step.number }}</span>
            <div class="relative">
              <div class="grid h-12 w-12 place-items-center rounded-xl bg-red-700 text-white">
                <component :is="step.icon" class="h-6 w-6" />
              </div>
              <h3 class="mt-5 text-lg font-black text-neutral-900">{{ step.title }}</h3>
              <p class="mt-3 text-sm font-bold leading-7 text-neutral-600">{{ step.description }}</p>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="mx-auto w-full max-w-7xl px-5 py-16 md:px-8 lg:py-20">
      <div class="overflow-hidden rounded-3xl bg-red-700 px-6 py-10 text-white shadow-xl md:px-10 md:py-12">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-sm font-black tracking-[0.14em] text-white/75">START ORDER</p>
            <h2 class="mt-3 text-3xl font-black">気になる店舗を探してみましょう</h2>
            <p class="mt-3 max-w-2xl text-sm font-bold leading-7 text-white/85">
              店舗情報とメニューを確認して、そのままオンラインで注文できます。
            </p>
          </div>

          <button
            class="inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-lg bg-white px-6 text-sm font-black text-red-700 hover:bg-red-50"
            type="button"
            @click="navigate('/stores')"
          >
            店舗一覧を見る
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>
      </div>
    </section>
  </main>
</template>