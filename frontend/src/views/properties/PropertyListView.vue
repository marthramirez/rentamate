<template>
  <div>
    <PageHeader title="Properties" subtitle="Boarding houses managed in this system.">
      <RouterLink class="btn btn-primary" :to="{ name: 'property-create' }">Add property</RouterLink>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <form class="panel p-3 mb-3" @submit.prevent="applyFilters">
      <div class="row g-2 align-items-end">
        <div class="col-md-5">
          <label class="form-label" for="property-search">Search</label>
          <input id="property-search" v-model="filters.search" class="form-control" placeholder="Name, address, or contact" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="property-status">Status</label>
          <select id="property-status" v-model="filters.status" class="form-select">
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
              <th scope="col">Address</th>
              <th scope="col">Contact</th>
              <th scope="col">Rooms</th>
              <th scope="col">Status</th>
              <th scope="col"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!items.length">
              <td colspan="6" class="text-muted">No properties found.</td>
            </tr>
            <tr v-for="property in items" :key="property.id">
              <td>{{ property.name }}</td>
              <td>{{ property.address }}</td>
              <td>{{ property.contact_number || '—' }}</td>
              <td>{{ property.rooms_count }}</td>
              <td><StatusBadge :status="property.status" /></td>
              <td class="text-end text-nowrap">
                <RouterLink class="btn btn-sm btn-outline-primary" :to="{ name: 'property-detail', params: { id: property.id } }">View</RouterLink>
                <RouterLink class="btn btn-sm btn-outline-primary ms-1" :to="{ name: 'property-edit', params: { id: property.id } }">Edit</RouterLink>
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
import { propertiesApi } from '@/api'
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

async function load() {
  loading.value = true
  try {
    const response = await propertiesApi.list({
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
