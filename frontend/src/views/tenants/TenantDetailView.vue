<template>
  <div>
    <PageHeader :title="tenant ? tenant.full_name : 'Tenant'">
      <RouterLink class="btn btn-outline-primary" :to="{ name: 'tenants' }">Back</RouterLink>
      <RouterLink v-if="tenant" class="btn btn-outline-primary" :to="{ name: 'tenant-edit', params: { id: tenant.id } }">Edit</RouterLink>
      <button v-if="tenant" class="btn btn-outline-danger" type="button" @click="dialog.show = true">Remove</button>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <LoadingBlock :loading="loading" />
    <div v-if="tenant" class="row g-3">
      <div class="col-lg-4">
        <div class="panel p-3">
          <dl class="mb-0">
            <dt>Contact</dt>
            <dd>{{ tenant.contact_number }}</dd>
            <dt>Email</dt>
            <dd>{{ tenant.email || '—' }}</dd>
            <dt>Address</dt>
            <dd>{{ tenant.address || '—' }}</dd>
            <dt>Emergency contact</dt>
            <dd>{{ tenant.emergency_contact_name || '—' }}<span v-if="tenant.emergency_contact_number"> · {{ tenant.emergency_contact_number }}</span></dd>
            <dt>Status</dt>
            <dd class="mb-0"><StatusBadge :status="tenant.status" /></dd>
          </dl>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="panel mb-3">
          <div class="p-3 border-bottom"><h2 class="h6 mb-0">Leases</h2></div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th scope="col">Number</th>
                  <th scope="col">Room</th>
                  <th scope="col">Start</th>
                  <th scope="col">Rent</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!(tenant.leases || []).length">
                  <td colspan="5" class="text-muted">No leases yet.</td>
                </tr>
                <tr v-for="lease in tenant.leases" :key="lease.id">
                  <td><RouterLink :to="{ name: 'lease-detail', params: { id: lease.id } }">{{ lease.lease_number }}</RouterLink></td>
                  <td>{{ lease.room?.property?.name }} · {{ lease.room?.room_number }}</td>
                  <td>{{ formatDate(lease.start_date) }}</td>
                  <td>{{ formatMoney(lease.monthly_rent) }}</td>
                  <td><StatusBadge :status="lease.status" /></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="panel">
          <div class="p-3 border-bottom"><h2 class="h6 mb-0">Billing history</h2></div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th scope="col">Bill</th>
                  <th scope="col">Due</th>
                  <th scope="col">Amount</th>
                  <th scope="col">Balance</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!bills.length">
                  <td colspan="5" class="text-muted">No bills yet.</td>
                </tr>
                <tr v-for="bill in bills" :key="bill.id">
                  <td><RouterLink :to="{ name: 'billing-detail', params: { id: bill.id } }">{{ bill.billing_number }}</RouterLink></td>
                  <td>{{ formatDate(bill.due_date) }}</td>
                  <td>{{ formatMoney(bill.amount) }}</td>
                  <td>{{ formatMoney(bill.balance) }}</td>
                  <td><StatusBadge :status="bill.computed_status" /></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <ModalShell :show="dialog.show" title="Remove tenant" @cancel="dialog.show = false">
      <p class="mb-0">Tenants with lease history are deactivated. Tenants with an active lease cannot be removed until that lease ends.</p>
      <template #footer>
        <button class="btn btn-outline-secondary" type="button" @click="dialog.show = false">Cancel</button>
        <button class="btn btn-danger" type="button" :disabled="dialog.busy" @click="confirmRemove">Continue</button>
      </template>
    </ModalShell>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { tenantsApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import ModalShell from '@/components/ModalShell.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { errorMessage, flash } from '@/utils/errors'
import { formatDate, formatMoney } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const tenant = ref(null)
const loading = ref(true)
const banner = ref({ type: 'success', message: '' })
const dialog = reactive({ show: false, busy: false })
const bills = computed(() => (tenant.value?.leases || []).flatMap((lease) => lease.billing_records || []))

async function load() {
  loading.value = true
  try {
    const response = await tenantsApi.get(route.params.id)
    tenant.value = response.data.data
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    loading.value = false
  }
}

async function confirmRemove() {
  dialog.busy = true
  try {
    const response = await tenantsApi.remove(tenant.value.id)
    flash(response.data.message)
    dialog.show = false
    if (response.data.deleted) {
      router.push({ name: 'tenants' })
    } else {
      await load()
    }
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
    dialog.show = false
  } finally {
    dialog.busy = false
  }
}

onMounted(load)
</script>
