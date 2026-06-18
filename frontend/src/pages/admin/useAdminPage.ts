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
  AdminContactMessageRow,
  AdminOrderRow,
  AdminPageKey,
  AdminProductRow,
  AdminSalesRow,
  AdminSettingRow,
  AdminStoreRow,
  DashboardOrderView,
  MainVisualSetting,
  MenuRowView,
  OrderActionTarget,
  ToastType,
} from './adminTypes'

type TimeRangeMetric = {
  label: string
  order_count: number
  load: number
}

const MAX_IMAGE_FILE_SIZE_MB = 5
const MAX_IMAGE_FILE_SIZE_BYTES = MAX_IMAGE_FILE_SIZE_MB * 1024 * 1024

export function useAdminPage(
  initialAdminPage: AdminPageKey = 'dashboard',
  onAdminPageRouteChange?: (page: AdminPageKey) => void,
) {
  const loading = ref(false)
  const errorMessage = ref('')
  const successMessage = ref('')
  const admin = ref<User | null>(null)
  const activeAdminPage = ref<AdminPageKey>(initialAdminPage)

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
  const kitchenBars = ref<Array<number | TimeRangeMetric>>([
    { label: '10:00-12:00', order_count: 0, load: 0 },
    { label: '12:00-14:00', order_count: 0, load: 0 },
    { label: '14:00-16:00', order_count: 0, load: 0 },
    { label: '16:00-19:00', order_count: 0, load: 0 },
    { label: '19:00-22:00', order_count: 0, load: 0 },
  ])
  const adminStores = ref<AdminStoreRow[]>([])
  const adminCategories = ref<AdminCategoryRow[]>([])
  const menuRows = ref<AdminProductRow[]>([])
  const salesRows = ref<AdminSalesRow[]>([])
  const contactMessages = ref<AdminContactMessageRow[]>([])
  const settingRows = ref<AdminSettingRow[]>([])
  const mainVisualSetting = ref<MainVisualSetting>({
    title: '今日の一杯を見つけよう',
    description: '厳選された究極のラーメン店ガイド。あなたの気分に合わせた最高の一杯をご提案します。',
    image_path: null,
  })
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
    phone: '',
    weekday_hours: '11:00-15:00\n17:00-22:00',
    weekend_hours: '11:00-22:00',
    holidays: ['火曜日'] as string[],
    budget_min: '',
    budget_max: '',
    image: null as File | null,
    image_path: '',
  })

  const storeProfileForm = reactive({
    name: '',
    description: '',
    address: '',
    phone: '',
    weekday_hours: '',
    weekend_hours: '',
    holidays: [] as string[],
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
    image: null as File | null,
    imagePath: '',
    imageName: '',
    status: 'active',
    isDisplay: true,
  })

  const mainVisualForm = reactive({
    title: '',
    description: '',
    image: null as File | null,
    image_path: '',
    image_name: '',
  })

  const activeAdminTitle = computed(() => {
    const labels: Record<AdminPageKey, string> = {
      dashboard: 'ダッシュボード',
      orders: '注文管理',
      contactMessages: 'お問い合わせ',
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

  const activeOrdersForPage = computed(() =>
    ordersForPage.value.filter((order) => ['received', 'cooking', 'delivering'].includes(order.status)),
  )

  const filteredActiveOrders = computed(() => {
    if (activeOrderFilter.value === 'all') {
      return activeOrdersForPage.value
    }

    return activeOrdersForPage.value.filter((order) => order.status === activeOrderFilter.value)
  })

  const activeOrderFilterItems = computed(() => [
    { key: 'all', label: `すべて ${activeOrdersForPage.value.length}` },
    { key: 'received', label: `新規注文 ${activeOrdersForPage.value.filter((order) => order.status === 'received').length}` },
    { key: 'cooking', label: `調理中 ${activeOrdersForPage.value.filter((order) => order.status === 'cooking').length}` },
    { key: 'delivering', label: `配送中 ${activeOrdersForPage.value.filter((order) => order.status === 'delivering').length}` },
  ])

  function timeRangeBarRows(values: Array<number | TimeRangeMetric>) {
    if (values.length > 0 && typeof values[0] === 'object') {
      return (values as TimeRangeMetric[]).map((value) => ({
        label: value.label,
        value: value.load,
        orderCount: value.order_count,
        max: 100,
      }))
    }

    const intervals = [
      { label: '10:00-12:00', start: 0, end: 2 },
      { label: '12:00-14:00', start: 2, end: 4 },
      { label: '14:00-16:00', start: 4, end: 6 },
      { label: '16:00-19:00', start: 6, end: 9 },
      { label: '19:00-22:00', start: 9, end: 12 },
    ]

    return intervals.map((interval) => {
      const intervalValues = (values as number[]).slice(interval.start, interval.end)
      const value = intervalValues.length
        ? Math.round(intervalValues.reduce((total, current) => total + current, 0) / intervalValues.length)
        : 0

      return {
        label: interval.label,
        value,
        orderCount: 0,
        max: 100,
      }
    })
  }

  const kitchenBarRows = computed(() => timeRangeBarRows(kitchenBars.value))

  const salesAnalysisBarRows = computed(() => timeRangeBarRows(kitchenBars.value))

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
      onAdminPageRouteChange?.(activeAdminPage.value)
      await loadAdminPage(activeAdminPage.value)
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
      onAdminPageRouteChange?.(activeAdminPage.value)
      await loadAdminPage(activeAdminPage.value)
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : '処理に失敗しました。'
    } finally {
      loading.value = false
    }
  }

  async function selectAdminPage(page: AdminPageKey) {
    activeAdminPage.value = page
    onAdminPageRouteChange?.(page)
    await loadAdminPage(page)
  }

  async function syncAdminPageFromRoute(page: AdminPageKey) {
    if (activeAdminPage.value === page) {
      return
    }

    await setAdminPage(page)
  }

  async function setAdminPage(page: AdminPageKey) {
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
          kitchen_bars: Array<number | TimeRangeMetric>
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

      if (page === 'contactMessages') {
        const response = await apiRequest<{ contact_messages: AdminContactMessageRow[] }>('/admin/contact-messages', {
          headers: authHeaders(token),
        })
        contactMessages.value = response.contact_messages
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
        const response = await apiRequest<{ summary: AdminSalesRow[]; bars: Array<number | TimeRangeMetric> }>('/admin/sales', {
          headers: authHeaders(token),
        })
        salesRows.value = response.summary
        kitchenBars.value = response.bars
        return
      }

      if (page === 'settings') {
        const response = await apiRequest<{
          settings: AdminSettingRow[]
          main_visual_setting: MainVisualSetting
        }>('/admin/settings', {
          headers: authHeaders(token),
        })
        settingRows.value = response.settings
        mainVisualSetting.value = response.main_visual_setting
        syncMainVisualForm(response.main_visual_setting)
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
    storeProfileForm.phone = store.phone ?? ''
    storeProfileForm.weekday_hours = store.weekday_hours ?? ''
    storeProfileForm.weekend_hours = store.weekend_hours ?? ''
    storeProfileForm.holidays = parseHolidayValue(store.holiday)
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
          phone: storeProfileForm.phone || null,
          weekday_hours: storeProfileForm.weekday_hours || null,
          weekend_hours: storeProfileForm.weekend_hours || null,
          holiday: formatHolidayValue(storeProfileForm.holidays),
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
          phone: storeForm.phone || null,
          weekday_hours: storeForm.weekday_hours || null,
          weekend_hours: storeForm.weekend_hours || null,
          holiday: formatHolidayValue(storeForm.holidays),
          budget_label: formatBudgetLabel(storeForm.budget_min, storeForm.budget_max),
        }),
      })
      let createdStore = response.store

      if (storeForm.image) {
        createdStore = await uploadStoreImageToStore(response.store.id, storeForm.image)
      }

      resetStoreForm()
      await selectAdminPage('menus')
      selectAdminStore({ ...createdStore, store_id: createdStore.id })
      showAdminToast('店舗を追加しました。')
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : '店舗登録に失敗しました。'
    } finally {
      adminPageLoading.value = false
    }
  }

  function resetStoreForm() {
    storeForm.name = ''
    storeForm.description = ''
    storeForm.address = ''
    storeForm.phone = ''
    storeForm.weekday_hours = '11:00-15:00\n17:00-22:00'
    storeForm.weekend_hours = '11:00-22:00'
    storeForm.holidays = ['火曜日']
    storeForm.budget_min = ''
    storeForm.budget_max = ''
    storeForm.image = null
    if (storeForm.image_path.startsWith('blob:')) {
      URL.revokeObjectURL(storeForm.image_path)
    }
    storeForm.image_path = ''
  }

  function parseHolidayValue(value?: string | null) {
    return (value ?? '')
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean)
  }

  function formatHolidayValue(value: string[]) {
    return value.length ? value.join(',') : null
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
      const response = await apiRequest<{ product: AdminProductRow }>(
        isEditing ? `/admin/products/${editingProductId.value}` : '/admin/products',
        {
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
        },
      )

      if (productForm.image) {
        const updatedProduct = await uploadProductImageToProduct(response.product.id, productForm.image)
        replaceAdminProduct(updatedProduct)
      }

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
    productForm.image = null
    productForm.imagePath = menu.imagePath ?? ''
    productForm.imageName = ''
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
    productForm.image = null
    if (productForm.imagePath.startsWith('blob:')) {
      URL.revokeObjectURL(productForm.imagePath)
    }
    productForm.imagePath = ''
    productForm.imageName = ''
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

    if (!validateImageFileSize(file, input)) {
      return
    }

    adminPageLoading.value = true
    errorMessage.value = ''

    try {
      const updatedStore = await uploadStoreImageToStore(selectedAdminStore.value.id, file)
      replaceAdminStore(updatedStore)
      selectAdminStore({ ...updatedStore, store_id: updatedStore.id })
      showAdminToast('店舗画像を保存しました。')
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : '店舗画像のアップロードに失敗しました。'
    } finally {
      input.value = ''
      adminPageLoading.value = false
    }
  }

  function setCreateStoreImage(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]

    if (!file) {
      return
    }

    if (!validateImageFileSize(file, input)) {
      return
    }

    if (storeForm.image_path.startsWith('blob:')) {
      URL.revokeObjectURL(storeForm.image_path)
    }

    storeForm.image = file
    storeForm.image_path = URL.createObjectURL(file)
    input.value = ''
  }

  async function uploadStoreImageToStore(storeId: number, file: File) {
    const token = getAdminToken()

    if (!token) {
      throw new Error('管理者認証が必要です。')
    }

    const formData = new FormData()
    formData.append('image', file)

    const response = await fetch(`${apiBaseUrl}/admin/stores/${storeId}/image`, {
      method: 'POST',
      headers: authHeaders(token),
      body: formData,
    })
    const data = await response.json().catch(() => ({}))

    if (!response.ok) {
      throw new Error(data.message ?? '店舗画像のアップロードに失敗しました。')
    }

    return data.store as AdminStoreRow
  }

  function replaceAdminStore(store: AdminStoreRow) {
    const storeId = store.id ?? store.store_id
    const index = adminStores.value.findIndex((item) => (item.id ?? item.store_id) === storeId)

    if (index >= 0) {
      adminStores.value.splice(index, 1, store)
      return
    }

    adminStores.value.push(store)
  }

  function uploadAdminProductImage(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]

    if (!file) {
      return
    }

    if (!validateImageFileSize(file, input)) {
      return
    }

    if (productForm.imagePath.startsWith('blob:')) {
      URL.revokeObjectURL(productForm.imagePath)
    }

    productForm.image = file
    productForm.imagePath = URL.createObjectURL(file)
    productForm.imageName = file.name
    input.value = ''
  }

  async function uploadProductImageToProduct(productId: number, file: File) {
    const token = getAdminToken()

    if (!token) {
      throw new Error('管理者認証が必要です。')
    }

    const formData = new FormData()
    formData.append('image', file)

    const response = await fetch(`${apiBaseUrl}/admin/products/${productId}/image`, {
      method: 'POST',
      headers: authHeaders(token),
      body: formData,
    })
    const data = await response.json().catch(() => ({}))

    if (!response.ok) {
      throw new Error(data.message ?? 'メニュー画像のアップロードに失敗しました。')
    }

    return normalizeAdminProduct(data.product)
  }

  function validateImageFileSize(file: File, input: HTMLInputElement) {
    if (file.size <= MAX_IMAGE_FILE_SIZE_BYTES) {
      return true
    }

    input.value = ''
    showAdminToast(`画像サイズが${MAX_IMAGE_FILE_SIZE_MB}MBを超えています。${MAX_IMAGE_FILE_SIZE_MB}MB以下の画像を選択してください。`, 'warning')
    return false
  }

  function syncMainVisualForm(setting: MainVisualSetting) {
    mainVisualForm.title = setting.title
    mainVisualForm.description = setting.description ?? ''
    mainVisualForm.image = null
    mainVisualForm.image_path = setting.image_path ?? ''
    mainVisualForm.image_name = ''
  }

  function setMainVisualImage(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]

    if (!file) {
      return
    }

    if (!validateImageFileSize(file, input)) {
      return
    }

    if (mainVisualForm.image_path.startsWith('blob:')) {
      URL.revokeObjectURL(mainVisualForm.image_path)
    }

    mainVisualForm.image = file
    mainVisualForm.image_path = URL.createObjectURL(file)
    mainVisualForm.image_name = file.name
    input.value = ''
  }

  async function saveMainVisualSetting() {
    const token = getAdminToken()
    if (!token) {
      return
    }

    adminPageLoading.value = true
    errorMessage.value = ''

    try {
      const response = await apiRequest<{ main_visual_setting: MainVisualSetting }>('/admin/main-visual-setting', {
        method: 'PATCH',
        headers: authHeaders(token),
        body: JSON.stringify({
          title: mainVisualForm.title,
          description: mainVisualForm.description || null,
        }),
      })

      let savedSetting = response.main_visual_setting

      if (mainVisualForm.image) {
        savedSetting = await uploadMainVisualImage(mainVisualForm.image)
      }

      mainVisualSetting.value = savedSetting
      syncMainVisualForm(savedSetting)
      showAdminToast('メイン画面設定を保存しました。')
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : 'メイン画面設定の保存に失敗しました。'
    } finally {
      adminPageLoading.value = false
    }
  }

  async function uploadMainVisualImage(file: File) {
    const token = getAdminToken()

    if (!token) {
      throw new Error('管理者認証が必要です。')
    }

    const formData = new FormData()
    formData.append('image', file)

    const response = await fetch(`${apiBaseUrl}/admin/main-visual-setting/image`, {
      method: 'POST',
      headers: authHeaders(token),
      body: formData,
    })
    const data = await response.json().catch(() => ({}))

    if (!response.ok) {
      throw new Error(data.message ?? 'メイン画像のアップロードに失敗しました。')
    }

    return data.main_visual_setting as MainVisualSetting
  }

  function replaceAdminProduct(product: AdminProductRow) {
    const index = menuRows.value.findIndex((item) => item.id === product.id)

    if (index >= 0) {
      menuRows.value.splice(index, 1, product)
      return
    }

    menuRows.value.push(product)
  }

  function normalizeAdminProduct(product: AdminProductRow & { image_path?: string | null }) {
    return {
      ...product,
      imagePath: product.imagePath ?? product.image_path ?? null,
    }
  }

  async function handleUpdateOrderStatus(order: OrderActionTarget, deliveryStaffName?: string) {
    const nextStatus = nextOrderStatus(order)

    if (!nextStatus) {
      return
    }

    await updateOrderStatus(order, nextStatus, deliveryStaffName)
  }

  async function cancelOrder(order: OrderActionTarget) {
    await updateOrderStatus(order, 'canceled')
  }

  async function updateContactMessageStatus(message: AdminContactMessageRow, status: string) {
    const token = getAdminToken()
    if (!token) {
      return
    }

    adminPageLoading.value = true
    errorMessage.value = ''

    try {
      const response = await apiRequest<{ contact_message: AdminContactMessageRow }>(
        `/admin/contact-messages/${message.id}`,
        {
          method: 'PATCH',
          headers: authHeaders(token),
          body: JSON.stringify({ status }),
        },
      )

      const index = contactMessages.value.findIndex((item) => item.id === message.id)
      if (index >= 0) {
        contactMessages.value.splice(index, 1, response.contact_message)
      }
      showAdminToast('お問い合わせの対応状況を更新しました。')
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : 'お問い合わせの更新に失敗しました。'
    } finally {
      adminPageLoading.value = false
    }
  }

  async function updateOrderStatus(
    order: OrderActionTarget,
    status: 'cooking' | 'delivering' | 'completed' | 'canceled',
    deliveryStaffName?: string,
  ) {
    const token = getAdminToken()
    const orderId = order.id ?? Number(order.order_id)
    if (!token || !orderId) {
      return
    }

    adminPageLoading.value = true
    errorMessage.value = ''

    try {
      const response = await apiRequest<{ order: AdminOrderRow }>(`/admin/orders/${orderId}/status`, {
        method: 'PATCH',
        headers: authHeaders(token),
        body: JSON.stringify({
          order_status: status,
          ...(status === 'delivering' ? { delivery_staff_name: deliveryStaffName || '佐藤A' } : {}),
        }),
      })

      const index = orders.value.findIndex((item) => item.id === response.order.id)
      if (index >= 0) {
        orders.value.splice(index, 1, response.order)
      }

      await loadAdminPage(activeAdminPage.value)
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : '注文ステータスの更新に失敗しました。'
    } finally {
      adminPageLoading.value = false
    }
  }

  function nextOrderStatus(order: OrderActionTarget): 'cooking' | 'delivering' | 'completed' | undefined {
    const status = order.status ?? order.order_status

    if (status === 'completed' || status === 'canceled') {
      return undefined
    }

    if (status === 'cooking') {
      return isDeliveryOrder(order) ? 'delivering' : 'completed'
    }

    if (status === 'delivering') {
      return 'completed'
    }

    return 'cooking'
  }

  function orderActionLabel(order: OrderActionTarget) {
    const status = order.status ?? order.order_status

    if (status === 'completed' || status === 'canceled') {
      return '対応済み'
    }

    if (status === 'cooking') {
      return isDeliveryOrder(order) ? '配送開始' : '完了'
    }

    return status === 'delivering' ? '完了' : '調理開始'
  }

  function isDeliveryOrder(order: OrderActionTarget) {
    return order.receipt_type === 'delivery' || order.type === 'デリバリー'
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
    cancelOrder,
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
  }
}
