export type ReceiptPdfOrderItem = {
  subtotal: number
}

export type ReceiptPdfOrder = {
  order_number: string
  total_amount: number
  subtotal_amount?: number
  delivery_fee?: number
  membership_discount_rate?: number
  membership_discount_amount?: number
  delivery_discount_amount?: number
  tax_amount?: number
  payment_status?: string
  payment_method?: string | null
  store_name?: string | null
  store_invoice_number?: string | null
  ordered_at?: string | null
  items: ReceiptPdfOrderItem[]
}

export async function downloadReceiptPdfFile(order: ReceiptPdfOrder) {
  const receiptContent = createReceiptPdfElement(order)
  document.body.appendChild(receiptContent)

  try {
    const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
      import('html2canvas'),
      import('jspdf'),
    ])
    const canvas = await html2canvas(receiptContent, {
      backgroundColor: '#ffffff',
      scale: Math.min(window.devicePixelRatio || 2, 2),
      useCORS: true,
    })
    const imageData = canvas.toDataURL('image/png')
    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    const pageWidth = pdf.internal.pageSize.getWidth()
    const pageHeight = pdf.internal.pageSize.getHeight()
    const margin = 12
    const contentWidth = pageWidth - margin * 2
    const contentHeight = (canvas.height * contentWidth) / canvas.width
    const availableHeight = pageHeight - margin * 2
    let heightLeft = contentHeight
    let imageTop = margin

    pdf.addImage(imageData, 'PNG', margin, imageTop, contentWidth, contentHeight)
    heightLeft -= availableHeight

    while (heightLeft > 0) {
      pdf.addPage()
      imageTop = margin - (contentHeight - heightLeft)
      pdf.addImage(imageData, 'PNG', margin, imageTop, contentWidth, contentHeight)
      heightLeft -= availableHeight
    }

    pdf.save(receiptPdfFileName(order))
  } finally {
    receiptContent.remove()
  }
}

function createReceiptPdfElement(order: ReceiptPdfOrder) {
  const root = document.createElement('div')
  root.style.cssText = [
    'position:fixed',
    'left:-10000px',
    'top:0',
    'width:680px',
    'box-sizing:border-box',
    'background:#ffffff',
    'color:#171717',
    'font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
    'border:1px solid #e5e5e5',
    'border-radius:8px',
    'padding:24px',
  ].join(';')

  const header = document.createElement('div')
  header.style.cssText = 'display:flex;justify-content:space-between;gap:20px;border-bottom:1px solid #e5e5e5;padding-bottom:20px'
  header.append(
    receiptBlock('注文番号', order.order_number, '18px'),
    receiptBlock('発行日', formatOrderedAt(order.ordered_at), '16px', 'right'),
  )

  const recipient = document.createElement('div')
  recipient.style.cssText = 'margin-top:24px'
  recipient.append(receiptText('宛名', '14px', '#737373', '700'))
  recipient.append(receiptText('お客様', '20px', '#171717', '900', '4px'))

  const storeInfo = receiptPanel()
  storeInfo.append(
    receiptRow('店舗名', order.store_name ?? '-'),
    receiptRow('インボイス番号', order.store_invoice_number ?? '未登録', true),
  )

  const amountPanel = document.createElement('div')
  amountPanel.style.cssText = 'margin-top:24px;border-radius:8px;background:#fafafa;padding:20px'
  amountPanel.append(receiptText('但し書き', '14px', '#737373', '700'))
  amountPanel.append(receiptText('飲食代として', '16px', '#171717', '900', '4px'))
  amountPanel.append(receiptText('領収金額', '14px', '#737373', '700', '20px'))
  amountPanel.append(receiptText(formatPrice(order.total_amount), '36px', '#b91c1c', '900', '4px'))

  const summary = document.createElement('div')
  summary.style.cssText = 'display:grid;gap:12px;margin-top:24px;font-size:14px;font-weight:700;color:#404040'
  summary.append(
    receiptRow('支払方法', paymentMethodLabel(order.payment_method)),
    receiptRow('決済状態', paymentStatusLabel(order.payment_status)),
    receiptRow('小計', formatPrice(orderSubtotal(order))),
  )

  if ((order.membership_discount_amount ?? 0) > 0) {
    summary.append(receiptRow(membershipDiscountLabel(order), `-${formatPrice(order.membership_discount_amount ?? 0)}`, false, '#b91c1c'))
  }

  summary.append(receiptRow('配送料', order.delivery_fee ? formatPrice(order.delivery_fee) : '無料'))

  if ((order.delivery_discount_amount ?? 0) > 0) {
    summary.append(receiptRow('麺ナビ Plus 送料無料特典', `-${formatPrice(order.delivery_discount_amount ?? 0)}`, false, '#b91c1c'))
  }

  summary.append(receiptRow('税金', formatPrice(order.tax_amount ?? 0)))

  if (hasPlusBenefit(order)) {
    const plusNote = document.createElement('div')
    plusNote.textContent = '麺ナビ Plus特典が適用されています。'
    plusNote.style.cssText = 'border-radius:8px;background:#fef2f2;color:#b91c1c;padding:12px 16px;font-size:12px;font-weight:900'
    summary.append(plusNote)
  }

  const footer = document.createElement('div')
  footer.style.cssText = 'margin-top:24px;border-top:1px solid #e5e5e5;padding-top:20px;font-size:14px;font-weight:700;color:#525252'
  footer.append(receiptText('麺ナビ', '14px', '#525252', '700'))
  footer.append(receiptText('Mennavi Order', '14px', '#525252', '700', '4px'))

  root.append(header, recipient, storeInfo, amountPanel, summary, footer)

  return root
}

