<script setup lang="ts">
import { CheckCircle2, ClipboardList, Home } from 'lucide-vue-next'

const emit = defineEmits<{
  top: []
  orderHistory: []
}>()
</script>

<template>
  <main class="mx-auto grid min-h-[calc(100vh-220px)] w-full max-w-4xl place-items-center px-5 py-16 md:px-8">
    <section class="w-full rounded-lg border border-red-200 bg-white px-6 py-12 text-center shadow-sm md:px-10">
      <div class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-red-50 text-red-700">
        <CheckCircle2 class="h-11 w-11" :stroke-width="2.3" />
      </div>

      <p class="mt-6 text-sm font-black text-red-700">ORDER COMPLETED</p>
      <h1 class="mt-2 text-3xl font-black tracking-normal text-neutral-950 md:text-4xl">
        ご注文が完了しました
      </h1>
      <p class="mx-auto mt-4 max-w-xl text-sm font-bold leading-7 text-neutral-600 md:text-base">
        ご注文ありがとうございます。店舗で注文内容を確認し、調理状況を更新します。
        注文履歴から今回の注文内容を確認できます。
      </p>

      <div class="mx-auto mt-9 grid max-w-md gap-3 sm:grid-cols-2">
        <button
          class="inline-flex h-12 items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-5 text-sm font-black text-red-700 hover:bg-red-50"
          type="button"
          @click="emit('top')"
        >
          <Home class="h-4 w-4" />
          トップへ
        </button>
        <button
          class="inline-flex h-12 items-center justify-center gap-2 rounded-lg bg-red-700 px-5 text-sm font-black text-white hover:bg-red-800"
          type="button"
          @click="emit('orderHistory')"
        >
          <ClipboardList class="h-4 w-4" />
          注文履歴へ
        </button>
      </div>
    </section>
  </main>
</template>
