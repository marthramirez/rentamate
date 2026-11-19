<template>
  <div>
    <PageHeader title="Rooms" subtitle="Availability is calculated from active stays and reservations.">
      <RouterLink class="btn btn-primary" :to="{ name: 'room-create' }">Add room</RouterLink>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <form class="panel p-3 mb-3" @submit.prevent="applyFilters">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label" for="room-property">Property</label>
          <select id="room-property" v-model="filters.property_id" class="form-select">
            <option value="">All</option>
            <option v-for="property in properties" :key="property.id" :value="property.id">{{ property.name }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="room-status">Status</label>
          <select id="room-status" v-model="filters.status" class="form-select">
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="maintenance">Maintenance</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="room-search">Search</label>
          <input id="room-search" v-model="filters.search" class="form-control" placeholder="Room number" />
        </div>
        <div class="col-md-auto">
          <button class="btn btn-outline-primary" type="submit">Apply</button>
        </div>
      </div>
    </form>
    <div class="panel">
      <LoadingBlock :loading="loading" />
      <div v-if="!loading" class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th scope="col">Room</th>
              <th scope="col">Property</th>
              <th scope="col">Capacity</th>
              <th scope="col">Occupied</th>
              <th scope="col">Reserved</th>
              <th scope="col">Available</th>
              <th scope="col">Monthly rate</th>
              <th scope="col">Status</th>
              <th scope="col"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!items.length">
              <td colspan="9" class="text-muted">No rooms found.</td>
            </tr>
            <tr v-for="room in items" :key="room.id">
              <td>{{ room.room_number }}</td>
              <td>{{ room.property?.name || '—' }}</td>
              <td>{{ room.capacity }}</td>
              <td>{{ room.active_occupancy || 0 }}</td>
              <td>{{ room.reserved_count || 0 }}</td>
              <td>{{ room.available_spaces }}</td>
              <td>{{ formatMoney(room.monthly_rate) }}</td>
              <td><StatusBadge :status="room.status" /></td>
              <td class="text-end text-nowrap">
                <RouterLink class="btn btn-sm btn-outline-primary" :to="{ name: 'room-detail', params: { id: room.id } }">View</RouterLink>
                <RouterLink class="btn btn-sm btn-outline-primary ms-1" :to="{ name: 'room-edit', params: { id: room.id } }">Edit</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <PaginationBar :page="meta.page" :last-page="meta.lastPage" :total="meta.total" @change="changePage" />
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { propertiesApi, roomsApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { errorMessage, pullFlash } from '@/utils/errors'
import { formatMoney, readPage } from '@/utils/format'

const items = ref([])
const properties = ref([])
const loading = ref(false)
const page = ref(1)
const meta = ref({ page: 1, lastPage: 1, total: 0 })
const filters = reactive({ property_id: '', status: '', search: '' })
const banner = ref({ type: 'success', message: pullFlash() || '' })

async function load() {
  loading.value = true
  try {
    const response = await roomsApi.list({
      page: page.value,
      property_id: filters.property_id || undefined,
      status: filters.status || undefined,
      search: filters.search || undefined,
    })
    const result = readPage(response.data)
    items.value = result.items
    meta.value = result
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  page.value = 1
  load()
}

function changePage(next) {
  page.value = next
  load()
}

onMounted(async () => {
  try {
    const response = await propertiesApi.list({ all: 1 })
    properties.value = response.data.data
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  }
  await load()
})
</script>
