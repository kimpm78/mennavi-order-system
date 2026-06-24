<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Mail, MessageSquareText } from 'lucide-vue-next'
import type { AdminContactMessageRow } from './adminTypes'

const props = defineProps<{
  contactMessages: AdminContactMessageRow[]
  adminPageLoading?: boolean
}>()

const emit = defineEmits<{
  updateStatus: [message: AdminContactMessageRow, status: string]
}>()

const selectedMessageId = ref<number | null>(null)

const selectedMessage = computed(() => {
  return props.contactMessages.find((message) => message.id === selectedMessageId.value) ?? props.contactMessages[0] ?? null
})

watch(
  () => props.contactMessages,
  (messages) => {
    if (!messages.length) {
      selectedMessageId.value = null
      return
    }

    if (!selectedMessageId.value || !messages.some((message) => message.id === selectedMessageId.value)) {
      selectedMessageId.value = messages[0].id
    }
  },
  { immediate: true },
)

const statusLabel = (status: string) => {
  const labels: Record<string, string> = {
    new: '未対応',
    in_progress: '対応中',
    resolved: '解決済み',
    closed: '終了',
  }

  return labels[status] ?? status
}

const statusClass = (status: string) => {
  if (status === 'new') {
    return 'bg-red-50 text-red-700'
  }

  if (status === 'in_progress') {
    return 'bg-amber-50 text-amber-700'
  }

  return 'bg-neutral-100 text-neutral-600'
}

const nextStatus = (status: string) => {
  const transitions: Record<string, string> = {
    new: 'in_progress',
    in_progress: 'resolved',
  }

  return transitions[status]
}

const nextStatusActionLabel = (status: string) => {
  const labels: Record<string, string> = {
    new: '対応開始',
    in_progress: '解決済みにする',
  }

  return labels[status]
}

const updateSelectedStatus = () => {
  if (!selectedMessage.value) {
    return
  }

  const status = nextStatus(selectedMessage.value.status)
  if (!status) {
    return
  }

  emit('updateStatus', selectedMessage.value, status)
}

const formatDateTime = (value?: string | null) => {
  if (!value) {
    return '-'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hour = String(date.getHours()).padStart(2, '0')
  const minute = String(date.getMinutes()).padStart(2, '0')

  return `${year}-${month}-${day} (${hour}:${minute})`
}
</script>

<template>
  <div class="grid gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm font-bold text-red-600">お問い合わせ</p>
        <h1 class="text-2xl font-black text-neutral-900">お問い合わせ管理</h1>
      </div>

      <div class="rounded-full border border-red-100 bg-white px-4 py-2 text-sm font-bold text-neutral-600 shadow-sm">
        {{ adminPageLoading ? '読み込み中...' : `${contactMessages.length}件` }}
      </div>
    </div>

    <section
      v-if="contactMessages.length"
      class="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]"
    >
      <div class="overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm">
        <div class="flex items-center gap-3 border-b border-red-100 px-5 py-4">
          <div class="grid h-10 w-10 place-items-center rounded-xl bg-red-50 text-red-700">
            <Mail class="h-5 w-5" />
          </div>
          <div>
            <h2 class="text-lg font-black tracking-normal">受信一覧</h2>
            <p class="mt-1 text-xs font-bold text-neutral-500">新しいお問い合わせ順</p>
          </div>
        </div>

        <div class="divide-y divide-red-50">
          <button
            v-for="message in contactMessages"
            :key="message.id"
            type="button"
            class="grid w-full gap-2 px-5 py-4 text-left transition hover:bg-red-50"
            :class="selectedMessage?.id === message.id ? 'bg-red-50' : 'bg-white'"
            @click="selectedMessageId = message.id"
          >
            <div class="flex items-center justify-between gap-3">
              <p class="truncate text-sm font-black text-neutral-900">{{ message.category }}</p>
              <span
                class="shrink-0 rounded-full px-3 py-1 text-xs font-black"
                :class="statusClass(message.status)"
              >
                {{ statusLabel(message.status) }}
              </span>
            </div>
            <p class="truncate text-sm font-bold text-neutral-600">{{ message.name }} / {{ message.email }}</p>
            <p class="text-xs font-bold text-neutral-400">{{ formatDateTime(message.created_at) }}</p>
          </button>
        </div>
      </div>

      <article class="rounded-xl border border-red-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <p class="text-sm font-bold text-red-600">お問い合わせ詳細</p>
            <h2 class="mt-1 text-2xl font-black tracking-normal text-neutral-900">
              {{ selectedMessage?.category }}
            </h2>
          </div>
          <span
            v-if="selectedMessage"
            class="shrink-0 rounded-full px-3 py-1 text-xs font-black"
            :class="statusClass(selectedMessage.status)"
          >
            {{ statusLabel(selectedMessage.status) }}
          </span>
        </div>

        <div v-if="selectedMessage && nextStatus(selectedMessage.status)" class="mt-5 flex justify-end">
          <button
            class="h-11 rounded-lg bg-red-700 px-5 text-sm font-black text-white hover:bg-red-800 disabled:opacity-60"
            type="button"
            :disabled="adminPageLoading"
            @click="updateSelectedStatus"
          >
            {{ nextStatusActionLabel(selectedMessage.status) }}
          </button>
        </div>

        <p
          v-if="selectedMessage"
          class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold leading-6 text-amber-900"
        >
          お問い合わせには実際にメールで対応し、対応完了後に「解決済みにする」を選択してください。
        </p>

        <dl v-if="selectedMessage" class="mt-6 grid gap-4 text-sm">
          <div class="grid gap-1 rounded-lg bg-neutral-50 p-4">
            <dt class="font-black text-neutral-500">お名前</dt>
            <dd class="font-bold text-neutral-900">{{ selectedMessage.name }}</dd>
          </div>
          <div class="grid gap-1 rounded-lg bg-neutral-50 p-4">
            <dt class="font-black text-neutral-500">メールアドレス</dt>
            <dd class="font-bold text-neutral-900">{{ selectedMessage.email }}</dd>
          </div>
          <div class="grid gap-1 rounded-lg bg-neutral-50 p-4">
            <dt class="font-black text-neutral-500">注文番号</dt>
            <dd class="font-bold text-neutral-900">{{ selectedMessage.order_number || '未入力' }}</dd>
          </div>
          <div class="grid gap-1 rounded-lg bg-neutral-50 p-4">
            <dt class="font-black text-neutral-500">送信日時</dt>
            <dd class="font-bold text-neutral-900">{{ formatDateTime(selectedMessage.created_at) }}</dd>
          </div>
          <div class="grid gap-2 rounded-lg border border-red-100 bg-white p-4">
            <dt class="flex items-center gap-2 font-black text-neutral-900">
              <MessageSquareText class="h-4 w-4 text-red-700" />
              内容
            </dt>
            <dd class="whitespace-pre-wrap text-sm font-bold leading-7 text-neutral-700">
              {{ selectedMessage.message }}
            </dd>
          </div>
        </dl>
      </article>
    </section>

    <section
      v-else
      class="rounded-xl border border-dashed border-red-200 bg-white p-10 text-center shadow-sm"
    >
      <Mail class="mx-auto h-10 w-10 text-red-200" />
      <p class="mt-4 text-sm font-bold text-neutral-500">お問い合わせはまだありません。</p>
    </section>
  </div>
</template>
