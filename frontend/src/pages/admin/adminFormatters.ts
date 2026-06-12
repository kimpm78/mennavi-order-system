export function parseBudgetLabel(label?: string | null) {
  const values = (label ?? '').match(/\d[\d,]*/g) ?? []

  return {
    min: values[0]?.replace(/,/g, '') ?? '',
    max: values[1]?.replace(/,/g, '') ?? '',
  }
}

export function formatBudgetLabel(min: string, max: string) {
  if (!min && !max) {
    return null
  }

  const minLabel = min ? `¥${Number(min).toLocaleString('ja-JP')}` : ''
  const maxLabel = max ? `¥${Number(max).toLocaleString('ja-JP')}` : ''

  if (minLabel && maxLabel) {
    return `予算: ${minLabel}〜${maxLabel}`
  }

  return `予算: ${minLabel || maxLabel}`
}

export function formatPrice(value: number) {
  return `¥${Number(value).toLocaleString('ja-JP')}`
}

export function formatChangeRate(value: string | number | null | undefined) {
  if (value === null || value === undefined || value === '') {
    return '-'
  }

  if (typeof value === 'string' && value.includes('%')) {
    return value
  }

  const numericValue = Number(value)
  const sign = numericValue >= 0 ? '+' : ''
  return `前日比 ${sign}${numericValue}%`
}

export function formatElapsed(value?: string | number | null) {
  if (typeof value === 'string') {
    return value
  }

  const minutes = Math.max(0, Math.floor(value ?? 0))
  return `${Math.floor(minutes / 60).toString().padStart(2, '0')}:${(minutes % 60).toString().padStart(2, '0')}`
}

export function orderStatusLabel(status?: string | null) {
  const labels: Record<string, string> = {
    received: '待機中',
    cooking: '調理中',
    completed: '完了',
    canceled: '取消',
  }

  return labels[status ?? ''] ?? status ?? '-'
}

export function paymentStatusLabel(status?: string | null) {
  const labels: Record<string, string> = {
    pending: '未決済',
    paid: 'PAY.JP 決済済み',
    failed: '決済失敗',
    partial_refunded: 'PAY.JP 一部返金',
    refunded: 'PAY.JP 返金済み',
  }

  return labels[status ?? ''] ?? '-'
}

export function paymentStatusClass(status?: string | null) {
  const classes: Record<string, string> = {
    paid: 'bg-emerald-50 text-emerald-700',
    pending: 'bg-amber-50 text-amber-700',
    failed: 'bg-red-100 text-red-700',
    partial_refunded: 'bg-sky-50 text-sky-700',
    refunded: 'bg-neutral-200 text-neutral-700',
  }

  return classes[status ?? ''] ?? 'bg-neutral-100 text-neutral-500'
}
