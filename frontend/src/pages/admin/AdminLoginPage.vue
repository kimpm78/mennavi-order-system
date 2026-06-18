<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import AuthLayout from '../../components/auth/AuthLayout.vue'
import AdminLoginForm from '../../components/admin/AdminLoginForm.vue'
import AdminToast from '../../components/admin/AdminToast.vue'
import ProductModal from '../../components/admin/ProductModal.vue'
import AdminContactMessagesPage from './AdminContactMessagesPage.vue'
import AdminDashboardPage from './AdminDashboardPage.vue'
import AdminLayout from './AdminLayout.vue'
import AdminMenusPage from './AdminMenusPage.vue'
import AdminOrdersPage from './AdminOrdersPage.vue'
import AdminSalesPage from './AdminSalesPage.vue'
import AdminSettingsPage from './AdminSettingsPage.vue'
import AdminStoreCreatePage from './AdminStoreCreatePage.vue'
import { apiRequest, authHeaders } from '../../lib/api'
import { getAdminToken } from '../../lib/authStorage'
import { useAdminPage } from './useAdminPage'
import type { ActiveOrderFilterKey, AdminPageKey, DashboardOrderView } from './adminTypes'

const props = defineProps<{
  currentPath: string
  goTo: (path: string) => void
}>()

const adminPagePaths: Record<AdminPageKey, string> = {
  dashboard: '/admin/dashboard',
  orders: '/admin/orders',
  contactMessages: '/admin/contact-messages',
  menus: '/admin/menus',
  storeCreate: '/admin/stores/new',
  sales: '/admin/sales',
  settings: '/admin/settings',
}

const routeAdminPage = computed(() => adminPageFromPath(props.currentPath))

type AdminNotification = {
  id: string
  orderId: number | string
  title: string
  message: string
  tone: 'order' | 'success' | 'warning'
  time?: string
}

const readNotificationIds = ref<Set<string>>(new Set())
const pendingOrderDetailId = ref<number | string | null>(null)

function adminPageFromPath(path: string): AdminPageKey {
  const normalizedPath = path.replace(/\/$/, '')
  const matchedPage = (Object.entries(adminPagePaths) as Array<[AdminPageKey, string]>)
    .find(([, pagePath]) => pagePath === normalizedPath)?.[0]

  return matchedPage ?? 'dashboard'
}

function goToAdminPage(page: AdminPageKey) {
  props.goTo(adminPagePaths[page])
}

const {
  activeAdminPage,
  activeAdminTitle,
  activeOrderFilter,
  activeOrderFilterItems,
  admin,
  adminCategories,
  adminPageLoading,
  adminStoreRowsForPage,
  adminToast,
  cancelOrder,
  closeProductModal,
  contactMessages,
  createAdminProduct,
  createAdminStore,
  deleteAdminProduct,
  editAdminProduct,
  editingProductId,
  errorMessage,
  filteredActiveOrders,
  form,
  formatChangeRate,
  formatElapsed,
  formatPrice,
  handleUpdateOrderStatus,
  isProductModalOpen,
  kitchenBarRows,
  kitchenStatus,
  lastUpdatedLabel,
  loading,
  logout,
  mainVisualForm,
  mainVisualSetting,
  menuRowsForPage,
  nextOrderStatus,
  orderActionLabel,
  ordersForPage,
  orderStatusLabel,
  paymentStatusClass,
  paymentStatusLabel,
  productForm,
  salesRowsForPage,
  salesAnalysisBarRows,
  saveAdminStoreProfile,
  saveMainVisualSetting,
  selectAdminPage,
  selectAdminStore,
  selectedAdminStore,
  settingRows,
  setCreateStoreImage,
  setMainVisualImage,
  storeForm,
  storeProfileForm,
  syncAdminPageFromRoute,
  submitAdminLogin,
  successMessage,
  summaryCards,
  updateContactMessageStatus,
  uploadAdminProductImage,
  uploadAdminStoreImage,
} = useAdminPage(routeAdminPage.value, goToAdminPage)

watch(routeAdminPage, async (page) => {
  if (admin.value) {
    await syncAdminPageFromRoute(page)
  }
})

watch(
  () => admin.value?.email,
  async () => {
    await loadReadNotificationIds()
  },
)

async function handleAdminLogout() {
  await logout()
  props.goTo('/admin/login')
}

