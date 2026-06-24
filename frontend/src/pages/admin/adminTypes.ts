export type AdminPageKey = 'dashboard' | 'orders' | 'contactMessages' | 'menus' | 'storeCreate' | 'sales' | 'settings'
export type ActiveOrderFilterKey = 'all' | 'received' | 'cooking' | 'delivering'
export type ToastType = 'success' | 'error' | 'warning' | 'info'

export type AdminNotification = {
  id: string
  orderId?: number | string
  title: string
  message: string
  tone: 'order' | 'success' | 'warning'
  time?: string
}

export type AdminOrderRow = {
  id?: number
  number?: string
  order_number?: string
  customer_name?: string | null
  store_name?: string | null
  customer_phone?: string | null
  recipient_phone?: string | null
  shipping_address?: string | null
  shipping_address_snapshot?: string | null
  title: string
  note?: string
  type: string
  elapsed_minutes?: number
  status: string
  order_status?: string | null
  payment_status?: string
  receipt_type?: string
  delivery_staff_name?: string | null
  delivered_at?: string | null
  received_at?: string | null
  total_amount?: number
  ordered_at?: string | null
  created_at?: string | null
}

export type AdminProductRow = {
  id: number
  name: string
  store?: string
  store_id?: number
  category_id?: number
  category: string
  description?: string | null
  imagePath?: string | null
  price: number
  status: string
}

export type AdminStoreRow = {
  id: number
  store_id?: number
  name: string
  description?: string | null
  address?: string | null
  phone?: string | null
  invoice_number?: string | null
  weekday_hours?: string | null
  weekend_hours?: string | null
  holiday?: string | null
  image_path?: string | null
  budget_label?: string | null
  budget_min?: string | number | null
  budget_max?: string | number | null
  is_active: boolean
}

export type AdminCategoryRow = {
  id: number
  name: string
  is_active: boolean
}

export type AdminSalesRow = {
  label: string
  amount: number
  orders: number
  rate: string
}

export type AdminContactMessageRow = {
  id: number
  user_name?: string | null
  name: string
  email: string
  category: string
  order_number?: string | null
  message: string
  status: string
  admin_note?: string | null
  created_at?: string | null
}

export type AdminSettingRow = {
  key: 'admin_name' | 'admin_email' | 'admin_notifications_enabled'
  label: string
  value: string | boolean
  category?: string | null
  description?: string | null
}

export type MainVisualSetting = {
  id?: number | null
  title: string
  description?: string | null
  image_path?: string | null
}

export type DashboardOrderView = AdminOrderRow & {
  order_id: number | string
  order_status: string
  created_at?: string | null
}

export type OrderActionTarget = {
  id?: number | null
  order_id?: number | string | null
  status?: string | null
  order_status?: string | null
  receipt_type?: string | null
  type?: string | null
}

export type MenuRowView = AdminProductRow & {
  isDisplay: boolean
}
