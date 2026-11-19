<template>
  <div>
    <PageHeader :title="editing ? 'Edit tenant' : 'New tenant'" subtitle="Contact number is required.">
      <RouterLink class="btn btn-outline-primary" :to="{ name: 'tenants' }">Back</RouterLink>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <LoadingBlock :loading="loading" />
    <form v-if="!loading" class="panel p-4" @submit.prevent="save">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label req" for="first">First name</label>
          <input id="first" v-model="form.first_name" class="form-control" :class="{ 'is-invalid': errors.first_name }" />
          <FieldError :errors="errors" name="first_name" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="middle">Middle name</label>
          <input id="middle" v-model="form.middle_name" class="form-control" />
        </div>
        <div class="col-md-4">
          <label class="form-label req" for="last">Last name</label>
          <input id="last" v-model="form.last_name" class="form-control" :class="{ 'is-invalid': errors.last_name }" />
          <FieldError :errors="errors" name="last_name" />
        </div>
        <div class="col-md-4">
          <label class="form-label req" for="contact">Contact number</label>
          <input id="contact" v-model="form.contact_number" class="form-control" :class="{ 'is-invalid': errors.contact_number }" />
          <FieldError :errors="errors" name="contact_number" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="email">Email</label>
          <input id="email" v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': errors.email }" />
          <FieldError :errors="errors" name="email" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="status">Status</label>
          <select id="status" v-model="form.status" class="form-select" :class="{ 'is-invalid': errors.status }">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
          <FieldError :errors="errors" name="status" />
        </div>
        <div class="col-12">
          <label class="form-label" for="address">Address</label>
          <input id="address" v-model="form.address" class="form-control" />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="emergency-name">Emergency contact name</label>
          <input id="emergency-name" v-model="form.emergency_contact_name" class="form-control" />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="emergency-number">Emergency contact number</label>
          <input id="emergency-number" v-model="form.emergency_contact_number" class="form-control" />
        </div>
      </div>
      <button class="btn btn-primary mt-4" type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save tenant' }}</button>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { tenantsApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import FieldError from '@/components/FieldError.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import PageHeader from '@/components/PageHeader.vue'
import { errorMessage, fieldErrors, flash } from '@/utils/errors'
import { emptyToNull } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const editing = computed(() => route.name === 'tenant-edit')
const loading = ref(false)
const saving = ref(false)
const errors = ref({})
const banner = ref({ type: 'danger', message: '' })
const form = reactive({
  first_name: '',
  middle_name: '',
  last_name: '',
  contact_number: '',
  email: '',
  address: '',
  emergency_contact_name: '',
  emergency_contact_number: '',
  status: 'active',
})

function apply(tenant) {
  Object.keys(form).forEach((key) => {
    form[key] = tenant[key] || (key === 'status' ? 'active' : '')
  })
}

function payload() {
  return {
    first_name: form.first_name,
    middle_name: emptyToNull(form.middle_name),
    last_name: form.last_name,
    contact_number: form.contact_number,
    email: emptyToNull(form.email),
    address: emptyToNull(form.address),
    emergency_contact_name: emptyToNull(form.emergency_contact_name),
    emergency_contact_number: emptyToNull(form.emergency_contact_number),
    status: form.status,
  }
}

onMounted(async () => {
  if (!editing.value) {
    return
  }
  loading.value = true
  try {
    const response = await tenantsApi.get(route.params.id)
    apply(response.data.data)
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  errors.value = {}
  try {
    if (editing.value) {
      await tenantsApi.update(route.params.id, payload())
      flash('Tenant updated.')
    } else {
      await tenantsApi.create(payload())
      flash('Tenant registered.')
    }
    router.push({ name: 'tenants' })
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    saving.value = false
  }
}
</script>
