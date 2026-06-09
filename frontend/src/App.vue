<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import AdminLoginPage from './pages/admin/AdminLoginPage.vue'
import UserLoginPage from './pages/user/LoginPage.vue'

const basePath = import.meta.env.BASE_URL.replace(/\/$/, '')
const currentPath = ref(normalizePath(window.location.pathname))

onMounted(() => {
  window.addEventListener('popstate', syncPath)
})

onUnmounted(() => {
  window.removeEventListener('popstate', syncPath)
})

function goTo(path: string) {
  window.history.pushState({}, '', `${basePath}${path}`)
  currentPath.value = normalizePath(path)
}

function syncPath() {
  currentPath.value = normalizePath(window.location.pathname)
}

function normalizePath(path: string) {
  const normalizedPath = basePath && path.startsWith(basePath)
    ? path.slice(basePath.length) || '/'
    : path

  if (normalizedPath === '/' || normalizedPath === '') {
    return '/login'
  }

  return normalizedPath
}
</script>

<template>
  <AdminLoginPage v-if="currentPath === '/admin/login'" :go-to="goTo" />
  <UserLoginPage v-else :go-to="goTo" />
</template>
