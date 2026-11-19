<template>
  <div>
    <PageHeader title="Room allocation" subtitle="Assign a tenant only when the room has space. Reservations hold a space too." />
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />

    <form class="panel p-4 mb-4" @submit.prevent="allocate">
      <h2 class="h6 mb-3">Allocate tenant</h2>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label req" for="tenant">Tenant</label>
          <select id="tenant" v-model="form.tenant_id" class="form-select" :class="{ 'is-invalid': errors.tenant_id }">
            <option value="">Select tenant</option>
            <option v-for="tenant in openTenants" :key="tenant.id" :value="tenant.id">
              {{ tenant.full_name }} · {{ tenant.contact_number }}
            </option>
          </select>
          <FieldError :errors="errors" name="tenant_id" />
        </div>
        <div class="col-md-6">
          <label class="form-label req" for="room">Room</label>
          <select id="room" v-model="form.room_id" class="form-select" :class="{ 'is-invalid': errors.room_id }" @change="applyRoomRate">
            <option value="">Select room</option>
            <option v-for="room in rooms" :key="room.id" :value="room.id">
              {{ room.property?.name }} · {{ room.room_number }} ({{ room.available_spaces }} open)
            </option>
          </select>
          <FieldError :errors="errors" name="room_id" />
        </div>
        <div class="col-md-3">
          <label class="form-label req" for="start">Start / move-in</label>
          <input id="start" v-model="form.start_date" type="date" class="form-control" :class="{ 'is-invalid': errors.start_date || errors.move_in_date }" />
          <FieldError :errors="errors" name="start_date" />
          <FieldError :errors="errors" name="move_in_date" />
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
          <input id="deposit" v-model="form.deposit_amount" type="number" min="0" step="0.01" class="form-control" />
        </div>
        <div class="col-12">
          <label class="form-label" for="terms">Terms</label>
          <textarea id="terms" v-model="form.terms" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input id="reserve" v-model="form.reserve" class="form-check-input" type="checkbox" />
            <label class="form-check-label" for="reserve">Reserve only. This holds capacity but does not count as an occupied space.</label>
          </div>
        </div>
      </div>
      <button class="btn btn-primary mt-3" type="submit" :disabled="saving || !rooms.length">{{ saving ? 'Saving…' : 'Create lease and assign room' }}</button>
      <p v-if="!rooms.length" class="text-muted small mt-2 mb-0">No active rooms currently have available space.</p>
    </form>

    <div class="panel mb-4">
      <div class="p-3 border-bottom"><h2 class="h6 mb-0">Current occupants and reservations</h2></div>
      <LoadingBlock :loading="loadingCurrent" />
      <div v-if="!loadingCurrent" class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th scope="col">Tenant</th>
              <th scope="col">Room</th>
              <th scope="col">Move in</th>
              <th scope="col">Rent</th>
              <th scope="col">Status</th>
              <th scope="col"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!current.length">
              <td colspan="6" class="text-muted">No open assignments.</td>
            </tr>
            <tr v-for="assignment in current" :key="assignment.id">
              <td>{{ assignment.lease?.tenant?.full_name }}</td>
              <td>{{ assignment.room?.property?.name }} · {{ assignment.room?.room_number }}</td>
              <td>{{ formatDate(assignment.move_in_date) }}</td>
              <td>{{ formatMoney(assignment.lease?.monthly_rent) }}</td>
              <td><StatusBadge :status="assignment.status" /></td>
              <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-outline-primary" type="button" @click="openMoveOut(assignment)">Move out</button>
                <button class="btn btn-sm btn-outline-primary ms-1" type="button" @click="openTransfer(assignment)">Transfer</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0">Assignment history</h2>
        <select v-model="historyStatus" class="form-select form-select-sm w-auto" @change="loadHistory(1)">
          <option value="">All</option>
          <option value="active">Active</option>
          <option value="reserved">Reserved</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <LoadingBlock :loading="loadingHistory" />
      <div v-if="!loadingHistory" class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th scope="col">Tenant</th>
              <th scope="col">Room</th>
              <th scope="col">Move in</th>
              <th scope="col">Move out</th>
              <th scope="col">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!history.length">
              <td colspan="5" class="text-muted">No assignment records.</td>
            </tr>
            <tr v-for="assignment in history" :key="assignment.id">
              <td>{{ assignment.lease?.tenant?.full_name }}</td>
              <td>{{ assignment.room?.property?.name }} · {{ assignment.room?.room_number }}</td>
              <td>{{ formatDate(assignment.move_in_date) }}</td>
              <td>{{ formatDate(assignment.move_out_date) }}</td>
              <td><StatusBadge :status="assignment.status" /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <PaginationBar :page="historyMeta.page" :last-page="historyMeta.lastPage" :total="historyMeta.total" @change="loadHistory" />

    <ModalShell :show="moveOut.show" title="Record move-out" @cancel="moveOut.show = false">
      <div class="mb-3">
        <label class="form-label req" for="move-out-date">Move-out date</label>
        <input id="move-out-date" v-model="moveOut.date" type="date" class="form-control" />
      </div>
      <div class="form-check">
        <input id="terminate" v-model="moveOut.terminate" class="form-check-input" type="checkbox" />
        <label class="form-check-label" for="terminate">Terminate the lease. Leave this unchecked only if the tenant will be assigned again on the same lease.</label>
      </div>
      <template #footer>
        <button class="btn btn-outline-secondary" type="button" @click="moveOut.show = false">Cancel</button>
        <button class="btn btn-danger" type="button" :disabled="moveOut.busy" @click="submitMoveOut">Confirm move-out</button>
      </template>
    </ModalShell>

    <ModalShell :show="transfer.show" title="Move to another room" wide @cancel="transfer.show = false">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label req" for="new-room">New room</label>
          <select id="new-room" v-model="transfer.room_id" class="form-select">
            <option value="">Select room</option>
            <option v-for="room in transferRooms" :key="room.id" :value="room.id">
              {{ room.property?.name }} · {{ room.room_number }} ({{ room.available_spaces }} open)
            </option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label req" for="transfer-out">Move-out</label>
          <input id="transfer-out" v-model="transfer.move_out_date" type="date" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label req" for="transfer-start">New start</label>
          <input id="transfer-start" v-model="transfer.start_date" type="date" class="form-control" />
        </div>
        <div class="col-md-4">
          <label class="form-label req" for="transfer-rent">Agreed rent</label>
          <input id="transfer-rent" v-model="transfer.monthly_rent" type="number" min="0" step="0.01" class="form-control" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="transfer-deposit">Deposit</label>
          <input id="transfer-deposit" v-model="transfer.deposit_amount" type="number" min="0" step="0.01" class="form-control" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="transfer-end">End date</label>
          <input id="transfer-end" v-model="transfer.end_date" type="date" class="form-control" />
        </div>
      </div>
      <template #footer>
        <button class="btn btn-outline-secondary" type="button" @click="transfer.show = false">Cancel</button>
        <button class="btn btn-primary" type="button" :disabled="transfer.busy" @click="submitTransfer">Move tenant</button>
      </template>
    </ModalShell>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { assignmentsApi, leasesApi, roomsApi, tenantsApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import FieldError from '@/components/FieldError.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import ModalShell from '@/components/ModalShell.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { errorMessage, fieldErrors } from '@/utils/errors'
import { emptyToNull, formatDate, formatMoney, readPage, todayISO } from '@/utils/format'

const banner = ref({ type: 'success', message: '' })
const errors = ref({})
const saving = ref(false)
const tenants = ref([])
const rooms = ref([])
const allRooms = ref([])
const current = ref([])
const history = ref([])
const loadingCurrent = ref(false)
const loadingHistory = ref(false)
const historyStatus = ref('')
const historyMeta = ref({ page: 1, lastPage: 1, total: 0 })
const form = reactive({
  tenant_id: '',
  room_id: '',
  start_date: todayISO(),
  end_date: '',
  monthly_rent: '',
  deposit_amount: 0,
  terms: '',
  reserve: false,
})
const moveOut = reactive({ show: false, busy: false, id: null, date: todayISO(), terminate: true })
const transfer = reactive({
  show: false,
  busy: false,
  id: null,
  currentRoomId: null,
  room_id: '',
  move_out_date: todayISO(),
  start_date: todayISO(),
  end_date: '',
  monthly_rent: '',
  deposit_amount: 0,
})

const openTenants = computed(() => tenants.value.filter((tenant) => !(tenant.leases || []).length))
const transferRooms = computed(() => allRooms.value.filter((room) => room.id !== transfer.currentRoomId && room.available_spaces > 0 && room.status === 'active'))

function applyRoomRate() {
  const room = rooms.value.find((item) => item.id === Number(form.room_id))
  if (room) {
    form.monthly_rent = room.monthly_rate
  }
}

async function loadOptions() {
  const [tenantResponse, roomResponse, everyRoom] = await Promise.all([
    tenantsApi.list({ all: 1 }),
    roomsApi.list({ all: 1, available: 1 }),
    roomsApi.list({ all: 1, status: 'active' }),
  ])
  tenants.value = tenantResponse.data.data
  rooms.value = roomResponse.data.data
  allRooms.value = everyRoom.data.data
}

async function loadCurrent() {
  loadingCurrent.value = true
  try {
    const response = await assignmentsApi.list({ all: 1, status: 'active' })
    const reserved = await assignmentsApi.list({ all: 1, status: 'reserved' })
    current.value = [...response.data.data, ...reserved.data.data]
  } finally {
    loadingCurrent.value = false
  }
}

async function loadHistory(page = 1) {
  loadingHistory.value = true
  try {
    const response = await assignmentsApi.list({
      page,
      status: historyStatus.value || undefined,
    })
    const result = readPage(response.data)
    history.value = result.items
    historyMeta.value = result
  } finally {
    loadingHistory.value = false
  }
}

async function refresh() {
  await Promise.all([loadOptions(), loadCurrent(), loadHistory(historyMeta.value.page || 1)])
}

async function allocate() {
  saving.value = true
  errors.value = {}
  try {
    await leasesApi.create({
      tenant_id: form.tenant_id,
      room_id: form.room_id,
      start_date: form.start_date,
      end_date: emptyToNull(form.end_date),
      monthly_rent: form.monthly_rent,
      deposit_amount: form.deposit_amount || 0,
      terms: emptyToNull(form.terms),
      status: 'active',
      move_in_date: form.start_date,
      assignment_status: form.reserve ? 'reserved' : 'active',
    })
    banner.value = { type: 'success', message: 'Tenant assigned. The agreed rent was saved on the lease.' }
    form.tenant_id = ''
    form.room_id = ''
    form.end_date = ''
    form.terms = ''
    form.reserve = false
    await refresh()
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    saving.value = false
  }
}

function openMoveOut(assignment) {
  moveOut.id = assignment.id
  moveOut.date = todayISO()
  moveOut.terminate = true
  moveOut.show = true
}

function openTransfer(assignment) {
  transfer.id = assignment.id
  transfer.currentRoomId = assignment.room_id
  transfer.room_id = ''
  transfer.move_out_date = todayISO()
  transfer.start_date = todayISO()
  transfer.end_date = ''
  transfer.monthly_rent = assignment.lease?.monthly_rent || ''
  transfer.deposit_amount = 0
  transfer.show = true
}

async function submitMoveOut() {
  moveOut.busy = true
  try {
    await assignmentsApi.moveOut(moveOut.id, {
      move_out_date: moveOut.date,
      terminate_lease: moveOut.terminate,
    })
    moveOut.show = false
    banner.value = { type: 'success', message: 'Move-out recorded. The assignment was kept in history.' }
    await refresh()
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    moveOut.busy = false
  }
}

async function submitTransfer() {
  transfer.busy = true
  try {
    await assignmentsApi.transfer(transfer.id, {
      room_id: transfer.room_id,
      move_out_date: transfer.move_out_date,
      start_date: transfer.start_date,
      end_date: emptyToNull(transfer.end_date),
      monthly_rent: transfer.monthly_rent,
      deposit_amount: transfer.deposit_amount || 0,
    })
    transfer.show = false
    banner.value = { type: 'success', message: 'Tenant moved. A new lease stores the agreed rent for the new room.' }
    await refresh()
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    transfer.busy = false
  }
}

onMounted(async () => {
  try {
    await refresh()
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  }
})
</script>
