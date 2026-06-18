

<script setup lang="ts">
import {
  ArrowLeft,
  BadgeCheck,
  Check,
  CreditCard,
  Crown,
  PackageCheck,
  Percent,
  Truck,
  X,
} from 'lucide-vue-next'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { apiRequest, authHeaders } from '@/lib/api'
import { getCustomerToken } from '@/lib/authStorage'

type SubscriptionStatus = 'pending' | 'active' | 'canceled' | 'expired' | 'payment_failed'

type PlusPlan = {
  name: string
  price: number
  currency: string
  discountRate: number
  freeDelivery: boolean
}

type UserSubscription = {
  status: SubscriptionStatus
  currentPeriodEnd?: string | null
  cancelAtPeriodEnd?: boolean
}

type SubscriptionApiItem = {
  status?: SubscriptionStatus
  current_period_end?: string | null
  cancel_at_period_end?: boolean
}

type SubscriptionApiResponse = {
  message?: string
  subscription?: SubscriptionApiItem | null
}

type PaymentMethod = {
  id: number
  provider: string
  brand?: string | null
  last4?: string | null
  exp_month?: number | null
  exp_year?: number | null
  is_default: boolean
}

type PayjpElement = {
  mount: (selector: string) => void
  unmount: () => void
}

type PayjpInstance = {
  elements: () => {
    create: (type: 'card', options?: Record<string, unknown>) => PayjpElement
  }
  createToken: (
    element: PayjpElement,
    data?: Record<string, unknown>,
  ) => Promise<{ id?: string; error?: { message?: string } }>
}

declare global {
  interface Window {
    Payjp?: (publicKey: string, options?: Record<string, unknown>) => PayjpInstance
  }
}

const props = withDefaults(
  defineProps<{
    plan?: PlusPlan
    subscription?: UserSubscription | null
    processing?: boolean
  }>(),
  {
    plan: () => ({
      name: '麺ナビ Plus',
      price: 980,
      currency: 'JPY',
      discountRate: 15,
      freeDelivery: true,
    }),
    subscription: null,
    processing: false,
  },
)

const emit = defineEmits<{
  back: []
  subscriptionUpdated: [subscription: SubscriptionApiItem | null]
}>()

// 画面内で使用する契約情報を保持
const localSubscription = ref<UserSubscription | null>(props.subscription)

// API処理中の状態を保持
const submitting = ref(false)

// API実行結果のメッセージを保持
const successMessage = ref('')
const errorMessage = ref('')
const isCancelConfirmOpen = ref(false)
const isPaymentModalOpen = ref(false)
const isSuccessModalOpen = ref(false)
const saveAsDefault = ref(true)
const paymentMethods = ref<PaymentMethod[]>([])
let payjpInstance: PayjpInstance | null = null
let payjpCardElement: PayjpElement | null = null
let payjpScriptPromise: Promise<void> | null = null

// 親コンポーネントから契約情報が更新された場合に同期
watch(
  () => props.subscription,
  (subscription) => {
    localSubscription.value = subscription
  },
)

onMounted(() => {
  loadSubscription()
  loadPaymentMethods()
})

onBeforeUnmount(() => {
  unmountPayjpCard()
})

// 親コンポーネントと画面内の処理状態をまとめて判定
const isProcessing = computed(() => props.processing || submitting.value)

// 現在の契約が有効か判定
const isActive = computed(() => localSubscription.value?.status === 'active')

// 契約終了時の解約予約が設定されているか判定
const isCancelScheduled = computed(
  () => isActive.value && Boolean(localSubscription.value?.cancelAtPeriodEnd),
)

function applySubscription(subscription?: SubscriptionApiItem | null): void {
  localSubscription.value = subscription
    ? {
        status: subscription.status ?? 'active',
        currentPeriodEnd: subscription.current_period_end ?? null,
        cancelAtPeriodEnd: subscription.cancel_at_period_end ?? false,
      }
    : null

  emit('subscriptionUpdated', subscription ?? null)
}

function normalizePayjpError(message?: string): string {
  if (!message) {
    return 'カード情報を確認してください。'
  }

  if (message.includes('invalid') || message.includes('format')) {
    return 'カード情報の入力形式が正しくありません。テストカード番号・有効期限・CVCを確認してください。'
  }

  return message
}

