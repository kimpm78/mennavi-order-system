<script setup lang="ts">
import { computed } from 'vue'
import { apiBaseUrl } from '../../lib/api'
import { publicPath } from '../../lib/publicPath'

const props = withDefaults(
  defineProps<{
    src?: string | null
    alt: string
    fallbackSrc?: string
  }>(),
  {
    fallbackSrc: 'images/no-image.png',
  },
)

const backendOrigin = apiBaseUrl.replace(/\/api\/?$/, '')
const fallbackImageSrc = computed(() => publicPath(props.fallbackSrc))
const resolvedSrc = computed(() => {
  if (!props.src) {
    return fallbackImageSrc.value
  }

  if (props.src.startsWith('/storage/')) {
    return `${backendOrigin}${props.src}`
  }

  return props.src
})

function handleImageError(event: Event) {
  const image = event.target as HTMLImageElement

  if (image.src === new URL(fallbackImageSrc.value, window.location.href).href) {
    return
  }

  image.src = fallbackImageSrc.value
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
