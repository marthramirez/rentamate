<template>
  <div>
    <PageHeader :title="editing ? 'Edit room' : 'New room'" subtitle="Room numbers must be unique within a property.">
      <RouterLink class="btn btn-outline-primary" :to="{ name: 'rooms' }">Back</RouterLink>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <LoadingBlock :loading="loading" />
    <form v-if="!loading" class="panel p-4" @submit.prevent="save">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label req" for="property">Property</label>
          <select id="property" v-model="form.property_id" class="form-select" :class="{ 'is-invalid': errors.property_id }">
            <option value="">Select property</option>
            <option v-for="property in properties" :key="property.id" :value="property.id">{{ property.name }}</option>
          </select>
          <FieldError :errors="errors" name="property_id" />
        </div>
        <div class="col-md-3">
          <label class="form-label req" for="room-number">Room number</label>
          <input id="room-number" v-model="form.room_number" class="form-control" :class="{ 'is-invalid': errors.room_number }" />
          <FieldError :errors="errors" name="room_number" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="floor">Floor</label>
          <input id="floor" v-model="form.floor" class="form-control" :class="{ 'is-invalid': errors.floor }" />
          <FieldError :errors="errors" name="floor" />
        </div>
        <div class="col-md-4">
          <label class="form-label req" for="capacity">Capacity</label>
          <input id="capacity" v-model.number="form.capacity" type="number" min="1" class="form-control" :class="{ 'is-invalid': errors.capacity }" />
          <FieldError :errors="errors" name="capacity" />
        </div>
        <div class="col-md-4">
          <label class="form-label req" for="rate">Standard monthly rate</label>
          <input id="rate" v-model="form.monthly_rate" type="number" min="0" step="0.01" class="form-control" :class="{ 'is-invalid': errors.monthly_rate }" />
          <FieldError :errors="errors" name="monthly_rate" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="status">Status</label>
          <select id="status" v-model="form.status" class="form-select" :class="{ 'is-invalid': errors.status }">
            <option value="active">Active</option>
            <option value="maintenance">Maintenance</option>
            <option value="inactive">Inactive</option>
          </select>
          <FieldError :errors="errors" name="status" />
        </div>
        <div class="col-12">
          <label class="form-label" for="description">Description</label>
          <textarea id="description" v-model="form.description" class="form-control" rows="3"></textarea>
          <FieldError :errors="errors" name="description" />
        </div>
      </div>
      <button class="btn btn-primary mt-4" type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save room' }}</button>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { propertiesApi, roomsApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import FieldError from '@/components/FieldError.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import PageHeader from '@/components/PageHeader.vue'
import { errorMessage, fieldErrors, flash } from '@/utils/errors'
import { emptyToNull } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const editing = computed(() => route.name === 'room-edit')
const loading = ref(true)
const saving = ref(false)
const properties = ref([])
const errors = ref({})
const banner = ref({ type: 'danger', message: '' })
const form = reactive({
  property_id: '',
  room_number: '',
  floor: '',
  capacity: 1,
  monthly_rate: '',
  description: '',
  status: 'active',
})

onMounted(async () => {
  try {
    const response = await propertiesApi.list({ all: 1 })
    properties.value = response.data.data
    if (!editing.value && route.query.property) {
      form.property_id = Number(route.query.property)
    }
    if (editing.value) {
      const roomResponse = await roomsApi.get(route.params.id)
      const room = roomResponse.data.data
      form.property_id = room.property_id
      form.room_number = room.room_number
      form.floor = room.floor || ''
      form.capacity = room.capacity
      form.monthly_rate = room.monthly_rate
      form.description = room.description || ''
      form.status = room.status
    }
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  errors.value = {}
  const payload = {
    property_id: form.property_id,
    room_number: form.room_number,
    floor: emptyToNull(form.floor),
    capacity: form.capacity,
    monthly_rate: form.monthly_rate,
    description: emptyToNull(form.description),
    status: form.status,
  }
  try {
    if (editing.value) {
      await roomsApi.update(route.params.id, payload)
      flash('Room updated.')
    } else {
      await roomsApi.create(payload)
      flash('Room created.')
    }
    router.push({ name: 'rooms' })
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    saving.value = false
  }
}
</script>
