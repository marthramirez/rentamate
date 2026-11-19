<template>
  <div class="login-shell">
    <form class="login-card" @submit.prevent="submit">
      <div class="login-brand">Boarding House</div>
      <p class="text-muted mb-4">Sign in to manage properties, tenants, and billing.</p>
      <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
      <div class="mb-3">
        <label class="form-label req" for="email">Email</label>
        <input id="email" v-model="form.email" type="email" autocomplete="username" class="form-control" :class="{ 'is-invalid': errors.email }" />
        <FieldError :errors="errors" name="email" />
      </div>
      <div class="mb-4">
        <label class="form-label req" for="password">Password</label>
        <input id="password" v-model="form.password" type="password" autocomplete="current-password" class="form-control" :class="{ 'is-invalid': errors.password }" />
        <FieldError :errors="errors" name="password" />
      </div>
      <button class="btn btn-primary w-100" type="submit" :disabled="saving">{{ saving ? 'Signing in…' : 'Sign in' }}</button>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { authApi } from '@/api'
import { setSession } from '@/auth/session'
import AlertBanner from '@/components/AlertBanner.vue'
import FieldError from '@/components/FieldError.vue'
import { errorMessage, fieldErrors } from '@/utils/errors'

const route = useRoute()
const router = useRouter()
const saving = ref(false)
const errors = ref({})
const banner = ref({ type: 'danger', message: '' })
const form = reactive({
  email: '',
  password: '',
})

function nextPath() {
  const redirect = route.query.redirect
  if (typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')) {
    return redirect
  }
  return '/'
}

async function submit() {
  saving.value = true
  errors.value = {}
  try {
    const response = await authApi.login({
      email: form.email,
      password: form.password,
    })
    setSession(response.data.data.token, response.data.data.user)
    router.push(nextPath())
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    saving.value = false
  }
}
</script>
