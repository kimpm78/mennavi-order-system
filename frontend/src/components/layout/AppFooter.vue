<script setup lang="ts">
type FooterLink = {
  label: string
  path: string
}

const props = defineProps<{
  goTo?: (path: string) => void
}>()

const footerLinks: FooterLink[] = [
  { label: 'サービス紹介', path: '/about' },
  { label: 'お問い合わせ', path: '/contact' },
  { label: '利用規約', path: '/terms' },
  { label: 'プライバシーポリシー', path: '/privacy' },
]

const navigate = (path: string) => {
  if (props.goTo) {
    props.goTo(path)
    return
  }

  window.location.href = path
}
</script>

<template>
  <footer class="border-t border-neutral-200 bg-neutral-100">
    <div
      class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-5 py-10 md:flex-row md:items-end md:justify-between md:px-8"
    >
      <div>
        <p class="text-lg font-black text-red-700">麺ナビ</p>
        <p class="mt-4 text-sm text-neutral-500">© 2026 麺ナビ (Men-Navi). All rights reserved.</p>
      </div>
      <nav
        aria-label="フッターナビゲーション"
        class="flex flex-wrap gap-x-7 gap-y-3 text-sm font-bold text-neutral-600"
      >
        <button
          v-for="link in footerLinks"
          :key="link.path"
          class="hover:text-red-700"
          type="button"
          @click="navigate(link.path)"
        >
          {{ link.label }}
        </button>
      </nav>
    </div>
  </footer>
</template>
