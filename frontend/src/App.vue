<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import AdminLoginPage from './pages/admin/AdminLoginPage.vue'
import UserLoginPage from './pages/user/LoginPage.vue'

const currentPath = ref(normalizePath(window.location.pathname))

onMounted(() => {
  window.addEventListener('popstate', syncPath)
})

onUnmounted(() => {
  window.removeEventListener('popstate', syncPath)
})

function goTo(path: string) {
  window.history.pushState({}, '', path)
  currentPath.value = normalizePath(path)
}

function syncPath() {
  currentPath.value = normalizePath(window.location.pathname)
}

function normalizePath(path: string) {
  if (path === '/' || path === '') {
    return '/login'
  }

  return path
}
</script>

<template>
  <AdminLoginPage v-if="currentPath === '/admin/login'" :go-to="goTo" />
  <UserLoginPage v-else :go-to="goTo" />
</template>
