
<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import {
  CheckCircle2,
  ChevronRight,
  Clock3,
  Mail,
  MessageSquareText,
  Phone,
  Send,
} from 'lucide-vue-next'
import { apiRequest, authHeaders } from '../../lib/api'
import { getCustomerToken } from '../../lib/authStorage'

const props = defineProps<{
  goTo?: (path: string) => void
}>()

const form = reactive({
  name: '',
  email: '',
  category: '',
  orderNumber: '',
  message: '',
  agreed: false,
})

const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const inquiryCategories = [
  '注文について',
  '配送について',
  '決済について',
  '店舗・メニューについて',
  '会員情報について',
  'その他',
]

const canSubmit = computed(() => {
  return Boolean(
    form.name.trim() &&
    form.email.trim() &&
    form.category &&
    form.message.trim() &&
    form.agreed,
  )
})

const navigate = (path: string) => {
  if (props.goTo) {
    props.goTo(path)
    return
  }

  window.location.href = path
}

const submitContact = async () => {
  errorMessage.value = ''
  successMessage.value = ''

  if (!canSubmit.value) {
    errorMessage.value = '必須項目を入力し、プライバシーポリシーに同意してください。'
    return
  }

  loading.value = true

  try {
    const token = getCustomerToken()

    await apiRequest('/contact-messages', {
      method: 'POST',
      headers: token ? authHeaders(token) : undefined,
      body: JSON.stringify({
        name: form.name.trim(),
        email: form.email.trim(),
        category: form.category,
        order_number: form.orderNumber.trim() || null,
        message: form.message.trim(),
      }),
    })

    successMessage.value = 'お問い合わせを受け付けました。内容を確認のうえ、担当者よりご連絡いたします。'
    form.name = ''
    form.email = ''
    form.category = ''
    form.orderNumber = ''
    form.message = ''
    form.agreed = false
  } catch {
    errorMessage.value = '送信に失敗しました。時間をおいて再度お試しください。'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-[#fffaf8] text-neutral-900">
    <!-- ページ見出し -->
    <section class="border-b border-red-100 bg-gradient-to-br from-red-50 via-white to-orange-50">
      <div class="mx-auto w-full max-w-7xl px-5 py-14 md:px-8 lg:py-18">
        <p class="text-sm font-black tracking-[0.18em] text-red-700">CONTACT</p>
        <h1 class="mt-3 text-4xl font-black tracking-tight text-neutral-950">お問い合わせ</h1>
        <p class="mt-5 max-w-2xl text-base font-bold leading-8 text-neutral-600">
          ご注文、配送、決済、店舗情報などに関するお問い合わせを受け付けています。
          下記フォームに必要事項をご入力ください。
        </p>
      </div>
    </section>

    <section class="mx-auto grid w-full max-w-7xl gap-8 px-5 py-12 md:px-8 lg:grid-cols-[minmax(0,1fr)_340px] lg:py-16">
      <!-- お問い合わせフォーム -->
      <section class="min-w-0 rounded-2xl border border-red-100 bg-white p-6 shadow-sm md:p-8">
        <div class="flex items-center gap-3 border-b border-red-100 pb-5">
          <div class="grid h-11 w-11 place-items-center rounded-xl bg-red-50 text-red-700">
            <MessageSquareText class="h-5 w-5" />
          </div>
          <div>
            <h2 class="text-xl font-black text-neutral-900">お問い合わせフォーム</h2>
            <p class="mt-1 text-xs font-bold text-neutral-500">「必須」の項目は必ずご入力ください。</p>
          </div>
        </div>

        <form class="mt-6 grid gap-5" @submit.prevent="submitContact">
          <label class="grid gap-2 text-sm font-black text-[#5c4644]">
            お名前 <span class="text-red-700">必須</span>
            <input
              v-model="form.name"
              class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-neutral-50 px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:bg-white focus:ring-2 focus:ring-red-100"
              autocomplete="name"
              maxlength="100"
              placeholder="例：山田 太郎"
              required
              type="text"
            />
          </label>

          <label class="grid gap-2 text-sm font-black text-[#5c4644]">
            メールアドレス <span class="text-red-700">必須</span>
            <input
              v-model="form.email"
              class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-neutral-50 px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:bg-white focus:ring-2 focus:ring-red-100"
              autocomplete="email"
              maxlength="255"
              placeholder="example@email.com"
              required
              type="email"
            />
          </label>

          <label class="grid gap-2 text-sm font-black text-[#5c4644]">
            お問い合わせ種別 <span class="text-red-700">必須</span>
            <span class="relative block">
              <select
                v-model="form.category"
                class="h-12 w-full min-w-0 appearance-none rounded-lg border border-red-200 bg-neutral-50 px-4 pr-12 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:bg-white focus:ring-2 focus:ring-red-100"
                required
              >
                <option disabled value="">選択してください</option>
                <option v-for="category in inquiryCategories" :key="category" :value="category">
                  {{ category }}
                </option>
              </select>
              <ChevronRight
                class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 rotate-90 text-neutral-500"
                aria-hidden="true"
              />
            </span>
          </label>

          <label class="grid gap-2 text-sm font-black text-[#5c4644]">
            注文番号 <span class="text-xs text-neutral-500">任意</span>
            <input
              v-model="form.orderNumber"
              class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-neutral-50 px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:bg-white focus:ring-2 focus:ring-red-100"
              maxlength="50"
              placeholder="例：88219"
              type="text"
            />
          </label>

          <label class="grid gap-2 text-sm font-black text-[#5c4644]">
            お問い合わせ内容 <span class="text-red-700">必須</span>
            <textarea
              v-model="form.message"
              class="min-h-44 w-full min-w-0 rounded-lg border border-red-200 bg-neutral-50 px-4 py-3 text-sm font-bold leading-7 text-neutral-900 outline-none focus:border-red-400 focus:bg-white focus:ring-2 focus:ring-red-100"
              maxlength="2000"
              placeholder="お問い合わせ内容をご入力ください。"
              required
            />
            <span class="text-right text-xs font-bold text-neutral-400">
              {{ form.message.length }} / 2000
            </span>
          </label>

          <label class="flex items-start gap-3 rounded-xl border border-red-100 bg-red-50 px-4 py-4 text-sm font-bold leading-6 text-neutral-700">
            <input
              v-model="form.agreed"
              class="mt-1 h-5 w-5 shrink-0 accent-red-700"
              type="checkbox"
            />
            <span>
              <button
                class="font-black text-red-700 underline underline-offset-2 hover:text-red-800"
                type="button"
                @click="navigate('/privacy')"
              >
                プライバシーポリシー
              </button>
              を確認し、個人情報の取り扱いに同意します。
            </span>
          </label>

          <p
            v-if="errorMessage"
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-black text-red-700"
            role="alert"
          >
            {{ errorMessage }}
          </p>

          <p
            v-if="successMessage"
            class="flex items-start gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-black leading-6 text-green-700"
            role="status"
          >
            <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0" />
            {{ successMessage }}
          </p>

          <div class="flex justify-end">
            <button
              class="inline-flex h-12 items-center justify-center gap-2 rounded-lg bg-red-700 px-7 text-sm font-black text-white shadow-sm hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="loading || !canSubmit"
              type="submit"
            >
              <Send class="h-4 w-4" />
              {{ loading ? '送信中...' : 'お問い合わせを送信' }}
            </button>
          </div>
        </form>
      </section>

      <!-- お問い合わせ案内 -->
      <aside class="grid content-start gap-5">
        <section class="rounded-2xl border border-red-100 bg-white p-6 shadow-sm">
          <h2 class="text-lg font-black text-neutral-900">お問い合わせ前の確認</h2>
          <p class="mt-3 text-sm font-bold leading-7 text-neutral-600">
            注文に関するお問い合わせの場合は、注文履歴に表示されている注文番号をご入力ください。
          </p>
          <button
            class="mt-5 inline-flex items-center gap-2 text-sm font-black text-red-700 hover:text-red-800"
            type="button"
            @click="navigate('/orders')"
          >
            注文履歴を確認する
            <ChevronRight class="h-4 w-4" />
          </button>
        </section>

        <section class="rounded-2xl border border-red-100 bg-white p-6 shadow-sm">
          <div class="flex items-center gap-3">
            <div class="grid h-10 w-10 place-items-center rounded-xl bg-red-50 text-red-700">
              <Clock3 class="h-5 w-5" />
            </div>
            <h2 class="text-lg font-black text-neutral-900">受付時間</h2>
          </div>
          <p class="mt-4 text-sm font-bold leading-7 text-neutral-600">
            平日 10:00〜18:00<br />
            土日・祝日、年末年始を除く
          </p>
          <p class="mt-3 text-xs font-bold leading-5 text-neutral-500">
            受付時間外のお問い合わせは、翌営業日以降に順次対応します。
          </p>
        </section>

        <section class="rounded-2xl border border-red-100 bg-red-700 p-6 text-white shadow-sm">
          <h2 class="text-lg font-black">その他の連絡方法</h2>
          <div class="mt-5 grid gap-4 text-sm font-bold">
            <div class="flex items-start gap-3">
              <Mail class="mt-0.5 h-5 w-5 shrink-0" />
              <div>
                <p class="font-black">メール</p>
                <p class="mt-1 text-white/80">support@example.com</p>
              </div>
            </div>
            <div class="flex items-start gap-3">
              <Phone class="mt-0.5 h-5 w-5 shrink-0" />
              <div>
                <p class="font-black">電話</p>
                <p class="mt-1 text-white/80">03-0000-0000</p>
              </div>
            </div>
          </div>
          <p class="mt-5 text-xs font-bold leading-5 text-white/70">
            ※ 上記の連絡先はサンプルです。公開前に実際の連絡先へ変更してください。
          </p>
        </section>
      </aside>
    </section>
  </main>
</template>
