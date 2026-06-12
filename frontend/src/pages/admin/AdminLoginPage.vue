<script setup lang="ts">
import AuthLayout from '../../components/auth/AuthLayout.vue'
import AdminLoginForm from '../../components/admin/AdminLoginForm.vue'
import AdminToast from '../../components/admin/AdminToast.vue'
import ProductModal from '../../components/admin/ProductModal.vue'
import AdminDashboardPage from './AdminDashboardPage.vue'
import AdminLayout from './AdminLayout.vue'
import AdminMenusPage from './AdminMenusPage.vue'
import AdminOrdersPage from './AdminOrdersPage.vue'
import AdminSalesPage from './AdminSalesPage.vue'
import AdminSettingsPage from './AdminSettingsPage.vue'
import AdminStoreCreatePage from './AdminStoreCreatePage.vue'
import { useAdminPage } from './useAdminPage'
import type { ActiveOrderFilterKey } from './adminTypes'

defineProps<{
  goTo: (path: string) => void
}>()

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
  closeProductModal,
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
  menuRowsForPage,
  nextOrderStatus,
  orderActionLabel,
  ordersForPage,
  orderStatusLabel,
  paymentStatusClass,
  paymentStatusLabel,
  productForm,
  salesRowsForPage,
  saveAdminStoreProfile,
  selectAdminPage,
  selectAdminStore,
  selectedAdminStore,
  settingRows,
  storeForm,
  storeProfileForm,
  submitAdminLogin,
  successMessage,
  summaryCards,
  uploadAdminProductImage,
  uploadAdminStoreImage,
} = useAdminPage()
</script>

<template>
  <AdminLayout
    v-if="admin"
    :admin="admin"
    :active-page="activeAdminPage"
    :title="activeAdminTitle"
    :loading="loading || adminPageLoading"
    @select-page="selectAdminPage"
    @logout="logout"
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
        :admin-page-loading="adminPageLoading"
        :format-price="formatPrice"
        :format-elapsed="formatElapsed"
        :order-status-label="orderStatusLabel"
        :payment-status-label="paymentStatusLabel"
        :payment-status-class="paymentStatusClass"
        :next-order-status="nextOrderStatus"
        :order-action-label="orderActionLabel"
        @update-order-status="handleUpdateOrderStatus"
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
        @upload-store-image="uploadAdminStoreImage"
      />

      <AdminSalesPage
        v-else-if="activeAdminPage === 'sales'"
        :sales-rows="salesRowsForPage"
        :kitchen-bars="kitchenBarRows"
        :admin-page-loading="adminPageLoading"
        :format-price="formatPrice"
        :format-change-rate="formatChangeRate"
      />

      <AdminSettingsPage
        v-else
        :setting-rows="settingRows"
        :admin-page-loading="adminPageLoading"
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
  >
    <AdminLoginForm
      :form="form"
      :loading="loading"
      :error-message="errorMessage"
      :success-message="successMessage"
      @submit="submitAdminLogin"
      @user-login="goTo('/login')"
    />
  </AuthLayout>
</template>