// 契約状態に応じた表示文言を返す
const statusLabel = computed(() => {
  switch (localSubscription.value?.status) {
    case 'active':
      return isCancelScheduled.value ? '解約予約中' : '利用中'
    case 'pending':
      return '手続き中'
    case 'canceled':
      return '解約済み'
    case 'expired':
      return '期限切れ'
    case 'payment_failed':
      return '決済失敗'
    default:
      return '未加入'
  }
})

// 契約状態に応じた補足メッセージを返す
const statusMessage = computed(() => {
  if (isCancelScheduled.value) {
    return '現在の利用期間終了後に自動で解約されます。'
  }

  switch (localSubscription.value?.status) {
    case 'active':
      return '麺ナビ Plusの特典が注文時に自動で適用されます。'
    case 'pending':
      return '決済処理の完了を確認しています。'
    case 'payment_failed':
      return '決済方法を確認して、もう一度お申し込みください。'
    case 'canceled':
    case 'expired':
      return '再度お申し込みいただくと、特典を利用できます。'
    default:
      return '月額プランに加入すると、お得な特典を利用できます。'
  }
})

// 金額を日本円形式で表示
function formatPrice(value: number): string {
  return new Intl.NumberFormat('ja-JP', {
    style: 'currency',
    currency: props.plan.currency,
    maximumFractionDigits: 0,
  }).format(value)
}

// 日付を日本語形式で表示
function formatDate(value?: string | null): string {
  if (!value) {
    return '未定'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return '未定'
  }

  return new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(date)
}

function openPaymentModal(): void {
  if (isProcessing.value) {
    return
  }

  successMessage.value = ''
  errorMessage.value = ''
  isPaymentModalOpen.value = true
  saveAsDefault.value = true

  nextTick(() => {
    mountPayjpCard()
  })
}

function closePaymentModal(): void {
  if (isProcessing.value) {
    return
  }

  isPaymentModalOpen.value = false
  unmountPayjpCard()
}

function closeSuccessModal(): void {
  isSuccessModalOpen.value = false
}

function loadPayjpScript(): Promise<void> {
  if (window.Payjp) {
    return Promise.resolve()
  }

  if (payjpScriptPromise) {
    return payjpScriptPromise
  }

  payjpScriptPromise = new Promise((resolve, reject) => {
    const existingScript = document.querySelector<HTMLScriptElement>('script[src="https://js.pay.jp/v2/pay.js"]')

    if (existingScript) {
      existingScript.addEventListener('load', () => resolve(), { once: true })
      existingScript.addEventListener('error', () => reject(new Error('payjp.js load failed')), { once: true })
      return
    }

    const script = document.createElement('script')
    script.src = 'https://js.pay.jp/v2/pay.js'
    script.async = true
    script.onload = () => resolve()
    script.onerror = () => reject(new Error('payjp.js load failed'))
    document.head.appendChild(script)
  })

  return payjpScriptPromise
}

async function mountPayjpCard(): Promise<boolean> {
  if (payjpCardElement) {
    return true
  }

  const publicKey = import.meta.env.VITE_PAYJP_PUBLIC_KEY
  if (!publicKey) {
    errorMessage.value = 'PAY.JPの公開鍵が設定されていません。'
    return false
  }

  try {
    await loadPayjpScript()
  } catch {
    errorMessage.value = 'PAY.JPの決済フォームを読み込めませんでした。ネットワーク接続を確認してください。'
    return false
  }

  if (!window.Payjp) {
    errorMessage.value = 'PAY.JPの決済フォームを初期化できませんでした。'
    return false
  }

  payjpInstance = window.Payjp(publicKey, { locale: 'ja' })
  payjpCardElement = payjpInstance.elements().create('card', {
    style: {
      base: {
        color: '#171717',
        fontSize: '15px',
      },
    },
  })
  payjpCardElement.mount('#plus-payjp-modal-card')
  return true
}

function unmountPayjpCard(): void {
  if (!payjpCardElement) {
    return
  }

  payjpCardElement.unmount()
  payjpCardElement = null
  payjpInstance = null
}

async function loadPaymentMethods(): Promise<void> {
  const token = getCustomerToken()
  if (!token) {
    return
  }

  try {
    const response = await apiRequest<{
      payment_methods: PaymentMethod[]
      default_payment_method: PaymentMethod | null
    }>('/payment-methods', {
      headers: authHeaders(token),
    })

    paymentMethods.value = response.payment_methods
  } catch {
    paymentMethods.value = []
  }
}

