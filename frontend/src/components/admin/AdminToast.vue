<script setup lang="ts">
import { AlertTriangle, CheckCircle2, Info, X, XCircle } from 'lucide-vue-next'

type ToastType = 'success' | 'error' | 'warning' | 'info'

const props = withDefaults(
  defineProps<{
    show: boolean
    message: string
    type?: ToastType
  }>(),
  {
    type: 'info',
  },
)

const emit = defineEmits<{
  close: []
}>()

const toastClassMap: Record<ToastType, string> = {
  success: 'border-green-200 bg-green-50 text-green-800',
  error: 'border-red-200 bg-red-50 text-red-800',
  warning: 'border-yellow-200 bg-yellow-50 text-yellow-800',
  info: 'border-blue-200 bg-blue-50 text-blue-800',
}

const iconClassMap: Record<ToastType, string> = {
  success: 'text-green-700',
  error: 'text-red-700',
  warning: 'text-yellow-700',
  info: 'text-blue-700',
}

const iconMap = {
  success: CheckCircle2,
  error: XCircle,
  warning: AlertTriangle,
  info: Info,
}

const toastClass = () => {
  return toastClassMap[props.type]
}

const iconClass = () => {
  return iconClassMap[props.type]
}

const toastIcon = () => {
  return iconMap[props.type]
}
</script>

<template>
  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="translate-y-2 opacity-0"
    enter-to-class="translate-y-0 opacity-100"
    leave-active-class="transition duration-150 ease-in"
    leave-from-class="translate-y-0 opacity-100"
    leave-to-class="translate-y-2 opacity-0"
  >
    <div
      v-if="show && message"
      class="fixed bottom-6 right-6 z-[60] flex w-[calc(100%-3rem)] max-w-md items-start gap-3 rounded-xl border px-4 py-3 shadow-lg"
      :class="toastClass()"
      role="status"
    >
      <component
        :is="toastIcon()"
        class="mt-0.5 h-5 w-5 shrink-0"
        :class="iconClass()"
      />

      <p class="min-w-0 flex-1 text-sm font-black leading-6">
        {{ message }}
      </p>

      <button
        class="grid h-7 w-7 shrink-0 place-items-center rounded-full hover:bg-white/70"
        type="button"
        aria-label="通知を閉じる"
        @click="emit('close')"
      >
        <X class="h-4 w-4" />
      </button>
    </div>
  </Transition>
</template>