const adminNotifications = computed<AdminNotification[]>(() => {
  const notifications: AdminNotification[] = []

  ordersForPage.value
    .filter(isTodayOrder)
    .forEach((order) => {
      const status = order.order_status || order.status
      const orderNumber = order.order_number || order.number || order.order_id
      const elapsedMinutes = Number(order.elapsed_minutes ?? 0)

      if (status === 'received') {
        notifications.push({
          id: `order-${order.order_id}`,
          orderId: order.order_id,
          title: '新規注文',
          message: `注文 #${orderNumber} が入りました。`,
          tone: 'order',
          time: formatNotificationTime(order.ordered_at || order.created_at),
        })
      }

      if (status === 'completed') {
        notifications.push({
          id: `completed-${order.order_id}`,
          orderId: order.order_id,
          title: '完了',
          message: `注文 #${orderNumber} が完了しました。`,
          tone: 'success',
          time: formatNotificationTime(order.received_at || order.ordered_at || order.created_at),
        })
      }

      if (['received', 'cooking', 'delivering'].includes(status) && elapsedMinutes >= 20) {
        notifications.push({
          id: `delay-${order.order_id}`,
          orderId: order.order_id,
          title: '遅延アラート',
          message: `注文 #${orderNumber} が${elapsedMinutes}分経過しています。対応状況を確認してください。`,
          tone: 'warning',
          time: `${elapsedMinutes}分経過`,
        })
      }
    })

  return notifications.filter((notification) => !readNotificationIds.value.has(notification.id))
})

async function handleOpenNotification(notification: AdminNotification) {
  readNotificationIds.value = new Set([...readNotificationIds.value, notification.id])
  await saveReadNotificationId(notification.id).catch(() => undefined)
  pendingOrderDetailId.value = notification.orderId
  await selectAdminPage('orders')
}

function clearPendingOrderDetailId() {
  pendingOrderDetailId.value = null
}

async function loadReadNotificationIds() {
  const token = getAdminToken()
  if (!token) {
    readNotificationIds.value = new Set()
    return
  }

  try {
    const response = await apiRequest<{ notification_ids: string[] }>('/admin/notification-reads', {
      headers: authHeaders(token),
    })
    readNotificationIds.value = new Set(response.notification_ids)
  } catch {
    readNotificationIds.value = new Set()
  }
}

async function saveReadNotificationId(notificationId: string) {
  const token = getAdminToken()
  if (!token) {
    return
  }

  await apiRequest('/admin/notification-reads', {
    method: 'POST',
    headers: authHeaders(token),
    body: JSON.stringify({ notification_id: notificationId }),
  })
}

function isTodayOrder(order: DashboardOrderView) {
  const value = order.ordered_at || order.created_at
  if (!value) {
    return false
  }

  const date = new Date(value)
  const today = new Date()

  if (Number.isNaN(date.getTime())) {
    return false
  }

  return date.getFullYear() === today.getFullYear() &&
    date.getMonth() === today.getMonth() &&
    date.getDate() === today.getDate()
}

function formatNotificationTime(value: string | null | undefined) {
  if (!value) {
    return ''
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return ''
  }

  const hour = String(date.getHours()).padStart(2, '0')
  const minute = String(date.getMinutes()).padStart(2, '0')

  return `${hour}:${minute}`
}
</script>

