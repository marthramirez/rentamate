<template>
  <div>
    <PageHeader :title="room ? `Room ${room.room_number}` : 'Room'" :subtitle="room?.property?.name || ''">
      <RouterLink class="btn btn-outline-primary" :to="{ name: 'rooms' }">Back</RouterLink>
      <RouterLink v-if="room" class="btn btn-outline-primary" :to="{ name: 'room-edit', params: { id: room.id } }">Edit</RouterLink>
      <button v-if="room" class="btn btn-outline-danger" type="button" @click="dialog.show = true">{{ hasHistory ? 'Deactivate' : 'Delete' }}</button>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <LoadingBlock :loading="loading" />
    <div v-if="room" class="row g-3">
      <div class="col-lg-4">
        <div class="panel p-3">
          <dl class="mb-0">
            <dt>Floor</dt>
            <dd>{{ room.floor || '—' }}</dd>
            <dt>Capacity</dt>
            <dd>{{ room.capacity }}</dd>
            <dt>Occupied / reserved</dt>
            <dd>{{ room.active_occupancy || 0 }} / {{ room.reserved_count || 0 }}</dd>
            <dt>Available</dt>
            <dd>{{ room.available_spaces }}</dd>
            <dt>Standard rate</dt>
            <dd>{{ formatMoney(room.monthly_rate) }}</dd>
            <dt>Status</dt>
            <dd><StatusBadge :status="room.status" /></dd>
            <dt>Description</dt>
            <dd class="mb-0">{{ room.description || '—' }}</dd>
          </dl>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="panel">
          <div class="p-3 border-bottom"><h2 class="h6 mb-0">Assignment history</h2></div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th scope="col">Tenant</th>
                  <th scope="col">Move in</th>
                  <th scope="col">Move out</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!(room.assignments || []).length">
                  <td colspan="4" class="text-muted">No assignments yet.</td>
                </tr>
                <tr v-for="assignment in room.assignments" :key="assignment.id">
                  <td>{{ assignment.lease?.tenant?.full_name || '—' }}</td>
                  <td>{{ formatDate(assignment.move_in_date) }}</td>
                  <td>{{ formatDate(assignment.move_out_date) }}</td>
                  <td><StatusBadge :status="assignment.status" /></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <ModalShell :show="dialog.show" title="Remove room" @cancel="dialog.show = false">
      <p class="mb-0">{{ hasHistory ? 'This room has history, so it will be deactivated instead of deleted.' : 'This room has no history and will be deleted.' }}</p>
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
import { roomsApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import ModalShell from '@/components/ModalShell.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { errorMessage, flash } from '@/utils/errors'
import { formatDate, formatMoney } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const room = ref(null)
const loading = ref(true)
const banner = ref({ type: 'success', message: '' })
const dialog = reactive({ show: false, busy: false })
const hasHistory = computed(() => (room.value?.assignments || []).length > 0 || (room.value?.leases_count || 0) > 0)

async function load() {
  loading.value = true
  try {
    const response = await roomsApi.get(route.params.id)
    room.value = response.data.data
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    loading.value = false
  }
}

async function confirmRemove() {
  dialog.busy = true
  try {
    const response = await roomsApi.remove(room.value.id)
    flash(response.data.message)
    dialog.show = false
    if (response.data.deleted) {
      router.push({ name: 'rooms' })
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
