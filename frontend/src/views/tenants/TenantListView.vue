<template>
  <div>
    <PageHeader title="Tenants" subtitle="Search by name, contact number, or email.">
      <RouterLink class="btn btn-primary" :to="{ name: 'tenant-create' }">Add tenant</RouterLink>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <form class="panel p-3 mb-3" @submit.prevent="applyFilters">
      <div class="row g-2 align-items-end">
        <div class="col-md-5">
          <label class="form-label" for="tenant-search">Search</label>
          <input id="tenant-search" v-model="filters.search" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="tenant-status">Status</label>
          <select id="tenant-status" v-model="filters.status" class="form-select">
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
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
              <th scope="col">Name</th>
              <th scope="col">Contact</th>
              <th scope="col">Email</th>
              <th scope="col">Current room</th>
              <th scope="col">Status</th>
              <th scope="col"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!items.length">
              <td colspan="6" class="text-muted">No tenants found.</td>
            </tr>
            <tr v-for="tenant in items" :key="tenant.id">
              <td>{{ tenant.full_name }}</td>
              <td>{{ tenant.contact_number }}</td>
              <td>{{ tenant.email || '—' }}</td>
              <td>{{ currentRoom(tenant) }}</td>
              <td><StatusBadge :status="tenant.status" /></td>
              <td class="text-end text-nowrap">
                <RouterLink class="btn btn-sm btn-outline-primary" :to="{ name: 'tenant-detail', params: { id: tenant.id } }">View</RouterLink>
                <RouterLink class="btn btn-sm btn-outline-primary ms-1" :to="{ name: 'tenant-edit', params: { id: tenant.id } }">Edit</RouterLink>
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
import { tenantsApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { errorMessage, pullFlash } from '@/utils/errors'
import { readPage } from '@/utils/format'

const items = ref([])
const loading = ref(false)
const page = ref(1)
const meta = ref({ page: 1, lastPage: 1, total: 0 })
const filters = reactive({ search: '', status: '' })
const banner = ref({ type: 'success', message: pullFlash() || '' })

function currentRoom(tenant) {
  const lease = (tenant.leases || [])[0]
  if (!lease?.room) {
    return '—'
  }
  return `${lease.room.property?.name || 'Property'} · ${lease.room.room_number}`
}

async function load() {
  loading.value = true
  try {
    const response = await tenantsApi.list({
      page: page.value,
      search: filters.search || undefined,
      status: filters.status || undefined,
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

onMounted(load)
</script>
