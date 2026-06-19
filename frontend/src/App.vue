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
  const nextPath = normalizePath(path)
  const nextBrowserPath = `${basePath}${getBrowserPath(path, nextPath)}`

  if (window.location.pathname !== nextBrowserPath) {
    window.history.pushState({}, '', nextBrowserPath)
  }

  currentPath.value = nextPath
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

  if (normalizedPath === '/admin' || normalizedPath === '/admin/') {
    return '/admin/dashboard'
  }

  return normalizedPath
}

function getBrowserPath(path: string, normalizedPath: string) {
  if (path === '/' || path === '') {
    return '/'
  }

  return normalizedPath
}
</script>

<template>
  <AdminLoginPage
    v-if="currentPath.startsWith('/admin')"
    :current-path="currentPath"
    :go-to="goTo"
  />
  <UserLoginPage v-else :current-path="currentPath" :go-to="goTo" />
</template>
