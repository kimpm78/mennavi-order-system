<script setup lang="ts">
defineProps<{
  eyebrow: string
  title: string
  lead: string
  panelLabel: string
  panelTitle: string
}>()
</script>

<template>
  <main class="app-shell">
    <section class="brand-panel" aria-label="Mennavi order system">
      <div class="brand-copy">
        <p class="eyebrow">{{ eyebrow }}</p>
        <img class="brand-logo" src="/logo.svg" alt="麺ナビ" />
        <p class="lead">{{ lead }}</p>
      </div>
    </section>

    <section class="auth-panel" :aria-label="panelLabel">
      <div class="panel-header">
        <p class="eyebrow">{{ panelLabel }}</p>
        <h2>{{ panelTitle }}</h2>
      </div>
      <slot />
    </section>
  </main>
</template>