function receiptBlock(label: string, value: string, valueSize = '16px', align: 'left' | 'right' = 'left') {
  const block = document.createElement('div')
  block.style.cssText = `text-align:${align}`
  block.append(receiptText(label, '14px', '#737373', '700'))
  block.append(receiptText(value, valueSize, '#171717', '900', '4px'))

  return block
}

function receiptPanel() {
  const panel = document.createElement('div')
  panel.style.cssText = 'display:grid;gap:12px;margin-top:24px;border:1px solid #e5e5e5;border-radius:8px;padding:12px 16px;font-size:14px;font-weight:700;color:#404040'

  return panel
}

function receiptRow(label: string, value: string, hasMargin = false, color = '#404040') {
  const row = document.createElement('div')
  row.style.cssText = `display:flex;justify-content:space-between;gap:16px;color:${color}${hasMargin ? ';margin-top:12px' : ''}`
  const term = document.createElement('span')
  term.textContent = label
  const description = document.createElement('span')
  description.textContent = value
  description.style.cssText = 'font-weight:900;color:inherit;text-align:right'
  row.append(term, description)

  return row
}

function receiptText(value: string, size: string, color: string, weight: string, marginTop = '0') {
  const text = document.createElement('p')
  text.textContent = value
  text.style.cssText = `margin:${marginTop} 0 0;font-size:${size};color:${color};font-weight:${weight};line-height:1.4`

  return text
}

function formatPrice(price: number) {
  return `¥${price.toLocaleString('ja-JP')}`
}

function formatOrderedAt(value?: string | null) {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

function paymentMethodLabel(method?: string | null) {
  const labels: Record<string, string> = {
    card: 'クレジットカード',
    paypay: 'PayPay / QR決済',
    cash: '現金',
  }

  return labels[method ?? ''] ?? method ?? '-'
}

function paymentStatusLabel(status?: string) {
  const labels: Record<string, string> = {
    pending: '未決済',
    paid: '決済済み',
    failed: '決済失敗',
    partial_refunded: '一部返金',
    refunded: '返金済み',
  }

  return labels[status ?? ''] ?? '-'
}

function orderSubtotal(order: ReceiptPdfOrder) {
  return order.subtotal_amount ?? order.items.reduce((total, item) => total + item.subtotal, 0)
}

function membershipDiscountLabel(order: ReceiptPdfOrder) {
  const rate = order.membership_discount_rate ?? 0
  return rate > 0 ? `麺ナビ Plus ${Number(rate).toLocaleString('ja-JP')}%割引` : '麺ナビ Plus 割引'
}

function hasPlusBenefit(order: ReceiptPdfOrder) {
  return Boolean((order.membership_discount_amount ?? 0) > 0 || (order.delivery_discount_amount ?? 0) > 0)
}

function receiptDocumentTitle(order: ReceiptPdfOrder) {
  return [
    order.order_number,
    order.store_invoice_number || 'インボイス番号未登録',
    order.store_name || '店舗名未登録',
  ].map(sanitizeReceiptFilePart).join('-')
}

function receiptPdfFileName(order: ReceiptPdfOrder) {
  return `${receiptDocumentTitle(order)}.pdf`
}

function sanitizeReceiptFilePart(value: string) {
  return value.replace(/[\\/:*?"<>|]/g, '_').trim() || '未登録'
}