// 麺ナビ Plusへ申し込む
async function subscribePlus(paymentMethodId: number): Promise<void> {
  const token = getCustomerToken()
  if (!token) {
    throw new Error('ログインが必要です。')
  }

  const data = await apiRequest<SubscriptionApiResponse>('/me/subscription', {
    method: 'POST',
    headers: authHeaders(token),
    body: JSON.stringify({
      plan_code: 'mennavi_plus',
      payment_method_id: paymentMethodId,
    }),
  })

  applySubscription(data.subscription ?? null)
  successMessage.value = data.message ?? '麺ナビ Plusへの申し込みが完了しました。'
  isSuccessModalOpen.value = true
}

async function submitPlusPayment(): Promise<void> {
  if (isProcessing.value) {
    return
  }

  submitting.value = true
  successMessage.value = ''
  errorMessage.value = ''

  try {
    const token = getCustomerToken()
    if (!token) {
      throw new Error('ログインが必要です。')
    }

    if (!payjpInstance || !payjpCardElement) {
      await nextTick()
      const mounted = await mountPayjpCard()
      if (!mounted || !payjpInstance || !payjpCardElement) {
        return
      }
    }

    const tokenResponse = await payjpInstance.createToken(payjpCardElement)
    if (tokenResponse.error || !tokenResponse.id) {
      throw new Error(normalizePayjpError(tokenResponse.error?.message))
    }

    const paymentMethodResponse = await apiRequest<{ payment_method: PaymentMethod }>('/payment-methods', {
      method: 'POST',
      headers: authHeaders(token),
      body: JSON.stringify({
        payjp_token: tokenResponse.id,
        is_default: saveAsDefault.value,
      }),
    })

    paymentMethods.value = [
      paymentMethodResponse.payment_method,
      ...paymentMethods.value
        .filter((method) => method.id !== paymentMethodResponse.payment_method.id)
        .map((method) => ({
          ...method,
          is_default: saveAsDefault.value ? false : method.is_default,
        })),
    ]

    await subscribePlus(paymentMethodResponse.payment_method.id)
    isPaymentModalOpen.value = false
    unmountPayjpCard()
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error
      ? error.message
      : '決済に失敗しました。'
  } finally {
    submitting.value = false
  }
}

async function loadSubscription(): Promise<void> {
  const token = getCustomerToken()
  if (!token) {
    return
  }

  try {
    const data = await apiRequest<SubscriptionApiResponse>('/me/subscription', {
      headers: authHeaders(token),
    })

    applySubscription(data.subscription ?? null)
  } catch {
    applySubscription(null)
  }
}

function openCancelConfirm(): void {
  successMessage.value = ''
  errorMessage.value = ''
  isCancelConfirmOpen.value = true
}

function closeCancelConfirm(): void {
  if (isProcessing.value) {
    return
  }

  isCancelConfirmOpen.value = false
}

async function cancelSubscription(): Promise<void> {
  if (isProcessing.value) {
    return
  }

  submitting.value = true
  successMessage.value = ''
  errorMessage.value = ''

  try {
    const token = getCustomerToken()
    if (!token) {
      throw new Error('ログインが必要です。')
    }

    const data = await apiRequest<SubscriptionApiResponse>('/me/subscription/cancel', {
      method: 'PATCH',
      headers: authHeaders(token),
    })

    applySubscription({
      status: data.subscription?.status ?? 'active',
      current_period_end: data.subscription?.current_period_end ?? localSubscription.value?.currentPeriodEnd ?? null,
      cancel_at_period_end: data.subscription?.cancel_at_period_end ?? true,
    })

    successMessage.value = data.message ?? '現在の利用期間終了時の解約を受け付けました。'
    isCancelConfirmOpen.value = false
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error
      ? error.message
      : '解約手続きに失敗しました。'
  } finally {
    submitting.value = false
  }
}

