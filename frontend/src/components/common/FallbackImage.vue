<script setup lang="ts">
import { computed } from 'vue'
import { apiBaseUrl } from '../../lib/api'

const props = withDefaults(
  defineProps<{
    src?: string | null
    alt: string
    fallbackSrc?: string
  }>(),
  {
    fallbackSrc: '/images/no-image.png',
  },
)

const backendOrigin = apiBaseUrl.replace(/\/api\/?$/, '')
const resolvedSrc = computed(() => {
  if (!props.src) {
    return props.fallbackSrc
  }

  if (props.src.startsWith('/storage/')) {
    return `${backendOrigin}${props.src}`
  }

  return props.src
})

function handleImageError(event: Event) {
  const image = event.target as HTMLImageElement

  if (image.src.endsWith(props.fallbackSrc)) {
    return
  }

  image.src = props.fallbackSrc
}
</script>

<template>
  <img
    class="h-full w-full object-cover"
    :src="resolvedSrc"
    :alt="alt"
    loading="lazy"
    @error="handleImageError"
  />
</template>
