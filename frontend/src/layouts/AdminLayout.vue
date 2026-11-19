<template>
  <div class="admin-shell">
    <div class="sidebar-backdrop" :class="{ show: open }" @click="open = false"></div>
    <aside class="sidebar" :class="{ open }">
      <div class="brand">
        <div class="brand-title">Boarding House</div>
        <div class="brand-sub">Management</div>
      </div>
      <nav class="side-nav" aria-label="Main">
        <RouterLink
          v-for="item in items"
          :key="item.section"
          :to="item.to"
          class="side-link"
          :class="{ active: route.meta.section === item.section }"
        >
          {{ item.label }}
        </RouterLink>
      </nav>
    </aside>
    <div class="admin-main">
      <header class="topbar">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-primary btn-sm menu-btn" type="button" @click="open = !open">Menu</button>
          <h1 class="page-title">{{ route.meta.title || 'Boarding House' }}</h1>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div class="user-chip">
            <div class="name">{{ session.user?.name }}</div>
            <div class="role">{{ session.user?.role === 'admin' ? 'Administrator' : 'Staff' }}</div>
          </div>
          <RouterLink class="btn btn-outline-primary btn-sm" :to="{ name: 'account' }">Account</RouterLink>
          <button class="btn btn-outline-primary btn-sm" type="button" @click="logout">Log out</button>
        </div>
      </header>
      <main class="content">
        <RouterView :key="route.fullPath" />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { authApi } from '@/api'
import { clearSession, session, setSession } from '@/auth/session'

const route = useRoute()
const router = useRouter()
const open = ref(false)

watch(() => route.fullPath, () => {
  open.value = false
})

const items = computed(() => {
  const links = [
    { label: 'Dashboard', to: { name: 'dashboard' }, section: 'dashboard' },
    { label: 'Properties', to: { name: 'properties' }, section: 'properties' },
    { label: 'Rooms', to: { name: 'rooms' }, section: 'rooms' },
    { label: 'Tenants', to: { name: 'tenants' }, section: 'tenants' },
    { label: 'Room Allocation', to: { name: 'allocation' }, section: 'allocation' },
    { label: 'Leases', to: { name: 'leases' }, section: 'leases' },
    { label: 'Billing & Payments', to: { name: 'billing' }, section: 'billing' },
  ]

  if (session.user?.role === 'admin') {
    links.push({ label: 'Users', to: { name: 'users' }, section: 'users' })
  }

  return links
})

async function logout() {
  try {
    await authApi.logout()
  } catch {
    // The session is cleared either way.
  }
  clearSession()
  router.push({ name: 'login' })
}

onMounted(async () => {
  if (!session.token) {
    return
  }
  try {
    const response = await authApi.me()
    setSession(session.token, response.data.data)
    if (route.matched.some((record) => record.meta.admin) && response.data.data.role !== 'admin') {
      router.replace({ name: 'dashboard' })
    }
  } catch {
    // A rejected token is cleared by the API client.
  }
})
</script>