async function resumeSubscription(): Promise<void> {
  if (isProcessing.value) {
    return
  }

  submitting.value = true
  successMessage.value = ''
  errorMessage.value = ''

  try {
    const token = getCustomerToken()
    if (!token) {
      throw new Error('ログインが必要です。')
    }

    const data = await apiRequest<SubscriptionApiResponse>('/me/subscription/resume', {
      method: 'PATCH',
      headers: authHeaders(token),
    })

    applySubscription({
      status: data.subscription?.status ?? 'active',
      current_period_end: data.subscription?.current_period_end ?? localSubscription.value?.currentPeriodEnd ?? null,
      cancel_at_period_end: data.subscription?.cancel_at_period_end ?? false,
    })

    successMessage.value = data.message ?? '解約予約を取り消しました。'
  } catch (error: unknown) {
    errorMessage.value = error instanceof Error
      ? error.message
      : '解約予約の取り消しに失敗しました。'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-neutral-50">
    <section class="border-b border-neutral-200 bg-white">
      <div class="mx-auto max-w-6xl px-5 py-5 md:px-8">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-sm font-black text-neutral-600 transition hover:text-red-700"
          @click="emit('back')"
        >
          <ArrowLeft class="h-4 w-4" aria-hidden="true" />
          戻る
        </button>
      </div>
    </section>

    <section class="bg-linear-to-br from-red-800 via-red-700 to-red-900 text-white">
      <div class="mx-auto grid max-w-6xl gap-10 px-5 py-16 md:px-8 lg:grid-cols-[1.2fr_0.8fr] lg:items-center lg:py-20">
        <div>
          <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-black backdrop-blur">
            <Crown class="h-4 w-4" aria-hidden="true" />
            麺ナビ会員限定プラン
          </span>

          <h1 class="mt-6 text-4xl font-black tracking-tight sm:text-5xl">
            {{ plan.name }}
          </h1>

          <p class="mt-5 max-w-2xl text-base font-bold leading-8 text-red-50 sm:text-lg">
            ラーメンをもっとお得に、もっと気軽に。配送料無料と注文割引を毎回利用できる月額プランです。
          </p>

          <div class="mt-8 flex flex-wrap items-end gap-3">
            <span class="text-5xl font-black">{{ formatPrice(plan.price) }}</span>
            <span class="pb-1 text-sm font-black text-red-100">/ 月</span>
          </div>
        </div>

        <div class="rounded-2xl bg-white p-6 text-neutral-900 shadow-2xl sm:p-8">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-sm font-black text-neutral-500">現在のステータス</p>
              <p class="mt-2 text-2xl font-black">{{ statusLabel }}</p>
            </div>
            <BadgeCheck class="h-10 w-10 text-red-700" aria-hidden="true" />
          </div>

          <p class="mt-4 text-sm font-bold leading-6 text-neutral-600">
            {{ statusMessage }}
          </p>

          <div v-if="isActive" class="mt-6 rounded-xl bg-neutral-50 p-4">
            <p class="text-xs font-black text-neutral-500">現在の利用期限</p>
            <p class="mt-1 text-base font-black text-neutral-900">
              {{ formatDate(localSubscription?.currentPeriodEnd) }}
            </p>
          </div>

          <button
            v-if="!isActive"
            type="button"
            class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-red-700 px-6 py-4 text-base font-black text-white transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="isProcessing"
            @click="openPaymentModal"
          >
            <CreditCard class="h-5 w-5" aria-hidden="true" />
            {{ isProcessing ? '処理中...' : '麺ナビ Plusに申し込む' }}
          </button>

          <button
            v-else-if="isCancelScheduled"
            type="button"
            class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-red-700 px-6 py-4 text-base font-black text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="isProcessing"
            @click="resumeSubscription"
          >
            {{ isProcessing ? '処理中...' : '解約予約を取り消す' }}
          </button>

          <button
            v-else
            type="button"
            class="mt-6 inline-flex w-full items-center justify-center rounded-xl border border-neutral-300 px-6 py-4 text-base font-black text-neutral-700 transition hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="isProcessing"
            @click="openCancelConfirm"
          >
            {{ isProcessing ? '処理中...' : '解約する' }}
          </button>

          <p
            v-if="successMessage"
            class="mt-4 rounded-lg border border-green-100 bg-green-50 px-4 py-3 text-sm font-black text-green-700"
          >
            {{ successMessage }}
          </p>

          <p
            v-if="errorMessage"
            class="mt-4 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm font-black text-red-700"
          >
            {{ errorMessage }}
          </p>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-5 py-14 md:px-8 lg:py-16">
      <div class="text-center">
        <p class="text-sm font-black text-red-700">MEMBER BENEFITS</p>
        <h2 class="mt-3 text-3xl font-black tracking-tight text-neutral-900">
          麺ナビ Plusの特典
        </h2>
      </div>

      <div class="mt-10 grid gap-6 md:grid-cols-3">
        <article class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
          <div class="grid h-12 w-12 place-items-center rounded-xl bg-red-50 text-red-700">
            <Truck class="h-6 w-6" aria-hidden="true" />
          </div>
          <h3 class="mt-5 text-xl font-black text-neutral-900">配送料無料</h3>
          <p class="mt-3 text-sm font-bold leading-7 text-neutral-600">
            対象店舗の配送料が無料になります。注文回数を気にせず利用できます。
          </p>
        </article>

        <article class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
          <div class="grid h-12 w-12 place-items-center rounded-xl bg-red-50 text-red-700">
            <Percent class="h-6 w-6" aria-hidden="true" />
          </div>
          <h3 class="mt-5 text-xl font-black text-neutral-900">注文ごとに{{ plan.discountRate }}%割引</h3>
          <p class="mt-3 text-sm font-bold leading-7 text-neutral-600">
            商品小計から会員割引を自動適用します。クーポン入力は必要ありません。
          </p>
        </article>

        <article class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
          <div class="grid h-12 w-12 place-items-center rounded-xl bg-red-50 text-red-700">
            <PackageCheck class="h-6 w-6" aria-hidden="true" />
          </div>
          <h3 class="mt-5 text-xl font-black text-neutral-900">自動で特典適用</h3>
          <p class="mt-3 text-sm font-bold leading-7 text-neutral-600">
            注文確認時に対象の割引と配送料無料が自動で反映されます。
          </p>
        </article>
      </div>
    </section>

    <section class="border-y border-neutral-200 bg-white">
      <div class="mx-auto max-w-4xl px-5 py-14 md:px-8">
        <h2 class="text-center text-3xl font-black text-neutral-900">ご利用について</h2>

        <ul class="mt-8 space-y-4">
          <li class="flex gap-3 rounded-xl bg-neutral-50 p-4 text-sm font-bold leading-6 text-neutral-700">
            <Check class="mt-0.5 h-5 w-5 shrink-0 text-red-700" aria-hidden="true" />
            お申し込み完了後から、対象注文に特典が適用されます。
          </li>
          <li class="flex gap-3 rounded-xl bg-neutral-50 p-4 text-sm font-bold leading-6 text-neutral-700">
            <Check class="mt-0.5 h-5 w-5 shrink-0 text-red-700" aria-hidden="true" />
            解約を予約した場合でも、現在の契約期間終了日までは特典を利用できます。
          </li>
          <li class="flex gap-3 rounded-xl bg-neutral-50 p-4 text-sm font-bold leading-6 text-neutral-700">
            <Check class="mt-0.5 h-5 w-5 shrink-0 text-red-700" aria-hidden="true" />
            決済に失敗した場合は特典が停止されることがあります。
          </li>
        </ul>
      </div>
    </section>

    <div
      v-if="isCancelConfirmOpen"
      class="fixed inset-0 z-50 grid place-items-center bg-black/45 px-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="plus-cancel-title"
    >
      <section class="w-full max-w-md rounded-2xl bg-white p-6 text-neutral-900 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-sm font-black text-red-700">麺ナビ Plus</p>
            <h2 id="plus-cancel-title" class="mt-1 text-2xl font-black tracking-normal">
              解約してもよろしいですか？
            </h2>
          </div>
          <button
            class="grid h-9 w-9 place-items-center rounded-full text-neutral-500 hover:bg-neutral-100"
            type="button"
            aria-label="閉じる"
            @click="closeCancelConfirm"
          >
            <X class="h-5 w-5" />
          </button>
        </div>

        <p class="mt-4 text-sm font-bold leading-7 text-neutral-600">
          解約しても、現在の利用期限までは麺ナビ Plusの特典を利用できます。
        </p>

        <div class="mt-5 rounded-xl bg-neutral-50 p-4">
          <p class="text-xs font-black text-neutral-500">現在の利用期限</p>
          <p class="mt-1 text-lg font-black text-neutral-900">
            {{ formatDate(localSubscription?.currentPeriodEnd) }}
          </p>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2">
          <button
            class="h-12 rounded-xl border border-neutral-300 bg-white text-sm font-black text-neutral-700 hover:bg-neutral-50 disabled:opacity-60"
            type="button"
            :disabled="isProcessing"
            @click="closeCancelConfirm"
          >
            いいえ
          </button>
          <button
            class="h-12 rounded-xl bg-red-700 text-sm font-black text-white hover:bg-red-800 disabled:opacity-60"
            type="button"
            :disabled="isProcessing"
            @click="cancelSubscription"
          >
            {{ isProcessing ? '処理中...' : 'はい、解約する' }}
          </button>
        </div>
      </section>
    </div>

    <div
      v-if="isPaymentModalOpen"
      class="fixed inset-0 z-50 bg-black/40"
      role="presentation"
      @click="closePaymentModal"
    />

    <section
      v-if="isPaymentModalOpen"
      class="fixed left-1/2 top-1/2 z-[60] w-[calc(100%-32px)] max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-lg bg-white p-6 text-neutral-900 shadow-2xl"
      role="dialog"
      aria-modal="true"
      aria-label="麺ナビ Plus決済"
      @click.stop
    >
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-sm font-black text-red-700">麺ナビ Plus</p>
          <h2 class="mt-1 text-2xl font-black tracking-normal">カード情報を入力</h2>
          <p class="mt-2 text-sm font-bold leading-6 text-neutral-500">
            PAY.JPの安全な入力フォームで月額プランの決済を行います。
          </p>
          <p class="mt-2 text-xs font-bold leading-5 text-red-700">
            テスト決済は 4242 4242 4242 4242、有効期限は未来の月/年、CVCは3桁で入力してください。
          </p>
        </div>
        <button
          class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-neutral-500 hover:bg-neutral-100"
          type="button"
          aria-label="閉じる"
          @click="closePaymentModal"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <div class="mt-6 rounded-xl bg-neutral-50 p-4">
        <div class="flex items-center justify-between gap-4 text-sm font-black">
          <span>{{ plan.name }}</span>
          <span class="text-red-700">{{ formatPrice(plan.price) }} / 月</span>
        </div>
        <p class="mt-2 text-xs font-bold leading-5 text-neutral-500">
          決済完了後、配送料無料と{{ plan.discountRate }}%割引がすぐに利用できます。
        </p>
      </div>

      <div class="mt-6">
        <label class="text-sm font-black text-neutral-700" for="plus-payjp-modal-card">
          カード情報
        </label>
        <div
          id="plus-payjp-modal-card"
          class="mt-2 min-h-12 rounded-lg border border-neutral-200 px-4 py-3"
        />
        <p class="mt-2 text-xs font-bold leading-5 text-neutral-500">
          例: カード番号 4242 4242 4242 4242 / 有効期限 12/30 / CVC 123
        </p>
      </div>

      <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-lg bg-neutral-50 p-4">
        <input
          v-model="saveAsDefault"
          class="mt-1 h-4 w-4 accent-red-700"
          type="checkbox"
        />
        <span>
          <span class="block text-sm font-black text-neutral-900">
            このカードをデフォルトで使用する
          </span>
          <span class="mt-1 block text-xs font-bold leading-5 text-neutral-500">
            次回以降、ご注文内容のお支払い方法に登録済みカードを表示します。
          </span>
        </span>
      </label>

      <p v-if="errorMessage" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm font-black text-red-700">
        {{ errorMessage }}
      </p>

      <div class="mt-6 grid grid-cols-2 gap-3">
        <button
          class="rounded-full border border-neutral-200 px-5 py-3 text-sm font-black text-neutral-600 hover:bg-neutral-50 disabled:opacity-60"
          type="button"
          :disabled="isProcessing"
          @click="closePaymentModal"
        >
          キャンセル
        </button>
        <button
          class="rounded-full bg-red-700 px-5 py-3 text-sm font-black text-white hover:bg-red-800 disabled:opacity-50"
          type="button"
          :disabled="isProcessing"
          @click="submitPlusPayment"
        >
          {{ isProcessing ? '決済中...' : '決済' }}
        </button>
      </div>
    </section>

    <div
      v-if="isSuccessModalOpen"
      class="fixed inset-0 z-50 grid place-items-center bg-black/45 px-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="plus-success-title"
    >
      <section class="w-full max-w-md rounded-2xl bg-white p-6 text-center text-neutral-900 shadow-2xl">
        <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-red-50 text-red-700">
          <Check class="h-7 w-7" />
        </div>
        <p class="mt-5 text-sm font-black text-red-700">麺ナビ Plus</p>
        <h2 id="plus-success-title" class="mt-1 text-2xl font-black tracking-normal">
          決済が完了しました
        </h2>
        <p class="mt-3 text-sm font-bold leading-7 text-neutral-600">
          麺ナビ Plusへの申し込みが完了しました。特典は次回の注文から自動で適用されます。
        </p>
        <button
          class="mt-6 h-12 w-full rounded-xl bg-red-700 text-sm font-black text-white hover:bg-red-800"
          type="button"
          @click="closeSuccessModal"
        >
          OK
        </button>
      </section>
    </div>
  </main>
</template>
