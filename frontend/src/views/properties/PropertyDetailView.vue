<template>
  <div>
    <PageHeader :title="property ? property.name : 'Property'" subtitle="Rooms stay with the property. Deactivate it when it should no longer take new stays.">
      <RouterLink class="btn btn-outline-primary" :to="{ name: 'properties' }">Back</RouterLink>
      <RouterLink v-if="property" class="btn btn-outline-primary" :to="{ name: 'property-edit', params: { id: property.id } }">Edit</RouterLink>
      <button v-if="property" class="btn btn-outline-danger" type="button" @click="askRemove">{{ property.rooms_count ? 'Deactivate' : 'Delete' }}</button>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <LoadingBlock :loading="loading" />
    <div v-if="property" class="row g-3">
      <div class="col-lg-4">
        <div class="panel p-3">
          <dl class="mb-0">
            <dt>Address</dt>
            <dd>{{ property.address }}</dd>
            <dt>Contact</dt>
            <dd>{{ property.contact_number || '—' }}</dd>
            <dt>Status</dt>
            <dd><StatusBadge :status="property.status" /></dd>
            <dt>Description</dt>
            <dd class="mb-0">{{ property.description || '—' }}</dd>
          </dl>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="panel">
          <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
            <h2 class="h6 mb-0">Rooms</h2>
            <RouterLink class="btn btn-sm btn-primary" :to="{ name: 'room-create', query: { property: property.id } }">Add room</RouterLink>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th scope="col">Room</th>
                  <th scope="col">Capacity</th>
                  <th scope="col">Occupied</th>
                  <th scope="col">Available</th>
                  <th scope="col">Rate</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!(property.rooms || []).length">
                  <td colspan="6" class="text-muted">No rooms yet.</td>
                </tr>
                <tr v-for="room in property.rooms" :key="room.id">
                  <td>
                    <RouterLink :to="{ name: 'room-detail', params: { id: room.id } }">{{ room.room_number }}</RouterLink>
                    <div v-if="room.floor" class="small text-muted">Floor {{ room.floor }}</div>
                  </td>
                  <td>{{ room.capacity }}</td>
                  <td>{{ room.active_occupancy || 0 }}</td>
                  <td>{{ room.available_spaces }}</td>
                  <td>{{ formatMoney(room.monthly_rate) }}</td>
                  <td><StatusBadge :status="room.status" /></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <ModalShell :show="dialog.show" title="Remove property" @cancel="dialog.show = false">
      <p class="mb-0">{{ dialog.message }}</p>
      <template #footer>
        <button class="btn btn-outline-secondary" type="button" @click="dialog.show = false">Cancel</button>
        <button class="btn btn-danger" type="button" :disabled="dialog.busy" @click="confirmRemove">Continue</button>
      </template>
    </ModalShell>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { propertiesApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import ModalShell from '@/components/ModalShell.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { errorMessage, flash } from '@/utils/errors'
import { formatMoney } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const property = ref(null)
const loading = ref(true)
const banner = ref({ type: 'success', message: '' })
const dialog = reactive({ show: false, busy: false, message: '' })

async function load() {
  loading.value = true
  try {
    const response = await propertiesApi.get(route.params.id)
    property.value = response.data.data
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    loading.value = false
  }
}

function askRemove() {
  dialog.message = property.value.rooms_count
    ? 'This property has rooms, so it will be deactivated instead of deleted.'
    : 'This property has no rooms and will be deleted.'
  dialog.show = true
}

async function confirmRemove() {
  dialog.busy = true
  try {
    const response = await propertiesApi.remove(property.value.id)
    flash(response.data.message)
    dialog.show = false
    if (response.data.deleted) {
      router.push({ name: 'properties' })
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
