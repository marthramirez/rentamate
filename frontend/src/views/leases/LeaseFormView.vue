<template>
  <div>
    <PageHeader :title="editing ? 'Edit lease' : 'New lease'" :subtitle="editing ? 'Changing the agreed rent does not rewrite existing bills.' : 'Choose active to assign the room immediately, or draft to save the terms first.'">
      <RouterLink class="btn btn-outline-primary" :to="{ name: 'leases' }">Back</RouterLink>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <LoadingBlock :loading="loading" />
    <form v-if="!loading" class="panel p-4" @submit.prevent="save">
      <div class="row g-3">
        <div v-if="!editing" class="col-md-6">
          <label class="form-label req" for="tenant">Tenant</label>
          <select id="tenant" v-model="form.tenant_id" class="form-select" :class="{ 'is-invalid': errors.tenant_id }">
            <option value="">Select tenant</option>
            <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">{{ tenant.full_name }}</option>
          </select>
          <FieldError :errors="errors" name="tenant_id" />
        </div>
        <div v-if="!editing" class="col-md-6">
          <label class="form-label req" for="room">Room</label>
          <select id="room" v-model="form.room_id" class="form-select" :class="{ 'is-invalid': errors.room_id }" @change="applyRoomRate">
            <option value="">Select room</option>
            <option v-for="room in rooms" :key="room.id" :value="room.id">
              {{ room.property?.name }} · {{ room.room_number }} · {{ formatMoney(room.monthly_rate) }}
            </option>
          </select>
          <FieldError :errors="errors" name="room_id" />
        </div>
        <div v-if="editing" class="col-md-6">
          <label class="form-label req" for="number">Lease number</label>
          <input id="number" v-model="form.lease_number" class="form-control" :class="{ 'is-invalid': errors.lease_number }" />
          <FieldError :errors="errors" name="lease_number" />
        </div>
        <div class="col-md-3">
          <label class="form-label req" for="start">Start date</label>
          <input id="start" v-model="form.start_date" type="date" class="form-control" :class="{ 'is-invalid': errors.start_date }" />
          <FieldError :errors="errors" name="start_date" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="end">End date</label>
          <input id="end" v-model="form.end_date" type="date" class="form-control" :class="{ 'is-invalid': errors.end_date }" />
          <FieldError :errors="errors" name="end_date" />
        </div>
        <div class="col-md-3">
          <label class="form-label req" for="rent">Agreed monthly rent</label>
          <input id="rent" v-model="form.monthly_rent" type="number" min="0" step="0.01" class="form-control" :class="{ 'is-invalid': errors.monthly_rent }" />
          <FieldError :errors="errors" name="monthly_rent" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="deposit">Deposit</label>
          <input id="deposit" v-model="form.deposit_amount" type="number" min="0" step="0.01" class="form-control" :class="{ 'is-invalid': errors.deposit_amount }" />
          <FieldError :errors="errors" name="deposit_amount" />
        </div>
        <div v-if="!editing" class="col-md-3">
          <label class="form-label" for="status">Status</label>
          <select id="status" v-model="form.status" class="form-select">
            <option value="active">Active</option>
            <option value="draft">Draft</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label" for="terms">Terms</label>
          <textarea id="terms" v-model="form.terms" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <button class="btn btn-primary mt-4" type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save lease' }}</button>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { leasesApi, roomsApi, tenantsApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import FieldError from '@/components/FieldError.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import PageHeader from '@/components/PageHeader.vue'
import { errorMessage, fieldErrors, flash } from '@/utils/errors'
import { emptyToNull, formatMoney, todayISO } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const editing = computed(() => route.name === 'lease-edit')
const loading = ref(true)
const saving = ref(false)
const tenants = ref([])
const rooms = ref([])
const errors = ref({})
const banner = ref({ type: 'danger', message: '' })
const form = reactive({
  tenant_id: '',
  room_id: '',
  lease_number: '',
  start_date: todayISO(),
  end_date: '',
  monthly_rent: '',
  deposit_amount: 0,
  terms: '',
  status: 'active',
})

function applyRoomRate() {
  const room = rooms.value.find((item) => item.id === Number(form.room_id))
  if (room && form.monthly_rent === '') {
    form.monthly_rent = room.monthly_rate
  }
}

onMounted(async () => {
  try {
    if (editing.value) {
      const response = await leasesApi.get(route.params.id)
      const lease = response.data.data
      form.lease_number = lease.lease_number
      form.start_date = String(lease.start_date).slice(0, 10)
      form.end_date = lease.end_date ? String(lease.end_date).slice(0, 10) : ''
      form.monthly_rent = lease.monthly_rent
      form.deposit_amount = lease.deposit_amount
      form.terms = lease.terms || ''
    } else {
      const [tenantResponse, roomResponse] = await Promise.all([
        tenantsApi.list({ all: 1 }),
        roomsApi.list({ all: 1, status: 'active' }),
      ])
      tenants.value = tenantResponse.data.data
      rooms.value = roomResponse.data.data
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
  try {
    if (editing.value) {
      await leasesApi.update(route.params.id, {
        lease_number: form.lease_number,
        start_date: form.start_date,
        end_date: emptyToNull(form.end_date),
        monthly_rent: form.monthly_rent,
        deposit_amount: form.deposit_amount || 0,
        terms: emptyToNull(form.terms),
      })
      flash('Lease updated.')
      router.push({ name: 'lease-detail', params: { id: route.params.id } })
    } else {
      const response = await leasesApi.create({
        tenant_id: form.tenant_id,
        room_id: form.room_id,
        start_date: form.start_date,
        end_date: emptyToNull(form.end_date),
        monthly_rent: form.monthly_rent,
        deposit_amount: form.deposit_amount || 0,
        terms: emptyToNull(form.terms),
        status: form.status,
        move_in_date: form.start_date,
      })
      flash('Lease saved.')
      router.push({ name: 'lease-detail', params: { id: response.data.data.id } })
    }
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    saving.value = false
  }
}
</script>
