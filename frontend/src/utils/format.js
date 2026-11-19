export function formatMoney(value) {
  const amount = Number(value || 0)
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(Number.isFinite(amount) ? amount : 0)
}

export function formatDate(value) {
  if (!value) {
    return '—'
  }
  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (!match) {
    return String(value)
  }
  const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
  return date.toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

export function formatDateTime(value) {
  if (!value) {
    return '—'
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return String(value)
  }
  return date.toLocaleString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

export function todayISO() {
  const date = new Date()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

export function emptyToNull(value) {
  return value === '' || value === undefined ? null : value
}

export function readPage(body) {
  const items = Array.isArray(body?.data) ? body.data : []
  return {
    items,
    page: body?.current_page || 1,
    lastPage: body?.last_page || 1,
    total: body?.total ?? items.length,
  }
}

export function statusLabel(status) {
  return String(status || 'unknown').replace(/_/g, ' ')
}
