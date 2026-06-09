<script setup lang="ts">
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
    :src="src || fallbackSrc"
    :alt="alt"
    loading="lazy"
    @error="handleImageError"
  />
</template>
