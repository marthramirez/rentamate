<template>
  <div>
    <PageHeader title="Account" subtitle="Change the password for the account you are signed in with." />
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <div class="panel p-4 mb-3">
      <div class="row g-3">
        <div class="col-md-4">
          <div class="text-muted small">Name</div>
          <div class="fw-semibold">{{ session.user?.name }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small">Email</div>
          <div class="fw-semibold">{{ session.user?.email }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small">Role</div>
          <div class="fw-semibold">{{ session.user?.role === 'admin' ? 'Administrator' : 'Staff' }}</div>
        </div>
      </div>
      <p class="text-muted small mb-0 mt-3">An administrator changes names, emails, and roles from Users.</p>
    </div>
    <form class="panel p-4" @submit.prevent="save">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label req" for="current-password">Current password</label>
          <input id="current-password" v-model="form.current_password" type="password" autocomplete="current-password" class="form-control" :class="{ 'is-invalid': errors.current_password }" />
          <FieldError :errors="errors" name="current_password" />
        </div>
        <div class="col-md-6">
          <label class="form-label req" for="password">New password</label>
          <input id="password" v-model="form.password" type="password" autocomplete="new-password" class="form-control" :class="{ 'is-invalid': errors.password }" />
          <div class="form-text">At least 8 characters. Other signed-in sessions for this account are signed out.</div>
          <FieldError :errors="errors" name="password" />
        </div>
        <div class="col-md-6">
          <label class="form-label req" for="password-confirmation">Confirm new password</label>
          <input id="password-confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" class="form-control" />
        </div>
      </div>
      <button class="btn btn-primary mt-4" type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Update password' }}</button>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { authApi } from '@/api'
import { session, setSession } from '@/auth/session'
import AlertBanner from '@/components/AlertBanner.vue'
import FieldError from '@/components/FieldError.vue'
import PageHeader from '@/components/PageHeader.vue'
import { errorMessage, fieldErrors } from '@/utils/errors'

const saving = ref(false)
const errors = ref({})
const banner = ref({ type: 'success', message: '' })
const form = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const response = await authApi.updatePassword({
      current_password: form.current_password,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
    setSession(session.token, response.data.data)
    form.current_password = ''
    form.password = ''
    form.password_confirmation = ''
    banner.value = { type: 'success', message: 'Password updated.' }
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    saving.value = false
  }
}
</script>
