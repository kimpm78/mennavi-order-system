import { computed, onMounted, reactive, ref, watch } from 'vue'
import { apiBaseUrl, apiRequest, authHeaders } from '../../lib/api'
import { clearAdminToken, getAdminToken, setAdminToken } from '../../lib/authStorage'
import type { AuthResponse, User } from '../../types/auth'
import {
  formatBudgetLabel,
  formatChangeRate,
  formatElapsed,
  formatPrice,
  orderStatusLabel,
  parseBudgetLabel,
  paymentStatusClass,
  paymentStatusLabel,
} from './adminFormatters'
import type {
  ActiveOrderFilterKey,
  AdminCategoryRow,
  AdminOrderRow,
  AdminPageKey,
  AdminProductRow,
  AdminSalesRow,
  AdminSettingRow,
  AdminStoreRow,
  DashboardOrderView,
  MenuRowView,
  OrderActionTarget,
  ToastType,
} from './adminTypes'

export function useAdminPage() {
  const loading = ref(false)
  const errorMessage = ref('')
  const successMessage = ref('')
  const admin = ref<User | null>(null)
  const activeAdminPage = ref<AdminPageKey>('dashboard')

  const form = reactive({
    email: '',
    password: '',
  })

  const dashboardSummary = ref({
    today_orders: 0,
    today_sales: 0,
    today_orders_change_rate: 0,
    today_sales_change_rate: 0,
    average_cooking_minutes: 0,
    kitchen_load: 0,
  })
  const orders = ref<AdminOrderRow[]>([])
  const activeOrderFilter = ref<ActiveOrderFilterKey>('all')
  const kitchenBars = ref<number[]>([12, 18, 24, 30, 36, 42, 36, 30, 24, 18])
  const adminStores = ref<AdminStoreRow[]>([])
  const adminCategories = ref<AdminCategoryRow[]>([])
  const menuRows = ref<AdminProductRow[]>([])
  const salesRows = ref<AdminSalesRow[]>([])
  const settingRows = ref<AdminSettingRow[]>([])
  const adminPageLoading = ref(false)
  const lastUpdatedAt = ref('')
  const selectedAdminStoreId = ref<number | null>(null)
  const editingProductId = ref<number | null>(null)
  const isProductModalOpen = ref(false)
  const adminToast = reactive({
    show: false,
    message: '',
    type: 'success' as ToastType,
  })

  const storeForm = reactive({
    name: '',
    description: '',
    address: '',
    budget_min: '',
    budget_max: '',
  })

  const storeProfileForm = reactive({
    name: '',
    description: '',
    address: '',
    budget_min: '',
    budget_max: '',
  })

  const productForm = reactive({
    store_id: '',
    category_id: '',
    category: '',
    name: '',
    description: '',
    price: '',
    status: 'active',
    isDisplay: true,
  })

  const activeAdminTitle = computed(() => {
    const labels: Record<AdminPageKey, string> = {
      dashboard: 'ダッシュボード',
      orders: '注文管理',
      menus: '店舗・メニュー管理',
      storeCreate: '店舗追加',
      sales: '売上分析',
      settings: '設定',
    }

    return labels[activeAdminPage.value]
  })

  const summaryCards = computed(() => [
    {
      label: '本日の注文数',
      value: dashboardSummary.value.today_orders.toLocaleString('ja-JP'),
      change: dashboardSummary.value.today_orders_change_rate,
      icon: 'orders',
    },
    {
      label: '本日の売上',
      value: formatPrice(dashboardSummary.value.today_sales),
      change: dashboardSummary.value.today_sales_change_rate,
      icon: 'sales',
    },
    {
      label: '平均調理時間',
      value: `${dashboardSummary.value.average_cooking_minutes}分`,
      change: null,
      icon: 'time',
    },
    {
      label: 'キッチン負荷',
      value: `${dashboardSummary.value.kitchen_load}%`,
      change: null,
      icon: 'receipt',
    },
  ])

  const selectedAdminStore = computed(() => {
    const store = adminStores.value.find((item) => item.id === selectedAdminStoreId.value)

    if (!store) {
      return null
    }

    return {
      ...store,
      store_id: store.id,
    }
  })

  const adminStoreRowsForPage = computed(() =>
    adminStores.value.map((store) => ({
      ...store,
      store_id: store.id,
    })),
  )

  const menuRowsForPage = computed<MenuRowView[]>(() => {
    if (!selectedAdminStoreId.value) {
      return []
    }

    return menuRows.value
      .filter((menu) => menu.store_id === selectedAdminStoreId.value)
      .map((menu) => ({
        ...menu,
        isDisplay: menu.status === 'active',
      }))
  })

  const ordersForPage = computed<DashboardOrderView[]>(() =>
    orders.value.map((order) => ({
      ...order,
      order_id: order.id ?? order.order_number ?? order.number ?? '',
      order_status: order.status,
      order_number: order.order_number ?? order.number,
    })),
  )

  const filteredActiveOrders = computed(() => {
    if (activeOrderFilter.value === 'all') {
      return ordersForPage.value
    }

    return ordersForPage.value.filter((order) => order.status === activeOrderFilter.value)
  })

  const activeOrderFilterItems = computed(() => [
    { key: 'all', label: `すべて ${orders.value.length}` },
    { key: 'received', label: `新規注文 ${orders.value.filter((order) => order.status === 'received').length}` },
    { key: 'cooking', label: `調理中 ${orders.value.filter((order) => order.status === 'cooking').length}` },
  ])

  const kitchenBarRows = computed(() =>
    kitchenBars.value.slice(0, 6).map((value, index) => ({
      label: `${10 + index}:00`,
      value,
      max: 100,
    })),
  )

  const kitchenStatus = computed(() => ({
    load: dashboardSummary.value.kitchen_load,
    averageCookingMinutes: dashboardSummary.value.average_cooking_minutes,
    cookingOrderCount: orders.value.filter((order) => order.status === 'cooking').length,
  }))

  const lastUpdatedLabel = computed(() => lastUpdatedAt.value || '未更新')

  const salesRowsForPage = computed(() =>
    salesRows.value.map((row) => ({
      label: row.label,
      order_count: row.orders,
      total_sales: row.amount,
      average_order_amount: row.orders > 0 ? Math.round(row.amount / row.orders) : 0,
      change_rate: row.rate,
    })),
  )

  watch(selectedAdminStore, (store) => {
    if (store) {
      syncStoreProfileForm(store)
    }
  })

  onMounted(async () => {
    const token = getAdminToken()

    if (!token) {
      return
    }

    try {
      const response = await apiRequest<{ user: User }>('/admin/me', {
        headers: authHeaders(token),
      })
      admin.value = response.user
      await loadAdminPage('dashboard')
    } catch {
      clearAdminToken()
    }
  })

  async function submitAdminLogin() {
    loading.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
      const response = await apiRequest<AuthResponse>('/admin/login', {
        method: 'POST',
        body: JSON.stringify(form),
      })

      setAdminToken(response.token)
      admin.value = response.user
      form.password = ''
      successMessage.value = '管理者としてログインしました。'
      await loadAdminPage('dashboard')
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : '処理に失敗しました。'
    } finally {
      loading.value = false
    }
  }

  async function selectAdminPage(page: AdminPageKey) {
    activeAdminPage.value = page
    await loadAdminPage(page)
  }

  async function loadAdminPage(page: AdminPageKey) {
    const token = getAdminToken()
    if (!token) {
      return
    }

    adminPageLoading.value = true
    errorMessage.value = ''

    try {
      if (page === 'dashboard') {
        const response = await apiRequest<{
          summary: typeof dashboardSummary.value
          orders: AdminOrderRow[]
          kitchen_bars: number[]
          last_updated_at?: string | null
        }>('/admin/dashboard', { headers: authHeaders(token) })

        dashboardSummary.value = response.summary
        orders.value = response.orders
        kitchenBars.value = response.kitchen_bars
        updateLastUpdatedAt(response.last_updated_at)
        return
      }

      if (page === 'orders') {
        const response = await apiRequest<{ orders: AdminOrderRow[] }>('/admin/orders', {
          headers: authHeaders(token),
        })
        orders.value = response.orders
        return
      }

      if (page === 'menus') {
        const response = await apiRequest<{
          stores: AdminStoreRow[]
          categories: AdminCategoryRow[]
          products: AdminProductRow[]
        }>('/admin/products', {
          headers: authHeaders(token),
        })
        adminStores.value = response.stores
        adminCategories.value = response.categories
        menuRows.value = response.products

        if (!selectedAdminStoreId.value && response.stores.length) {
          selectAdminStore({ ...response.stores[0], store_id: response.stores[0].id })
        } else if (selectedAdminStore.value) {
          syncStoreProfileForm(selectedAdminStore.value)
        }
        return
      }

      if (page === 'sales') {
        const response = await apiRequest<{ summary: AdminSalesRow[]; bars: number[] }>('/admin/sales', {
          headers: authHeaders(token),
        })
        salesRows.value = response.summary
        kitchenBars.value = response.bars
        return
      }

      if (page === 'settings') {
        const response = await apiRequest<{ settings: AdminSettingRow[] }>('/admin/settings', {
          headers: authHeaders(token),
        })
        settingRows.value = response.settings
      }
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : '管理データの取得に失敗しました。'
    } finally {
      adminPageLoading.value = false
    }
  }

  function updateLastUpdatedAt(value?: string | null) {
    if (!value) {
      lastUpdatedAt.value = ''
      return
    }

    const updatedAt = new Date(value)

    if (Number.isNaN(updatedAt.getTime())) {
      lastUpdatedAt.value = ''
      return
    }

    lastUpdatedAt.value = new Intl.DateTimeFormat('ja-JP', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false,
    }).format(updatedAt)
  }

  function selectAdminStore(store: AdminStoreRow) {
    selectedAdminStoreId.value = store.id ?? store.store_id ?? null
    productForm.store_id = selectedAdminStoreId.value ? String(selectedAdminStoreId.value) : ''
    syncStoreProfileForm(store)
    resetProductForm()
  }

  function syncStoreProfileForm(store: AdminStoreRow) {
    const budget = parseBudgetLabel(store.budget_label)
    storeProfileForm.name = store.name
    storeProfileForm.description = store.description ?? ''
    storeProfileForm.address = store.address ?? ''
    storeProfileForm.budget_min = budget.min
    storeProfileForm.budget_max = budget.max
  }

  function showAdminToast(message: string, type: ToastType = 'success') {
    adminToast.show = true
    adminToast.message = message
    adminToast.type = type
    window.setTimeout(() => {
      if (adminToast.message === message) {
        adminToast.show = false
        adminToast.message = ''
      }
    }, 2400)
  }

  async function saveAdminStoreProfile() {
    const token = getAdminToken()
    if (!token || !selectedAdminStore.value) {
      return
    }

    adminPageLoading.value = true
    errorMessage.value = ''

    try {
      await apiRequest(`/admin/stores/${selectedAdminStore.value.id}`, {
        method: 'PATCH',
        headers: authHeaders(token),
        body: JSON.stringify({
          name: storeProfileForm.name,
          description: storeProfileForm.description || null,
          address: storeProfileForm.address || null,
          budget_label: formatBudgetLabel(storeProfileForm.budget_min, storeProfileForm.budget_max),
          is_active: selectedAdminStore.value.is_active,
        }),
      })

      await loadAdminPage('menus')
      showAdminToast('店舗情報を保存しました。')
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : '店舗情報の保存に失敗しました。'
    } finally {
      adminPageLoading.value = false
    }
  }

  async function createAdminStore() {
    const token = getAdminToken()
    if (!token) {
      return
    }

    adminPageLoading.value = true
    errorMessage.value = ''

    try {
      const response = await apiRequest<{ store: AdminStoreRow }>('/admin/stores', {
        method: 'POST',
        headers: authHeaders(token),
        body: JSON.stringify({
          name: storeForm.name,
          description: storeForm.description || null,
          address: storeForm.address || null,
          budget_label: formatBudgetLabel(storeForm.budget_min, storeForm.budget_max),
        }),
      })
      storeForm.name = ''
      storeForm.description = ''
      storeForm.address = ''
      storeForm.budget_min = ''
      storeForm.budget_max = ''
      activeAdminPage.value = 'menus'
      await loadAdminPage('menus')
      selectAdminStore({ ...response.store, store_id: response.store.id })
      showAdminToast('店舗を追加しました。')
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : '店舗登録に失敗しました。'
    } finally {
      adminPageLoading.value = false
    }
  }

  async function createAdminProduct() {
    const token = getAdminToken()
    if (!token) {
      return
    }

    adminPageLoading.value = true
    errorMessage.value = ''

    try {
      const isEditing = editingProductId.value !== null
      await apiRequest(isEditing ? `/admin/products/${editingProductId.value}` : '/admin/products', {
        method: isEditing ? 'PATCH' : 'POST',
        headers: authHeaders(token),
        body: JSON.stringify({
          store_id: Number(productForm.store_id),
          category_id: Number(productForm.category_id),
          name: productForm.name,
          description: productForm.description || null,
          price: Number(productForm.price),
          status: productForm.isDisplay ? 'active' : productForm.status,
        }),
      })
      resetProductForm()
      await loadAdminPage('menus')
      closeProductModal()
      showAdminToast(isEditing ? 'メニューを更新しました。' : 'メニューを追加しました。')
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : 'メニュー保存に失敗しました。'
    } finally {
      adminPageLoading.value = false
    }
  }

  function openCreateProductModal() {
    resetProductForm()
    isProductModalOpen.value = true
  }

  function editAdminProduct(menu?: MenuRowView) {
    if (!menu) {
      openCreateProductModal()
      return
    }

    editingProductId.value = menu.id
    productForm.store_id = String(menu.store_id ?? selectedAdminStoreId.value ?? '')
    productForm.category_id = String(menu.category_id ?? '')
    productForm.category = menu.category
    productForm.name = menu.name
    productForm.description = menu.description ?? ''
    productForm.price = String(menu.price)
    productForm.status = menu.status
    productForm.isDisplay = menu.status === 'active'
    isProductModalOpen.value = true
  }

  function closeProductModal() {
    isProductModalOpen.value = false
    resetProductForm()
  }

  function resetProductForm() {
    editingProductId.value = null
    productForm.store_id = selectedAdminStoreId.value ? String(selectedAdminStoreId.value) : ''
    productForm.category_id = adminCategories.value[0]?.id ? String(adminCategories.value[0].id) : ''
    productForm.category = adminCategories.value[0]?.name ?? ''
    productForm.name = ''
    productForm.description = ''
    productForm.price = ''
    productForm.status = 'active'
    productForm.isDisplay = true
  }

  async function deleteAdminProduct(menu: MenuRowView) {
    const token = getAdminToken()
    if (!token) {
      return
    }

    adminPageLoading.value = true
    errorMessage.value = ''

    try {
      await apiRequest(`/admin/products/${menu.id}`, {
        method: 'DELETE',
        headers: authHeaders(token),
      })

      if (editingProductId.value === menu.id) {
        resetProductForm()
      }

      await loadAdminPage('menus')
      showAdminToast('メニューを削除しました。')
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : 'メニュー削除に失敗しました。'
    } finally {
      adminPageLoading.value = false
    }
  }

  async function uploadAdminStoreImage(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    const token = getAdminToken()

    if (!file || !token || !selectedAdminStore.value) {
      return
    }

    adminPageLoading.value = true
    errorMessage.value = ''

    try {
      const formData = new FormData()
      formData.append('image', file)

      const response = await fetch(`${apiBaseUrl}/admin/stores/${selectedAdminStore.value.id}/image`, {
        method: 'POST',
        headers: authHeaders(token),
        body: formData,
      })
      const data = await response.json().catch(() => ({}))

      if (!response.ok) {
        throw new Error(data.message ?? '店舗画像のアップロードに失敗しました。')
      }

      await loadAdminPage('menus')
      showAdminToast('店舗画像を保存しました。')
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : '店舗画像のアップロードに失敗しました。'
    } finally {
      input.value = ''
      adminPageLoading.value = false
    }
  }

  function uploadAdminProductImage() {
    showAdminToast('商品画像アップロードは後続対応予定です。', 'info')
  }

  async function handleUpdateOrderStatus(order: OrderActionTarget) {
    const nextStatus = nextOrderStatus(order)

    if (!nextStatus) {
      return
    }

    await updateOrderStatus(order, nextStatus)
  }

  async function updateOrderStatus(order: OrderActionTarget, status: 'cooking' | 'completed') {
    const token = getAdminToken()
    const orderId = order.id ?? Number(order.order_id)
    if (!token || !orderId) {
      return
    }

    adminPageLoading.value = true
    errorMessage.value = ''

    try {
      await apiRequest<{ order: AdminOrderRow }>(`/admin/orders/${orderId}/status`, {
        method: 'PATCH',
        headers: authHeaders(token),
        body: JSON.stringify({ order_status: status }),
      })
      await loadAdminPage(activeAdminPage.value)
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : '注文ステータスの更新に失敗しました。'
    } finally {
      adminPageLoading.value = false
    }
  }

  function nextOrderStatus(order: OrderActionTarget): 'cooking' | 'completed' | undefined {
    const status = order.status ?? order.order_status

    if (status === 'completed' || status === 'canceled') {
      return undefined
    }

    return status === 'cooking' ? 'completed' : 'cooking'
  }

  function orderActionLabel(order: OrderActionTarget) {
    const status = order.status ?? order.order_status

    if (status === 'completed' || status === 'canceled') {
      return '対応済み'
    }

    return status === 'cooking' ? '完了' : '調理開始'
  }

  async function logout() {
    const token = getAdminToken()
    loading.value = true
    errorMessage.value = ''

    try {
      if (token) {
        await apiRequest('/logout', {
          method: 'POST',
          headers: authHeaders(token),
        })
      }
    } finally {
      clearAdminToken()
      admin.value = null
      loading.value = false
      successMessage.value = 'ログアウトしました。'
    }
  }

  return {
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
  }
}
