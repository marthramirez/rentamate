<template>
  <div>
    <PageHeader title="Users" subtitle="Administrators and staff who can sign in.">
      <RouterLink class="btn btn-primary" :to="{ name: 'user-create' }">Add user</RouterLink>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <form class="panel p-3 mb-3" @submit.prevent="applyFilters">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label" for="user-search">Search</label>
          <input id="user-search" v-model="filters.search" class="form-control" placeholder="Name or email" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="user-role">Role</label>
          <select id="user-role" v-model="filters.role" class="form-select">
            <option value="">All</option>
            <option value="admin">Administrator</option>
            <option value="staff">Staff</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="user-status">Status</label>
          <select id="user-status" v-model="filters.status" class="form-select">
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
              <th scope="col">Email</th>
              <th scope="col">Role</th>
              <th scope="col">Status</th>
              <th scope="col"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!items.length">
              <td colspan="5" class="text-muted">No users found.</td>
            </tr>
            <tr v-for="user in items" :key="user.id">
              <td>{{ user.name }}</td>
              <td>{{ user.email }}</td>
              <td>{{ roleLabel(user.role) }}</td>
              <td><StatusBadge :status="user.status" /></td>
              <td class="text-end">
                <RouterLink class="btn btn-sm btn-outline-primary" :to="{ name: 'user-edit', params: { id: user.id } }">Edit</RouterLink>
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
import { usersApi } from '@/api'
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
const filters = reactive({ search: '', role: '', status: '' })
const banner = ref({ type: 'success', message: pullFlash() || '' })

function roleLabel(role) {
  return role === 'admin' ? 'Administrator' : 'Staff'
}

async function load() {
  loading.value = true
  try {
    const response = await usersApi.list({
      page: page.value,
      search: filters.search || undefined,
      role: filters.role || undefined,
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
