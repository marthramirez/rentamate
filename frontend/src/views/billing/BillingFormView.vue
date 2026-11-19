<template>
  <div>
    <PageHeader title="New bill" subtitle="Use the lease's agreed rent for monthly rent, or enter another charge such as water or fees.">
      <RouterLink class="btn btn-outline-primary" :to="{ name: 'billing' }">Back</RouterLink>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <LoadingBlock :loading="loading" />
    <form v-if="!loading" class="panel p-4" @submit.prevent="save">
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label req" for="lease">Lease</label>
          <select id="lease" v-model="form.lease_id" class="form-select" :class="{ 'is-invalid': errors.lease_id }" @change="applyLease">
            <option value="">Select lease</option>
            <option v-for="lease in leases" :key="lease.id" :value="lease.id">
              {{ lease.lease_number }} · {{ lease.tenant?.full_name }} · {{ lease.room?.room_number }} ({{ lease.status }})
            </option>
          </select>
          <FieldError :errors="errors" name="lease_id" />
        </div>
        <div class="col-md-4">
          <label class="form-label req" for="amount">Amount</label>
          <input id="amount" v-model="form.amount" type="number" min="0.01" step="0.01" class="form-control" :class="{ 'is-invalid': errors.amount }" />
          <FieldError :errors="errors" name="amount" />
        </div>
        <div class="col-12">
          <label class="form-label req" for="description">Description</label>
          <input id="description" v-model="form.description" class="form-control" placeholder="Monthly rent, water bill, fee" :class="{ 'is-invalid': errors.description }" />
          <FieldError :errors="errors" name="description" />
        </div>
        <div class="col-md-3">
          <label class="form-label req" for="billing-date">Billing date</label>
          <input id="billing-date" v-model="form.billing_date" type="date" class="form-control" :class="{ 'is-invalid': errors.billing_date }" />
          <FieldError :errors="errors" name="billing_date" />
        </div>
        <div class="col-md-3">
          <label class="form-label req" for="due-date">Due date</label>
          <input id="due-date" v-model="form.due_date" type="date" class="form-control" :class="{ 'is-invalid': errors.due_date }" />
          <FieldError :errors="errors" name="due_date" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="period-start">Period start</label>
          <input id="period-start" v-model="form.period_start" type="date" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="period-end">Period end</label>
          <input id="period-end" v-model="form.period_end" type="date" class="form-control" :class="{ 'is-invalid': errors.period_end }" />
          <FieldError :errors="errors" name="period_end" />
        </div>
      </div>
      <button class="btn btn-primary mt-4" type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Create bill' }}</button>
    </form>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { billingApi, leasesApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import FieldError from '@/components/FieldError.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import PageHeader from '@/components/PageHeader.vue'
import { errorMessage, fieldErrors, flash } from '@/utils/errors'
import { emptyToNull, todayISO } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const saving = ref(false)
const leases = ref([])
const errors = ref({})
const banner = ref({ type: 'danger', message: '' })
const form = reactive({
  lease_id: '',
  description: 'Monthly rent',
  amount: '',
  billing_date: todayISO(),
  due_date: todayISO(),
  period_start: '',
  period_end: '',
})

function applyLease() {
  const lease = leases.value.find((item) => item.id === Number(form.lease_id))
  if (lease) {
    form.amount = lease.monthly_rent
    if (!form.description) {
      form.description = 'Monthly rent'
    }
  }
}

onMounted(async () => {
  try {
    const response = await leasesApi.list({ all: 1 })
    leases.value = response.data.data.filter((lease) => ['active', 'expired', 'terminated'].includes(lease.status))
    if (route.query.lease) {
      form.lease_id = Number(route.query.lease)
      applyLease()
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
    const response = await billingApi.create({
      lease_id: form.lease_id,
      description: form.description,
      amount: form.amount,
      billing_date: form.billing_date,
      due_date: form.due_date,
      period_start: emptyToNull(form.period_start),
      period_end: emptyToNull(form.period_end),
    })
    flash('Bill created.')
    router.push({ name: 'billing-detail', params: { id: response.data.data.id } })
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    saving.value = false
  }
}
</script>
