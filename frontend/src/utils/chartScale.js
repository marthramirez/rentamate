export function niceMax(value) {
  const number = Number(value) || 0
  if (number <= 0) {
    return 1
  }
  const power = 10 ** Math.floor(Math.log10(number))
  const fraction = number / power
  let nice = 10
  if (fraction <= 1) {
    nice = 1
  } else if (fraction <= 2) {
    nice = 2
  } else if (fraction <= 5) {
    nice = 5
  }
  return nice * power
}

export function chartTicks(max, format) {
  if (format !== 'money') {
    const top = Math.max(1, Math.ceil(Number(max) || 0))
    if (top <= 6) {
      return Array.from({ length: top + 1 }, (_, index) => index)
    }
  }
  return [0, max / 2, max]
}

export function formatTick(value, format) {
  if (format === 'money') {
    if (value >= 1000000) {
      return `₱${(value / 1000000).toFixed(1)}M`
    }
    if (value >= 1000) {
      const scaled = value / 1000
      const digits = scaled >= 10 || Number.isInteger(scaled) ? 0 : 1
      return `₱${scaled.toFixed(digits)}k`
    }
    return `₱${Math.round(value)}`
  }
  return String(Math.round(value))
}
