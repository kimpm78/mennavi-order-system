<script setup lang="ts">
const props = defineProps<{
  status?: string | null
  label?: string
}>()

const statusClassMap: Record<string, string> = {
  pending: 'bg-yellow-50 text-yellow-800 ring-yellow-200',
  accepted: 'bg-blue-50 text-blue-800 ring-blue-200',
  preparing: 'bg-orange-50 text-orange-800 ring-orange-200',
  ready: 'bg-purple-50 text-purple-800 ring-purple-200',
  completed: 'bg-green-50 text-green-800 ring-green-200',
  cancelled: 'bg-neutral-100 text-neutral-600 ring-neutral-200',
  canceled: 'bg-neutral-100 text-neutral-600 ring-neutral-200',
}

const statusLabelMap: Record<string, string> = {
  pending: '受付待ち',
  accepted: '受付済み',
  preparing: '調理中',
  ready: '受け渡し待ち',
  completed: '完了',
  cancelled: 'キャンセル',
  canceled: 'キャンセル',
}

const normalizedStatus = () => {
  return String(props.status || '').toLowerCase()
}

const badgeLabel = () => {
  if (props.label) {
    return props.label
  }

  return statusLabelMap[normalizedStatus()] || '未設定'
}

const badgeClass = () => {
  return statusClassMap[normalizedStatus()] || 'bg-red-50 text-red-700 ring-red-100'
}
</script>

<template>
  <span
    class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-xs font-black ring-1 ring-inset"
    :class="badgeClass()"
  >
    {{ badgeLabel() }}
  </span>
</template>