<script setup lang="ts">
import { computed } from 'vue'
import { publicPath } from '../../lib/publicPath'

const props = defineProps<{
  eyebrow: string
  title: string
  lead: string
  panelLabel: string
  panelTitle: string
  brandImage?: string
}>()

const defaultBrandImage = publicPath('images/mennavi-login.png')
const logoPath = publicPath('logo.svg')
const brandPanelStyle = computed(() => ({
  '--brand-image': `url('${props.brandImage ? publicPath(props.brandImage) : defaultBrandImage}')`,
}))
</script>

<template>
  <main class="app-shell">
    <section
      class="brand-panel"
      :style="brandPanelStyle"
      aria-label="Mennavi order system"
    >
      <div class="brand-copy">
        <p class="eyebrow">{{ eyebrow }}</p>
        <img class="brand-logo" :src="logoPath" alt="麺ナビ" />
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
