<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { Banknote, CheckCircle2, CreditCard, MapPin, Trash2, WalletCards, X } from 'lucide-vue-next'
import FallbackImage from '../../components/common/FallbackImage.vue'
import { apiRequest, authHeaders } from '../../lib/api'
import { getCustomerToken } from '../../lib/authStorage'
import type { User } from '../../types/auth'

type CartItem = {
  storeName: string
  menuItemId: number
  name: string
  price: number
  quantity: number
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

const props = defineProps<{
  cartItems: CartItem[]
  cartTotal: number
  user: User
}>()

const emit = defineEmits<{
  back: []
  completed: []
  updateQuantity: [payload: { item: CartItem; amount: number }]
  removeItem: [item: CartItem]
}>()

const receiptType = ref<'delivery' | 'pickup'>('delivery')
const paymentMethod = ref<'card' | 'paypay' | 'cash'>('card')
const isPaymentModalOpen = ref(false)
const saveAsDefault = ref(true)
const paymentLoading = ref(false)
const paymentError = ref('')
const paymentMethods = ref<PaymentMethod[]>([])
const selectedPaymentMethodId = ref<number | null>(null)
const oneTimePayjpToken = ref<string | null>(null)
let payjpInstance: PayjpInstance | null = null
let payjpCardElement: PayjpElement | null = null

const deliveryFeeBase = 350
const taxRate = 8
const deliveryFee = computed(() => (receiptType.value === 'delivery' ? deliveryFeeBase : 0))
const discount = computed(() => 0)
const taxAmount = computed(() => Math.floor((props.cartTotal + deliveryFee.value) * taxRate / 100))
const totalAmount = computed(() => props.cartTotal + deliveryFee.value + taxAmount.value - discount.value)
const totalQuantity = computed(() =>
  props.cartItems.reduce((total, item) => total + item.quantity, 0),
)
const deliveryAddress = computed(() => {
  if (!props.user.address) {
    return '配送先情報から住所を登録してください。'
  }

  return `${props.user.address}${props.user.postal_code ? ` / ${props.user.postal_code}` : ''}`
})
const selectedPaymentMethod = computed(() =>
  paymentMethods.value.find((method) => method.id === selectedPaymentMethodId.value) ?? null,
)
const canSubmitCardPayment = computed(() =>
  paymentMethod.value === 'card' && Boolean(selectedPaymentMethod.value || oneTimePayjpToken.value),
)
const primaryPaymentButtonLabel = computed(() => {
  if (paymentLoading.value) {
    return '決済中...'
  }

  return canSubmitCardPayment.value ? '注文を確定する' : '決済画面へ'
})

onMounted(() => {
  loadPaymentMethods()
})

onBeforeUnmount(() => {
  unmountPayjpCard()
})

function unmountPayjpCard() {
  if (payjpCardElement) {
    payjpCardElement.unmount()
    payjpCardElement = null
  }
}

function selectPaymentMethod(method: 'card' | 'paypay' | 'cash') {
  paymentMethod.value = method
  paymentError.value = ''

  if (method !== 'card') {
    isPaymentModalOpen.value = false
    unmountPayjpCard()
  }
}

async function openPaymentForm() {
  if (!props.cartItems.length) {
    paymentError.value = 'カートが空です。'
    return
  }

  if (paymentMethod.value !== 'card') {
    paymentError.value = '現在はクレジットカード決済のみ対応しています。'
    return
  }

  isPaymentModalOpen.value = true
  oneTimePayjpToken.value = null
  saveAsDefault.value = true
  paymentError.value = ''
  await nextTick()
  mountPayjpCard()
}

function closePaymentModal() {
  isPaymentModalOpen.value = false
  unmountPayjpCard()
}

function mountPayjpCard() {
  if (payjpCardElement) {
    return
  }

  const publicKey = import.meta.env.VITE_PAYJP_PUBLIC_KEY
  if (!publicKey || !window.Payjp) {
    paymentError.value = 'PAY.JPの公開鍵またはpayjp.jsが設定されていません。'
    return
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
  payjpCardElement.mount('#checkout-payjp-modal-card')
}

async function loadPaymentMethods() {
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
    selectedPaymentMethodId.value = response.default_payment_method?.id ?? null
  } catch {
    paymentMethods.value = []
    selectedPaymentMethodId.value = null
  }
}

async function setupCardPayment() {
  const token = getCustomerToken()
  if (!token || !payjpInstance || !payjpCardElement) {
    paymentError.value = '決済フォームを読み込めませんでした。'
    return
  }

  paymentLoading.value = true
  paymentError.value = ''

  try {
    const tokenResponse = await payjpInstance.createToken(payjpCardElement)
    if (tokenResponse.error || !tokenResponse.id) {
      throw new Error(tokenResponse.error?.message ?? 'カード情報を確認してください。')
    }

    if (saveAsDefault.value) {
      const response = await apiRequest<{ payment_method: PaymentMethod }>('/payment-methods', {
        method: 'POST',
        headers: authHeaders(token),
        body: JSON.stringify({
          payjp_token: tokenResponse.id,
          is_default: true,
        }),
      })

      paymentMethods.value = [
        response.payment_method,
        ...paymentMethods.value
          .filter((method) => method.id !== response.payment_method.id)
          .map((method) => ({ ...method, is_default: false })),
      ]
      selectedPaymentMethodId.value = response.payment_method.id
      oneTimePayjpToken.value = null
    } else {
      oneTimePayjpToken.value = tokenResponse.id
      selectedPaymentMethodId.value = null
    }

    closePaymentModal()
  } catch (error) {
    paymentError.value = error instanceof Error ? error.message : 'カード情報の設定に失敗しました。'
  } finally {
    paymentLoading.value = false
  }
}

async function handlePrimaryPaymentAction() {
  if (canSubmitCardPayment.value) {
    await submitOrder()
    return
  }

  await openPaymentForm()
}

async function submitOrder() {
  const token = getCustomerToken()
  if (!token) {
    paymentError.value = '認証が必要です。'
    return
  }

  if (!props.cartItems.length) {
    paymentError.value = 'カートが空です。'
    return
  }

  if (paymentMethod.value !== 'card') {
    paymentError.value = '現在はクレジットカード決済のみ対応しています。'
    return
  }

  if (!selectedPaymentMethod.value && !oneTimePayjpToken.value) {
    paymentError.value = 'カード情報を設定してください。'
    return
  }

  paymentLoading.value = true
  paymentError.value = ''

  try {
    await apiRequest('/orders', {
      method: 'POST',
      headers: authHeaders(token),
        body: JSON.stringify({
          ...(selectedPaymentMethod.value
            ? { payment_method_id: selectedPaymentMethod.value.id }
            : { payjp_token: oneTimePayjpToken.value }),
          payment_method: 'card',
          receipt_type: receiptType.value,
        }),
      })

    emit('completed')
  } catch (error) {
    paymentError.value = error instanceof Error ? error.message : '決済に失敗しました。'
  } finally {
    paymentLoading.value = false
  }
}

function formatPrice(price: number) {
  return `¥${price.toLocaleString('ja-JP')}`
}

function formatPaymentMethod(method: PaymentMethod) {
  const brand = method.brand ?? 'Card'
  const last4 = method.last4 ? `**** **** **** ${method.last4}` : '登録済みカード'
  const expiry =
    method.exp_month && method.exp_year
      ? `有効期限 ${method.exp_month.toString().padStart(2, '0')}/${method.exp_year}`
      : ''

  return [brand, last4, expiry].filter(Boolean).join(' / ')
}
</script>

<template>
  <main class="mx-auto w-full max-w-7xl px-5 py-9 md:px-8">
    <h1 class="text-4xl font-black tracking-normal md:text-5xl">ご注文内容</h1>

    <div class="mt-12 grid gap-6 lg:grid-cols-[minmax(0,1fr)_356px]">
      <section class="grid content-start gap-6">
        <div class="w-full max-w-sm rounded-lg border border-red-200 bg-white p-4">
          <p class="text-sm font-black text-neutral-500">受け取り方法</p>
          <div class="mt-2 grid rounded-full bg-neutral-100 p-1 text-sm font-black sm:grid-cols-2">
            <button
              :class="[
                'rounded-full px-5 py-3',
                receiptType === 'delivery' ? 'bg-red-700 text-white shadow-sm' : 'text-neutral-600',
              ]"
              type="button"
              @click="receiptType = 'delivery'"
            >
              配達
            </button>
            <button
              :class="[
                'rounded-full px-5 py-3',
                receiptType === 'pickup' ? 'bg-red-700 text-white shadow-sm' : 'text-neutral-600',
              ]"
              type="button"
              @click="receiptType = 'pickup'"
            >
              お持ち帰り
            </button>
          </div>
          <p class="mt-3 text-xs font-bold leading-5 text-neutral-500">
            配達は配送料 {{ formatPrice(deliveryFeeBase) }}、お持ち帰りは配送料無料です。税率は8%で計算します。
          </p>
        </div>

        <article
          v-for="item in cartItems"
          :key="`${item.storeName}-${item.menuItemId}`"
          class="grid gap-4 rounded-lg border border-red-200 bg-white p-4 shadow-sm md:grid-cols-[96px_minmax(0,1fr)_auto]"
        >
          <div class="h-24 overflow-hidden rounded-md bg-neutral-100">
            <FallbackImage :src="null" :alt="`${item.name}の画像`" />
          </div>

          <div>
            <h2 class="text-2xl font-black tracking-normal">{{ item.name }}</h2>
            <p class="mt-2 text-sm font-bold text-neutral-500">{{ item.storeName }}</p>
            <p class="mt-4 text-2xl font-black text-red-700">{{ formatPrice(item.price) }}</p>
          </div>

          <div class="flex items-center justify-between gap-5 md:flex-col md:items-end">
            <button
              class="grid h-10 w-10 place-items-center rounded-full text-neutral-500 hover:bg-red-50 hover:text-red-700"
              type="button"
              aria-label="削除"
              @click="emit('removeItem', item)"
            >
              <Trash2 class="h-5 w-5" />
            </button>
            <div class="inline-flex items-center rounded-full bg-neutral-100 px-3 py-2">
              <button
                class="grid h-7 w-7 place-items-center rounded-full font-black text-neutral-700 hover:bg-white"
                type="button"
                @click="emit('updateQuantity', { item, amount: -1 })"
              >
                -
              </button>
              <span class="w-9 text-center text-sm font-black">{{ item.quantity }}</span>
              <button
                class="grid h-7 w-7 place-items-center rounded-full font-black text-red-700 hover:bg-white"
                type="button"
                @click="emit('updateQuantity', { item, amount: 1 })"
              >
                +
              </button>
            </div>
          </div>
        </article>

        <div
          v-if="!cartItems.length"
          class="rounded-lg border border-dashed border-neutral-300 bg-white px-6 py-16 text-center"
        >
          <p class="text-xl font-black tracking-normal">カートは空です</p>
          <button
            class="mt-5 rounded-full bg-red-700 px-7 py-3 text-sm font-black text-white"
            type="button"
            @click="emit('back')"
          >
            店舗一覧へ戻る
          </button>
        </div>
      </section>

      <aside class="grid content-start gap-4">
        <section class="rounded-lg border border-red-200 bg-white p-5">
          <h2 class="text-2xl font-black tracking-normal">お支払い方法</h2>
          <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <button
              :class="[
                'grid min-h-20 place-items-center rounded-lg border px-3 text-sm font-black',
                paymentMethod === 'card'
                  ? 'border-red-700 text-neutral-900 ring-1 ring-red-700'
                  : 'border-red-200 text-neutral-700',
              ]"
              type="button"
              @click="selectPaymentMethod('card')"
            >
              <CreditCard class="mb-1 h-6 w-6 text-red-700" />
              クレジットカード
            </button>
            <button
              :class="[
                'grid min-h-20 place-items-center rounded-lg border px-3 text-sm font-black',
                paymentMethod === 'paypay'
                  ? 'border-red-700 text-neutral-900 ring-1 ring-red-700'
                  : 'border-red-200 text-neutral-700',
              ]"
              type="button"
              @click="selectPaymentMethod('paypay')"
            >
              <WalletCards
                :class="[
                  'mb-1 h-6 w-6',
                  paymentMethod === 'paypay' ? 'text-red-700' : 'text-neutral-500',
                ]"
              />
              PayPay / QR決済
            </button>
            <button
              :class="[
                'grid min-h-20 place-items-center rounded-lg border px-3 text-sm font-black',
                paymentMethod === 'cash'
                  ? 'border-red-700 text-neutral-900 ring-1 ring-red-700'
                  : 'border-red-200 text-neutral-700',
              ]"
              type="button"
              @click="selectPaymentMethod('cash')"
            >
              <Banknote
                :class="[
                  'mb-1 h-6 w-6',
                  paymentMethod === 'cash' ? 'text-red-700' : 'text-neutral-500',
                ]"
              />
              現金（代引）
            </button>
          </div>

          <div v-if="paymentMethod === 'card'" class="mt-5">
            <div
              v-if="selectedPaymentMethod"
              class="rounded-lg border border-red-100 bg-red-50 px-4 py-3"
            >
              <div class="flex items-start gap-3">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-red-700" />
                <div>
                  <p class="text-sm font-black text-neutral-900">デフォルトのカード情報</p>
                  <p class="mt-1 text-sm font-bold text-neutral-600">
                    {{ formatPaymentMethod(selectedPaymentMethod) }}
                  </p>
                </div>
              </div>
            </div>
            <div
              v-else-if="oneTimePayjpToken"
              class="rounded-lg border border-red-100 bg-red-50 px-4 py-3"
            >
              <p class="text-sm font-black text-neutral-900">今回入力したカードを使用します</p>
              <p class="mt-1 text-xs font-bold text-neutral-500">
                注文確定時にPAY.JPで決済します。
              </p>
            </div>
            <p v-else class="rounded-lg bg-neutral-50 px-4 py-3 text-sm font-bold text-neutral-500">
              登録済みカードがありません。決済画面でカード情報を入力してください。
            </p>
          </div>
        </section>

        <section class="rounded-lg border border-red-200 bg-white p-6">
          <h2 class="text-2xl font-black tracking-normal">注文概要</h2>
          <dl class="mt-5 grid gap-3 text-sm font-bold text-neutral-600">
            <div class="flex justify-between">
              <dt>小計 ({{ totalQuantity }}点)</dt>
              <dd>{{ formatPrice(cartTotal) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt>配送料</dt>
              <dd>{{ deliveryFee ? formatPrice(deliveryFee) : '無料' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt>税金 ({{ taxRate }}%)</dt>
              <dd>{{ formatPrice(taxAmount) }}</dd>
            </div>
            <div class="flex justify-between text-amber-700">
              <dt>クーポン割引</dt>
              <dd>-{{ formatPrice(discount) }}</dd>
            </div>
          </dl>

          <div class="mt-5 flex items-center justify-between border-t border-red-100 pt-5">
            <span class="text-xl font-black">合計金額</span>
            <span class="text-4xl font-black text-red-700">{{ formatPrice(totalAmount) }}</span>
          </div>

          <div class="mt-6 rounded-lg bg-neutral-100 p-4 text-sm font-bold text-neutral-700">
            <div class="flex gap-2">
              <MapPin class="mt-0.5 h-5 w-5 shrink-0 text-neutral-500" />
              <div>
                <p class="font-black">お届け先</p>
                <p class="mt-1 leading-6">{{ deliveryAddress }}</p>
              </div>
            </div>
          </div>

          <p v-if="paymentError" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm font-black text-red-700">
            {{ paymentError }}
          </p>

          <button
            class="mt-5 h-16 w-full rounded-lg bg-red-700 text-lg font-black text-white hover:bg-red-800 disabled:opacity-50"
            type="button"
            :disabled="paymentLoading || !cartItems.length"
            @click="handlePrimaryPaymentAction"
          >
            {{ primaryPaymentButtonLabel }}
          </button>

          <p class="mt-4 text-center text-xs font-bold leading-5 text-neutral-500">
            {{
              canSubmitCardPayment
                ? '「注文を確定する」を押すと、利用規約に同意したことになります。'
                : 'カード情報の入力画面に進みます。'
            }}
          </p>
        </section>
      </aside>
    </div>
  </main>

  <div
    v-if="isPaymentModalOpen"
    class="fixed inset-0 z-50 bg-black/40"
    role="presentation"
    @click="closePaymentModal"
  />

  <section
    v-if="isPaymentModalOpen"
    class="fixed left-1/2 top-1/2 z-[60] w-[calc(100%-32px)] max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-lg bg-white p-6 shadow-2xl"
    role="dialog"
    aria-modal="true"
    aria-label="カード情報入力"
    @click.stop
  >
    <div class="flex items-start justify-between gap-4">
      <div>
        <h2 class="text-2xl font-black tracking-normal">カード情報を入力</h2>
        <p class="mt-2 text-sm font-bold leading-6 text-neutral-500">
          PAY.JPの安全な入力フォームでカード情報を設定します。
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

    <div class="mt-6">
      <label class="text-sm font-black text-neutral-700" for="checkout-payjp-modal-card">
        カード情報
      </label>
      <div
        id="checkout-payjp-modal-card"
        class="mt-2 min-h-12 rounded-lg border border-neutral-200 px-4 py-3"
      />
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

    <p v-if="paymentError" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm font-black text-red-700">
      {{ paymentError }}
    </p>

    <div class="mt-6 grid grid-cols-2 gap-3">
      <button
        class="rounded-full border border-neutral-200 px-5 py-3 text-sm font-black text-neutral-600 hover:bg-neutral-50"
        type="button"
        @click="closePaymentModal"
      >
        キャンセル
      </button>
      <button
        class="rounded-full bg-red-700 px-5 py-3 text-sm font-black text-white hover:bg-red-800 disabled:opacity-50"
        type="button"
        :disabled="paymentLoading"
        @click="setupCardPayment"
      >
        {{ paymentLoading ? '設定中...' : 'カードを設定する' }}
      </button>
    </div>
  </section>
</template>
