<script setup lang="ts">
defineProps<{
  type: 'error' | 'success'
  message: string
}>()
</script>

<template>
  <p v-if="message" class="message" :class="type">{{ message }}</p>
</template>
