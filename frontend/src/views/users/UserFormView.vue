<template>
  <div>
    <PageHeader :title="editing ? 'Edit user' : 'New user'" subtitle="Administrators can manage users. Staff can use the other modules.">
      <RouterLink class="btn btn-outline-primary" :to="{ name: 'users' }">Back</RouterLink>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <LoadingBlock :loading="loading" />
    <form v-if="!loading" class="panel p-4" @submit.prevent="save">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label req" for="name">Name</label>
          <input id="name" v-model="form.name" class="form-control" :class="{ 'is-invalid': errors.name }" />
          <FieldError :errors="errors" name="name" />
        </div>
        <div class="col-md-6">
          <label class="form-label req" for="email">Email</label>
          <input id="email" v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': errors.email }" />
          <FieldError :errors="errors" name="email" />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="role">Role</label>
          <select id="role" v-model="form.role" class="form-select" :class="{ 'is-invalid': errors.role }">
            <option value="admin">Administrator</option>
            <option value="staff">Staff</option>
          </select>
          <FieldError :errors="errors" name="role" />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="status">Status</label>
          <select id="status" v-model="form.status" class="form-select" :class="{ 'is-invalid': errors.status }" :disabled="isSelf">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
          <div v-if="isSelf" class="form-text">You cannot deactivate your own account.</div>
          <FieldError :errors="errors" name="status" />
        </div>
        <div class="col-md-6">
          <label class="form-label" :class="{ req: !editing }" for="password">{{ editing ? 'New password' : 'Password' }}</label>
          <input id="password" v-model="form.password" type="password" autocomplete="new-password" class="form-control" :class="{ 'is-invalid': errors.password }" />
          <div v-if="editing" class="form-text">Leave blank to keep the current password. At least 8 characters.</div>
          <FieldError :errors="errors" name="password" />
        </div>
        <div class="col-md-6">
          <label class="form-label" :class="{ req: !editing }" for="password-confirmation">Confirm password</label>
          <input id="password-confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" class="form-control" />
        </div>
      </div>
      <button class="btn btn-primary mt-4" type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save user' }}</button>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usersApi } from '@/api'
import { session, setSession } from '@/auth/session'
import AlertBanner from '@/components/AlertBanner.vue'
import FieldError from '@/components/FieldError.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import PageHeader from '@/components/PageHeader.vue'
import { errorMessage, fieldErrors, flash } from '@/utils/errors'

const route = useRoute()
const router = useRouter()
const editing = computed(() => route.name === 'user-edit')
const isSelf = computed(() => editing.value && Number(session.user?.id) === Number(route.params.id))
const loading = ref(false)
const saving = ref(false)
const errors = ref({})
const banner = ref({ type: 'danger', message: '' })
const form = reactive({
  name: '',
  email: '',
  role: 'staff',
  status: 'active',
  password: '',
  password_confirmation: '',
})

function payload() {
  const body = {
    name: form.name,
    email: form.email,
    role: form.role,
    status: form.status,
  }
  if (form.password) {
    body.password = form.password
    body.password_confirmation = form.password_confirmation
  }
  return body
}

async function load() {
  if (!editing.value) {
    return
  }
  loading.value = true
  try {
    const response = await usersApi.get(route.params.id)
    const user = response.data.data
    form.name = user.name
    form.email = user.email
    form.role = user.role
    form.status = user.status
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const response = editing.value
      ? await usersApi.update(route.params.id, payload())
      : await usersApi.create(payload())
    if (Number(session.user?.id) === Number(response.data.data.id)) {
      setSession(session.token, response.data.data)
    }
    flash(editing.value ? 'User updated.' : 'User created.')
    router.push(session.user?.role === 'admin' ? { name: 'users' } : { name: 'dashboard' })
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
