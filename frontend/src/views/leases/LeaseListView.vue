<template>
  <div>
    <PageHeader title="Leases" subtitle="The monthly rent on a lease is the agreed rate and does not follow later room price changes.">
      <RouterLink class="btn btn-primary" :to="{ name: 'lease-create' }">New lease</RouterLink>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <form class="panel p-3 mb-3" @submit.prevent="applyFilters">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label" for="lease-search">Search</label>
          <input id="lease-search" v-model="filters.search" class="form-control" placeholder="Number or tenant" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="lease-status">Status</label>
          <select id="lease-status" v-model="filters.status" class="form-select">
            <option value="">All</option>
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="expired">Expired</option>
            <option value="terminated">Terminated</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="lease-property">Property</label>
          <select id="lease-property" v-model="filters.property_id" class="form-select">
            <option value="">All</option>
            <option v-for="property in properties" :key="property.id" :value="property.id">{{ property.name }}</option>
          </select>
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
              <th scope="col">Number</th>
              <th scope="col">Tenant</th>
              <th scope="col">Room</th>
              <th scope="col">Dates</th>
              <th scope="col">Rent</th>
              <th scope="col">Status</th>
              <th scope="col"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!items.length">
              <td colspan="7" class="text-muted">No leases found.</td>
            </tr>
            <tr v-for="lease in items" :key="lease.id">
              <td>{{ lease.lease_number }}</td>
              <td>{{ lease.tenant?.full_name }}</td>
              <td>{{ lease.room?.property?.name }} · {{ lease.room?.room_number }}</td>
              <td>{{ formatDate(lease.start_date) }} – {{ formatDate(lease.end_date) }}</td>
              <td>{{ formatMoney(lease.monthly_rent) }}</td>
              <td><StatusBadge :status="lease.status" /></td>
              <td class="text-end">
                <RouterLink class="btn btn-sm btn-outline-primary" :to="{ name: 'lease-detail', params: { id: lease.id } }">View</RouterLink>
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
import { leasesApi, propertiesApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { errorMessage, pullFlash } from '@/utils/errors'
import { formatDate, formatMoney, readPage } from '@/utils/format'

const items = ref([])
const properties = ref([])
const loading = ref(false)
const page = ref(1)
const meta = ref({ page: 1, lastPage: 1, total: 0 })
const filters = reactive({ search: '', status: '', property_id: '' })
const banner = ref({ type: 'success', message: pullFlash() || '' })

async function load() {
  loading.value = true
  try {
    const response = await leasesApi.list({
      page: page.value,
      search: filters.search || undefined,
      status: filters.status || undefined,
      property_id: filters.property_id || undefined,
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
