const FLASH_KEY = 'bhms_flash'

export function flash(message) {
  sessionStorage.setItem(FLASH_KEY, message)
}

export function pullFlash() {
  const message = sessionStorage.getItem(FLASH_KEY)
  if (message) {
    sessionStorage.removeItem(FLASH_KEY)
  }
  return message
}

export function fieldErrors(error) {
  const errors = error?.response?.data?.errors
  return errors && typeof errors === 'object' ? errors : {}
}

export function errorMessage(error) {
  if (!error?.response) {
    return 'Unable to reach the server. Check that the API is running.'
  }

  const data = error.response.data
  if (typeof data === 'string') {
    return 'The server returned an unexpected response.'
  }

  if (error.response.status === 404) {
    return 'The requested record was not found.'
  }

  if (error.response.status === 422 && data?.errors) {
    const first = Object.values(data.errors)[0]
    if (Array.isArray(first) && first.length) {
      return first[0]
    }
  }

  if (error.response.status >= 500) {
    return 'The server could not complete that request.'
  }

  return data?.message || 'Request failed.'
}
