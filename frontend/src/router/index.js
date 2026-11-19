import { createRouter, createWebHistory } from 'vue-router'
import { session } from '@/auth/session'
import AdminLayout from '@/layouts/AdminLayout.vue'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { title: 'Sign in', public: true },
  },
  {
    path: '/',
    component: AdminLayout,
    children: [
      { path: '', name: 'dashboard', component: () => import('@/views/DashboardView.vue'), meta: { title: 'Dashboard', section: 'dashboard' } },
      { path: 'properties', name: 'properties', component: () => import('@/views/properties/PropertyListView.vue'), meta: { title: 'Properties', section: 'properties' } },
      { path: 'properties/new', name: 'property-create', component: () => import('@/views/properties/PropertyFormView.vue'), meta: { title: 'New property', section: 'properties' } },
      { path: 'properties/:id/edit', name: 'property-edit', component: () => import('@/views/properties/PropertyFormView.vue'), meta: { title: 'Edit property', section: 'properties' } },
      { path: 'properties/:id', name: 'property-detail', component: () => import('@/views/properties/PropertyDetailView.vue'), meta: { title: 'Property', section: 'properties' } },
      { path: 'rooms', name: 'rooms', component: () => import('@/views/rooms/RoomListView.vue'), meta: { title: 'Rooms', section: 'rooms' } },
      { path: 'rooms/new', name: 'room-create', component: () => import('@/views/rooms/RoomFormView.vue'), meta: { title: 'New room', section: 'rooms' } },
      { path: 'rooms/:id/edit', name: 'room-edit', component: () => import('@/views/rooms/RoomFormView.vue'), meta: { title: 'Edit room', section: 'rooms' } },
      { path: 'rooms/:id', name: 'room-detail', component: () => import('@/views/rooms/RoomDetailView.vue'), meta: { title: 'Room', section: 'rooms' } },
      { path: 'tenants', name: 'tenants', component: () => import('@/views/tenants/TenantListView.vue'), meta: { title: 'Tenants', section: 'tenants' } },
      { path: 'tenants/new', name: 'tenant-create', component: () => import('@/views/tenants/TenantFormView.vue'), meta: { title: 'New tenant', section: 'tenants' } },
      { path: 'tenants/:id/edit', name: 'tenant-edit', component: () => import('@/views/tenants/TenantFormView.vue'), meta: { title: 'Edit tenant', section: 'tenants' } },
      { path: 'tenants/:id', name: 'tenant-detail', component: () => import('@/views/tenants/TenantDetailView.vue'), meta: { title: 'Tenant', section: 'tenants' } },
      { path: 'allocation', name: 'allocation', component: () => import('@/views/allocation/AllocationView.vue'), meta: { title: 'Room Allocation', section: 'allocation' } },
      { path: 'leases', name: 'leases', component: () => import('@/views/leases/LeaseListView.vue'), meta: { title: 'Leases', section: 'leases' } },
      { path: 'leases/new', name: 'lease-create', component: () => import('@/views/leases/LeaseFormView.vue'), meta: { title: 'New lease', section: 'leases' } },
      { path: 'leases/:id/edit', name: 'lease-edit', component: () => import('@/views/leases/LeaseFormView.vue'), meta: { title: 'Edit lease', section: 'leases' } },
      { path: 'leases/:id', name: 'lease-detail', component: () => import('@/views/leases/LeaseDetailView.vue'), meta: { title: 'Lease', section: 'leases' } },
      { path: 'billing', name: 'billing', component: () => import('@/views/billing/BillingListView.vue'), meta: { title: 'Billing & Payments', section: 'billing' } },
      { path: 'billing/new', name: 'billing-create', component: () => import('@/views/billing/BillingFormView.vue'), meta: { title: 'New bill', section: 'billing' } },
      { path: 'billing/:id', name: 'billing-detail', component: () => import('@/views/billing/BillingDetailView.vue'), meta: { title: 'Bill', section: 'billing' } },
      { path: 'users', name: 'users', component: () => import('@/views/users/UserListView.vue'), meta: { title: 'Users', section: 'users', admin: true } },
      { path: 'users/new', name: 'user-create', component: () => import('@/views/users/UserFormView.vue'), meta: { title: 'New user', section: 'users', admin: true } },
      { path: 'users/:id/edit', name: 'user-edit', component: () => import('@/views/users/UserFormView.vue'), meta: { title: 'Edit user', section: 'users', admin: true } },
      { path: 'account', name: 'account', component: () => import('@/views/AccountView.vue'), meta: { title: 'Account', section: 'account' } },
      { path: ':pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue'), meta: { title: 'Not found', section: '' } },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

router.beforeEach((to) => {
  const signedIn = session.token !== ''

  if (to.name === 'login') {
    return signedIn ? { name: 'dashboard' } : true
  }

  if (!signedIn) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.matched.some((record) => record.meta.admin) && session.user?.role !== 'admin') {
    return { name: 'dashboard' }
  }

  return true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Boarding House` : 'Boarding House'
})

export default router
