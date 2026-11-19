<template>
  <div>
    <PageHeader :title="lease ? lease.lease_number : 'Lease'" :subtitle="lease ? `${lease.tenant?.full_name} · ${lease.room?.property?.name} ${lease.room?.room_number}` : ''">
      <RouterLink class="btn btn-outline-primary" :to="{ name: 'leases' }">Back</RouterLink>
      <RouterLink v-if="lease && lease.status !== 'cancelled'" class="btn btn-outline-primary" :to="{ name: 'lease-edit', params: { id: lease.id } }">Edit</RouterLink>
      <RouterLink v-if="canBill" class="btn btn-primary" :to="{ name: 'billing-create', query: { lease: lease.id } }">Create bill</RouterLink>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <LoadingBlock :loading="loading" />
    <div v-if="lease" class="row g-3">
      <div class="col-lg-4">
        <div class="panel p-3 mb-3">
          <dl class="mb-0">
            <dt>Status</dt>
            <dd><StatusBadge :status="lease.status" /></dd>
            <dt>Start</dt>
            <dd>{{ formatDate(lease.start_date) }}</dd>
            <dt>End</dt>
            <dd>{{ formatDate(lease.end_date) }}</dd>
            <dt>Agreed rent</dt>
            <dd>{{ formatMoney(lease.monthly_rent) }}</dd>
            <dt>Deposit</dt>
            <dd>{{ formatMoney(lease.deposit_amount) }}</dd>
            <dt>Room standard rate</dt>
            <dd>{{ formatMoney(lease.room?.monthly_rate) }}</dd>
            <dt>Terms</dt>
            <dd class="mb-0">{{ lease.terms || '—' }}</dd>
          </dl>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button v-if="lease.status === 'active'" class="btn btn-outline-danger" type="button" @click="openAction('terminate')">Terminate</button>
          <button v-if="lease.status === 'active'" class="btn btn-outline-secondary" type="button" @click="openAction('expire')">Mark expired</button>
          <button v-if="lease.status === 'draft'" class="btn btn-outline-danger" type="button" @click="openAction('cancel')">Cancel draft</button>
        </div>
      </div>
      <div class="col-lg-8">
        <div v-if="lease.status === 'draft'" class="panel p-3 mb-3">
          <h2 class="h6">Allocate this lease</h2>
          <p class="text-muted small">The assignment uses this lease's room. A different room requires a new lease.</p>
          <div class="row g-2 align-items-end">
            <div class="col-md-4">
              <label class="form-label" for="move-in">Move-in date</label>
              <input id="move-in" v-model="allocateDate" type="date" class="form-control" />
            </div>
            <div class="col-md-4">
              <div class="form-check mb-2">
                <input id="reserve-lease" v-model="reserveOnly" class="form-check-input" type="checkbox" />
                <label class="form-check-label" for="reserve-lease">Reserve only</label>
              </div>
            </div>
            <div class="col-md-4">
              <button class="btn btn-primary" type="button" :disabled="acting" @click="allocate">Allocate</button>
            </div>
          </div>
        </div>
        <div class="panel mb-3">
          <div class="p-3 border-bottom"><h2 class="h6 mb-0">Room assignments</h2></div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th scope="col">Room</th>
                  <th scope="col">Move in</th>
                  <th scope="col">Move out</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!(lease.assignments || []).length">
                  <td colspan="4" class="text-muted">No assignment yet.</td>
                </tr>
                <tr v-for="assignment in lease.assignments" :key="assignment.id">
                  <td>{{ assignment.room?.room_number }}</td>
                  <td>{{ formatDate(assignment.move_in_date) }}</td>
                  <td>{{ formatDate(assignment.move_out_date) }}</td>
                  <td><StatusBadge :status="assignment.status" /></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="panel">
          <div class="p-3 border-bottom"><h2 class="h6 mb-0">Bills</h2></div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th scope="col">Number</th>
                  <th scope="col">Description</th>
                  <th scope="col">Balance</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!(lease.billing_records || []).length">
                  <td colspan="4" class="text-muted">No bills yet.</td>
                </tr>
                <tr v-for="bill in lease.billing_records" :key="bill.id">
                  <td><RouterLink :to="{ name: 'billing-detail', params: { id: bill.id } }">{{ bill.billing_number }}</RouterLink></td>
                  <td>{{ bill.description }}</td>
                  <td>{{ formatMoney(bill.balance) }}</td>
                  <td><StatusBadge :status="bill.computed_status" /></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <ModalShell :show="action.show" :title="action.title" @cancel="action.show = false">
      <p>{{ action.message }}</p>
      <div v-if="action.type === 'terminate'" class="mb-2">
        <label class="form-label" for="end-action">End / move-out date</label>
        <input id="end-action" v-model="action.date" type="date" class="form-control" />
      </div>
      <template #footer>
        <button class="btn btn-outline-secondary" type="button" @click="action.show = false">Cancel</button>
        <button class="btn btn-danger" type="button" :disabled="acting" @click="runAction">Confirm</button>
      </template>
    </ModalShell>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { leasesApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import ModalShell from '@/components/ModalShell.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { errorMessage } from '@/utils/errors'
import { formatDate, formatMoney, todayISO } from '@/utils/format'

const route = useRoute()
const lease = ref(null)
const loading = ref(true)
const acting = ref(false)
const allocateDate = ref(todayISO())
const reserveOnly = ref(false)
const banner = ref({ type: 'success', message: '' })
const action = reactive({ show: false, type: '', title: '', message: '', date: todayISO() })
const canBill = computed(() => ['active', 'expired', 'terminated'].includes(lease.value?.status))

async function load() {
  loading.value = true
  try {
    const response = await leasesApi.get(route.params.id)
    lease.value = response.data.data
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    loading.value = false
  }
}

function openAction(type) {
  action.type = type
  action.date = todayISO()
  action.title = type === 'terminate' ? 'Terminate lease' : type === 'expire' ? 'Mark lease expired' : 'Cancel draft lease'
  action.message = type === 'cancel'
    ? 'This draft will be marked cancelled. Assignment and billing history is kept when it exists.'
    : 'Open room assignments will be closed and the historical record will stay.'
  action.show = true
}

async function allocate() {
  acting.value = true
  try {
    await leasesApi.allocate(lease.value.id, {
      move_in_date: allocateDate.value,
      assignment_status: reserveOnly.value ? 'reserved' : 'active',
    })
    banner.value = { type: 'success', message: 'Room assignment saved.' }
    await load()
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    acting.value = false
  }
}

async function runAction() {
  acting.value = true
  try {
    if (action.type === 'terminate') {
      await leasesApi.terminate(lease.value.id, { end_date: action.date, move_out_date: action.date })
    } else if (action.type === 'expire') {
      await leasesApi.expire(lease.value.id, { move_out_date: action.date })
    } else {
      await leasesApi.cancel(lease.value.id)
    }
    action.show = false
    banner.value = { type: 'success', message: 'Lease updated. Related history was kept.' }
    await load()
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    acting.value = false
  }
}

onMounted(load)
</script>
