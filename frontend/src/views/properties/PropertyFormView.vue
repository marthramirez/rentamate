<template>
  <div>
    <PageHeader :title="editing ? 'Edit property' : 'New property'" subtitle="Name and address are required.">
      <RouterLink class="btn btn-outline-primary" :to="{ name: 'properties' }">Back</RouterLink>
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
          <label class="form-label" for="status">Status</label>
          <select id="status" v-model="form.status" class="form-select" :class="{ 'is-invalid': errors.status }">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
          <FieldError :errors="errors" name="status" />
        </div>
        <div class="col-12">
          <label class="form-label req" for="address">Address</label>
          <input id="address" v-model="form.address" class="form-control" :class="{ 'is-invalid': errors.address }" />
          <FieldError :errors="errors" name="address" />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="contact">Contact number</label>
          <input id="contact" v-model="form.contact_number" class="form-control" :class="{ 'is-invalid': errors.contact_number }" />
          <FieldError :errors="errors" name="contact_number" />
        </div>
        <div class="col-12">
          <label class="form-label" for="description">Description</label>
          <textarea id="description" v-model="form.description" class="form-control" rows="3" :class="{ 'is-invalid': errors.description }"></textarea>
          <FieldError :errors="errors" name="description" />
        </div>
      </div>
      <button class="btn btn-primary mt-4" type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save property' }}</button>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { propertiesApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import FieldError from '@/components/FieldError.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import PageHeader from '@/components/PageHeader.vue'
import { errorMessage, fieldErrors, flash } from '@/utils/errors'
import { emptyToNull } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const editing = computed(() => route.name === 'property-edit')
const loading = ref(false)
const saving = ref(false)
const errors = ref({})
const banner = ref({ type: 'danger', message: '' })
const form = reactive({
  name: '',
  address: '',
  contact_number: '',
  description: '',
  status: 'active',
})

function payload() {
  return {
    name: form.name,
    address: form.address,
    contact_number: emptyToNull(form.contact_number),
    description: emptyToNull(form.description),
    status: form.status,
  }
}

async function load() {
  if (!editing.value) {
    return
  }
  loading.value = true
  try {
    const response = await propertiesApi.get(route.params.id)
    const property = response.data.data
    form.name = property.name
    form.address = property.address
    form.contact_number = property.contact_number || ''
    form.description = property.description || ''
    form.status = property.status
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
    if (editing.value) {
      await propertiesApi.update(route.params.id, payload())
      flash('Property updated.')
    } else {
      await propertiesApi.create(payload())
      flash('Property created.')
    }
    router.push({ name: 'properties' })
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