<template>
  <AdminLayout
    v-if="admin"
    :admin="admin"
    :active-page="activeAdminPage"
    :title="activeAdminTitle"
    :loading="loading || adminPageLoading"
    :notifications="adminNotifications"
    @select-page="selectAdminPage"
    @open-notification="handleOpenNotification"
    @logout="handleAdminLogout"
  >
    <div class="grid gap-4">
      <p
        v-if="errorMessage"
        class="rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm font-black text-red-700"
      >
        {{ errorMessage }}
      </p>

      <p
        v-if="adminPageLoading"
        class="rounded-lg border border-red-100 bg-white px-4 py-3 text-sm font-black text-neutral-500"
      >
        読み込み中...
      </p>

      <AdminDashboardPage
        v-if="activeAdminPage === 'dashboard'"
        :summary-cards="summaryCards"
        :filtered-active-orders="filteredActiveOrders"
        :active-order-filter="activeOrderFilter"
        :active-order-filter-items="activeOrderFilterItems"
        :kitchen-bars="kitchenBarRows"
        :kitchen-status="kitchenStatus"
        :last-updated-label="lastUpdatedLabel"
        :admin-page-loading="adminPageLoading"
        :format-price="formatPrice"
        :format-change-rate="formatChangeRate"
        :format-elapsed="formatElapsed"
        :order-status-label="orderStatusLabel"
        :payment-status-label="paymentStatusLabel"
        :payment-status-class="paymentStatusClass"
        :next-order-status="nextOrderStatus"
        :order-action-label="orderActionLabel"
        @update-active-order-filter="activeOrderFilter = $event as ActiveOrderFilterKey"
        @update-order-status="handleUpdateOrderStatus"
      />

      <AdminOrdersPage
        v-else-if="activeAdminPage === 'orders'"
        :orders="ordersForPage"
        :detail-order-id="pendingOrderDetailId"
        :admin-page-loading="adminPageLoading"
        :format-price="formatPrice"
        :format-elapsed="formatElapsed"
        :order-status-label="orderStatusLabel"
        :payment-status-label="paymentStatusLabel"
        :payment-status-class="paymentStatusClass"
        :next-order-status="nextOrderStatus"
        :order-action-label="orderActionLabel"
        @update-order-status="handleUpdateOrderStatus"
        @cancel-order="cancelOrder"
        @detail-order-opened="clearPendingOrderDetailId"
      />

      <AdminContactMessagesPage
        v-else-if="activeAdminPage === 'contactMessages'"
        :contact-messages="contactMessages"
        :admin-page-loading="adminPageLoading"
        @update-status="updateContactMessageStatus"
      />

      <AdminMenusPage
        v-else-if="activeAdminPage === 'menus'"
        :admin-stores="adminStoreRowsForPage"
        :selected-admin-store="selectedAdminStore"
        :store-profile-form="storeProfileForm"
        :menu-rows="menuRowsForPage"
        :admin-page-loading="adminPageLoading"
        :format-price="formatPrice"
        @select-store="selectAdminStore"
        @upload-store-image="uploadAdminStoreImage"
        @save-store-profile="saveAdminStoreProfile"
        @open-product-modal="editAdminProduct"
        @delete-menu="deleteAdminProduct"
      />

      <AdminStoreCreatePage
        v-else-if="activeAdminPage === 'storeCreate'"
        :store-form="storeForm"
        :admin-page-loading="adminPageLoading"
        @create-store="createAdminStore"
        @upload-store-image="setCreateStoreImage"
      />

      <AdminSalesPage
        v-else-if="activeAdminPage === 'sales'"
        :sales-rows="salesRowsForPage"
        :kitchen-bars="salesAnalysisBarRows"
        :admin-page-loading="adminPageLoading"
        :format-price="formatPrice"
        :format-change-rate="formatChangeRate"
      />

      <AdminSettingsPage
        v-else
        :setting-rows="settingRows"
        :main-visual-setting="mainVisualSetting"
        :main-visual-form="mainVisualForm"
        :admin-page-loading="adminPageLoading"
        @upload-main-visual-image="setMainVisualImage"
        @save-main-visual-setting="saveMainVisualSetting"
      />
    </div>

    <ProductModal
      v-if="isProductModalOpen"
      :product-form="productForm"
      :category-options="adminCategories"
      :is-edit="editingProductId !== null"
      :loading="adminPageLoading"
      @close="closeProductModal"
      @save="createAdminProduct"
      @upload-image="uploadAdminProductImage"
    />

    <AdminToast
      :show="adminToast.show"
      :message="adminToast.message"
      :type="adminToast.type"
      @close="adminToast.show = false"
    />
  </AdminLayout>

  <AuthLayout
    v-if="!admin"
    eyebrow="Mennavi Admin"
    title="管理者ログイン"
    lead="商品、カテゴリ、注文情報を管理するための管理者専用画面です。"
    panel-label="Admin Account"
    panel-title="管理者ログイン"
    brand-image="/images/admin_ramen.png"
  >
    <AdminLoginForm
      :form="form"
      :loading="loading"
      :error-message="errorMessage"
      :success-message="successMessage"
      @submit="submitAdminLogin"
      @user-login="props.goTo('/login')"
    />
  </AuthLayout>
</template>
