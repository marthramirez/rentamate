import axios from 'axios'
import { clearSession, session } from '@/auth/session'

const client = axios.create({
  baseURL: process.env.VUE_APP_API_URL || '/api',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

client.interceptors.request.use((config) => {
  if (session.token) {
    config.headers.Authorization = `Bearer ${session.token}`
  }
  return config
})

client.interceptors.response.use(
  (response) => response,
  async (error) => {
    const url = error.config?.url || ''
    if (error.response?.status === 401 && !url.includes('/login')) {
      clearSession()
      const { default: router } = await import('@/router')
      if (router.currentRoute.value.name !== 'login') {
        router.push({ name: 'login' })
      }
    }
    return Promise.reject(error)
  },
)

export default client
